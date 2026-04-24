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

<!-- SECTION_BREAK_AFTER_3 -->
