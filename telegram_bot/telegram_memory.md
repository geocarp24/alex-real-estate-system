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
