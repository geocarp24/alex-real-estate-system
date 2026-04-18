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
#
# Astra reads these via global-color-palette key in the astra-settings option.
# Reversible: 00-backup.sh saves astra-settings.json before changes.

set -euo pipefail

WP_PATH="${WP_PATH:-$HOME/domains/pinnaclegroupwi.com/public_html}"
cd "$WP_PATH"

PALETTE='{"palette":["#0D3B2E","#C9A84C","#0D3B2E","#0D3B2E","#FFFFFF","#F5F5F0","#F5F0E8","#1A1A1A","#FFFFFF"],"title":"Pinnacle Holdings"}'

echo "[1/4] Updating Astra global color palette..."
wp option patch update astra-settings global-color-palette "$PALETTE" --format=json

echo "[2/4] Forcing button color (CTA primary -> gold on green)..."
wp option patch update astra-settings button-bg-color "var(--ast-global-color-1)"
wp option patch update astra-settings button-bg-h-color "#B89538"
wp option patch update astra-settings button-color "#0D3B2E"
wp option patch update astra-settings button-h-color "#FFFFFF"

echo "[3/4] Forcing link color -> gold..."
wp option patch update astra-settings link-color "var(--ast-global-color-1)"
wp option patch update astra-settings link-h-color "#B89538"

echo "[4/4] Purging LiteSpeed cache..."
wp litespeed-purge all 2>/dev/null || wp cache flush 2>/dev/null || echo "    (no cache plugin found, skipping purge)"

echo
echo "OK. Visit https://pinnaclegroupwi.com/ in incognito to verify palette."
