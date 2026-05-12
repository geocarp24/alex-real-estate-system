#!/usr/bin/env bash
# ============================================================
# build_tenant_context.sh
#
# Genera un bundle Markdown por tenant para subir a Claude Projects.
# Cada bundle incluye:
#   1. Reglas globales (CLAUDE.md + protocolos) — comunes a todos
#   2. Secciones filtradas de memoria por menciones del tenant
#   3. Archivos específicos del tenant (agents/tenants/<slug>/*)
#
# Uso:
#   bash scripts/build_tenant_context.sh <tenant-slug> [alias1] [alias2] ...
#
# Ejemplos:
#   bash scripts/build_tenant_context.sh pinnacle "Pinnacle" "pinnaclegroupwi"
#   bash scripts/build_tenant_context.sh geo-carpentry "Geo Carpentry" "GeoCarpentry" "Geo Budget"
#   bash scripts/build_tenant_context.sh fc-multiservices "FC Multiservices" "FC Multi"
# ============================================================

set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

if [ $# -lt 1 ]; then
  echo "usage: $0 <tenant-slug> [alias1] [alias2] ..." >&2
  echo "example: $0 geo-carpentry 'Geo Carpentry' 'GeoCarpentry' 'Geo Budget'" >&2
  exit 1
fi

TENANT_SLUG="$1"
shift

# Build alias array; default to TENANT_SLUG if no aliases given
ALIASES=("$TENANT_SLUG")
if [ $# -gt 0 ]; then
  ALIASES+=("$@")
fi

# Build OR-joined regex for grep
ALIAS_REGEX=""
for alias in "${ALIASES[@]}"; do
  if [ -z "$ALIAS_REGEX" ]; then
    ALIAS_REGEX="$alias"
  else
    ALIAS_REGEX="$ALIAS_REGEX|$alias"
  fi
done

TENANT_UPPER=$(echo "$TENANT_SLUG" | tr '[:lower:]-' '[:upper:]_')
OUT="${TENANT_UPPER}_PROJECT_CONTEXT.md"
TIMESTAMP=$(date -u +"%Y-%m-%d %H:%M:%S UTC")

# Sources for global rules (always included verbatim)
GLOBAL_SOURCES=(
  "CLAUDE.md"
  "agents/PROTOCOLO_EJECUCION.md"
  "agents/protocolo_seguro.md"
)

# Sources to filter by tenant aliases (per-block context)
FILTERED_SOURCES=(
  "memoria_ALex.md"
  "agents/memoria_alex.md"
  "agents/memoria_social_media.md"
  "telegram_bot/telegram_memory.md"
)

# ── 1. Header ───────────────────────────────────────────
cat > "$OUT" <<HEADER
# ${TENANT_UPPER//_/ } — Project Knowledge Bundle

**Auto-generated** for Claude Projects upload — do NOT edit by hand.
**Regenerate with:** \`bash scripts/build_tenant_context.sh $TENANT_SLUG ${ALIASES[@]:1}\`

- **Tenant slug:** \`$TENANT_SLUG\`
- **Aliases buscados:** ${ALIASES[*]}
- **Last generated:** $TIMESTAMP
- **Idioma:** Español por defecto

> ⚠️ All secrets (API tokens, base IDs, bot tokens) are REDACTED.
> For real credentials, see local \`.env\` / Doppler / config.php.

---

## Cómo usar este bundle

Subir a Claude Projects (claude.ai/projects) como Project Knowledge del proyecto
**${TENANT_UPPER//_/ }**. Custom instructions sugerido:

> Eres ALEX trabajando en el contexto del tenant **$TENANT_SLUG**. Lee
> SIEMPRE el Project Knowledge antes de responder. Idioma español. Modo
> /GOD activo. Reglas globales aplican (sección 1 del bundle); secciones 2-3
> son específicas a este tenant.

---

HEADER

# ── 2. Append global rules (full) ───────────────────────
{
  echo ""
  echo "## ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "## SECCIÓN 1 — Reglas Globales (aplican a todos los tenants)"
  echo "## ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
} >> "$OUT"

for src in "${GLOBAL_SOURCES[@]}"; do
  if [ -f "$src" ]; then
    {
      echo ""
      echo "### SOURCE: \`$src\` (full)"
      echo ""
      cat "$src"
      echo ""
    } >> "$OUT"
  fi
done

# ── 3. Append tenant-specific files (full) ──────────────
TENANT_DIR="agents/tenants/$TENANT_SLUG"
TENANT_JSON="agents/tenants/${TENANT_SLUG}.json"

{
  echo ""
  echo "## ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "## SECCIÓN 2 — Config Específica del Tenant"
  echo "## ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
} >> "$OUT"

TENANT_FILES_FOUND=0
if [ -d "$TENANT_DIR" ]; then
  while IFS= read -r -d '' f; do
    {
      echo ""
      echo "### SOURCE: \`$f\` (full)"
      echo ""
      echo '```'
      cat "$f"
      echo '```'
      echo ""
    } >> "$OUT"
    TENANT_FILES_FOUND=$((TENANT_FILES_FOUND + 1))
  done < <(find "$TENANT_DIR" -type f -print0)
fi

if [ -f "$TENANT_JSON" ]; then
  {
    echo ""
    echo "### SOURCE: \`$TENANT_JSON\` (full)"
    echo ""
    echo '```json'
    cat "$TENANT_JSON"
    echo '```'
    echo ""
  } >> "$OUT"
  TENANT_FILES_FOUND=$((TENANT_FILES_FOUND + 1))
fi

if [ $TENANT_FILES_FOUND -eq 0 ]; then
  {
    echo ""
    echo "_No se encontraron archivos de config específica del tenant en \`$TENANT_DIR/\` ni \`$TENANT_JSON\`._"
    echo "_Si este tenant ya tiene operaciones registradas, considera crear su carpeta tenant para config dedicada._"
    echo ""
  } >> "$OUT"
fi

# ── 4. Append filtered memory (blocks mentioning tenant aliases) ──
{
  echo ""
  echo "## ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "## SECCIÓN 3 — Memoria Operacional Filtrada por Aliases"
  echo "## ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo ""
  echo "**Filtros aplicados:** \`$ALIAS_REGEX\` (case-insensitive)."
  echo "**Heurística:** se incluye cada bloque (delimitado por línea vacía) que mencione cualquiera de los aliases."
  echo ""
} >> "$OUT"

filter_blocks_by_aliases() {
  local file="$1"
  awk -v aliases="$ALIAS_REGEX" '
    BEGIN { IGNORECASE = 1; RS = ""; ORS = "\n\n" }
    {
      if ($0 ~ aliases) {
        print
      }
    }
  ' "$file"
}

FILTERED_BLOCKS=0
for src in "${FILTERED_SOURCES[@]}"; do
  if [ -f "$src" ]; then
    FILTERED=$(filter_blocks_by_aliases "$src")
    if [ -n "$FILTERED" ]; then
      BLOCK_COUNT=$(echo "$FILTERED" | grep -c '^' || true)
      {
        echo ""
        echo "### SOURCE: \`$src\` (filtered blocks)"
        echo ""
        echo "$FILTERED"
      } >> "$OUT"
      FILTERED_BLOCKS=$((FILTERED_BLOCKS + BLOCK_COUNT))
    fi
  fi
done

if [ $FILTERED_BLOCKS -eq 0 ]; then
  echo "_No se encontraron menciones del tenant en las memorias filtradas._" >> "$OUT"
fi

# ── 5. Sanitize secrets ─────────────────────────────────
echo "" >> "$OUT"
echo "Sanitizing secrets..."
SECRETS_FOUND=0

redact() {
  local pattern="$1"; local replacement="$2"; local label="$3"
  if grep -qE "$pattern" "$OUT" 2>/dev/null; then
    local count
    count=$(grep -cE "$pattern" "$OUT" || true)
    sed -i.bak -E "s/$pattern/$replacement/g" "$OUT"
    rm -f "${OUT}.bak"
    SECRETS_FOUND=$((SECRETS_FOUND + count))
    echo "  ✓ Redacted $count $label"
  fi
}

redact 'pat[A-Za-z0-9]{14}\.[a-f0-9]{40,}' '[REDACTED_AIRTABLE_PAT]' 'Airtable PAT'
redact 'app[A-Za-z0-9]{14}' '[REDACTED_AIRTABLE_BASE_ID]' 'Airtable base ID'
redact 'tbl[A-Za-z0-9]{14}' '[REDACTED_AIRTABLE_TABLE_ID]' 'Airtable table ID'
redact '[0-9]{9,10}:[A-Za-z0-9_-]{35}' '[REDACTED_TELEGRAM_BOT_TOKEN]' 'Telegram token'
redact 'AKIA[A-Z0-9]{16}' '[REDACTED_AWS_ACCESS_KEY]' 'AWS access key'
redact 'sk-ant-[A-Za-z0-9_-]+' '[REDACTED_ANTHROPIC_KEY]' 'Anthropic key'
redact 'sk-[A-Za-z0-9_-]{30,}' '[REDACTED_OPENAI_KEY]' 'OpenAI key'
redact 'Bearer [A-Za-z0-9._-]{30,}' 'Bearer [REDACTED_TOKEN]' 'Bearer token'
redact 'sk_live_[A-Za-z0-9]+' '[REDACTED_STRIPE_LIVE_KEY]' 'Stripe live key'
redact 'sk_test_[A-Za-z0-9]+' '[REDACTED_STRIPE_TEST_KEY]' 'Stripe test key'
redact 'pk_live_[A-Za-z0-9]+' '[REDACTED_STRIPE_LIVE_PUB]' 'Stripe live publishable'
redact 'cloudinary://[^[:space:]"]+' 'cloudinary://[REDACTED]' 'Cloudinary URL'

# ── 6. Final report + safety check ──────────────────────
SIZE=$(wc -c < "$OUT" | tr -d ' ')
LINES=$(wc -l < "$OUT" | tr -d ' ')

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✓ Generated $OUT (tenant: $TENANT_SLUG)"
echo "  Size: $SIZE bytes ($((SIZE / 1024)) KB), $LINES lines"
echo "  Tenant files included: $TENANT_FILES_FOUND"
echo "  Secret matches redacted: $SECRETS_FOUND"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if grep -qE 'pat[A-Za-z0-9]{14}\.[a-f0-9]|sk-ant-[A-Za-z0-9]|sk-[A-Za-z0-9]{40}|AKIA[A-Z0-9]{16}|Bearer [A-Za-z0-9]{40}' "$OUT"; then
  echo "⚠️  WARNING: residual secret pattern detected. Inspect $OUT before upload!" >&2
  exit 1
fi

echo "✅ Safety check passed — $OUT is safe to upload."
