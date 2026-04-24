# El Creativo v2 — Design Spec

**Date:** 2026-04-24
**Status:** Draft — pending Jefe approval
**Author:** ALEX (with Jorge Cruz)
**Branch:** `claude/greeting-setup-yOfqf`
**Replaces:** Blotato-based flow (`agents/creativo.md` v5.0)

---

## 1. Purpose

Replace Blotato entirely for static social media content generation. Build a Node-based, deterministic, code-driven carousel generator for Pinnacle Holdings Group that produces 1080x1350 JPG slides using pre-defined brand themes (T1-T5), without depending on any third-party visual-template SaaS.

**Why:** Blotato no longer serves the operation — unreliable MCP loading, template rigidity, recurring cost, and loss of brand control. The existing `agents/creativo_runner/themes.mjs` already implements the 5 brand themes and slide builders; we complete the pipeline around it.

---

## 2. Success Criteria

### POC (Fase 1)
- 6 JPGs render without error from a hardcoded sample spec
- Dimensions exactly 1080x1350, format JPG quality 90
- Colors match T1 hex (#0D3B2E bg, #FFFFFF text, #C9A84C accent)
- Pinnacle logo visible on hook slide (watermark) and CTA slide (centered, prominent)
- Bilingual text (EN + ES) legible, no clipping, no overlap
- Phone (920) 777-9886 and pinnaclegroupwi.com visible on CTA slide
- Jorge visually confirms output is "publishable on IG"

### Fase 2 (Production)
- At least 1 real Airtable record processes end-to-end without manual intervention
- Cloudinary URLs resolve (HTTP 200) 24h after upload
- Retries and error handling verified with 1 malformed Visual_Prompt case

---

## 3. Scope and Phases

### Fase 1 — POC (build now)
- Reuse existing theme logic from `agents/creativo_runner/themes.mjs`
- Add HTML wrapper, Puppeteer renderer, sample spec, orchestrator
- Output: 6 JPGs of a T1 carousel, committed to repo for review
- **No** Airtable, **no** Cloudinary, **no** Nano Banana

### Fase 2 — Production (after POC approval)
- Add Airtable reader/parser/writer (REST via fetch, no SDK)
- Add Cloudinary uploader (signed upload via fetch)
- Replace POC orchestrator with production `main.mjs`
- Deploy via cron (GitHub Actions or VPS) every 15 min

### Fase 3 — Enrichment (after Fase 2 stable 2-4 weeks)
- Add `nano_banana.mjs` — Gemini API (direct) for optional hero images
- Add `slideMedia` type — supports testimonials, Jorge photos, house images
- Support additional aspect ratios (1:1, 9:16) via `aspect` param

Each fase is independently deployable. Stopping after Fase 1 leaves a working local renderer; stopping after Fase 2 leaves a production system.

---

## 4. Architecture

### Directory layout
```
agents/creativo_v2/
  package.json               only dep: puppeteer
  README.md
  src/
    themes.mjs               moved from agents/creativo_runner/themes.mjs
    wrapper.mjs              HTML shell + Google Fonts
    render.mjs               Puppeteer HTML to JPG
    airtable.mjs             Fase 2
    cloudinary.mjs           Fase 2
    main.mjs                 Fase 2 orchestrator
    nano_banana.mjs          Fase 3
  spec/
    poc_t1_5reasons.json     hardcoded sample content
  output/                    POC only, .gitignored after Fase 2
  logs/                      JSONL runs, .gitignored
  poc.mjs                    POC entry point
```

### Module responsibilities

| Module | Fase | Input | Output | Dependencies |
|---|---|---|---|---|
| `themes.mjs` | 1 | spec `{theme, hook, points[], cta}` | array of 6 HTML body strings | none |
| `wrapper.mjs` | 1 | body HTML + themeCode | full HTML doc string | none |
| `render.mjs` | 1 | HTML + outputPath | JPG file on disk | puppeteer |
| `poc.mjs` | 1 | spec JSON file | 6 JPGs in output/ | themes, wrapper, render |
| `airtable.mjs` | 2 | filter criteria | records[], parsed specs | fetch + env |
| `cloudinary.mjs` | 2 | jpgPaths + naming | secure URLs | fetch + env |
| `main.mjs` | 2 | none (cron trigger) | Airtable updates | all of the above |
| `nano_banana.mjs` | 3 | prompt + reference images | PNG file | fetch + GEMINI_API_KEY |

### Data Flow

**POC:**
```
spec JSON -> buildCarousel -> 6 HTML bodies -> wrapSlideHtml -> full HTML -> Puppeteer -> 6 JPGs
```

**Production (Fase 2):**
```
Airtable listPending -> records[] -> parseVisualPrompt -> spec -> buildCarousel -> wrap -> render -> 6 JPGs -> Cloudinary upload -> secure URLs -> Airtable update (visual_url, Status=Lista para Publicar)
```

---

## 5. Design Decisions (Confirmed with Jefe)

| Decision | Chosen | Rationale |
|---|---|---|
| POC scope | 1 complete 6-slide T1 carousel | Validates full narrative flow; other 4 themes are CSS variable swaps |
| Content source (POC) | Hardcoded sample JSON | Isolates renderer from Airtable dependency |
| Nano Banana in POC | No — pure typography | Educational carousels work better with clean type; isolates POC |
| Dimensions | 1080x1350 (4:5 vertical) | IG/FB priority format in mobile feed; more screen area = more engagement |
| Image format | JPG quality 90 | IG/FB native, smaller file, indistinguishable quality for text+colors |
| POC delivery | Commit JPGs to repo + inline chat preview | Versioned history + immediate feedback |

---

## 6. Sample Spec (POC)

File: `spec/poc_t1_5reasons.json`

```json
{
  "theme": "T1",
  "hook": {
    "en": "5 Reasons to Sell for Cash",
    "es": "5 Razones para Vender por Efectivo",
    "badge": "WISCONSIN"
  },
  "points": [
    {
      "headingEn": "No Repairs Needed",
      "headingEs": "Sin Reparaciones",
      "bodyEn": "We buy your house as-is, no upgrades, no cleaning, no stress.",
      "bodyEs": "Compramos tu casa como esta, sin arreglos, sin limpieza, sin estres."
    },
    {
      "headingEn": "Close in 7 Days",
      "headingEs": "Cierre en 7 Dias",
      "bodyEn": "From offer to cash in your hand in one week.",
      "bodyEs": "De la oferta al efectivo en tu mano en una semana."
    },
    {
      "headingEn": "No Commissions",
      "headingEs": "Sin Comisiones",
      "bodyEn": "You keep 100% of the offer. No realtor fees, no closing costs.",
      "bodyEs": "Te quedas con el 100% de la oferta. Sin comisiones ni costos de cierre."
    },
    {
      "headingEn": "No Showings",
      "headingEs": "Sin Visitas",
      "bodyEn": "Skip open houses, strangers walking through, and staging costs.",
      "bodyEs": "Sin visitas, sin extranos en tu casa, sin costos de presentacion."
    }
  ],
  "cta": {
    "en": "We Buy Houses. Cash. Fast. Fair.",
    "es": "Compramos Casas. Efectivo. Rapido. Justo."
  }
}
```

Total 6 slides: hook + 4 points + CTA.

---

## 7. Error Handling

### POC
| Error | Handling |
|---|---|
| Puppeteer launch fails | Clear error + install command suggestion |
| Blank render | Log offending HTML + slide index |
| Google Fonts timeout | Fallback to system-ui (in themes.mjs) |
| Logo 404 | Fallback to text "PINNACLE" |
| Write fails | Check perms, exit 1 |

### Fase 2
| Error | Handling |
|---|---|
| Doppler token invalid | Exit immediately with remediation message |
| Airtable 4xx/5xx | 3x retry with backoff (2s, 4s, 8s), then log+skip |
| parseVisualPrompt fails | PATCH record Status=Error with reason, continue |
| Cloudinary upload fails | 2x retry, keep local JPG, mark Status=Error Upload |
| Render fails mid-carousel | Skip record (no partial carousels) |
| Process crashes | GitHub Actions exit code != 0 triggers Telegram alert |

### Principle
One bad record never blocks the batch. Individual try/catch per record. JSONL log per run.

---

## 8. Testing

### POC
- Manual visual inspection by Jorge (primary)
- Smoke: 6 files exist, each 80KB-300KB, dimensions 1080x1350

### Fase 2
- Integration: 1 test Airtable record processes end-to-end
- Dry-run mode (`--dry-run`) skips Airtable PATCH for safe testing
- Cloudinary URL accessibility test 24h post-upload

---

## 9. Security and Secrets

All secrets via Doppler (already configured):
- `CLOUDINARY_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET`
- Airtable token from existing `patSlNwngu7SJoa52...` in `agents/social_media.md` (migrate to Doppler as `AIRTABLE_SM_TOKEN` during Fase 2)
- `GEMINI_API_KEY` and `REPLICATE_API_TOKEN` for Fase 3

Execution pattern: `doppler run -- node src/main.mjs`. No `.env` files. No secrets in git.

---

## 10. Non-Goals

- Not rebuilding the Social Media Agent (it already generates Visual_Prompt correctly)
- Not replacing El Director (videos/Reels) — separate effort, different tech
- Not replacing El Programador (FB/IG publishing) — separate effort, Meta Graph API direct
- Not supporting LinkedIn or Twitter/X formats in Fase 1/2 — Fase 3+
- Not handling video in this system at all
- Not building a UI — agent runs headless via cron

---

## 11. Resolved Implementation Decisions

- **themes.mjs location:** move physically to `creativo_v2/src/themes.mjs`. Makes `creativo_v2/` a self-contained module. Old location `agents/creativo_runner/themes.mjs` deleted in same commit.
- **Puppeteer chromium:** use bundled chromium (~170MB in node_modules). Reliability over disk size — CI/CD environments rarely have a system chromium.
- **parseVisualPrompt strategy (Fase 2):** regex-first on `TEMA:`, `Slide N:` markers produced by Social Media Agent. If parse fails, PATCH Airtable with `Status=Error` and `Error_Reason="parse failed"` — do NOT fall back to LLM parse in Fase 2 (adds cost and non-determinism). LLM fallback is a Fase 3+ consideration only if parse error rate exceeds 5%.

---

## 12. Commit Plan

One commit per module:
1. `creativo_v2: scaffold + package.json + README`
2. `creativo_v2: move themes.mjs from creativo_runner`
3. `creativo_v2: add wrapper.mjs`
4. `creativo_v2: add render.mjs`
5. `creativo_v2: add poc.mjs + sample spec`
6. `creativo_v2: POC output (6 T1 slides)`

After Jefe approves POC, Fase 2 commits follow same cadence.

---

*End of spec.*
