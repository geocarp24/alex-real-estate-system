# F2 — Product Packaging

> Qué features incluye cada tier, cómo se empaqueta el producto, cómo lo diferencia de competencia.
> Parte de `docs/COMMERCIALIZATION.md`.

---

## 🎯 FRAMEWORK DE PACKAGING

Diseñado con 3 ejes de valor — cliente elige tier basado en el que más le importa:

1. **Volumen** — cuántos leads / deals / posts procesa al mes
2. **Sofisticación** — qué agentes IA avanzados tiene acceso
3. **Control** — branding, API, multi-user, white-label

Cada upgrade de tier aumenta los 3 ejes simultáneamente para forzar upgrade paths claros.

---

## 📊 MATRIZ DE FEATURES POR TIER

| Feature | Starter $297 | Growth $697 | Pro $1,497 | Enterprise $3,500+ |
|---|:---:|:---:|:---:|:---:|
| **🤖 Fer AI Receptionist (SMS bilingüe)** | 1 número | 2 números | 5 números | ∞ |
| **🕵️ Tracy Skip Tracing** | 50/mes | 200/mes | 1,000/mes | Custom |
| **📊 Deal analysis (Scout+Mat+FC)** | — | 20/mes | 100/mes | ∞ |
| **📝 Web form "Get My Offer"** | Básico | Branded | White-label | Custom domain |
| **📱 Social Media pipeline (FB+IG)** | — | 30 posts/mes | 100 posts/mes | ∞ |
| **📧 El Secretario (email monitor)** | — | 1 inbox | 3 inboxes | ∞ |
| **📅 Calendar manager (GCal)** | — | ✅ | ✅ | ✅ |
| **📞 Call Assistant** | — | 2 users | 5 users | ∞ |
| **🔗 API access (REST)** | — | — | ✅ | ✅ + webhooks |
| **🎨 White-label branding** | — | — | ✅ | ✅ + custom UI |
| **🏢 Multi-location** | — | — | 3 locations | ∞ |
| **📈 Analytics dashboard** | Básico | Avanzado | Advanced + cohorts | Custom BI |
| **🛡️ SLA uptime** | Best effort | 99% | 99.5% | 99.9% |
| **🎯 Support** | Email 48h | Email 24h | Priority 4h | Dedicated CSM |
| **💰 Claude token budget** | 2M/mes | 8M/mes | 30M/mes | Custom |
| **👥 Team seats** | 1 | 5 | 20 | ∞ |
| **🔒 SSO / SAML** | — | — | ✅ | ✅ + SCIM |
| **📜 Audit logs** | 7 días | 30 días | 1 año | 7 años |
| **🌐 Languages** | EN/ES | EN/ES | EN/ES + custom | EN/ES + translate any |

---

## 🎁 ADD-ONS (extras comprables separadamente)

Para cuando cliente quiere UN feature de tier superior sin upgrade completo:

| Add-on | Precio | Disponible en |
|---|---|---|
| Extra Quo phone number | +$30/mo | Todos |
| +100 skip traces | +$50/mo | Starter, Growth |
| +50 social posts | +$100/mo | Growth |
| Extra inbox monitor | +$40/mo | Growth, Pro |
| Custom branded domain | +$15/mo (one-time setup $50) | Pro |
| Priority deployment | +$200 one-time | Growth, Pro |
| Training sesión 2h | $497 | Todos |
| Custom agent prompt | $1,000 one-time | Pro, Enterprise |
| Additional team seat | +$25/mo/seat | Growth, Pro |

---

## 🏗️ ONBOARDING FLOW (cliente nuevo)

### Día 0 — Pago y self-serve setup (target: 10 minutos)
1. Cliente elige tier en landing page
2. Stripe checkout → confirma email
3. Sistema provisiona automático:
   - Airtable base desde template
   - Quo phone number asignado
   - Telegram bot invitation
   - Credenciales entregadas via dashboard
4. Welcome email con video 5min "Get started"

### Día 1-3 — Kickoff call (incluida en Setup Fee $997)
- Session 2h con Jorge (Starter/Growth) o CSM (Pro/Enterprise)
- Review goals cliente, configurar Fer prompt tone, integrar Blotato/Gmail/GCal
- Configurar primer form embed en su site
- Entregar access a dashboard + docs

### Día 4-7 — Primer lead vivo
- Cliente recibe primer lead real
- Sistema envía Fer SMS → conversación → escalation
- CSM sigue en paralelo para resolver fricciones
- Meta: **primer deal cerrado antes del día 30**

### Día 30 — First check-in
- Review metrics del primer mes
- Ajustar Fer prompt si tone/length no encaja
- Upsell cross-features si cliente está maximizando tier

### Día 60 — Money-back window closes
- Si el cliente sigue activo = convertido
- Si no generamos deals → refund full (pre-agreed)

### Día 90 — Success milestone
- Case study opportunity (con permiso)
- Referral program activation
- Upgrade conversation si corresponde

---

## 🥊 COMPETENCIA + DIFERENCIADORES

### Comparación vs alternativas del mercado

| Producto | Precio | Qué hace | Lo que le falta | Nuestro ventaja |
|---|---|---|---|---|
| **Call Porter AI** | $199-999/mo | Answering service AI | Sin CRM ni skip trace | Pipeline completo integrado |
| **REI BlackBook** | $247-697/mo | CRM para real estate | Sin AI receptionist ni social | IA nativa + social automation |
| **BatchLeads** | $97-297/mo | Lead gen + skip trace | Sin conversación AI | Conversación bilingüe + follow-up |
| **Podium** | $400-600/mo | Text messaging + reviews | Genérico, no real estate | Específico RE + multi-agente |
| **GoHighLevel** | $97-497/mo | All-in-one marketing | Complejo, requiere configuración alta | Setup plug-and-play con agentes pre-configurados |
| **Investor Fuse** | $97-247/mo | CRM wholesale-focused | Sin AI receptionist | Fer + Tracy integrados out-of-box |

### Nuestros 5 diferenciadores clave
1. **Bilingüe nativo EN/ES** — 60% mercado RE en WI/TX/CA tiene componente hispano; competencia no lo cubre
2. **Pipeline COMPLETO en 1 producto** — otros venden CRM, o AI, o skip trace; nosotros = todo
3. **Agentes autónomos** — Fer contesta 24/7 sin intervención, otros requieren humano en loop
4. **Performance fee alineado** — competencia cobra flat; nosotros ganamos solo si cliente gana
5. **Setup <10 minutos** — self-serve real; competencia requiere 1-4 semanas implementación

---

## 🎨 BRANDING + POSITIONING

### Tagline (opciones para test A/B en beta)
- "The AI team that sells houses while you sleep"
- "From lead to closed deal — automated, bilingual, 24/7"
- "Your entire back office, in one AI-powered platform"
- "Stop losing leads. Start closing deals."

### Proposition de valor por persona

**Para el flipper part-time:**
> "Your first AI employee. Answer every lead instantly, in English or Spanish, while you're at your day job."

**Para el wholesaler activo:**
> "Scale to 10 deals/mo without hiring. Your AI team handles qualification, follow-up, and social media."

**Para la agency:**
> "The back office your competitors can't afford. Multi-location. Multi-agent. Multi-user. One subscription."

**Para el contractor (Geo Carpentry-type):**
> "Turn every missed call into a job. Your AI receptionist books estimates while you work."

---

## 📦 PACKAGING EN LA LANDING

### Hero
- **H1:** "The AI team that runs your real estate business"
- **Sub:** "Fer answers calls. Tracy finds contacts. ALEX analyzes deals. You close them."
- **CTA:** "Start Free 14-Day Trial" (no credit card, gated)

### Social proof section (una vez tengamos beta clients)
- 3 logos clientes beta
- 1 case study quote por persona (flipper / agency / contractor)
- Stat: "X deals closed through ALEX system in [timeframe]"

### Pricing section
- 3 tiers side-by-side (Starter / Growth / Pro)
- "Enterprise" como CTA separado
- Toggle monthly/annual
- Feature matrix expandible
- FAQ below (qué pasa si no cierro deals, data ownership, etc.)

### Integrations
- Logo grid: Airtable, Stripe, Quo/OpenPhone, Google, FB, IG, Blotato
- "More integrations coming" badges

---

## 🚪 TRIAL STRATEGY

**14 días free trial** (con card, auto-converts):
- Todas las features de Growth por 14 días
- 1 número Quo asignado
- 10 skip traces incluidos
- 5 social posts permitidos
- Sin performance fee (trial = solo subscription después)

**Por qué con tarjeta:** free sin card tiene 60% tire-kickers. Con card: 85% conversion al paid.

**Success gate:** si en 14 días cliente genera al menos 1 lead pasando a Contacted, trial es exitoso → auto-convert a Starter. Si no, 7 días extra con CSM outreach.

---

## 🔁 UPSELL / CROSS-SELL PATHS

### De Starter → Growth
- Disparador: cliente usa > 80% de skip trace budget
- Message: "You're outgrowing Starter. Growth gives you 4x more skip traces + Deal Analysis + Social Media"
- Descuento: upgrade sin proration penalty

### De Growth → Pro
- Disparador: cliente agrega 3er usuario, o pide API access
- Message: "Scale your team. Pro includes 20 seats, API, and white-label for your brand."

### De Pro → Enterprise
- Disparador: cliente alcanza $150k MRR atribuible O menciona multi-location
- Message: "Let's build this for your whole operation. Dedicated infra + custom agents."

---

## 🔗 RELACIONADOS

- Pricing detallado → `docs/commercialization/01_pricing_model.md`
- Go-to-market y distribución → `docs/commercialization/03_go_to_market.md`
- Features técnicos que soportan cada tier → `docs/AGENT_REGISTRY.md`
