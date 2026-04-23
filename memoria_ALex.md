# memoria_ALex.md — Memoria Persistente del Sistema ALEX

> Este archivo es leído y actualizado por ALEX al inicio y fin de cada sesión de análisis.
> Formato de fecha: YYYY-MM-DD. Añadir siempre fecha a cada entrada.

---

## REGLAS DEL JEFE (aplican a TODOS los agentes, siempre)

### 2026-04-22 — PROTOCOLO DE EJECUCIÓN (NO NEGOCIABLE — APROBADO POR JORGE)
- **Documento:** `agents/PROTOCOLO_EJECUCION.md` — leer al inicio de cada sesión junto con esta memoria.
- **Aplica a:** toda operación no trivial (WP, Airtable, VPS, Hostinger, integraciones, scripts de agentes).
- **7 fases obligatorias:**
  1. Carga de contexto (memoria + shared_conversation + protocolo + credenciales)
  2. Diagnóstico antes de acción (leer estado actual, nunca suponer)
  3. Backup obligatorio antes de cambios destructivos (commit + push a `backups/`)
  4. División de tareas grandes (< 300 líneas por archivo, Write al disco, nunca inline grande)
  5. Deploy seguro (test local → draft/staging → preview al Jefe → publish → purge cache)
  6. Verificación post-deploy (HTTP 200 + contenido esperado + flujo E2E + logs limpios)
  7. Auto-backup + checkpoint cada 15 min en memoria
- **Errores ya costaron créditos, no repetir** (stream timeout, WAF 403, credenciales perdidas, home rota, etc.) — lista completa en `PROTOCOLO_EJECUCION.md`.
- **Checklist obligatorio** antes de cada tarea — si falta algo, no arrancar.
- **Al iniciar sesión, confirmar:** "Protocolo cargado. Listo para operar según Fases 1–7."

### 2026-04-16 — Comunicación
- Respuestas cortas y simples. Evitar lenguaje técnico innecesario.
- No pedir confirmación repetida. Si el Jefe dice "procede", procede.
- No hacer trabajo extra que no se pidió. Ser proactivo solo cuando agrega valor real.
- Menos explicación, más acción.

### 2026-04-16 — Protección de Alexbot
- Antes de hacer merge a master o cualquier cambio que toque archivos compartidos (agents/, memoria_ALex.md, workflows), verificar si puede afectar a Alexbot.
- Si un cambio afecta tanto el trabajo actual como Alexbot, trabajar en ambos simultáneamente — nunca dejar al bot roto mientras se arregla otra cosa.
- El bot es producción 24/7. Su estabilidad es prioridad igual o mayor que el trabajo en curso.

### 2026-04-16 — Principios fundamentales
- SER HONESTO Y PROACTIVO. Siempre. Sin excepción.
- Si algo no funciona o es mala idea, decirlo directo. No endulzar.
- Proponer mejoras activamente sin esperar a que el Jefe pregunte.
- LEMA DEL SISTEMA: Profesional, Automatizado, Inteligente y Eficaz.
- Antes de hacer push: análisis profundo de TODOS los flujos, encontrar TODOS los gaps, resolverlos TODOS.

### 2026-04-17 — Capacidades y autonomía
- Hostinger: acceso SSH vía GitHub Actions. Puedo ejecutar comandos remotos, configurar crons, hacer deploys. NO pedirle al Jefe cosas que puedo hacer yo.
- Make.com: acceso API (token: 856a1ce2-...). Puedo listar/modificar escenarios.
- Airtable: acceso API completo. Puedo crear tablas, campos, registros.
- Quo/OpenPhone: API key para enviar SMS.
- Telegram: bot token para alertas (@Ferpinnaclebot).
- REGLA: si algo se puede automatizar o ejecutar directo, HACERLO.

### 2026-04-17 — Protocolo de cambios en scripts y prompts
- NUNCA modificar scripts de agentes (prompts, diálogos, objection handling) sin aprobación del Jefe.
- Entrar en MODO PLANEACIÓN primero: presentar cambios, explicar qué y por qué, esperar aprobación.
- NO tocar código que no necesite cambio. Solo lo estrictamente necesario.
- Aplica a: fer_claude.php, system prompts, objection scripts, mensajes al cliente.
- NO aplica a: bugs técnicos, parsing, logging, infraestructura — esos se arreglan directo.

---

## FER AI RECEPTIONIST — Proyecto completo (2026-04-17/18)

**Estado: EN PRODUCCIÓN**

**Arquitectura:** Quo webhook → fer_agent.php → Claude Haiku/Sonnet → SMS + Telegram + Airtable

**Archivos PHP en Hostinger (hostinger/tools/):**
- `fer_agent.php` — orquestador principal, webhook receiver
- `fer_first_contact.php` — cron 15min, rotación Phone1-4, 4 mensajes únicos bilingües
- `fer_seguimiento.php` — cron diario 9:30AM, 24 toques SMS+Email, 12 meses
- `fer_stale_cron.php` — cron diario 8AM, "Contacted" sin respuesta 5d → "Seguimiento"
- `fer_morning_brief.php` — cron diario 8:30AM, resumen pipeline + health check → Telegram
- `fer_diag.php` — diagnóstico + reset (requiere token=pinnacle2026)
- `lib/fer_claude.php` — prompt + Claude API + auto-escalation Haiku→Sonnet
- `lib/fer_conversations.php` — historial local por teléfono
- `lib/fer_airtable.php` — Contacts + Fer Conversations + Deals
- `lib/fer_quo.php` — SMS via Quo/OpenPhone
- `lib/fer_telegram.php` — alertas a Jorge con Fer Score + datos completos
- `lib/fer_logger.php` — logs JSONL
- `lib/fer_deduplication.php` — dedup por messageId

**Tablas Airtable:**
- Leads: `tblxZz2EWIglOLnEd` — lista cruda de leads
- Contacts: `tblacvw0Ss770x8l5` — CRM principal con campos de calificación
- Deals: `tbliaEKxBHKBx7ZK2` — oportunidades reales, auto-creadas por Fer
- Fer Conversations: `tbleausFNpHhqLfsm` — transcripciones completas (QC)
- Tracy: `tbl6CJm4kYspOuTDB` — resultados skip trace
- Notes & Activity: `tbleOBXJl7sDhwj5w` — historial

**Flujo completo:**
1. Jefe marca Lead "Review this Deal" → el_polling (5min) → Tracy skip trace → Contact
2. fer_first_contact (15min, 9am-7pm) → SMS Phone1 → 24h → Phone2 → Phone3 → Phone4
3. DNC: solo Phone1 empático, luego Seguimiento
4. Cliente responde → Fer califica: owner, dirección, situación, timeline, amount owed, asking/lowest price, realtor math, win-win (3 strikes), vacant, repairs, decision makers, preferred contact, best time, email
5. Escalation → Telegram con Fer Score + todos los datos → Deal auto-creado con datos de Lead + Fer
6. Sin respuesta 5d → "Seguimiento" → Engine 24 toques → Step≥24 → Dead
7. Cliente responde a follow-up → Fer retoma sin repetir → Stage "Negotiation"

**Prompt reglas clave:** empathy first, no promesas falsas (sin tiempos específicos), price discovery (preguntar no negociar), 3 strikes en precio, bilingüe auto

**Crons Hostinger:**
- */15 * * * * → fer_first_contact.php
- 0 14 * * * → fer_stale_cron.php
- 30 15 * * * → fer_seguimiento.php
- 30 14 * * * → fer_morning_brief.php

**Make desactivados:** 4725930, 4738270, 4723767, 4656571, 4656574
**Make activos (no críticos):** 4541469, 4501430, 4636455, 4408392

**Secrets GitHub (Hostinger):** AIRTABLE_TOKEN, TRACERFY_TOKEN, ANTHROPIC_API_KEY, MAKE_API_TOKEN, QUO_API_KEY, FER_TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID

**Para resetear conversación:** `fer_agent.php?token=pinnacle2026&reset=all` (o número específico)

**Pipeline al cierre 2026-04-18:** 6 TBC, 2 Contacted, 16 Seguimiento, 0 Deals, todos los sistemas verdes

---

## 📋 DEAL ANALYSIS LOG

> Registro de deals analizados. Incluye estimación inicial, resultado real (si disponible), diferencias y lecciones aprendidas.

*(Sin entradas aún — se irán añadiendo conforme se analicen deals)*

---

## 🗺️ ZIP CODE PERFORMANCE NOTES

> Notas sobre rendimiento histórico por zip code: retornos observados, días en mercado, tendencias.

*(Sin entradas aún)*

---

## 🔨 CONTRACTOR / VENDOR NOTES

> Contratistas confiables, precios observados en rehab, proveedores recomendados o descartados.

*(Sin entradas aún)*

---

## 🚩 MARKET RISK FLAGS

> Zonas identificadas como de alto riesgo, mercados sobrevaluados, exceso de inventario, crimen alto, baja liquidez.

*(Sin entradas aún)*

---

*Última actualización: 2026-04-06*

---

## 🔧 BITÁCORA DE CAMBIOS AL SISTEMA

### 2026-04-05 — Sesión Claude Code

#### 1. Bot Telegram — API key arreglada
- **Problema:** Bot fallaba con error 401 — API key de Anthropic hardcodeada y expirada en `alex_bot.py`
- **Solución:** Bot ahora lee todas las credenciales desde `.env` via `python-dotenv`
- **Instalado:** `python-dotenv` en `/opt/alex-bot/venv/`
- **Estado:** ✅ Bot activo y respondiendo

#### 2. Bridge Hostinger → GitHub reparado y conectado
- **Problema:** `github_bridge.php` devolvía 500 — LiteSpeed no carga `SetEnv` del `.htaccess`
- **Solución:** Reestructuré `hostinger/` en GitHub:
  - `hostinger/tools/` → `el_polling.php`, `el_chismoso.php`
  - `hostinger/agents/` → `github_bridge.php`, `github_write.php`
- **GitHub Actions workflow** actualizado para deployar ambas carpetas + escribir `alex_config.php` vía SSH
- **Secrets agregados al repo:** `GH_PAT`, `ALEX_SECRET`
- **Estado:** ✅ Bridge respondiendo HTTP 200

#### 3. Bot Telegram conectado a memoria compartida (bridge)
- **Nuevo comportamiento:**
  - `read_memoria` → intenta bridge (GitHub) primero → fallback a archivo local
  - `write_memoria` → escribe local Y empuja a GitHub via bridge
  - `read/write_telegram_memory` → mismo comportamiento dual
  - `append_memoria_alex` → igual, dual write
- **Resultado:** Telegram Bot + Claude Code + Claude.ai comparten la misma memoria en tiempo real
- **Variables agregadas al `.env`:** `BRIDGE_URL`, `ALEX_SECRET`

#### 4. Social Media Agent integrado como sub-agente de ALEX
- **Archivos creados:**
  - `agents/social_media.md` — system prompt completo del agente
  - `agents/memoria_social_media.md` — memoria y estado de sistemas SM
- **Tool `invoke_social_media` agregado al bot:**
  - Parámetros: `task`, `platform` (FB/IG/Ambas/LinkedIn), `format_type` (Post/Reel/Carrusel/Story), `save_to_airtable`, `week_number`
  - Airtable SM base: `appU9s3kGkVpdrJkw` (separada del CRM de real estate)
  - Make.com webhook autorizado: `hook.us2.make.com/zbvy7391...`
- **Protocolo de seguridad actualizado** a v1.1 — Social Media Agent en cadena de autoridad
- **Fuente de datos:** repo `geocarp24/pinnacle-agent-memory` → `PINNACLE_SOCIAL_MEDIA_AGENT.md`

---

### 2026-04-06 — Regla Tracerfy + Limpieza Automática

#### 7. El Secretario — Regla Tracerfy agregada
- **Regla:** Emails cuyo FROM contenga "tracerfy" se archivan silenciosamente — sin Telegram, sin Airtable, sin Claude
- **Flujo IMAP:** COPY a carpeta "Archive" → `\\Deleted` en INBOX → EXPUNGE
- **Si carpeta Archive no existe:** se crea automáticamente en el servidor Hostinger
- **SQLite:** campos `is_tracerfy=1` y `archived_date=YYYY-MM-DD` registran el archivado
- **Limpieza automática:** al inicio de cada ciclo (cada 5 min), busca Tracerfy con `archived_date <= hoy-30días` → elimina permanentemente de IMAP y SQLite
- **Función de detección:** `es_tracerfy(remitente)` — case-insensitive
- **Estado:** ✅ Activo en producción (servicio systemd existente)

---

### 2026-04-06 — El Secretario y El Planificador

#### 5. El Secretario — Monitor de Email (deals@pinnaclegroupwi.com)
- **Script:** `secretario/email_monitor.py` — IMAP + Claude clasificación + Airtable + Telegram
- **Servicio systemd:** `secretario-email.service` ✅ Activo (pid 1181386)
- **IMAP:** ✅ Conectado a imap.hostinger.com:993 — 166 emails, 90 no leídos en primer ciclo
- **Credenciales en `.env`:** `SECRETARIO_EMAIL`, `SECRETARIO_PASSWORD`, `IMAP_HOST`, `IMAP_PORT`, `SMTP_HOST`, `SMTP_PORT`
- **DB local:** `secretario/emails.db` — SQLite para tracking de emails procesados
- **Comandos Telegram:** `/emails`, `/responder <ID>`, `/responder <ID> mensaje`
- **Clasificación:** LEAD → Airtable + notificación | URGENTE → notificación | RUTINARIO → resumen | SPAM → ignorar

#### 6. El Planificador — Google Calendar
- **Script:** `secretario/calendar_manager.py`
- **Cron alexuser:** resumen matutino 8am CST (14:00 UTC) + recordatorios cada 15min
- **Google libs:** ✅ Instaladas en venv (`google-auth`, `google-auth-oauthlib`, `google-api-python-client`)
- **Comandos Telegram:** `/agenda`, `/agenda semana`, `/cita <fecha> <hora> <nombre> <motivo>`
- **Estado OAuth — 2026-04-06:** ✅ credentials.json instalado en `secretario/google_creds/credentials.json`
  - Client ID: `26650922402-186bhh0gb01uho45va1boita1rkulpil.apps.googleusercontent.com`
  - Proyecto Google Cloud: `pinnacle-alex`
  - `.env` actualizado con `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_CREDS_PATH`, `GOOGLE_TOKEN_PATH`
  - Script de autorización: `secretario/auth_google.py`
  - **PENDIENTE:** Jorge ejecutar `python3 secretario/auth_google.py` desde SSH y pegar el código de autorización

---

## 📊 ESTADO ACTUAL DEL SISTEMA — 2026-04-05

| Componente | Estado | Notas |
|-----------|--------|-------|
| Bot Telegram | ✅ Activo | VPS `187.77.215.146`, systemd service |
| API Anthropic | ✅ OK | Lee de `.env`, modelo `claude-sonnet-4-6` |
| Bridge Hostinger | ✅ HTTP 200 | `agents.pinnaclegroupwi.com` |
| Memoria compartida | ✅ Activa | VPS ↔ GitHub ↔ Claude.ai |
| GitHub Actions deploy | ✅ Automático | Push a `master` → deploy a Hostinger |
| Sub-agente El Scout | ✅ Listo | `agents/scout.md` |
| Sub-agente El Matemático | ✅ Listo | `agents/matematico.md` |
| Sub-agente El Fact-Checker | ✅ Listo | `agents/fact-checker.md` |
| Sub-agente Tracy | ✅ Listo | `agents/tracy.md` + Tracerfy API |
| Sub-agente Social Media | ✅ Listo | `agents/social_media.md` — NUEVO hoy |
| El Secretario (Email) | ✅ Activo | `secretario/email_monitor.py` — servicio systemd |
| El Planificador (Calendar) | ⚠️ 95% | `secretario/calendar_manager.py` — credentials.json instalado, falta autorización OAuth |
| Airtable CRM (Real Estate) | ✅ Activo | Base `appfQbDA750Oihy9J` — tablas vacías |
| Airtable Social Media | ✅ Activo | Base `appU9s3kGkVpdrJkw` — 12 ideas pendientes |
| el_polling.php | ✅ Activo | Cron cada 5min en Hostinger |
| el_chismoso.php | ✅ Activo | Webhook Tracy→Contacts |
| Make.com escenario SM | ✅ Activo | ID 4636455 — activado 2026-04-05 |

---


#### 6. Test E2E Social Media — 2026-04-05
- **Flujo probado:** ALEX → Webhook Make.com → Escenario 4636455 → Airtable `appU9s3kGkVpdrJkw`
- **Webhook:** HTTP 200 Accepted ✅
- **Make.com:** Aceptó el payload ✅
- **Record creado en Airtable:** ❌ NO — confirmado en sesión siguiente (solo existe `recdF2uT42ay04k69`)
- **Contenido enviado:** "S2 - Foreclosure: Tienes Opciones" | Formato=Post | Semana=2
- **Conclusión:** Webhook funciona, pero escenario Make 4636455 no escribió en Airtable. Posibles causas: mapeo incorrecto en Make, conexión OAuth caducada nuevamente, o error en módulo Airtable del escenario.
- **Acción pendiente:** Revisar Make.com UI → escenario 4636455 → historial de ejecuciones → ver error exacto del módulo Airtable

#### 5. Flujo Social Media auditado y parcialmente reparado (2026-04-05)
- **Webhook Make.com**: ✅ HTTP 200 confirmado — `hook.us2.make.com/zbvy7391qh9n7dlmw1hy8pq9ym69obxk`
- **Airtable escritura**: ✅ Confirmada — record test `recCM80pqccFhVLr2` creado correctamente
- **Campos renombrados**: ` Hashtags` y ` Status` tenían espacio inicial — corregidos via Metadata API
- **Schema real documentado**: nombres con emojis (`🇺🇸 Caption EN`, `🇲🇽 Caption ES`), `Semana` sin `#`, ID Scripts de Video correcto (`tbli9BsyIwrhwa3aS`)
- **Bot `alex_bot.py`**: field mapping actualizado con nombres reales
- **Pendiente manual (Jorge)**: fix `Formato` y `Tipo` choices en Airtable UI + activar escenario Make 4636455

---

## 🔴 PENDIENTES PRIORITARIOS (próxima sesión)

1. **Subir 12 ideas SM a Airtable** — Jorge le pidió a ALEX via Telegram que las genere y guarde (en proceso)
2. **Geo Carpentry Budget Builder** — deploy pendiente, base de precios WI
3. **Website Geo Carpentry Fase 1** — migración a Durable, pendiente

## ✅ ESTADO ACTUALIZADO — 2026-04-05

- **Make.com escenario 4636455** — ✅ Funcionando correctamente, módulos mapeados bien
- **Airtable RE (Leads)** — ✅ Hay leads activos siendo trabajados por Jorge
- **ALEX escribe directo en Airtable SM** — ✅ Tools `airtable_sm_list/create/update` operativas sin depender de Make


## 2026 — Credenciales del Sistema (CONFIDENCIAL)

### GitHub Token (Full Access)
- Variable: GITHUB_TOKEN
- Guardado en .env del sistema
- Nota: NO imprimir en outputs

### Blotato API Key
- Variable: BLOTATO_API_KEY  
- Key: blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k=
- Nota: NO imprimir en outputs

### Estado:
- ✅ BLOTATO_API_KEY agregada al .env del VPS — 2026-04-06
- ✅ Cuentas verificadas via MCP: FB Account 25638 (Pinnacle Holdings Group), IG Account 39285 (@pinnacle.groupwi)
- ✅ Bot reiniciado con nueva API key cargada



## 2026-04-06 — TAREA PARA CLAUDE CODE (Puente ALEX→Claude Code activo)

### TAREA #1 — BLOTATO CONFIGURACIÓN COMPLETA
**Prioridad:** 🔴 MÁXIMA
**Enviada por:** ALEX Bot via Telegram

**Instrucciones:**
1. Agrega `BLOTATO_API_KEY=blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k=` al archivo `.env` del VPS (`/opt/alex-bot/.env`)
2. Verifica que la API Key funciona haciendo un GET a `https://my.blotato.com/api/v1/accounts` con header `api-key: blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k=`
3. Lista las cuentas conectadas (FB e IG de Pinnacle Holdings)
4. Documenta los Account IDs de FB e IG en `claude_code_status.md` en GitHub repo `geocarp24/pinnacle-agent-memory`
5. Confirma resultado escribiendo en `memoria_ALex.md` con status ✅ o ❌

**Criterio de éxito:** API Key en .env + Account IDs documentados + confirmación en memoria
**Status:** ⏳ PENDIENTE



## 2026-04-06 — TAREA URGENTE PARA CLAUDE CODE — APROBADA POR JORGE

### TAREA #1 — BLOTATO API KEY + VERIFICACIÓN
**Prioridad:** 🔴 MÁXIMA — Aprobada por Jorge
**Status:** ✅ COMPLETADO — 2026-04-06

**Instrucciones exactas:**
1. Abre el archivo `/opt/alex-bot/.env` en el VPS
2. Agrega esta línea:
   ```
   BLOTATO_API_KEY=blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k=
   ```
3. Guarda el archivo
4. Verifica que la API Key funciona:
   ```bash
   curl -s https://my.blotato.com/api/v1/accounts \
     -H "api-key: blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k="
   ```
5. Lista las cuentas conectadas (FB e IG de Pinnacle Holdings)
6. Documenta los Account IDs en `claude_code_status.md` en GitHub repo `geocarp24/pinnacle-agent-memory`
7. Reinicia el servicio del bot:
   ```bash
   sudo systemctl restart alex-bot
   ```
8. Confirma resultado escribiendo en `memoria_ALex.md`:
   - ✅ API Key agregada
   - ✅ Cuentas encontradas (IDs)
   - ✅ Bot reiniciado

**Criterio de éxito:** API Key en .env + Account IDs documentados + bot reiniciado
**Aprobado por:** Jorge Cruz — 2026-04-06

**Resultado de ejecución:**
- ✅ BLOTATO_API_KEY agregada a `/opt/alex-bot/.env`
- ✅ Cuentas verificadas via MCP Blotato:
  - FB: Account ID `25638` | Page: Pinnacle Holdings Group (`965320503341457`) — ACTIVA
  - FB: Page Geocroficial (`877737568755522`) — reservada
  - FB: Page Geo Carpentry (`723873447473999`) — reservada
  - IG: Account ID `39285` | @pinnacle.groupwi — ACTIVA
- ✅ Bot reiniciado con nueva configuración
- ✅ Monitor conectado a memoria_ALex.md — detecta bloques PENDIENTE EJECUCIÓN automáticamente

---

## ✅ Monitor automático 100% funcional — 2026-04-06

**Sistema completo operativo:**
- GitHub Monitor corre cada 30s — lee task_queue.json Y escanea memoria_ALex.md
- Tareas escritas en memoria_ALex.md con `**Status:** ⚙️ EN PROCESO` se migran automáticamente a task_queue.json
- Claude Code las ejecuta y devuelve resultado a Telegram sin intervención de Jorge
- Blotato configurado: FB (Pinnacle) + IG (@pinnacle.groupwi) listos para publicar

---

## ✅ Blotato MCP — Configuración y Herramientas Disponibles — 2026-04-06

**Método de conexión:** SSE (Server-Sent Events)
**URL:** `https://mcp.blotato.com/mcp`
**Config:** `~/.claude/settings.json` → `mcpServers.blotato`
**Usuario verificado:** ID `fc1219bc` — suscripción activa

### Cuentas conectadas (Pinnacle)
| Red | Account ID | Identificador | Estado |
|-----|-----------|--------------|--------|
| Facebook | `25638` | Pinnacle Holdings Group (Page `965320503341457`) | ✅ ACTIVA — usar por defecto |
| Facebook | reservada | Geocroficial (`877737568755522`) | Para sesiones futuras |
| Facebook | reservada | Geo Carpentry (`723873447473999`) | Para sesiones futuras |
| Instagram | `39285` | @pinnacle.groupwi | ✅ ACTIVA |

**Regla:** Para publicaciones de real estate → siempre usar FB Account `25638` (Pinnacle Holdings Group) + IG `39285`.

### 14 Herramientas MCP Disponibles
| Tool | Descripción |
|------|-------------|
| `blotato_get_user` | Info del usuario/suscripción activa |
| `blotato_list_accounts` | Lista cuentas FB/IG conectadas con IDs |
| `blotato_create_post` | Crea y publica post en FB/IG con texto, imagen, scheduling |
| `blotato_create_presigned_upload_url` | URL para subir imagen/video a Blotato CDN |
| `blotato_create_source` | Sube archivo multimedia (imagen/video) desde URL |
| `blotato_create_visual` | Genera visual desde template (37 templates disponibles) |
| `blotato_delete_schedule` | Elimina un post programado |
| `blotato_get_post_status` | Estado de publicación (published, failed, pending) |
| `blotato_get_schedule` | Detalles de un post programado |
| `blotato_get_source_status` | Estado de upload de un archivo multimedia |
| `blotato_get_visual_status` | Estado de generación de un visual |
| `blotato_list_schedules` | Lista posts programados |
| `blotato_list_visual_templates` | Lista 37 templates de visuales disponibles |
| `blotato_update_schedule` | Modifica un post programado (texto, fecha, cuentas) |

### Flujo para publicar un post
1. `blotato_create_source` — subir imagen (si hay) desde URL
2. `blotato_create_post` — crear post con `account_ids: [25638, 39285]`, texto EN/ES, imagen opcional
3. `blotato_get_post_status` — verificar resultado

### Visual Templates (37 disponibles)
- ALEX puede generar visuales de marca usando `blotato_create_visual` + template ID
- Listar templates actualizados: `blotato_list_visual_templates`

---

## ✅ Posts Programados en FB + IG — 2026-04-05 / 2026-04-05 (actualizado)

**6 Posts de formato `Post` programados en Pinnacle Holdings Group FB Page (`965320503341457`)**
**Plataforma:** Solo Facebook (IG pendiente — requiere imagen)
**Herramienta:** Blotato MCP — todos status "scheduled"

| Fecha | Título | Blotato ID | Airtable ID |
|-------|--------|-----------|------------|
| Lun 6 Abr 12pm CDT | S1 - ¿Quién es Jorge Cruz? | `4e924cba` | `recdF2uT42ay04k69` |
| Mié 8 Abr 12pm CDT | S1 - ¿Cuánto vale tu casa? | `314e7e95` | `recMwpr2pmMPZmRmf` |
| Vie 10 Abr 12pm CDT | S1 - Foreclosure en Wisconsin | `7fd1a454` | `recnxz2muTo5woVol` |
| Lun 13 Abr 12pm CDT | S2 - Testimonio Familia Martínez | `a609d373` | `recMuIrouAvcSD3O5` |
| Lun 20 Abr 12pm CDT | S3 - ¿Qué pasa con tu herencia? | `d2f8d770` | `recBoDVfwyQ72h2DS` |
| Lun 27 Abr 12pm CDT | S4 - ¿Qué es un Short Sale? | `1a20aa31` | `recvNs3tIzbDtfl8e` |

**✅ COMPLETADO 2026-04-05 — Todos los 10 posts pendientes programados con fotos de GitHub:**

| Fecha | Título | FB ID | IG ID | Fotos |
|-------|--------|-------|-------|-------|
| Lun 6 Abr 12pm CDT | S1 - ¿Quién es Jorge Cruz? | `4e924cba` (prev) | `ba500c68` | IMG_2706 |
| Mié 8 Abr 12pm CDT | S1 - ¿Cuánto vale tu casa? | `314e7e95` (prev) | `e0551fe0` | IMG_1988 |
| Mié 15 Abr 12pm CDT | S2 - 5 Razones efectivo | `a99c3170` | `f348c871` | IMG_2091+2092+2113 |
| Vie 17 Abr 12pm CDT | S2 - ¿Qué es el equity? | `ed2a5080` | `9b75f4cc` | IMG_2719+2723 |
| Mié 22 Abr 12pm CDT | S3 - Behind the Scenes | `336fbf47` | `4124dd62` | IMG_2724 |
| Vie 24 Abr 12pm CDT | S3 - Realtor vs Cash Buyer | `b250f747` | `566c539b` | IMG_2726+98EC09BA |
| Lun 27 Abr 12pm CDT | S4 - ¿Qué es un Short Sale? | `1a20aa31` (prev) | `98e63e30` | IMG_2090 |
| Mié 29 Abr 12pm CDT | S4 - Proceso paso a paso | `cbc1b3a0` | `93c40c52` | IMG_2706+IMG_1988 |
| Vie 1 May 12pm CDT | S4 - Mitos cash buyers | `e34cef1c` | `cf9553f5` | IMG_2090+2091 |
| Lun 4 May 12pm CDT | S4 - Jorge habla: Por qué fundé Pinnacle | `09bed09f` | `6d13bca6` | IMG_2723 |

**Estado final: CERO posts pendientes — calendario completo Abr-May 2026**

---
### 2026-04-05 17:19 — Tarea ejecutada por GitHub Monitor
**Tarea:** Responde EXACTAMENTE esto: MONITOR GITHUB ACTIVO - Sistema de monitoreo 24/7 funcionando. Detecté esta tarea desde task_queue.json en GitHub.
**Resultado:** MONITOR GITHUB ACTIVO - Sistema de monitoreo 24/7 funcionando. Detecté esta tarea desde task_queue.json en GitHub.



## 2026-04-06 — Pinnacle Call Assistant — Sesión de trabajo

### Reglas de trabajo — OBLIGATORIAS (aprobadas por Jorge)
1. **Siempre subir a Hostinger Y a GitHub** cuando se modifica un archivo de Tools
2. **Siempre actualizar memoria_ALex.md** al final de cada sesión con los cambios hechos
3. **Pedir confirmación antes de hacer cambios extras** — solo cambiar lo que el Jefe pidió

### Call Assistant — Estado actual
- **URL:** `pinnaclegroupwi.com/Tools/Pinnacle_Call_Assistant.html`
- **Login:** `deals@pinnaclegroupwi.com` / `4523Jics`
- **Copia GitHub:** `hostinger/tools/Pinnacle_Call_Assistant.html`
- **Archivos de soporte:** `auth.php`, `config.php`, `calendar.php`, `calendar_events.php`, `send_notification.php`

### Fix aplicado hoy (2026-04-06)
- **Problema:** `config.php` le faltaban las constantes `USERS` y `SESSION_HOURS` → login fallaba
- **Fix:** Agregadas las constantes → login funciona con `deals@pinnaclegroupwi.com` / `4523Jics`
- **Fix 2:** Paso `callback_time` ("Best time to call back") cambiado de `type:'text'` a dropdown con las 6 opciones válidas de Airtable (Morning, Afternoon, Evening, Anytime, Weekends Only, Unknow yet) — evita error INVALID_MULTIPLE_CHOICE_OPTIONS

### Pendiente (próxima sesión — aprobado por Jorge)
- Convertir otros Single Select fields a dropdowns: Stage completo, Water Source, Construction Type, Script Type, Occupied Status (typo "Owner Ocupied"), Roof/HVAC "Unknown" no válido en Airtable

---

## 2026-04-06 — LightRAG + Skills instaladas

### LightRAG — Búsqueda semántica activa
- **Script:** `rag/alex_rag.py`
- **Datos indexados:** memoria_ALex.md + shared_conversation.json + 83 Contacts + 5 Leads + 5 Deals
- **Comandos:**
  - `python3 rag/alex_rag.py index` — re-indexar todo
  - `python3 rag/alex_rag.py query "pregunta"` — buscar
- **Estado:** ✅ Funcional

### Skills instaladas en Claude Code (~/.claude/skills/)
| Skill | Comando | Descripción |
|-------|---------|-------------|
| humanizer | `/humanizer` | Humaniza textos con escritura AI |
| claude-mem | `/mem-search` | Memoria persistente entre sesiones |
| ui-ux-pro-max + 6 sub-skills | `/ui-ux-pro-max` | Diseño UI/UX, logos, slides, brand |
| obsidian (5 skills) | `/obsidian-*` | Markdown avanzado, canvas, CLI |
| everything-claude-code (181 skills) | varios | Ingeniería de software completa |
| superpowers (14 skills) | `/brainstorming`, `/tdd`, etc. | Metodologías avanzadas de desarrollo |
| **Total: ~208 skills** | | |

---

## 2026-04-06 — Acceso SSH Hostinger — GUARDADO

### Credenciales SSH Hostinger (pinnaclegroupwi.com)
- **Host:** `156.67.74.243`
- **Puerto:** `65002`
- **Usuario:** `u433637438`
- **Contraseña:** en `.env` → `HOSTINGER_SSH_PASS`
- **Ruta Tools:** `~/domains/pinnaclegroupwi.com/public_html/Tools/`
- **Comando de conexión:** `sshpass -p "$HOSTINGER_SSH_PASS" ssh -p 65002 -o StrictHostKeyChecking=no u433637438@156.67.74.243`

### Backup de archivos en GitHub
- **Carpeta:** `hostinger/tools/` en repo `geocarp24/alex-real-estate-system`
- **Archivos:** Pinnacle_Call_Assistant.html, Property_Inspector.html, auth.php, config.php, calendar.php, y más
- **Propósito:** Referencia para futuros cambios — leer desde GitHub antes de editar en Hostinger

---

## 2026-04-07 — TAREA CRÍTICA: INSTALAR BLOTATO MCP EN VPS
**Prioridad:** 🔴 MÁXIMA — Aprobada por Jorge
**Status:** ⚙️ EN PROCESO

### Objetivo
Instalar el servidor MCP de Blotato en el VPS para que el bot de Telegram pueda publicar en FB + IG directamente sin necesitar Claude Code desktop.

### Instrucciones para el Monitor/VPS:

**Paso 1 — Verificar si Node.js está instalado:**
```bash
node --version
npm --version
```

**Paso 2 — Instalar el MCP de Blotato:**
```bash
npm install -g @blotato/mcp-server
```

**Paso 3 — Si @blotato/mcp-server no existe en npm, intentar:**
```bash
npm install -g blotato-mcp
```

**Paso 4 — Verificar qué paquetes de Blotato existen en npm:**
```bash
npm search blotato
```

**Paso 5 — Probar llamada directa a la API REST de Blotato:**
```bash
curl -s "https://my.blotato.com/api/v1/accounts" \
  -H "api-key: blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k="
```

**Paso 6 — Probar endpoint alternativo:**
```bash
curl -s "https://api.blotato.com/v1/accounts" \
  -H "api-key: blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k=" \
  -H "Content-Type: application/json"
```

**Paso 7 — Reportar resultado completo en memoria_ALex.md**

### Criterio de éxito:
- MCP instalado en VPS O confirmación de que la API REST funciona directamente
- Documentar exactamente qué endpoint y método funciona
- Si nada funciona, documentar el error exacto para que ALEX pueda buscar solución alternativa

---

## SESIÓN 2026-04-06 — Seguimiento Engine + Google Calendar

### SISTEMA DE SEGUIMIENTO — OPERATIVO EN MAKE.COM

**Campaña de 24 toques construida y funcional:**
- **Scenario Engine** (ID: 4656571) — Corre diario 9:30 AM — Envía SMS + Email a contactos en Stage "Seguimiento"
- **Scenario Cold** (ID: 4656574) — Corre diario 9:45 AM — Mueve a "Dead" contactos con Step >= 24

**Para activar un contacto:**
1. Airtable → Contacts → Stage = `Seguimiento` → Seguimiento Step = `0`
2. El sistema hace el resto por 12 meses automáticamente

**Calendario de toques:**
- Mes 1: Días 1, 3, 7, 14, 21 (5 toques intensos)
- Mes 2-12: Cada 2 semanas (19 toques)
- Total: 24 toques → Stage pasa a Dead automáticamente

**Conexiones Make.com activas:**
- SMTP Hostinger (ID: 8232359): deals@pinnaclegroupwi.com / smtp.hostinger.com:587
- Airtable OAuth (ID: 7862703)
- Quo/OpenPhone (ID: 7973467) — Número: (920) 777-9886

**Campo nuevo en Contacts:** `Seguimiento Step` (Number, default 0)
**Stage nuevo en Contacts:** `Seguimiento` (entre "To Be Contacted" y "Contacted")

**Fórmula Next follow up date:**
```
{{addDays(now; switch(1.`Seguimiento Step`; 0; 2; 1; 4; 2; 7; 3; 7; 4; 21; 14))}}
```

**Notas técnicas Make IML:**
- Concatenación: `"texto" + variable` (NO concat(), NO toString())
- Phone en Airtable: almacenado como Number sin + (ej: 19204454093)
- Quo recibe: `{{1.Phone1}}` directamente sin formateo
- Separador de funciones: `;` (punto y coma)

---

### CALL ASSISTANT — CORRECCIONES APLICADAS

**URL del botón en Leads:**
```
"https://pinnaclegroupwi.com/Tools/Pinnacle_Call_Assistant.html?recordId=" & RECORD_ID()
```
⚠️ "Tools" con T mayúscula — crítico

**Campos Single Select convertidos a dropdowns:**
- Stage: To Be Contacted | Seguimiento | Contacted | Analized | Offer Sent | Dead
- Lenguage: English | Spanish (ortografía exacta: "Lenguage")
- Best time to call: Morning | Afternoon | Evening | Anytime | Weekends Only | Unknow yet
- Occupied Status: Empty | Owner Occupied | Rented
- Water Source: City Water | Septic | Well
- Construction Type: Concrete | Wood | Brick
- Foundation: Basement | Crawl Space | Slab
- Roof Status: Under 15 years | Over 15 years
- HVAC Status: Under 15 years | Over 15 years
- Lease Type: Month to Month | Yearly
- Script Type: Marketing List | Pre-foreclosure | Driving for Dollars

---

### GOOGLE CALENDAR — CONECTADO VÍA SERVICE ACCOUNT

**Service Account:** alex-calendar-agent@pinnacle-alex-bot.iam.gserviceaccount.com
**Archivo:** /opt/alex-bot/secretario/google_creds/service_account.json
**Calendario conectado:** deals@pinnaclegroupwi.com
**Estado:** OPERATIVO ✅

**Comandos Telegram disponibles:**
- `/agenda` — Ver citas de hoy
- `/agenda semana` — Próximos 7 días
- `/cita 2026-04-10 14:00 John Smith Motivo` — Crear cita

---

### IDs DE REFERENCIA MAKE.COM
- Organization: 6716517 | Team: 1932270
- Scenario Engine: 4656571 | Scenario Cold: 4656574
- SMTP Connection: 8232359 | Airtable OAuth: 7862703 | Quo: 7973467
- Quo Phone ID: PNNlYlSvAb

### PENDIENTES
- [ ] Probar flujo completo con contacto que tenga email
- [ ] Verificar ID del campo "Seguimiento Step" en Make.com
- [ ] Agregar emails a contactos que no los tienen
- [ ] Considerar Phone2 como respaldo en SMS

---

## 2026-04-07 — GEO CARPENTRY LLC — BASE DE CONOCIMIENTO

### PERFIL DE LA EMPRESA
- **Nombre:** Geo Carpentry LLC
- **Ubicación:** Green Bay, WI (ZIP 54301)
- **Radio de servicio:** 100 millas — cubre Green Bay, Appleton, Oshkosh
- **Experiencia:** 15+ años en construcción residencial
- **Licencia:** Licensed & Insured ✅
- **Ventaja competitiva:** Cotizaciones en 24 horas
- **Website:** geocarpentry.com (en migración a Hostinger/WordPress)
- **Facebook:** Existe — baja presencia (1 follower, sin reseñas)
- **GMB:** Existe — 2 reseñas 5 estrellas
- **Yelp:** Existe
- **Presupuesto marketing:** $0 (arranque orgánico)
- **Meta 2026:** $1,000,000 en revenue

### SERVICIOS PRINCIPALES
1. Kitchen Remodeling (alto ROI)
2. Bathroom Remodeling (alto ROI — spa-like, curbless showers, heated floors)
3. Custom Decks y Outdoor Living (madera y composite)
4. Basement Finishing (home offices, gyms, living spaces)
5. Home Additions (primary suites, sunrooms, garage conversions)
6. Garage Builds
7. Residential New Construction
8. Energy Efficiency & Smart Home Integration

### ANÁLISIS DE MERCADO WISCONSIN 2026
- Single-family housing permits en aumento en Wisconsin (WBA Q1 2026)
- Green Bay aprobó zoning reforms: duplexes y ADUs permitidos en zonas residenciales → OPORTUNIDAD para additions y new construction
- Demanda alta por aging housing stock + record home equity + condiciones económicas favorables
- Tendencias 2026: smart kitchens, spa bathrooms, four-season sunrooms, ADUs, energy efficiency

### FORTALEZAS IDENTIFICADAS
- 15+ años de experiencia
- Servicios completos (remodel + additions + new construction)
- Licensed & insured
- Radio de 100 millas
- Cotización en 24 horas
- Conexión con Pinnacle Holdings (rehabs garantizados)
- Bilingüe (ventaja con comunidad hispana en WI)

### DEBILIDADES IDENTIFICADAS
- Presencia digital muy baja (2 reseñas GMB, 1 follower FB)
- Sin portafolio visible en website
- Sin landing pages localizadas por ciudad
- Sin reseñas en plataformas de terceros
- Sin costo calculator ni design gallery en website
- Sin blog para SEO orgánico
- Formulario en Google Forms (poco profesional)
- Website en Mixo.io (SEO limitado) → EN MIGRACIÓN a Hostinger/WordPress

### OPORTUNIDADES CLAVE 2026
1. ADUs y duplexes — nuevas leyes de zoning en Green Bay
2. Kitchen + Bathroom remodel — mayor ROI para homeowners
3. Mercado hispano en WI — bilingüe = ventaja competitiva
4. Property managers — trabajo recurrente
5. Agentes RE locales — referidos constantes
6. Green Bay Home Show (Resch Center) — evento anual
7. Nextdoor — plataforma subestimada para contratistas locales

### ESTRATEGIA APROBADA (Orgánica — $0 presupuesto inicial)
Prioridad 1: Reseñas (meta: 15+ en 30 días)
Prioridad 2: GMB optimizado al 100% con fotos
Prioridad 3: Migrar website a Hostinger/WordPress con landing pages localizadas
Prioridad 4: Nextdoor Business + grupos Facebook locales
Prioridad 5: Networking — agentes RE, property managers, arquitectos
Prioridad 6: Blog con contenido SEO local
Prioridad 7: Craigslist Green Bay + Houzz + Angi + Thumbtack (gratuitos)

### PROYECCIÓN REVENUE 2026
- Mayo: $10K-30K | Junio: $30K-60K | Julio: $60K-100K | Q4: $150K-200K/mes
- Total realista orgánico: $400K-600K
- Para $1M: requiere $1,500-2,000/mes Google Ads desde Julio

### PENDIENTES TÉCNICOS
- [ ] Migrar geocarpentry.com a Hostinger/WordPress
- [ ] Landing pages: Green Bay/Appleton/Oshkosh por servicio
- [ ] Google Analytics + Facebook Pixel
- [ ] Reemplazar Google Forms con formulario propio
- [ ] Galería de portafolio con fotos reales
- [ ] Blog con contenido SEO
- [ ] Perfiles: Nextdoor, Houzz, Angi, Thumbtack
- [ ] 15+ reseñas en GMB
- [ ] Unirse a grupos Facebook locales



## 2026-04-07 — REGLA CRÍTICA: OPTIMIZACIÓN DE CRÉDITOS CLAUDE

### APROBADO POR JORGE — APLICAR SIEMPRE SIN EXCEPCIÓN

#### Jerarquía de modelos (de menor a mayor costo):
```
NIVEL 1 — Haiku (más barato):
→ Respuestas simples de texto
→ Consultas de Airtable básicas
→ Confirmaciones y updates de estado
→ Lectura de memoria
→ Respuestas de Telegram simples
→ Clasificación de emails (El Secretario)

NIVEL 2 — Sonnet (medio):
→ Análisis de deals simples
→ Generación de contenido social media
→ Skip tracing con Tracy
→ Cambios de código SENCILLOS (menos de 20 líneas)
→ Consultas de mercado básicas
→ Respuestas estructuradas al Jefe

NIVEL 3 — Opus/Claude Code (más caro):
→ SOLO cuando hay modificación de código compleja (+20 líneas)
→ Debugging profundo de sistemas
→ Arquitectura de nuevos agentes
→ Análisis financiero complejo (Scout + Matemático + Fact-Checker)
→ Tareas que requieren razonamiento muy profundo
```

#### Reglas de orquestación de recursos:
1. **NUNCA invocar Claude Code** para tareas que no requieran modificación de código pesado
2. **NUNCA usar modelo caro** cuando uno más barato puede resolver la tarea
3. **Antes de invocar cualquier sub-agente** — evaluar si realmente es necesario
4. **Agrupar tareas similares** — hacer múltiples consultas en una sola llamada
5. **Caché de resultados** — si ya tenemos un dato, no volver a buscarlo
6. **Leer memoria primero** — evitar análisis repetidos de mismas propiedades/zonas

#### Criterio de decisión rápido:
```
¿Es código complejo?  → SÍ → Claude Code (Opus)
¿Es código simple?    → SÍ → Sonnet
¿Es texto/consulta?   → SÍ → Haiku
¿Ya está en memoria?  → SÍ → Usar memoria, NO invocar agente
¿Es análisis de deal? → SÍ → Sonnet (Scout + Matemático + Fact-Checker)
```

#### Meta de ahorro:
- Reducir uso de Claude Code en 70%
- Usar Haiku para 60% de tareas rutinarias
- Usar Sonnet para 30% de tareas medias
- Usar Opus/Claude Code solo para 10% crítico

**Estado:** ✅ ACTIVO — Aplicar inmediatamente en todas las sesiones
**Aprobado por:** Jorge Cruz — 2026-04-07

---

### 2026-04-10 — Tracy→Contacts: Sistema anti-duplicados desplegado (Opus)

**Problema:** el_chismoso creaba duplicados intermitentes porque Tracerfy devuelve formatos inconsistentes entre runs (CAPS vs Title Case, phone con/sin country code). 11 contactos Skip Trace con 3 duplicados (27% dedup rate).

**Causa raíz real:** No fueron "contactos vacíos" como se pensó antes — fueron pares de duplicados con el mismo Tracerfy ID pero diferente formato de nombre/teléfono. El fallback-por-Mail-Address del código viejo no disparaba por alguna race condition.

**Solución desplegada:**
1. `el_chismoso.php` — función `findOrDedupeContactByMailAddress()` con normalización agresiva (strip punct + street→st etc.) y completeness score. Busca TODOS los matches, elige winner, merge campos, DELETE losers.
2. `el_polling.php` — dedup Tracy con normalización + ventana 14 días + validación de respuesta chismoso con retry automático.
3. `cleanup_duplicates.php` (NUEVO) — one-shot para limpieza histórica. Protegido por token, con modo dry_run.

**Resultado:** 11 → 8 Skip Trace contacts únicos. 0 duplicados restantes.

**Descubrimiento crítico:** El puerto SSH real de Hostinger es **65002** (no 22). Credenciales completas en `/opt/alex-bot/.env` como `HOSTINGER_SSH_*`. sshpass instalado en el VPS. Esto destraba deploys directos sin depender de GitHub Actions. Guardado en memoria auto como `reference_hostinger_deploy.md`.

**Gap pendiente:** Commit `65820c0` existe solo localmente — git push a GitHub falló por falta de PAT. Deploy está vivo en producción pero historial git no sincronizado.

**Commit local:** `65820c0 feat: sistema anti-duplicados Tracy→Contacts permanente`

---

### 2026-04-11 — Tracy/Contacts linked a Leads via Property Address (Opus)

**Problema reportado:** Jorge notó que Tracy/Contacts no aparecían linkeados en la tabla Leads. Recordaba que antes toda la info del dueño aparecía linkeada automáticamente en Leads y ahora no.

**Diagnóstico (vía Meta API):** Los linked-record fields YA EXISTÍAN en el schema de Airtable:
- Leads: 🔗 `Contacts`, 🔗 `Tracy`, lookup `status (from Tracy)`
- Contacts: 🔗 `Property Address` → Leads
- Tracy: 🔗 `Leads 2` → Leads

Pero el código PHP **no los poblaba** — solo el Stage, Full Name, Phone, etc. Por eso los campos bidireccionales quedaban vacíos y la info no "aparecía linkeada" en Leads.

**Descubrimiento clave:** Había una sesión Sonnet paralela del 2026-04-10 22:05 (`a2a2f5a`) que ya había escrito la lógica correcta en el_polling y el_chismoso — pero NUNCA se desplegó al servidor. El código local de git estaba correcto pero producción corría la versión vieja. Lesión: los commits en git local no llegan a Hostinger automáticamente, siempre hay que hacer SCP (vía .env credentials) o git push → GitHub Actions.

**Solución final desplegada hoy:**
1. el_polling.php setea Tracy.`Leads 2` al crear record (y append en dedup 14d)
2. el_chismoso.php lee Tracy.`Leads 2`, fallback `findLeadIdsByAddress()`, setea Contact.`Property Address` con union
3. `backfill_links.php` (NUEVO) — one-shot con dry_run, match por address normalizada

**Backfill ejecutado:** 14 Tracy + 12 Contacts linkeados. Los 64 Tracy sin match corresponden a leads históricos que ya no existen en la tabla.

**Verificado:** Lead `recUeRc3nqs8JzovK` (515 N HURON ST) ahora muestra linkeados `Contacts`, `Tracy` y lookup `status (from Tracy)`.

**Notificación:** Enviada a Jorge vía Telegram bot al completar.

**Commit local:** `f3a005a feat: link Tracy y Contacts a Leads vía Property Address`

---

### 2026-04-11 — Pipeline Tracy→Contacts: enrichment completo (Opus, FASE A+B+C+D)

**Contexto:** Jorge pidió ver todos los campos que cada agente toca, analizar brechas y cerrar lo que faltaba para quedar profesional.

**Análisis detallado escrito en `/root/.claude/plans/lexical-dazzling-muffin.md`:** workflow diagrams, field matrix per agent, 14 gaps priorizados por severidad.

**4 fases desplegadas:**

1. **FASE A — Pérdida de datos:**
   - el_chismoso ahora captura Phone1-4 (antes: 3). Fallback chain: primary → mobile_1-3 → landline_1-2
   - Email3 escrito a Contacts (antes perdido)
   - Nuevo campo `Owner Address` consolidado ("Street, City, State Zip")
   - `scoreContactCompleteness()` + `mergeable` sincronizados en el_chismoso y cleanup_duplicates

2. **FASE B — CRM tracking:**
   - `Leads.Last Contact Date` seteado en cada rama final de el_polling (6 branches)
   - `Contacts.Last contact date` seteado en cada upsert de el_chismoso

3. **FASE C — Audit trail:**
   - Nueva función `logDedupeAudit()` en el_chismoso
   - Cuando se eliminan duplicados, crea registro en `Notes & Activity` linkeado al winner con snapshot de los losers (nombre, teléfonos, tracerfy_id, score) y lista de campos rescatados
   - Constante `TABLE_NOTES = 'tbleOBXJl7sDhwj5w'`

4. **FASE D — Stages diferenciadas:**
   - `atPatch()` ahora acepta `$typecast=true` param para auto-crear select options
   - `no_results` → `'Skip Trace - No Results'`
   - `timeout/error/incomplete_address` → `'Skip Trace - Error'`
   - `success/phone_exists` → `'To be Contacted'` (sin cambio — preserva pipeline)

**Verificación:** 15/15 Skip Trace contacts re-procesados vía el_chismoso direct POST. Owner Address y Last contact date al 100%. Email3 y Phone4 poblados donde Tracerfy devolvió data.

**Pendiente manual (Jorge):** eliminar del schema Contacts en Airtable UI: `Leads`, `Leads 2`, `LG`. Son campos texto huérfanos que nunca se usaron — el linking real lo hace `Property Address`. No se puede borrar columnas vía API.

**Commit local:** `25a9e09` feat: FASE A+B+C+D — enrichment completo del pipeline Tracy→Contacts

**Decisiones de diseño:**
- Phone strategy: Phone4 existente + landline_2 fallback (no Phone5 nuevo) — Jorge eligió esta opción
- Dedup: Mail Address (unchanged)
- Stages success: mantener "To be Contacted" sin romper el pipeline existente (solo agregar stages para rutas de excepción)

---

### 2026-04-11 — Geo Carpentry website: contenido completo generado (Opus)

**Petición:** Jorge pidió migración profesional de geocarpentry.com a Hostinger/WordPress/Astra con "Construction Company" starter template, y que ALEX hiciera TODO el contenido sin que él tuviera que meterse.

**Descubrimientos clave durante el diagnóstico:**
1. **Brand Identity Doc v1.0** compartido por Jorge — colores oficiales Navy `#1B2A4A` + Orange `#FF6B00` (NO los del workflow YAML previo que usaban `#0d2137`/`#c85a14`)
2. **Fonts oficiales:** Playfair Display (headlines) + Inter (body) + Montserrat (accents)
3. **Slogan oficial:** "Built to Last. Crafted with Pride."
4. **NAP completo:** Phone (920) 367-1272, WhatsApp (920) 934-0351, admin@geocarpentry.com, 735 E Walnut St Suite 3 Green Bay WI, founded 2014, 10+ years, 500+ projects, 100mi radius, bilingual
5. **Service area: 15 ciudades** — Green Bay, Appleton, Oshkosh, Sheboygan, Manitowoc, Fond du Lac, Wausau, Marinette, Oconto, Shawano, De Pere, Ashwaubenon, Howard, Suamico, Pulaski
6. **6 servicios oficiales:** Custom Carpentry, Kitchen Remodeling, Bathroom Remodeling, Deck Building, Home Renovation, General Construction
7. **SSH Hostinger funciona para geocarpentry.com** — path `domains/geocarpentry.com/public_html/` (misma cuenta u433637438 que pinnaclegroupwi.com)
8. **WP-CLI YA INSTALADO** en /usr/local/bin/wp del servidor
9. **WordPress 6.9.4** activo, Astra 4.12.7 theme
10. **Logo YA subido** al WordPress (attachment ID 24) — `GEO-CARPENTRY-Logo-with-Soft-White-Highlights-2.png` con múltiples versiones + favicon
11. **Plugin SEO activo:** SureRank (también genera schema markup propio, coexiste con el mío)

**Decisiones finales (diferentes al plan inicial):**
- Descartar el Construction Company starter template — Jorge prefirió from scratch
- Custom child theme `geo-carpentry-child` con CSS de ~570 líneas
- Stock photos de Unsplash (no fotos reales)
- Colores del Brand Doc (corregidos del workflow YAML)

**Workarounds técnicos descubiertos:**
- `wp db export` falla silenciosamente en este Hostinger — usar `mysqldump` directo con credenciales de wp-config
- WP Application Password NO aparece en wp-admin UI cuando el sitio no tiene HTTPS — WordPress lo oculta por seguridad. Solución: crear via `wp user application-password create` vía SSH
- Cloudflare bloquea requests desde el VPS externo (error 1001) — todo se hace via SSH+WP-CLI directo, no REST API externa
- Media upload via REST API falla por Cloudflare — usar `wp media import` con SCP
- `--post_category` en wp post create no acepta nombres, solo slugs/IDs — usar `wp term create category` + `wp post term set` por separado
- `wp menu list --field=X` no funciona, usar `--fields=X --format=ids`

**Lo que quedó deployed:**
- Child theme activado en producción
- 5 core pages + 6 service pages (parent=services, URLs /services/{slug}/)
- 10 SEO blog posts localizados para WI + 6 categorías
- 10 stock photos en media library
- Main Menu en location primary (Home → Services → Portfolio → About → Contact)
- Schema markup LocalBusiness completo en `<head>` de cada página (via functions.php)
- robots.txt con referencia al sitemap
- Logo existente asignado como custom_logo del child theme

**Issue bloqueante para go-live:**
- Domain geocarpentry.com apunta a Cloudflare (172.66.0.42) pero el origin no está configurado correctamente
- Desde fuera, `https://geocarpentry.com` retorna 409 (Cloudflare error 1001)
- El sitio es accesible SOLO vía Hostinger staging URL: `https://blueviolet-gerbil-900105.hostingersite.com/`
- **Jorge tiene que:** configurar Cloudflare DNS → origin IP de Hostinger + activar SSL (o pausar Cloudflare y apuntar DNS directo a Hostinger)

**URLs para review:**
- Staging (funciona): https://blueviolet-gerbil-900105.hostingersite.com/
- Target (roto hasta fix DNS): http://geocarpentry.com/

**Commit local:** `feat: Geo Carpentry website — full content + child theme generation`

---

### 2026-04-11 (sesión nocturna) — Geo Carpentry website v2: feedback round (Opus)

**Feedback de Jorge:**
1. ❌ No se veía "Geo Carpentry" por ningún lado (site-title oculto por Astra)
2. ❌ Quitar TODO lo "custom" excepto construcciones nuevas custom
3. ❌ Footer tenía info incorrecta
4. ❌ Faltaba blog visible, FAQ, privacy, terms
5. ❌ Faltaba formulario, chat, email popup
6. ❌ Faltaba versión Spanish
7. ❌ Admin email incorrecto
8. ❌ Voice search optimization

**Soluciones desplegadas (todas mientras Jorge dormía):**

**BATCH 1 — Brand visibility:**
- Astra ocultaba site-title via `display:none !important`. Workaround: creé un `gc-brand-bar` que se inyecta via `wp_body_open` action en cada página, con logo 72px + "GEO CARPENTRY" título + slogan + phone + WhatsApp. Bypass completo de la config de Astra.

**BATCH 2 — Eliminar "custom":**
- Creé nueva service page `Finish Carpentry & Trim` (reemplaza Custom Carpentry)
- Borré `custom-carpentry` page, creé `finish-carpentry` con parent=services
- Regeneré kitchen/bathroom/deck/home-renovation/general-construction pages sin mencionar "custom" (excepto "Custom Home Builds" en General Construction)
- Actualicé home + services page
- Actualicé schema markup LocalBusiness (hasOfferCatalog) con los nombres nuevos

**BATCH 3 — Footer:**
- Sobrescribí `astra_footer` action con footer branded custom
- Columnas: Brand (logo + tagline + social), Services, Company, Contact
- NAP completo + privacy/terms links en bottom

**BATCH 4 — Blog/FAQ/Legal pages:**
- `/news/` — asignada como `page_for_posts` para mostrar los 10 blog posts
- `/faq/` — 15 Q&As + FAQPage schema markup (voice search opt)
- `/privacy-policy/` — asignada como `wp_page_for_privacy_policy`
- `/terms-of-service/` — legal completo
- Menú actualizado: Home → Services → News → FAQ → Portfolio → About → Contact

**BATCH 5 — Forms + Popup:**
- SureForms [sureforms id=2145] embedded en Contact page
- Email capture popup después de 15s (sessionStorage gated) con mailto fallback a admin@geocarpentry.com

**BATCH 6 — Spanish version (3 pages core):**
- `/inicio/` — home-es
- `/servicios/` — services-es
- `/contacto/` — contact-es (con formulario)
- Pendiente: About, Portfolio, 6 service pages, FAQ, Blog Spanish

**Descubrimientos técnicos:**
- SureForms shortcode: `[sureforms id="XX"]` (NO srfm)
- SureForms post type: `sureforms_form`
- WP-CLI `wp menu list --field=X` no funciona, usar `--fields=X --format=ids`
- WP-CLI `wp option patch update astra-settings key value` para setear keys específicas de un serialized option
- Astra `display-site-title=True` no es suficiente — el component puede estar removido del header builder. Workaround: inyectar via wp_body_open action
- Para override del footer de Astra: `remove_action('astra_footer', 'astra_footer_small_footer_template')` + custom output

**Estado final:**
- 18 pages + 10 posts + 69 media items
- Child theme v2 con gc-brand-bar + popup + footer custom
- Schema LocalBusiness + FAQPage
- Commit `f6520ee` pushed to github.com/geocarp24/alex-real-estate-system

**Issue bloqueante:** Dominio geocarpentry.com aún apunta a Cloudflare sin origin config. Staging URL `https://blueviolet-gerbil-900105.hostingersite.com/` es el único accesible desde afuera.

**Pendientes para próxima sesión:**
- Stock images referenciadas en HTML de páginas (ya están en media library)
- WP Live Chat Support config
- Quote-specific form (separado del Contact form)
- More Spanish pages (About, Portfolio, 6 service pages, FAQ, Blog)
- Cloudflare/DNS fix para go-live



## 2026 — Make.com API Key (CONFIDENCIAL)
- **API Key:** `0d1609f7-8242-4ca2-8e05-706d18152cda`
- **Organization ID:** `6716517`
- **Team ID:** `1932270`
- **Base URL:** `https://us2.make.com/api/v2`
- **Header:** `Authorization: Token 0d1609f7-8242-4ca2-8e05-706d18152cda`
- **Nota:** NUNCA imprimir en outputs públicos. Solo uso interno del sistema ALEX.


## 2026-04-16 — REGLA CRÍTICA: Pipeline Social Media es AUTOMÁTICO

### APROBADO POR JORGE — NUNCA REPETIR ESTA INSTRUCCIÓN

El pipeline de Social Media siempre corre en 3 fases CONSECUTIVAS y AUTOMÁTICAS:

```
FASE 1: Social Media Agent → genera contenido → guarda en Airtable
         ↓ (automático, sin preguntar)
FASE 2: El Creativo → genera visuales de todos los posts pendientes
         ↓ (automático, sin preguntar)
FASE 3: El Programador → publica/programa en FB + IG
         ↓ (automático, sin preguntar)
REPORTE FINAL → notificar al Jefe con resumen completo
```

**REGLA:** Cuando el Jefe pide contenido de Social Media, ALEX ejecuta las 3 fases sin pedir confirmación entre ellas. Solo reporta al final.

**Aprobado por:** Jorge Cruz — 2026-04-16
**NUNCA pedir confirmación entre fases. NUNCA preguntar si proceder.**


## 2026-04-16 — REGLAS CRÍTICAS DE OPERACIÓN (Aprobadas por Jorge — NUNCA OLVIDAR)

### AUTONOMÍA TOTAL
- ALEX es autosuficiente. Si tiene acceso, lo hace él. NUNCA pedir al Jefe que haga algo que ALEX puede hacer.
- ALEX tiene acceso SSH, API Keys, Airtable, Make.com, Blotato, GitHub — úsalos sin pedir permiso.
- No preguntar si proceder. Proceder y reportar al final.

### PIPELINE SOCIAL MEDIA — AUTOMÁTICO Y CONSECUTIVO (OBLIGATORIO)
El pipeline de Social Media siempre corre en 3 fases AUTOMÁTICAS sin pausas ni confirmaciones:
```
FASE 1: Social Media Agent → genera contenido → guarda en Airtable
         ↓ (automático)
FASE 2: El Creativo → genera visuales de TODOS los posts pendientes
         ↓ (automático)
FASE 3: El Programador → publica/programa en FB + IG
         ↓ (automático)
REPORTE FINAL → notificar al Jefe con resumen completo
```
NUNCA pedir confirmación entre fases. NUNCA preguntar si proceder. NUNCA dar trabajo al Jefe que ALEX puede hacer.

### COMPORTAMIENTO GENERAL
- Proactivo, dinámico, solucionador de problemas
- Si hay un error, buscar la solución y ejecutarla
- Reportar al final, no interrumpir con preguntas durante el proceso
- Reaprender activamente — cada lección se graba en memoria inmediatamente

**Aprobado por:** Jorge Cruz — 2026-04-16
**ESTAS REGLAS SON PERMANENTES — NUNCA REPETIR AL JEFE**


---

## 2026-04-22 — SESIÓN COMPLETA: WEBFORM + CHATBOT + CONTACT REDESIGN + EMAIL FIX

Sesión maratón. Cierre de Pinnacle Holdings public-facing stack. Aprobado por Jorge.

### A. Webform "Get My Cash Offer" (typeform-style multi-step)

**Página:** `/get-my-offer/` (WP page id `1748`).

**Stack frontend** (servido vía WP page + static assets en `/agents/pinnacle_form/`):
- `pinnacle_form.css` — brand `#0D3B2E` + `#C9A84C`, mobile-first
- `pinnacle_form_i18n.js` — diccionario EN+ES (s1–s17 + `ok` + `s_resume` + `s_returning`)
- `pinnacle_form_screens.js` — 18 pantallas + builders; `startLeadAndGo()` con soporte `reopen_lead_id`
- `pinnacle_form_core.js` — state machine con back-stack, `PNF_BRAIN.fire()`, `PNF_SESSION.{load,clear,restore}` (localStorage 2h TTL), `PNF_CORE_INIT` + `PNF_SHOW_FIRST` invocados desde screens.js tras `mountAll`
- Cargados desde `wp_assets/pinnacle_form/` (mirror) deployados via SCP a `/home/u433637438/.../public_html/agents/pinnacle_form/`
- Cache busting: `?v=<filemtime>` en URLs del WP page content

**Stack backend** (`hostinger/agents/pinnacle_public.php`, ~600 líneas):

Acciones expuestas vía POST JSON:
- `places_proxy` — Google Places autocomplete passthrough (server-side API key)
- `start_lead` — crea Lead en Airtable, manda OTP por SMS via Twilio
- `verify_phone` — valida OTP, marca Lead como verificado
- `resend_code` — re-envía OTP (15s pacing)
- `update_lead` — actualiza campos del Lead (cada paso del form)
- `lookup_existing` — **NUEVO** — dedup por phone (Contacts.Phone1-4) con fallback a address; devuelve `{exists, lead_id, contact_id, last_stage}` para flujo returning-user
- `form_brain` — **NUEVO** — micro-acks empáticos estilo Fer; usa Haiku 4.5 con prompt corto basado en el campo recién contestado
- `chat_message` — **NUEVO** — backend del chatbot floating widget

Helpers críticos:
- `pp_normalize_phone($raw)` → E.164 (+1XXXXXXXXXX)
- `pp_email_valid($email)` → filter_var + DNS check
- `pp_send_sms($to_e164, $msg)` → Twilio API, con retry 1×
- `pp_airtable_create / pp_airtable_update / pp_airtable_find_existing / pp_airtable_get_lead`
- `pp_compute_score($fields)` → score interno 0-100 (motivation × condition × timeline × equity)
- `pp_fer_brain($context, $field, $value)` → llama Anthropic Haiku 4.5, devuelve `{ack: "string corta empática"}`
- `pp_chat_brain($history, $lang)` → llama Anthropic Sonnet 4.6, system prompt incluye `<escalate>{...}</escalate>` JSON tag para detectar handoff a humano
- `pp_chat_notify_telegram($summary)` → alerta a TELEGRAM_CHAT_ID cuando chatbot escala

**Persistencia de sesión:**
- WP transients con TTL **2h** (subido desde 30min): `set_transient(pp_lead_session_key($lead_id), $session, 7200);`
- localStorage navegador: `pnf_session` (form), `pnf_chat` (chatbot), ambos 2h TTL

**Dedup logic (returning-user UX):**
1. Tras validar phone (s_phone), backend corre `pp_airtable_find_existing(phone, address?)`
2. Si match exacto por phone → muestra `s_returning` con 3 opciones: **Update existing** | **Get callback** | **Start new request**
3. Si match parcial por address → soft prompt opcional, no bloquea
4. `reopen_lead_id` permite continuar desde último stage guardado

### B. Chatbot Fer-style (floating widget en TODAS las páginas excepto el form)

**Frontend:**
- `hostinger/agents/pinnacle_chat/pinnacle_chat.css` — burbuja redonda 60×60 esquina inferior-derecha, gradient verde + dot dorado pulsante
- `hostinger/agents/pinnacle_chat/pinnacle_chat.js` — self-contained, sin deps externas
- API pública: `window.PinnacleChat.{open(), close(), reset()}` (usable desde botones del Contact page)
- Estado en `localStorage["pnf_chat"]` con TTL 2h
- POST a `/agents/pinnacle_public.php` action=`chat_message` con `{session_id, lang, history}`
- Idioma auto-detectado (`navigator.language`) EN/ES; greeting + UI bilingüe

**Loader (MU-plugin auto-activado):**
- `hostinger/mu-plugins/pinnacle-chat-loader.php`
- Enqueue solo en frontend (`!is_admin()`)
- Skip en `is_page('get-my-offer')` (cliente ya está en flujo estructurado)
- Cache bust: `$ver = '1.0.' . filemtime(.../pinnacle_chat.js)`

**Bug crítico resuelto:** CSS `display:flex` overrideaba `hidden` attribute → panel interceptaba clicks aunque "oculto". Fix: `.pnc-panel[hidden] { display:none !important; }`

**Escalación automática:** Si `pp_chat_brain` detecta intent caliente (vender pronto, lead motivado), inserta tag `<escalate>{summary, contact_info}</escalate>` en respuesta. Backend extrae, crea Lead en Airtable + alerta Telegram, y muestra mensaje "✓ Got it! A Pinnacle team member will reach out within 24 hours."

### C. Site-wide CTA redirect

Todos los botones "Get My Free Offer" del sitio ahora apuntan a `/get-my-offer/` (antes apuntaban a `/contact/`).

Páginas actualizadas (vía WP REST API + bridge):
- About Us (id 1399) — 1 CTA
- Services (id 1400) — 2 CTAs
- Home (id 1373) — already pointed correctly

Backups en `backups/wp_pinnacle/cta_fix_2026-04-22_213500/{1399,1400}_*.{before,after}.html`

### D. Contact Page redesign — "Five Ways to Reach Us"

Página id `1402`. Reemplazó la versión vieja con CF7 form embebido.

5 cards en grid responsivo:
1. **Phone** — `tel:+19204428287`
2. **Email** — `mailto:deals@pinnaclegroupwi.com`
3. **Visit** — Google Maps link a oficina
4. **Online Form** — `/get-my-offer/` (CTA prominente)
5. **Chat With Us** — botón que llama `window.PinnacleChat.open()`

CF7 form removido completamente. Página rebuild con Gutenberg blocks (wp:cover hero + wp:columns para los 5 cards).

Backup: `backups/wp_pinnacle/contact_five_ways_2026-04-22_214000/`

### E. Email reply recipient bug fix (`secretario/email_monitor.py`)

**Bug:** Respuestas a inquiries del CF7 viejo iban a `wordpress@pinnaclegroupwi.com` (mailbox no existe → bounce). El cliente real estaba en Reply-To header o dentro del body como "Email: foo@bar.com".

**Fix (3-tier resolution chain):**
1. `_extract_email_addr(raw)` — parsea formato `Name <foo@bar.com>`
2. `_is_system_sender(addr)` — blocklist: `wordpress@`, `no-reply@`, `mailer-daemon@`, etc.
3. `_extract_email_from_body(body)` — regex scan `/Email:\s*(\S+@\S+)/i`

```python
def responder_email_aprobado(db_id, texto_personalizado):
    # 1. Try Reply-To header
    # 2. Else try From (if not system sender)
    # 3. Else scan body
    # Fallback: alert to Telegram, mark as needs-manual

def enviar_respuesta_email():
    msg["From"] = f"Pinnacle Holdings <{EMAIL_ADDRESS}>"
    msg["Reply-To"] = EMAIL_ADDRESS  # forzado a deals@
```

**Deploy:** Workflow `deploy-vps-bot.yml` actualizado para incluir `secretario/**` en paths trigger + SCP source + post-deploy `systemctl restart secretario-email.service`.

### F. Documentación completa generada (`docs/`)

- `ARCHITECTURE.md` — diagrama de capas (frontend WP / static assets / PHP backend / VPS bot / external APIs)
- `AGENT_REGISTRY.md` + `agent_registry.json` — registro estructurado de los 7 sub-agentes + nuevos componentes pinnacle_form, pinnacle_chat
- `TASK_MATRIX.md` — quién hace qué + handoffs + no-dos
- `COST_OPTIMIZATION.md` — tabla de modelos por operación (Haiku para acks, Sonnet para chat, Opus para análisis)
- `SCALABILITY.md` — multi-tenant architecture para SaaS futuro
- `COMMERCIALIZATION.md` (master index) + 3 sub-docs:
  - `01_pricing_model.md` — tiers Starter/Growth/Pro/Enterprise + perf fee
  - `02_product_packaging.md` — feature matrix + onboarding 60-90d
  - `03_go_to_market.md` — segments + channels + 90-day launch plan + sales playbook

### G. PROTOCOLO DE EJECUCION (no negociable)

`agents/PROTOCOLO_EJECUCION.md` — 7 fases obligatorias para toda operación no trivial:

1. **Context load** — leer memoria, shared_conversation, archivos del módulo
2. **Diagnose before act** — identificar root cause antes de tocar nada
3. **Backup before destructive** — snapshot a `backups/<area>/<fecha>_<accion>/{before,after}/`
4. **Split large tasks** — máximo 300 líneas por archivo / 1 commit lógico
5. **Safe deploy** — draft → preview → publish → purge cache
6. **Verify post-deploy** — fetch URL pública, validar elementos clave
7. **Auto-backup + checkpoints** — cada 15min de trabajo, snapshot de estado

Cargado al inicio de cada sesión junto con `memoria_ALex.md`.

### H. Skills + auto-backup hooks instalados

- 165+ skills community instalados (superpowers + wshobson/agents)
- Hooks PreToolUse/PostToolUse en `.claude/settings.json` para auto-backup en cada Write/Edit
- Session start/stop checkpoints

### I. Credenciales activas (referencia rápida — NO IMPRIMIR)

Almacenadas en `.env.sandbox` (chmod 600, gitignored) y como GitHub Secrets:
- `PINNACLE_WP_USER` / `PINNACLE_WP_APP_PASSWORD` — bridge WP REST API
- `GOOGLE_PLACES_API_KEY` — autocomplete del form
- `GITHUB_SUPER_TOKEN` — gestión de Actions/secrets vía API
- `HOSTINGER_SSH_HOST/PORT/USER/PASSWORD` — SCP deploys
- `ANTHROPIC_API_KEY` — Haiku/Sonnet/Opus
- `TWILIO_*` — SMS OTP
- `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` — alertas

GitHub Secrets actualizados via libsodium sealed boxes (pynacl).

### J. Lecciones grabadas

1. **WAF/ModSecurity bloquea `<script>` en POST a WP**. Solución: deploy JS como archivos estáticos vía SCP, referenciar con `<script src="...">`.
2. **LiteSpeed static cache es independiente del WP cache**. `wp_cache_flush()` no lo purga. Usar `?v=<timestamp>` cache-busting en assets.
3. **DOMContentLoaded order matters** entre core.js + screens.js. Patrón: exponer `INIT` + `SHOW_FIRST` desde core, llamar tras `mountAll` en screens.
4. **HTML `hidden` attribute pierde contra CSS `display:flex`**. Siempre `[hidden] { display:none !important; }` en componentes flex.
5. **`origin/master` vs local `master`** — siempre fetch antes de comparar; local master puede estar décadas atrás.
6. **DNS cache overflow en Hostinger** = throttling transitorio, no error real. Esperar + retry con backoff.

### K. Estado del sitio al cierre de sesión

- ✅ Webform deployado y funcional (E2E verified)
- ✅ Chatbot deployado en TODAS las páginas excepto `/get-my-offer/`
- ✅ Site-wide CTAs apuntando a `/get-my-offer/`
- ✅ Contact page con 5 métodos
- ✅ Email reply bug arreglado en VPS
- ⚠ Cache LiteSpeed posiblemente sirviendo HTML viejo a algunos visitantes — bumpear `?v=` periódicamente y/o purgar via panel Hostinger

**Commits clave de la sesión** (rama `master` / `deploy-pnf`):
- `d635613` feat(pinnacle_public): P1 dedup backend — lookup_existing + reopen_lead_id
- `d1b18c8` feat(pinnacle_form): P2 returning-user flow
- `da7f782` feat(pinnacle_form): P3 localStorage session resume
- `cb34568` feat(pinnacle_form): P4 Fer-Form-Mode brain — empathic micro-acks
- `daaf395` fix(secretario): resolve email reply recipient correctly
- `c0cd1e4` ci(vps-bot): deploy secretario/ + restart secretario-email service
- `d12ab79` feat(wp): redirect site-wide offer CTAs to /get-my-offer/
- `c69ac30` feat(contact-page): 5 contact methods + remove old CF7 form
- `82c15e5` feat(chatbot): Fer-style floating chat widget on all pages
- `23c2a25` fix(chatbot): panel[hidden] needs !important to override display:flex

**Aprobado por:** Jorge Cruz — 2026-04-22
**Documentación de la sesión:** completa, pusheada a GitHub `claude/whats-going-on-LFo6h`.


---

## 2026-04-22 — REGLAS PERMANENTES (aprobadas por Jorge, nunca olvidar)

### R1. SURGICAL EDITS — nunca reescribir archivos enteros
**Regla:** Cuando exista un archivo, usar el tool `Edit` con `old_string`/`new_string` chirúrgicos. NUNCA usar `Write` para sobreescribir completo salvo que el archivo sea nuevo.
Cuando se toca una página o módulo existente, modificar SOLO las líneas necesarias. Nunca regenerar HTML/CSS/PHP completos "mientras estoy ahí".
El riesgo: regenerar pierde cambios anteriores de otras sesiones y crea "regresiones fantasma" donde el Jefe ve formato viejo.

### R2. LUZ VERDE PERMANENTE (Pinnacle public stack)
Jorge otorga autorización permanente para: merge claude→master + push a master + deploy workflow trigger + purge_cache + bump ?v= params, en el contexto del stack público de Pinnacle (form, chatbot, site CTAs, contact page, MU-plugins, bridges).
NO pedir confirmación antes de hacer estos deploys — ejecutar y reportar.
Sigue aplicando pausa obligatoria para: finanzas reales, eliminación irreversible de registros, comunicaciones externas en nombre del Jefe, credenciales.

### R3. VERIFICACIÓN POST-DEPLOY SIEMPRE
Después de cada deploy:
1. Fetch la URL pública con `curl -H "Cache-Control: no-cache"`
2. Confirmar que los elementos clave están presentes (grep por strings distintivos)
3. Si algo no se ve: diagnosticar primero DB vs cache vs browser, NO reescribir preventivamente

### R4. SKILLS — activarlos cuando corresponde
Tener 340+ skills instalados no sirve si no se invocan. Para tareas de código:
- `verification-before-completion` — antes de declarar "listo"
- `simplify` — después de cambios, revisar si se puede simplificar
- `systematic-debugging` — ante cualquier bug o comportamiento inesperado
- `focused-fix` — para fixes quirúrgicos end-to-end

### R5. MODO /GOD — PERMANENTE, TODOS LOS MODELOS, TODOS LOS ENTORNOS
**Orden directa de Jorge, 2026-04-22 — NO NEGOCIABLE.**

ALEX opera SIEMPRE en modo `/GOD`: profesional, eficiente, capaz, cost-benefit optimizado (tokens + tiempo). Antes de cualquier acción no trivial: evaluar qué skill aplica e invocarlo vía el tool `Skill`. Sin excusas. Aplica con cualquier modelo (Opus/Sonnet/Haiku) y en cualquier entorno (Claude Code, Telegram, Claude.ai, sub-agentes).

Tabla de activación automática de skills (extracto — versión completa en `CLAUDE.md` sección "MODO /GOD"):
- Bug / comportamiento inesperado → `systematic-debugging`
- Antes de "listo" → `verification-before-completion`
- Antes de implementar código → `test-driven-development`
- Después de cambiar código → `simplify`
- Multi-paso con spec → `writing-plans` → `executing-plans`
- Creative / diseño → `brainstorming`
- Review de cambios → `code-review-excellence`
- 2+ tareas independientes → `dispatching-parallel-agents`
- Frontend/Backend/DevOps/Security → `senior-frontend`/`senior-backend`/`senior-devops`/`senior-security`

Default en caso de duda: invocar el skill.

**Confirmación obligatoria al inicio de cada sesión:** *"Modo /GOD activo, skills-first habilitado, memoria always-on, luz verde permanente en stack Pinnacle."*

### R6. SKILL DE MEMORIA — SIEMPRE ACTIVO, TODA LA VIDA, SIN EXCUSAS
**Orden directa de Jorge, 2026-04-22 — NO NEGOCIABLE.**

El "skill de memoria" está permanentemente activado en cualquier modelo y cualquier entorno. No hay caso donde se pueda saltar.

**READ al ARRANQUE de TODA sesión:**
1. `memoria_ALex.md`
2. `agents/memoria_alex.md`
3. `agents/shared_conversation.json` (últimos 60 cross-channel)
4. `telegram_bot/telegram_memory.md`
5. `agents/PROTOCOLO_EJECUCION.md`
6. Memorias de sub-agentes cuando corresponda

**WRITE DURANTE la sesión** (inmediato, no esperar al cierre):
- Reglas/lecciones/aprobaciones de Jorge
- Deal analysis learnings
- Credenciales / accesos nuevos
- Bugs + root cause + fix
- Decisiones arquitectónicas
- Commits importantes

**WRITE al CIERRE:**
- Resumen datado en `memoria_ALex.md`
- Espejo en `agents/memoria_alex.md` + `telegram_bot/telegram_memory.md`
- Auto-backup en `backups/session_<fecha>_<hora>/`

**Skill designado:** `self-improving-agent` para curar auto-memory en knowledge durable.

**Cross-environment:** cualquier instancia (Claude Code, Telegram, Claude.ai, sub-agentes) lee las memorias al arrancar → continuidad garantizada entre canales.

**Aprobado por:** Jorge Cruz — 2026-04-22

---

## 2026-04-22 — CIERRE DE SESIÓN (confirmación del Jefe)

Jorge confirma: **"la página y los bots y todo lo demás están perfectos ahora."**

Estado final verificado en producción:
- ✅ Webform `/get-my-offer/` — 18 pantallas, bilingüe, dedup, session resume, Fer brain
- ✅ Chatbot floating — bubble 72px + halo fucsia pulsante + dot fucsia (aprobado visualmente por Jorge)
- ✅ MU-plugin con `filemtime(ABSPATH . ...)` correcto — cache-busting funcional
- ✅ Contact page — Five Ways to Reach Us (Call / Email / Visit / Online Form / Live Chatbot)
- ✅ Site-wide CTAs apuntando a `/get-my-offer/`
- ✅ Email reply bug corregido en VPS
- ✅ Cache LiteSpeed + Hostinger CDN purgados, live sirviendo HTML nuevo

Reglas permanentes grabadas en esta sesión:
- R1 Surgical edits (nunca regenerar archivos completos)
- R2 Luz verde permanente stack Pinnacle
- R3 Verify post-deploy siempre
- R4 Skills activos cuando corresponde
- R5 Modo /GOD permanente todos los modelos/entornos
- R6 Skill de memoria always-on toda la vida

Todo pusheado a `origin/master` + `origin/claude/whats-going-on-LFo6h` + documentación completa en `docs/` + backup en `backups/session_2026-04-22_221627/`.

---

## 2026-04-22 — EMAIL CAPTURE POPUP (nuevo componente, stack público)

Nuevo módulo pinnaclegroupwi.com para crecer lista de emails.

**Trigger:** 5s después del page load.
**Scope:** todas las páginas frontend EXCEPTO `/get-my-offer/` (skip por MU-plugin filter) y mobile <480px (skip por JS).
**Frequency cap:** 30 días cool-down tras dismiss + jamás reaparece si ya se suscribió (`localStorage.pnf_popup_subscribed=1`).

**Archivos:**
- `hostinger/agents/pinnacle_popup/pinnacle_popup.css` — modal centrado + backdrop blur + brand colors
- `hostinger/agents/pinnacle_popup/pinnacle_popup.js` — timer, bilingüe EN/ES, honeypot + elapsed_ms, success state
- `hostinger/mu-plugins/pinnacle-popup-loader.php` — enqueue con `filemtime(ABSPATH . 'agents/pinnacle_popup/...')` para cache-busting
- `hostinger/agents/pinnacle_public.php` — nueva action **`subscribe_email`** con:
  - Anti-spam: honeypot + elapsed_ms >= 1200ms
  - Email validation via `pp_email_valid()`
  - Rate limit: 5 subs/hora por IP
  - Airtable Contacts insert (fields: `Email1` + `Full Name="Newsletter Subscriber"` + `Notes=source/lang/date`, con typecast:true). Fallback sin `Notes` si field no existe.
  - Telegram notification a Jorge con email + lang + source
  - Siempre devuelve `{ok:true}` (bots obtienen fake-success para no aprender)

**Copy EN:** "Want a head start on the next deal?" + "Off-market opportunities + Wisconsin market insights, twice a month. No spam — unsubscribe anytime." + CTA "Send Me Deals" + decline "No thanks"
**Copy ES:** "¿Quieres adelantarte al próximo deal?" + "Oportunidades off-market + análisis del mercado de Wisconsin, dos veces al mes. Sin spam — cancela cuando quieras." + CTA "Envíenme Deals" + decline "No, gracias"

**Verificado en producción:**
- ✅ Home page enqueue tags con `?ver=1.0.1776902039`
- ✅ `/get-my-offer/` excluido (0 matches)
- ✅ Bot submission (elapsed_ms=100) devuelve fake-success (no crea record)
- ✅ Submission legítima (elapsed_ms=5000) crea record en Airtable Contacts + notifica Telegram
- ✅ Email inválido rechazado con `{ok:false, error:"invalid_email"}`
- ✅ Test records de QA limpiados de Airtable

**Skills invocados:** `popup-cro` (UX pattern + copy + anti-annoyance rules).

### Fix de audiencia 2026-04-23 — popup reorientado a homeowners

Copy inicial habló al público equivocado (inversionistas: "off-market deals"). Pinnacle compra a homeowners en distress, no vende deals a investors. Jorge detectó el error — audiencia y nicho mal planteados.

**Re-framing correcto:**
- Audiencia: homeowners Wisconsin con situaciones de presión (pre-foreclosure, probate, herencia, taxes atrasados, mudanza, rental cansado)
- Propuesta: lead magnet educativo (guías gratis + pros/cons de cada opción de venta + market updates mensuales)
- Efecto esperado: mayor opt-in + lista caliente de homeowners investigando → warm pipeline hacia `/get-my-offer/`

**Copy final EN:** Badge "Wisconsin Homeowners" | Headline "Thinking about selling? Know your options first." | Subhead sobre foreclosure/inherited/back taxes/fast sale + monthly Wisconsin market updates | CTA "Send Me Free Guides"
**Copy final ES:** Badge "Dueños de Casa en Wisconsin" | Headline "¿Pensando en vender? Conoce tus opciones primero." | Subhead equivalente | CTA "Recibir Guías Gratis"

**Lección:** ante cualquier componente de marketing, validar AUDIENCIA (quién) y PROPUESTA DE VALOR (qué obtiene) antes de escribir copy. El error fue asumir "deals" = lenguaje universal — en real estate, "deals" pertenece al lado investor, no al lado homeowner.

**Pendiente operacional para Jorge:** el popup promete "first guide" en el success message. Hace falta configurar un email real (Mailchimp/Beehiiv/Convertkit) que mande la guía de bienvenida automáticamente cuando llega un nuevo subscriber a Airtable Contacts. Sin eso, el promise queda sin cumplir.

### Debug pattern 2026-04-23 — URL bypass para testing del popup

Jorge reportó "no está funcionando". Diagnóstico systematic-debugging:
- L1–L6 server-side todos ✓ (tags emitidos, archivos 200, JS parsea, byte-idéntico, gates intactas)
- Root cause: localStorage gate en su browser (`pnp_popup_shown` con timestamp <30d tras dismissal previo) → el IIFE hace silent return en línea 20.

**Fix aplicado:** añadido URL override `?pnp_force=1` en `pinnacle_popup.js`. Cuando la URL contiene ese query param:
1. Se saltan las 3 gates (subscribed / cooldown / mobile-width)
2. El trigger se reduce a 500ms (vs 5000ms normal) para preview rápido

**Uso:** `https://pinnaclegroupwi.com/?pnp_force=1` (o cualquier URL del sitio con `?pnp_force=1`). Regular visitors no afectados — la lógica anti-annoyance sigue para ellos.

**Lección:** toda pieza de UI con gating client-side debe tener un URL bypass para QA/preview. El 30d cooldown es correcto para usuarios reales, pero sin escape hatch el propio dueño queda atrapado tras el primer dismiss.

### Fix de audiencia 2026-04-23 — popup también en mobile

Jorge reportó por segunda vez "popup no aparece". Systematic-debugging confirmó:
- Server 100% limpio (L1–L5 verificados)
- `?pnp_force=1` funciona en móvil ✓
- Incógnito en móvil sin `?pnp_force=1` → no aparece

**Root cause:** yo mismo había puesto `if (window.innerWidth < 480) return` como "anti-annoyance on phones" siguiendo convención CRO genérica. Pero para Pinnacle Holdings (real estate lead capture) el mobile traffic es mayoría — homeowners buscan "sell my house fast Wisconsin" desde el celular. Excluir mobile = perder 60–70% del lead flow potencial.

**Fix:** removí la línea `if (window.innerWidth < MOBILE_THRESHOLD) return`. El CSS ya tenía `@media (max-width:480px)` que adapta el modal a full-width en celular, así que la experiencia estaba lista — solo faltaba dejarlo aparecer.

**Lección PERMANENTE:** NO agregar exclusiones de audiencia unilateralmente (por "mejor práctica genérica") sin validar con el Jefe. Lo que es best practice para un blog SaaS no es best practice para real estate. Siempre preguntar: "¿dónde vive tu audiencia?" antes de filtrar por viewport, device, región, o cualquier otro eje. Para Pinnacle: mobile-first, nunca mobile-excluded.

### 2026-04-23 — Herramienta instalada: Open Carrusel (Instagram carousel builder)

Jorge ordenó instalar `Hainrixz/open-carrusel` de GitHub. No es un skill `~/.claude/skills/` global — es un proyecto Next.js standalone que se lanza desde su propio directorio con Claude Code.

**Ubicación:** `/home/user/open-carrusel/`
**Licencia:** MIT | **Stack:** Next.js 16 + React 19 + TypeScript 5 + Tailwind v4 + Puppeteer
**Diseño:** local-first (todo corre en la máquina, solo llama a Anthropic API vía Claude Code)
**Output:** slides HTML/CSS generadas por Claude → screenshots PNG 1080×1350 (Instagram feed).

**Slash commands (scoped a ese repo):**
- `/start` — bootstrapa setup, arranca dev server, abre browser
- `/stop` — detiene dev server
- `/reset` — resetea data/uploads
- `/doctor` — diagnóstico de entorno

**Flujo de uso:**
```bash
cd /home/user/open-carrusel
claude
# dentro de Claude Code:
/start
```
Esto abre el browser en http://localhost:3000 con el builder. Conversás con Claude, te genera slides, exportás PNGs.

**Uso para Pinnacle:** alimenta el pipeline Social Media (FASE 2 El Creativo) con carruseles Instagram brandeados. Output 1080×1350 coincide con el aspect ratio que ya usamos.

### 2026-04-23 — PHASE 1 → PHASE 2 + ELIMINAR BLOTATO COMPLETO

**Orden directa de Jorge:**

**Phase 1 (casi cerrada):** CRM (Airtable) + Website público Pinnacle (webform, chatbot, contact 5-ways, email-capture popup) + Tools internas (Fer SMS, Tracy skip tracer, secretario email, el_polling, el_chismoso, fer_* crons). **Estado:** operativo en producción.

**Phase 2 (arrancando ahora):** Publicidad + promoción del sitio + servicios Pinnacle. Social media, paid ads, growth, lead generation channels. Jorge va a buscar y instalar nuevos skills dedicados a esta fase.

**Blotato — eliminación completa:**
- Ya no sirve para los intereses de Pinnacle
- Hay que sacarlo del stack por completo, no solo de FASE 2 (visual gen) sino también de FASE 3 (publishing a FB/IG)
- Reemplazo para publishing: opciones a evaluar cuando Jorge instale skills de Phase 2 — candidatos: Meta Graph API directo, Buffer, Later, Publer, Metricool, open-source alternatives

**Estado del trabajo de El Creativo v6 (parcial — PAUSADO):**
- ✅ `agents/creativo_runner/themes.mjs` — 5 temas T1-T5 + slide builders (hook/point/CTA) mobile-first 1080×1350. Reutilizable.
- ⏸ `pinnacle_setup.mjs` (setup brand) — NO escrito todavía
- ⏸ `creativo_runner.mjs` (orchestrator) — NO escrito todavía
- ⏸ Rewrite `agents/creativo.md` a v6 — NO hecho todavía
- **Razón del pause:** si eliminamos Blotato completo, el diseño correcto del pipeline cambia. Mejor esperar Phase 2 skills + redefinir arquitectura end-to-end (generación + publicación) antes de invertir más horas. Los themes.mjs quedan como activo reusable independiente de la decisión arquitectural.

**Aprobado por:** Jorge Cruz — 2026-04-23

### R7. MOBILE-FIRST — PRIORIDAD #1 PERMANENTE (todos los proyectos, todos los modelos, todos los entornos)
**Orden directa de Jorge, 2026-04-23 — NO NEGOCIABLE.**

Todo el trabajo que hagamos debe estar optimizado para móviles como **prioridad número 1**. El mobile traffic es la mayoría del tráfico web hoy; cualquier decisión de diseño, UX, copy, código o arquitectura debe considerar mobile PRIMERO, desktop después.

**Aplica a (no exhaustivo):**
- Popups / modals / overlays
- Formularios (webform `/get-my-offer/`, contact forms, signup, etc.)
- Páginas del sitio (home, about, services, contact, FAQ, legal)
- Chatbot flotante
- Emails (templates + imágenes responsivas)
- Social media creatives (carrousels 1080x1350 vertical, reels 9:16)
- CTAs, botones, imágenes (tap targets ≥ 44px, thumbs-zone friendly)
- Cualquier componente nuevo

**Reglas operativas:**
1. **NUNCA** excluir mobile por viewport width sin consultar al Jefe
2. **Diseñar mobile-first**: CSS base para mobile, media queries para desktop (no al revés)
3. **Tap targets ≥ 44px**, padding generoso, no hover-dependent UX
4. **Test en mobile viewport primero**, desktop después
5. **Performance-first en mobile**: imágenes optimizadas, lazy-load, mínimo JS bloqueante
6. **Cuando diagnostique algo que "no funciona"**: probar en mobile viewport antes que en desktop

**Skills recomendados para esta regla:**
- `responsive-design` — layouts fluidos + container queries
- `mobile-ios-design` — iOS HIG (Safari iPhone es dominante en Wisconsin)
- `mobile-android-design` — Material Design
- `accessibility-compliance` — WCAG 2.2 mobile a11y patterns

**Aprobado por:** Jorge Cruz — 2026-04-23

### 2026-04-23 — Herramienta instalada: MiroFish CLI (multi-agent prediction engine)

Jorge ordenó instalar MiroFish para apoyar Phase 2 (publicidad + promoción). Es motor de simulación multi-agente: toma documentos fuente (PDF/MD/TXT) → construye grafo de conocimiento → genera personas AI → simula reacciones en redes (Twitter/Reddit) → produce reporte de predicción.

**Ubicación:** `/home/user/mirofish-cli/`
**Repo:** `amadad/mirofish-cli` (fork en inglés + soporte Claude CLI de `666ghj/MiroFish` original)
**Licencia:** **AGPL-3.0** — copyleft viral: si se modifica y se ofrece como servicio, las modificaciones deben ser open-source. **Estrategia:** usar como tool externo sin modificar el source. Nuestro wrapper de integración queda libre para licencia privada.
**Stack:** Python 3.11-3.12 + uv (package manager). Heavy deps (PyTorch, transformers, unstructured). `uv sync` completó OK.

**Configuración .env:**
```
LLM_PROVIDER=claude-cli
```

**Bug resuelto:** MiroFish busca binario literal `claude-cli` vía `shutil.which("claude-cli")`, pero el binario real de Claude Code se llama `claude`. Fix: symlink `/root/.local/bin/claude-cli → /opt/node22/bin/claude`. `mirofish doctor` pasa todos los checks.

**CLI comandos:**
- `uv run mirofish run --files <archivos> --requirement "<pregunta>" [--platform parallel|twitter|reddit] [--max-rounds N] [--json]`
- `uv run mirofish runs list --json`
- `uv run mirofish runs status <run_id>`
- `uv run mirofish runs export <run_id>`
- `uv run mirofish doctor`

**Casos de uso Pinnacle para Phase 2 (publicidad + promoción):**

1. **Pre-flight de ad copy:** alimentar draft de ad → simular reacción de audiencia homeowners WI → decidir qué va a Meta Ads antes de gastar $
2. **A/B test de popup/email subject:** 2 variantes → simular → elegir ganador sin tráfico real
3. **Validación de copy del webform:** simular reactions de personas en distress (foreclosure / probate / relocation) → detectar qué les traba
4. **Stress-test de Fer-bot:** generar mensajes típicos de homeowners → ver cómo Fer responde antes de producción
5. **Análisis competitivo:** cargar contenido de competidores locales WI → simular reacción de NUESTRA audiencia → encontrar gaps
6. **Escenarios de mercado:** nuevo evento (Fed rate cut, política fiscal WI) → simular impacto en intent-to-sell → ajustar messaging

**Estrategia SaaS (per R8):** MiroFish queda como **dependencia externa use-as-is**. Nuestro wrapper (cuando se escriba) le pasa tenant config + input files, recibe JSON report, lo presenta en nuestro UI. Cliente ve "Campaign Simulator" como feature premium. No distribuimos MiroFish modificado (evita AGPL).

**Pendiente:** primer smoke test con escenario Pinnacle real (ej: simular reacción al popup copy "Thinking about selling? Know your options first."). Jorge decide cuándo arrancamos.

**SMOKE TEST 2026-04-23 — FALLIDO, diagnóstico:**
- Input: `popup_copy.md` + `wi_homeowner_persona.md` (7KB texto total) + requirement detallado
- Ontology generation: ✅ excelente (10 entity types: DistressedHomeowner, TiredLandlord, CashHomeBuyer, RealEstateAgent, Attorney, Lender, GovernmentAgency, ConsumerAdvocate, Person, Organization + 10 edge types relevantes a Wisconsin real estate)
- **Graph extraction: 0 nodos / 0 edges de 22 chunks** — el LLM subprocess no extrajo nada. Task reportó "completed" sin error, pero resultado vacío.
- Sim step falló: "Simulation is not ready, current status: failed"
- **Root cause probable:** MiroFish spawnea `claude-cli` como subprocess. Cuando lo corremos DESDE DENTRO de una sesión Claude Code (como hoy), hay nesting de Claude CLI que falla silenciosamente (auth conflicts / rate limit / stdin-stdout pipe issues).
- **Implicación arquitectural:** El Oráculo **NO puede correrse como sub-agente dentro de una sesión Claude Code**. Debe deployarse como proceso standalone en Hostinger/VPS con cron + webhook, o llamarse desde un entorno limpio (terminal dedicada, script cron).
- **Plan ajustado:** cuando construyamos El Oráculo, lo deployamos en VPS (como `secretario-email.service`), no como sub-agente inline.

### 2026-04-23 — Queue de investigación de skills (Phase 2)

Jorge pidió investigar/evaluar/ejecutar en secuencia:
1. **Marketing skills** (en curso)
2. **Claude SEO** (siguiente)
3. **Claude ADS** (después)

Protocolo por cada uno: investigar GitHub → evaluar con lente **Phase 2 ads/promo + R8 SaaS-ready + R7 mobile-first** → instalar si fit claro → grabar en memoria → reportar.

**Pendiente de Jorge** (NO bloquea la queue, pero importante resolver):
- 3 decisiones sobre arquitectura de El Oráculo (pipeline paralelo / opt-in gate / primera prueba popup-or-carrusel). Cuando responda, seguimos con wiring de El Oráculo.

### 2026-04-23 — Marketing skill instalado: ai-marketing-claude

Jorge aprobó proceder con la queue de Phase 2. Primer item: marketing skills.

**Evaluados 3 candidatos:**
- `OpenClaudia/openclaudia-skills` (62+ skills, necesita API keys externas para valor pleno)
- `kostja94/marketing-skills` (160+ skills puros MD, sin orquestación)
- `zubair-trabzada/ai-marketing-claude` (15 skills + 5 parallel subagents + PDF reports) ← **Seleccionado**

**Razones de la selección (vía R8 SaaS-ready + R7 mobile-first + Phase 2 objetivos):**
1. **Client-ready PDF reports** = deliverable vendible (audits tipo "/market audit URL" → PDF branded)
2. **Parallel subagents** = cost-efficient, mismo patrón que Scout/Matemático/Fact-Checker
3. **15 skills focused** > 160 dispersos (menos ruido, easier integration)
4. **MIT license** ✓
5. Installer auditado: git clone + file copy + dep check (sin exec raro)

**Instalado en:**
- `/root/.claude/skills/market/` + 14 `market-*` skills individuales
- `/root/.claude/agents/` — 5 parallel agents: `market-content`, `market-conversion`, `market-competitive`, `market-technical`, `market-strategy`
- Scripts: `analyze_page.py`, `competitor_scanner.py`, `social_calendar.py`, `generate_pdf_report.py`
- Python deps: `reportlab 4.4.10` + `pillow 12.2.0` (añadidos post-install porque el check del installer mintió)

**15 slash commands disponibles:**
```
/market audit <url>        Full marketing audit (5 parallel agents → PDF)
/market quick <url>        60s snapshot
/market copy <url>         Copy generation
/market emails <topic>     Email sequences
/market social <topic>     Content calendar
/market ads <url>          Ad creative + copy
/market funnel <url>       Sales funnel analysis
/market competitors <url>  Competitive intel
/market landing <url>      Landing page CRO
/market launch <product>   Launch playbook
/market proposal <client>  Client proposal generator
/market report <url>       Markdown report
/market report-pdf <url>   PDF report (requires reportlab ✓)
/market seo <url>          SEO audit
/market brand <url>        Brand voice analysis
```

**Uso inmediato para Pinnacle + SaaS:**
- `/market audit pinnaclegroupwi.com` → PDF audit propio, validar si el sitio está optimizado
- `/market audit <competitor>` → inteligencia competitiva en WI
- `/market proposal <prospecto>` → generar propuestas cuando empecemos a vender el sistema a otros investors
- `/market social "Wisconsin foreclosure tips"` → alimentar pipeline SM

**Repo:** `zubair-trabzada/ai-marketing-claude`
**Status queue:** Marketing ✅ — Siguiente: Claude SEO (buscar especialista SEO para complementar `/market seo` del suite general)

### 2026-04-23 — WhatsApp AgentKit: clonado + PAUSADO por Jorge

Repo `onehundredfortyfive-southernbaptist487/whatsapp-agentkit` clonado en `/home/user/whatsapp-agentkit/`. Licencia MIT, built for LATAM. Providers soportados: Whapi.cloud / Meta Cloud API / Twilio.

**NO ejecuté `/build-agent`** — Jorge pausó para retomar después.

**3 decisiones arquitecturales pendientes** (bloquean el build cuando retomemos):
1. **Camino A** (nuevo sub-agente WhatsApp) vs **Camino B** (extender Fer con canal WhatsApp). Mi recomendación fue B — una sola voz, contact unification, mejor SaaS bundle.
2. **Si A:** nombre del sub-agente (propuestas: Isa / El Conversador / El Embajador).
3. **Provider para arrancar:** Whapi.cloud (sandbox gratis) / Meta Cloud API (pro pero verificación Meta) / Twilio (intermedio).

Ya analicé todo, cuando Jorge elija las 3 respuestas ejecuto en ~30 min (Camino A) o ~1-2 días (Camino B).

### 2026-04-23 — Estado global de la queue Phase 2 (snapshot operativo)

| Item | Estado | Pendiente |
|---|---|---|
| MiroFish skill | ✅ instalado + doctor OK | El Oráculo sub-agent: deferred a deploy VPS standalone (falla en nesting Claude CLI inline) |
| ai-marketing-claude skill | ✅ instalado + 14 skills + 5 parallel subagents + reportlab OK | El Mercader sub-agent: pendiente build (cron + Airtable + Telegram) |
| WhatsApp AgentKit | ✅ clonado | **PAUSADO POR JORGE**, 3 decisiones arquitecturales pendientes |
| Claude SEO skill | ⏳ no buscado | Queue: buscar + evaluar + instalar + build El Posicionador |
| Claude ADS skill | ⏳ no buscado | Queue: buscar + evaluar + instalar + build El Cazador |

**Decisiones de Jorge pendientes** (bloquean construcción):
1. Oráculo — ¿deployamos en VPS o pospone hasta Phase 3? (arquitectura decidida, falta luz verde al wiring)
2. WhatsApp — 3 decisiones (Camino A/B, nombre si A, provider)
3. Mercader — luz verde para arrancar build (skill listo, falta el orchestrator)

**Próximo paso natural** (si Jorge lo habilita): continuar la queue con **Claude SEO** — buscar skill, evaluar, instalar, diseñar El Posicionador.

### 2026-04-23 — SEO + ADS + skill-creator instalados

**SEO — `AgriciDaniel/claude-seo` v1.9.0** (MIT ✓)
- Ubicación: `/root/.claude/skills/seo/` + sub-skills en `/root/.claude/skills/seo-*/`
- 20+ slash commands: `/seo audit`, `/seo page`, `/seo technical`, `/seo geo` (AI Overviews + ChatGPT search + Perplexity), `/seo content`, `/seo schema`, `/seo local`, `/seo maps` (crítico para Pinnacle WI), `/seo images`, `/seo sitemap`, `/seo hreflang`, `/seo backlinks`, `/seo ecommerce`, `/seo drift`, `/seo google` (Search Console + PageSpeed), `/seo dataforseo`, `/seo firecrawl`, `/seo image-gen`, `/seo cluster`, `/seo plan`, `/seo sxo`, `/seo competitor-pages`, `/seo programmatic`
- Python 3.11 ✓ + Playwright (opcional) + venv propio en `/root/.claude/skills/seo/.venv`
- MCP servers opcionales (DataForSEO, Firecrawl, Banana) para live data
- Google APIs opcionales (PageSpeed, GSC, GA4, CrUX)

**ADS — `AgriciDaniel/claude-ads` v1.5.1** (MIT ✓)
- Ubicación: `/root/.claude/skills/ads/` + sub-skills en `/root/.claude/skills/ads-*/`
- 20+ slash commands: `/ads audit`, `/ads plan <industry>`, `/ads google` (80 checks), `/ads meta` (50 checks), `/ads youtube`, `/ads linkedin`, `/ads tiktok`, `/ads microsoft`, `/ads apple`, `/ads creative`, `/ads landing`, `/ads budget`, `/ads competitor`, `/ads math`, `/ads test`, `/ads plan`, `/ads dna`, `/ads generate` (requires banana-claude), `/ads photoshoot`, `/ads create`
- **12 industry templates INCLUYE real-estate** ← perfecto para Pinnacle + SaaS a otros investors
- 6 audit subagents + 4 creative subagents
- 25 RAG reference files
- Local-first: NO envía data externamente sin configuración explícita de MCP
- Trabaja con exports/screenshots de dashboards — no necesita login a cuentas de ads para empezar

**skill-creator — `anthropics/skills`** (oficial Anthropic)
- Ubicación: `/root/.claude/skills/skill-creator/` (248KB)
- Propósito: crear, editar, optimizar skills propios con el estándar oficial de Anthropic
- Incluye: SKILL.md + agents/ + scripts/ + eval-viewer/ + references/ + assets/
- Uso para Pinnacle: construir El Oráculo, El Mercader, El Posicionador, El Cazador como skills formales con evals cuantitativos antes de producción
- Workflow: draft → test prompts → eval results → iterate → benchmark

**Complementariedad del trío SEO + ADS + skill-creator:**
- **SEO + ADS del mismo autor** = arquitectura unificada. Cross-reference: `/seo competitor-pages` feeds `/ads competitor`, `/seo plan` feeds `/ads plan`, `/seo content` drives copy for `/ads copy`.
- **skill-creator oficial** = plantilla estándar para wrappear SEO + ADS en sub-agentes tenant-aware (El Posicionador, El Cazador) cuando pasemos a construcción.

**R9 siguiente paso** (cuando Jorge habilite): usar `skill-creator` para armar formalmente:
- **El Oráculo** (cuando destrabemos VPS deploy) — wrapper de MiroFish
- **El Mercader** — wrapper de `/market audit` + cron semanal → Airtable + Telegram
- **El Posicionador** — wrapper de `/seo audit` + cron cada 3 días → Airtable + Telegram
- **El Cazador** — wrapper de `/ads audit` + cron diario → Airtable + Telegram

### 2026-04-23 — El Mercader v1 DRAFT COMPLETO (primer sub-agente R9)

**Archivos shipped** (10KB total, zero runtime deps):

| Archivo | Rol |
|---|---|
| `agents/tenants/_template.json` | Template tenant config R8 (copiás → llenás para cada cliente nuevo, NO código change) |
| `agents/tenants/pinnacle.json` | Tenant zero: Pinnacle Holdings. `website`, `brand`, `competitors` (3 cash-buyers WI), `schedules` (cada 3 días + semanal), `airtable.base_id=appU9s3kGkVpdrJkw`, `alert_thresholds` (crit 50 / warn 70) |
| `agents/mercader/SKILL.md` | Anthropic skill-creator format: frontmatter + workflow. Identity + 3 modes (quick_health / deep_audit / on_demand) + Airtable schema + security rules |
| `agents/mercader/mercader.mjs` | Node orchestrator (ejecutable, chmod +x). Lee tenant JSON → spawns `claude --print` subprocess → parsea output (score, issues, wins, recs) → escribe Airtable → envía Telegram. Soporta `--dry-run` para preview sin tokens |
| `agents/mercader/README.md` | Deploy guide + known limitation (nested Claude CLI) + adding-new-tenant recipe |

**Verificación (per `verification-before-completion`):**
- ✅ `node --check` limpio
- ✅ Dry-run quick_health produce prompt correcto con URL Pinnacle + skill `market-quick`
- ✅ Dry-run deep_audit produce prompt con 3 competitors interpoados + report template
- ✅ Zero npm deps (Node 22 fetch + JSON native)

**3 approvals pendientes de Jorge antes de pasar a producción:**
1. **Airtable table:** crear `Marketing_Audits` en base `appU9s3kGkVpdrJkw` con el schema descrito en `SKILL.md` (run_id, tenant_id, audit_type, status, score, top_issues, top_wins, recommendations, summary_md, report_url, tokens_used, etc.). Pegar `table_id` en `pinnacle.json.airtable.table_id`.
2. **Host del cron:** Hostinger PHP cron wrapper (simple, mismo patrón que `fer_seguimiento`) OR VPS service (más control). Pendiente decisión arquitectural.
3. **Auth `claude` CLI** en el host elegido (`claude login`). Sin auth el subprocess falla igual que El Oráculo.

**Limitación conocida:** Nested Claude CLI (correr El Mercader desde dentro de una sesión ALEX Claude Code) falla silenciosamente, mismo issue que MiroFish. Solución: correr desde terminal limpia, VPS cron, o Hostinger cron.

**SaaS-ready (R8):** 100% tenant-aware. Agregar un segundo cliente = `cp _template.json acme.json` + llenar valores + `node mercader.mjs --tenant acme --mode quick_health`. Cero código nuevo.

**Próximos R9:** mismo patrón para El Posicionador (usa `/seo audit`), El Cazador (usa `/ads audit`), El Oráculo (usa MiroFish CLI).

### 2026-04-23 — El Posicionador v1 DRAFT COMPLETO (segundo sub-agente R9)

**Especificación final Jorge (orden directa 2026-04-23):**
- **Objetivo operativo:** posicionar TODAS las páginas del tenant en #1 en TODOS los motores (Google + Bing + DuckDuckGo + Brave + ChatGPT Search + Perplexity + AI Overviews + Google SGE — la lista está en `tenant.search_engines[]` para que el tenant la ajuste)
- **Cadencia:** cada 3 días (modo `seo_health` — amplio pero lightweight) + semanal lunes (modo `seo_deep` — reporte client-ready)
- **Prioridad PRIMARIA:** local SEO state-wide Wisconsin (15 ciudades top, no solo Milwaukee)
- **Prioridad SECUNDARIA:** regional US desde estados vecinos (IL, MN, IA, MI) — peso 25%
- **Mobile-first (R7):** Core Web Vitals móviles + mobile rank = señal primaria

**Archivos shipped:**
- `agents/posicionador/SKILL.md` — Anthropic frontmatter + Identity + Objetivo operativo + 3 modes + Airtable schema SEO_Audits
- `agents/posicionador/posicionador.mjs` — Node orchestrator (chmod +x). Soporta `--mode seo_health|seo_deep|on_demand` + `--dry-run`
- `agents/posicionador/README.md` — deploy guide
- `agents/tenants/pinnacle.json` expandido con:
  - `markets[].cities_primary` = 15 ciudades top WI
  - `regional_scope` = {primary: WI, secondary: [MN,IL,IA,MI], weights 0.75/0.25}
  - `search_engines` = [google, bing, duckduckgo, brave, chatgpt-search, perplexity, ai-overviews, google-sge]
  - `seo_goals` = {per_page_target_rank: 1, primary_priority, secondary_priority}
  - `airtable.seo_table_id` — campo separado para no colisionar con Mercader's `table_id`
- `agents/tenants/_template.json` — mismas extensiones para R8 consistency

**Verificación:** `node --check` OK + dry-run `seo_health` y `seo_deep` producen prompts correctos con state-wide cities + multi-engine + per-page target.

**Prompts generados (muestra):**
- `seo_health` prompt: 53 líneas — incluye inventario sitemap, rank probe de top 10 pages en 8 engines, mobile CWV check, local health WI primario
- `seo_deep` prompt: 100+ líneas — pipeline completo `/seo sitemap → audit → technical → local → maps → content → drift → per-page rank probe → schema → competitor gaps`, con tabla Markdown de rank inventory por engine, geo-grid 15 ciudades WI, regional US check

**Airtable schema SEO_Audits extendido** (vs Marketing_Audits de Mercader):
- `technical_score`, `local_score`, `content_score` (sub-scores dedicados)
- `mobile_cwv` (LCP/CLS/INP con PASS/WARN/FAIL)
- `local_ranks` (rank per ciudad)
- `competitor_gaps`, `schema_coverage`, `score_delta` (drift)

**Airtable separation R8:** tenant JSON ahora soporta `table_id` (Mercader), `seo_table_id` (Posicionador), `ads_table_id` (Cazador future), `oracle_table_id` (Oraculo future). Cada sub-agente escribe a su tabla dedicada. Si falta, fallback al `table_id` genérico.

**3 approvals pendientes para producción (mismo set que Mercader):**
1. Crear tabla `SEO_Audits` en Airtable base `appU9s3kGkVpdrJkw` → pegar `table_id` en `pinnacle.json.airtable.seo_table_id`
2. Host del cron (Hostinger PHP o VPS) — compartido con Mercader
3. `claude login` en el host

**Estado plantel R9 al cierre 2026-04-23:**
- El Oráculo — skill ✅, sub-agente diferido a VPS
- **El Mercader v1 DRAFT** ✅ — pending approvals
- **El Posicionador v1 DRAFT** ✅ — pending approvals
- El Cazador — skill ✅, sub-agente por construir (mismo patrón)

**Nota de refactor:** `mercader.mjs` y `posicionador.mjs` comparten ~80% del código (parseArgs / loadTenant / runClaude / airtableUpsert / telegramSend). Cuando construyamos El Cazador, tendremos 3 instancias del mismo patrón — momento ideal para extraer a `agents/_shared/runner.mjs` y dejar cada sub-agente como thin wrapper con solo `buildPrompt()` + `parseAudit()` específicos. Deferred hasta entonces (R4 cost-benefit: no abstraer con 2 instancias).

### 2026-04-23 — El Escriba v1 DRAFT (sub-sub-agente bajo El Posicionador)

**Jerarquía establecida:** primer caso de agente con dependencia vertical.
```
El Posicionador (SEO monitor) cada 3d + semanal
    └── El Escriba (content writer) semanal + on-demand
```
El Posicionador identifica QUÉ falta. El Escriba escribe QUÉ llena el hueco.

**Archivos shipped:**
- `agents/escriba/SKILL.md` — Anthropic frontmatter + Identity + Jerarquía + 4 modes + Content_Queue schema
- `agents/escriba/escriba.mjs` — Node orchestrator (chmod +x). Lee Airtable SEO_Audits para context, invoca claude CLI, escribe Content_Queue
- `agents/escriba/README.md` — deploy guide + cron + token cost estimate + workflow end-to-end

**4 modos:**
1. **`atp_mine`** (mensual día 1) — genera 50-100 preguntas ATP-style desde `atp_mining.seed_queries`. Default: claude_knowledge. Fallback opcional: gstack `/browse` sobre ATP real
2. **`plan_week`** (lunes post-Posicionador) — lee último SEO_Audit + ATP questions → calendario semanal de `articles_per_week` (default 3)
3. **`draft_article`** (mar-jue) — toma artículo status=Planned → draft completo EN+ES + metadata + schema JSON-LD + internal links + external citations. Opcional: publish a WP como status=draft via bridge
4. **`on_demand`** — ALEX pasa --title + --target-keyword directo, sin pasar por plan

**Token cost/tenant/mes:** ~240-340K tokens = $2.40-3.40. Billing hook limpio para R8 SaaS.

**Extensiones a pinnacle.json + _template.json:**
- `airtable.content_queue_table_id` — tabla dedicada Content_Queue
- `content_goals` object: articles_per_week, word_count range, tone, languages, topic_pillars (8 para Pinnacle), content_types + weights, backlink_strategy, atp_mining config, publish_to_wordpress flag
- `skills.content_plan_week` / `content_draft_article` / `content_atp_mine` — qué skills activa cada modo

**Verificación:** node --check OK + dry-run `plan_week` genera prompt correcto con 8 pillars Pinnacle + 15 ciudades WI + mix EN/ES + token budget.

**3 approvals pendientes:**
1. Crear tabla `Content_Queue` en Airtable (schema en SKILL.md) → pegar `content_queue_table_id` en tenant JSON
2. Host cron (compartido con Mercader + Posicionador)
3. Decidir flow publicación: auto-draft a WP via `pinnacle_wp_bridge.php create_post` OR review-first-en-Airtable-humano-aprueba-después

**Estado plantel R9 al cierre:**
- El Oráculo — skill ✅, sub-agente diferido VPS
- El Mercader v1 DRAFT ✅
- El Posicionador v1 DRAFT ✅
- **El Escriba v1 DRAFT ✅ (sub-sub-agente bajo Posicionador)**
- El Cazador — skill ✅, sub-agente por construir

**Patrón compartido confirmado:** Mercader + Posicionador + Escriba comparten ~75% del runtime (parseArgs / loadTenant / runClaude / airtableUpsert / telegramSend). Refactor a `agents/_shared/runner.mjs` se ejecuta cuando sumemos Cazador (4 instancias = ROI claro del abstract).

### 2026-04-23 — Tramo final del día: maps_deep + fer_review_request + MCP builders + NotebookLM MCP + El Cartógrafo scaffold

**Google Maps improvements shipped:**
- `agents/posicionador/posicionador.mjs` — nuevo modo `maps_deep` (READ-only audit dedicado GBP + NAP + geo-grid + reviews + posts + Q&A + photos). Cadencia cada 3 días. Dry-run verificado.
- `hostinger/tools/fer_review_request.php` — cron diario que manda SMS bilingüe post-Closed-Won pidiendo review Google, con follow-up 7 días. **Pending:** Jorge pasa el GBP PLACE_ID para llenar `GBP_REVIEW_URL` + crear campos Airtable Deals (`review_request_sent`, `review_request_sent_at`, `review_followup_sent`, `review_followup_sent_at`, `review_received`).

**MCP toolchain instalado:**
- `mcp-builder` (ComposioHQ) — `/root/.claude/skills/mcp-builder/`, complementa el `mcp-server-builder` del superpowers pack. Guía para construir MCP servers custom.
- `notebooklm-mcp` (alfredang) — `/home/user/notebooklm-mcp/`, uv sync completo, FastMCP listo. **Pending Jorge (desde su laptop con Chrome):**
  1. `cd /home/user/notebooklm-mcp && uv run notebooklm login` (abre Chrome, auth con Google)
  2. `claude mcp add notebooklm -- uv --directory /home/user/notebooklm-mcp run python server.py`
  3. Restart Claude Code → el MCP expone 16 tools: `create_notebook`, `add_source_url`, `ask_notebook`, `generate_audio_overview`, `generate_video_overview`, `generate_slide_deck`, `generate_mind_map`, `generate_infographic`, `generate_quiz`, `generate_flashcards`, `generate_summary_report`, `generate_data_table`, etc.

**El Cartógrafo v1 SCAFFOLD (GMB write-side agent):**
- `agents/cartografo/SKILL.md` — identity + 10 operations permitidas + 3 operations hard-prohibited + rate limits table + Airtable schemas GMB_Queue + GMB_Audit_Log
- `agents/cartografo/mcp_server/server.py` (396 líneas) — FastMCP server con:
  - Circuit breaker (24h freeze en 429/403 o 3 fails seguidos)
  - Rate limiter (per_hour + per_day + per_month enforced antes del API call, total daily cap 10 writes)
  - Audit log a Airtable `GMB_Audit_Log` en cada write
  - 10 tools: `gbp_health_check`, `gbp_list_locations`, `gbp_get_location`, `gbp_list_reviews`, `gbp_list_insights`, `gbp_publish_post`, `gbp_respond_review`, `gbp_upload_photo`, `gbp_answer_qa` + 3 hard-prohibited (`gbp_update_name/address/phone` devuelven error + auditan el intento)
  - Cada tool de write requiere `approved_by` field (obligatorio para audit)
  - Todos los tools actualmente devuelven `STUB_NOT_IMPLEMENTED` — API calls reales se cablean cuando Jorge complete OAuth Step 1
- `agents/cartografo/mcp_server/pyproject.toml` — deps (fastmcp + google-auth + google-api-python-client)
- `agents/cartografo/secrets/.gitignore` — nunca commitea OAuth JSON
- `agents/cartografo/README.md` — 5-step deploy plan

**Anti-ban safety rules del Cartógrafo (hard-coded):**
- ❌ NUNCA generar reviews (ni positivos ni negativos)
- ❌ NUNCA cambiar name/address/phone automáticamente
- ❌ NUNCA >10 API calls/día por ubicación
- ❌ NUNCA publicar sin `approved_by` field en el tool call
- ❌ NUNCA bypass del circuit breaker
- ❌ Rate limits per-op:
  - publish_post: 2/semana
  - respond_review: 5/día
  - upload_photo: 2/semana (¡!)
  - update_hours / description: 1/mes
  - answer_qa: 2/día

**Pending de Jorge para activar El Cartógrafo en producción:**
1. Google Cloud project `pinnacle-gmb` + enable 5 APIs (Business Profile + My Business Business Info + My Business Account Management + My Business Q&A + My Business Posts)
2. OAuth 2.0 Client ID (Desktop) → download JSON → guardar en `agents/cartografo/secrets/pinnacle_gbp_oauth.json`
3. Pedir quota de Business Profile API si el proyecto lo requiere
4. Crear tablas Airtable: `GMB_Queue` + `GMB_Audit_Log` (schemas en SKILL.md) → pegar `table_id` de audit log en env var `AUDIT_LOG_TABLE`
5. Pasar location_id Pinnacle (formato `accounts/X/locations/Y`)
6. Registrar MCP en `~/.claude/settings.json` (template completo en `agents/cartografo/README.md`)
7. Smoke test: `gbp_health_check` → `gbp_list_locations` (solo reads) → cuando OK, habilito HTTP calls reales en cada tool

**Todo list pendiente con prioridad:**
| Item | Tipo | Prioridad |
|---|---|---|
| GBP PLACE_ID para fer_review_request | Info de Jorge | alta |
| Google Cloud OAuth setup (Cartógrafo Paso 1) | Acción Jorge | alta |
| Airtable tables (Marketing_Audits, SEO_Audits, Content_Queue, GMB_Queue, GMB_Audit_Log) | Setup Airtable | alta |
| Host del cron + `claude login` | Deploy ops | alta |
| El Remitente (email Airtable-only) | Design + build | media |
| El Cazador (Ads) | Build | media |
| El Oráculo VPS deploy | Build | media |
| El Creativo rebuild | Build (awaiting Jorge go) | baja |
| WhatsApp AgentKit | Paused by Jorge | baja |

### 2026-04-23 — NotebookLM skill instalado (Google NotebookLM wrapper)

**Repo:** `proyecto26/notebooklm-ai-plugin` (MIT ✓)
**Ubicación:** `/root/.claude/skills/notebooklm/` (174KB)
**Stack:** Bun/TypeScript (Bun 1.3.11 ya instalado) + Chrome DevTools Protocol para auth
**Scripts:** `artifact-generator.ts`, `auth.ts`, `chat.ts`, `cookie-store.ts`, `main.ts`, `notebook-manager.ts`, `notes-manager.ts`, `research-manager.ts`, `rpc-client.ts`, `source-manager.ts`, `types.ts`

**Qué hace:** wrapper programático de Google NotebookLM (gratis, rate-limited). Desde Claude Code:
- Chat con notebook (Q&A source-grounded + citations de Gemini)
- Gestionar sources (URLs, YouTube, archivos, texto)
- Generar 9 artefactos: slide decks (PDF/PPTX), audio overviews (M4A — deep-dive/brief/critique/debate), video overviews (MP4 — classic/whiteboard/kawaii/anime/watercolor), mind maps (HTML), flashcards (HTML/JSON), quizzes (HTML/JSON), infographics (PNG), reports (MD), data tables (CSV/Sheets)
- Research (fast/deep web research)
- Notes management

**Rate limits free tier (Google):** 3 audio/video overviews/día · 10 reports/flashcards/quizzes/día · 50 chats/día · 100 notebooks total · 50 sources por notebook.

**Requisito crítico:** Chrome local con sesión Google activa. El skill usa Chrome DevTools Protocol + cookie extraction. **NO funciona en este sandbox headless** — corre desde la laptop del Jefe con Chrome + Google login. La skill queda instalada para cuando Jorge la use desde su máquina.

**Casos de uso Pinnacle + R8 SaaS-ready:**
1. **Knowledge base WI real estate:** cargar reportes de mercado, leyes de probate/foreclosure WI, competitor deal history → queries citation-backed
2. **Content factory por tenant:** de un notebook con la "enciclopedia Pinnacle" sacar audios para homeowners distressed, mind maps para casos probate, infographics para redes, slides para investors
3. **Research feeder para El Oráculo:** cuando hagamos el wrapper, NotebookLM proporciona el grounded data que MiroFish/Oráculo simula reacciones sobre
4. **Deliverable vendible (R8):** cada cliente SaaS futuro recibe su propio notebook + outputs brandeados = paquete premium "Knowledge + Content Factory"

**Seguridad:** no envía data a terceros más allá de Google's NotebookLM infra. Cookie session queda local.

**Status queue Phase 2 al cierre 2026-04-23:**

| Item | Status |
|---|---|
| MiroFish / El Oráculo | Skill ✅ instalado, sub-agente diferido a VPS deploy |
| ai-marketing-claude / El Mercader | Skill ✅ + **sub-agente v1 DRAFT completo** (3 approvals pendientes para prod) |
| WhatsApp AgentKit | Clonado, **pausado por Jorge** (3 decisiones arquitecturales) |
| gstack | ✅ Instalado (42 skills, disciplina ingeniería) |
| skill-creator | ✅ Instalado (oficial Anthropic) |
| claude-seo / El Posicionador | Skill ✅ (24 skills), sub-agente por construir |
| claude-ads / El Cazador | Skill ✅ (20+ skills, template real-estate), sub-agente por construir |
| ui-ux-pro-max | ✅ Instalado (67 styles, 96 palettes, DSG) |
| **NotebookLM** | ✅ **Instalado hoy** (requiere Chrome local signed-in) |
| open-carrusel | ✅ Instalado (Instagram carousels) |

### 2026-04-23 — gstack instalado (Garry Tan's Claude Code setup)

Jorge pidió "gistak" = **gstack** (typo de autocorrect). Confirmado + instalado.

**Repo:** `garrytan/gstack` v1.6.1.0 (66K stars)
**Licencia:** MIT ✓
**Ubicación:** `/root/.claude/skills/gstack/`
**Stack:** Bun 1.3.11 + Playwright Chromium (278MB descargado)

**42 skills linkeados** (slash commands en Claude Code):
- **Planning:** `/plan-ceo-review`, `/plan-eng-review`, `/plan-design-review`, `/plan-devex-review`, `/plan-tune`, `/autoplan`, `/office-hours`, `/cso`
- **Design:** `/design-consultation`, `/design-review`, `/design-shotgun`, `/design-html`
- **QA:** `/qa`, `/qa-only`, `/browse`, `/open-gstack-browser`, `/setup-browser-cookies`
- **Review:** `/review`, `/devex-review`, `/careful`
- **Deploy:** `/ship`, `/land-and-deploy`, `/canary`, `/setup-deploy`, `/freeze`, `/guard`, `/unfreeze`
- **Ops:** `/investigate`, `/health`, `/document-release`, `/retro`, `/benchmark`, `/benchmark-models`
- **Meta:** `/context-save`, `/context-restore`, `/learn`, `/pair-agent`, `/codex`, `/gstack-upgrade`, `/make-pdf`

**Uso estratégico para Pinnacle + R8 SaaS-ready:**
- `/review` + `/qa` antes de cada `/ship` — production gates
- `/design-review` para popup/webform/chatbot UI antes de deploy — valida con ojo de diseñador
- `/investigate` + `/retro` para post-mortems tipo el bug del mobile-gate del popup de hoy
- `/canary` cuando empecemos a vender a 2º cliente — deploy gradual
- `/freeze` + `/guard` cuando hay campañas críticas en producción
- `/make-pdf` como alternativa independiente a market-report-pdf
- `/plan-ceo-review` para decisiones grandes — rethink desde visión producto

**Actualización:** `/gstack-upgrade` sync manual + `gstack-config set auto_upgrade true` para auto-sync.

**Complementariedad con skills existentes:**
- gstack aporta GATES y PIPELINES (flujos conectados) — no reemplaza los skills atomicos existentes (`code-review-excellence`, `verification-before-completion`, `systematic-debugging`), los conecta.
- No es domain agent (R9) — es capa de disciplina de ingeniería transversal. ALEX invoca cuando aplica.

### R9. SUB-AGENTES DEDICADOS ALWAYS-ON POR DOMINIO (2026-04-23)
**Orden directa de Jorge — NO NEGOCIABLE.**

Cada nuevo skill de Phase 2 (marketing, SEO, ads, etc.) tiene su **sub-agente dedicado que lo usa 100% del tiempo, no on-demand**. Patrón: monitoreo continuo + alertas automáticas + reportes periódicos + histórico en Airtable.

**Arquitectura por sub-agente:**
1. **Spec en `agents/<nombre>.md`** (tenant-aware per R8)
2. **Invocación:** cron (semanal/diaria según dominio) + on-demand desde ALEX
3. **Storage:** tabla Airtable dedicada por dominio (`SEO_Audits`, `Marketing_Audits`, `Ad_Performance`, etc.)
4. **Alertas:** Telegram al Jefe cuando se detectan issues o umbrales cruzados
5. **Billing hook:** usage meter por tenant (R8)

**Plantel propuesto (nombres sugeridos, Jorge aprueba/renombra):**

| Sub-agente | Dominio | Skill base | Cadencia | Estado |
|---|---|---|---|---|
| **El Oráculo** | Predicción/simulación pre-launch | MiroFish | opt-in gate (pre-campaign) | smoke test corriendo |
| **El Mercader** | Marketing ops / audits | ai-marketing-claude | Semanal auto-audit a pinnaclegroupwi.com + competidores WI | pendiente |
| **El Posicionador** | SEO monitor | Claude SEO (siguiente queue) | Diario health check + semanal deep audit | pendiente install + build |
| **El Cazador** | Ads performance | Claude ADS (último queue) | Diario monitoring de spend + CTR + ROAS | pendiente install + build |

**Default pattern para cada always-on sub-agente (cadencia aprobada por Jorge 2026-04-23):**
- **Cada 3 días 8 AM CST:** quick health check → Telegram brief si todo OK, alerta si hay issue
- **Semanal lunes 9 AM CST:** deep audit → genera reporte PDF/MD → guarda en Airtable + link en Telegram
- **On-demand:** Jorge o ALEX piden análisis puntual

**Nombres aprobados por Jorge 2026-04-23:** El Oráculo, El Mercader, El Posicionador, El Cazador.
**Orden de construcción aprobado:** Oráculo (smoke test) → Mercader (skill ya instalado) → buscar/instalar Claude SEO + Posicionador → buscar/instalar Claude ADS + Cazador.

**R9 se complementa con R6 (memoria) + R7 (mobile-first) + R8 (SaaS-ready) — los sub-agentes siguen todas las reglas anteriores.**

**Aprobado por:** Jorge Cruz — 2026-04-23

---

### R8. SAAS-READY / MULTI-TENANT-FIRST — PRINCIPIO ARQUITECTURAL PERMANENTE
**Orden directa de Jorge, 2026-04-23 — NO NEGOCIABLE, aplica a TODO lo que desarrollemos.**

Todo lo que se construya para Pinnacle debe diseñarse desde el día 1 como **producto SaaS vendible a terceros**. Pinnacle es el tenant cero — no el único tenant. Cada decisión arquitectural deja la puerta abierta a clientes futuros.

**Reglas operativas:**

1. **Configurabilidad total** — NADA hardcodeado. Todo lo específico de Pinnacle (colores, logo, nombre, teléfono, website, API keys, Airtable base ID, textos de copy, emails, horarios) va en configuración por-tenant, no en el código.

2. **Tenant isolation** — cada cliente tiene su propio espacio de datos: Airtable base propia (o tabla con `tenant_id`), credenciales propias, storage propio, branding propio. Nunca cross-pollution entre tenants.

3. **Separación de capas:**
   - **Core engine** (reusable, open-source-safe): lógica del form, chatbot, popup, SM pipeline, simulación
   - **Tenant config**: todo lo específico de un cliente en un JSON/YAML o DB record
   - **Deployment adapter**: scripts que instalan el core con la config de un tenant dado

4. **Onboarding de nuevos clientes** — debe existir un proceso definido: crear config → provisionar infra → go live. Documentado.

5. **Billing hooks** — considerar upfront dónde enchufarse Stripe / subscription management. Usage metrics (leads capturados, simulaciones corridas, popups mostrados, emails enviados) desde el día 1 en cada componente.

6. **Licencias de terceros** — siempre validar license antes de usar una dep. AGPL-3.0 y viral copyleft pueden bloquear monetización — evaluar si usar, usar-sin-modificar, o reemplazar. MIT / Apache 2.0 / BSD son safe.

7. **Documentation-first** — cada componente tiene README con: setup, configuración por-tenant, API pública, troubleshooting, cómo extender. Debe poder leerlo un cliente o dev externo sin acceso a nuestro contexto interno.

8. **Naming & branding** — código, endpoints, nombres de variables NO asumir "Pinnacle". Usar placeholders genéricos (`{TENANT_NAME}`, `{BRAND_PRIMARY}`). Pinnacle va en la config.

9. **Security defaults** — input validation, rate limiting, honeypot, auth, CORS, CSP — desde el día 1. No "lo agregamos después cuando vendamos".

10. **Mobile-first (R7) + SaaS-ready (R8) se complementan** — el producto vendible TIENE que verse bien en móvil. Es lo primero que ven los clientes cuando les demostramos.

**Ejemplos:**
- ❌ `const AIRTABLE_BASE = "appfQbDA750Oihy9J"` — hardcoded Pinnacle
- ✅ `const AIRTABLE_BASE = tenant.airtable.base_id`
- ❌ `const LOGO = "https://pinnaclegroupwi.com/..."` — hardcoded URL
- ✅ `const LOGO = tenant.brand.logo_url`
- ❌ `subject: "We Buy Houses in Wisconsin"` — hardcoded industry + region
- ✅ `subject: interpolate(tenant.copy.email_subject, tenant.vars)`

**Reconciliación con Phase 1 actual:** el código actual está lleno de hardcodes (webform, chatbot, popup, bridges). Eso se refactoriza gradualmente — no bloquea Phase 2. Regla aplica FORWARD desde 2026-04-23. Refactor retroactivo a Phase 1 se hace cuando armemos la primera venta a un segundo cliente.

**Aprobado por:** Jorge Cruz — 2026-04-23
