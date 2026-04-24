# El Director v2 — Design Spec

**Fecha:** 2026-04-24
**Autor:** ALEX (Claude Code, Opus 4.7) con Jorge (Pinnacle Holdings Group)
**Estado:** Draft — pendiente aprobación final de Jorge
**Ubicación en repo:** `agents/director_v2/`
**Branch:** `claude/greeting-setup-yOfqf`
**Relacionado con:** `docs/superpowers/specs/2026-04-24-creativo-v2-design.md`, `docs/superpowers/plans/2026-04-24-creativo-v2-prod.md`

---

## 1. Contexto y justificación

El Creativo v2 quedó 100% operativo el 2026-04-24 para generar carruseles/imágenes estáticas de Pinnacle Holdings Group. Ver `agents/creativo_v2/` (5 temas T1-T5 × 3 aspect ratios × 3 slide-types, 42 tests verdes, integración Airtable + Cloudinary vía Doppler).

**El Director v2** es la contraparte del Creativo v2 para contenido en video (Reels / Stories). Hereda la misma filosofía:

- **Brand consistency** — reutiliza `themes.mjs` y `wrapper.mjs` del Creativo v2 para que los Reels compartan identidad con los carruseles
- **Code-first** — Puppeteer para renderizar captions y escenas como frames; ffmpeg para ensamblar
- **Secrets vía Doppler** — cero plaintext en repo
- **Tests verdes** — ≥40 tests antes de declarar "100% operativo"
- **Por partes** — cualquier output >300 líneas se divide (regla no-negociable)
- **Legacy backfill** — Task final obligatoria para migrar records existentes (regla no-negociable)

**Objetivo del MVP:** generar videos Reels de **7-15 segundos en formato 9:16 (1080x1920)** con audio, música royalty-free, imágenes stock + AI branded, y captions bilingües, que se publiquen automáticamente junto con los carruseles del Creativo como parte del mismo flujo de social media de Pinnacle.

**Lo que NO hace el MVP** (explícito, Sprint 3+):
- Avatares hablados de Jorge (bloqueado en compra de HeyGen)
- Voice-over en inglés/español (bloqueado en compra de ElevenLabs)
- Video generativo con Kling/Replicate (fuera de scope MVP)
- Testimonios de clientes reales (bloqueado en consent escrito)

---

## 2. Decisiones tomadas en brainstorming (2026-04-24)

Las siete decisiones que definen el MVP, aprobadas por Jorge:

| # | Decisión | Valor elegido |
|---|---|---|
| 1 | Alcance MVP | **B**: Slideshow + Nano Banana hero selectivo (no Kling, no HeyGen) |
| 2 | Narrativas soportadas | **Mezcla B + A + C** (D se activa con HeyGen o testimonios reales) |
| 3 | Input contract | **C**: narrativas como presets internos; el Social Media Agent emite JSON simple en `Visual_Prompt` |
| 4 | Audio / música | **B**: librería local royalty-free (5-8 tracks CC0 Pixabay en `assets/music/`) |
| 5 | Animación captions y transiciones | **B + C selectivo**: xfade + Ken Burns por default, kinetic typography opcional por escena |
| 6 | Imágenes hero | **C → D**: Pexels stock por default, Nano Banana en hook y CTA; migración a pool local cuando Pinnacle tenga portfolio fotográfico |
| 7 | Layout escenas intermedias | **D**: imagen fullscreen + overlay theme + caption bottom-third |

**Costo estimado en producción estable:** ~$0.08/reel narrativa B, ~$0.12/A, ~$0.04/C.  
Con 30 reels/mes ≈ **$2-4/mes** en Nano Banana.

---

## 3. Arquitectura y componentes (Sección 1/6 del design)

### 3.1 Estructura de archivos

```
agents/director_v2/
├── package.json                      # deps: puppeteer, +local assets
├── main.mjs                          # orquestador (Airtable → render → Cloudinary → PATCH)
├── render_poc.mjs                    # POC standalone (1 video demo)
├── spec/
│   └── poc_narrative_b.json          # sample input para el POC
├── src/
│   ├── themes.mjs                    # ← re-export desde creativo_v2/src/themes.mjs
│   ├── wrapper.mjs                   # ← re-export desde creativo_v2/src/wrapper.mjs
│   ├── narratives/
│   │   ├── index.mjs                 # dispatcher: narrative code → scenes[]
│   │   ├── narrative_B.mjs           # hook → 3 puntos → CTA
│   │   ├── narrative_A.mjs           # problem → solution → CTA (Sprint 2)
│   │   └── narrative_C.mjs           # before → after → stats → CTA (Sprint 2)
│   ├── scene_layout.mjs              # layout D: imagen + overlay + caption
│   ├── render.mjs                    # Puppeteer: HTML → JPG o PNG sequence
│   ├── pexels.mjs                    # Pexels API client (stock photos)
│   ├── nano_banana.mjs               # Gemini API client (imágenes branded)
│   ├── audio.mjs                     # pickMusic(mood, duration) + ducking
│   ├── ffmpeg.mjs                    # build video: xfade + zoompan + audio mix
│   ├── cloudinary.mjs                # ← extend desde creativo_v2 con uploadVideo
│   ├── airtable.mjs                  # lista records con Media_Type=reel
│   └── util/
│       └── retry.mjs                 # withRetry(fn, { attempts, baseDelayMs })
├── assets/
│   ├── music/
│   │   ├── upbeat_1.mp3              # 5-8 tracks CC0 de Pixabay
│   │   ├── cinematic_1.mp3
│   │   ├── chill_1.mp3
│   │   ├── tension_1.mp3
│   │   └── LICENSES.md               # URL fuente + licencia por track
│   └── sfx/                          # opcional: whoosh, pop para transiciones
├── test/
│   ├── narratives.test.mjs           # ~12 tests
│   ├── scene_layout.test.mjs         # ~8 tests
│   ├── audio.test.mjs                # ~5 tests
│   ├── pexels.test.mjs               # ~4 tests mocked
│   ├── nano_banana.test.mjs          # ~5 tests mocked
│   ├── ffmpeg.test.mjs               # ~6 tests
│   ├── render.test.mjs               # ~3 tests
│   ├── airtable.test.mjs             # ~6 tests
│   ├── cloudinary.test.mjs           # ~3 tests
│   ├── cost_control.test.mjs         # ~4 tests
│   ├── retry.test.mjs                # ~3 tests
│   ├── main.test.mjs                 # ~4 tests integration mocked
│   ├── smoke.test.mjs                # ~1 smoke opcional (RUN_SMOKE=1)
│   └── fixtures/
│       ├── spec_narrative_B_valid.json
│       ├── spec_narrative_A_valid.json
│       ├── spec_narrative_C_valid.json
│       ├── spec_narrative_B_missing_points.json
│       ├── spec_malformed.txt
│       ├── pexels_response_ok.json
│       ├── pexels_response_429.json
│       ├── gemini_response_ok.png
│       └── cloudinary_video_response.json
├── state/
│   └── nano_banana_usage.json        # contador mensual persistente
└── samples/                          # output videos generados en dev (gitignored)
```

### 3.2 Módulos reutilizados del Creativo v2 (no duplicar)

| Módulo | Rol |
|---|---|
| `themes.mjs` | Los 5 temas T1-T5 con `{bg, text, accent, muted, subtle}` + dims por aspect |
| `wrapper.mjs` | HTML shell con Montserrat + Pinnacle logo inlined (base64) |
| `cloudinary.mjs` | Signed upload (SHA1 signature + FormData) — extendido con `uploadVideo()` |
| Patrón de `airtable.mjs` | `listPending() / parseVisualPrompt() / updateRecord()` con filtro específico para reels |

### 3.3 Módulos NUEVOS del Director v2

| Módulo | Responsabilidad |
|---|---|
| `narratives/*.mjs` | Convierten spec → scenes[] según preset (A/B/C) |
| `scene_layout.mjs` | Layout D: HTML con hero image + gradient overlay + caption + logo |
| `pexels.mjs` | Cliente Pexels: search + download 1080×1920 |
| `nano_banana.mjs` | Cliente Gemini: generate + re-roll + cost counter |
| `audio.mjs` | Selector de track por mood + rotación + ducking futuro |
| `ffmpeg.mjs` | Ensambla escenas: xfade + zoompan + audio mix + libx264 |
| `render.mjs` | Superconjunto del renderer Creativo (1 JPG o N PNGs kinetic) |
| `util/retry.mjs` | Retry wrapper común con backoff exponencial |

### 3.4 Separación de responsabilidades

- `narratives/` decide **QUÉ** escenas y con **QUÉ** duración/mood (conoce spec, no conoce HTML)
- `scene_layout.mjs` decide **CÓMO SE VE** cada escena (conoce HTML, no conoce narrativa)
- `render.mjs` convierte HTML → imágenes (conoce Puppeteer, no conoce ffmpeg)
- `ffmpeg.mjs` ensambla imágenes + música → MP4 (conoce ffmpeg, no conoce tema)
- `main.mjs` orquesta: Airtable → narrative → assets → render → ffmpeg → Cloudinary → PATCH

Cada módulo es testeable independientemente con mocks.

<!-- SECTION_BREAK_AFTER_1 -->
