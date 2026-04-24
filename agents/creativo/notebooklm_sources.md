# NotebookLM — Fuentes iniciales para el notebook de Pinnacle

> Notebook name sugerido: **"Pinnacle WI — Distressed Homeowner Knowledge Base"**
> Tipo de fuentes: URLs públicas + PDFs descargables (bajar del sitio, subir al notebook).
> Máximo 50 fuentes por notebook en plan free. Arrancamos con 10.

**Importante:** las URLs oficiales de agencias estatales cambian de path seguido. Si una URL específica no abre, Jorge navega desde la home del sitio a la sección pertinente. Las URLs raíz están verificadas. Algunas subpáginas llevan año "verificar antes de cargar" — se hace en 30 segundos desde Chrome.

---

## 1. Wisconsin Department of Financial Institutions (DFI)

- **URL raíz:** https://dfi.wi.gov/
- **Navegar a:** sección "Consumers" → "Foreclosure Information" (o buscar "foreclosure" en el site search)
- **Qué cargar:** cualquier PDF de "Foreclosure Prevention Guide" + la página HTML principal de consumer foreclosure resources
- **Por qué suma:** regulador oficial estatal. Credibilidad máxima para contenido "cómo funciona el proceso legal en WI" que Fer y el Creativo usan todos los días.

## 2. Wisconsin State Bar — Probate: Answering Your Legal Questions

- **URL exacta:** https://www.wisbar.org/forPublic/INeedInformation/Pages/probate.aspx ✅ (verificada hoy)
- **Qué cargar:** la página HTML + los PDFs "Transfer by Affidavit" descargables
- **Por qué suma:** material #1 para contenido sobre propiedades heredadas (motivación "inherited" de Fer). Uno de los nichos más rentables de Pinnacle.

## 3. Wisconsin Court System — Self-Help

- **URL raíz:** https://www.wicourts.gov/
- **Navegar a:** "Services" → "For the Public" → "Self-Help Law Center" → secciones sobre foreclosure y real estate
- **Qué cargar:** páginas HTML + PDFs de procedimiento judicial
- **Por qué suma:** proceso de sheriff sale, court timelines. Precisión legal para contenido educativo.

## 4. Wisconsin Department of Revenue — Delinquent Property Taxes

- **URL raíz:** https://www.revenue.wi.gov/
- **Navegar a:** buscar "delinquent property tax" en el site search
- **Qué cargar:** páginas de explicación del proceso de tax delinquent + PDFs si hay
- **Por qué suma:** motivación "tax delinquent" de Fer. Contenido educativo sobre qué pasa cuando no podés pagar impuestos de propiedad.

## 5. Wisconsin Realtors Association (WRA) — Housing Reports

- **URL raíz:** https://www.wra.org/
- **Navegar a:** "Resources" o "Research" en el menú principal — reportes mensuales del mercado WI
- **Qué cargar:** los últimos 3-6 reportes mensuales en PDF (median prices, inventory, days on market, por condado)
- **Por qué suma:** data real de mercado para contenido "estado del mercado WI". Citable con fuente reconocida localmente.

## 6. Zillow Research — Wisconsin Market Data

- **URL exacta:** https://www.zillow.com/research/data/ (bloquea scrapers pero abre en Chrome humano)
- **Qué cargar:** el CSV de Zillow Home Value Index filtrado por WI (Milwaukee, Madison, Green Bay, etc.)
- **Por qué suma:** data agregada de valuación y tendencias que se usa para posts de "cuánto vale tu casa en {ciudad WI}".

## 7. Redfin Market Reports — Wisconsin

- **URL raíz:** https://www.redfin.com/us-housing-market (bloquea scrapers pero abre en Chrome humano)
- **Navegar a:** buscar "Milwaukee WI", "Green Bay WI", "Madison WI", "Appleton WI" — cada ciudad tiene su página de housing market
- **Qué cargar:** las páginas de housing market de las 4-5 ciudades principales de Pinnacle
- **Por qué suma:** complementa a WRA con data updated semanalmente. Perfecto para contenido hiperlocal "Green Bay market este mes".

## 8. HUD — Wisconsin Housing Counseling + Foreclosure Avoidance

- **URL raíz:** https://www.hud.gov/
- **Navegar a:** "States" → "Wisconsin" → "Homeownership" → "Avoiding Foreclosure"
- **Qué cargar:** páginas de recursos + lista de agencias de counseling aprobadas por HUD en WI
- **Por qué suma:** ofrece contexto nacional + recursos que Pinnacle puede recomendar como "alternativa" en posts honestos tipo "si no querés vender, acá hay ayuda gratis".

## 9. FTC / CFPB — Mortgage assistance scam alerts

- **URL raíz CFPB:** https://www.consumerfinance.gov/
- **Navegar a:** buscar "avoid foreclosure scam"
- **Qué cargar:** artículos oficiales sobre scams de foreclosure rescue + consumer rights
- **Por qué suma:** Pinnacle se diferencia siendo el cash buyer legítimo. Contenido "cómo distinguir un cash buyer real de una estafa" posiciona a Pinnacle como la opción segura.

## 10. Pinnacle — Internal knowledge pack (subir manualmente)

- **Qué cargar:** (ALEX prepara un paquete con estos ítems en un zip)
  - Brief de marca Pinnacle (tono, value prop, target audience, T1-T5 temas visuales)
  - Top 20 FAQs del chatbot Fer (las preguntas más comunes + respuestas aprobadas)
  - 10 transcripts anonimizados de conversaciones Fer donde se cerró deal (sacar nombres + direcciones antes de subir)
  - El prompt base del Creativo (`agents/creativo.md`)
  - Scripts de los 4 SMS de `fer_first_contact.php` (ES + EN)
- **Por qué suma:** hace que NotebookLM responda "como Pinnacle" — no como ChatGPT genérico. Todos los posts respetan el tono, el nicho y las respuestas validadas por vos.

---

## Orden de carga recomendado (por prioridad de value)

1. Fuente **#10 (Pinnacle internal)** — sin esto el notebook no tiene voz Pinnacle.
2. Fuente **#2 (State Bar Probate)** — nicho #1 rentable, URL verificada.
3. Fuente **#1 (DFI Foreclosure)** — nicho #2 rentable.
4. Fuente **#5 (WRA reports)** — data mensual para posts recurrentes de mercado.
5. Fuente **#3 (Wisconsin Court)** — complemento legal.
6. Fuentes #4, #6, #7, #8, #9 — completar el notebook.

## Nota sobre rate limits (plan free NotebookLM)

- 50 fuentes por notebook → tenemos margen para llegar a 50 cuando sumemos blog posts + transcripts adicionales.
- 100 notebooks en total → podríamos crear notebooks temáticos separados después ("Foreclosure WI", "Probate WI", "Market Data WI") si el single notebook se satura.

*Última actualización: 2026-04-23 — ALEX*
