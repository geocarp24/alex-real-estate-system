#!/usr/bin/env bash
# 99-run-quickwins.sh
# Runs all quick wins in order: backup -> fix code -> delete hello-world -> brand palette -> tracking.
# Each script is idempotent. Stops on first failure.

set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=========================================="
echo "  Pinnacle WP Optimization — Quick Wins"
echo "=========================================="
echo

bash "$DIR/00-backup.sh"
echo
bash "$DIR/01-fix-code-wrappers.sh"
echo
bash "$DIR/02-delete-hello-world.sh"
echo
bash "$DIR/03-apply-brand-palette.sh"
echo
bash "$DIR/04-install-tracking.sh"
echo

echo "=========================================="
echo "  All quick wins applied successfully."
echo "  Verify in incognito: https://pinnaclegroupwi.com/"
echo "=========================================="

# trigger: 2026-04-18T04:59:15Z
