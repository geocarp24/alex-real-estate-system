# memoria_ALex.md — Memoria Persistente del Sistema ALEX

> Este archivo es leído y actualizado por ALEX al inicio y fin de cada sesión de análisis.
> Formato de fecha: YYYY-MM-DD. Añadir siempre fecha a cada entrada.

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

*Última actualización: 2026-04-05*

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
### 2026-04-05 17:19 — Tarea ejecutada por GitHub Monitor
**Tarea:** Responde EXACTAMENTE esto: MONITOR GITHUB ACTIVO - Sistema de monitoreo 24/7 funcionando. Detecté esta tarea desde task_queue.json en GitHub.
**Resultado:** MONITOR GITHUB ACTIVO - Sistema de monitoreo 24/7 funcionando. Detecté esta tarea desde task_queue.json en GitHub.



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
