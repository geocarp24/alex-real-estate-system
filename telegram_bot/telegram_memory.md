# MEMORIA DE CONVERSACIONES — ALEX Telegram Bot

Este archivo contiene resúmenes automáticos de las sesiones de Telegram.
Se actualiza con /guardar o /reset. ALEX lo lee al inicio de cada sesión.

---

## 2026-04-22 — Sesión Claude Code (resumen para continuidad)

Jorge cerró la fase pública del sitio Pinnacle. Quedaron en producción:
- Webform `/get-my-offer/` multi-step (EN/ES, dedup por phone con fallback a address, flujo returning-user, session resume 2h via localStorage, Fer brain micro-acks empáticos)
- Chatbot Fer-style flotante en todas las páginas excepto el form (con escalación automática a Telegram cuando detecta lead caliente)
- Contact page rediseñada: 5 métodos (Phone, Email, Visit, Online Form, Chat)
- CTAs del sitio ("Get My Free Offer") apuntando todos a `/get-my-offer/`
- Bug de email reply corregido en `secretario/email_monitor.py` (resolución 3-tier: Reply-To > From > body scan)

Backend: `hostinger/agents/pinnacle_public.php` (~600 líneas) expone `lookup_existing`, `form_brain`, `chat_message` (acciones nuevas).

Documentación completa: `docs/{ARCHITECTURE,AGENT_REGISTRY,TASK_MATRIX,COST_OPTIMIZATION,SCALABILITY,COMMERCIALIZATION}.md` + 3 sub-docs de comercialización (pricing, packaging, GTM).

Protocolo de ejecución de 7 fases en `agents/PROTOCOLO_EJECUCION.md` (cargar al inicio de cada sesión).

Si Jorge retoma desde Telegram, el contexto está en `memoria_ALex.md` raíz, sección "2026-04-22 — SESIÓN COMPLETA".

---

## 2026-04-22 — MODO /GOD (permanente)

Orden directa de Jorge — NO NEGOCIABLE — aplica a TODOS los modelos (Opus/Sonnet/Haiku) y TODOS los entornos (Telegram, Claude Code, Claude.ai, sub-agentes).

ALEX opera siempre en modo `/GOD`: skills-first sin excusas + cost-benefit (tokens+tiempo) + surgical edits + diagnose-before-code + luz verde permanente en stack Pinnacle.

Antes de cualquier acción no trivial: evaluar qué skill aplica e invocarlo vía tool `Skill`. Default en duda: invocar.

Skills de activación automática:
- Bug → `systematic-debugging`
- Antes de "listo" → `verification-before-completion`
- Código nuevo → `test-driven-development`
- Post-cambio → `simplify`
- Multi-paso → `writing-plans`/`executing-plans`
- Creative → `brainstorming`
- 2+ tareas paralelas → `dispatching-parallel-agents`

Detalle completo en `CLAUDE.md` sección "MODO /GOD — OPERACIÓN PERMANENTE".

**Confirmación obligatoria al inicio de sesión (Telegram incluido):** *"Modo /GOD activo, skills-first habilitado, memoria always-on, luz verde permanente en stack Pinnacle."*

### Skill de memoria SIEMPRE ACTIVO (toda la vida, sin excusas)
El bot de Telegram debe leer al arrancar: `memoria_ALex.md`, `agents/memoria_alex.md`, `agents/shared_conversation.json` (últimos 60), `telegram_memory.md`, `agents/PROTOCOLO_EJECUCION.md`. Escribir inmediato cuando Jorge apruebe regla/lección. Escribir al cierre resumen datado en las 3 memorias conjuntas. Skill designado: `self-improving-agent`.

### MOBILE-FIRST — PRIORIDAD #1 PERMANENTE (2026-04-23)
Todo el trabajo de Pinnacle se optimiza mobile-first como prioridad #1. Mayor tráfico web hoy = mobile. Aplica a popups, formularios, páginas, chatbot, emails, social creatives, CTAs, imágenes, cualquier componente nuevo. NUNCA excluir mobile por viewport sin consultar al Jefe. Test mobile PRIMERO, desktop después. Detalle completo en `memoria_ALex.md` regla R7 y `CLAUDE.md` sección 1b.

### SAAS-READY / MULTI-TENANT-FIRST (2026-04-23)
Todo se construye como producto vendible a terceros. Pinnacle = tenant cero. Reglas: nada hardcodeado (todo por-tenant config), tenant isolation, separación core/config/deployment, onboarding documentado, billing hooks upfront, validar licencias deps (AGPL no-go para mono core, MIT/Apache safe), security defaults día 1, naming genérico. Detalle completo en `memoria_ALex.md` regla R8 y `CLAUDE.md` sección 1c.

---

## 2026-04-28 — Sesión Claude Code — Fix spam Supervisor

Jorge reportó "el auditor me está enviando mensajes a cada rato y en fila". Causa: El **Supervisor deep mode** (cada 1h) alertaba a Telegram cada vez que había warnings, y el warning "seg_sms_sent stale 40h" (falso positivo crónico) generaba 24 mensajes idénticos/día.

**Fix aplicado:** dedup 24h en `agents/supervisor/supervisor.mjs` — compara warning-set contra runs deep en ventana 24h, suprime si idéntico, notifica si cambió.

**Falso positivo del warning:** verificado en Airtable que `Last contact date=2026-04-28` y `SMS Sent=true` → el reloj suizo Hostinger SÍ corre. El warning aparece porque no hay contactos due en Seguimiento (5 en stage, ninguno necesita toque hoy). El log no registra `seg_sms_sent` cuando no hay nada que enviar — el threshold dispara warning falso. Mejora futura: hacer threshold dinámico según pipeline real.

**Memoria desync detectado:** `shared_conversation.json` congelado en 2026-04-06. Bot Telegram en VPS escribe el archivo localmente pero nunca pushea a git. Por eso al abrir Claude Code, el JSON está stale. Memoria canonical sigue siendo `memoria_ALex.md` raíz (actualizada hasta hoy). Pendiente decisión arquitectónica: bot auto-push vs cron VPS→repo sync vs deprecar el JSON.

Resto del estado al cierre 2026-04-23 sigue válido — ver `memoria_ALex.md` sección "2026-04-23 NIGHT" para plantel R9 (10 agentes) y crons activos (17 GHA + 4 Hostinger = 21 jobs).

---

## 2026-04-28 PM — FASE 1 SUPERVISOR AUTÓNOMO

Jorge aprobó visión de convertir El Supervisor en agente auto-curativo, auto-mejorable y autosuficiente. Roadmap 5 fases (1=memoria · 2=confidence + LLM diagnosis · 3=auto-fix expandido + rollback · 4=self-modification propose-only · 5=auto-merge whitelist).

**Fase 1 implementada hoy (no-destructiva):**
- Tabla `Lessons_Learned` en Airtable (`tbloCtdxSukBI3R3j`) — síntoma + categoría + outcome + occurrence_count + recommended_action.
- Módulo Learning en `supervisor.mjs`: cada warning/critical observado se registra (CREATE primera vez, INCREMENT recurrencias).
- Recognition: classifier con 5 categorías (infra/pipeline/code/data/unknown).
- Normalizer compartido con dedup de alertas — variantes "stale 40h"/"stale 38h" colapsan al mismo lesson.
- Failure-tolerant: si la tabla no existe, supervisor sigue corriendo.

**Próximas fases requieren aprobación explícita.** El sistema todavía NO toca código solo, NO hace fixes nuevos, NO mergea PRs. Solo aprende.

---

## 2026-04-28/29 — FASE 2 SUPERVISOR AUTÓNOMO

Aprobada y implementada inmediatamente. Construido:

- **LLM Diagnosis** con Sonnet 4.6 (Anthropic API directa) → root_cause + recommended_action + requires_human + action_category por cada lesson recurrente.
- **Confidence Scoring** determinístico — 0 si requires_human o sin fixes; sube por resolved consecutive; HARD FLOOR 0 ante worsened reciente.
- **Decision Layer** — HIGH (>=0.9) auto-apply candidate | MED propone+alerta | LOW escala a humano.
- **Force-alert** para HIGH/MED — rompe dedup porque propuesta nueva = info nueva.
- Costo: ~$0.60/día/tenant.

Phase 2 SIGUE siendo no-destructiva: auto_apply es FLAG para que Fase 3 actúe, no acción inmediata.

**Pendientes:** Fase 3 (auto-fix + rollback + outcome recording), Fase 4 (self-modification propose-only), Fase 5 (auto-merge — siempre decisión humana).

---
