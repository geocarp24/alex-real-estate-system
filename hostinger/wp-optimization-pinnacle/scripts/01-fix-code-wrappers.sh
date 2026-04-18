#!/usr/bin/env bash
# 01-fix-code-wrappers.sh
# Strips spurious <code>...</code> tags from page content on pages that should
# render normal prose. Affects: home, services, about-us.
# Reversible via the snapshot saved by 00-backup.sh.

# Note: we intentionally do NOT use `set -e` because grep pipelines return
# exit 1 on zero matches, which with pipefail would abort prematurely.
set -uo pipefail

WP_PATH="${WP_PATH:-$HOME/domains/pinnaclegroupwi.com/public_html}"
cd "$WP_PATH"

count_code_tags() {
  # Counts occurrences of <code>...</code> in stdin. Always exits 0.
  local n
  n=$(printf '%s' "$1" | grep -oE '<code>[^<]+</code>' 2>/dev/null | wc -l | tr -d ' ')
  echo "${n:-0}"
}

declare -a SLUGS=("" "services" "about-us")

for slug in "${SLUGS[@]}"; do
  if [ -z "$slug" ]; then
    pid=$(wp option get page_on_front 2>/dev/null)
    label="home"
  else
    pid=$(wp post list --post_type=page --name="$slug" --field=ID 2>/dev/null | head -1)
    label="$slug"
  fi

  if [ -z "${pid:-}" ] || [ "$pid" = "0" ]; then
    echo "skip: $label (page not found)"
    continue
  fi

  current=$(wp post get "$pid" --field=post_content 2>/dev/null) || {
    echo "WARN: could not read post_content for $label (id=$pid)"
    continue
  }

  before_count=$(count_code_tags "$current")
  if [ "$before_count" = "0" ]; then
    echo "skip: $label (id=$pid) — no <code> wrappers found"
    continue
  fi

  cleaned=$(printf '%s' "$current" | perl -0777 -pe 's{<code>(.*?)</code>}{$1}gs')
  after_count=$(count_code_tags "$cleaned")

  tmpfile=$(mktemp)
  printf '%s' "$cleaned" > "$tmpfile"
  if wp post update "$pid" --post_content=- < "$tmpfile" >/dev/null 2>&1; then
    echo "fixed: $label (id=$pid) — stripped $before_count <code> wrappers (remaining: $after_count)"
  else
    echo "ERROR: wp post update failed for $label (id=$pid)"
  fi
  rm -f "$tmpfile"
done

echo
echo "OK. Visit https://pinnaclegroupwi.com/ in incognito to verify rendering."
exit 0
