#!/usr/bin/env bash
# 00-backup.sh — ALWAYS RUN FIRST
# Creates a timestamped backup of WordPress DB + key files.
# Idempotent and safe to run multiple times.

set -euo pipefail

WP_PATH="${WP_PATH:-$HOME/domains/pinnaclegroupwi.com/public_html}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/alex_backups/wp-optimization}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_PATH="$BACKUP_DIR/$TIMESTAMP"

mkdir -p "$BACKUP_PATH"
cd "$WP_PATH"

echo "[1/3] Database dump..."
wp db export "$BACKUP_PATH/db.sql" --add-drop-table

echo "[2/3] Snapshot of pages 1373 (home), Services, About-Us, FAQ, Contact..."
for slug in "" services about-us faq contact; do
  if [ -z "$slug" ]; then
    pid=$(wp option get page_on_front)
    fname="home_${pid}.html"
  else
    pid=$(wp post list --post_type=page --name="$slug" --field=ID 2>/dev/null | head -1)
    fname="${slug}_${pid}.html"
  fi
  if [ -n "$pid" ]; then
    wp post get "$pid" --field=post_content > "$BACKUP_PATH/$fname"
    echo "    saved $fname (post_id=$pid)"
  fi
done

echo "[3/3] Snapshot of astra-settings option..."
wp option get astra-settings --format=json > "$BACKUP_PATH/astra-settings.json"

echo
echo "OK. Backup at: $BACKUP_PATH"
echo "To rollback DB: wp db import $BACKUP_PATH/db.sql"
