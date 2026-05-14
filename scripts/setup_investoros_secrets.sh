#!/usr/bin/env bash
# ============================================================
# setup_investoros_secrets.sh
#
# Helper para poblar los secrets de GitHub Actions en
# `geocarp24/investoros-web` durante la migración multi-repo.
#
# El Jefe ya tiene estos secrets en `geocarp24/alex-real-estate-system`
# (no se pueden leer via API por seguridad). Hay dos formas de usar este
# script:
#
#   OPCIÓN A — Sourcear un .env con los valores (más rápido):
#       1. Crear archivo `.investoros-secrets.env` con KEY=value por línea
#          (ej: ANTHROPIC_KEY=sk-ant-... )
#       2. source .investoros-secrets.env
#       3. bash scripts/setup_investoros_secrets.sh
#       4. ⚠ Borrar el .env después: rm .investoros-secrets.env
#
#   OPCIÓN B — Pegar cada valor cuando el script pregunte:
#       bash scripts/setup_investoros_secrets.sh --interactive
#
# Después de ejecutar, verificar con:
#       gh secret list --repo geocarp24/investoros-web
# ============================================================

set -euo pipefail

REPO="geocarp24/investoros-web"

# Secrets que necesitan los workflows del SaaS (ya excluidos los que sé):
# VPS_HOST, VPS_USER, VPS_PORT, VPS_PASSWORD, AIRTABLE_TOKEN, TELEGRAM_BOT_TOKEN
# (ya seteados directamente por el script de migración)

SECRETS=(
  # Anthropic — dos nombres porque algunos workflows usan uno, otros otro
  "ANTHROPIC_KEY"
  "ANTHROPIC_API_KEY"

  # Telegram (Pinnacle bot, alertas, chat ID)
  "FER_TELEGRAM_BOT_TOKEN"
  "TELEGRAM_CHAT_ID"

  # Anthropic / OpenAI bridges
  "ALEX_SECRET"

  # Tracerfy / Skip tracing
  "TRACERFY_TOKEN"

  # Secretario email
  "SECRETARIO_EMAIL_PASSWORD"

  # HeyGen avatares (Reels production)
  "HEYGEN_API_KEY"
  "HEYGEN_AVATAR_ID_JORGE"
  "HEYGEN_VOICE_ID_JORGE_EN"
  "HEYGEN_VOICE_ID_JORGE_ES"

  # HuggingFace (Modal model downloads)
  "HF_TOKEN"

  # Modal serverless tokens + endpoints
  "MODAL_TOKEN_ID"
  "MODAL_TOKEN_SECRET"
  "MODAL_FLUX2_ENDPOINT_URL"
  "MODAL_IMAGE_EDIT_ENDPOINT_URL"
  "MODAL_LTX2_ENDPOINT_URL"
  "MODAL_MUSIC_GEN_ENDPOINT_URL"
  "MODAL_QWEN3_TTS_ENDPOINT_URL"
  "MODAL_SADTALKER_ENDPOINT_URL"

  # Make.com (orquestación email + SMS)
  "MAKE_API_TOKEN"

  # Quo / OpenPhone (SMS)
  "QUO_API_KEY"

  # Meta Graph API (FB + IG)
  "META_PAGE_ACCESS_TOKEN"
  "META_USER_TOKEN"

  # Doppler (secrets manager — sirve como root para todo lo demás)
  "DOPPLER_TOKEN"

  # GitHub PATs
  "GH_PAT"
  "INVESTOROS_PAT"
)

MODE="${1:-from-env}"

set_secret() {
  local name="$1"
  local value="$2"
  if [ -z "$value" ]; then
    echo "  [skip] $name (vacío)"
    return 0
  fi
  printf '%s' "$value" | gh secret set "$name" --repo "$REPO" 2>&1 | sed 's/^/    /'
  echo "  [OK]   $name"
}

echo "=== Seteando secrets en $REPO ==="
echo ""

if [ "$MODE" == "--interactive" ]; then
  for s in "${SECRETS[@]}"; do
    read -srp "  $s: " value
    echo ""
    set_secret "$s" "$value"
  done
else
  # Modo from-env: leer cada secret desde variable de entorno con el mismo nombre
  echo "Modo: from-env (asume que sourceaste .investoros-secrets.env)"
  echo ""
  for s in "${SECRETS[@]}"; do
    value="${!s:-}"
    set_secret "$s" "$value"
  done
fi

echo ""
echo "=== Listado final ==="
gh secret list --repo "$REPO"
