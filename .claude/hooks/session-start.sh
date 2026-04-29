#!/bin/bash
set -euo pipefail

# Only run in remote (web) environments
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

echo "==> Installing Firecrawl CLI..."

# Install firecrawl-cli globally via npm
npm install -g firecrawl-cli

echo "==> Firecrawl CLI installed: $(firecrawl --version 2>/dev/null || echo 'installed')"

# ── claude-mem (persistent memory across sessions) — Jorge order 2026-04-29 ──
# Auto-install + auto-start every session. Captures conversation context, persists
# across sandbox resets so future Claudes don't repeat work the team already did.
if ! command -v claude-mem >/dev/null 2>&1; then
  echo "==> Installing claude-mem..."
  npm install -g claude-mem --silent 2>&1 | tail -3
fi
if command -v claude-mem >/dev/null 2>&1; then
  claude-mem install --ide claude-code 2>&1 | tail -3 || true
  if command -v bun >/dev/null 2>&1; then
    claude-mem start 2>&1 | head -2 || true
    echo "==> claude-mem: $(claude-mem status 2>&1 | head -1)"
  else
    echo "==> claude-mem installed (Bun missing — worker disabled, hooks still capture)"
  fi
fi

# Install Python dependencies
PROJECT_DIR="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/../.." && pwd)}"
if [ -f "${PROJECT_DIR}/telegram_bot/requirements.txt" ]; then
  echo "==> Installing Python dependencies..."
  pip install -r "${PROJECT_DIR}/telegram_bot/requirements.txt" --quiet
fi
