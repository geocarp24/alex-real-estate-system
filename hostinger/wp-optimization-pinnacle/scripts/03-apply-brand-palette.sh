#!/usr/bin/env bash
# 03-apply-brand-palette.sh
# Applies Pinnacle Holdings brand palette to Astra theme global colors.
#
#   Primary  (color-0): #0D3B2E  dark green (money green)
#   Secondary(color-1): #C9A84C  gold accent
#   Body text(color-2): #0D3B2E  dark green
#   Headings (color-3): #0D3B2E  dark green
#   Light bg (color-4): #FFFFFF  white
#   Subtle bg(color-5): #F5F5F0  warm off-white
#   Alt bg   (color-6): #F5F0E8  cream
#   Footer bg(color-7): #1A1A1A  soft black
#   Footer fg(color-8): #FFFFFF  white
#
# Astra reads these via global-color-palette key in the astra-settings option.
# Reversible: 00-backup.sh saves astra-settings.json before changes.

set -uo pipefail

WP_PATH="${WP_PATH:-$HOME/domains/pinnaclegroupwi.com/public_html}"
cd "$WP_PATH"

PALETTE='{"palette":["#0D3B2E","#C9A84C","#0D3B2E","#0D3B2E","#FFFFFF","#F5F5F0","#F5F0E8","#1A1A1A","#FFFFFF"],"title":"Pinnacle Holdings"}'

run_patch() {
  # run_patch KEY VALUE [--format=json]
  local key="$1" value="$2" fmt="${3:-}"
  if [ -n "$fmt" ]; then
    wp option patch update astra-settings "$key" "$value" "$fmt" 2>&1 \
      && echo "    patched $key" \
      || echo "    WARN: patch $key failed (continuing)"
  else
    wp option patch update astra-settings "$key" "$value" 2>&1 \
      && echo "    patched $key" \
      || echo "    WARN: patch $key failed (continuing)"
  fi
}

echo "[1/4] Updating Astra global color palette..."
run_patch "global-color-palette" "$PALETTE" "--format=json"

echo "[2/4] Button colors (CTA primary -> gold on green)..."
run_patch "button-bg-color"   "var(--ast-global-color-1)"
run_patch "button-bg-h-color" "#B89538"
run_patch "button-color"      "#0D3B2E"
run_patch "button-h-color"    "#FFFFFF"

echo "[3/4] Link colors -> gold..."
run_patch "link-color"   "var(--ast-global-color-1)"
run_patch "link-h-color" "#B89538"

echo "[4/4] Purging cache..."
wp litespeed-purge all >/dev/null 2>&1 && echo "    LiteSpeed cache purged" \
  || wp cache flush >/dev/null 2>&1 && echo "    WP cache flushed" \
  || echo "    (no cache plugin found)"

echo
echo "OK. Visit https://pinnaclegroupwi.com/ in incognito to verify palette."
exit 0
