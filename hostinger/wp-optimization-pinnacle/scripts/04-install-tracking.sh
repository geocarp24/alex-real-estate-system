#!/usr/bin/env bash
# 04-install-tracking.sh
# Installs the Pinnacle Tracking MU-plugin (Meta Pixel + Microsoft Clarity).
# Reads tracking IDs from environment and writes them to wp-config.php.
#
# Usage:
#   PINNACLE_META_PIXEL_ID=1234567890 PINNACLE_CLARITY_ID=abcdefghij ./04-install-tracking.sh
#
# If the IDs are not set, the MU-plugin is still installed but tracks nothing
# (snippets are skipped at runtime).

set -euo pipefail

WP_PATH="${WP_PATH:-$HOME/domains/pinnaclegroupwi.com/public_html}"
SRC_PLUGIN="$(dirname "$0")/../mu-plugins/pinnacle-tracking.php"
DEST_DIR="$WP_PATH/wp-content/mu-plugins"

mkdir -p "$DEST_DIR"
cp -f "$SRC_PLUGIN" "$DEST_DIR/pinnacle-tracking.php"
echo "[1/2] Installed mu-plugin: $DEST_DIR/pinnacle-tracking.php"

WP_CONFIG="$WP_PATH/wp-config.php"
if [ ! -f "$WP_CONFIG" ]; then
  echo "ERROR: wp-config.php not found at $WP_CONFIG"
  exit 1
fi

write_const () {
  local name="$1" value="$2"
  if grep -q "define( *['\"]${name}['\"]" "$WP_CONFIG"; then
    sed -i.bak -E "s|define\( *['\"]${name}['\"][^)]*\)|define('${name}', '${value}')|" "$WP_CONFIG"
    echo "    updated ${name}"
  else
    sed -i.bak "/\/\* That's all, stop editing/i define('${name}', '${value}');" "$WP_CONFIG"
    echo "    inserted ${name}"
  fi
}

echo "[2/2] Writing tracking IDs to wp-config.php..."
if [ -n "${PINNACLE_META_PIXEL_ID:-}" ]; then
  write_const "PINNACLE_META_PIXEL_ID" "$PINNACLE_META_PIXEL_ID"
else
  echo "    PINNACLE_META_PIXEL_ID not set — Meta Pixel will be inactive"
fi
if [ -n "${PINNACLE_CLARITY_ID:-}" ]; then
  write_const "PINNACLE_CLARITY_ID" "$PINNACLE_CLARITY_ID"
else
  echo "    PINNACLE_CLARITY_ID not set — Clarity will be inactive"
fi

echo
echo "OK. Verify with: curl -s https://pinnaclegroupwi.com/ | grep -E 'fbq|clarity'"
