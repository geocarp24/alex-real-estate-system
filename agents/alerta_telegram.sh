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
DESCRIPCION="${2:-Sin descripcion}"
SOLUCIONES="${3:-Revisar logs del sistema}"
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")

# Seleccionar emoji según nivel
case "$NIVEL" in
  CRITICO)     BADGE="CRITICO" ;;
  ADVERTENCIA) BADGE="ADVERTENCIA" ;;
  ATENCION)    BADGE="ATENCION" ;;
  INFO)        BADGE="INFO" ;;
  *)           BADGE="$NIVEL" ;;
esac

# Construir mensaje (sin comillas dobles internas para evitar problemas de escape)
MESSAGE="ALERTA ALEX - ${BADGE}

Situacion: ${DESCRIPCION}

Detectado: ${TIMESTAMP}

Posibles soluciones:
${SOLUCIONES}

-- Sistema ALEX (notificacion automatica)"

# Enviar a Telegram usando --data-urlencode para evitar problemas de caracteres
RESPONSE=$(curl -s -X POST "$API_URL" \
  --data-urlencode "chat_id=${CHAT_ID}" \
  --data-urlencode "text=${MESSAGE}")

# Verificar resultado
if echo "$RESPONSE" | grep -q '"ok":true'; then
  echo "ALERTA ENVIADA OK - ${NIVEL}: ${DESCRIPCION}"
  exit 0
else
  echo "ERROR enviando alerta: $RESPONSE"
  exit 1
fi
