# SCALABILITY.md — De Single-Tenant a SaaS Multi-Cliente

> Cómo transformar el sistema actual (solo Pinnacle + Geo Carpentry) en una plataforma SaaS que puede servir a 10, 100, 1,000 clientes sin rehacerlo.
> Última actualización: 2026-04-22.

---

## 🎯 OBJETIVO

Pasar de **"Jorge opera su empresa con ALEX"** a **"Jorge vende ALEX a otros empresarios"** manteniendo:
- Separación total de datos entre clientes (multi-tenant aislamiento)
- Onboarding self-serve (cliente configura sus credenciales sin intervención manual)
- Billing automático (Stripe + metering)
- Admin dashboard para Jorge
- Cada cliente ve su marca, no "ALEX"

Timeline: **60-90 días** desde aprobación.

---

## ⚠️ 5 LÍNEAS DE FALLA ACTUALES (identificadas en TASK_MATRIX)

Son los hard-codes que impiden multi-tenant hoy:

| # | Hard-code actual | Multi-tenant needs |
|---|---|---|
| 1 | `AIRTABLE_BASE_ID = 'appfQbDA750Oihy9J'` en todos los workers | Base per cliente, routing dinámico |
| 2 | `FB accountId=25638 + IG=39285` en Programador | Mapping `client_id → accounts` |
| 3 | `TELEGRAM_CHAT_ID = <Jorge>` en notifications | chat_id per cliente |
| 4 | `FER_QUO_FROM_NUMBER_ID = 'PNNlYlSvAb'` en Fer | Número Quo per cliente |
| 5 | Google Calendar service account único | Calendar per cliente via OAuth |

Resolver estos 5 = 80% del trabajo multi-tenant.

---

## 🏛️ ARQUITECTURA MULTI-TENANT PROPUESTA

```
                    ┌─────────────────────────────┐
                    │   CONTROL PLANE (ALEX HQ)   │
                    │  Admin dashboard + billing  │
                    │  Tenant registry + routing  │
                    └──────────┬──────────────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        ▼                      ▼                      ▼
   ┌─────────┐           ┌─────────┐            ┌─────────┐
   │Client A │           │Client B │            │Client N │
   │ tenant  │           │ tenant  │            │ tenant  │
   │         │           │         │            │         │
   │ Own:    │           │ Own:    │            │ Own:    │
   │ Airtable│           │ Airtable│            │ Airtable│
   │ Blotato │           │ Blotato │            │ Blotato │
   │ Quo #   │           │ Quo #   │            │ Quo #   │
   │ Gmail   │           │ Gmail   │            │ Gmail   │
   │ GCal    │           │ GCal    │            │ GCal    │
   │ TG chat │           │ TG chat │            │ TG chat │
   │ Branding│           │ Branding│            │ Branding│
   └─────────┘           └─────────┘            └─────────┘
        ▲                      ▲                      ▲
        └──────────────────────┼──────────────────────┘
                               │ (shared compute)
                    ┌──────────┴──────────────┐
                    │   DATA PLANE (workers)  │
                    │  Stateless agents que   │
                    │  reciben tenant_id y    │
                    │  buscan credenciales    │
                    │  en el registry         │
                    └─────────────────────────┘
```

**Principio:** datos separados por cliente, compute compartido.

---

## 🔑 TENANT REGISTRY (el corazón del multi-tenant)

Una tabla central (Airtable o DB dedicada) con una row por cliente:

```json
{
  "tenant_id": "alexclient_001",
  "business_name": "ABC Real Estate Investments",
  "brand": {
    "primary_color": "#0D3B2E",
    "accent_color": "#C9A84C",
    "logo_url": "https://...",
    "business_phone": "+19207779886",
    "business_email": "deals@abcrei.com"
  },
  "credentials": {
    "airtable_base_id": "appXXXXX",
    "airtable_token": "pat...",
    "blotato_fb_account": 99999,
    "blotato_ig_account": 99998,
    "quo_phone_id": "QUO...",
    "quo_api_key": "...",
    "tracerfy_token": "...",
    "telegram_chat_id": "12345678",
    "google_calendar_id": "abc@group.calendar.google.com",
    "notify_email": "jorge@abcrei.com"
  },
  "subscription": {
    "tier": "growth",
    "monthly_amount": 697,
    "stripe_customer_id": "cus_...",
    "status": "active",
    "trial_ends": null,
    "token_budget_mtd": 8000000,
    "token_used_mtd": 1234567
  },
  "features_enabled": ["fer", "tracy", "social_media", "email_monitor"],
  "features_disabled": ["calendar"],
  "created_at": "2026-05-01",
  "health": "green"
}
```

Cada worker / agente, al procesar una request, hace:
1. Identifica `tenant_id` (del phone number entrante, del webhook URL, del user query)
2. Llama `get_tenant_config(tenant_id)` → obtiene credentials + brand
3. Usa esas credentials para Airtable, Blotato, Quo, etc.

---

## 🔄 CAMBIOS REQUERIDOS POR COMPONENTE

### 1. Airtable (agregar columna `tenant_id`)
- Cada lead, contact, deal, conversation incluye `tenant_id`
- O mejor: **1 base Airtable per cliente** (aislamiento + simpler)
- Cliente recibe su base al onboarding (script la clona desde template)

### 2. Fer AI
- Hoy: webhook único `pinnaclegroupwi.com/Tools/fer_agent.php`
- Mañana: webhook único pero recibe `to_number` → lookup tenant → config
- Número Quo entrante identifica al cliente
- Prompt de Fer: inyectar variables `{{business_name}}`, `{{business_phone}}`, `{{tone}}`

### 3. Web form
- Hoy: single URL `/get-my-offer/` de Pinnacle
- Mañana: cada cliente tiene su subdomain/slug: `alexclient.com/abc-rei/form`
- Form JS lee `window.PNF_TENANT_ID` desde el shell HTML (inyectado por el loader PHP)

### 4. Telegram Bot
- Hoy: 1 bot @Ferpinnaclebot → chat único con Jorge
- Mañana: 1 bot único multi-tenant ("@ALEXSystemBot") donde cada cliente se registra y el bot reconoce chat_ids; OR bot separado por cliente (más seguro, más admin)
- Admin bot aparte para Jorge que monitorea todos

### 5. Social Media
- Mapping `tenant_id → blotato accounts` en registry
- Brand compliance: Creativo y Director leen brand colors del registry
- Airtable SM: 1 base por cliente (o shared con tenant_id)

### 6. Bridges
- `pinnacle_wp_bridge.php` → generalizar a `client_wp_bridge.php?tenant=XXX`
- OR: cada cliente WP tiene su propio bridge (más seguro)
- ALEX_SECRET rotativo per tenant

### 7. Billing (nuevo módulo)
- Stripe webhook handler → marca subscription activa/inactiva
- Metering de deals cerrados atribuibles (via Airtable trigger)
- Fee calculation mensual → Stripe invoice
- Grace period 7 días si falta pago

### 8. Onboarding flow (nuevo)
- Form público "Become an ALEX client"
- Captura: business name, email, phone
- Stripe checkout
- Tras pago → trigger onboarding script:
  1. Crea Airtable base desde template
  2. Crea Quo phone number (via API)
  3. Request cliente conecte Blotato / Gmail / GCal vía OAuth
  4. Crea Telegram chat + invita cliente
  5. Configura WordPress widget/embed code
- Tiempo ideal: **<10 min desde pago a sistema funcional**

---

## 🛠️ MÓDULOS NUEVOS A CONSTRUIR

| Módulo | Responsabilidad | Tiempo estimado |
|---|---|---|
| `admin/dashboard.php` | Jorge ve status de todos los clientes | 1 semana |
| `api/tenant_registry.php` | CRUD del tenant registry (interno) | 3 días |
| `api/onboarding.php` | Flow self-serve de alta | 2 semanas |
| `api/billing/stripe_webhook.php` | Metering + invoicing | 1 semana |
| `api/tenant_resolver.php` | Middleware que inyecta tenant_id en cada request | 3 días |
| `client/embed.js` | Widget JS para que cliente embeba form en su site | 3 días |
| `agents/brand_injector.php` | Inyecta colores/logo/texto del cliente en prompts IA | 2 días |
| `docs/CLIENT_ONBOARDING.md` | Guía paso a paso para cliente nuevo | 2 días |
| `cli/provision_tenant.py` | Script para provisionar cliente (dev/admin) | 3 días |

**Total: ~5-6 semanas de dev para tener MVP multi-tenant.**

---

## 📐 TIER DE FEATURES (qué habilita cada tier)

| Feature | Starter | Growth | Pro | Enterprise |
|---|---|---|---|---|
| AI Receptionist (Fer) | ✅ 1 número | ✅ 2 números | ✅ 5 números | Unlimited |
| Skip tracing (Tracy) | ✅ 50/mes | ✅ 200/mes | ✅ 1000/mes | Custom |
| Deal analysis (Scout+Mat+FC) | — | ✅ 20/mes | ✅ 100/mes | Unlimited |
| Web form (Get My Offer) | ✅ básico | ✅ branded | ✅ white-label | ✅ + custom domain |
| Social Media pipeline | — | ✅ 30 posts/mes | ✅ 100 posts/mes | Unlimited |
| Email monitor (Secretario) | — | ✅ 1 inbox | ✅ 3 inboxes | Unlimited |
| Calendar (Planificador) | — | ✅ | ✅ | ✅ |
| Call Assistant | — | ✅ 2 users | ✅ 5 users | Unlimited |
| API access | — | — | ✅ | ✅ |
| White-label branding | — | — | ✅ | ✅ |
| Multi-location | — | — | 3 locations | Unlimited |
| SLA | Best effort | 99% | 99.5% | 99.9% |
| Support | Email 48h | Email 24h | Priority 4h | Dedicated |
| Token budget | 2M | 8M | 30M | Custom |

Performance fees aplican a todos los tiers igual (escalonado por MRR atribuido).

---

## 🚀 PLAN DE EJECUCIÓN 60-90 DÍAS

### Semanas 1-2 (Fundación)
- Tenant registry (Airtable o Postgres)
- `tenant_resolver.php` middleware
- Refactor de 3 workers críticos (el_polling, fer_agent, social media) para aceptar `tenant_id`

### Semanas 3-4 (Billing + Onboarding)
- Stripe integration (subscriptions + metering)
- Onboarding script self-serve
- Admin dashboard v1 (ver clientes + health)

### Semanas 5-6 (Multi-tenant completo)
- Widget JS embebible (form en sitios de clientes)
- Brand injection en prompts Fer / Social Media
- Telegram bot multi-tenant (o N bots)

### Semanas 7-8 (Polish + beta)
- Legal (Terms of Service, Privacy Policy, DPA)
- Docs públicas (help center)
- Beta cerrada con 3-5 clientes (amigos / Pinnacle network)
- Iteración basada en feedback

### Semanas 9-12 (Launch)
- Ajustes finales de pricing según beta data
- Landing page comercial (distinta al site de Pinnacle)
- Product Hunt launch + campaña paid mínima
- Referral program activo
- Meta: 20 clientes activos al día 90

---

## 🛡️ CONSIDERACIONES DE SEGURIDAD MULTI-TENANT

1. **Aislamiento de datos:** cliente A NO puede ver datos de cliente B. Testing riguroso con E2E tests per tenant.
2. **Credential rotation:** cada cliente puede rotar sus API keys sin afectar a otros.
3. **Rate limiting por tenant:** para que un cliente "ruidoso" no afecte performance de otros.
4. **Audit logs per tenant:** cliente puede ver quién hizo qué con su data.
5. **GDPR / data subject rights:** cliente europeo puede exportar/eliminar su data con 1 click.
6. **Backups aislados:** per tenant, retención 30 días default.
7. **Incident response:** un bug en cliente A no debe caer a cliente B. Circuit breakers.

Detalle de compliance → usar skills `gdpr-dsgvo-expert` + `soc2-compliance` cuando escalemos.

---

## 🏗️ INFRAESTRUCTURA A ESCALAR

### Hoy (single-tenant)
- 1 Hostinger shared hosting (suficiente para Pinnacle solo)
- 1 VPS medium (bot + Secretario)
- 1 Airtable workspace
- Costo infra: ~$150/mes

### Multi-tenant 50 clientes
- Hostinger VPS dedicado (16GB RAM) → **$80/mes**
- VPS principal (upgrade a large, 32GB) → **$120/mes**
- Airtable Enterprise workspace (multi-base) → **$500/mes**
- Redis para session caching → **$15/mes**
- PostgreSQL para tenant registry → **$25/mes** (Neon o Supabase)
- Cloudflare Pro → **$20/mes**
- Monitoring (UptimeRobot + Sentry) → **$50/mes**
- **Total infra: ~$810/mes para 50 clientes = $16/cliente**

### Multi-tenant 500 clientes
- Migración a AWS/GCP multi-region → ~$3,000/mes
- $6/cliente en infra — excelente unit economics

---

## 📈 KPIs POST-LAUNCH

Semanales:
- Nuevos signups / Churn / Active clients
- MRR / ARR / Performance fees collected
- Claude tokens per tenant average
- Time to first deal (TTFD) — cuánto tarda un cliente nuevo en cerrar su primer deal con el sistema
- NPS

Mensuales:
- Gross margin
- CAC (customer acquisition cost)
- LTV (lifetime value)
- LTV:CAC ratio (target > 3)

---

## 🔗 REFERENCIAS

- Arquitectura base → `docs/ARCHITECTURE.md`
- Tareas y handoffs → `docs/TASK_MATRIX.md` sección "5 líneas de falla"
- Pricing → `docs/COMMERCIALIZATION.md`
- Costos → `docs/COST_OPTIMIZATION.md`
