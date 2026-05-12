#!/usr/bin/env bash
# ============================================================
# build_claude_project_context.sh
#
# Consolida toda la memoria operacional de ALEX en un solo
# archivo Markdown listo para subir a Claude Projects en
# claude.ai/projects → ALEX / Pinnacle Real Estate.
#
# Sanitiza secrets conocidos (Airtable PAT, Telegram tokens,
# AWS/Anthropic/OpenAI keys, generic Bearer headers) usando
# regex patterns ANTES de escribir el output.
#
# Uso:
#   bash scripts/build_claude_project_context.sh
#
# Re-sync:
#   Cada vez que edites memoria_ALex.md, CLAUDE.md, etc.,
#   re-corre este script y sube el output al Project Knowledge.
# ============================================================

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

OUT="ALEX_PROJECT_CONTEXT.md"
TIMESTAMP=$(date -u +"%Y-%m-%d %H:%M:%S UTC")

# Sources to consolidate (in load-priority order)
SOURCES=(
  "CLAUDE.md"
  "memoria_ALex.md"
  "agents/memoria_alex.md"
  "agents/PROTOCOLO_EJECUCION.md"
  "agents/protocolo_seguro.md"
  "telegram_bot/telegram_memory.md"
)

# ── 1. Build header ──────────────────────────────────────
cat > "$OUT" <<HEADER
# ALEX / Pinnacle Real Estate — Project Knowledge Bundle

**Auto-generated** for Claude Projects upload — do NOT edit by hand.
**Regenerate with:** \`bash scripts/build_claude_project_context.sh\`

- **Last generated:** $TIMESTAMP
- **Tenant:** Pinnacle Holdings Group (tenant cero del SaaS InvestorOS)
- **Stack:** Wisconsin real estate + InvestorOS multi-tenant SaaS
- **Idioma por defecto:** Español (cambiar a inglés solo si Jorge lo pide)

> ⚠️ All secrets (API tokens, base IDs, bot tokens) are REDACTED in this bundle.
> For real credentials, see local \`.env\` / Doppler / config.php (NOT in this file).

---

HEADER

# ── 2. Append each source file ───────────────────────────
for src in "${SOURCES[@]}"; do
  if [ -f "$src" ]; then
    echo "" >> "$OUT"
    echo "" >> "$OUT"
    echo "## ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" >> "$OUT"
    echo "## SOURCE: \`$src\`" >> "$OUT"
    echo "## ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" >> "$OUT"
    echo "" >> "$OUT"
    cat "$src" >> "$OUT"
  else
    echo "  ⚠️  Source not found, skipping: $src" >&2
  fi
done

# ── 3. Sanitize secrets ─────────────────────────────────
SECRETS_FOUND=0

redact() {
  local pattern="$1"
  local replacement="$2"
  local label="$3"
  if grep -qE "$pattern" "$OUT" 2>/dev/null; then
    local count
    count=$(grep -cE "$pattern" "$OUT" || true)
    sed -i.bak -E "s/$pattern/$replacement/g" "$OUT"
    rm -f "${OUT}.bak"
    echo "  ✓ Redacted $count $label match(es)"
    SECRETS_FOUND=$((SECRETS_FOUND + count))
  fi
}

echo "Sanitizing secrets..."

# Airtable Personal Access Token: pat[14 chars].[40+ hex]
redact 'pat[A-Za-z0-9]{14}\.[a-f0-9]{40,}' '[REDACTED_AIRTABLE_PAT]' 'Airtable PAT'

# Airtable base ID: app[14 chars]
redact 'app[A-Za-z0-9]{14}' '[REDACTED_AIRTABLE_BASE_ID]' 'Airtable base ID'

# Airtable table ID: tbl[14 chars]
redact 'tbl[A-Za-z0-9]{14}' '[REDACTED_AIRTABLE_TABLE_ID]' 'Airtable table ID'

# Telegram bot token: 9-10 digits : 35 chars
redact '[0-9]{9,10}:[A-Za-z0-9_-]{35}' '[REDACTED_TELEGRAM_BOT_TOKEN]' 'Telegram token'

# AWS access key
redact 'AKIA[A-Z0-9]{16}' '[REDACTED_AWS_ACCESS_KEY]' 'AWS access key'

# Anthropic API key
redact 'sk-ant-[A-Za-z0-9_-]+' '[REDACTED_ANTHROPIC_KEY]' 'Anthropic key'

# OpenAI API key
redact 'sk-[A-Za-z0-9_-]{30,}' '[REDACTED_OPENAI_KEY]' 'OpenAI key'

# Generic Bearer token in HTTP headers
redact 'Bearer [A-Za-z0-9._-]{30,}' 'Bearer [REDACTED_TOKEN]' 'Bearer token'

# Stripe keys
redact 'sk_live_[A-Za-z0-9]+' '[REDACTED_STRIPE_LIVE_KEY]' 'Stripe live key'
redact 'sk_test_[A-Za-z0-9]+' '[REDACTED_STRIPE_TEST_KEY]' 'Stripe test key'
redact 'pk_live_[A-Za-z0-9]+' '[REDACTED_STRIPE_LIVE_PUB]' 'Stripe live publishable'

# Cloudinary
redact 'cloudinary://[^[:space:]"]+' 'cloudinary://[REDACTED]' 'Cloudinary URL'

# Generic 32+ char hex secrets following common label patterns
redact '(SECRET|PASSWORD|TOKEN|API_KEY)[[:space:]]*[:=][[:space:]]*[\"]?[A-Za-z0-9_.-]{20,}[\"]?' '\1=[REDACTED]' 'generic secret'

# ── 4. Final report ─────────────────────────────────────
SIZE=$(wc -c < "$OUT" | tr -d ' ')
LINES=$(wc -l < "$OUT" | tr -d ' ')

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✓ Generated $OUT"
echo "  Size: $SIZE bytes ($((SIZE / 1024)) KB), $LINES lines"
echo "  Total secret matches redacted: $SECRETS_FOUND"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Next steps:"
echo "  1. Open https://claude.ai/projects"
echo "  2. Go to: ALEX / Pinnacle Real Estate (or create it)"
echo "  3. Project Knowledge → upload $OUT"
echo "  4. Re-sync after every memory edit by re-running this script"
echo ""

# ── 5. Final safety check — warn if any common secret pattern survived ─
if grep -qE 'pat[A-Za-z0-9]{14}\.|sk-[A-Za-z0-9]|AKIA[A-Z0-9]|Bearer [A-Za-z0-9]{30}' "$OUT"; then
  echo "⚠️  WARNING: file may still contain unredacted secret patterns. Inspect manually before upload!" >&2
  exit 1
fi

echo "✅ Safety check passed — file is safe to upload."
