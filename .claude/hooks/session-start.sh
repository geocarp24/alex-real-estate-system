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

# Install Python dependencies
PROJECT_DIR="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/../.." && pwd)}"
if [ -f "${PROJECT_DIR}/telegram_bot/requirements.txt" ]; then
  echo "==> Installing Python dependencies..."
  pip install -r "${PROJECT_DIR}/telegram_bot/requirements.txt" --quiet
fi
