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
│   ├── airtable.mjs                  # lista records con Formato='Reel'
│   ├── cost_control.mjs              # monthly + per-video Nano Banana budget caps
│   └── util/
│       ├── retry.mjs                 # withRetry(fn, { attempts, baseDelayMs })
│       └── sanitize.mjs              # escapeHtml, sanitizePexelsQuery, sanitizeNanoBananaPrompt, sanitizePublicId
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

## 4. Flujo de datos (Sección 2/6 del design)

### 4.1 Input contract — el JSON de `Visual_Prompt` en Airtable

El Social Media Agent emite un record en `tblAj0Pkj1jW4p5Ld` (base `appU9s3kGkVpdrJkw`) con el campo `Visual_Prompt` que contiene un JSON stringificado. El Director v2 lee records con `Media_Type='reel'`.

**Formato común a todas las narrativas:**
```json
{
  "media_type": "reel",
  "theme": "T1",
  "aspect": "9:16",
  "narrative": "B",
  "duration": 10,
  "mood": "upbeat",
  "hero_hints": {
    "1": { "source": "nano_banana", "prompt": "..." },
    "5": { "source": "nano_banana", "prompt": "..." }
  }
}
```

**Campos específicos por narrativa** — ver Sección 5 del design (expansión de narrativas).

### 4.2 Output — lo que escribe de vuelta a Airtable

Después de procesar exitosamente un record, el Director hace `PATCH` al mismo `recordId` con:

| Campo | Tipo | Valor |
|---|---|---|
| `visual_url` | URL | Cloudinary secure_url del MP4 |
| `video_duration` | number (decimal 1) | segundos reales del MP4 renderizado |
| `video_cost_cents` | number (int) | costo en centavos (Nano Banana × 4) |
| `Status` | single select | `"Lista"` (exitoso) o `"Error"` |
| `Error_Reason` | long text | vacío en éxito, short msg en error |

`director_version` se omite intencionalmente — el prefijo `directorv2/` en el Cloudinary `public_id` identifica al agente y el suffix `.mp4` de `visual_url` distingue reel de carrusel.

### 4.3 Pipeline end-to-end (lo que ejecuta `main.mjs`)

```
 1. Airtable.listPending() filtra por:
    Status='Nueva' AND Visual_Prompt!='' AND visual_url='' AND Media_Type='reel'

 2. Por cada record:
    2a. parseVisualPrompt(record) → spec JSON
         (acepta ```json fenced o JSON plano)
    2b. validateSpec(spec) valida:
         - theme ∈ T1-T5
         - aspect === '9:16'
         - narrative ∈ A|B|C
         - 7 ≤ duration ≤ 15
         - campos específicos de la narrativa presentes
         → throw con mensaje específico si falla

 3. narratives.expandNarrative(spec) → scenes[]:
    scene = {
      index,
      duration,              // segundos
      layoutType,            // 'hook'|'layout_d'|'cta'
      captionEn, captionEs,
      heroSource,            // 'pexels'|'nano_banana'|'local'|'theme_solid'
      heroQuery,             // para pexels
      heroPrompt,            // para nano_banana
      heroLocalId,           // para local (Cloudinary URL o pool curado)
      kinetic,               // boolean
      zoompan,               // { from, to } | null
      transitionOut,         // 'cut'|'crossfade'|'wipeleft'|'slideup'
      mood
    }

 4. Por cada scene:
    4a. Resolver hero image:
         - pexels      → pexels.searchPhoto(query, 1080, 1920) → download a tmp/
         - nano_banana → nano_banana.generate(prompt, 1080, 1920) → download a tmp/
         - local       → si URL remota, descargar con fetch; si id, leer de assets/hero/
         - theme_solid → generar PNG sólido del theme (1080×1920) con node-canvas
                         o inyectar directo como CSS background en el HTML
    4b. scene_layout.buildSceneHtml(scene, heroImagePath, theme, aspect)
    4c. render.renderScene(html, scene):
         - kinetic=false → 1 JPG a tmp/{recordId}/scene_{index}.jpg
         - kinetic=true  → N PNGs (N = fps × duration, ej. 30 × 2 = 60)
                           a tmp/{recordId}/scene_{index}_{frame}.png

 5. audio.pickMusic(spec.mood, totalDuration):
     → path a track mp3 de assets/music/
     → cacheado en disco (no re-lectura por video)

 6. ffmpeg.buildVideo({
      scenes: [
        { imagePath, duration, transitionOut, zoompan, kinetic }
      ],
      musicPath,
      outputPath: `tmp/{recordId}.mp4`,
      width: 1080, height: 1920, fps: 30
    }):
     → comando ffmpeg construido como argv[] (no shell string)
     → xfade entre scenes (0.3s overlap cada transición)
     → zoompan por scene (Ken Burns)
     → amix con música (volume=0.3 default, -4dB LUFS target)
     → libx264 -pix_fmt yuv420p -r 30 -movflags +faststart
     → -c:a aac -b:a 128k

 7. cloudinary.uploadVideo(mp4Path, {
      publicId: `directorv2/{recordId}`,
      folder: 'pinnacle-social-media/videos',
      resource_type: 'video'
    }) → secure_url

 8. airtable.updateRecord(recordId, {
      visual_url: cloudinaryUrl,
      video_duration: actualDurationSeconds,
      video_cost_cents: totalCostCents,
      Status: 'Lista',
      Error_Reason: ''
    })

 9. Cleanup tmp/{recordId}/  (borrar frames temporales)
```

### 4.4 Dry-run mode

Flag CLI: `doppler run -- node main.mjs --dry-run`

**Qué cambia:**
- Paso 1: igual (lee Airtable real)
- Pasos 2-6: igual (render real con Pexels + Nano Banana + ffmpeg)
- Paso 7: **skip** Cloudinary upload
- Paso 8: **skip** Airtable PATCH
- Output local: `samples/dry_run_{recordId}.mp4` para revisión antes de commitear

Utilidad: probar cambios en narratives/scene_layout sin gastar cloud budget ni contaminar Airtable.

### 4.5 Error isolation

**Principio:** un fallo en el procesamiento de 1 record NUNCA rompe el batch.

Estructura del loop:
```javascript
for (const record of pending) {
  try {
    await processRecord(record, { dryRun });
    stats.ok++;
  } catch (err) {
    await safePatchError(record.id, shortMessage(err));
    stats.error++;
    log.error({ recordId: record.id, err: err.message });
    continue;
  }
}
```

`safePatchError()` también envuelve PATCH en try/catch — si Airtable está caído, se loguea a `logs/patch_failures.ndjson` para recovery manual.

## 5. Narrativas A/B/C (Sección 3/6 del design)

Cada narrativa es un módulo `narratives/narrative_X.mjs` que exporta `expand(spec) → scenes[]`. Todas las narrativas producen el mismo shape de `scene` para que `scene_layout.mjs` y `ffmpeg.mjs` sean agnósticos del preset usado.

### 5.1 Shape común de una `scene`

```typescript
type Scene = {
  index: number;                                         // 1-based
  duration: number;                                      // segundos (float)
  layoutType: "hook" | "layout_d" | "cta";
  captionEn: string;
  captionEs?: string;                                    // opcional en cta o stats
  heroSource: "nano_banana" | "pexels" | "local" | "theme_solid";
  heroPrompt?: string | null;                            // si source=nano_banana
  heroQuery?: string | null;                             // si source=pexels
  heroLocalId?: string | null;                           // si source=local
  kinetic: boolean;                                      // true → PNG sequence; false → 1 JPG
  zoompan: { from: number, to: number } | null;          // Ken Burns (scale factors)
  transitionOut: "cut" | "crossfade" | "wipeleft" | "slideup" | "none";
  mood: "upbeat" | "chill" | "cinematic" | "tension";
};
```

### 5.2 Narrativa B — Hook + 3 puntos + CTA (default del MVP)

**Reusa el spec del Creativo v2** — el Social Media Agent emite UN record, y si `media_type` es `"both"` ese record genera simultáneamente el carrusel (Creativo) y el reel (Director).

**Spec mínimo:**
```json
{
  "media_type": "reel",
  "theme": "T1",
  "aspect": "9:16",
  "narrative": "B",
  "duration": 10,
  "mood": "upbeat",
  "hook": { "en": "3 REASONS TO SELL OFF-MARKET", "es": "3 RAZONES PARA VENDER OFF-MARKET", "badge": "WISCONSIN" },
  "points": [
    { "headingEn": "Faster Than Banks", "headingEs": "Más Rápido Que Los Bancos", "bodyEn": "No waiting", "bodyEs": "Sin esperar" },
    { "headingEn": "No Commissions",    "headingEs": "Sin Comisiones",             "bodyEn": "Keep 100%",  "bodyEs": "Quedate 100%" },
    { "headingEn": "No Showings",       "headingEs": "Sin Visitas",                "bodyEn": "Sell as-is", "bodyEs": "Como está" }
  ],
  "cta": { "en": "Get your cash offer today", "es": "Reciba su oferta hoy" }
}
```

**Expansión (5 scenes):**

| # | Rol | Duración | Layout | Kinetic | Hero | Mood | TransitionOut |
|---|---|---|---|---|---|---|---|
| 1 | hook | 2.5s | hook | ✓ | nano_banana (branded) | upbeat | crossfade |
| 2 | point 1 | 2.0s | layout_d | ✗ | pexels (derived query) | upbeat | wipeleft |
| 3 | point 2 | 2.0s | layout_d | ✗ | pexels | upbeat | crossfade |
| 4 | point 3 | 2.0s | layout_d | ✗ | pexels | upbeat | slideup |
| 5 | cta | 2.5s | cta | ✓ | nano_banana (branded) | upbeat | none |

**Total:** 11s → con 4 × 0.3s de xfade solapado ≈ **9.8s efectivo**.

**Nano Banana calls:** 2 por video (hook + cta) = **$0.08/video**.

**heroPrompt defaults:**
- scene 1: `"Modern real estate scene matching: {hook.en}, Pinnacle brand, cinematic, 9:16"`
- scene 5: `"Pinnacle Holdings branded CTA scene, golden hour exterior, cinematic, 9:16"`

**heroQuery derivation** para scenes 2-4: primeras 2 palabras sustantivas del `headingEn` + palabra contextual fija. Tabla de derivación:
```
"Faster Than Banks"  → "clock time money"
"No Commissions"     → "real estate contract"
"No Showings"        → "house closed sign"
"No Repairs"         → "home renovation"
```
Si el heading no matchea ninguna entrada → fallback genérico `"real estate wisconsin"`.

**zoompan** en scenes 1 y 5: `{ from: 1.0, to: 1.05 }` (Ken Burns sutil 5%).  
**zoompan** en scenes 2-4: `{ from: 1.0, to: 1.03 }` (3%, más sutil porque Pexels ya tiene composición fija).

### 5.3 Narrativa A — Problem → Solution → Benefits → CTA (wholesale)

**Spec específico:**
```json
{
  "media_type": "reel",
  "theme": "T1",
  "aspect": "9:16",
  "narrative": "A",
  "duration": 10,
  "problem":  { "en": "Tired of repairs eating your equity?", "es": "¿Cansado de reparaciones?" },
  "solution": { "en": "We buy as-is. Any condition.",         "es": "Compramos como está." },
  "benefits": [
    { "en": "No commissions", "es": "Sin comisiones" },
    { "en": "No showings",    "es": "Sin visitas" }
  ],
  "cta":      { "en": "Get your cash offer", "es": "Reciba su oferta" }
}
```

**Expansión (5 scenes):**

| # | Rol | Duración | Layout | Kinetic | Hero | Mood | TransitionOut |
|---|---|---|---|---|---|---|---|
| 1 | problem | 2.5s | hook | ✓ | nano_banana | tension | crossfade |
| 2 | solution | 2.5s | layout_d | ✓ | nano_banana | upbeat | crossfade |
| 3 | benefit 1 | 1.75s | layout_d | ✗ | pexels | upbeat | wipeleft |
| 4 | benefit 2 | 1.75s | layout_d | ✗ | pexels | upbeat | slideup |
| 5 | cta | 2.0s | cta | ✓ | nano_banana | upbeat | none |

**Total:** 10.5s. **Nano Banana:** 3 calls = **$0.12/video**.

**Mood shift scene 1 → scene 2** (tension → upbeat) se refleja en:
- Selección de track de música (scene 1 empieza en track de mood `tension`, scene 2 en adelante music switch a `upbeat` — handled por ffmpeg audio crossfade)
- Color overlay: scene 1 usa theme.bg (dark premium = verde oscuro) con tinte extra dark; scene 2+ usa el tinte normal

**heroPrompt defaults:**
- scene 1: `"Stressed homeowner looking at damaged house, dramatic lighting, moody, 9:16"`
- scene 2: `"Bright confident real estate professional handshake, Pinnacle brand, golden hour, 9:16"`
- scene 5: `"Pinnacle Holdings modern logo reveal, cinematic, 9:16"`

### 5.4 Narrativa C — Before / After + Stats + CTA (rehab portfolio)

**Spec específico:**
```json
{
  "media_type": "reel",
  "theme": "T3",
  "aspect": "9:16",
  "narrative": "C",
  "duration": 10,
  "intro":      { "en": "We bought this house last month", "es": "Compramos esta casa el mes pasado" },
  "before_url": "https://res.cloudinary.com/.../before_xyz.jpg",
  "after_url":  "https://res.cloudinary.com/.../after_xyz.jpg",
  "stats": [
    { "label": "ROI",  "value": "42%" },
    { "label": "Days", "value": "90"  }
  ],
  "cta": { "en": "Your house could be next", "es": "Su casa podría ser la siguiente" }
}
```

**Expansión (5 scenes):**

| # | Rol | Duración | Layout | Kinetic | Hero | Mood | TransitionOut |
|---|---|---|---|---|---|---|---|
| 1 | intro | 1.5s | hook | ✗ | local (before thumbnail) | chill | crossfade |
| 2 | before | 2.5s | layout_d | ✗ | local (before_url) | chill | wipeleft |
| 3 | after | 2.5s | layout_d | ✗ | local (after_url) | cinematic | crossfade |
| 4 | stats | 2.0s | cta | ✓ | theme_solid | cinematic | crossfade |
| 5 | cta | 2.0s | cta | ✓ | nano_banana | cinematic | none |

**Total:** 10.5s. **Nano Banana:** 1 call = **$0.04/video**.

**Detalles especiales narrativa C:**
- **zoompan scene 2:** `{ from: 1.0, to: 1.08 }` — Ken Burns fuerte para enfatizar deterioro
- **zoompan scene 3:** `{ from: 1.08, to: 1.0 }` — zoom out para revelar resultado completo
- **heroSource `theme_solid`** en scene 4 (stats) — fondo sólido del theme con stats grandes superpuestos
- **captionEn scene 2:** `"BEFORE"` (mayúsculas, hero tratamiento, sin bilingüe)
- **captionEn scene 3:** `"AFTER"`
- **captionEn scene 4:** `stats.map(s => \`${s.label}: ${s.value}\`).join(' • ')` → `"ROI: 42% • Days: 90"`

**Theme recomendado para narrativa C:** T3 (Gold & Black) — contrasta mejor con fotos de rehab y da sensación premium.

### 5.5 `heroSource: "theme_solid"` (nuevo del Director)

No pide ni a Pexels ni a Nano Banana. Genera un fondo visual puramente del theme sin foto. Dos implementaciones posibles:

**Opción 1 (MVP):** CSS puro en el HTML de `scene_layout.mjs`:
```html
<div style="
  width:1080px; height:1920px;
  background: {theme.bg};
  background-image:
    radial-gradient(circle at 20% 20%, rgba(255,255,255,.08), transparent 50%),
    radial-gradient(circle at 80% 80%, {theme.accent}22, transparent 50%);
"></div>
```

**Opción 2 (post-MVP):** generar PNG con `node-canvas` (evita Puppeteer roundtrip para un fondo estático).

### 5.6 Dispatcher `narratives/index.mjs`

```javascript
import { expand as expandB } from './narrative_B.mjs';
import { expand as expandA } from './narrative_A.mjs';
import { expand as expandC } from './narrative_C.mjs';

const REGISTRY = { A: expandA, B: expandB, C: expandC };

export function expandNarrative(spec) {
  const fn = REGISTRY[spec.narrative];
  if (!fn) throw new Error(`Unknown narrative: ${spec.narrative}`);
  return fn(spec);
}

export function validateSpec(spec) {
  if (!spec || typeof spec !== 'object') throw new Error('spec must be object');
  if (!['A', 'B', 'C'].includes(spec.narrative)) throw new Error(`narrative must be A|B|C, got: ${spec.narrative}`);
  if (spec.aspect !== '9:16') throw new Error(`aspect must be 9:16 for Director, got: ${spec.aspect}`);
  if (!['T1','T2','T3','T4','T5'].includes(spec.theme)) throw new Error(`theme must be T1-T5, got: ${spec.theme}`);
  const d = Number(spec.duration);
  if (!Number.isFinite(d) || d < 7 || d > 15) throw new Error(`duration must be 7-15, got: ${spec.duration}`);

  // per-narrative validation
  switch (spec.narrative) {
    case 'B':
      if (!spec.hook?.en || !spec.hook?.es) throw new Error('narrative B requires hook.en and hook.es');
      if (!Array.isArray(spec.points) || spec.points.length < 3) throw new Error('narrative B requires points[3+]');
      if (!spec.cta?.en || !spec.cta?.es) throw new Error('narrative B requires cta.en and cta.es');
      break;
    case 'A':
      if (!spec.problem?.en) throw new Error('narrative A requires problem.en');
      if (!spec.solution?.en) throw new Error('narrative A requires solution.en');
      if (!Array.isArray(spec.benefits) || spec.benefits.length < 2) throw new Error('narrative A requires benefits[2+]');
      if (!spec.cta?.en) throw new Error('narrative A requires cta.en');
      break;
    case 'C':
      if (!spec.before_url) throw new Error('narrative C requires before_url');
      if (!spec.after_url) throw new Error('narrative C requires after_url');
      if (!Array.isArray(spec.stats) || spec.stats.length < 2) throw new Error('narrative C requires stats[2+]');
      if (!spec.cta?.en) throw new Error('narrative C requires cta.en');
      break;
  }
  return true;
}
```

## 6. Error handling + cost control + security (Sección 4/6 del design)

### 6.1 Error handling — matriz de fallos por step del pipeline

| Step | Fallo posible | Estrategia |
|---|---|---|
| 2a `parseVisualPrompt` | JSON malformado | catch → PATCH `Status=Error, Error_Reason='Visual_Prompt no es JSON válido'` → continue |
| 2b `validateSpec` | Campo faltante / valor inválido | PATCH `Error_Reason='invalid: <field>: <detail>'` → continue |
| 3 `expandNarrative` | Narrativa desconocida o spec incompleto | PATCH `Error_Reason='narrative <X> missing <field>'` → continue |
| 4a hero Pexels | Query sin resultados / 429 rate limit | Retry 3× exp backoff → si sigue fallando → fallback a `theme_solid` (no bloquear) |
| 4a hero Nano Banana | Timeout / filtro de seguridad / cara deformada detectada | Re-roll 1× con prompt refinado → si sigue fallando → fallback a Pexels → si Pexels falla → `theme_solid` |
| 4c render Puppeteer | Crash del browser / OOM | Restart browser + reintentar escena 1× → si sigue, PATCH error |
| 5 `pickMusic` | Track missing en disco | Fallback al primer track del mood genérico `"upbeat"` |
| 6 ffmpeg | Exit code ≠ 0 | Capturar stderr → PATCH `Error_Reason='ffmpeg: <last line>'` → continue |
| 7 Cloudinary upload | 5xx / timeout | Retry 3× exp backoff → si sigue fallando → PATCH error |
| 8 Airtable PATCH final | 429 / 5xx | Retry 3× → si falla, log a disco `logs/patch_failures.ndjson` para recovery manual |

**Principio invariante:** un fallo en 1 record NUNCA rompe el batch. El loop procesa N records y al final imprime resumen de status.

### 6.2 Fallback chains

**Hero image fallback chain (por escena):**
```
heroSource = "nano_banana" (solicitado)
   ↓ falla re-roll
heroSource = "pexels" (fallback automático con heroQuery derivado)
   ↓ falla 3× retry
heroSource = "theme_solid" (último recurso, nunca falla)
```

La scene se marca con `heroSourceActual` además de `heroSource` original para tracking. El log NDJSON incluye ambos.

**Music fallback chain:**
```
pickMusic(spec.mood) requested track
   ↓ missing from disk
fallback first track of spec.mood
   ↓ mood dir empty
fallback first track of "upbeat"
   ↓ upbeat dir empty (should never happen)
throw — abort video (esto NO es recuperable)
```

### 6.3 Cost control — caps y tracking

| Cap | Valor | Implementación |
|---|---|---|
| Nano Banana por video | **Máx 3 calls** | `validateSceneBudget(scenes)` cuenta `heroSource === 'nano_banana'` antes de render; si > 3 throws `BudgetExceededError` antes de gastar |
| Nano Banana mensual | **Máx $10/mes (250 calls)** | Contador persistente en `state/nano_banana_usage.json` (YYYY-MM key); al superar, fallback automático a Pexels con log `cost_cap_reached` |
| Cloudinary storage | Free tier 25GB | Cron separado (fuera del MVP): borra videos >30 días con `visual_url` populated |
| Cost tracking per record | `video_cost_cents` field | Cada Nano Banana call incrementa contador del record en +4 cents |

**Monthly budget file format:**
```json
{
  "2026-04": { "calls": 47, "cents": 188, "videos": 18 },
  "2026-05": { "calls": 0, "cents": 0, "videos": 0 }
}
```

**Atomic write pattern** (evita corrupción con concurrent runs):
```javascript
import { writeFile, rename } from 'node:fs/promises';
async function writeUsageAtomic(path, data) {
  const tmp = `${path}.tmp.${process.pid}`;
  await writeFile(tmp, JSON.stringify(data, null, 2));
  await rename(tmp, path);
}
```

**Budget check antes del render:**
```javascript
const currentMonth = new Date().toISOString().slice(0, 7); // "2026-04"
const usage = await loadUsage();
const monthUsage = usage[currentMonth] || { cents: 0 };
const thisVideoCents = countNanoBananaScenes(scenes) * 4;
if (monthUsage.cents + thisVideoCents > 1000) {
  log.warn({ event: 'cost_cap_approaching', monthCents: monthUsage.cents });
  // force ALL scenes to pexels fallback
  scenes.forEach(s => { if (s.heroSource === 'nano_banana') s.heroSource = 'pexels'; });
}
```

### 6.4 Security — secrets, sanitización, scopes

**Secrets — todos vía Doppler, cero plaintext:**
```
GEMINI_API_KEY               (Nano Banana)
PEXELS_API_KEY               (nuevo — hay que añadirlo a Doppler)
CLOUDINARY_NAME              (ya existe)
CLOUDINARY_API_KEY           (ya existe)
CLOUDINARY_API_SECRET        (ya existe)
AIRTABLE_SM_TOKEN            (ya existe)
AIRTABLE_SM_BASE_ID          (ya existe)
AIRTABLE_SM_TABLE_ID         (ya existe)
```

Doppler project: `pinnacle-social-publisher`, config `dev_personal`.  
Ejecución siempre con `doppler run -- node main.mjs`.  
`package.json.scripts.prod` envuelve el comando.

**Sanitización de inputs que llegan a HTML, ffmpeg, shell:**

| Input | Destino | Sanitización |
|---|---|---|
| `captionEn`, `captionEs` | HTML template | escape HTML entities: `&` → `&amp;`, `<` → `&lt;`, `>` → `&gt;`, `"` → `&quot;`, `'` → `&#39;` |
| `heroQuery` | Pexels API URL | URL encode + whitelist `[a-zA-Z0-9 ]+` (strip el resto antes de encode) |
| `heroPrompt` | Nano Banana API body | max 500 chars + strip markers conocidos de prompt injection: `<\|im_end\|>`, `<\|system\|>`, `<\|endoftext\|>`, `###`, `[INST]`, `[/INST]` |
| Paths a ffmpeg | argv array | **NUNCA** string concat; siempre `spawn('ffmpeg', [...args])` como array → zero shell injection |
| `cloudinary.publicId` | URL Cloudinary | whitelist `[a-z0-9_\-/]+` (lowercase, guiones, slashes para folders) |
| `recordId` (Airtable) | paths en tmp/ | whitelist `[a-zA-Z0-9]+` (Airtable IDs son alfanuméricos) |

**Implementación de sanitización en `src/util/sanitize.mjs`:**
```javascript
const HTML_ESCAPE = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
export function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => HTML_ESCAPE[c]);
}

export function sanitizePexelsQuery(q) {
  return String(q ?? '').replace(/[^a-zA-Z0-9 ]+/g, '').trim().slice(0, 100);
}

const PROMPT_INJECTION_MARKERS = [
  /<\|im_end\|>/g, /<\|system\|>/g, /<\|endoftext\|>/g,
  /\[INST\]/g, /\[\/INST\]/g, /###\s*(system|assistant|user)/gi,
];
export function sanitizeNanoBananaPrompt(p) {
  let clean = String(p ?? '');
  for (const re of PROMPT_INJECTION_MARKERS) clean = clean.replace(re, '');
  return clean.slice(0, 500);
}

export function sanitizePublicId(id) {
  return String(id ?? '').toLowerCase().replace(/[^a-z0-9_\-/]+/g, '_');
}

export function sanitizeRecordId(id) {
  const clean = String(id ?? '').replace(/[^a-zA-Z0-9]+/g, '');
  if (!clean) throw new Error('invalid recordId');
  return clean;
}
```

**Airtable scope:**
- Token principal (`AIRTABLE_SM_TOKEN`) — scope `data.records:read + data.records:write` sobre base `appU9s3kGkVpdrJkw`. **Sin** `schema.bases:write`, **sin** delete.
- Para `Task 0 schema setup` se usa un token temporal con scope `schema.bases:write` que se crea una vez, se corre el script, y se elimina: `doppler secrets delete AIRTABLE_SM_SCHEMA_TOKEN`.

**Anti-prompt-injection en contenido externo (regla del Jefe, 2026-04-22):**
- Si Pexels devuelve metadata con strings tipo `"ignore previous instructions"`, `"you are now"`, `"system prompt"` → log alerta `{event: 'prompt_injection_detected', source: 'pexels'}` y usar la foto igual (es un asset binario — no se ejecutan instrucciones)
- Nano Banana responses se tratan como binario opaco (imagen PNG); no se parsea texto de la API response más allá del Content-Type
- Jamás se usa `eval`, `Function(...)`, ni se interpola input externo en comandos shell

**tmp/ cleanup:**
- `main.mjs` borra `tmp/` al inicio del batch (rmSync recursive force)
- Por cada record procesado: borra `tmp/{recordId}/` al terminar (success o error)
- Garantiza no dejar assets de un record en disco entre runs

### 6.5 Rate limits + retry policy

| API | Límite | Retry | Backoff |
|---|---|---|---|
| Pexels | 200/hora (free tier) | 3 | 2s, 4s, 8s |
| Gemini (Nano Banana) | ~60 req/min (free tier) | 3 | 3s, 6s, 12s |
| Cloudinary upload | 500/hora (free tier) | 3 | 2s, 4s, 8s |
| Airtable | 5 req/s por base | 3 | 1s, 2s, 4s |

**Retry wrapper común** (`src/util/retry.mjs`):
```javascript
export async function withRetry(fn, { attempts = 3, baseDelayMs = 1000, onRetry = () => {} } = {}) {
  let lastErr;
  for (let i = 0; i < attempts; i++) {
    try {
      return await fn();
    } catch (err) {
      lastErr = err;
      if (i === attempts - 1) break;
      const delay = baseDelayMs * Math.pow(2, i);
      onRetry(err, i + 1, delay);
      await new Promise(r => setTimeout(r, delay));
    }
  }
  throw lastErr;
}
```

Los clientes (`pexels.mjs`, `nano_banana.mjs`, `cloudinary.mjs`, `airtable.mjs`) usan este wrapper con los valores de la tabla.

### 6.6 Observabilidad — log estructurado

**Formato:** NDJSON a stdout (una línea JSON por evento). Redirigir a `logs/director_v2_{YYYY-MM-DD}.ndjson` vía `tee` en producción.

**Ejemplos de líneas:**
```json
{"ts":"2026-04-24T14:22:01Z","recordId":"rec123","step":"render","scene":2,"duration_ms":1240}
{"ts":"2026-04-24T14:22:05Z","recordId":"rec123","step":"nano_banana","cost_cents":4,"scene":1}
{"ts":"2026-04-24T14:22:06Z","recordId":"rec123","step":"hero_fallback","from":"nano_banana","to":"pexels","reason":"re_roll_failed"}
{"ts":"2026-04-24T14:22:10Z","recordId":"rec123","step":"ffmpeg","ok":true,"output_mb":2.4,"duration_s":10.2}
{"ts":"2026-04-24T14:22:12Z","recordId":"rec123","step":"cloudinary_upload","ok":true,"public_id":"directorv2/rec123"}
{"ts":"2026-04-24T14:22:13Z","recordId":"rec123","step":"airtable_patch","status":"Lista"}
```

**Resumen al final del batch (stdout, legible):**
```
════════════════════════════════════════
Director v2 — Batch Summary
════════════════════════════════════════
Records procesados:  20
 ├─ Lista:           17
 ├─ Error:            2
 └─ Fallback:         1 (nano_banana → pexels cost cap)
Nano Banana calls:  35 ($1.40)
Pexels calls:       52
Cloudinary uploads: 17 (total 38.2 MB)
Duración total:     4m 12s
════════════════════════════════════════
```

## 7. Testing strategy (Sección 5/6 del design)

### 7.1 Framework

- **Test runner:** Node built-in (`node --test test/**/*.test.mjs`) — mismo stack que Creativo v2, cero deps externas
- **Assertion library:** `node:assert/strict` built-in
- **Mock HTTP:** `src/__mocks__/fetch.mjs` — inject vía `__setFetch()` en cada cliente (patrón del Creativo: `import { __setFetch } from './airtable.mjs'`)
- **Fixtures:** archivos estáticos en `test/fixtures/`
- **Ejecución:** `doppler run -- npm test` (los tests mocked no necesitan secrets reales pero Doppler no falla si están vacíos)

**Script de test en `package.json`:**
```json
{
  "scripts": {
    "test": "node --test test/*.test.mjs",
    "test:smoke": "RUN_SMOKE=1 node --test test/smoke.test.mjs",
    "prod": "doppler run -- node main.mjs",
    "prod:dry-run": "doppler run -- node main.mjs --dry-run",
    "poc": "doppler run -- node render_poc.mjs"
  }
}
```

### 7.2 Objetivo de cobertura

**Threshold para marcar "100% operativo":** ≥40 tests verdes (iguala o supera Creativo v2 que tiene 42).

### 7.3 Pirámide de tests

```
     ┌─────────┐
     │ smoke 1 │     1 E2E real (sin Nano Banana) — opcional, no bloqueante en CI
     ├─────────┤
     │ integ 8 │     8 integration (mocked HTTP / Puppeteer)
     ├─────────┤
     │unit 35+ │     35+ unit tests (funciones puras)
     └─────────┘
```

### 7.4 Coverage por módulo (breakdown de ~55 tests)

**`test/narratives.test.mjs` (~12 tests)**
- narrative_B expande a 5 scenes con durations correctas (2.5, 2.0, 2.0, 2.0, 2.5)
- narrative_B valida 3+ points obligatorios
- narrative_A expande a 5 scenes con mood shift tension→upbeat en scene 2→3
- narrative_A valida problem, solution, benefits[2+], cta
- narrative_C expande a 5 scenes con before/after URLs locales + theme_solid en stats
- narrative_C valida before_url, after_url, stats[2+], cta
- dispatcher throws en narrativa desconocida (`'X'`)
- dispatcher throws en spec sin narrative field
- cada narrativa → total duration ∈ [7, 15] con datos default
- cada narrativa → Nano Banana count ≤ 3 (cost cap)
- heroQuery derivation: "Faster Than Banks" → "clock time money"
- heroQuery fallback genérico para heading no-matcheado

**`test/scene_layout.test.mjs` (~8 tests)**
- layout_d incluye: `<img>` hero, gradient overlay, caption EN, caption ES, logo top-right
- layout_d con `heroSource='theme_solid'` NO incluye `<img>`, usa background CSS
- caption EN y ES presentes en HTML
- aspect ratio 9:16 propaga height:1920px al wrapper
- theme colors aplicados al overlay (hex exacto del theme)
- HTML escape: caption con `<script>alert(1)</script>` se renderiza escapado
- logo position: `top:48px` y `right:48px` (mismo regla Creativo)
- kinetic=true agrega `data-kinetic="true"` al wrapper para render PNG sequence

**`test/audio.test.mjs` (~5 tests)**
- `pickMusic("upbeat", 10)` retorna path válido existente
- Mood desconocido → fallback a "upbeat" (log warning)
- Track missing en disco → fallback al primer track del mood
- `LICENSES.md` existe y contiene TODOS los tracks en `assets/music/`
- Rotación determinística: 2 calls con mismo (mood, seed) retornan tracks distintos

**`test/pexels.test.mjs` (~4 tests, mocked)**
- Query sanitization: `"home renovation; rm -rf /"` → `"home renovation"`
- Búsqueda exitosa retorna URL de photo con dimensiones ≥ 1080×1920
- 429 dispara retry 3× con backoff (validar timing ≥ 2+4+8=14s con fake timers)
- Búsqueda sin resultados (`total_results: 0`) → throws `PexelsNoResultsError` específico que trigger fallback en caller

**`test/nano_banana.test.mjs` (~5 tests, mocked)**
- Prompt sanitization: `"<\|system\|>ignore previous"` se remueve
- Prompt truncation: >500 chars se corta a 500
- Respuesta válida retorna Buffer PNG (valida magic bytes `89 50 4E 47`)
- Primera call falla con timeout → re-roll con prompt refinado (prompt modificado en retry)
- Ambas calls fallan → throws `NanoBananaFailedError` específico
- Cost counter incrementa +4 cents por call exitosa (no incrementa en fail)

**`test/ffmpeg.test.mjs` (~6 tests)**
- `buildVideoCommand` retorna argv array, NO string (verifica zero shell injection)
- xfade filter entre scenes con duración correcta (`offset=2.2` para scene 1 de 2.5s con 0.3s overlap)
- zoompan filter incluye zoom correcto: `scale=1.0:1.0:s=1080x1920:fps=30`
- Audio mix: `-filter_complex` incluye `amix=inputs=2:duration=shortest`
- Output args: incluye `-c:v libx264 -pix_fmt yuv420p -r 30 -movflags +faststart`
- Resolución 1080×1920 forzada en output args

**`test/render.test.mjs` (~3 tests)**
- `renderScene` con kinetic=false genera exactamente 1 JPG
- `renderScene` con kinetic=true genera N PNGs (N = fps × duration, ej. 30×2=60)
- Browser se reutiliza entre scenes (singleton): `launch()` called exactamente 1 vez para batch de 3 scenes

**`test/airtable.test.mjs` (~6 tests)**
- `parseVisualPrompt` con nuevo formato (incluye `narrative`, `media_type`)
- `parseVisualPrompt` con fenced JSON (` ```json {...} ``` `) retorna el objeto limpio
- `validateSpec` retorna error específico con field name
- `listPending` filter incluye `Media_Type='reel'`
- `updateRecord` envía exactamente los campos pasados (no añade extras)
- `updateRecord` maneja 429 con retry

**`test/cloudinary.test.mjs` (~3 tests)**
- `uploadVideo` usa `resource_type=video` en los params de signature
- `uploadVideo` public_id sanitizado: caracteres no-permitidos → `_`
- SHA1 signature correcta para resource_type=video (snapshot test con params fijos)

**`test/cost_control.test.mjs` (~4 tests)**
- Cap per-video: 4 scenes con `heroSource='nano_banana'` → throws `BudgetExceededError` antes de render
- Cap mensual: usage 980 cents + video de 40 cents → todos los nano_banana se forzan a pexels
- Counter file corrupto → se re-inicializa en próximo read (no crash)
- Atomic write: `writeUsageAtomic` crea tmp file y rename (verify con spy on `rename`)

**`test/retry.test.mjs` (~3 tests)**
- `withRetry(fn, {attempts:3})` con fn que falla 2 veces + succ la 3ª → retorna el valor correcto
- onRetry callback invocado con `(error, attemptNumber, delay)` en retries 1 y 2
- fn que falla las 3 veces → throws el último error

**`test/main.test.mjs` (~4 tests, integration mocked)**
- 1 record procesa end-to-end OK con todas las APIs mockedas
- 1 record con `Visual_Prompt` malformado → PATCH `Status=Error`, no rompe batch
- Dry-run mode: NO llama Cloudinary, NO llama Airtable PATCH, MP4 queda en `samples/dry_run_{recordId}.mp4`
- Batch de 3 records (1 OK, 1 malformed, 1 ffmpeg error) → summary correcto: `ok:1, error:2`

### 7.5 Smoke test opcional (`test/smoke.test.mjs`)

Se corre sólo con `RUN_SMOKE=1 npm run test:smoke`.

```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import { execSync } from 'node:child_process';

test('render poc_narrative_b.json end-to-end (Pexels only, no Nano Banana)',
  { skip: !process.env.RUN_SMOKE }, async () => {
    // lee spec POC, fuerza todos los heros a pexels
    const spec = JSON.parse(await readFile('spec/poc_narrative_b.json', 'utf8'));
    spec.hero_hints = {}; // remove nano_banana overrides
    process.env.FORCE_PEXELS_ONLY = '1';

    execSync('node render_poc.mjs --dry-run', { stdio: 'inherit' });

    const mp4 = await stat('samples/dry_run_poc.mp4');
    assert.ok(mp4.size > 100_000, 'MP4 debe pesar al menos 100KB');

    // validate with ffprobe
    const probe = execSync('ffprobe -v error -show_entries stream=codec_name,width,height,duration -of json samples/dry_run_poc.mp4').toString();
    const info = JSON.parse(probe);
    const video = info.streams.find(s => s.codec_name === 'h264');
    assert.equal(video.width, 1080);
    assert.equal(video.height, 1920);
    assert.ok(Math.abs(parseFloat(video.duration) - 10) < 1.5, 'duration within 10±1.5s');
});
```

### 7.6 Fixtures (`test/fixtures/`)

| Archivo | Uso |
|---|---|
| `spec_narrative_B_valid.json` | caso feliz narrative B |
| `spec_narrative_A_valid.json` | caso feliz narrative A |
| `spec_narrative_C_valid.json` | caso feliz narrative C |
| `spec_narrative_B_missing_points.json` | validación de campos |
| `spec_malformed.txt` | JSON roto para test de parseVisualPrompt |
| `pexels_response_ok.json` | mock de `/v1/search` success |
| `pexels_response_429.json` | mock de rate limit |
| `pexels_response_empty.json` | mock de no results |
| `gemini_response_ok.png` | mock binary (magic bytes válidos) |
| `cloudinary_video_response.json` | mock de upload video success |
| `airtable_records_pending.json` | mock de listPending |

### 7.7 CI (Sprint 2, no bloqueante del MVP)

GitHub Actions workflow `.github/workflows/director_v2_test.yml`:
```yaml
name: Director v2 tests
on:
  push:
    branches: [claude/greeting-setup-yOfqf, main]
    paths: ['agents/director_v2/**']
  pull_request:
    paths: ['agents/director_v2/**']
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '22' }
      - run: sudo apt-get update && sudo apt-get install -y ffmpeg
      - run: npm ci
        working-directory: agents/director_v2
      - run: npm test
        working-directory: agents/director_v2
```

No se corre el smoke test en CI (requiere Pexels API real). Se ejecuta manualmente antes de cada push significativo.

## 8. POC + Sprints + Task 0 + Backfill (Sección 6/6 del design)

### 8.1 POC concreto (primera demo que rendenderamos)

**Archivo:** `agents/director_v2/spec/poc_narrative_b.json`

**Concepto:** el mismo mensaje del carrusel POC del Creativo v2 ("5 reasons to sell off-market"), adaptado a 10s con 3 puntos top. Mismo theme T1 (Dark Premium). Cohesión cross-format: el carrusel (Creativo) y el reel (Director) forman una misma pieza de comunicación para publicación paralela.

**Contenido del spec:**
```json
{
  "media_type": "reel",
  "theme": "T1",
  "aspect": "9:16",
  "narrative": "B",
  "duration": 10,
  "mood": "upbeat",
  "hook": {
    "en": "3 REASONS TO SELL OFF-MARKET",
    "es": "3 RAZONES PARA VENDER OFF-MARKET",
    "badge": "WISCONSIN"
  },
  "points": [
    { "headingEn": "Faster Than Banks",   "headingEs": "Más Rápido Que Los Bancos", "bodyEn": "No waiting for approval", "bodyEs": "Sin esperar aprobación" },
    { "headingEn": "No Commissions",      "headingEs": "Sin Comisiones",             "bodyEn": "Keep 100% of offer",      "bodyEs": "Quedate con el 100%" },
    { "headingEn": "No Showings",         "headingEs": "Sin Visitas",                "bodyEn": "Sell as-is, today",       "bodyEs": "Vende como está, hoy" }
  ],
  "cta": {
    "en": "Get your cash offer today",
    "es": "Reciba su oferta en efectivo hoy"
  }
}
```

### 8.2 Criterios de éxito del POC

El POC se considera aprobado si TODOS estos se cumplen:

1. **Formato correcto:** MP4 H.264, 1080×1920, duración 10±1s
2. **Audio:** stream de audio presente (AAC 128kbps stereo/mono), música audible
3. **Branding:** Pinnacle logo visible en top-right de las 5 scenes
4. **Assets:** hook + CTA con Nano Banana hero branded; points 2-4 con Pexels stock
5. **Animación:** kinetic typography en scene 1 y scene 5; xfade entre todas las transiciones; zoompan sutil en todas las scenes
6. **Deploy:** Cloudinary URL retornado y funcional (abre en browser, reproduce)
7. **Costo real:** ≤ $0.10 (2 Nano Banana × $0.04 + margen)
8. **Performance:** tiempo total de render ≤ 60s (browser reuse + paralelismo en fetch de Pexels)
9. **Preview social:** el URL se puede previsualizar en IG Stories / FB Reels sin errors de formato

Jorge revisa la URL → da "go" → POC aprobado → pasamos a productivizar.

### 8.3 Task 0 — Airtable schema setup (pre-Sprint 1)

**Script:** `scripts/airtable_schema_setup.mjs` (idempotente, con dry-run).

**Acciones:**
1. Verificar conexión con base `appU9s3kGkVpdrJkw` usando `AIRTABLE_SM_SCHEMA_TOKEN`
2. Descubrir tabla `tblAj0Pkj1jW4p5Ld` via `GET /meta/bases/{baseId}/tables`
3. Verificar si `Media_Type` tiene opción `"reel"`:
   - Si no, `PATCH /meta/bases/{baseId}/tables/{tableId}/fields/{fieldId}` con `options.choices += [{name: 'reel'}]`
4. Verificar si campo `video_duration` existe:
   - Si no, `POST /meta/bases/{baseId}/tables/{tableId}/fields` con `{name: 'video_duration', type: 'number', options: {precision: 1}}`
5. Verificar si campo `video_cost_cents` existe:
   - Si no, `POST /meta/bases/{baseId}/tables/{tableId}/fields` con `{name: 'video_cost_cents', type: 'number', options: {precision: 0}}`
6. Log de cambios aplicados o `"no changes needed"` si ya está todo

**Instrucciones para Jorge (una sola vez, antes de Task 0):**
> 1. Airtable → Account → Developer Hub → Create PAT
> 2. Scopes: `schema.bases:write` + acceso a base `appU9s3kGkVpdrJkw`
> 3. Copiar token
> 4. `doppler secrets set AIRTABLE_SM_SCHEMA_TOKEN=patXXXXX.YYYYY`
> 5. Después de correr Task 0 exitoso:
>    `doppler secrets delete AIRTABLE_SM_SCHEMA_TOKEN`
>    (limpieza del token elevado — el Director en producción sólo necesita scope read/write de data, nunca schema)

**Dry-run:**
```bash
doppler run -- node scripts/airtable_schema_setup.mjs --dry-run
```
Imprime las operaciones que haría sin ejecutarlas.

**Ejecución real:**
```bash
doppler run -- node scripts/airtable_schema_setup.mjs
```

### 8.4 Sprint 1 — MVP Narrativa B + stack completo (~3-5 sesiones)

| # | Task | Output esperado | Tests |
|---|---|---|---|
| 0 | Task 0 Airtable schema setup | 2 campos creados + opción `reel` añadida | N/A |
| 1 | Scaffold `agents/director_v2/` + package.json + doppler setup local | árbol de archivos + `npm test` corre vacío (OK) | — |
| 2 | Descargar 5-8 tracks CC0 de Pixabay + LICENSES.md + `src/audio.mjs` | tracks en `assets/music/` + selector | 5 |
| 3 | `src/util/retry.mjs` + `src/util/sanitize.mjs` | utilities | 3 retry tests |
| 4 | `src/pexels.mjs` con sanitization + retry + cliente | cliente Pexels | 4 |
| 5 | `src/nano_banana.mjs` con re-roll + cost counter + atomic write | cliente Gemini | 5 |
| 6 | `src/scene_layout.mjs` — layout D con theme_solid fallback | HTML renderizable | 8 |
| 7 | `src/render.mjs` — JPG único o PNG sequence con browser singleton | renderer | 3 |
| 8 | `src/narratives/narrative_B.mjs` + `narratives/index.mjs` dispatcher | expander B + validator | 4 (B only) |
| 9 | `src/ffmpeg.mjs` — build command con xfade, zoompan, amix | ensamblador video | 6 |
| 10 | `src/cloudinary.mjs` (extend desde Creativo con `uploadVideo`) | uploader video | 3 |
| 11 | `src/airtable.mjs` (adaptar desde Creativo con narrative validation) | lister + parser + updater | 6 |
| 12 | `main.mjs` + `--dry-run` flag + error isolation | orquestador | 4 integration |
| 13 | `src/cost_control.mjs` + persistent usage counter | cost tracker | 4 |
| 14 | **POC render** (`render_poc.mjs`) + review manual de Jorge | MP4 en Cloudinary | smoke 1 |
| 15 | Commit + push branch `claude/greeting-setup-yOfqf` | historial git limpio | — |

**Total tests Sprint 1:** ~55 (supera threshold de 40).

### 8.5 Sprint 2 — Narrativas A + C (~2 sesiones, no bloqueante)

| # | Task |
|---|---|
| 16 | `src/narratives/narrative_A.mjs` + fixture spec |
| 17 | `src/narratives/narrative_C.mjs` + soporte `heroSource='local'` (descargar before/after Cloudinary URLs) |
| 18 | Soporte `heroSource='theme_solid'` completo en scene_layout (ya existe parcial en Sprint 1 para narrativa C scene 4) |
| 19 | 8 tests nuevos para narratives A y C |
| 20 | POCs opcionales: 1 video narrativa A + 1 video narrativa C para validar los 3 formatos |

### 8.6 Sprint 3 — Enhancements futuros (bloqueados)

| # | Task | Bloqueo |
|---|---|---|
| 21 | `src/heygen.mjs` — avatar Jorge talking head | Jorge compra HeyGen |
| 22 | `src/elevenlabs.mjs` — voice-over bilingüe | Jorge compra ElevenLabs |
| 23 | `narratives/narrative_D.mjs` (testimoniales) | Consent escrito de clientes reales |
| 24 | `src/replicate.mjs` — Kling generative video clips | Cuando aparezca caso de uso específico |
| 25 | Kinetic typography avanzado (FFmpeg libass con ASS subtitles) | Sprint 3 |
| 26 | Cloudinary video cleanup cron (30 días) | Sprint 3 |
| 27 | Migración a pool local de heros (opción D de la Pregunta 6) | Pinnacle tiene portfolio fotográfico documentado con consent |

### 8.7 Task Backfill — Legacy Record Backfill (obligatoria post-Sprint 1)

Según la **regla del Jefe del 2026-04-24 "LEGACY RECORD BACKFILL" (no negociable)**, cualquier flujo nuevo que procese records con formato distinto debe incluir una Task final de backfill one-time.

**Script:** `scripts/backfill_legacy_reels.mjs`

**Lógica:**
```
1. Leer Airtable records con:
   Media_Type='reel' AND (visual_url='' OR Status='Error')

2. Para cada record legacy:
   2a. Leer Visual_Prompt actual
   2b. Intentar JSON.parse:
       - Si parsea y tiene {narrative, theme, hook, points, cta} → YA ES NUEVO FORMATO → skip
       - Si parsea pero es formato viejo → migrar a nuevo formato con heurísticas:
           narrative: "B" (default más común)
           theme: "T1" (default)
           aspect: "9:16"
           duration: 10
           mood: "upbeat"
           hook: extraer de campos { text, title, idea } o generar desde Caption_EN
           points: mapear de campos { reasons, bullets, benefits } o parsear Caption_EN por "-" / "•" / "\n"
           cta: extraer de { cta, call_to_action } o default "Get your cash offer today"
       - Si NO parsea (es string descriptivo tipo "A modern real estate ad with..."): construir JSON mínimo desde Caption_EN splitting por líneas, asumiendo narrativa B

   2c. PATCH Airtable con:
       - Visual_Prompt: nuevo JSON stringificado
       - Status: 'Nueva' (re-activa para el próximo run del Director)
       - Error_Reason: '' (limpia errores previos)
       - video_duration: null (lo llenará el Director)
       - video_cost_cents: null

3. Dry-run mode: imprime diff propuesto (antes → después) por record, no PATCH

4. Log NDJSON a disco: logs/backfill_reels_YYYY-MM-DD.ndjson
   - Cada línea: {recordId, action: 'migrated'|'skipped'|'failed', reason}

5. Resumen al final:
   ════════════════════════════════
   Backfill Legacy Reels Summary
   ════════════════════════════════
   Total records escaneados:  N
   Migrados:                  N
   Skip (ya eran v2):         N
   Failed (no parseable):     N   (listados para revisión manual)
   ════════════════════════════════

6. Commit explícito: "backfill: migrate legacy reel records to v2 format"

7. Una vez ejecutado → main.mjs procesa los records migrados en el próximo run normal
```

**Idempotencia:** el script detecta records que ya tienen JSON v2 válido y los saltea. Puede correrse N veces sin efectos laterales.

**Dry-run obligatorio antes de ejecutar en producción:**
```bash
doppler run -- node scripts/backfill_legacy_reels.mjs --dry-run
# Revisar output
doppler run -- node scripts/backfill_legacy_reels.mjs
```

### 8.8 Criterio de cierre "Director v2 al 100% operativo"

Checklist que replica el cierre del Creativo v2 (2026-04-24):

- ✅ Sprint 1 completo (Tasks 0-15)
- ✅ ≥40 tests verdes (target: ~55)
- ✅ POC narrativa B aprobado por Jorge manualmente
- ✅ Doppler con 8+ secrets:
  - AIRTABLE_SM_TOKEN, AIRTABLE_SM_BASE_ID, AIRTABLE_SM_TABLE_ID
  - CLOUDINARY_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET
  - GEMINI_API_KEY
  - **PEXELS_API_KEY** (nuevo)
  - (temporalmente: AIRTABLE_SM_SCHEMA_TOKEN durante Task 0, se borra después)
- ✅ Task 0 Airtable schema setup ejecutado OK (2 campos + opción reel)
- ✅ Task Backfill legacy reels ejecutado (dry-run + producción)
- ✅ Commit + push a `claude/greeting-setup-yOfqf` con historial limpio
- ✅ Memorias actualizadas con entrada "2026-XX-XX — EL DIRECTOR v2 100% OPERATIVO":
  - `memoria_ALex.md`
  - `agents/memoria_alex.md`
  - `telegram_bot/telegram_memory.md`
- ✅ `CLAUDE.md` actualizado con referencia a Director v2 (sub-agente listado)
- ✅ `agents/director.md` (v2.0 legacy Blotato) reemplazado o marcado como deprecated
- Sprint 2 (A + C) y Sprint 3 (HeyGen/ElevenLabs/Kling) marcados como **"pendientes no-bloqueantes"**

## 9. Appendix

### 9.1 Doppler secrets breakdown

**Project:** `pinnacle-social-publisher`  
**Config:** `dev_personal` (local y producción)

| Secret | Usado por | Scope |
|---|---|---|
| `AIRTABLE_SM_TOKEN` | Creativo + Director | data.records:read + write, base `appU9s3kGkVpdrJkw` |
| `AIRTABLE_SM_BASE_ID` | Creativo + Director | `appU9s3kGkVpdrJkw` |
| `AIRTABLE_SM_TABLE_ID` | Creativo + Director | `tblAj0Pkj1jW4p5Ld` |
| `AIRTABLE_SM_SCHEMA_TOKEN` | Director Task 0 únicamente | schema.bases:write — TEMPORAL, se borra post-Task 0 |
| `CLOUDINARY_NAME` | Creativo + Director | `dzzlhhk0m` |
| `CLOUDINARY_API_KEY` | Creativo + Director | upload signed |
| `CLOUDINARY_API_SECRET` | Creativo + Director | signature |
| `GEMINI_API_KEY` | Director (nano_banana) | Nano Banana image gen |
| `PEXELS_API_KEY` | Director (pexels) | **NUEVO** — stock photos |
| `REPLICATE_API_TOKEN` | Director Sprint 3+ | Kling video (bloqueado) |

### 9.2 Ejemplos de heroPrompt por theme

Para Nano Banana hook/cta — el prompt se construye concatenando el copy del hook con guardrails del theme:

| Theme | Prompt suffix para hook |
|---|---|
| T1 Dark Premium | `", cinematic, dark moody lighting, emerald green and gold accents, Pinnacle Holdings brand aesthetic, 9:16 vertical"` |
| T2 White Clean | `", bright clean minimalist, white background, natural lighting, modern real estate aesthetic, 9:16 vertical"` |
| T3 Gold & Black | `", luxury aesthetic, black and gold, dramatic contrast, premium real estate, 9:16 vertical"` |
| T4 Soft Cream | `", warm cream and beige tones, soft natural lighting, cozy home aesthetic, 9:16 vertical"` |
| T5 Vibrant Blue | `", vibrant energetic, blue and pink accents, modern urban aesthetic, bold, 9:16 vertical"` |

### 9.3 Ejemplos de heroQuery derivation (Pexels)

Mapeo de `headingEn` frecuentes en wholesale/fix&flip a query de Pexels:

| heading | query |
|---|---|
| Faster Than Banks | `clock time money` |
| No Commissions | `real estate contract` |
| No Showings | `house closed sign` |
| No Repairs | `home renovation` |
| Cash Offer | `cash money deal` |
| Close in 7 Days | `calendar keys house` |
| Any Condition | `vintage house exterior` |
| Sell As-Is | `house vintage interior` |
| _fallback_ | `real estate wisconsin` |

### 9.4 ffmpeg command example (narrativa B POC)

Para referencia, el comando que se genera para 5 scenes con xfade y zoompan:

```
ffmpeg -y \
  -loop 1 -t 2.5 -i tmp/rec123/scene_1.jpg \
  -loop 1 -t 2.0 -i tmp/rec123/scene_2.jpg \
  -loop 1 -t 2.0 -i tmp/rec123/scene_3.jpg \
  -loop 1 -t 2.0 -i tmp/rec123/scene_4.jpg \
  -loop 1 -t 2.5 -i tmp/rec123/scene_5.jpg \
  -i assets/music/upbeat_1.mp3 \
  -filter_complex "\
    [0:v]scale=1080:1920,zoompan=z='min(1.0+0.05*on/75,1.05)':d=75:s=1080x1920:fps=30[v0]; \
    [1:v]scale=1080:1920,zoompan=z='min(1.0+0.03*on/60,1.03)':d=60:s=1080x1920:fps=30[v1]; \
    [2:v]scale=1080:1920,zoompan=z='min(1.0+0.03*on/60,1.03)':d=60:s=1080x1920:fps=30[v2]; \
    [3:v]scale=1080:1920,zoompan=z='min(1.0+0.03*on/60,1.03)':d=60:s=1080x1920:fps=30[v3]; \
    [4:v]scale=1080:1920,zoompan=z='min(1.0+0.05*on/75,1.05)':d=75:s=1080x1920:fps=30[v4]; \
    [v0][v1]xfade=transition=fade:duration=0.3:offset=2.2[x01]; \
    [x01][v2]xfade=transition=wipeleft:duration=0.3:offset=3.9[x012]; \
    [x012][v3]xfade=transition=fade:duration=0.3:offset=5.6[x0123]; \
    [x0123][v4]xfade=transition=slideup:duration=0.3:offset=7.3[vout]; \
    [5:a]volume=0.35,aloop=loop=-1:size=2e+09[aout] \
  " \
  -map "[vout]" -map "[aout]" \
  -c:v libx264 -pix_fmt yuv420p -r 30 -movflags +faststart \
  -c:a aac -b:a 128k \
  -t 10.0 \
  tmp/rec123.mp4
```

Todos los `[i]` vienen de argv indices, nunca de string concat. `spawn('ffmpeg', [...])` acepta arrays.

### 9.5 package.json (draft)

```json
{
  "name": "@pinnacle/director-v2",
  "private": true,
  "type": "module",
  "version": "0.1.0",
  "engines": { "node": ">=22.0.0" },
  "scripts": {
    "test":         "node --test test/*.test.mjs",
    "test:smoke":   "RUN_SMOKE=1 node --test test/smoke.test.mjs",
    "prod":         "doppler run -- node main.mjs",
    "prod:dry-run": "doppler run -- node main.mjs --dry-run",
    "poc":          "doppler run -- node render_poc.mjs",
    "backfill":             "doppler run -- node scripts/backfill_legacy_reels.mjs",
    "backfill:dry-run":     "doppler run -- node scripts/backfill_legacy_reels.mjs --dry-run",
    "schema":               "doppler run -- node scripts/airtable_schema_setup.mjs",
    "schema:dry-run":       "doppler run -- node scripts/airtable_schema_setup.mjs --dry-run"
  },
  "dependencies": {
    "puppeteer": "^23.0.0"
  }
}
```

Sin otras deps runtime — usa node built-ins (`node:fetch`, `node:fs/promises`, `node:crypto`, `node:child_process` para ffmpeg, `node:test` para tests).

### 9.6 .gitignore additions

```
# Director v2
agents/director_v2/tmp/
agents/director_v2/samples/
agents/director_v2/logs/
agents/director_v2/state/nano_banana_usage.json
agents/director_v2/node_modules/
```

### 9.7 Dependencies en sistema (no-Node)

- **ffmpeg** 6.1.1+ con libx264, libass, libmp3lame, libvorbis — ya instalado (`apt-get install ffmpeg`)
- **Chromium** (viene con Puppeteer) — descargado automáticamente en `npm install`
- **Doppler CLI** 3.76.0+ — ya instalado en la máquina de Jorge y en Claude Code env

---

## 10. Resumen ejecutivo del design (las 6 secciones)

| Sección | Decisión clave |
|---|---|
| 1. Arquitectura | `agents/director_v2/` con 7 módulos nuevos + 4 reuse del Creativo |
| 2. Flujo de datos | Input JSON en `Visual_Prompt` Airtable; pipeline de 9 steps; `--dry-run` flag |
| 3. Narrativas | 3 presets (B default, A wholesale, C rehab) que expanden a 5 scenes cada uno |
| 4. Errores/Cost/Sec | Fallback chain Nano Banana → Pexels → theme_solid; cap $10/mes; Doppler only; sanitización multi-capa |
| 5. Testing | ≥40 tests Node built-in; unit + integration mocked + smoke opcional |
| 6. Plan | Task 0 schema + Sprint 1 MVP + POC + Task Backfill + Sprints 2-3 futuros |

**Costo operacional estimado:** ~$2-4/mes en Nano Banana para 30 reels/mes.  
**Tiempo de render por video:** ≤ 60s (browser reuse + paralelismo).  
**Duración del Sprint 1:** 3-5 sesiones de trabajo continuo aplicando regla "por partes".

---

## 11. Abiertas / riesgos conocidos / dependencias externas

### 11.1 Riesgos técnicos
- **Puppeteer headless Chromium** puede tener crashes intermitentes en servidores con poca RAM — mitigación: browser singleton + restart on crash + retry por scene
- **ffmpeg xfade** con filter_complex complejo puede fallar en versiones antiguas — requerimos 6.0+ (tenemos 6.1.1 verificado)
- **Nano Banana puede generar imágenes con textos mal escritos o caras deformadas** — mitigación: NO usar Nano Banana para puntos intermedios (solo hook + CTA), y el re-roll con prompt refinado

### 11.2 Riesgos de producto
- **Reels sin audio** penalizados por algoritmo IG — mitigación: música royalty-free siempre presente, nunca generar MP4 sin audio stream
- **Captions ilegibles en mobile small screens** — mitigación: font-size mínimo 48px, backdrop blur + dark tint garantizados en layout_d
- **Duración efectiva < 7s** (por overlaps de xfade agresivos) — mitigación: `ffmpeg.mjs` valida duración final post-render, warning si <7s

### 11.3 Bloqueos externos
| Bloqueo | Impacto | Mitigación |
|---|---|---|
| PEXELS_API_KEY no registrado aún | bloquea Sprint 1 task 4 | Jorge registra cuenta free en pexels.com/api (5 minutos), copia key a Doppler |
| HeyGen account | bloquea Sprint 3 task 21 | bloqueo explícito Sprint 3+ |
| ElevenLabs account | bloquea Sprint 3 task 22 | bloqueo explícito Sprint 3+ |
| Consent escrito de testimonios | bloquea Sprint 3 task 23 (narrativa D) | bloqueo explícito Sprint 3+ |

### 11.4 Items para revisar antes de empezar
- Confirmar con Jorge que el token `AIRTABLE_SM_TOKEN` actual tiene permiso sobre la tabla `tblAj0Pkj1jW4p5Ld` (debería — lo usa Creativo)
- Confirmar cantidad de records legacy con `Media_Type='reel'` antes de ejecutar backfill (`curl GET` con filter)
- Validar que `agents/director.md` antiguo (v2.0 Blotato) no está siendo invocado por Social Media Agent — si lo está, marcar como deprecated antes de reemplazar

---

## 12. Post-deployment follow-up (Sprint 3+ roadmap)

Items documentados aquí para referencia pero fuera del scope MVP:

1. **A/B testing de narrativas:** instrumentar qué narrativa (A/B/C) genera mejor engagement cuando se publique en IG/FB, guardar métricas en Airtable `engagement_score`
2. **Random theme rotation:** en vez de `theme: "T1"` fijo, el Social Media Agent emite `theme: "random"` y el Director elige entre T1-T5 con pesos configurables
3. **Multi-duration:** soportar 7s (Stories quick) + 10s (Reels standard) + 15s (Reels long) con narrativas adaptadas
4. **Voice-over bilingüe con ElevenLabs:** el Director narra hook y CTA en EN y ES en dos versiones del MP4 (2 Cloudinary URLs, 1 record)
5. **HeyGen avatar integration:** escena 1 o escena final con Jorge hablando (talking head) superpuesto al hero
6. **Replicate Kling video:** para escenas específicas con movimiento (ej. puerta abriéndose, casa transformándose)
7. **Video variant A/B with caption styles:** 2 versiones del mismo video con captions kinetic vs estáticos para testear qué performa mejor
8. **Analytics loop-back:** después de publicar, el Programador lee engagement de FB/IG Graph API y actualiza record con métricas; el Creativo y Director aprenden qué narrativa + theme + mood genera mejor resultado

---

## 13. Historial de decisiones y cambios

| Fecha | Decisión/Cambio | Autor |
|---|---|---|
| 2026-04-24 | Creativo v2 marcado como 100% operativo (42 tests, 5 temas × 3 aspects × 3 slide-types) | Jorge + Claude (Opus 4.7) |
| 2026-04-24 | Brainstorming Director v2 — 7 decisiones aprobadas (B MVP, mezcla B+A+C, preset C, music B, anim B+C, hero C→D, layout D) | Jorge + Claude (Opus 4.7) |
| 2026-04-24 | Spec Director v2 escrito y commiteado (7 partes por regla "por partes") | Claude (Opus 4.7) |
| — | Pendiente: review de Jorge antes de pasar a `writing-plans` | — |

---

## 14. Próximo paso (terminal state del skill `brainstorming`)

Después de que Jorge apruebe este spec:

1. ✅ Spec self-review (placeholders, consistencia, ambigüedad, scope)
2. ⏳ Jorge revisa el spec escrito y da aprobación
3. ⏳ Invocar skill `writing-plans` para crear plan de implementación detallado del Sprint 1, aplicando regla "por partes" (<300 líneas por Write/Edit)
4. ⏳ Invocar skill `executing-plans` para comenzar la ejecución paso a paso del Sprint 1

Ninguna implementación comienza hasta que Jorge apruebe el plan detallado.

— Fin del spec —

