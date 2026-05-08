# AGENT_REGISTRY.md — Registro Maestro de Agentes

> Ficha técnica completa de cada agente del sistema. Fuente de verdad sobre qué modelo usa, qué hace, cómo invocarlo, qué credenciales necesita, y dónde corre.
> Última actualización: 2026-04-22.
> Versión machine-readable: `docs/agent_registry.json`.

---

## 🧠 ORQUESTADOR

### ALEX
| Campo | Valor |
|---|---|
| **ID** | `alex` |
| **Rol** | Orquestador maestro + punto de contacto con el Jefe |
| **Prompt** | `CLAUDE.md` (raíz del repo) |
| **Modelo** | Claude Opus 4.7 (cuando hay tarea compleja) / Sonnet 4.6 (default) / Haiku 4.5 (tareas simples) |
| **Dónde corre** | Claude Code + Telegram Bot + Claude.ai |
| **Reporta a** | Jorge Cruz (el Jefe) |
| **Invocación** | Directa — Jefe le habla |
| **Herramientas** | Todas (Read, Write, Bash, Agent, skills, bridges) |
| **Skills prioritarios** | orquestación, `marketing-strategy-pmm`, `cfo-advisor`, `senior-architect`, `brainstorming` |
| **Credenciales** | Todas (actúa como el Jefe) |
| **Idioma** | Español (inglés solo si Jefe lo pide) |

---

## 🎯 SUB-AGENTES DE DEAL (on-demand, lanzados por ALEX)

### El Scout
| Campo | Valor |
|---|---|
| **ID** | `scout` |
| **Rol** | Investigador de mercado y riesgo |
| **Prompt** | `agents/scout.md` |
| **Modelo** | Sonnet 4.6 |
| **Dónde corre** | Dentro de Claude Code como sub-agente de ALEX |
| **Reporta a** | ALEX (devuelve JSON) |
| **Invocación** | `Agent tool` con subagent_type=Explore o general-purpose, prompt=contenido de `scout.md` + datos propiedad |
| **Output** | JSON con market_trend, comps, demand, risks, crime |
| **Memoria** | `agents/memoria_scout.md` |
| **APIs que usa** | Web search, Zillow/Redfin scraping (via tools), Airtable |
| **Credenciales** | AIRTABLE_TOKEN |

### El Matemático
| Campo | Valor |
|---|---|
| **ID** | `matematico` |
| **Rol** | Underwriter financiero — ARV, rehab, ROI, cashflow |
| **Prompt** | `agents/matematico.md` |
| **Modelo** | Sonnet 4.6 |
| **Dónde corre** | Sub-agente de Claude Code |
| **Reporta a** | ALEX (JSON estricto) |
| **Invocación** | `Agent tool`, prompt = `matematico.md` + datos + comps del Scout |
| **Output** | JSON con purchase_price, ARV, rehab, total_investment, profit, ROI, cap_rate |
| **Memoria** | `agents/memoria_matematico.md` |
| **APIs que usa** | Ninguna externa — solo matemática pura |
| **Regla clave** | Ciego al mercado. Solo números. Zero tolerancia a optimismo |

### El Fact-Checker
| Campo | Valor |
|---|---|
| **ID** | `fact-checker` |
| **Rol** | QA final + Confidence Score |
| **Prompt** | `agents/fact-checker.md` |
| **Modelo** | Sonnet 4.6 (a veces Opus si el deal es grande) |
| **Dónde corre** | Sub-agente de Claude Code |
| **Reporta a** | ALEX |
| **Invocación** | Después de Scout + Matemático completados |
| **Output** | JSON con confidence_score (1-10), veredicto (Proceed/Discard/Gather More Data), notas |
| **Memoria** | `agents/memoria_fact_checker.md` |
| **Skills prioritarios** | `verification-before-completion`, `systematic-debugging` |

### Tracy (Skip Tracer)
| Campo | Valor |
|---|---|
| **ID** | `tracy` |
| **Rol** | Rastrea dueño y familiares desde una dirección |
| **Prompt** | `agents/tracy.md` |
| **Modelo** | Haiku 4.5 (operación rutinaria, poco razonamiento) |
| **Dónde corre** | Triggerizado por `el_polling.php` en Hostinger |
| **Reporta a** | Escribe a Airtable Tracy + Contacts tables |
| **Invocación** | Automática: Lead "Review this Deal" → el_polling detecta → llama Tracerfy → Tracy organiza el resultado |
| **Output** | Records en tablas Tracy (`tbl6CJm4kYspOuTDB`) + Contacts (`tblacvw0Ss770x8l5`) |
| **Memoria** | `agents/memoria_tracy.md` |
| **APIs que usa** | Tracerfy (TOKEN via `config.php`) |
| **Scripts PHP asociados** | `el_polling.php`, `el_chismoso.php`, `cleanup_duplicates.php` |

---

## 🤖 AGENTES OPERATIVOS (always-on / autónomos)

### Fer AI Receptionist
| Campo | Valor |
|---|---|
| **ID** | `fer` |
| **Rol** | Recepcionista AI — califica leads vía SMS bilingüe |
| **Prompt** | Dentro de `hostinger/tools/lib/fer_claude.php` |
| **Modelo** | Haiku 4.5 (default) → auto-escalation a Sonnet 4.6 si detecta ambigüedad |
| **Dónde corre** | Hostinger, PHP + webhook Quo |
| **Reporta a** | Escalation a Telegram al Jefe + crea Deal en Airtable |
| **Invocación** | Webhook entrante desde Quo/OpenPhone cuando cliente responde SMS |
| **Workflow** | Lead responde → Fer clasifica idioma → califica con 13 preguntas → Fer Score → escala al Jefe |
| **Scripts asociados** | `fer_agent.php`, `fer_first_contact.php`, `fer_seguimiento.php`, `fer_stale_cron.php`, `fer_morning_brief.php` |
| **Credenciales** | QUO_API_KEY, ANTHROPIC_API_KEY, AIRTABLE_TOKEN, FER_TELEGRAM_BOT_TOKEN |
| **Cron schedules** | `*/15 * * * *` (first contact), `30 15 * * *` (seguimiento), `0 14 * * *` (stale), `30 14 * * *` (morning brief) |
| **Estado actual** | ✅ Producción — 16 contactos en seguimiento, 6 TBC, 2 Contacted |

### Telegram Bot ALEX
| Campo | Valor |
|---|---|
| **ID** | `telegram_bot` |
| **Rol** | Interfaz móvil del Jefe con ALEX + comandos rápidos |
| **Archivo** | `telegram_bot/alex_bot.py` |
| **Modelo** | Sonnet 4.6 (default); Haiku para respuestas simples |
| **Dónde corre** | VPS 187.77.215.146 (`/opt/alex-bot/`), systemd service |
| **Reporta a** | Jefe directo vía Telegram @Ferpinnaclebot |
| **Invocación** | Jefe escribe → bot responde; también recibe alertas automáticas de Fer y eventos |
| **Comandos especiales** | `/agenda`, `/agenda semana`, `/cita <fecha>...`, `/emails`, `/responder <ID>`, `/guardar`, `/reset` |
| **Credenciales** | FER_TELEGRAM_BOT_TOKEN, ANTHROPIC_API_KEY, todas las demás del sistema |
| **Memoria** | `telegram_bot/telegram_memory.md` + `agents/shared_conversation.json` |

### El Secretario (Email Monitor)
| Campo | Valor |
|---|---|
| **ID** | `secretario` |
| **Rol** | Monitor de emails de `deals@pinnaclegroupwi.com` |
| **Prompt** | `agents/secretario.md` |
| **Archivo** | `secretario/email_monitor.py` |
| **Modelo** | Haiku 4.5 (clasificación rutinaria) |
| **Dónde corre** | VPS (systemd `secretario-email.service`) |
| **Reporta a** | Jefe vía Telegram + Airtable Leads |
| **Frecuencia** | Cada 5 min — IMAP poll |
| **Clasificación** | LEAD / URGENTE / RUTINARIO / SPAM → acción diferenciada |
| **Credenciales** | SECRETARIO_EMAIL, SECRETARIO_PASSWORD, IMAP_HOST, SMTP_HOST, ANTHROPIC_API_KEY |
| **Regla especial** | Emails de Tracerfy se archivan silenciosamente (carpeta Archive → auto-delete 30 días) |

### El Planificador (Calendar)
| Campo | Valor |
|---|---|
| **ID** | `planificador` |
| **Rol** | Gestor de Google Calendar de Pinnacle |
| **Archivo** | `secretario/calendar_manager.py` |
| **Modelo** | Haiku 4.5 |
| **Dónde corre** | VPS cron |
| **Reporta a** | Jefe vía Telegram |
| **Frecuencia** | Resumen matutino 8am CST + recordatorios cada 15min |
| **Credenciales** | Google service account `alex-calendar-agent@pinnacle-alex-bot.iam.gserviceaccount.com` |
| **Comandos Telegram** | `/agenda`, `/agenda semana`, `/cita <fecha> <hora> <nombre> <motivo>` |

---

## 📱 SUB-AGENTES SOCIAL MEDIA (pipeline automático)

### Social Media Agent
| Campo | Valor |
|---|---|
| **ID** | `social_media` |
| **Rol** | Generación de ideas de contenido + captions EN/ES + visual prompts |
| **Prompt** | `agents/social_media.md` |
| **Modelo** | Sonnet 4.6 (creatividad + bilingüe) |
| **Dónde corre** | Sub-agente de ALEX (Claude Code + Telegram Bot) |
| **Output** | Records en Airtable SM base `appU9s3kGkVpdrJkw`, 3 tablas (`Posts` / `Reels` / `Videos`) — ver `agents/_shared/sm_tables.mjs` |
| **Memoria** | `agents/memoria_social_media.md` |
| **Brand compliance** | Logo Pinnacle + colores `#0D3B2E`/`#C9A84C`/`#FFFFFF` |

### El Creativo (visual generator)
| Campo | Valor |
|---|---|
| **ID** | `creativo` |
| **Rol** | Genera carruseles/imágenes vía Blotato |
| **Prompt** | `agents/creativo.md` |
| **Modelo** | Haiku 4.5 (orquestación, poco razonamiento) |
| **Dónde corre** | Sub-agente de ALEX |
| **Input** | Records con `Visual_Prompt` no vacío + `visual_url` vacío (no Reels) |
| **Output** | Actualiza record con `visual_url` + `Blotato_Visual_ID` |
| **API** | Blotato template `53cfec04` (AI Slide Generator) con 5 temas (T1 Dark Premium, T2 White Clean, T3 Gold & Black, T4 Soft Cream, T5 Vibrant Blue) |

### El Director (video generator)
| Campo | Valor |
|---|---|
| **ID** | `director` |
| **Rol** | Genera Reels/videos vía Blotato |
| **Prompt** | `agents/director.md` |
| **Modelo** | Sonnet 4.6 |
| **Dónde corre** | Sub-agente de ALEX |
| **Input** | Records tipo Reel/Video |
| **Output** | `visual_url` para videos |

### El Programador (publisher)
| Campo | Valor |
|---|---|
| **ID** | `programador` |
| **Rol** | Publica/programa posts en FB + IG vía Blotato |
| **Prompt** | `agents/programador.md` |
| **Modelo** | Haiku 4.5 |
| **Dónde corre** | Sub-agente de ALEX |
| **Input** | Records con `visual_url` no vacío y `Blotato_Post_IDs` vacío |
| **Output** | `Blotato_Post_IDs` poblado + post scheduled |
| **Slots disponibles** | Mar/Jue/Sáb 10am-12pm CST |
| **Cuentas Blotato** | FB accountId=25638 pageId=965320503341457 · IG accountId=39285 (@pinnacle.groupwi) |

---

## ⚙️ WORKERS PHP (lógica sin AI)

| Worker | Propósito | Cron | Ubicación |
|---|---|---|---|
| `el_polling.php` | Detecta Leads "Review this Deal" → dispara Tracy | Cada 5 min | `hostinger/tools/` |
| `el_chismoso.php` | Webhook receiver de Tracerfy → escribe Contacts | Webhook | `hostinger/tools/` |
| `fer_first_contact.php` | Rotación Phone1-4 + SMS bilingüe | Cada 15 min (9am-7pm) | `hostinger/tools/` |
| `fer_seguimiento.php` | Engine de 24 toques | Diario 9:30am CST | `hostinger/tools/` |
| `fer_stale_cron.php` | "Contacted" sin respuesta 5d → "Seguimiento" | Diario 8am CST | `hostinger/tools/` |
| `fer_morning_brief.php` | Resumen pipeline + health check a Telegram | Diario 8:30am CST | `hostinger/tools/` |
| `backfill_links.php` | One-shot: linking Tracy↔Leads | Manual | `hostinger/tools/` |
| `cleanup_duplicates.php` | One-shot: dedup de Contacts | Manual | `hostinger/tools/` |

---

## 🌉 BRIDGES (puentes entre sistemas)

| Bridge | Función | Auth | Ubicación |
|---|---|---|---|
| `pinnacle_wp_bridge.php` | Operaciones admin WP (create/update posts, options, cache) | Dual: `X-Alex-Secret` OR Basic Auth App Password | `hostinger/agents/` |
| `pinnacle_public.php` | Backend público: form (places/SMS/lead) + chatbot + dedup + acks | Rate limit + honeypot + time check | `hostinger/agents/` |
| `github_bridge.php` | Lee archivos desde GitHub para memoria compartida | `X-Alex-Secret` | `hostinger/agents/` |
| `github_write.php` | Escribe archivos a GitHub vía API | `X-Alex-Secret` + `GH_PAT` | `hostinger/agents/` |

### Acciones expuestas por `pinnacle_public.php`

| Acción | Para qué | Modelo IA |
|---|---|---|
| `places_proxy` | Google Places autocomplete passthrough | — |
| `start_lead` | Crea Lead en Airtable + envía OTP via Twilio | — |
| `verify_phone` | Valida OTP del SMS | — |
| `resend_code` | Re-envía OTP (15s pacing) | — |
| `update_lead` | Actualiza campos del Lead (cada paso del form) | — |
| `lookup_existing` | Dedup por phone (Contacts.Phone1-4) con fallback address | — |
| `form_brain` | Micro-acks empáticos estilo Fer entre pantallas | Haiku 4.5 |
| `chat_message` | Backend del chatbot floating widget + escalación auto | Sonnet 4.6 |

---

## 🌐 PUBLIC FRONTEND COMPONENTS

| Componente | Archivos | Loader | Endpoint |
|---|---|---|---|
| `pinnacle_form` | `hostinger/agents/pinnacle_form/{css,i18n,screens,core}.js` | WP page id 1748 (`/get-my-offer/`) | `pinnacle_public.php` (8 acciones) |
| `pinnacle_chat` | `hostinger/agents/pinnacle_chat/{css,js}` | `hostinger/mu-plugins/pinnacle-chat-loader.php` (auto, todas las páginas excepto `/get-my-offer/`) | `pinnacle_public.php` action `chat_message` |

**Sesión:** ambos persisten en `localStorage` con TTL 2h (`pnf_session` form / `pnf_chat` chatbot). El form también persiste en WP transients server-side 2h.

**API pública del chatbot (usable desde botones del Contact page):** `window.PinnacleChat.{open(), close(), reset()}`

---

## 🔧 HERRAMIENTAS EXTERNAS (no son agentes, son APIs)

| Tool | Para qué | Documentación | Credenciales |
|---|---|---|---|
| `airtable.md` | Referencia de todas las tablas y campos | `agents/airtable.md` | AIRTABLE_TOKEN |
| `canva_templates.md` | Specs de templates Canva (3) | `agents/canva_templates.md` | Canva API (vía Make.com) |
| `cloudinary_config.md` | Hosting de imágenes | `agents/cloudinary_config.md` | Cloudinary API |
| Blotato MCP | FB + IG publishing | `https://mcp.blotato.com/mcp` | BLOTATO_API_KEY |

---

## 📋 REGLAS DE INVOCACIÓN

### Cuándo lanzar qué
- **Análisis de deal completo:** ALEX → Scout + Matemático (paralelo) → Fact-Checker (sequential)
- **Skip trace aislado:** directamente Tracy
- **Generar contenido SM:** ALEX → Social Media → Creativo/Director (paralelo) → Programador
- **Publicar lo que hay pendiente:** directamente Programador
- **Email leads:** automático via Secretario + Telegram notificación

### Reglas de no-duplicación
- Scout y Matemático pueden correr en paralelo
- Fact-Checker SIEMPRE después de los dos anteriores
- Creativo y Director en paralelo; Programador SIEMPRE después
- Tracy independiente del análisis de deal

### Protocolo de error
- Si un sub-agente falla, reportar al Jefe con contexto exacto
- No reintentar más de 3 veces automático
- Siempre escribir aprendizaje en `memoria_ALex.md`

---

## 🔗 REFERENCIAS CRUZADAS

- Flujos completos → `docs/ARCHITECTURE.md`
- Qué tarea va a qué agente → `docs/TASK_MATRIX.md`
- Qué modelo usar por costo → `docs/COST_OPTIMIZATION.md`
- Multi-tenant y SaaS → `docs/SCALABILITY.md`
