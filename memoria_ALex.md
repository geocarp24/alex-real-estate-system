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

### Acción pendiente:
- Agregar BLOTATO_API_KEY al .env del VPS
- Configurar Make.com con Blotato para publicación automática SM



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
