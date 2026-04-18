#!/usr/bin/env bash
# 99-rollback.sh — EMERGENCY RESTORE
# Restores pages from the OLDEST backup (pristine pre-optimization state).
# Also restores astra-settings.json.

set -uo pipefail

WP_PATH="${WP_PATH:-$HOME/domains/pinnaclegroupwi.com/public_html}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/alex_backups/wp-optimization-pinnacle}"

if [ ! -d "$BACKUP_DIR" ]; then
  echo "ERROR: backup dir not found: $BACKUP_DIR"
  exit 1
fi

# Pick the OLDEST backup (first one ever taken = pristine state)
OLDEST=$(ls -1d "$BACKUP_DIR"/*/ 2>/dev/null | sort | head -1)
if [ -z "$OLDEST" ]; then
  echo "ERROR: no backup snapshots found in $BACKUP_DIR"
  exit 1
fi
OLDEST="${OLDEST%/}"

echo "=== EMERGENCY ROLLBACK ==="
echo "Using OLDEST backup: $OLDEST"
echo "Contents:"
ls -la "$OLDEST/" | head -20
echo

cd "$WP_PATH"

echo "[1/2] Restoring page post_content..."
shopt -s nullglob
for f in "$OLDEST"/*.html; do
  bname=$(basename "$f")
  # filename format: <slug>_<pid>.html  (or  home_<pid>.html)
  pid=$(echo "$bname" | grep -oE '[0-9]+' | tail -1)
  label=$(echo "$bname" | sed -E 's/_[0-9]+\.html$//')
  if [ -z "${pid:-}" ] || [ "$pid" = "0" ]; then
    echo "  skip: $bname (no pid parsed)"
    continue
  fi
  size=$(wc -c < "$f")
  if [ "$size" -lt 100 ]; then
    echo "  skip: $bname (backup too small: ${size}B — possibly corrupt)"
    continue
  fi
  if wp post update "$pid" --post_content=- < "$f" >/dev/null 2>&1; then
    echo "  restored: $label (id=$pid) <- $bname (${size}B)"
  else
    echo "  ERROR: failed to restore $label (id=$pid)"
  fi
done

echo "[2/2] Restoring astra-settings..."
if [ -f "$OLDEST/astra-settings.json" ]; then
  size=$(wc -c < "$OLDEST/astra-settings.json")
  if [ "$size" -gt 10 ]; then
    if wp option update astra-settings "$(cat "$OLDEST/astra-settings.json")" --format=json >/dev/null 2>&1; then
      echo "  restored: astra-settings.json (${size}B)"
    else
      echo "  ERROR: wp option update astra-settings failed"
    fi
  else
    echo "  skip: astra-settings.json too small (${size}B)"
  fi
else
  echo "  skip: astra-settings.json not in backup"
fi

echo
echo "Purging cache..."
wp litespeed-purge all >/dev/null 2>&1 && echo "  LiteSpeed purged" \
  || wp cache flush >/dev/null 2>&1 && echo "  WP cache flushed" \
  || echo "  no cache plugin"

echo
echo "=== ROLLBACK COMPLETE ==="
exit 0
