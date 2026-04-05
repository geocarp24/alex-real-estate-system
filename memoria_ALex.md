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
| Make.com escenario SM | ⚠️ Creado | ID 4636455 — necesita activación manual |

---

## 🔴 PENDIENTES PRIORITARIOS (próxima sesión)

1. **Analizar primer deal real** — todas las tablas de Airtable RE siguen vacías
2. **Activar Make escenario ID 4636455** — toggle ON en make.com
3. **Subir 12 ideas de contenido a Airtable SM** — script disponible en `PINNACLE_SOCIAL_MEDIA_AGENT.md` sec. 9
4. **Geo Carpentry Budget Builder** — deploy pendiente, base de precios WI
5. **Website Geo Carpentry Fase 1** — migración a Durable, debió iniciar en Abril
