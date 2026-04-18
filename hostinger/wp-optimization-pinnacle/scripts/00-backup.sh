#!/usr/bin/env bash
# 00-backup.sh — ALWAYS RUN FIRST
# Creates a timestamped backup of the pages and theme settings we are about
# to modify. The actual rollback mechanism is per-page post_content and the
# astra-settings JSON — a full DB dump is optional (Hostinger already runs
# daily backups at the hosting level).

set -uo pipefail

WP_PATH="${WP_PATH:-$HOME/domains/pinnaclegroupwi.com/public_html}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/alex_backups/wp-optimization-pinnacle}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_PATH="$BACKUP_DIR/$TIMESTAMP"

mkdir -p "$BACKUP_PATH"
cd "$WP_PATH"

echo "[1/3] Database dump (optional — continues on failure)..."
if wp db export "$BACKUP_PATH/db.sql" --add-drop-table 2>"$BACKUP_PATH/db_export.err"; then
  echo "    saved db.sql ($(wc -c < "$BACKUP_PATH/db.sql") bytes)"
  rm -f "$BACKUP_PATH/db_export.err"
else
  echo "    WARN: wp db export failed ($(cat "$BACKUP_PATH/db_export.err" | head -1))"
  echo "    proceeding — page-level snapshots below are the real rollback"
  rm -f "$BACKUP_PATH/db.sql"
fi

echo "[2/3] Snapshot of pages (home, services, about-us, faq, contact)..."
for slug in "" services about-us faq contact; do
  if [ -z "$slug" ]; then
    pid=$(wp option get page_on_front 2>/dev/null)
    fname="home_${pid}.html"
  else
    pid=$(wp post list --post_type=page --name="$slug" --field=ID 2>/dev/null | head -1)
    fname="${slug}_${pid}.html"
  fi
  if [ -n "${pid:-}" ] && [ "$pid" != "0" ]; then
    if wp post get "$pid" --field=post_content > "$BACKUP_PATH/$fname" 2>/dev/null; then
      echo "    saved $fname (post_id=$pid)"
    else
      echo "    WARN: could not snapshot $slug (id=$pid)"
      rm -f "$BACKUP_PATH/$fname"
    fi
  else
    echo "    skip: $slug (page not found)"
  fi
done

echo "[3/3] Snapshot of astra-settings option..."
if wp option get astra-settings --format=json > "$BACKUP_PATH/astra-settings.json" 2>/dev/null; then
  echo "    saved astra-settings.json ($(wc -c < "$BACKUP_PATH/astra-settings.json") bytes)"
else
  echo "    WARN: could not snapshot astra-settings"
  rm -f "$BACKUP_PATH/astra-settings.json"
fi

echo
echo "OK. Backup at: $BACKUP_PATH"
