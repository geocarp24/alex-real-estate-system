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
