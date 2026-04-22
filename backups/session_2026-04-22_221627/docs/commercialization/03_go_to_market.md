# F3 — Go-To-Market

> Plan de 90 días para lanzar comercialmente: segmentos target, canales de adquisición, sales playbook, KPIs.
> Parte de `docs/COMMERCIALIZATION.md`.

---

## 🎯 SEGMENTOS TARGET (orden de prioridad)

### Segmento 1: Real Estate Flippers (PRIORIDAD 1)
- **Perfil:** 1-10 flips/año, facturación $100k-$2M/año
- **Tamaño mercado US:** ~100,000 flippers activos
- **Pain:** pierde leads por no contestar rápido; skip tracing caro; follow-up manual roto
- **ICP (ideal customer profile):**
  - Wisconsin/Midwest inicial (expansión nationwide)
  - 1-5 empleados
  - Gasta $500-3,000/mes en herramientas separadas
  - Usa Facebook Ads o direct mail
- **Canal primario:** Facebook Groups (WI Real Estate Investors, BiggerPockets WI)
- **Gancho:** "Answer every lead in 30 seconds, bilingual, while you're on a flip"

### Segmento 2: Wholesalers (PRIORIDAD 1)
- **Perfil:** 5-30 deals/mes, volumen alto bajo margen
- **Tamaño mercado US:** ~50,000 wholesalers
- **Pain:** follow-up 24-touch imposible manual; skip trace en volumen es caro
- **ICP:** operación >$500k/año revenue, usa CRM tipo Podio o InvestorFuse
- **Canal primario:** BiggerPockets forums + podcasts (Wholesaling Inc, FlipNerd)
- **Gancho:** "Automate the 24-touch follow-up that closes deals — we built it already"

### Segmento 3: Real Estate Agencies (PRIORIDAD 2)
- **Perfil:** 5-50 agentes, volumen mixto compra/venta
- **Pain:** cada agente contesta sus propios leads (inconsistente); sin automation
- **ICP:** agencia independiente (no franquicia), $1-10M GCI
- **Canal primario:** LinkedIn outbound + trade shows (NAR, state associations)
- **Gancho:** "Your agents focus on closing. ALEX handles the rest."

### Segmento 4: Contractors / Home Services (PRIORIDAD 3 — secundario)
- **Perfil:** como Geo Carpentry (carpentry, roofing, HVAC, etc.)
- **Pain:** missed calls = missed jobs; no follow-up sistemático
- **ICP:** $200k-$2M/año revenue, 1-15 employees
- **Canal primario:** Houzz, Angi, Nextdoor Business ads + local chambers
- **Gancho:** "Every missed call is a missed $5k job. ALEX books them while you work."

### Segmento 5: Property Managers (PRIORIDAD 4 — expansión)
- **Perfil:** 20-500 units, necesita screen tenants + maintenance requests
- **Pain:** tenants reclaman tarde, screening manual lento
- **Canal primario:** NARPM + state apartment associations
- **Gancho:** "Screen tenants + handle maintenance requests — bilingual, 24/7"

---

## 📣 CANALES DE ADQUISICIÓN (mix)

### Orgánicos (baja-cero costo, alta conversion)
1. **Jorge's own network** — Pinnacle contacts, LinkedIn, local WI investor groups
2. **Content marketing** — blog posts SEO ("How to answer real estate leads 24/7", "Bilingual wholesaling tips")
3. **YouTube** — demos, case studies (Pinnacle usando ALEX en vivo)
4. **BiggerPockets** — forum answers + free tools / guides
5. **Referral program** — 20% MRR por 12 meses al referidor
6. **Case studies** — Pinnacle como primer case, Geo Carpentry como B2B-contractor case

### Paid (inversión escalable)
7. **Facebook Ads** — audience lookalikes de flipper/wholesaler groups
8. **Google Ads** — keywords "real estate AI receptionist", "automated skip tracing"
9. **LinkedIn Ads** — para Agencies + Property Managers (outbound)
10. **Podcast sponsorships** — Wholesaling Inc, BiggerPockets Podcast

### Partnerships
11. **Real estate coaches** — affiliate deal 30% first year MRR (target: 5-10 coaches)
12. **Integration partners** — Podio, GoHighLevel, Zapier marketplaces
13. **Local REIA chapters** — sponsorship + monthly demo presentations

---

## 📅 90-DAY LAUNCH PLAN

### Semana 1-2 — Foundation
- [ ] Tenant registry + multi-tenant refactor (ver `docs/SCALABILITY.md`)
- [ ] Stripe integration (subscriptions + performance metering)
- [ ] Admin dashboard v1
- [ ] Landing page (distinto al site Pinnacle)

### Semana 3-4 — Content + Assets
- [ ] Escribir 10 blog posts SEO (usar skill `ai-seo`)
- [ ] Grabar 3 demos video (Fer en acción, Tracy skip trace, análisis deal)
- [ ] Setup Google Ads + FB Ads accounts
- [ ] Docs públicas (help center + getting-started guides)

### Semana 5-6 — Beta Cerrada
- [ ] Reclutar 5 clientes beta (Jorge's network) — **gratis** por 2 meses a cambio de feedback + testimonials
- [ ] Onboarding manual con cada uno
- [ ] Iterar diario según feedback (fix bugs, ajustar copy, UX)
- [ ] Validar métricas de success (TTFD — time to first deal)

### Semana 7-8 — Beta Abierta
- [ ] Lista de espera abierta pública
- [ ] 20 slots a 50% descuento primeros 3 meses (early adopter lock-in precio)
- [ ] Primer case study publicado (Pinnacle)
- [ ] Post en BiggerPockets + WI groups

### Semana 9-10 — Launch Público
- [ ] Product Hunt launch (día viernes, equipo coordinado para votos)
- [ ] LinkedIn + Twitter announcement Jorge + network
- [ ] Press release a Inman News, Real Deal, RISMedia
- [ ] FB Ads live ($1,000 budget inicial)
- [ ] Google Ads live ($500 budget inicial)

### Semana 11-12 — Scale & Iterate
- [ ] Analizar CAC por canal, doblar apuesta en ganadores
- [ ] Webinar semanal "ALEX 101" para leads
- [ ] Referral program lanzado
- [ ] **Meta final 90 días: 20 clientes activos pagando**

---

## 🎬 SALES PLAYBOOK

### Lead stages
1. **Awareness** → ve ad/post/referral
2. **Interest** → visita landing, ve demo video
3. **Consideration** → agenda demo 30min (calendly o directo desde landing)
4. **Decision** → demo + trial + purchase
5. **Onboarding** → setup + first deal
6. **Expansion** → upgrade tier o add-ons

### Demo call (30min) estructura
1. **Min 0-5** — descubrimiento: "Cuéntame tu operación actual"
2. **Min 5-15** — pain dig: "Dónde pierdes deals? Cuánto tiempo en follow-up?"
3. **Min 15-22** — demo vivo: Fer contestando SMS en vivo + dashboard Airtable
4. **Min 22-27** — objeciones: precio, data security, integración
5. **Min 27-30** — cierre: "Vamos al trial? Te mando el link ahora"

### Objection handling

**"Too expensive"**
→ "ROI: nuestro cliente promedio Growth factura $2,447/mo y genera $35k en deals. Es 14x. Qué precio te haría comfortable start con Starter?"

**"I don't trust AI with my leads"**
→ "Completo control: cada escalation va a tu Telegram, revisas antes de responder si prefieres. Empezamos en 'supervised mode' por 7 días."

**"I already have a CRM"**
→ "ALEX no reemplaza tu CRM, lo alimenta. Airtable sync bidireccional. Podemos integrar con tu sistema existente en onboarding."

**"What if you go out of business?"**
→ "Data export 1-click. Own tu Airtable base. Credentials son tuyas. Podrías migrar en 30 min si quisieras."

**"I need to think about it"**
→ "Totally get it. Qué específicamente te detiene? Precio, setup time, o el tipo de leads que manejamos?"

---

## 📊 KPIs DE GROWTH

### Semanales (dashboard Jorge)
| Métrica | Target Mes 1 | Target Mes 2 | Target Mes 3 |
|---|---|---|---|
| Landing page visitors | 500 | 2,000 | 5,000 |
| Trial signups | 5 | 20 | 50 |
| Demo calls booked | 3 | 10 | 25 |
| Paid conversions | 2 | 8 | 20 |
| Active clients (total) | 2 | 10 | 20 |
| MRR | $500 | $4,500 | $12,000+ |

### Mensuales
- CAC por canal (target: <$500 blended)
- Trial → paid conversion (target: >25%)
- Churn rate (target: <5%/mes primer año)
- NPS (target: >40 mes 3, >50 mes 6)
- Time to first deal — TTFD (target: <30 días)
- LTV:CAC ratio (target: >3:1, ideal >10:1)

### Alertas
- **Rojo:** CAC > $1,500 o churn > 10% → pause ads, investigate
- **Amarillo:** trial→paid <15% → iterar onboarding
- **Verde:** todas las KPIs en target → acelerar inversión

---

## 💰 BUDGET INICIAL (90 días)

| Item | Mes 1 | Mes 2 | Mes 3 | Total |
|---|---|---|---|---|
| FB Ads | $500 | $1,500 | $3,000 | $5,000 |
| Google Ads | $300 | $1,000 | $2,000 | $3,300 |
| LinkedIn Ads | $0 | $500 | $1,000 | $1,500 |
| Content (blog, video) | $500 | $500 | $500 | $1,500 |
| Tools (Ahrefs, HubSpot free, etc.) | $200 | $200 | $200 | $600 |
| Press release services | $0 | $300 | $0 | $300 |
| Beta client credits (2 meses gratis × 5) | — | — | — | $0 (lost rev, not cash) |
| **Total cash outlay** | **$1,500** | **$4,000** | **$6,700** | **$12,200** |

ROI breakeven si logramos los 20 clientes = ~$12,000 MRR → 2 meses payback.

---

## 🏁 SUCCESS CRITERIA POST-90-DÍAS

**Minimum viable success (Go/No-Go)**
- 10 clientes activos pagando
- $5,000+ MRR
- CAC blended < $800
- Churn < 10%
- At least 2 clientes en Growth tier o superior (validación pricing)

**Aspiracional**
- 20 clientes
- $12,000+ MRR
- 3-5 case studies filmados
- 1 integración partner confirmada
- Lista de espera con 100+ emails

Si no llegamos a "minimum viable" → análisis profundo pricing/positioning antes de seguir gastando. Si llegamos → aceleramos a $50k MRR en 6 meses adicionales.

---

## 🔗 RELACIONADOS

- Pricing completo → `docs/commercialization/01_pricing_model.md`
- Features por tier → `docs/commercialization/02_product_packaging.md`
- Scalability técnica → `docs/SCALABILITY.md`
- Cost structure que soporta → `docs/COST_OPTIMIZATION.md`
