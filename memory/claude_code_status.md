# CLAUDE CODE STATUS — Sistema ALEX
> Última actualización: 2026-04-06 | VPS: 187.77.215.146

---

## SERVICIOS ACTIVOS (4 servicios)

| Servicio | Estado | Función |
|---------|--------|---------|
| alex-bot.service | ✅ Running | Bot Telegram |
| claude-worker.service | ✅ Running | Worker fallback local |
| claude-api.service :5001 | ✅ Running | HTTP API puente Bot-Claude Code |
| github-monitor.service | ✅ Running | Monitor 24/7 task_queue + memoria_ALex |

---

## BLOTATO — CUENTAS CONECTADAS ✅ (2026-04-06)

| Plataforma | Account ID | Detalle | Uso |
|-----------|-----------|---------|-----|
| Facebook | 25638 | Jorge Cruz | Cuenta principal |
| Page | 965320503341457 | Pinnacle Holdings Group | ACTIVA — default |
| Page | 877737568755522 | Geocroficial | Reservada |
| Page | 723873447473999 | Geo Carpentry | Reservada |
| Instagram | 39285 | @pinnacle.groupwi | ACTIVA |

API Key: en .env como BLOTATO_API_KEY

---

## 3 CANALES PARA ENVIAR TAREAS A CLAUDE CODE

**Canal 1 — /tarea en Telegram:**
Bot escribe en task_queue.json GitHub → monitor detecta en 30s → Claude ejecuta → Telegram

**Canal 2 — /claude en Telegram:**
POST http://localhost:5001/task → Claude ejecuta en background → Telegram

**Canal 3 — memoria_ALex.md:**
Escribe bloque con Status PENDIENTE EJECUCION → monitor detecta en 30s → migra a queue → Claude ejecuta → Telegram

---

## TEST E2E PASADO 100% — 2026-04-06

- task_queue.json detectado y ejecutado en 10s
- memoria_ALex.md scanner operativo
- BLOTATO_API_KEY en .env
- Cuentas FB/IG verificadas
- Resultados llegan a Telegram automaticamente

*Actualizado por ALEX Claude Code — 2026-04-06*
