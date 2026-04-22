# COMMERCIALIZATION.md — Plan Comercial del Sistema ALEX

> Plan maestro para convertir el sistema multi-agente ALEX en un producto SaaS comercial.
> Timeline aprobado: **60-90 días** al producto completo (ver `docs/SCALABILITY.md` para plan técnico).
> Pricing model aprobado por Jorge 2026-04-22.
> Última actualización: 2026-04-22.

---

## 📋 ESTRUCTURA DE ESTE PLAN

Dividido en 3 documentos focalizados + este índice maestro:

| # | Documento | Contenido |
|---|---|---|
| **F1** | [`commercialization/01_pricing_model.md`](commercialization/01_pricing_model.md) | Subscription tiers, performance fees, ejemplos reales, unit economics (CAC/LTV), términos contractuales |
| **F2** | [`commercialization/02_product_packaging.md`](commercialization/02_product_packaging.md) | Feature matrix por tier, add-ons, onboarding flow, comparación competidores, diferenciadores, trial strategy |
| **F3** | [`commercialization/03_go_to_market.md`](commercialization/03_go_to_market.md) | Segmentos target, canales de adquisición, plan 90 días semana-por-semana, sales playbook, KPIs, budget |

---

## 🎯 RESUMEN EJECUTIVO

### Producto
Sistema multi-agente IA para real estate investors, wholesalers, agencies y contractors. Automatiza captura de leads, calificación bilingüe (EN/ES) vía SMS, skip tracing, análisis financiero, follow-up de 24 toques, social media, email y calendar.

### Pricing
- **Subscription:** $297 / $697 / $1,497 / Enterprise mensual
- **Performance fee:** 2-5% sobre ganancia de deals atribuibles (0% si MRR<$10k)
- **Setup fee:** $997 one-time
- **Annual discount:** 2 meses gratis (16.6% OFF)

### Target
5 segmentos, prioridad:
1. Flippers (100k en US)
2. Wholesalers (50k en US)
3. Agencies
4. Contractors
5. Property Managers (expansión)

### Canales
Orgánicos (Jorge network, BiggerPockets, YouTube) + Paid (FB Ads, Google Ads, LinkedIn) + Partnerships (RE coaches, REIA chapters).

### Timeline
- **Semanas 1-4:** foundation + content + assets
- **Semanas 5-6:** beta cerrada (5 clientes)
- **Semanas 7-8:** beta abierta (20 slots)
- **Semanas 9-10:** launch público (Product Hunt + press)
- **Semanas 11-12:** scale & iterate
- **Meta día 90:** 20 clientes activos, $12k+ MRR

### Unit Economics
- CAC target: **<$500 blended**
- LTV estimado: **$33,750**
- LTV:CAC: **67:1** (outlier, indica posible price increase)
- Payback: **~2.2 meses**
- Gross margin: **75%**
- Budget 90 días: **$12,200** — breakeven mes 2-3

---

## 🏁 SUCCESS CRITERIA POST-90-DÍAS

### Mínimo viable (Go/No-Go)
- 10 clientes activos pagando
- $5,000+ MRR
- CAC < $800
- Churn < 10%
- ≥2 clientes en Growth o superior

### Aspiracional
- 20 clientes
- $12,000+ MRR
- 3-5 case studies filmados
- 1 integración partner confirmada
- 100+ emails en waitlist

---

## 🚩 RIESGOS PRINCIPALES

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| Producto complejo para self-serve | Media | Setup fee $997 incluye 2h training + CSM |
| CAC > LTV | Baja | Pivot a canales orgánicos si paid no funciona |
| Cliente esconde deals atribuibles | Media | Onboarding firma + Stripe visibility + ventana 90d clara |
| Competencia baja precio agresivamente | Media | Nuestro diferenciador es integración + bilingüe, no precio |
| Costo Claude sube | Baja | 25% buffer en margen + prompt caching + batch API |
| Regulación cambia (TCPA, SMS) | Baja | Quo/OpenPhone compliance + consent en form |
| Churn alto si no genera deals rápido | Alta | Money-back 60 días + CSM proactivo en primer mes |

---

## 🏗️ DEPENDENCIAS TÉCNICAS PREVIAS

Para poder ejecutar este plan, la plataforma necesita (de `docs/SCALABILITY.md`):

1. ✅ Sistema actual funciona single-tenant (Pinnacle + Geo Carpentry)
2. ⏳ Multi-tenant refactor (5-6 semanas)
3. ⏳ Stripe integration (1 semana)
4. ⏳ Onboarding self-serve (2 semanas)
5. ⏳ Admin dashboard (1 semana)
6. ⏳ Widget JS embebible (3 días)
7. ⏳ Brand injection en prompts (2 días)
8. ⏳ Legal (Terms, Privacy, DPA) (1 semana)

**Total dev pre-launch: ~5-6 semanas** — cabe en el plan 90 días (4 semanas foundation + 6 semanas go-to-market).

---

## 🎨 BRANDING (pendiente de decisión)

ALEX es nombre interno. Necesitamos nombre comercial antes de lanzar. Candidatos a explorar:

- **Propzy AI** (catchy, claim dominio propzy.ai disponible?)
- **DealDaddy** (playful, memorable, pero informal)
- **LeadHub.ai** (descriptivo, sin personalidad)
- **Real Concierge** (posiciona como servicio, no software)
- **Homeraly** (inventado, tipo "Calendly for homes")
- **Pipe.co** (corto, fácil de recordar)
- **FlipBrain** (niche a flippers, limita expansion)

Recomendación: brainstormear con `ai-seo` + `brand-guidelines` skill en sesión dedicada. Validar domain + trademark antes de commit.

---

## 📚 DECISIONES PENDIENTES (para Jorge antes de launch)

1. **Nombre comercial final** — separado de ALEX interno
2. **Dominio principal** — .com vs .ai vs otro
3. **Si se hace white-label por default** — "Powered by X" visible o no
4. **Stripe vs Paddle vs alternativa** para billing (tax handling)
5. **Jurisdicción legal** — Wisconsin LLC vs Delaware C-Corp si planeas fundraise
6. **Marca propia del form vs permitir cliente branding completo en Starter**
7. **Refund policy específica** — 60 días es generoso, ¿ajustamos?
8. **Target launch date real** — ¿aim día 90 exacto o flexibilidad?

---

## 📞 SIGUIENTE PASO INMEDIATO

Una vez aprobado este plan, el próximo paso es:

1. **Comenzar Semana 1** de plan técnico (Tenant Registry + multi-tenant refactor)
2. Paralelo: decidir nombre comercial + registrar dominio
3. Paralelo: draft legal documents (Terms, Privacy, DPA) — usar skill `gdpr-dsgvo-expert`
4. Paralelo: Jorge lista su network de 5 potenciales beta clients

**ALEX está listo para operar según Fases 1-7 del `PROTOCOLO_EJECUCION.md` en cada paso del plan.**

---

## 🔗 REFERENCIAS CRUZADAS

| Documento | Propósito |
|---|---|
| `docs/ARCHITECTURE.md` | Mapa técnico del sistema en 3 capas |
| `docs/AGENT_REGISTRY.md` | Ficha técnica de cada agente |
| `docs/TASK_MATRIX.md` | Quién hace qué, handoffs, no-dos |
| `docs/COST_OPTIMIZATION.md` | Jerarquía modelos + budget proyectado |
| `docs/SCALABILITY.md` | Plan técnico multi-tenant |
| `docs/COMMERCIALIZATION.md` | **← estás aquí** (índice maestro) |
| `docs/commercialization/01_pricing_model.md` | Pricing detallado |
| `docs/commercialization/02_product_packaging.md` | Features + empaquetado |
| `docs/commercialization/03_go_to_market.md` | Plan 90 días + sales playbook |
| `agents/PROTOCOLO_EJECUCION.md` | Las 7 fases para no romper nada |
| `memoria_ALex.md` | Memoria persistente + reglas del Jefe |
