#!/usr/bin/env bash
# setup-skills.sh — registra marketplaces + instala los 4 plugins Phase 2.
# Idempotente. Corré tras clonar fresh o si los plugins se desregistraron.
set -euo pipefail
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
echo "==> Registrando 4 marketplaces desde $REPO_DIR/plugin-marketplaces/"
for dir in "$REPO_DIR"/plugin-marketplaces/*/; do
  claude plugin marketplace add "$dir" 2>&1 | tail -1 || true
done
echo "==> Instalando 4 plugins Phase 2"
claude plugin install banana-claude@banana-claude-marketplace 2>&1 | tail -1 || true
claude plugin install claude-ads@agricidaniel-claude-ads 2>&1 | tail -1 || true
claude plugin install claude-seo@agricidaniel-seo 2>&1 | tail -1 || true
claude plugin install document-skills@anthropic-agent-skills 2>&1 | tail -1 || true
echo "==> Plugins instalados:"
claude plugin list 2>&1 | grep -E "❯|Version" | head -20
