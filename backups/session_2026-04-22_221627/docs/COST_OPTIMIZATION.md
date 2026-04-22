# COST_OPTIMIZATION.md — Jerarquía de Modelos y Control de Costos

> Reglas obligatorias para usar el modelo Claude MÁS BARATO que puede resolver cada tarea.
> Aprobado por Jorge 2026-04-07 (memoria_ALex.md), formalizado aquí 2026-04-22.
> Meta: **reducir uso de Opus 70%**, **60% Haiku**, **30% Sonnet**, **10% Opus**.

---

## 💵 PRECIO POR MILLÓN DE TOKENS (Claude 4.X family)

| Modelo | Input | Output | Velocidad | Razonamiento |
|---|---|---|---|---|
| **Haiku 4.5** (`claude-haiku-4-5-20251001`) | $1 | $5 | ⚡⚡⚡ | Básico, clasificación, respuestas simples |
| **Sonnet 4.6** (`claude-sonnet-4-6`) | $3 | $15 | ⚡⚡ | Intermedio, razonamiento moderado, código simple |
| **Opus 4.7** (`claude-opus-4-7`) | $15 | $75 | ⚡ | Máximo, arquitectura, debug profundo, análisis complejo |

**Ratios:** Opus cuesta 15x Haiku input, 15x Haiku output. **Nunca usar Opus por defecto.**

---

## 🎯 JERARQUÍA DE MODELOS (regla de oro: usar el más barato que pueda)

```
NIVEL 1 — HAIKU (default para todo, salvo excepción)
├─ Clasificación (Secretario: LEAD/URGENTE/SPAM)
├─ Respuestas SMS cortas (Fer conversación normal)
├─ Updates de status en Telegram
├─ Skip trace organization (Tracy)
├─ Publicar posts (Programador)
├─ Generar visuales templated (Creativo — decisión de tema)
├─ Resumen de memoria
└─ Consultas Airtable simples

NIVEL 2 — SONNET (cuando Haiku no basta)
├─ Análisis de deal (Scout + Matemático + Fact-Checker)
├─ Generación de contenido SM (captions EN/ES)
├─ Video scripts (Director)
├─ Orquestación ALEX en tareas medias
├─ Código < 100 líneas
├─ Fer auto-escalation cuando detecta ambigüedad
└─ Respuestas al Jefe que requieren razonamiento

NIVEL 3 — OPUS (excepcional, 10% del tiempo)
├─ Arquitectura de nuevo sistema / feature complejo
├─ Debug profundo (bug no reproducible)
├─ Refactor grande (> 500 líneas tocadas)
├─ Análisis financiero crítico (deals > $200k)
├─ Plan comercial / estratégico (doc como éste)
├─ Code review antes de merge a producción
└─ Cuando 2-3 intentos con Sonnet fallaron
```

---

## 🤖 MODELO ASIGNADO POR AGENTE

| Agente | Modelo default | Escalation | Razón |
|---|---|---|---|
| ALEX (orquestador) | **Sonnet 4.6** | Opus para planning complejo | Razonamiento constante, ocasional arquitectura |
| El Scout | **Sonnet 4.6** | — | Investigación estructurada |
| El Matemático | **Sonnet 4.6** | — | Matemática precisa, cero alucinación |
| El Fact-Checker | **Sonnet 4.6** | Opus para deals >$200k | Auditoría necesita razonamiento cuidadoso |
| Tracy | **Haiku 4.5** | — | Solo organiza datos de Tracerfy |
| Fer | **Haiku 4.5** | Sonnet si detecta ambigüedad | 90% conversaciones son rutinarias |
| Telegram Bot ALEX | **Sonnet 4.6** | Haiku para respuestas simples | Mix según tipo de pregunta |
| Secretario (email) | **Haiku 4.5** | — | Clasificación pura |
| Planificador (cal) | **Haiku 4.5** | — | Parseo de fechas + comandos |
| Social Media Agent | **Sonnet 4.6** | — | Creatividad + bilingüe |
| El Creativo | **Haiku 4.5** | — | Orquestación Blotato, poco razonamiento |
| El Director | **Sonnet 4.6** | — | Video script necesita creatividad |
| El Programador | **Haiku 4.5** | — | Llama API, valida, publica |

---

## 📉 REGLAS DE AHORRO ACTIVAS (aprobadas por Jorge 2026-04-07)

1. **NUNCA invocar Claude Code/Opus** para tareas que no requieran código pesado.
2. **NUNCA usar modelo caro** cuando uno más barato resuelve.
3. **Antes de invocar cualquier sub-agente** — evaluar si es necesario (¿la memoria ya lo responde?).
4. **Agrupar tareas similares** — múltiples consultas en una sola llamada cuando sea posible.
5. **Caché de resultados** — si ya tenemos un dato, no volver a buscarlo.
6. **Leer memoria primero** — evitar análisis repetidos de mismas zip codes / propiedades.
7. **Prompt caching** — usar system prompt caching para reducir input tokens en llamadas repetidas.
8. **Streaming con early stop** — cortar generación si ya tenemos respuesta aceptable.

### Criterio de decisión rápido
```
¿Es código complejo (>100 líneas)?     → SÍ → Opus
¿Es código simple (<100 líneas)?       → SÍ → Sonnet
¿Es texto / consulta / clasificación?  → SÍ → Haiku
¿Ya está en memoria?                   → SÍ → NO invocar agente
¿Es análisis de deal?                  → SÍ → Sonnet (Scout+Mat+FC)
¿Es conversación con cliente?          → SÍ → Haiku (Fer default)
```

---

## 📊 BUDGET MENSUAL ACTUAL (single-tenant = Pinnacle solo)

Asumiendo volumen actual (2026-04):
- Fer: ~500 conversaciones SMS/mes × 2k tokens avg = 1M tokens Haiku → **$3-4**
- Secretario: ~3,000 emails clasificados × 1k tokens = 3M tokens Haiku → **$9-12**
- Tracy: ~50 skip traces × 2k = 100k tokens Haiku → **$0.50**
- Análisis de deals: ~20 deals × 30k tokens (Scout+Mat+FC combinados) = 600k Sonnet → **$4-8**
- Social Media: ~40 posts × 10k = 400k Sonnet → **$3-5**
- ALEX orquestación (Telegram + Claude Code): ~1.5M tokens Sonnet/mes → **$20-30**
- Opus ocasional (arquitectura, esta session de docs): ~200k tokens → **$15**

**Total estimado: $55-75/mes de gastos Claude** para un cliente activo como Pinnacle.

### Infraestructura (fija, no-Claude)
- Hostinger shared hosting: ~$12/mes
- VPS: ~$30/mes
- Airtable Team: ~$20/mes (por workspace)
- Blotato: ~$30/mes
- Tracerfy: pay-per-call (~$20-40/mes según uso)
- Quo/OpenPhone: ~$30/mes
- GitHub: gratis (plan free)
- Google Places API: ~$5-10/mes
- **Total infra: ~$150/mes**

### Costo marginal por cliente (para pricing)
- **Claude tokens:** $55-75/mes variable con uso
- **Infra prorrateada multi-tenant:** $20-40/mes
- **Total:** **$75-115/mes por cliente activo**

---

## 💰 PROYECCIÓN DE COSTOS MULTI-TENANT (60-90 días)

Asumiendo 20 clientes activos al mes 3:

| Item | Costo/cliente | 20 clientes |
|---|---|---|
| Claude tokens | $75 | **$1,500** |
| Airtable workspaces | $20 | **$400** |
| Blotato accounts (per client) | $30 | **$600** |
| Quo numbers | $15 | **$300** |
| Tracerfy calls | $30 | **$600** |
| Hostinger + VPS (compartido) | $5 | **$100** |
| Google APIs | $10 | **$200** |
| Otras APIs (Places, Calendar) | $3 | **$60** |
| **Total costo mensual** | | **$3,760** |

### Revenue a 20 clientes (mix conservador)
- 12 Starter × $297 = $3,564
- 6 Growth × $697 = $4,182
- 2 Pro × $1,497 = $2,994
- **Subscription MRR: $10,740**
- Performance fees (estimado): +$4,500
- **Revenue total: $15,240/mes**

### Margen bruto
- Revenue: $15,240
- Costs: $3,760
- **Gross margin: $11,480/mes (75% margin)** ✅ Benchmark SaaS saludable

---

## 🎯 OPTIMIZACIONES A IMPLEMENTAR PRE-LAUNCH

Para mantener margen > 75% a escala, implementar:

### 1. Prompt caching (Anthropic feature, ya soportado)
- Cachear los system prompts de Fer, ALEX, sub-agentes
- Reduce input tokens en ~80% después del primer hit
- **Ahorro estimado:** 30-40% del gasto Claude

### 2. Token budget por cliente
- Cap mensual de tokens por tier (Starter: 2M, Growth: 8M, Pro: 30M)
- Alerta a Jefe si un cliente se acerca al 80% del budget
- Upsell automático si lo supera

### 3. Batch inference
- Para tareas no time-critical (social media, emails), usar Claude Batch API (50% descuento)
- **Ahorro:** $200-400/mes a 20 clientes

### 4. Model routing automático
- Sistema de routing que clasifica cada request → asigna modelo más barato que pueda
- Implementar en `agents/model_router_config.json` (ya existe como stub)

### 5. Cache de Airtable (Redis/SQLite local)
- Evita llamadas repetidas a Airtable API (tiene rate limits)
- No reduce Claude cost directo pero evita retry loops que sí consumen tokens

---

## 📈 KPIs DE COSTO A MONITOREAR

| Métrica | Target | Alerta |
|---|---|---|
| Costo Claude por cliente activo | < $100/mes | > $150 |
| % requests en Haiku | > 60% | < 40% |
| % requests en Opus | < 10% | > 20% |
| Margen bruto por cliente | > 70% | < 55% |
| Tokens por deal cerrado | < 300k | > 600k |
| Ratio deals/cliente/mes | > 2 | < 0.5 |

**Dashboard:** generar con skill `saas-metrics-coach` mensualmente.

---

## 🔗 REFERENCIAS

- Modelo asignado por agente → `docs/AGENT_REGISTRY.md`
- Tareas por agente → `docs/TASK_MATRIX.md`
- Reglas de Jorge sobre costos → `memoria_ALex.md` sección "2026-04-07 REGLA CRÍTICA"
- Configuración de modelos → `agents/model_router_config.json`
