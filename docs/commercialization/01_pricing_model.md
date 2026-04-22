# F1 — Pricing Model

> Modelo comercial: subscription mensual + performance fee sobre deals atribuibles.
> Aprobado por Jorge 2026-04-22. Parte de `docs/COMMERCIALIZATION.md`.

---

## 🎯 FILOSOFÍA DEL PRICING

1. **Low barrier de entrada** — subscription accesible para adquisición rápida
2. **Alineación de incentivos** — ganamos más cuando el cliente gana más (performance fee)
3. **Predictibilidad de MRR** — subscription fija paga infra + margen mínimo
4. **Upside compartido** — performance fee da upside escalable según éxito del cliente
5. **Sin sorpresas** — performance fee % decreciente con escala (el grande paga menos %)

---

## 📦 SUBSCRIPTION TIERS

| Tier | Mensual | Anual (16.6% OFF) | Target |
|---|---|---|---|
| **Starter** | $297 | $2,970 | 1 usuario, <$10k MRR — flippers part-time, wholesalers nuevos |
| **Growth** | $697 | $6,970 | Equipos 2-5, $10-100k MRR — flippers activos, agencies locales |
| **Pro** | $1,497 | $14,970 | Equipos 5-20, multi-market, $100k+ MRR, white-label |
| **Enterprise** | Desde $3,500 | Custom | Multi-tenant, SLA 99.9%, custom agents, API dedicada |

**Setup fee one-time:** $997 (onboarding + config + 2h training + 30 días premium support). Se descuenta del 1er mes si firma plan anual.

---

## 💸 PERFORMANCE FEE — sólo sobre deals ATRIBUIBLES

**Regla clave:** cobramos % sobre **ganancia bruta** (no revenue) de deals que el sistema **generó**.

### Qué cuenta como "deal atribuible"
- Lead entró por web form del sistema, Fer SMS, o Tracy skip trace
- Ventana de atribución: 90 días desde primer contacto con el cliente
- Tracking automático vía Airtable `Lead Source`
- Cliente firma cláusula de reporte honesto al onboarding

### Brackets (sobre ganancia mensual de deals atribuibles)

| MRR atribuible | Performance fee | Lógica |
|---|---|---|
| $0 – $10,000 | **0%** | Solo subscription — cliente chico no paga extra |
| $10,001 – $50,000 | **5%** | Cliente en crecimiento — alineación fuerte |
| $50,001 – $100,000 | **4%** | Cliente mid-market — decreciente |
| $100,001 – $150,000 | **3%** | Cliente grande — margen menor pero volumen alto |
| $150,001+ | **2%** | Cliente enterprise — piso inferior, escala |

### Por qué % sobre GANANCIA, no revenue
Si cliente cobra $80k pero su margen real fue $15k (deal de flip), cobrar 5% de $80k ($4k) lo hunde. 5% de $15k ($750) es justo. Ambos ganan. Cliente reporta ganancia al sistema automático vía campo Airtable `Deal Profit`.

---

## 💡 EJEMPLOS REALES DE FACTURACIÓN

### Cliente A — Wholesaler part-time (2 deals/mes)
- Subscription: Starter $297
- Deals atribuibles: 2 × $4,000 profit promedio = **$8,000 MRR**
- Performance fee: **0%** (bajo de $10k)
- **Total factura: $297/mo**
- Su ROI con el sistema: $8,000 / $297 = **27x**

### Cliente B — Flipper activo (5 deals/mes)
- Subscription: Growth $697
- Deals atribuibles: 5 × $7,000 profit promedio = **$35,000 MRR**
- Performance fee: 5% × $35k = **$1,750**
- **Total factura: $2,447/mo**
- Su ROI: $35,000 / $2,447 = **14x**

### Cliente C — Agency mid-market (15 deals/mes)
- Subscription: Pro $1,497
- Deals atribuibles: 15 × $8,000 profit promedio = **$120,000 MRR**
- Performance fee: 3% × $120k = **$3,600**
- **Total factura: $5,097/mo**
- Su ROI: $120,000 / $5,097 = **23x**

### Cliente D — Enterprise (50+ deals/mes)
- Subscription: Enterprise $3,500
- Deals atribuibles: 60 × $10,000 profit promedio = **$600,000 MRR**
- Performance fee: 2% × $600k = **$12,000**
- **Total factura: $15,500/mo**
- Su ROI: $600,000 / $15,500 = **38x**

---

## 🎁 INCENTIVOS DE VENTA

### Descuentos
- **Pago anual:** 2 meses gratis (equivale 16.6% descuento)
- **Pago 2 años upfront:** 25% descuento
- **Referral program:** 20% del MRR del referido durante 12 meses
- **Upgrade dentro del mes:** sin proration penalty, crédito completo

### Garantías
- **Money-back guarantee:** 60 días sin preguntas — si no generamos al menos 1 deal atribuible en 60 días, refund completo
- **Pause option:** cliente puede pausar subscription 1 mes/año sin perder datos (útil para temporadas lentas)
- **Lock-in precio:** primeros 20 clientes beta → precio congelado por 24 meses

---

## 📈 PROYECCIÓN DE INGRESOS (90 días post-launch)

Mix conservador: **17 clientes activos al mes 3**

| Tier | Clientes | MRR subscription | Performance fee estimado | Total |
|---|---|---|---|---|
| Starter | 10 | $2,970 | $0 | $2,970 |
| Growth | 5 | $3,485 | $3,000 | $6,485 |
| Pro | 2 | $2,994 | $3,000 | $5,994 |
| **Total 17 clientes** | | **$9,449** | **$6,000** | **$15,449 MRR** |

### Año 1 proyectado (si mantenemos crecimiento)
- Mes 6: 40 clientes → ~$38k MRR
- Mes 9: 80 clientes → ~$78k MRR
- Mes 12: 150 clientes → ~$150k MRR = **$1.8M ARR**

Benchmark: SaaS B2B similares alcanzan $1M ARR en 18-24 meses. Si logramos en 12, excelente.

---

## 🧮 UNIT ECONOMICS

### CAC (Customer Acquisition Cost)
- Paid ads: $50-100 per lead, close rate 15% → **$400-700 CAC**
- Referrals + content: $50-150 CAC (mejor)
- Meta realista 12 meses: **$500 CAC promedio**

### LTV (Lifetime Value)
- Churn asumido: 5%/mes mes 1-6, 2%/mes después
- Average subscription + fee: $900/mes
- Gross margin: 75%
- LTV = $900 × 0.75 / 0.02 = **$33,750**

### LTV:CAC ratio
- $33,750 / $500 = **67:1**
- Target industry SaaS: >3:1
- **Nuestro ratio es outlier alto** — indica precio podría subir O gastar más en CAC

### Payback period
- Subscription solo: $297/mes × 0.75 = $223 gross/mes
- Payback de $500 CAC: **~2.2 meses** (excelente; target SaaS es <12)

---

## 🚩 RIESGOS DEL MODELO + MITIGACIONES

| Riesgo | Mitigación |
|---|---|
| Cliente esconde deals para evitar fee | Onboarding firma data-sharing + Stripe visibility + auto-attribution via Airtable |
| Cliente discute qué es "atribuible" | Ventana 90 días clara + UI transparente mostrando atribución |
| Performance fees generan resistencia | Tier Starter sin fee + quick wins primer mes |
| Churn por pricing alto | Money-back + pause option + tier Starter bajo |
| Competencia baja precio | Nuestro diferenciador es integración total, no precio |
| Costo Claude sube | Buffer de 25% en margen + capacidad de subir tiers |

---

## 📝 TÉRMINOS CONTRACTUALES CLAVE (para legal)

- **Duración:** mes a mes, salvo anual (12 meses commitment con descuento)
- **Cancelación:** 30 días anticipación para anual; inmediata para mensual
- **Data:** cliente es dueño 100% de sus datos; export en 1-click
- **Uptime SLA:** 99% Growth, 99.5% Pro, 99.9% Enterprise; créditos si no se cumple
- **Soporte:** Email (Starter 48h, Growth 24h, Pro 4h priority, Enterprise dedicado)
- **Performance fee dispute:** cliente puede disputar deal en 30 días; árbitro imparcial si no acuerdo
- **Termination:** cualquier lado con 30 días, data retention 60 días post-cancel

---

## 🔗 RELACIONADOS

- Packaging y features por tier → `docs/commercialization/02_product_packaging.md`
- Go-to-market plan → `docs/commercialization/03_go_to_market.md`
- Costos que soportan este pricing → `docs/COST_OPTIMIZATION.md`
