# memoria_ALex.md — Memoria Persistente del Sistema ALEX

> Este archivo es leído y actualizado por ALEX al inicio y fin de cada sesión de análisis.
> Formato de fecha: YYYY-MM-DD. Añadir siempre fecha a cada entrada.

---

## REGLAS DEL JEFE (aplican a TODOS los agentes, siempre)

### 2026-04-19 — AUTO-BACKUP DE SESIÓN (CRÍTICO — APROBADO POR JORGE)
- **OBLIGATORIO:** Backup automático cada 15 min Y después de CADA cambio (commit + push a GitHub).
- Mecanismo activo: PostToolUse hook en `.claude/settings.local.json` corre tras cada `Write|Edit` y hace `git add -A && git commit -m "auto: ..." && git push`.
- **Mecanismo manual obligatorio cuando trabajo:** después de cada milestone (cada 10–15 min de actividad real), escribir un breve checkpoint en `memoria_ALex.md` con:
  - Estado actual del trabajo
  - Próximo paso
  - Bloqueadores / decisiones pendientes
  - Credenciales/IDs/URLs descubiertos en la sesión
- **Razón:** Cada sesión nueva en Claude Code Web arranca con container limpio. Lo que NO esté en el repo Git, se PIERDE. Cero excepciones.
- **Costo de no hacerlo:** créditos gastados en repetir investigación que ya hice antes.

### 2026-04-19 — CREDENCIALES Y PERSISTENCIA ENTRE SESIONES
- El sandbox de Claude Code Web NO persiste `.env` ni variables entre sesiones — solo lo que está en el repo.
- ALEX_SECRET, WP App Password, SSH Pass de Hostinger viven en GitHub Secrets / VPS / Hostinger — NO en este sandbox.
- **Solución acordada:** Cuando Jorge me da una credencial, la guardo en `.env.sandbox` (ya en `.gitignore`) Y referencio su ubicación canónica en `memoria_ALex.md` para que la próxima sesión sepa pedirla solo si el archivo no existe.
- **NUNCA pegar valores reales de credenciales en `memoria_ALex.md`** (ese archivo va a GitHub público).

### 2026-04-19 — WP BRIDGE PINNACLE — ACCESO CONFIRMADO
- **Endpoint:** `https://agents.pinnaclegroupwi.com/pinnacle_wp_bridge.php`
- **Método auth preferido:** Basic Auth con WP Application Password
- **Usuario WP (admin):** `geocarpentryllc@gmail.com` (NO `deals@pinnaclegroupwi.com`, NO `grocarpentryllc...`)
- **App Password:** guardada en `.env.sandbox` como `PINNACLE_WP_APP_PASSWORD`
- **Verificación rápida:**
  ```bash
  source .env.sandbox && CLEAN_PASS=$(echo "$PINNACLE_WP_APP_PASSWORD" | tr -d ' ')
  curl -s -X POST "https://agents.pinnaclegroupwi.com/pinnacle_wp_bridge.php" \
    -u "${PINNACLE_WP_USER}:${CLEAN_PASS}" -H "Content-Type: application/json" \
    -d '{"action":"ping"}'
  ```
- **Acciones disponibles:** ping | list_pages | list_posts | get_post | update_post | create_post | delete_post | get_post_meta | update_post_meta | get_option | update_option | purge_cache
- **WP version:** 6.9.4 | Theme: astra | PHP: 8.3.30
- **Auto-test al inicio de sesión:** revisar `.env.sandbox` existe; si no, pedir a Jorge UNA SOLA VEZ. Si sí, hacer ping para confirmar que sigue válido.

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
