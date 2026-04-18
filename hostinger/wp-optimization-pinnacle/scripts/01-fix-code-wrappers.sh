#!/usr/bin/env bash
# 01-fix-code-wrappers.sh
# Strips spurious <code>...</code> tags from page content on pages that should
# render normal prose. Affects: home (1373), services, about-us.
# Reversible via the snapshot saved by 00-backup.sh.

set -euo pipefail

WP_PATH="${WP_PATH:-$HOME/domains/pinnaclegroupwi.com/public_html}"
cd "$WP_PATH"

declare -a SLUGS=("" "services" "about-us")

for slug in "${SLUGS[@]}"; do
  if [ -z "$slug" ]; then
    pid=$(wp option get page_on_front)
    label="home"
  else
    pid=$(wp post list --post_type=page --name="$slug" --field=ID 2>/dev/null | head -1)
    label="$slug"
  fi

  if [ -z "$pid" ]; then
    echo "skip: $label (page not found)"
    continue
  fi

  current="$(wp post get "$pid" --field=post_content)"
  before_count=$(printf '%s' "$current" | grep -oE '<code>[^<]+</code>' | wc -l | tr -d ' ')

  if [ "$before_count" = "0" ]; then
    echo "skip: $label (id=$pid) — no <code> wrappers found"
    continue
  fi

  cleaned="$(printf '%s' "$current" | perl -0777 -pe 's{<code>(.*?)</code>}{$1}gs')"
  after_count=$(printf '%s' "$cleaned" | grep -oE '<code>[^<]+</code>' | wc -l | tr -d ' ')

  tmpfile="$(mktemp)"
  printf '%s' "$cleaned" > "$tmpfile"
  wp post update "$pid" --post_content=- < "$tmpfile"
  rm -f "$tmpfile"

  echo "fixed: $label (id=$pid) — stripped $before_count <code> wrappers (remaining: $after_count)"
done

echo
echo "OK. Visit https://pinnaclegroupwi.com/ in incognito to verify rendering."
