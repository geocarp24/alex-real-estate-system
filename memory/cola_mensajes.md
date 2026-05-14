# COLA DE MENSAJES — ALEX Bot ↔ Claude Code

> Última actualización: 2026-04-05 | Estado: PUENTE ACTIVO ✅

---

## ESTADO DEL SISTEMA

| Componente | Estado | Ubicación |
|-----------|--------|-----------|
| ALEX Telegram Bot | ✅ Activo | VPS 187.77.215.146, systemd `alex-bot.service` |
| Claude Code CLI | ✅ Disponible | VPS 187.77.215.146, `/usr/local/bin/claude` |
| **Claude Worker** | ✅ **ACTIVO** | VPS, systemd `claude-worker.service` |
| Bridge Hostinger | ✅ HTTP 200 | agents.pinnaclegroupwi.com |
| Espejo conversación | ✅ Activo | `agents/shared_conversation.json` |

---

## ARQUITECTURA IMPLEMENTADA

```
Jorge (Telegram)
    ↓ /claude <tarea>
ALEX Bot (alex_bot.py)
    ↓ escribe en claude_inbox.json  {"task_id", "prompt", "chat_id", "status":"pending"}
    ↓ responde: "📨 Tarea encolada..."
claude_worker.py  (systemd, corre cada 2s)
    ↓ detecta status:pending
    ↓ ejecuta: claude --print "<historial + tarea>"
    ↓ escribe resultado en claude_outbox.json
    ↓ envía resultado directo a Telegram via Bot API
Jorge (Telegram)
    ✅ recibe respuesta automáticamente
```

---

## FASE 1 ✅ — subprocess /claude
- Implementado: 2026-04-05
- Archivo: `telegram_bot/alex_bot.py`
- Estado: Reemplazado por Fase 2 (ahora usa inbox asíncrono)

## FASE 2 ✅ — Cola asíncrona con worker daemon
- Implementado: 2026-04-05
- Worker: `/opt/alex-bot/claude_worker.py`
- Servicio: `/etc/systemd/system/claude-worker.service`
- Inbox: `/opt/alex-bot/agents/claude_inbox.json`
- Outbox: `/opt/alex-bot/agents/claude_outbox.json`
- Espejo: `/opt/alex-bot/agents/shared_conversation.json`
- Test: ✅ PASADO — "PUENTE ACTIVO" confirmado

## FASE 3 ✅ — Bidireccional (Claude → Telegram)
- Implementado dentro de `claude_worker.py`
- Worker envía resultados directamente via `https://api.telegram.org/bot.../sendMessage`
- No depende del bot para enviar — comunicación directa

---

## MENSAJES ACTIVOS EN COLA

*(Ver claude_inbox.json en el VPS)*

---

## HISTORIAL

| Fecha | Acción | Estado |
|-------|--------|--------|
| 2026-04-05 | Sistema documentado | ✅ |
| 2026-04-05 | Fase 1 — subprocess /claude | ✅ |
| 2026-04-05 | Fase 2 — worker daemon + inbox/outbox | ✅ |
| 2026-04-05 | Fase 3 — bidireccional worker→Telegram | ✅ |
| 2026-04-05 | Espejo shared_conversation.json | ✅ |
| 2026-04-05 | Test E2E — PUENTE ACTIVO confirmado | ✅ |

---

*Actualizado por ALEX Claude Code — 2026-04-05*
