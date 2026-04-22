# ARCHITECTURE.md — Sistema ALEX

> Mapa maestro del sistema multi-agente que alimenta Pinnacle Holdings (real estate),
> Geo Carpentry (contractor) y la futura plataforma SaaS comercial.
> Última actualización: 2026-04-22.

---

## 🎯 PROPÓSITO DEL SISTEMA

Un **sistema multi-agente IA** que automatiza operaciones de negocios de real estate / contractors:

1. **Captura de leads** (web form, SMS, llamada) →
2. **Calificación automática** (AI Receptionist Fer + skip trace Tracy) →
3. **Análisis financiero** (Scout + Matemático + Fact-Checker) →
4. **Seguimiento automatizado** (24-touch cadence multi-canal) →
5. **Cierre asistido** (Call Assistant + Property Inspector) →
6. **Marketing continuo** (Social Media pipeline bilingüe automático)

Todo orquestado por **ALEX**, el agente maestro.

---

## 🏗️ ARQUITECTURA EN 3 CAPAS

```
┌─────────────────────────────────────────────────────────────────────┐
│  CAPA 1 — INTERFACES DE USUARIO                                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │   WordPress  │  │   Telegram   │  │   SMS / Call │              │
│  │  /get-my-    │  │    Bot       │  │ (Quo/OpenPh) │              │
│  │   offer/     │  │  @Ferpinna-  │  │              │              │
│  │  (form JS)   │  │   clebot     │  │              │              │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘              │
└─────────┼─────────────────┼─────────────────┼──────────────────────┘
          │                 │                 │
          ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│  CAPA 2 — ORQUESTADOR + AGENTES IA                                  │
│                                                                     │
│                    ┌──────────────────┐                             │
│                    │      ALEX        │  ← punto de contacto       │
│                    │   (Orquestador)  │    con el Jefe             │
│                    └─────┬──────┬─────┘                             │
│             ┌────────────┘      └────────────┐                      │
│             ▼                                ▼                      │
│  ┌──────────────────────┐         ┌─────────────────────┐          │
│  │ SUB-AGENTES DE DEAL  │         │ SUB-AGENTES DE OPS  │          │
│  │ (on-demand)          │         │ (always-on)         │          │
│  ├──────────────────────┤         ├─────────────────────┤          │
│  │ • Scout (mercado)    │         │ • Fer (receptionist)│          │
│  │ • Matemático (UW)    │         │ • Secretario (email)│          │
│  │ • Fact-Checker (QA)  │         │ • Planificador (cal)│          │
│  │ • Tracy (skip trace) │         │ • Telegram Bot      │          │
│  └──────────────────────┘         └─────────────────────┘          │
│                                                                     │
│             ┌────────────┐         ┌──────────────┐                 │
│             │ SUB-AGENTES│         │  WORKERS PHP │                 │
│             │ SOCIAL     │         │ (cron jobs)  │                 │
│             ├────────────┤         ├──────────────┤                 │
│             │ • Social   │         │ • el_polling │                 │
│             │   Media    │         │ • el_chismoso│                 │
│             │ • Creativo │         │ • fer_*cron  │                 │
│             │ • Director │         │              │                 │
│             │ • Program- │         │              │                 │
│             │   ador     │         │              │                 │
│             └────────────┘         └──────────────┘                 │
└─────────────────────────────────────────────────────────────────────┘
          │                 │                 │
          ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│  CAPA 3 — INFRAESTRUCTURA + DATOS                                   │
│                                                                     │
│  ┌───────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ ┌──────────┐ │
│  │ Airtable  │ │ Hostinger│ │   VPS    │ │ GitHub  │ │ External │ │
│  │ (2 bases) │ │ (PHP+WP) │ │ 187.77.* │ │ Actions │ │   APIs   │ │
│  │           │ │          │ │ (Python) │ │  (CI/CD)│ │          │ │
│  │ CRM +     │ │ Fer +    │ │ Bot +    │ │ Deploy  │ │ Claude,  │ │
│  │ Social    │ │ Tracy +  │ │ Secreta- │ │ Memoria │ │ Tracerfy,│ │
│  │ Media     │ │ Web form │ │ rio      │ │ sync    │ │ Quo,     │ │
│  └───────────┘ └──────────┘ └──────────┘ └─────────┘ │ Blotato, │ │
│                                                      │ Google,  │ │
│                                                      │ Make.com │ │
│                                                      └──────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🔄 FLUJOS DE DATOS PRINCIPALES

### Flujo 1 — CAPTURA DE LEAD (web form)
```
Cliente → /get-my-offer/ (18 pantallas) → pinnacle_public.php
       → Airtable Leads (Stage: "Review this Deal", Source: "Website Form")
       → el_polling.php (cron 5min) detecta → dispara Tracy skip trace
       → el_chismoso.php escribe Contacts
       → fer_first_contact.php (cron 15min) envía SMS bilingüe
       → Fer AI conversa → califica → Escalation a Telegram al Jefe
       → Deal creado en Airtable auto
```

### Flujo 2 — ANÁLISIS DE DEAL (ALEX orquesta)
```
Jefe → ALEX (Telegram o Claude Code) → "analiza esta propiedad"
     → ALEX lanza en paralelo:
          Scout (mercado/comps/riesgo) ────┐
          Matemático (ARV/rehab/ROI) ──────┼──→ ALEX consolida
                                           │
          Fact-Checker (audita ambos) ─────┘
     → ALEX presenta "Análisis Estándar de Deal" con Confidence Score
     → ALEX escribe aprendizaje en memoria_ALex.md
```

### Flujo 3 — SOCIAL MEDIA PIPELINE (automático)
```
Jefe → ALEX → "genera contenido semana X"
     → Social Media Agent → genera ideas + Caption EN/ES + Visual_Prompt
                         → guarda en Airtable SM (tbl...)
     → El Creativo → lee records sin visual → genera carrusel con Blotato
     → El Director → lee records de Reels → genera video con Blotato
     → El Programador → lee records con visual_url → publica FB+IG
     → Reporta al Jefe resumen final (todo automático, sin preguntas)
```

### Flujo 4 — EMAIL MONITOR (El Secretario, 24/7)
```
deals@pinnaclegroupwi.com → IMAP Hostinger (cada 5 min)
     → El Secretario clasifica (Claude Haiku): Lead | Urgente | Rutinario | Spam
     → LEAD → Airtable + Telegram al Jefe
     → URGENTE → Telegram al Jefe
     → Spam/Tracerfy → archiva silencioso
     → Regla: archivados >30 días se eliminan auto
```

### Flujo 5 — SEGUIMIENTO 24-TOUCH
```
Lead "Contacted" sin respuesta 5 días → fer_stale_cron → "Seguimiento" Stage
     → fer_seguimiento.php (diario 9:30am) itera contactos
     → 24 toques SMS+Email durante 12 meses
     → Step ≥ 24 → Stage "Dead"
     → Si responde en el camino → Fer retoma sin repetir
```

---

## 🌐 ENDPOINTS DE ACCESO (URLs reales)

| URL | Propósito | Auth |
|---|---|---|
| `https://pinnaclegroupwi.com/` | Home pública (WP) | Público |
| `https://pinnaclegroupwi.com/get-my-offer/` | Form multi-step | Público |
| `https://pinnaclegroupwi.com/agents/pinnacle_public.php` | Backend form | Rate-limit + honeypot |
| `https://agents.pinnaclegroupwi.com/pinnacle_wp_bridge.php` | Admin WP | Basic Auth (App Password) |
| `https://pinnaclegroupwi.com/Tools/Pinnacle_Call_Assistant.html` | Call worker tool | Session login |
| `https://pinnaclegroupwi.com/Tools/fer_agent.php` | Fer webhook SMS | Quo signature |
| `https://pinnaclegroupwi.com/Tools/fer_diag.php?token=...` | Fer diagnóstico | Token en URL |
| `https://t.me/Ferpinnaclebot` | Telegram bot | chat_id Jefe |

---

## 🔐 CREDENCIALES (dónde viven, nunca expuestas en git)

| Servicio | Ubicación canónica | Variable |
|---|---|---|
| Airtable tokens | `hostinger/tools/config.php` + GitHub Secrets | `AIRTABLE_TOKEN` |
| Claude API key | GitHub Secrets + VPS `.env` | `ANTHROPIC_API_KEY` |
| WP App Password | `.env.sandbox` (gitignored) | `PINNACLE_WP_APP_PASSWORD` |
| SSH Hostinger | GitHub Secrets + VPS `.env` | `SSH_HOST`, `SSH_PASSWORD`, `SSH_PORT` |
| Quo/OpenPhone | GitHub Secrets | `QUO_API_KEY` |
| Tracerfy | `hostinger/tools/config.php` | `TRACERFY_TOKEN` |
| Telegram Bot | VPS `.env` + GitHub Secrets | `FER_TELEGRAM_BOT_TOKEN` |
| Blotato MCP | VPS `.env` | `BLOTATO_API_KEY` |
| Google OAuth | `secretario/google_creds/` | `GOOGLE_CLIENT_ID`, etc. |

**Regla oro:** nunca hardcodear en outputs. Cada sesión nueva debe encontrar las credenciales en `.env.sandbox` o pedirlas UNA vez al Jefe y guardarlas persistente (ver `PROTOCOLO_EJECUCION.md` Fase 1).

---

## 📦 DÓNDE CORRE CADA COSA

| Componente | Servidor | Path | Trigger |
|---|---|---|---|
| Sitio WordPress | Hostinger (compartido) | `~/domains/pinnaclegroupwi.com/public_html/` | HTTP request |
| Fer AI + workers PHP | Hostinger | `~/domains/.../public_html/Tools/` | Cron + webhook |
| Web form backend | Hostinger | `~/domains/.../public_html/agents/` | HTTP POST |
| Telegram Bot ALEX | VPS 187.77.215.146 | `/opt/alex-bot/` (systemd service) | Always-on |
| El Secretario Email | VPS (mismo) | `/opt/alex-bot/secretario/` | systemd `secretario-email.service` |
| El Planificador | VPS (mismo) | `/opt/alex-bot/secretario/` | Cron alexuser |
| GitHub Actions | GitHub (cloud) | `.github/workflows/*.yml` | Push to master / scheduled |
| ALEX prompts | GitHub repo `geocarp24/alex-real-estate-system` | `agents/*.md` | Lectura en cada invocación |

---

## 🔗 DEPENDENCIAS EXTERNAS (proveedores)

| Proveedor | Para qué | Plan actual | Riesgo si falla |
|---|---|---|---|
| Anthropic (Claude) | Todos los agentes IA | Pay-per-token | Sistema queda mudo |
| Hostinger | Web + PHP + MySQL | Shared hosting | Site y Fer offline |
| VPS (not specified) | Python bots + Secretario | Medium VPS | Bot Telegram + Email offline |
| GitHub | Código + memoria + CI/CD | Free tier | Deploys bloqueados, no afecta runtime |
| Airtable | CRM + Social DB | Team plan | Sin datos = sistema inerte |
| Tracerfy | Skip trace | Per-call | Tracy no funciona |
| Quo/OpenPhone | SMS + voice | Business | Fer no envía SMS |
| Blotato | FB + IG scheduler | Monthly | Social media stopped |
| Google Cloud | Places API + Calendar | Pay-as-you-go | Autocomplete + calendar offline |

---

## 🛣️ ROADMAP A PRODUCTO COMERCIAL (60-90 días)

El sistema actual es **single-tenant** (solo Pinnacle + Geo Carpentry). Para comercializarlo necesitamos:

- **Multi-tenant** — cada cliente tiene su propio Airtable / base / números Quo / cuentas Blotato
- **Onboarding self-serve** — formulario de alta que automatiza credenciales
- **Billing automation** — Stripe + metering por deals cerrados
- **Admin dashboard** — para ver status de N clientes a la vez
- **White-label** — cada cliente ve su marca, no "ALEX"
- **SLA + monitoring** — uptime + alertas por cliente

Detalle completo en `docs/SCALABILITY.md` y `docs/COMMERCIALIZATION.md`.

---

## 📚 DOCUMENTOS HERMANOS (navegación)

| Doc | Qué responde |
|---|---|
| `docs/AGENT_REGISTRY.md` | Ficha técnica de cada agente (modelo, skills, credenciales, cómo invocar) |
| `docs/TASK_MATRIX.md` | Qué tareas asignar a qué agente (handoffs, no-dos) |
| `docs/COST_OPTIMIZATION.md` | Jerarquía Haiku/Sonnet/Opus, budget proyectado, reglas de ahorro |
| `docs/SCALABILITY.md` | Multi-tenant architecture para SaaS |
| `docs/COMMERCIALIZATION.md` | Pricing, packaging, go-to-market, competencia |
| `agents/PROTOCOLO_EJECUCION.md` | 7 fases obligatorias para no romper nada |
| `agents/protocolo_seguro.md` | Reglas de seguridad + anti-prompt-injection |
| `memoria_ALex.md` | Memoria persistente + reglas del Jefe + log de cambios |
