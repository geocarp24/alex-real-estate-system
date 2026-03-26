#!/bin/bash
# ═══════════════════════════════════════════════════════
# ALERTA_TELEGRAM.SH — Sistema de Alertas de Seguridad ALEX
# Uso: bash alerta_telegram.sh "NIVEL" "DESCRIPCION" "SOLUCIONES"
# Niveles: CRITICO | ADVERTENCIA | ATENCION | INFO
# ═══════════════════════════════════════════════════════

BOT_TOKEN="8157575601:AAHmAo0OQroOUdXCnXZEjVh4hJkt0emx5_c"
CHAT_ID="8402370952"
API_URL="https://api.telegram.org/bot${BOT_TOKEN}/sendMessage"

NIVEL="${1:-INFO}"
DESCRIPCION="${2:-Sin descripción}"
SOLUCIONES="${3:-Revisar logs del sistema}"
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")

# Seleccionar emoji según nivel
case "$NIVEL" in
  CRITICO)     EMOJI="🚨" ; BADGE="🔴 CRÍTICO" ;;
  ADVERTENCIA) EMOJI="⚠️" ; BADGE="🟡 ADVERTENCIA" ;;
  ATENCION)    EMOJI="🔔" ; BADGE="🟠 ATENCIÓN" ;;
  INFO)        EMOJI="ℹ️" ; BADGE="🔵 INFO" ;;
  *)           EMOJI="📢" ; BADGE="$NIVEL" ;;
esac

# Construir mensaje
MESSAGE="${EMOJI} *ALERTA ALEX — ${BADGE}*

📍 *Situación:* ${DESCRIPCION}

🕐 *Detectado:* ${TIMESTAMP}

💡 *Posibles soluciones:*
${SOLUCIONES}

🔒 _Sistema ALEX — Notificación automática_"

# Enviar a Telegram
RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d "{
    \"chat_id\": \"${CHAT_ID}\",
    \"text\": $(echo "$MESSAGE" | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read()))'),
    \"parse_mode\": \"Markdown\"
  }")

# Verificar resultado
if echo "$RESPONSE" | grep -q '"ok":true'; then
  echo "ALERTA ENVIADA OK — ${NIVEL}: ${DESCRIPCION}"
  exit 0
else
  echo "ERROR enviando alerta: $RESPONSE"
  exit 1
fi
