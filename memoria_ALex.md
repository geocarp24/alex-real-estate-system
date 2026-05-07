# memoria_ALex.md — Memoria Persistente del Sistema ALEX

> Este archivo es leído y actualizado por ALEX al inicio y fin de cada sesión de análisis.
> Formato de fecha: YYYY-MM-DD. Añadir siempre fecha a cada entrada.

---

## 2026-05-07 (PM) — Arsenal completo: 11 templates production-ready

**Jorge confirmación 06:37 UTC**: 5 Reel + 5 Carrusel + 1 Post = 11 templates.

### Reels/Videos — Director v2 (5 templates)
1. **Hybrid Cinematic** (default) — HeyGen hook+CTA + FLUX2 puntos
2. **PiP** (`template:"pip"`) — Circle 360px top-left + FLUX2 fullscreen
3. **Voiceover** (`template:"voiceover"`) — Solo voz + FLUX2 fullscreen
4. **TalkingHead** (`template:"talkinghead"`) — Avatar fullscreen solo
5. **Editorial split-screen 70/30** (`template:"editorial"`) — FLUX2 dominante 70% top + avatar 30% bottom

Karaoke globalmente fucsia `&H009314FF` (#FF1493).

### Carruseles — 5 themes T1-T5 (`agents/creativo_runner/themes.mjs`)
- T1 Dark Premium — bg #0D3B2E, accent gold #C9A84C (DEFAULT Pinnacle)
- T2 White Clean — bg #FFFFFF, editorial cream
- T3 Gold & Black — bg #1A1A1A, premium gold heavy
- T4 Soft Cream — bg #F5F0E8, warm tone
- T5 Vibrant Blue — bg #1B2A8C, accent fucsia + verde

Builders: `slideHook` + `slidePoint` + `slideCTA` + `buildCarousel`. Logo Pinnacle 540px en hook/CTA.

### Posts ESTÁTICOS — 11 templates totales (Jorge confirmó 2026-05-07)

**Bloque A — 5 Solid Pinnacle** (slideHook + slideCTA path, fondo color theme):
1. T1 Solid Dark Premium #0D3B2E (default)
2. T2 Solid White Clean #FFFFFF
3. T3 Solid Gold & Black #1A1A1A
4. T4 Solid Soft Cream #F5F0E8
5. T5 Solid Vibrant Blue #1B2A8C

**Bloque B — 5 Photo Editorial Pexels** (slidePostEditorial + theme dim layer):
6. T1 Photo Editorial — Pexels portrait + dark green gradient dim
7. T2 Photo Editorial — Pexels + white dim (light editorial)
8. T3 Photo Editorial — Pexels + black/gold dim
9. T4 Photo Editorial — Pexels + cream dim (warm)
10. T5 Photo Editorial — Pexels + blue dim

**Bloque C — 1 Conceptual AI** (slidePostEditorial + FLUX-schnell bg):
11. FLUX Conceptual — Replicate AI-gen bg para escenas simbólicas Pexels no captura

**Función central**: `slidePostEditorial(theme, { hookEn, hookEs, ctaEs, bgUrl, photographer, badge })` en `themes.mjs`.

Arquitectura:
- Logo Pinnacle 540px (3x) top-left con drop-shadow
- Hero hookEn 92pt black weight, white, text-shadow
- Accent bar gold #C9A84C 96×4px
- hookEs **fucsia #FF1493** 38pt (Jorge 2026-05-07 — fucsia parte del logo, mejor visibilidad sobre photo)
- Bottom CTA: phrase muted + phone (920) 777-9886 54pt + website
- Photographer credit micro 12pt rgba(.42)

**Modo 1 — Pexels stock (FREE)**: deriveBgQuery() mapea keyword detection → query portrait. Seed deterministic por record.id (idempotente). Fallback evergreen library 8 fotos pre-curadas Wisconsin.

**Modo 2 — FLUX-schnell ($0.003)**: deriveBgQuery() retorna `{ flux: "..." }` para keywords conceptuales/simbólicos. Replicate API directo, aspect 4:5. Actualmente solo `divorce|divorc|separation|separac`.

Activación regla anti-regresión:
- Divorce keyword → FLUX prompt explícito **"ONE WOMAN... ONE MAN... heterosexual married couple"** (Jorge: Pinnacle no promueve homosexualidad, target persona es pareja tradicional WI)
- Divorce priority > testimonio (testimonio sobre divorce rutea a thematic divorce, no for-sale-sign)

### Skills aplicadas (regla 1e)
`impeccable` + `minimalist-ui` + `high-end-visual-design` + `emil-design-eng`. Default Pinnacle aesthetic: editorial limpio + warmth, NO tech-cyberpunk.

### Aprobaciones Jorge (2026-05-07)
- Posts: Testimonio Familia Martínez ✅, Green Bay (preserved) ✅, El Futuro Pinnacle ✅, Pinnacle Misión ✅, Testimonio Pareja Divorcio (FLUX hetero) ✅, Divorcio y Propiedades ✅
- Total Posts producción: 6 (con branding nuevo 2026-05-07)

### Pendientes
- Wire `safety.mjs` en `runner.mjs::processPosts()` antes de cualquier publicación real FB+IG
- Integrar `oraculo_inputs/wi_homeowner_persona.md` + `popup_copy.md` en pipeline Sonnet de creativo
- Revertir `BATCH_MAX_PER_RUN` 10 → 3 cuando complete migración masiva
- Rename Airtable fields legacy: `Blotato_Visual_ID` → `Carousel_URLs`, `Blotato_Post_IDs` → `Published_Post_IDs` (UI manual Jorge)

---

---

## 2026-05-07 — ARSENAL DE TEMPLATES VIDEO (Director v2)

**Template #1 — HYBRID CINEMATIC + KARAOKE** ✅ APROBADO POR JORGE (ES + EN)

| Aspecto | Detalle |
|---|---|
| **Estructura** | 5 escenas: hook (HeyGen) → 3 puntos (FLUX2 cinemático) → cta (HeyGen) |
| **Avatar** | digital_twin Jorge `0a681eef…`, voz clone `ec1256cf…`, V3 engine |
| **Voz** | `speed: 1.1`, sin expressiveness/motion_prompt (digital_twin no acepta) |
| **Música** | Background con sidechain compression — duck dinámico bajo voz |
| **Captions** | ASS karaoke `\kf` word-by-word, primary `&H003BEBFF` (yellow), secondary `&H00FFFFFF` (white), DejaVu Sans 68pt bold |
| **Transiciones** | Todo `crossfade`, `XFADE_OVERLAP=0.6s` |
| **Output** | 1080×1920 H.264 CRF 20 / preset medium / AAC 192k @ 48kHz |
| **Cache** | HeyGen MP4 cacheado en Cloudinary `directorv2/cache/{recordId}_scene_{i}_heygen_{hash}` — re-renders sin costo HeyGen |
| **Costo/Reel** | ~$2 (HeyGen $1 hook+cta + FLUX2 ~$0.10 puntos + compose) |
| **Locale** | `spec.locale='es'` o `'en'` → escoge captionEs/En + voiceId apropiado |
| **Validados** | ES: `v1778114954/…reciqvavtbcbg72wm.mp4` / EN: `v1778115721/…reciqvavtbcbg72wm.mp4` |
| **Trigger** | Airtable `Tipo=Personal` |

**Pipeline mejoras integradas en master tonight (commits c3db1a7 → 13a5e7b):**
1. `XFADE_OVERLAP 0.3 → 0.6` smoother transitions
2. `sidechaincompress` audio ducking (broadcast-grade)
3. `-crf 20 -preset medium` visual quality bump
4. `-b:a 192k -ar 48000` IG Reels audio standard
5. ASS karaoke captions (replaced drawtext)
6. HeyGen MP4 cache via Cloudinary (cost killer)
7. Locale-aware routing (`spec.locale` honors EN/ES per record)
8. `narrative_B` all-crossfade (sin wipeleft/slideup mecánicos)

**Template #2 — CIRCLE AVATAR PIP + SPEECH-SYNCED SLIDES** ✅ APROBADO POR JORGE (ES + EN) — 2026-05-07

| Aspecto | Detalle |
|---|---|
| **Estructura** | 5 escenas FLUX2 cinemáticas full-screen (hook + 3 puntos + cta) — backgrounds cambian, avatar persistente arriba-izquierda |
| **Avatar** | UNA sola call HeyGen V3 con script completo concatenado (`hook . points . cta` — sin "today/hoy"). Cacheado por hash en Cloudinary `directorv2/cache/{recordId}_global_{hash}` |
| **Posición avatar** | Círculo 360px, top-left (x=60, y=140) — libre del IG status bar y de los captions inferiores |
| **Crop circular** | `geq` filter alpha-mask (sin asset externo), borde feathered 4px |
| **Voz** | Audio del avatar global (continuo) → sidechain compressor duckea música |
| **Sync slides ↔ voz** | `ffprobe` mide duración real del avatar, redistribuye scene durations proporcional al char-count de cada segmento. El cambio de fondo cae cuando empieza la frase. |
| **Música** | Background con sidechain ducking (mismo que Template #1) |
| **Captions** | Karaoke ASS por-escena (mismo estilo Template #1, amarillo/blanco) |
| **Trigger** | Airtable `Tipo=Personal` + spec field `template:"pip"` (default sin field = `hybrid` = Template #1) |
| **Costo/Reel** | ~$2.50 (HeyGen 1× full-script ~$1.50 + 5× FLUX2 ~$0.50 + compose). Re-runs con cache HIT = solo compose ~$0.05 |
| **Output** | 1080×1920 H.264 CRF 20 / preset medium / AAC 192k @ 48kHz |
| **Validados** | EN synced: `v1778119937/…reciqvavtbcbg72wm.mp4` (avatar 9.80s, scene durations [3.09, 1.37, 1.37, 1.46, 4.90]). ES top-left: `v1778118796/…` |
| **Spec example** | `{"narrative":"B","aspect":"9:16","template":"pip","locale":"en","hook":{...},"points":[...],"cta":{...}}` |
| **Files** | `agents/director_v2/director_v2.mjs` (PiP routing + global avatar gen + duration sync) · `agents/director_v2/src/ffmpeg.mjs` (`probeMediaDuration`, `XFADE_OVERLAP` exports, circular overlay) |

**Lecciones Template #2 (2026-05-07):**
1. Avatar continuo = 1 sola call HeyGen (no 5) → costo controlado
2. Duración fija por escena causa drift voice/visual — solución = probar duración real con ffprobe + redistribuir por char-count
3. Pausas naturales entre frases con `". "` join — HeyGen respeta puntuación
4. Top-left libre de captions y UI chrome, mejor que bottom-center
5. Cache key incluye script completo → cualquier edición invalida cache (correcto)

**Template #3 — B-ROLL VOICEOVER ONLY** ✅ APROBADO POR JORGE (EN) — 2026-05-07

| Aspecto | Detalle |
|---|---|
| **Estructura** | 5 escenas FLUX2 cinemáticas full-screen — sin avatar visible, solo b-roll |
| **Voz** | Mismo HeyGen V3 global del Template #2 — flag `audioOnly:true` skipea overlay circular |
| **Cache** | Mismo cacheKey que Template #2 (script idéntico) → HIT inmediato, $0 voz |
| **Sync** | Mismo ffprobe + char-count distribution (Template #2 logic) |
| **Trigger** | spec field `template:"voiceover"` + `Tipo=Personal` |
| **Validado** | EN: `v1778121086/…` (cache HIT, 9.80s, scenes [3.09,1.37,1.37,1.46,4.90]) |
| **Costo/Reel** | ~$1 inicial (FLUX2 5×) — re-runs cache HIT ~$0.05 |

**Template #4 — TALKING HEAD SOLO** ✅ APROBADO POR JORGE (EN) — 2026-05-07

| Aspecto | Detalle |
|---|---|
| **Estructura** | Avatar Jorge full-screen toda la duración — sin FLUX2, sin xfades |
| **Pipeline** | Single-scene fullscreen + combined ASS con N karaoke events a offsets calculados (`buildCombinedAssSubtitle`) |
| **Cache** | Mismo cacheKey HeyGen → HIT del Template #2 |
| **Caption** | 1 ASS file con 5 Dialogue events (uno por frase del script), karaoke `\kf` por evento |
| **Trigger** | spec field `template:"talkinghead"` + `Tipo=Personal` |
| **Validado** | EN: `v1778121726/…` (cache HIT, 9.80s, 5 caption events) |
| **Costo/Reel** | $0 voz (cache HIT) + compose ~$0.05 |

**Template #5 — MAGAZINE EDITORIAL (SPLIT-SCREEN 70/30)** ✅ APROBADO POR JORGE (EN) — 2026-05-07

| Aspecto | Detalle |
|---|---|
| **Estructura** | Split horizontal 70/30 — top 1080×1344 = FLUX2 cinemáticos cambiando, bottom 1080×576 = avatar Jorge head/shoulders strip |
| **Avatar shape** | `globalAvatar.shape='split'`, `splitRatio=0.30` (override por defecto disponible) |
| **Composite** | FLUX2 xfade chain a 1080×1920 + overlay avatar `1080×576` en `y=1344` |
| **Captions** | Karaoke standard en bottom band (~y=1660) — encima del torso de Jorge, estilo editorial pull-quote |
| **Trigger** | spec field `template:"editorial"` + `Tipo=Personal` |
| **Validado** | EN: `v1778122816/…reciqvavtbcbg72wm.mp4` (cache HIT, 9.80s, 70/30 + fucsia) |
| **Costo/Reel** | ~$1 inicial (FLUX2 5×) — re-runs cache HIT ~$0.05 |

**🎨 ACTUALIZACIÓN COLOR GLOBAL — KARAOKE FUCSIA (Jorge 2026-05-07)**

| Antes | Ahora |
|---|---|
| PrimaryColour `&H003BEBFF` (yellow #FFEB3B) | PrimaryColour `&H009314FF` (deep pink #FF1493) |

Aplica a **TODOS los templates #1-#5** — `buildAssSubtitle` + `buildCombinedAssSubtitle` comparten la misma Style line. Re-renders futuros heredan automáticamente el color nuevo. SecondaryColour (white) sin cambios.

**🏆 ARSENAL COMPLETO — 5 TEMPLATES VIDEO PRODUCTION READY:**

| # | Nombre | Trigger | Avatar | Backgrounds |
|---|---|---|---|---|
| 1 | Hybrid Cinematic | (default, sin field) | HeyGen full hook+CTA | FLUX2 puntos |
| 2 | Circle PiP | `template:"pip"` | Circle 360px top-left | FLUX2 5× full-screen |
| 3 | B-Roll Voiceover | `template:"voiceover"` | Audio only (sin visual) | FLUX2 5× full-screen |
| 4 | Talking Head Solo | `template:"talkinghead"` | Full-screen avatar | (ninguno) |
| 5 | Magazine Editorial | `template:"editorial"` | Split 30% bottom | FLUX2 70% top |

**Próximo paso (Jorge directiva):** integrar este arsenal con sub-agentes El Director (selección de template por contenido/contexto) y El Programador (publishing FB+IG vía Graph API directo, deprecando Blotato — pendiente token de Jorge).

---

## 2026-05-06 (PM) — Plan B HeyGen Hybrid integrado a Director v2 cron (PRODUCTION READY)

**Tras la sesión de generar Reels Personales standalone, integré Plan B al pipeline automatizado del Director v2:**

1. **`heygen.mjs` v2 (V1 + V3 dual engine support)**:
   - `engine: 'v3'` (Avatar IV/V, ~$4/min, premium quality validado por Jorge "se ve muy bien")
   - `engine: 'v1'` (Avatar III legacy, ~$1/min, 4x cheaper para volumen alto)
   - Endpoint V3: `POST /v3/videos` con `type=avatar, avatar_id, script, voice_id, aspect_ratio, resolution`
   - Endpoint V1: `POST /v1/video.generate` con `clips=[...]`, `dimension={width,height}`
   - Polling shared: `GET /v1/video_status.get?video_id=...`
   - **Defaults importantes**: `expressiveness` y `motion_prompt` SIN default (only-if-set) — HeyGen rechaza estos params en `digital_twin` avatars; solo válidos en `photo_avatar`. Pasar `undefined` los excluye del payload.

2. **`director_v2.mjs` `applyTipoContenidoRouting` mejorada — Hybrid Personal**:
   - Antes: `Tipo=Personal` → todas las scenes vía heygen_avatar (caro y redundante)
   - Ahora: `Tipo=Personal` HYBRID:
     - scenes `layoutType: 'hook'` + `'cta'` → heygen_avatar (Jorge habla)
     - scenes `layoutType: 'point'` → flux2 (cinematic Pinnacle imagery)
     - Costo total ~$2 por Reel Personal de 13s (vs $5+ all-HeyGen)
   - Auto-asigna `scene.heyScript = scene.captionEs || scene.captionEn` para scenes hook/cta
   - HeyGen failure fallback: ahora cae a `flux2` (que usa heroPrompt) en vez de `pexels` (que necesita heroQuery, no seteado en hook/cta scenes)

3. **Avatar Pinnacle de Jorge configurado** (sin matting por ahora — pendiente re-train con green screen):
   - `HEYGEN_AVATAR_ID_JORGE = 0a681eef6a5a4e7680fec9d45b770fc1` (digital_twin "Jorge", look natural)
   - Otros looks disponibles: `08e7281db244473382cab2275ee77b80` (photo_avatar "The Real Estate Professional", lip-sync inferior)
   - `HEYGEN_VOICE_ID_JORGE_EN/ES = ec1256cf8c204211b337137d27577f70` (voice clone real validado)

4. **Reels generados y APROBADOS por Jorge:**
   - Reel A (digital_twin natural, fondo dark, 13s): $0.87 — "Se ve muy bien"
   - Reel D (digital_twin + Pinnacle FLUX2 office bg, 15.3s): $1.00 — fondo no se aplicó (avatar sin matting)
   - Detectada limitación: digital_twins sin matting NO permiten background swap. Solución pendiente = re-grabar training video con green screen.

5. **Bridges Hostinger nuevos esta sesión:**
   - `airtable_proxy.php` ya existía, validado funcional para PATCH/POST/GET en Social Media base
   - `github_query.php` ya existía con allowlist /artifacts + /logs + follow_redirects

6. **PRODUCTION READY ✅ — sistema autónomo activo:**
   - Cron `30 21 */3 * *` activo (cada 3 días dispara Director v2 batch)
   - Records con `Tipo=Personal` → hybrid HeyGen + FLUX2 automático
   - Records con `Tipo=Educativo|Tip|Caso|Brand` → all FLUX2 cinematic
   - Records sin Tipo → default Director v2 (Pexels + nano_banana fallback)

**HeyGen wallet status:** $6.60 remaining = ~6-8 Reels Personales más antes de recargar.

**Pendiente Jorge (no bloqueante):**
- Grabar nuevo training video con green screen / pared blanca para tener digital_twin con matting → desbloquea backgrounds custom Pinnacle en Reels Personales
- Avatar matting upgrade explicado en heygen-avatar/SKILL.md Phase 5

**VALIDACIÓN END-TO-END (Run #151, GHA id 25466704475):**
- Status: `completed/success` en 5m 9s
- Record `reciQVAvTbcBg72wm` → `Status=Visual Listo`, duration=12.88s, cost=0¢, error=empty
- MP4 generado: `https://res.cloudinary.com/dzzlhhk0m/video/upload/v1778110168/pinnacle-social-media/videos/directorv2/reciqvavtbcbg72wm.mp4`
- HeyGen V3 hook+CTA + FLUX2 puntos compuestos sin error de `motion_prompt` ni crash de fallback
- 1 record procesado, 0 errores, 1 Cloudinary upload (2.7 MB)
- **PIPELINE 100% PRODUCTION READY ✅**

---

## 2026-05-06 — Plan A + Plan B VALIDADOS END-TO-END (HeyGen funcionando)

**Hitos del día:**

1. **Plan A faceless premium production-ready** — 3 Reels generados y aprobados por Jorge:
   - Reel #1: T1 Dark + upbeat, 9.8s ($0.08)
   - Reel #2: T3 Gold + cinematic, 9.8s ($0.00 — flux2 bug fix funcionó)
   - Reel #3: T3 Gold + cinematic 15s con FLUX2 cinematográfico premium en las 5 escenas, custom prompts por escena, $0.00

2. **Bug fixes Director v2:**
   - Field name: `Tipo_Contenido` → `Tipo` (real Airtable schema)
   - flux2 wrapper: deriva prompt cuando `heroPrompt:null` para narrative B point scenes
   - narrative_B.mjs: escala duraciones según `spec.duration` (proporcional)
   - narrative_B.mjs: acepta `spec.prompts.{hook,cta}` overrides custom

3. **Bridges Hostinger nuevos:**
   - `airtable_proxy.php` — proxy autenticado GET/POST/PATCH a Airtable con allowlist por base
   - `github_query.php` — proxy autenticado de lectura GitHub API (runs/jobs/logs/artifacts)
   - Allowlist `deploy-modal.yml` agregada a `github_dispatch.php`

4. **Modal endpoints production:**
   - 7/8 endpoints deployed exitosos: qwen3-tts, flux2, image-edit, ltx2, sadtalker, music-gen, upscale
   - 8 GHA secrets cableados via `github_secret.php` bridge (libsodium-wrappers PyNaCl en sandbox)
   - workflow `deploy-modal.yml` rewritten a matrix strategy (8 jobs paralelos × 60min budget)

5. **Plan B HeyGen completo:**
   - Skills oficiales clonadas en `~/.claude/skills/heygen-skills`
   - HeyGen CLI v0.0.7 instalado en `~/.local/bin/heygen` (patched installer para sandbox SSL)
   - 4 GHA secrets: `HEYGEN_API_KEY`, `HEYGEN_AVATAR_ID_JORGE` (digital_twin look `0a681eef...`), `HEYGEN_VOICE_ID_JORGE_EN/ES` (`ec1256cf...` voice clone real)
   - Wallet API cargado $10
   - **Smoke test pass**: 2s avatar, $0.13
   - **Primer Reel Personal Pinnacle**: 13s, English, dark Pinnacle bg, $0.87 — Jorge aprobó "Se ve muy bien"

6. **Workflow agents-cron.yml fixes:**
   - Removidos 3 env override que sobreescribían valores de Doppler con secrets vacíos
   - Steps install separados (ffmpeg / npm-ci / puppeteer / video_toolkit) con timeouts individuales
   - timeout-minutes 30→60

**Métricas reales validadas:**
- Reel Faceless premium (Plan A): $0-3 por video, 2 min wall time
- Reel Personal HeyGen (Plan B): $0.87 por 13s, ~5s generación HeyGen
- Director v2 batch wall time: ~10 min (install + run)

**Estado producción:**
- ✅ Cron `30 21 */3 * *` activo — Director v2 batch cada 3 días
- ✅ Records viejos `recLgqG8...` y `recpevy...` archivados con Error_Reason (excluidos del batch filter)
- ✅ 3 records test exitosos en Airtable Social Media base
- ✅ Master branch contiene todo el stack — `dce655d` feat(director_v2) + posteriores

**Pendientes:**
- Integrar Director v2 + HeyGen via Tipo=Personal routing en records reales (código wired, falta record con script + dispatch)
- Recargar wallet HeyGen cuando se agote (~$9 actual = ~10 Reels Personales más)
- Limpiar tokens expuestos en chat (rotación HeyGen API key + Modal tokens cuando convenga)

---

## REGLAS DEL JEFE (aplican a TODOS los agentes, siempre)

### 2026-04-22 — PROTOCOLO DE EJECUCIÓN (NO NEGOCIABLE — APROBADO POR JORGE)
- **Documento:** `agents/PROTOCOLO_EJECUCION.md` — leer al inicio de cada sesión junto con esta memoria.
- **Aplica a:** toda operación no trivial (WP, Airtable, VPS, Hostinger, integraciones, scripts de agentes).
- **7 fases obligatorias:**
  1. Carga de contexto (memoria + shared_conversation + protocolo + credenciales)
  2. Diagnóstico antes de acción (leer estado actual, nunca suponer)
  3. Backup obligatorio antes de cambios destructivos (commit + push a `backups/`)
  4. División de tareas grandes (< 300 líneas por archivo, Write al disco, nunca inline grande)
  5. Deploy seguro (test local → draft/staging → preview al Jefe → publish → purge cache)
  6. Verificación post-deploy (HTTP 200 + contenido esperado + flujo E2E + logs limpios)
  7. Auto-backup + checkpoint cada 15 min en memoria
- **Errores ya costaron créditos, no repetir** (stream timeout, WAF 403, credenciales perdidas, home rota, etc.) — lista completa en `PROTOCOLO_EJECUCION.md`.
- **Checklist obligatorio** antes de cada tarea — si falta algo, no arrancar.
- **Al iniciar sesión, confirmar:** "Protocolo cargado. Listo para operar según Fases 1–7."

### 2026-04-16 — Comunicación
- Respuestas cortas y simples. Evitar lenguaje técnico innecesario.
- No pedir confirmación repetida. Si el Jefe dice "procede", procede.
- No hacer trabajo extra que no se pidió. Ser proactivo solo cuando agrega valor real.
- Menos explicación, más acción.

### 2026-04-16 — Protección de Alexbot
- Antes de hacer merge a master o cualquier cambio que toque archivos compartidos (agents/, memoria_ALex.md, workflows), verificar si puede afectar a Alexbot.
- Si un cambio afecta tanto el trabajo actual como Alexbot, trabajar en ambos simultáneamente — nunca dejar al bot roto mientras se arregla otra cosa.
- El bot es producción 24/7. Su estabilidad es prioridad igual o mayor que el trabajo en curso.

### 2026-04-16 — Principios fundamentales
- SER HONESTO Y PROACTIVO. Siempre. Sin excepción.
- Si algo no funciona o es mala idea, decirlo directo. No endulzar.
- Proponer mejoras activamente sin esperar a que el Jefe pregunte.
- LEMA DEL SISTEMA: Profesional, Automatizado, Inteligente y Eficaz.
- Antes de hacer push: análisis profundo de TODOS los flujos, encontrar TODOS los gaps, resolverlos TODOS.

### 2026-04-17 — Capacidades y autonomía
- Hostinger: acceso SSH vía GitHub Actions. Puedo ejecutar comandos remotos, configurar crons, hacer deploys. NO pedirle al Jefe cosas que puedo hacer yo.
- Make.com: acceso API (token: 856a1ce2-...). Puedo listar/modificar escenarios.
- Airtable: acceso API completo. Puedo crear tablas, campos, registros.
- Quo/OpenPhone: API key para enviar SMS.
- Telegram: bot token para alertas (@Ferpinnaclebot).
- REGLA: si algo se puede automatizar o ejecutar directo, HACERLO.

### 2026-04-17 — Protocolo de cambios en scripts y prompts
- NUNCA modificar scripts de agentes (prompts, diálogos, objection handling) sin aprobación del Jefe.
- Entrar en MODO PLANEACIÓN primero: presentar cambios, explicar qué y por qué, esperar aprobación.
- NO tocar código que no necesite cambio. Solo lo estrictamente necesario.
- Aplica a: fer_claude.php, system prompts, objection scripts, mensajes al cliente.
- NO aplica a: bugs técnicos, parsing, logging, infraestructura — esos se arreglan directo.

---

## FER AI RECEPTIONIST — Proyecto completo (2026-04-17/18)

**Estado: EN PRODUCCIÓN**

**Arquitectura:** Quo webhook → fer_agent.php → Claude Haiku/Sonnet → SMS + Telegram + Airtable

**Archivos PHP en Hostinger (hostinger/tools/):**
- `fer_agent.php` — orquestador principal, webhook receiver
- `fer_first_contact.php` — cron 15min, rotación Phone1-4, 4 mensajes únicos bilingües
- `fer_seguimiento.php` — cron diario 9:30AM, 24 toques SMS+Email, 12 meses
- `fer_stale_cron.php` — cron diario 8AM, "Contacted" sin respuesta 5d → "Seguimiento"
- `fer_morning_brief.php` — cron diario 8:30AM, resumen pipeline + health check → Telegram
- `fer_diag.php` — diagnóstico + reset (requiere token=pinnacle2026)
- `lib/fer_claude.php` — prompt + Claude API + auto-escalation Haiku→Sonnet
- `lib/fer_conversations.php` — historial local por teléfono
- `lib/fer_airtable.php` — Contacts + Fer Conversations + Deals
- `lib/fer_quo.php` — SMS via Quo/OpenPhone
- `lib/fer_telegram.php` — alertas a Jorge con Fer Score + datos completos
- `lib/fer_logger.php` — logs JSONL
- `lib/fer_deduplication.php` — dedup por messageId

**Tablas Airtable:**
- Leads: `tblxZz2EWIglOLnEd` — lista cruda de leads
- Contacts: `tblacvw0Ss770x8l5` — CRM principal con campos de calificación
- Deals: `tbliaEKxBHKBx7ZK2` — oportunidades reales, auto-creadas por Fer
- Fer Conversations: `tbleausFNpHhqLfsm` — transcripciones completas (QC)
- Tracy: `tbl6CJm4kYspOuTDB` — resultados skip trace
- Notes & Activity: `tbleOBXJl7sDhwj5w` — historial

**Flujo completo:**
1. Jefe marca Lead "Review this Deal" → el_polling (5min) → Tracy skip trace → Contact
2. fer_first_contact (15min, 9am-7pm) → SMS Phone1 → 24h → Phone2 → Phone3 → Phone4
3. DNC: solo Phone1 empático, luego Seguimiento
4. Cliente responde → Fer califica: owner, dirección, situación, timeline, amount owed, asking/lowest price, realtor math, win-win (3 strikes), vacant, repairs, decision makers, preferred contact, best time, email
5. Escalation → Telegram con Fer Score + todos los datos → Deal auto-creado con datos de Lead + Fer
6. Sin respuesta 5d → "Seguimiento" → Engine 24 toques → Step≥24 → Dead
7. Cliente responde a follow-up → Fer retoma sin repetir → Stage "Negotiation"

**Prompt reglas clave:** empathy first, no promesas falsas (sin tiempos específicos), price discovery (preguntar no negociar), 3 strikes en precio, bilingüe auto

**Crons Hostinger:**
- */15 * * * * → fer_first_contact.php
- 0 14 * * * → fer_stale_cron.php
- 30 15 * * * → fer_seguimiento.php
- 30 14 * * * → fer_morning_brief.php

**Make desactivados:** 4725930, 4738270, 4723767, 4656571, 4656574
**Make activos (no críticos):** 4541469, 4501430, 4636455, 4408392

**Secrets GitHub (Hostinger):** AIRTABLE_TOKEN, TRACERFY_TOKEN, ANTHROPIC_API_KEY, MAKE_API_TOKEN, QUO_API_KEY, FER_TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID

**Para resetear conversación:** `fer_agent.php?token=pinnacle2026&reset=all` (o número específico)

**Pipeline al cierre 2026-04-18:** 6 TBC, 2 Contacted, 16 Seguimiento, 0 Deals, todos los sistemas verdes

---

## 📋 DEAL ANALYSIS LOG

> Registro de deals analizados. Incluye estimación inicial, resultado real (si disponible), diferencias y lecciones aprendidas.

*(Sin entradas aún — se irán añadiendo conforme se analicen deals)*

---

## 🗺️ ZIP CODE PERFORMANCE NOTES

> Notas sobre rendimiento histórico por zip code: retornos observados, días en mercado, tendencias.

*(Sin entradas aún)*

---

## 🔨 CONTRACTOR / VENDOR NOTES

> Contratistas confiables, precios observados en rehab, proveedores recomendados o descartados.

*(Sin entradas aún)*

---

## 🚩 MARKET RISK FLAGS

> Zonas identificadas como de alto riesgo, mercados sobrevaluados, exceso de inventario, crimen alto, baja liquidez.

*(Sin entradas aún)*

---

*Última actualización: 2026-04-06*

---

## 🔧 BITÁCORA DE CAMBIOS AL SISTEMA

### 2026-04-05 — Sesión Claude Code

#### 1. Bot Telegram — API key arreglada
- **Problema:** Bot fallaba con error 401 — API key de Anthropic hardcodeada y expirada en `alex_bot.py`
- **Solución:** Bot ahora lee todas las credenciales desde `.env` via `python-dotenv`
- **Instalado:** `python-dotenv` en `/opt/alex-bot/venv/`
- **Estado:** ✅ Bot activo y respondiendo

#### 2. Bridge Hostinger → GitHub reparado y conectado
- **Problema:** `github_bridge.php` devolvía 500 — LiteSpeed no carga `SetEnv` del `.htaccess`
- **Solución:** Reestructuré `hostinger/` en GitHub:
  - `hostinger/tools/` → `el_polling.php`, `el_chismoso.php`
  - `hostinger/agents/` → `github_bridge.php`, `github_write.php`
- **GitHub Actions workflow** actualizado para deployar ambas carpetas + escribir `alex_config.php` vía SSH
- **Secrets agregados al repo:** `GH_PAT`, `ALEX_SECRET`
- **Estado:** ✅ Bridge respondiendo HTTP 200

#### 3. Bot Telegram conectado a memoria compartida (bridge)
- **Nuevo comportamiento:**
  - `read_memoria` → intenta bridge (GitHub) primero → fallback a archivo local
  - `write_memoria` → escribe local Y empuja a GitHub via bridge
  - `read/write_telegram_memory` → mismo comportamiento dual
  - `append_memoria_alex` → igual, dual write
- **Resultado:** Telegram Bot + Claude Code + Claude.ai comparten la misma memoria en tiempo real
- **Variables agregadas al `.env`:** `BRIDGE_URL`, `ALEX_SECRET`

#### 4. Social Media Agent integrado como sub-agente de ALEX
- **Archivos creados:**
  - `agents/social_media.md` — system prompt completo del agente
  - `agents/memoria_social_media.md` — memoria y estado de sistemas SM
- **Tool `invoke_social_media` agregado al bot:**
  - Parámetros: `task`, `platform` (FB/IG/Ambas/LinkedIn), `format_type` (Post/Reel/Carrusel/Story), `save_to_airtable`, `week_number`
  - Airtable SM base: `appU9s3kGkVpdrJkw` (separada del CRM de real estate)
  - Make.com webhook autorizado: `hook.us2.make.com/zbvy7391...`
- **Protocolo de seguridad actualizado** a v1.1 — Social Media Agent en cadena de autoridad
- **Fuente de datos:** repo `geocarp24/pinnacle-agent-memory` → `PINNACLE_SOCIAL_MEDIA_AGENT.md`

---

### 2026-04-06 — Regla Tracerfy + Limpieza Automática

#### 7. El Secretario — Regla Tracerfy agregada
- **Regla:** Emails cuyo FROM contenga "tracerfy" se archivan silenciosamente — sin Telegram, sin Airtable, sin Claude
- **Flujo IMAP:** COPY a carpeta "Archive" → `\\Deleted` en INBOX → EXPUNGE
- **Si carpeta Archive no existe:** se crea automáticamente en el servidor Hostinger
- **SQLite:** campos `is_tracerfy=1` y `archived_date=YYYY-MM-DD` registran el archivado
- **Limpieza automática:** al inicio de cada ciclo (cada 5 min), busca Tracerfy con `archived_date <= hoy-30días` → elimina permanentemente de IMAP y SQLite
- **Función de detección:** `es_tracerfy(remitente)` — case-insensitive
- **Estado:** ✅ Activo en producción (servicio systemd existente)

---

### 2026-04-06 — El Secretario y El Planificador

#### 5. El Secretario — Monitor de Email (deals@pinnaclegroupwi.com)
- **Script:** `secretario/email_monitor.py` — IMAP + Claude clasificación + Airtable + Telegram
- **Servicio systemd:** `secretario-email.service` ✅ Activo (pid 1181386)
- **IMAP:** ✅ Conectado a imap.hostinger.com:993 — 166 emails, 90 no leídos en primer ciclo
- **Credenciales en `.env`:** `SECRETARIO_EMAIL`, `SECRETARIO_PASSWORD`, `IMAP_HOST`, `IMAP_PORT`, `SMTP_HOST`, `SMTP_PORT`
- **DB local:** `secretario/emails.db` — SQLite para tracking de emails procesados
- **Comandos Telegram:** `/emails`, `/responder <ID>`, `/responder <ID> mensaje`
- **Clasificación:** LEAD → Airtable + notificación | URGENTE → notificación | RUTINARIO → resumen | SPAM → ignorar

#### 6. El Planificador — Google Calendar
- **Script:** `secretario/calendar_manager.py`
- **Cron alexuser:** resumen matutino 8am CST (14:00 UTC) + recordatorios cada 15min
- **Google libs:** ✅ Instaladas en venv (`google-auth`, `google-auth-oauthlib`, `google-api-python-client`)
- **Comandos Telegram:** `/agenda`, `/agenda semana`, `/cita <fecha> <hora> <nombre> <motivo>`
- **Estado OAuth — 2026-04-06:** ✅ credentials.json instalado en `secretario/google_creds/credentials.json`
  - Client ID: `26650922402-186bhh0gb01uho45va1boita1rkulpil.apps.googleusercontent.com`
  - Proyecto Google Cloud: `pinnacle-alex`
  - `.env` actualizado con `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_CREDS_PATH`, `GOOGLE_TOKEN_PATH`
  - Script de autorización: `secretario/auth_google.py`
  - **PENDIENTE:** Jorge ejecutar `python3 secretario/auth_google.py` desde SSH y pegar el código de autorización

---

## 📊 ESTADO ACTUAL DEL SISTEMA — 2026-04-05

| Componente | Estado | Notas |
|-----------|--------|-------|
| Bot Telegram | ✅ Activo | VPS `187.77.215.146`, systemd service |
| API Anthropic | ✅ OK | Lee de `.env`, modelo `claude-sonnet-4-6` |
| Bridge Hostinger | ✅ HTTP 200 | `agents.pinnaclegroupwi.com` |
| Memoria compartida | ✅ Activa | VPS ↔ GitHub ↔ Claude.ai |
| GitHub Actions deploy | ✅ Automático | Push a `master` → deploy a Hostinger |
| Sub-agente El Scout | ✅ Listo | `agents/scout.md` |
| Sub-agente El Matemático | ✅ Listo | `agents/matematico.md` |
| Sub-agente El Fact-Checker | ✅ Listo | `agents/fact-checker.md` |
| Sub-agente Tracy | ✅ Listo | `agents/tracy.md` + Tracerfy API |
| Sub-agente Social Media | ✅ Listo | `agents/social_media.md` — NUEVO hoy |
| El Secretario (Email) | ✅ Activo | `secretario/email_monitor.py` — servicio systemd |
| El Planificador (Calendar) | ⚠️ 95% | `secretario/calendar_manager.py` — credentials.json instalado, falta autorización OAuth |
| Airtable CRM (Real Estate) | ✅ Activo | Base `appfQbDA750Oihy9J` — tablas vacías |
| Airtable Social Media | ✅ Activo | Base `appU9s3kGkVpdrJkw` — 12 ideas pendientes |
| el_polling.php | ✅ Activo | Cron cada 5min en Hostinger |
| el_chismoso.php | ✅ Activo | Webhook Tracy→Contacts |
| Make.com escenario SM | ✅ Activo | ID 4636455 — activado 2026-04-05 |

---


#### 6. Test E2E Social Media — 2026-04-05
- **Flujo probado:** ALEX → Webhook Make.com → Escenario 4636455 → Airtable `appU9s3kGkVpdrJkw`
- **Webhook:** HTTP 200 Accepted ✅
- **Make.com:** Aceptó el payload ✅
- **Record creado en Airtable:** ❌ NO — confirmado en sesión siguiente (solo existe `recdF2uT42ay04k69`)
- **Contenido enviado:** "S2 - Foreclosure: Tienes Opciones" | Formato=Post | Semana=2
- **Conclusión:** Webhook funciona, pero escenario Make 4636455 no escribió en Airtable. Posibles causas: mapeo incorrecto en Make, conexión OAuth caducada nuevamente, o error en módulo Airtable del escenario.
- **Acción pendiente:** Revisar Make.com UI → escenario 4636455 → historial de ejecuciones → ver error exacto del módulo Airtable

#### 5. Flujo Social Media auditado y parcialmente reparado (2026-04-05)
- **Webhook Make.com**: ✅ HTTP 200 confirmado — `hook.us2.make.com/zbvy7391qh9n7dlmw1hy8pq9ym69obxk`
- **Airtable escritura**: ✅ Confirmada — record test `recCM80pqccFhVLr2` creado correctamente
- **Campos renombrados**: ` Hashtags` y ` Status` tenían espacio inicial — corregidos via Metadata API
- **Schema real documentado**: nombres con emojis (`🇺🇸 Caption EN`, `🇲🇽 Caption ES`), `Semana` sin `#`, ID Scripts de Video correcto (`tbli9BsyIwrhwa3aS`)
- **Bot `alex_bot.py`**: field mapping actualizado con nombres reales
- **Pendiente manual (Jorge)**: fix `Formato` y `Tipo` choices en Airtable UI + activar escenario Make 4636455

---

## 🔴 PENDIENTES PRIORITARIOS (próxima sesión)

1. **Subir 12 ideas SM a Airtable** — Jorge le pidió a ALEX via Telegram que las genere y guarde (en proceso)
2. **Geo Carpentry Budget Builder** — deploy pendiente, base de precios WI
3. **Website Geo Carpentry Fase 1** — migración a Durable, pendiente

## ✅ ESTADO ACTUALIZADO — 2026-04-05

- **Make.com escenario 4636455** — ✅ Funcionando correctamente, módulos mapeados bien
- **Airtable RE (Leads)** — ✅ Hay leads activos siendo trabajados por Jorge
- **ALEX escribe directo en Airtable SM** — ✅ Tools `airtable_sm_list/create/update` operativas sin depender de Make


## 2026 — Credenciales del Sistema (CONFIDENCIAL)

### GitHub Token (Full Access)
- Variable: GITHUB_TOKEN
- Guardado en .env del sistema
- Nota: NO imprimir en outputs

### Blotato API Key
- Variable: BLOTATO_API_KEY  
- Key: blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k=
- Nota: NO imprimir en outputs

### Estado:
- ✅ BLOTATO_API_KEY agregada al .env del VPS — 2026-04-06
- ✅ Cuentas verificadas via MCP: FB Account 25638 (Pinnacle Holdings Group), IG Account 39285 (@pinnacle.groupwi)
- ✅ Bot reiniciado con nueva API key cargada



## 2026-04-06 — TAREA PARA CLAUDE CODE (Puente ALEX→Claude Code activo)

### TAREA #1 — BLOTATO CONFIGURACIÓN COMPLETA
**Prioridad:** 🔴 MÁXIMA
**Enviada por:** ALEX Bot via Telegram

**Instrucciones:**
1. Agrega `BLOTATO_API_KEY=blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k=` al archivo `.env` del VPS (`/opt/alex-bot/.env`)
2. Verifica que la API Key funciona haciendo un GET a `https://my.blotato.com/api/v1/accounts` con header `api-key: blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k=`
3. Lista las cuentas conectadas (FB e IG de Pinnacle Holdings)
4. Documenta los Account IDs de FB e IG en `claude_code_status.md` en GitHub repo `geocarp24/pinnacle-agent-memory`
5. Confirma resultado escribiendo en `memoria_ALex.md` con status ✅ o ❌

**Criterio de éxito:** API Key en .env + Account IDs documentados + confirmación en memoria
**Status:** ⏳ PENDIENTE



## 2026-04-06 — TAREA URGENTE PARA CLAUDE CODE — APROBADA POR JORGE

### TAREA #1 — BLOTATO API KEY + VERIFICACIÓN
**Prioridad:** 🔴 MÁXIMA — Aprobada por Jorge
**Status:** ✅ COMPLETADO — 2026-04-06

**Instrucciones exactas:**
1. Abre el archivo `/opt/alex-bot/.env` en el VPS
2. Agrega esta línea:
   ```
   BLOTATO_API_KEY=blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k=
   ```
3. Guarda el archivo
4. Verifica que la API Key funciona:
   ```bash
   curl -s https://my.blotato.com/api/v1/accounts \
     -H "api-key: blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k="
   ```
5. Lista las cuentas conectadas (FB e IG de Pinnacle Holdings)
6. Documenta los Account IDs en `claude_code_status.md` en GitHub repo `geocarp24/pinnacle-agent-memory`
7. Reinicia el servicio del bot:
   ```bash
   sudo systemctl restart alex-bot
   ```
8. Confirma resultado escribiendo en `memoria_ALex.md`:
   - ✅ API Key agregada
   - ✅ Cuentas encontradas (IDs)
   - ✅ Bot reiniciado

**Criterio de éxito:** API Key en .env + Account IDs documentados + bot reiniciado
**Aprobado por:** Jorge Cruz — 2026-04-06

**Resultado de ejecución:**
- ✅ BLOTATO_API_KEY agregada a `/opt/alex-bot/.env`
- ✅ Cuentas verificadas via MCP Blotato:
  - FB: Account ID `25638` | Page: Pinnacle Holdings Group (`965320503341457`) — ACTIVA
  - FB: Page Geocroficial (`877737568755522`) — reservada
  - FB: Page Geo Carpentry (`723873447473999`) — reservada
  - IG: Account ID `39285` | @pinnacle.groupwi — ACTIVA
- ✅ Bot reiniciado con nueva configuración
- ✅ Monitor conectado a memoria_ALex.md — detecta bloques PENDIENTE EJECUCIÓN automáticamente

---

## ✅ Monitor automático 100% funcional — 2026-04-06

**Sistema completo operativo:**
- GitHub Monitor corre cada 30s — lee task_queue.json Y escanea memoria_ALex.md
- Tareas escritas en memoria_ALex.md con `**Status:** ⚙️ EN PROCESO` se migran automáticamente a task_queue.json
- Claude Code las ejecuta y devuelve resultado a Telegram sin intervención de Jorge
- Blotato configurado: FB (Pinnacle) + IG (@pinnacle.groupwi) listos para publicar

---

## ✅ Blotato MCP — Configuración y Herramientas Disponibles — 2026-04-06

**Método de conexión:** SSE (Server-Sent Events)
**URL:** `https://mcp.blotato.com/mcp`
**Config:** `~/.claude/settings.json` → `mcpServers.blotato`
**Usuario verificado:** ID `fc1219bc` — suscripción activa

### Cuentas conectadas (Pinnacle)
| Red | Account ID | Identificador | Estado |
|-----|-----------|--------------|--------|
| Facebook | `25638` | Pinnacle Holdings Group (Page `965320503341457`) | ✅ ACTIVA — usar por defecto |
| Facebook | reservada | Geocroficial (`877737568755522`) | Para sesiones futuras |
| Facebook | reservada | Geo Carpentry (`723873447473999`) | Para sesiones futuras |
| Instagram | `39285` | @pinnacle.groupwi | ✅ ACTIVA |

**Regla:** Para publicaciones de real estate → siempre usar FB Account `25638` (Pinnacle Holdings Group) + IG `39285`.

### 14 Herramientas MCP Disponibles
| Tool | Descripción |
|------|-------------|
| `blotato_get_user` | Info del usuario/suscripción activa |
| `blotato_list_accounts` | Lista cuentas FB/IG conectadas con IDs |
| `blotato_create_post` | Crea y publica post en FB/IG con texto, imagen, scheduling |
| `blotato_create_presigned_upload_url` | URL para subir imagen/video a Blotato CDN |
| `blotato_create_source` | Sube archivo multimedia (imagen/video) desde URL |
| `blotato_create_visual` | Genera visual desde template (37 templates disponibles) |
| `blotato_delete_schedule` | Elimina un post programado |
| `blotato_get_post_status` | Estado de publicación (published, failed, pending) |
| `blotato_get_schedule` | Detalles de un post programado |
| `blotato_get_source_status` | Estado de upload de un archivo multimedia |
| `blotato_get_visual_status` | Estado de generación de un visual |
| `blotato_list_schedules` | Lista posts programados |
| `blotato_list_visual_templates` | Lista 37 templates de visuales disponibles |
| `blotato_update_schedule` | Modifica un post programado (texto, fecha, cuentas) |

### Flujo para publicar un post
1. `blotato_create_source` — subir imagen (si hay) desde URL
2. `blotato_create_post` — crear post con `account_ids: [25638, 39285]`, texto EN/ES, imagen opcional
3. `blotato_get_post_status` — verificar resultado

### Visual Templates (37 disponibles)
- ALEX puede generar visuales de marca usando `blotato_create_visual` + template ID
- Listar templates actualizados: `blotato_list_visual_templates`

---

## ✅ Posts Programados en FB + IG — 2026-04-05 / 2026-04-05 (actualizado)

**6 Posts de formato `Post` programados en Pinnacle Holdings Group FB Page (`965320503341457`)**
**Plataforma:** Solo Facebook (IG pendiente — requiere imagen)
**Herramienta:** Blotato MCP — todos status "scheduled"

| Fecha | Título | Blotato ID | Airtable ID |
|-------|--------|-----------|------------|
| Lun 6 Abr 12pm CDT | S1 - ¿Quién es Jorge Cruz? | `4e924cba` | `recdF2uT42ay04k69` |
| Mié 8 Abr 12pm CDT | S1 - ¿Cuánto vale tu casa? | `314e7e95` | `recMwpr2pmMPZmRmf` |
| Vie 10 Abr 12pm CDT | S1 - Foreclosure en Wisconsin | `7fd1a454` | `recnxz2muTo5woVol` |
| Lun 13 Abr 12pm CDT | S2 - Testimonio Familia Martínez | `a609d373` | `recMuIrouAvcSD3O5` |
| Lun 20 Abr 12pm CDT | S3 - ¿Qué pasa con tu herencia? | `d2f8d770` | `recBoDVfwyQ72h2DS` |
| Lun 27 Abr 12pm CDT | S4 - ¿Qué es un Short Sale? | `1a20aa31` | `recvNs3tIzbDtfl8e` |

**✅ COMPLETADO 2026-04-05 — Todos los 10 posts pendientes programados con fotos de GitHub:**

| Fecha | Título | FB ID | IG ID | Fotos |
|-------|--------|-------|-------|-------|
| Lun 6 Abr 12pm CDT | S1 - ¿Quién es Jorge Cruz? | `4e924cba` (prev) | `ba500c68` | IMG_2706 |
| Mié 8 Abr 12pm CDT | S1 - ¿Cuánto vale tu casa? | `314e7e95` (prev) | `e0551fe0` | IMG_1988 |
| Mié 15 Abr 12pm CDT | S2 - 5 Razones efectivo | `a99c3170` | `f348c871` | IMG_2091+2092+2113 |
| Vie 17 Abr 12pm CDT | S2 - ¿Qué es el equity? | `ed2a5080` | `9b75f4cc` | IMG_2719+2723 |
| Mié 22 Abr 12pm CDT | S3 - Behind the Scenes | `336fbf47` | `4124dd62` | IMG_2724 |
| Vie 24 Abr 12pm CDT | S3 - Realtor vs Cash Buyer | `b250f747` | `566c539b` | IMG_2726+98EC09BA |
| Lun 27 Abr 12pm CDT | S4 - ¿Qué es un Short Sale? | `1a20aa31` (prev) | `98e63e30` | IMG_2090 |
| Mié 29 Abr 12pm CDT | S4 - Proceso paso a paso | `cbc1b3a0` | `93c40c52` | IMG_2706+IMG_1988 |
| Vie 1 May 12pm CDT | S4 - Mitos cash buyers | `e34cef1c` | `cf9553f5` | IMG_2090+2091 |
| Lun 4 May 12pm CDT | S4 - Jorge habla: Por qué fundé Pinnacle | `09bed09f` | `6d13bca6` | IMG_2723 |

**Estado final: CERO posts pendientes — calendario completo Abr-May 2026**

---
### 2026-04-05 17:19 — Tarea ejecutada por GitHub Monitor
**Tarea:** Responde EXACTAMENTE esto: MONITOR GITHUB ACTIVO - Sistema de monitoreo 24/7 funcionando. Detecté esta tarea desde task_queue.json en GitHub.
**Resultado:** MONITOR GITHUB ACTIVO - Sistema de monitoreo 24/7 funcionando. Detecté esta tarea desde task_queue.json en GitHub.



## 2026-04-06 — Pinnacle Call Assistant — Sesión de trabajo

### Reglas de trabajo — OBLIGATORIAS (aprobadas por Jorge)
1. **Siempre subir a Hostinger Y a GitHub** cuando se modifica un archivo de Tools
2. **Siempre actualizar memoria_ALex.md** al final de cada sesión con los cambios hechos
3. **Pedir confirmación antes de hacer cambios extras** — solo cambiar lo que el Jefe pidió

### Call Assistant — Estado actual
- **URL:** `pinnaclegroupwi.com/Tools/Pinnacle_Call_Assistant.html`
- **Login:** `deals@pinnaclegroupwi.com` / `4523Jics`
- **Copia GitHub:** `hostinger/tools/Pinnacle_Call_Assistant.html`
- **Archivos de soporte:** `auth.php`, `config.php`, `calendar.php`, `calendar_events.php`, `send_notification.php`

### Fix aplicado hoy (2026-04-06)
- **Problema:** `config.php` le faltaban las constantes `USERS` y `SESSION_HOURS` → login fallaba
- **Fix:** Agregadas las constantes → login funciona con `deals@pinnaclegroupwi.com` / `4523Jics`
- **Fix 2:** Paso `callback_time` ("Best time to call back") cambiado de `type:'text'` a dropdown con las 6 opciones válidas de Airtable (Morning, Afternoon, Evening, Anytime, Weekends Only, Unknow yet) — evita error INVALID_MULTIPLE_CHOICE_OPTIONS

### Pendiente (próxima sesión — aprobado por Jorge)
- Convertir otros Single Select fields a dropdowns: Stage completo, Water Source, Construction Type, Script Type, Occupied Status (typo "Owner Ocupied"), Roof/HVAC "Unknown" no válido en Airtable

---

## 2026-04-06 — LightRAG + Skills instaladas

### LightRAG — Búsqueda semántica activa
- **Script:** `rag/alex_rag.py`
- **Datos indexados:** memoria_ALex.md + shared_conversation.json + 83 Contacts + 5 Leads + 5 Deals
- **Comandos:**
  - `python3 rag/alex_rag.py index` — re-indexar todo
  - `python3 rag/alex_rag.py query "pregunta"` — buscar
- **Estado:** ✅ Funcional

### Skills instaladas en Claude Code (~/.claude/skills/)
| Skill | Comando | Descripción |
|-------|---------|-------------|
| humanizer | `/humanizer` | Humaniza textos con escritura AI |
| claude-mem | `/mem-search` | Memoria persistente entre sesiones |
| ui-ux-pro-max + 6 sub-skills | `/ui-ux-pro-max` | Diseño UI/UX, logos, slides, brand |
| obsidian (5 skills) | `/obsidian-*` | Markdown avanzado, canvas, CLI |
| everything-claude-code (181 skills) | varios | Ingeniería de software completa |
| superpowers (14 skills) | `/brainstorming`, `/tdd`, etc. | Metodologías avanzadas de desarrollo |
| **Total: ~208 skills** | | |

---

## 2026-04-06 — Acceso SSH Hostinger — GUARDADO

### Credenciales SSH Hostinger (pinnaclegroupwi.com)
- **Host:** `156.67.74.243`
- **Puerto:** `65002`
- **Usuario:** `u433637438`
- **Contraseña:** en `.env` → `HOSTINGER_SSH_PASS`
- **Ruta Tools:** `~/domains/pinnaclegroupwi.com/public_html/Tools/`
- **Comando de conexión:** `sshpass -p "$HOSTINGER_SSH_PASS" ssh -p 65002 -o StrictHostKeyChecking=no u433637438@156.67.74.243`

### Backup de archivos en GitHub
- **Carpeta:** `hostinger/tools/` en repo `geocarp24/alex-real-estate-system`
- **Archivos:** Pinnacle_Call_Assistant.html, Property_Inspector.html, auth.php, config.php, calendar.php, y más
- **Propósito:** Referencia para futuros cambios — leer desde GitHub antes de editar en Hostinger

---

## 2026-04-07 — TAREA CRÍTICA: INSTALAR BLOTATO MCP EN VPS
**Prioridad:** 🔴 MÁXIMA — Aprobada por Jorge
**Status:** ⚙️ EN PROCESO

### Objetivo
Instalar el servidor MCP de Blotato en el VPS para que el bot de Telegram pueda publicar en FB + IG directamente sin necesitar Claude Code desktop.

### Instrucciones para el Monitor/VPS:

**Paso 1 — Verificar si Node.js está instalado:**
```bash
node --version
npm --version
```

**Paso 2 — Instalar el MCP de Blotato:**
```bash
npm install -g @blotato/mcp-server
```

**Paso 3 — Si @blotato/mcp-server no existe en npm, intentar:**
```bash
npm install -g blotato-mcp
```

**Paso 4 — Verificar qué paquetes de Blotato existen en npm:**
```bash
npm search blotato
```

**Paso 5 — Probar llamada directa a la API REST de Blotato:**
```bash
curl -s "https://my.blotato.com/api/v1/accounts" \
  -H "api-key: blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k="
```

**Paso 6 — Probar endpoint alternativo:**
```bash
curl -s "https://api.blotato.com/v1/accounts" \
  -H "api-key: blt_2Jz5IZHqjY6WzhfTWkDVskRANpeibfXkyDTvUB+mn8k=" \
  -H "Content-Type: application/json"
```

**Paso 7 — Reportar resultado completo en memoria_ALex.md**

### Criterio de éxito:
- MCP instalado en VPS O confirmación de que la API REST funciona directamente
- Documentar exactamente qué endpoint y método funciona
- Si nada funciona, documentar el error exacto para que ALEX pueda buscar solución alternativa

---

## SESIÓN 2026-04-06 — Seguimiento Engine + Google Calendar

### SISTEMA DE SEGUIMIENTO — OPERATIVO EN MAKE.COM

**Campaña de 24 toques construida y funcional:**
- **Scenario Engine** (ID: 4656571) — Corre diario 9:30 AM — Envía SMS + Email a contactos en Stage "Seguimiento"
- **Scenario Cold** (ID: 4656574) — Corre diario 9:45 AM — Mueve a "Dead" contactos con Step >= 24

**Para activar un contacto:**
1. Airtable → Contacts → Stage = `Seguimiento` → Seguimiento Step = `0`
2. El sistema hace el resto por 12 meses automáticamente

**Calendario de toques:**
- Mes 1: Días 1, 3, 7, 14, 21 (5 toques intensos)
- Mes 2-12: Cada 2 semanas (19 toques)
- Total: 24 toques → Stage pasa a Dead automáticamente

**Conexiones Make.com activas:**
- SMTP Hostinger (ID: 8232359): deals@pinnaclegroupwi.com / smtp.hostinger.com:587
- Airtable OAuth (ID: 7862703)
- Quo/OpenPhone (ID: 7973467) — Número: (920) 777-9886

**Campo nuevo en Contacts:** `Seguimiento Step` (Number, default 0)
**Stage nuevo en Contacts:** `Seguimiento` (entre "To Be Contacted" y "Contacted")

**Fórmula Next follow up date:**
```
{{addDays(now; switch(1.`Seguimiento Step`; 0; 2; 1; 4; 2; 7; 3; 7; 4; 21; 14))}}
```

**Notas técnicas Make IML:**
- Concatenación: `"texto" + variable` (NO concat(), NO toString())
- Phone en Airtable: almacenado como Number sin + (ej: 19204454093)
- Quo recibe: `{{1.Phone1}}` directamente sin formateo
- Separador de funciones: `;` (punto y coma)

---

### CALL ASSISTANT — CORRECCIONES APLICADAS

**URL del botón en Leads:**
```
"https://pinnaclegroupwi.com/Tools/Pinnacle_Call_Assistant.html?recordId=" & RECORD_ID()
```
⚠️ "Tools" con T mayúscula — crítico

**Campos Single Select convertidos a dropdowns:**
- Stage: To Be Contacted | Seguimiento | Contacted | Analized | Offer Sent | Dead
- Lenguage: English | Spanish (ortografía exacta: "Lenguage")
- Best time to call: Morning | Afternoon | Evening | Anytime | Weekends Only | Unknow yet
- Occupied Status: Empty | Owner Occupied | Rented
- Water Source: City Water | Septic | Well
- Construction Type: Concrete | Wood | Brick
- Foundation: Basement | Crawl Space | Slab
- Roof Status: Under 15 years | Over 15 years
- HVAC Status: Under 15 years | Over 15 years
- Lease Type: Month to Month | Yearly
- Script Type: Marketing List | Pre-foreclosure | Driving for Dollars

---

### GOOGLE CALENDAR — CONECTADO VÍA SERVICE ACCOUNT

**Service Account:** alex-calendar-agent@pinnacle-alex-bot.iam.gserviceaccount.com
**Archivo:** /opt/alex-bot/secretario/google_creds/service_account.json
**Calendario conectado:** deals@pinnaclegroupwi.com
**Estado:** OPERATIVO ✅

**Comandos Telegram disponibles:**
- `/agenda` — Ver citas de hoy
- `/agenda semana` — Próximos 7 días
- `/cita 2026-04-10 14:00 John Smith Motivo` — Crear cita

---

### IDs DE REFERENCIA MAKE.COM
- Organization: 6716517 | Team: 1932270
- Scenario Engine: 4656571 | Scenario Cold: 4656574
- SMTP Connection: 8232359 | Airtable OAuth: 7862703 | Quo: 7973467
- Quo Phone ID: PNNlYlSvAb

### PENDIENTES
- [ ] Probar flujo completo con contacto que tenga email
- [ ] Verificar ID del campo "Seguimiento Step" en Make.com
- [ ] Agregar emails a contactos que no los tienen
- [ ] Considerar Phone2 como respaldo en SMS

---

## 2026-04-07 — GEO CARPENTRY LLC — BASE DE CONOCIMIENTO

### PERFIL DE LA EMPRESA
- **Nombre:** Geo Carpentry LLC
- **Ubicación:** Green Bay, WI (ZIP 54301)
- **Radio de servicio:** 100 millas — cubre Green Bay, Appleton, Oshkosh
- **Experiencia:** 15+ años en construcción residencial
- **Licencia:** Licensed & Insured ✅
- **Ventaja competitiva:** Cotizaciones en 24 horas
- **Website:** geocarpentry.com (en migración a Hostinger/WordPress)
- **Facebook:** Existe — baja presencia (1 follower, sin reseñas)
- **GMB:** Existe — 2 reseñas 5 estrellas
- **Yelp:** Existe
- **Presupuesto marketing:** $0 (arranque orgánico)
- **Meta 2026:** $1,000,000 en revenue

### SERVICIOS PRINCIPALES
1. Kitchen Remodeling (alto ROI)
2. Bathroom Remodeling (alto ROI — spa-like, curbless showers, heated floors)
3. Custom Decks y Outdoor Living (madera y composite)
4. Basement Finishing (home offices, gyms, living spaces)
5. Home Additions (primary suites, sunrooms, garage conversions)
6. Garage Builds
7. Residential New Construction
8. Energy Efficiency & Smart Home Integration

### ANÁLISIS DE MERCADO WISCONSIN 2026
- Single-family housing permits en aumento en Wisconsin (WBA Q1 2026)
- Green Bay aprobó zoning reforms: duplexes y ADUs permitidos en zonas residenciales → OPORTUNIDAD para additions y new construction
- Demanda alta por aging housing stock + record home equity + condiciones económicas favorables
- Tendencias 2026: smart kitchens, spa bathrooms, four-season sunrooms, ADUs, energy efficiency

### FORTALEZAS IDENTIFICADAS
- 15+ años de experiencia
- Servicios completos (remodel + additions + new construction)
- Licensed & insured
- Radio de 100 millas
- Cotización en 24 horas
- Conexión con Pinnacle Holdings (rehabs garantizados)
- Bilingüe (ventaja con comunidad hispana en WI)

### DEBILIDADES IDENTIFICADAS
- Presencia digital muy baja (2 reseñas GMB, 1 follower FB)
- Sin portafolio visible en website
- Sin landing pages localizadas por ciudad
- Sin reseñas en plataformas de terceros
- Sin costo calculator ni design gallery en website
- Sin blog para SEO orgánico
- Formulario en Google Forms (poco profesional)
- Website en Mixo.io (SEO limitado) → EN MIGRACIÓN a Hostinger/WordPress

### OPORTUNIDADES CLAVE 2026
1. ADUs y duplexes — nuevas leyes de zoning en Green Bay
2. Kitchen + Bathroom remodel — mayor ROI para homeowners
3. Mercado hispano en WI — bilingüe = ventaja competitiva
4. Property managers — trabajo recurrente
5. Agentes RE locales — referidos constantes
6. Green Bay Home Show (Resch Center) — evento anual
7. Nextdoor — plataforma subestimada para contratistas locales

### ESTRATEGIA APROBADA (Orgánica — $0 presupuesto inicial)
Prioridad 1: Reseñas (meta: 15+ en 30 días)
Prioridad 2: GMB optimizado al 100% con fotos
Prioridad 3: Migrar website a Hostinger/WordPress con landing pages localizadas
Prioridad 4: Nextdoor Business + grupos Facebook locales
Prioridad 5: Networking — agentes RE, property managers, arquitectos
Prioridad 6: Blog con contenido SEO local
Prioridad 7: Craigslist Green Bay + Houzz + Angi + Thumbtack (gratuitos)

### PROYECCIÓN REVENUE 2026
- Mayo: $10K-30K | Junio: $30K-60K | Julio: $60K-100K | Q4: $150K-200K/mes
- Total realista orgánico: $400K-600K
- Para $1M: requiere $1,500-2,000/mes Google Ads desde Julio

### PENDIENTES TÉCNICOS
- [ ] Migrar geocarpentry.com a Hostinger/WordPress
- [ ] Landing pages: Green Bay/Appleton/Oshkosh por servicio
- [ ] Google Analytics + Facebook Pixel
- [ ] Reemplazar Google Forms con formulario propio
- [ ] Galería de portafolio con fotos reales
- [ ] Blog con contenido SEO
- [ ] Perfiles: Nextdoor, Houzz, Angi, Thumbtack
- [ ] 15+ reseñas en GMB
- [ ] Unirse a grupos Facebook locales



## 2026-04-07 — REGLA CRÍTICA: OPTIMIZACIÓN DE CRÉDITOS CLAUDE

### APROBADO POR JORGE — APLICAR SIEMPRE SIN EXCEPCIÓN

#### Jerarquía de modelos (de menor a mayor costo):
```
NIVEL 1 — Haiku (más barato):
→ Respuestas simples de texto
→ Consultas de Airtable básicas
→ Confirmaciones y updates de estado
→ Lectura de memoria
→ Respuestas de Telegram simples
→ Clasificación de emails (El Secretario)

NIVEL 2 — Sonnet (medio):
→ Análisis de deals simples
→ Generación de contenido social media
→ Skip tracing con Tracy
→ Cambios de código SENCILLOS (menos de 20 líneas)
→ Consultas de mercado básicas
→ Respuestas estructuradas al Jefe

NIVEL 3 — Opus/Claude Code (más caro):
→ SOLO cuando hay modificación de código compleja (+20 líneas)
→ Debugging profundo de sistemas
→ Arquitectura de nuevos agentes
→ Análisis financiero complejo (Scout + Matemático + Fact-Checker)
→ Tareas que requieren razonamiento muy profundo
```

#### Reglas de orquestación de recursos:
1. **NUNCA invocar Claude Code** para tareas que no requieran modificación de código pesado
2. **NUNCA usar modelo caro** cuando uno más barato puede resolver la tarea
3. **Antes de invocar cualquier sub-agente** — evaluar si realmente es necesario
4. **Agrupar tareas similares** — hacer múltiples consultas en una sola llamada
5. **Caché de resultados** — si ya tenemos un dato, no volver a buscarlo
6. **Leer memoria primero** — evitar análisis repetidos de mismas propiedades/zonas

#### Criterio de decisión rápido:
```
¿Es código complejo?  → SÍ → Claude Code (Opus)
¿Es código simple?    → SÍ → Sonnet
¿Es texto/consulta?   → SÍ → Haiku
¿Ya está en memoria?  → SÍ → Usar memoria, NO invocar agente
¿Es análisis de deal? → SÍ → Sonnet (Scout + Matemático + Fact-Checker)
```

#### Meta de ahorro:
- Reducir uso de Claude Code en 70%
- Usar Haiku para 60% de tareas rutinarias
- Usar Sonnet para 30% de tareas medias
- Usar Opus/Claude Code solo para 10% crítico

**Estado:** ✅ ACTIVO — Aplicar inmediatamente en todas las sesiones
**Aprobado por:** Jorge Cruz — 2026-04-07

---

### 2026-04-10 — Tracy→Contacts: Sistema anti-duplicados desplegado (Opus)

**Problema:** el_chismoso creaba duplicados intermitentes porque Tracerfy devuelve formatos inconsistentes entre runs (CAPS vs Title Case, phone con/sin country code). 11 contactos Skip Trace con 3 duplicados (27% dedup rate).

**Causa raíz real:** No fueron "contactos vacíos" como se pensó antes — fueron pares de duplicados con el mismo Tracerfy ID pero diferente formato de nombre/teléfono. El fallback-por-Mail-Address del código viejo no disparaba por alguna race condition.

**Solución desplegada:**
1. `el_chismoso.php` — función `findOrDedupeContactByMailAddress()` con normalización agresiva (strip punct + street→st etc.) y completeness score. Busca TODOS los matches, elige winner, merge campos, DELETE losers.
2. `el_polling.php` — dedup Tracy con normalización + ventana 14 días + validación de respuesta chismoso con retry automático.
3. `cleanup_duplicates.php` (NUEVO) — one-shot para limpieza histórica. Protegido por token, con modo dry_run.

**Resultado:** 11 → 8 Skip Trace contacts únicos. 0 duplicados restantes.

**Descubrimiento crítico:** El puerto SSH real de Hostinger es **65002** (no 22). Credenciales completas en `/opt/alex-bot/.env` como `HOSTINGER_SSH_*`. sshpass instalado en el VPS. Esto destraba deploys directos sin depender de GitHub Actions. Guardado en memoria auto como `reference_hostinger_deploy.md`.

**Gap pendiente:** Commit `65820c0` existe solo localmente — git push a GitHub falló por falta de PAT. Deploy está vivo en producción pero historial git no sincronizado.

**Commit local:** `65820c0 feat: sistema anti-duplicados Tracy→Contacts permanente`

---

### 2026-04-11 — Tracy/Contacts linked a Leads via Property Address (Opus)

**Problema reportado:** Jorge notó que Tracy/Contacts no aparecían linkeados en la tabla Leads. Recordaba que antes toda la info del dueño aparecía linkeada automáticamente en Leads y ahora no.

**Diagnóstico (vía Meta API):** Los linked-record fields YA EXISTÍAN en el schema de Airtable:
- Leads: 🔗 `Contacts`, 🔗 `Tracy`, lookup `status (from Tracy)`
- Contacts: 🔗 `Property Address` → Leads
- Tracy: 🔗 `Leads 2` → Leads

Pero el código PHP **no los poblaba** — solo el Stage, Full Name, Phone, etc. Por eso los campos bidireccionales quedaban vacíos y la info no "aparecía linkeada" en Leads.

**Descubrimiento clave:** Había una sesión Sonnet paralela del 2026-04-10 22:05 (`a2a2f5a`) que ya había escrito la lógica correcta en el_polling y el_chismoso — pero NUNCA se desplegó al servidor. El código local de git estaba correcto pero producción corría la versión vieja. Lesión: los commits en git local no llegan a Hostinger automáticamente, siempre hay que hacer SCP (vía .env credentials) o git push → GitHub Actions.

**Solución final desplegada hoy:**
1. el_polling.php setea Tracy.`Leads 2` al crear record (y append en dedup 14d)
2. el_chismoso.php lee Tracy.`Leads 2`, fallback `findLeadIdsByAddress()`, setea Contact.`Property Address` con union
3. `backfill_links.php` (NUEVO) — one-shot con dry_run, match por address normalizada

**Backfill ejecutado:** 14 Tracy + 12 Contacts linkeados. Los 64 Tracy sin match corresponden a leads históricos que ya no existen en la tabla.

**Verificado:** Lead `recUeRc3nqs8JzovK` (515 N HURON ST) ahora muestra linkeados `Contacts`, `Tracy` y lookup `status (from Tracy)`.

**Notificación:** Enviada a Jorge vía Telegram bot al completar.

**Commit local:** `f3a005a feat: link Tracy y Contacts a Leads vía Property Address`

---

### 2026-04-11 — Pipeline Tracy→Contacts: enrichment completo (Opus, FASE A+B+C+D)

**Contexto:** Jorge pidió ver todos los campos que cada agente toca, analizar brechas y cerrar lo que faltaba para quedar profesional.

**Análisis detallado escrito en `/root/.claude/plans/lexical-dazzling-muffin.md`:** workflow diagrams, field matrix per agent, 14 gaps priorizados por severidad.

**4 fases desplegadas:**

1. **FASE A — Pérdida de datos:**
   - el_chismoso ahora captura Phone1-4 (antes: 3). Fallback chain: primary → mobile_1-3 → landline_1-2
   - Email3 escrito a Contacts (antes perdido)
   - Nuevo campo `Owner Address` consolidado ("Street, City, State Zip")
   - `scoreContactCompleteness()` + `mergeable` sincronizados en el_chismoso y cleanup_duplicates

2. **FASE B — CRM tracking:**
   - `Leads.Last Contact Date` seteado en cada rama final de el_polling (6 branches)
   - `Contacts.Last contact date` seteado en cada upsert de el_chismoso

3. **FASE C — Audit trail:**
   - Nueva función `logDedupeAudit()` en el_chismoso
   - Cuando se eliminan duplicados, crea registro en `Notes & Activity` linkeado al winner con snapshot de los losers (nombre, teléfonos, tracerfy_id, score) y lista de campos rescatados
   - Constante `TABLE_NOTES = 'tbleOBXJl7sDhwj5w'`

4. **FASE D — Stages diferenciadas:**
   - `atPatch()` ahora acepta `$typecast=true` param para auto-crear select options
   - `no_results` → `'Skip Trace - No Results'`
   - `timeout/error/incomplete_address` → `'Skip Trace - Error'`
   - `success/phone_exists` → `'To be Contacted'` (sin cambio — preserva pipeline)

**Verificación:** 15/15 Skip Trace contacts re-procesados vía el_chismoso direct POST. Owner Address y Last contact date al 100%. Email3 y Phone4 poblados donde Tracerfy devolvió data.

**Pendiente manual (Jorge):** eliminar del schema Contacts en Airtable UI: `Leads`, `Leads 2`, `LG`. Son campos texto huérfanos que nunca se usaron — el linking real lo hace `Property Address`. No se puede borrar columnas vía API.

**Commit local:** `25a9e09` feat: FASE A+B+C+D — enrichment completo del pipeline Tracy→Contacts

**Decisiones de diseño:**
- Phone strategy: Phone4 existente + landline_2 fallback (no Phone5 nuevo) — Jorge eligió esta opción
- Dedup: Mail Address (unchanged)
- Stages success: mantener "To be Contacted" sin romper el pipeline existente (solo agregar stages para rutas de excepción)

---

### 2026-04-11 — Geo Carpentry website: contenido completo generado (Opus)

**Petición:** Jorge pidió migración profesional de geocarpentry.com a Hostinger/WordPress/Astra con "Construction Company" starter template, y que ALEX hiciera TODO el contenido sin que él tuviera que meterse.

**Descubrimientos clave durante el diagnóstico:**
1. **Brand Identity Doc v1.0** compartido por Jorge — colores oficiales Navy `#1B2A4A` + Orange `#FF6B00` (NO los del workflow YAML previo que usaban `#0d2137`/`#c85a14`)
2. **Fonts oficiales:** Playfair Display (headlines) + Inter (body) + Montserrat (accents)
3. **Slogan oficial:** "Built to Last. Crafted with Pride."
4. **NAP completo:** Phone (920) 367-1272, WhatsApp (920) 934-0351, admin@geocarpentry.com, 735 E Walnut St Suite 3 Green Bay WI, founded 2014, 10+ years, 500+ projects, 100mi radius, bilingual
5. **Service area: 15 ciudades** — Green Bay, Appleton, Oshkosh, Sheboygan, Manitowoc, Fond du Lac, Wausau, Marinette, Oconto, Shawano, De Pere, Ashwaubenon, Howard, Suamico, Pulaski
6. **6 servicios oficiales:** Custom Carpentry, Kitchen Remodeling, Bathroom Remodeling, Deck Building, Home Renovation, General Construction
7. **SSH Hostinger funciona para geocarpentry.com** — path `domains/geocarpentry.com/public_html/` (misma cuenta u433637438 que pinnaclegroupwi.com)
8. **WP-CLI YA INSTALADO** en /usr/local/bin/wp del servidor
9. **WordPress 6.9.4** activo, Astra 4.12.7 theme
10. **Logo YA subido** al WordPress (attachment ID 24) — `GEO-CARPENTRY-Logo-with-Soft-White-Highlights-2.png` con múltiples versiones + favicon
11. **Plugin SEO activo:** SureRank (también genera schema markup propio, coexiste con el mío)

**Decisiones finales (diferentes al plan inicial):**
- Descartar el Construction Company starter template — Jorge prefirió from scratch
- Custom child theme `geo-carpentry-child` con CSS de ~570 líneas
- Stock photos de Unsplash (no fotos reales)
- Colores del Brand Doc (corregidos del workflow YAML)

**Workarounds técnicos descubiertos:**
- `wp db export` falla silenciosamente en este Hostinger — usar `mysqldump` directo con credenciales de wp-config
- WP Application Password NO aparece en wp-admin UI cuando el sitio no tiene HTTPS — WordPress lo oculta por seguridad. Solución: crear via `wp user application-password create` vía SSH
- Cloudflare bloquea requests desde el VPS externo (error 1001) — todo se hace via SSH+WP-CLI directo, no REST API externa
- Media upload via REST API falla por Cloudflare — usar `wp media import` con SCP
- `--post_category` en wp post create no acepta nombres, solo slugs/IDs — usar `wp term create category` + `wp post term set` por separado
- `wp menu list --field=X` no funciona, usar `--fields=X --format=ids`

**Lo que quedó deployed:**
- Child theme activado en producción
- 5 core pages + 6 service pages (parent=services, URLs /services/{slug}/)
- 10 SEO blog posts localizados para WI + 6 categorías
- 10 stock photos en media library
- Main Menu en location primary (Home → Services → Portfolio → About → Contact)
- Schema markup LocalBusiness completo en `<head>` de cada página (via functions.php)
- robots.txt con referencia al sitemap
- Logo existente asignado como custom_logo del child theme

**Issue bloqueante para go-live:**
- Domain geocarpentry.com apunta a Cloudflare (172.66.0.42) pero el origin no está configurado correctamente
- Desde fuera, `https://geocarpentry.com` retorna 409 (Cloudflare error 1001)
- El sitio es accesible SOLO vía Hostinger staging URL: `https://blueviolet-gerbil-900105.hostingersite.com/`
- **Jorge tiene que:** configurar Cloudflare DNS → origin IP de Hostinger + activar SSL (o pausar Cloudflare y apuntar DNS directo a Hostinger)

**URLs para review:**
- Staging (funciona): https://blueviolet-gerbil-900105.hostingersite.com/
- Target (roto hasta fix DNS): http://geocarpentry.com/

**Commit local:** `feat: Geo Carpentry website — full content + child theme generation`

---

### 2026-04-11 (sesión nocturna) — Geo Carpentry website v2: feedback round (Opus)

**Feedback de Jorge:**
1. ❌ No se veía "Geo Carpentry" por ningún lado (site-title oculto por Astra)
2. ❌ Quitar TODO lo "custom" excepto construcciones nuevas custom
3. ❌ Footer tenía info incorrecta
4. ❌ Faltaba blog visible, FAQ, privacy, terms
5. ❌ Faltaba formulario, chat, email popup
6. ❌ Faltaba versión Spanish
7. ❌ Admin email incorrecto
8. ❌ Voice search optimization

**Soluciones desplegadas (todas mientras Jorge dormía):**

**BATCH 1 — Brand visibility:**
- Astra ocultaba site-title via `display:none !important`. Workaround: creé un `gc-brand-bar` que se inyecta via `wp_body_open` action en cada página, con logo 72px + "GEO CARPENTRY" título + slogan + phone + WhatsApp. Bypass completo de la config de Astra.

**BATCH 2 — Eliminar "custom":**
- Creé nueva service page `Finish Carpentry & Trim` (reemplaza Custom Carpentry)
- Borré `custom-carpentry` page, creé `finish-carpentry` con parent=services
- Regeneré kitchen/bathroom/deck/home-renovation/general-construction pages sin mencionar "custom" (excepto "Custom Home Builds" en General Construction)
- Actualicé home + services page
- Actualicé schema markup LocalBusiness (hasOfferCatalog) con los nombres nuevos

**BATCH 3 — Footer:**
- Sobrescribí `astra_footer` action con footer branded custom
- Columnas: Brand (logo + tagline + social), Services, Company, Contact
- NAP completo + privacy/terms links en bottom

**BATCH 4 — Blog/FAQ/Legal pages:**
- `/news/` — asignada como `page_for_posts` para mostrar los 10 blog posts
- `/faq/` — 15 Q&As + FAQPage schema markup (voice search opt)
- `/privacy-policy/` — asignada como `wp_page_for_privacy_policy`
- `/terms-of-service/` — legal completo
- Menú actualizado: Home → Services → News → FAQ → Portfolio → About → Contact

**BATCH 5 — Forms + Popup:**
- SureForms [sureforms id=2145] embedded en Contact page
- Email capture popup después de 15s (sessionStorage gated) con mailto fallback a admin@geocarpentry.com

**BATCH 6 — Spanish version (3 pages core):**
- `/inicio/` — home-es
- `/servicios/` — services-es
- `/contacto/` — contact-es (con formulario)
- Pendiente: About, Portfolio, 6 service pages, FAQ, Blog Spanish

**Descubrimientos técnicos:**
- SureForms shortcode: `[sureforms id="XX"]` (NO srfm)
- SureForms post type: `sureforms_form`
- WP-CLI `wp menu list --field=X` no funciona, usar `--fields=X --format=ids`
- WP-CLI `wp option patch update astra-settings key value` para setear keys específicas de un serialized option
- Astra `display-site-title=True` no es suficiente — el component puede estar removido del header builder. Workaround: inyectar via wp_body_open action
- Para override del footer de Astra: `remove_action('astra_footer', 'astra_footer_small_footer_template')` + custom output

**Estado final:**
- 18 pages + 10 posts + 69 media items
- Child theme v2 con gc-brand-bar + popup + footer custom
- Schema LocalBusiness + FAQPage
- Commit `f6520ee` pushed to github.com/geocarp24/alex-real-estate-system

**Issue bloqueante:** Dominio geocarpentry.com aún apunta a Cloudflare sin origin config. Staging URL `https://blueviolet-gerbil-900105.hostingersite.com/` es el único accesible desde afuera.

**Pendientes para próxima sesión:**
- Stock images referenciadas en HTML de páginas (ya están en media library)
- WP Live Chat Support config
- Quote-specific form (separado del Contact form)
- More Spanish pages (About, Portfolio, 6 service pages, FAQ, Blog)
- Cloudflare/DNS fix para go-live



## 2026 — Make.com API Key (CONFIDENCIAL)
- **API Key:** `0d1609f7-8242-4ca2-8e05-706d18152cda`
- **Organization ID:** `6716517`
- **Team ID:** `1932270`
- **Base URL:** `https://us2.make.com/api/v2`
- **Header:** `Authorization: Token 0d1609f7-8242-4ca2-8e05-706d18152cda`
- **Nota:** NUNCA imprimir en outputs públicos. Solo uso interno del sistema ALEX.


## 2026-04-16 — REGLA CRÍTICA: Pipeline Social Media es AUTOMÁTICO

### APROBADO POR JORGE — NUNCA REPETIR ESTA INSTRUCCIÓN

El pipeline de Social Media siempre corre en 3 fases CONSECUTIVAS y AUTOMÁTICAS:

```
FASE 1: Social Media Agent → genera contenido → guarda en Airtable
         ↓ (automático, sin preguntar)
FASE 2: El Creativo → genera visuales de todos los posts pendientes
         ↓ (automático, sin preguntar)
FASE 3: El Programador → publica/programa en FB + IG
         ↓ (automático, sin preguntar)
REPORTE FINAL → notificar al Jefe con resumen completo
```

**REGLA:** Cuando el Jefe pide contenido de Social Media, ALEX ejecuta las 3 fases sin pedir confirmación entre ellas. Solo reporta al final.

**Aprobado por:** Jorge Cruz — 2026-04-16
**NUNCA pedir confirmación entre fases. NUNCA preguntar si proceder.**


## 2026-04-16 — REGLAS CRÍTICAS DE OPERACIÓN (Aprobadas por Jorge — NUNCA OLVIDAR)

### AUTONOMÍA TOTAL
- ALEX es autosuficiente. Si tiene acceso, lo hace él. NUNCA pedir al Jefe que haga algo que ALEX puede hacer.
- ALEX tiene acceso SSH, API Keys, Airtable, Make.com, Blotato, GitHub — úsalos sin pedir permiso.
- No preguntar si proceder. Proceder y reportar al final.

### PIPELINE SOCIAL MEDIA — AUTOMÁTICO Y CONSECUTIVO (OBLIGATORIO)
El pipeline de Social Media siempre corre en 3 fases AUTOMÁTICAS sin pausas ni confirmaciones:
```
FASE 1: Social Media Agent → genera contenido → guarda en Airtable
         ↓ (automático)
FASE 2: El Creativo → genera visuales de TODOS los posts pendientes
         ↓ (automático)
FASE 3: El Programador → publica/programa en FB + IG
         ↓ (automático)
REPORTE FINAL → notificar al Jefe con resumen completo
```
NUNCA pedir confirmación entre fases. NUNCA preguntar si proceder. NUNCA dar trabajo al Jefe que ALEX puede hacer.

### COMPORTAMIENTO GENERAL
- Proactivo, dinámico, solucionador de problemas
- Si hay un error, buscar la solución y ejecutarla
- Reportar al final, no interrumpir con preguntas durante el proceso
- Reaprender activamente — cada lección se graba en memoria inmediatamente

**Aprobado por:** Jorge Cruz — 2026-04-16
**ESTAS REGLAS SON PERMANENTES — NUNCA REPETIR AL JEFE**


---

## 2026-04-22 — SESIÓN COMPLETA: WEBFORM + CHATBOT + CONTACT REDESIGN + EMAIL FIX

Sesión maratón. Cierre de Pinnacle Holdings public-facing stack. Aprobado por Jorge.

### A. Webform "Get My Cash Offer" (typeform-style multi-step)

**Página:** `/get-my-offer/` (WP page id `1748`).

**Stack frontend** (servido vía WP page + static assets en `/agents/pinnacle_form/`):
- `pinnacle_form.css` — brand `#0D3B2E` + `#C9A84C`, mobile-first
- `pinnacle_form_i18n.js` — diccionario EN+ES (s1–s17 + `ok` + `s_resume` + `s_returning`)
- `pinnacle_form_screens.js` — 18 pantallas + builders; `startLeadAndGo()` con soporte `reopen_lead_id`
- `pinnacle_form_core.js` — state machine con back-stack, `PNF_BRAIN.fire()`, `PNF_SESSION.{load,clear,restore}` (localStorage 2h TTL), `PNF_CORE_INIT` + `PNF_SHOW_FIRST` invocados desde screens.js tras `mountAll`
- Cargados desde `wp_assets/pinnacle_form/` (mirror) deployados via SCP a `/home/u433637438/.../public_html/agents/pinnacle_form/`
- Cache busting: `?v=<filemtime>` en URLs del WP page content

**Stack backend** (`hostinger/agents/pinnacle_public.php`, ~600 líneas):

Acciones expuestas vía POST JSON:
- `places_proxy` — Google Places autocomplete passthrough (server-side API key)
- `start_lead` — crea Lead en Airtable, manda OTP por SMS via Twilio
- `verify_phone` — valida OTP, marca Lead como verificado
- `resend_code` — re-envía OTP (15s pacing)
- `update_lead` — actualiza campos del Lead (cada paso del form)
- `lookup_existing` — **NUEVO** — dedup por phone (Contacts.Phone1-4) con fallback a address; devuelve `{exists, lead_id, contact_id, last_stage}` para flujo returning-user
- `form_brain` — **NUEVO** — micro-acks empáticos estilo Fer; usa Haiku 4.5 con prompt corto basado en el campo recién contestado
- `chat_message` — **NUEVO** — backend del chatbot floating widget

Helpers críticos:
- `pp_normalize_phone($raw)` → E.164 (+1XXXXXXXXXX)
- `pp_email_valid($email)` → filter_var + DNS check
- `pp_send_sms($to_e164, $msg)` → Twilio API, con retry 1×
- `pp_airtable_create / pp_airtable_update / pp_airtable_find_existing / pp_airtable_get_lead`
- `pp_compute_score($fields)` → score interno 0-100 (motivation × condition × timeline × equity)
- `pp_fer_brain($context, $field, $value)` → llama Anthropic Haiku 4.5, devuelve `{ack: "string corta empática"}`
- `pp_chat_brain($history, $lang)` → llama Anthropic Sonnet 4.6, system prompt incluye `<escalate>{...}</escalate>` JSON tag para detectar handoff a humano
- `pp_chat_notify_telegram($summary)` → alerta a TELEGRAM_CHAT_ID cuando chatbot escala

**Persistencia de sesión:**
- WP transients con TTL **2h** (subido desde 30min): `set_transient(pp_lead_session_key($lead_id), $session, 7200);`
- localStorage navegador: `pnf_session` (form), `pnf_chat` (chatbot), ambos 2h TTL

**Dedup logic (returning-user UX):**
1. Tras validar phone (s_phone), backend corre `pp_airtable_find_existing(phone, address?)`
2. Si match exacto por phone → muestra `s_returning` con 3 opciones: **Update existing** | **Get callback** | **Start new request**
3. Si match parcial por address → soft prompt opcional, no bloquea
4. `reopen_lead_id` permite continuar desde último stage guardado

### B. Chatbot Fer-style (floating widget en TODAS las páginas excepto el form)

**Frontend:**
- `hostinger/agents/pinnacle_chat/pinnacle_chat.css` — burbuja redonda 60×60 esquina inferior-derecha, gradient verde + dot dorado pulsante
- `hostinger/agents/pinnacle_chat/pinnacle_chat.js` — self-contained, sin deps externas
- API pública: `window.PinnacleChat.{open(), close(), reset()}` (usable desde botones del Contact page)
- Estado en `localStorage["pnf_chat"]` con TTL 2h
- POST a `/agents/pinnacle_public.php` action=`chat_message` con `{session_id, lang, history}`
- Idioma auto-detectado (`navigator.language`) EN/ES; greeting + UI bilingüe

**Loader (MU-plugin auto-activado):**
- `hostinger/mu-plugins/pinnacle-chat-loader.php`
- Enqueue solo en frontend (`!is_admin()`)
- Skip en `is_page('get-my-offer')` (cliente ya está en flujo estructurado)
- Cache bust: `$ver = '1.0.' . filemtime(.../pinnacle_chat.js)`

**Bug crítico resuelto:** CSS `display:flex` overrideaba `hidden` attribute → panel interceptaba clicks aunque "oculto". Fix: `.pnc-panel[hidden] { display:none !important; }`

**Escalación automática:** Si `pp_chat_brain` detecta intent caliente (vender pronto, lead motivado), inserta tag `<escalate>{summary, contact_info}</escalate>` en respuesta. Backend extrae, crea Lead en Airtable + alerta Telegram, y muestra mensaje "✓ Got it! A Pinnacle team member will reach out within 24 hours."

### C. Site-wide CTA redirect

Todos los botones "Get My Free Offer" del sitio ahora apuntan a `/get-my-offer/` (antes apuntaban a `/contact/`).

Páginas actualizadas (vía WP REST API + bridge):
- About Us (id 1399) — 1 CTA
- Services (id 1400) — 2 CTAs
- Home (id 1373) — already pointed correctly

Backups en `backups/wp_pinnacle/cta_fix_2026-04-22_213500/{1399,1400}_*.{before,after}.html`

### D. Contact Page redesign — "Five Ways to Reach Us"

Página id `1402`. Reemplazó la versión vieja con CF7 form embebido.

5 cards en grid responsivo:
1. **Phone** — `tel:+19204428287`
2. **Email** — `mailto:deals@pinnaclegroupwi.com`
3. **Visit** — Google Maps link a oficina
4. **Online Form** — `/get-my-offer/` (CTA prominente)
5. **Chat With Us** — botón que llama `window.PinnacleChat.open()`

CF7 form removido completamente. Página rebuild con Gutenberg blocks (wp:cover hero + wp:columns para los 5 cards).

Backup: `backups/wp_pinnacle/contact_five_ways_2026-04-22_214000/`

### E. Email reply recipient bug fix (`secretario/email_monitor.py`)

**Bug:** Respuestas a inquiries del CF7 viejo iban a `wordpress@pinnaclegroupwi.com` (mailbox no existe → bounce). El cliente real estaba en Reply-To header o dentro del body como "Email: foo@bar.com".

**Fix (3-tier resolution chain):**
1. `_extract_email_addr(raw)` — parsea formato `Name <foo@bar.com>`
2. `_is_system_sender(addr)` — blocklist: `wordpress@`, `no-reply@`, `mailer-daemon@`, etc.
3. `_extract_email_from_body(body)` — regex scan `/Email:\s*(\S+@\S+)/i`

```python
def responder_email_aprobado(db_id, texto_personalizado):
    # 1. Try Reply-To header
    # 2. Else try From (if not system sender)
    # 3. Else scan body
    # Fallback: alert to Telegram, mark as needs-manual

def enviar_respuesta_email():
    msg["From"] = f"Pinnacle Holdings <{EMAIL_ADDRESS}>"
    msg["Reply-To"] = EMAIL_ADDRESS  # forzado a deals@
```

**Deploy:** Workflow `deploy-vps-bot.yml` actualizado para incluir `secretario/**` en paths trigger + SCP source + post-deploy `systemctl restart secretario-email.service`.

### F. Documentación completa generada (`docs/`)

- `ARCHITECTURE.md` — diagrama de capas (frontend WP / static assets / PHP backend / VPS bot / external APIs)
- `AGENT_REGISTRY.md` + `agent_registry.json` — registro estructurado de los 7 sub-agentes + nuevos componentes pinnacle_form, pinnacle_chat
- `TASK_MATRIX.md` — quién hace qué + handoffs + no-dos
- `COST_OPTIMIZATION.md` — tabla de modelos por operación (Haiku para acks, Sonnet para chat, Opus para análisis)
- `SCALABILITY.md` — multi-tenant architecture para SaaS futuro
- `COMMERCIALIZATION.md` (master index) + 3 sub-docs:
  - `01_pricing_model.md` — tiers Starter/Growth/Pro/Enterprise + perf fee
  - `02_product_packaging.md` — feature matrix + onboarding 60-90d
  - `03_go_to_market.md` — segments + channels + 90-day launch plan + sales playbook

### G. PROTOCOLO DE EJECUCION (no negociable)

`agents/PROTOCOLO_EJECUCION.md` — 7 fases obligatorias para toda operación no trivial:

1. **Context load** — leer memoria, shared_conversation, archivos del módulo
2. **Diagnose before act** — identificar root cause antes de tocar nada
3. **Backup before destructive** — snapshot a `backups/<area>/<fecha>_<accion>/{before,after}/`
4. **Split large tasks** — máximo 300 líneas por archivo / 1 commit lógico
5. **Safe deploy** — draft → preview → publish → purge cache
6. **Verify post-deploy** — fetch URL pública, validar elementos clave
7. **Auto-backup + checkpoints** — cada 15min de trabajo, snapshot de estado

Cargado al inicio de cada sesión junto con `memoria_ALex.md`.

### H. Skills + auto-backup hooks instalados

- 165+ skills community instalados (superpowers + wshobson/agents)
- Hooks PreToolUse/PostToolUse en `.claude/settings.json` para auto-backup en cada Write/Edit
- Session start/stop checkpoints

### I. Credenciales activas (referencia rápida — NO IMPRIMIR)

Almacenadas en `.env.sandbox` (chmod 600, gitignored) y como GitHub Secrets:
- `PINNACLE_WP_USER` / `PINNACLE_WP_APP_PASSWORD` — bridge WP REST API
- `GOOGLE_PLACES_API_KEY` — autocomplete del form
- `GITHUB_SUPER_TOKEN` — gestión de Actions/secrets vía API
- `HOSTINGER_SSH_HOST/PORT/USER/PASSWORD` — SCP deploys
- `ANTHROPIC_API_KEY` — Haiku/Sonnet/Opus
- `TWILIO_*` — SMS OTP
- `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` — alertas

GitHub Secrets actualizados via libsodium sealed boxes (pynacl).

### J. Lecciones grabadas

1. **WAF/ModSecurity bloquea `<script>` en POST a WP**. Solución: deploy JS como archivos estáticos vía SCP, referenciar con `<script src="...">`.
2. **LiteSpeed static cache es independiente del WP cache**. `wp_cache_flush()` no lo purga. Usar `?v=<timestamp>` cache-busting en assets.
3. **DOMContentLoaded order matters** entre core.js + screens.js. Patrón: exponer `INIT` + `SHOW_FIRST` desde core, llamar tras `mountAll` en screens.
4. **HTML `hidden` attribute pierde contra CSS `display:flex`**. Siempre `[hidden] { display:none !important; }` en componentes flex.
5. **`origin/master` vs local `master`** — siempre fetch antes de comparar; local master puede estar décadas atrás.
6. **DNS cache overflow en Hostinger** = throttling transitorio, no error real. Esperar + retry con backoff.

### K. Estado del sitio al cierre de sesión

- ✅ Webform deployado y funcional (E2E verified)
- ✅ Chatbot deployado en TODAS las páginas excepto `/get-my-offer/`
- ✅ Site-wide CTAs apuntando a `/get-my-offer/`
- ✅ Contact page con 5 métodos
- ✅ Email reply bug arreglado en VPS
- ⚠ Cache LiteSpeed posiblemente sirviendo HTML viejo a algunos visitantes — bumpear `?v=` periódicamente y/o purgar via panel Hostinger

**Commits clave de la sesión** (rama `master` / `deploy-pnf`):
- `d635613` feat(pinnacle_public): P1 dedup backend — lookup_existing + reopen_lead_id
- `d1b18c8` feat(pinnacle_form): P2 returning-user flow
- `da7f782` feat(pinnacle_form): P3 localStorage session resume
- `cb34568` feat(pinnacle_form): P4 Fer-Form-Mode brain — empathic micro-acks
- `daaf395` fix(secretario): resolve email reply recipient correctly
- `c0cd1e4` ci(vps-bot): deploy secretario/ + restart secretario-email service
- `d12ab79` feat(wp): redirect site-wide offer CTAs to /get-my-offer/
- `c69ac30` feat(contact-page): 5 contact methods + remove old CF7 form
- `82c15e5` feat(chatbot): Fer-style floating chat widget on all pages
- `23c2a25` fix(chatbot): panel[hidden] needs !important to override display:flex

**Aprobado por:** Jorge Cruz — 2026-04-22
**Documentación de la sesión:** completa, pusheada a GitHub `claude/whats-going-on-LFo6h`.


---

## 2026-04-22 — REGLAS PERMANENTES (aprobadas por Jorge, nunca olvidar)

### R1. SURGICAL EDITS — nunca reescribir archivos enteros
**Regla:** Cuando exista un archivo, usar el tool `Edit` con `old_string`/`new_string` chirúrgicos. NUNCA usar `Write` para sobreescribir completo salvo que el archivo sea nuevo.
Cuando se toca una página o módulo existente, modificar SOLO las líneas necesarias. Nunca regenerar HTML/CSS/PHP completos "mientras estoy ahí".
El riesgo: regenerar pierde cambios anteriores de otras sesiones y crea "regresiones fantasma" donde el Jefe ve formato viejo.

### R2. LUZ VERDE PERMANENTE (Pinnacle public stack)
Jorge otorga autorización permanente para: merge claude→master + push a master + deploy workflow trigger + purge_cache + bump ?v= params, en el contexto del stack público de Pinnacle (form, chatbot, site CTAs, contact page, MU-plugins, bridges).
NO pedir confirmación antes de hacer estos deploys — ejecutar y reportar.
Sigue aplicando pausa obligatoria para: finanzas reales, eliminación irreversible de registros, comunicaciones externas en nombre del Jefe, credenciales.

### R3. VERIFICACIÓN POST-DEPLOY SIEMPRE
Después de cada deploy:
1. Fetch la URL pública con `curl -H "Cache-Control: no-cache"`
2. Confirmar que los elementos clave están presentes (grep por strings distintivos)
3. Si algo no se ve: diagnosticar primero DB vs cache vs browser, NO reescribir preventivamente

### R4. SKILLS — activarlos cuando corresponde
Tener 340+ skills instalados no sirve si no se invocan. Para tareas de código:
- `verification-before-completion` — antes de declarar "listo"
- `simplify` — después de cambios, revisar si se puede simplificar
- `systematic-debugging` — ante cualquier bug o comportamiento inesperado
- `focused-fix` — para fixes quirúrgicos end-to-end

### R5. MODO /GOD — PERMANENTE, TODOS LOS MODELOS, TODOS LOS ENTORNOS
**Orden directa de Jorge, 2026-04-22 — NO NEGOCIABLE.**

ALEX opera SIEMPRE en modo `/GOD`: profesional, eficiente, capaz, cost-benefit optimizado (tokens + tiempo). Antes de cualquier acción no trivial: evaluar qué skill aplica e invocarlo vía el tool `Skill`. Sin excusas. Aplica con cualquier modelo (Opus/Sonnet/Haiku) y en cualquier entorno (Claude Code, Telegram, Claude.ai, sub-agentes).

Tabla de activación automática de skills (extracto — versión completa en `CLAUDE.md` sección "MODO /GOD"):
- Bug / comportamiento inesperado → `systematic-debugging`
- Antes de "listo" → `verification-before-completion`
- Antes de implementar código → `test-driven-development`
- Después de cambiar código → `simplify`
- Multi-paso con spec → `writing-plans` → `executing-plans`
- Creative / diseño → `brainstorming`
- Review de cambios → `code-review-excellence`
- 2+ tareas independientes → `dispatching-parallel-agents`
- Frontend/Backend/DevOps/Security → `senior-frontend`/`senior-backend`/`senior-devops`/`senior-security`

Default en caso de duda: invocar el skill.

**Confirmación obligatoria al inicio de cada sesión:** *"Modo /GOD activo, skills-first habilitado, memoria always-on, luz verde permanente en stack Pinnacle."*

### R6. SKILL DE MEMORIA — SIEMPRE ACTIVO, TODA LA VIDA, SIN EXCUSAS
**Orden directa de Jorge, 2026-04-22 — NO NEGOCIABLE.**

El "skill de memoria" está permanentemente activado en cualquier modelo y cualquier entorno. No hay caso donde se pueda saltar.

**READ al ARRANQUE de TODA sesión:**
1. `memoria_ALex.md`
2. `agents/memoria_alex.md`
3. `agents/shared_conversation.json` (últimos 60 cross-channel)
4. `telegram_bot/telegram_memory.md`
5. `agents/PROTOCOLO_EJECUCION.md`
6. Memorias de sub-agentes cuando corresponda

**WRITE DURANTE la sesión** (inmediato, no esperar al cierre):
- Reglas/lecciones/aprobaciones de Jorge
- Deal analysis learnings
- Credenciales / accesos nuevos
- Bugs + root cause + fix
- Decisiones arquitectónicas
- Commits importantes

**WRITE al CIERRE:**
- Resumen datado en `memoria_ALex.md`
- Espejo en `agents/memoria_alex.md` + `telegram_bot/telegram_memory.md`
- Auto-backup en `backups/session_<fecha>_<hora>/`

**Skill designado:** `self-improving-agent` para curar auto-memory en knowledge durable.

**Cross-environment:** cualquier instancia (Claude Code, Telegram, Claude.ai, sub-agentes) lee las memorias al arrancar → continuidad garantizada entre canales.

**Aprobado por:** Jorge Cruz — 2026-04-22

---

## 2026-04-22 — CIERRE DE SESIÓN (confirmación del Jefe)

Jorge confirma: **"la página y los bots y todo lo demás están perfectos ahora."**

Estado final verificado en producción:
- ✅ Webform `/get-my-offer/` — 18 pantallas, bilingüe, dedup, session resume, Fer brain
- ✅ Chatbot floating — bubble 72px + halo fucsia pulsante + dot fucsia (aprobado visualmente por Jorge)
- ✅ MU-plugin con `filemtime(ABSPATH . ...)` correcto — cache-busting funcional
- ✅ Contact page — Five Ways to Reach Us (Call / Email / Visit / Online Form / Live Chatbot)
- ✅ Site-wide CTAs apuntando a `/get-my-offer/`
- ✅ Email reply bug corregido en VPS
- ✅ Cache LiteSpeed + Hostinger CDN purgados, live sirviendo HTML nuevo

Reglas permanentes grabadas en esta sesión:
- R1 Surgical edits (nunca regenerar archivos completos)
- R2 Luz verde permanente stack Pinnacle
- R3 Verify post-deploy siempre
- R4 Skills activos cuando corresponde
- R5 Modo /GOD permanente todos los modelos/entornos
- R6 Skill de memoria always-on toda la vida

Todo pusheado a `origin/master` + `origin/claude/whats-going-on-LFo6h` + documentación completa en `docs/` + backup en `backups/session_2026-04-22_221627/`.

---

## 2026-04-22 — EMAIL CAPTURE POPUP (nuevo componente, stack público)

Nuevo módulo pinnaclegroupwi.com para crecer lista de emails.

**Trigger:** 5s después del page load.
**Scope:** todas las páginas frontend EXCEPTO `/get-my-offer/` (skip por MU-plugin filter) y mobile <480px (skip por JS).
**Frequency cap:** 30 días cool-down tras dismiss + jamás reaparece si ya se suscribió (`localStorage.pnf_popup_subscribed=1`).

**Archivos:**
- `hostinger/agents/pinnacle_popup/pinnacle_popup.css` — modal centrado + backdrop blur + brand colors
- `hostinger/agents/pinnacle_popup/pinnacle_popup.js` — timer, bilingüe EN/ES, honeypot + elapsed_ms, success state
- `hostinger/mu-plugins/pinnacle-popup-loader.php` — enqueue con `filemtime(ABSPATH . 'agents/pinnacle_popup/...')` para cache-busting
- `hostinger/agents/pinnacle_public.php` — nueva action **`subscribe_email`** con:
  - Anti-spam: honeypot + elapsed_ms >= 1200ms
  - Email validation via `pp_email_valid()`
  - Rate limit: 5 subs/hora por IP
  - Airtable Contacts insert (fields: `Email1` + `Full Name="Newsletter Subscriber"` + `Notes=source/lang/date`, con typecast:true). Fallback sin `Notes` si field no existe.
  - Telegram notification a Jorge con email + lang + source
  - Siempre devuelve `{ok:true}` (bots obtienen fake-success para no aprender)

**Copy EN:** "Want a head start on the next deal?" + "Off-market opportunities + Wisconsin market insights, twice a month. No spam — unsubscribe anytime." + CTA "Send Me Deals" + decline "No thanks"
**Copy ES:** "¿Quieres adelantarte al próximo deal?" + "Oportunidades off-market + análisis del mercado de Wisconsin, dos veces al mes. Sin spam — cancela cuando quieras." + CTA "Envíenme Deals" + decline "No, gracias"

**Verificado en producción:**
- ✅ Home page enqueue tags con `?ver=1.0.1776902039`
- ✅ `/get-my-offer/` excluido (0 matches)
- ✅ Bot submission (elapsed_ms=100) devuelve fake-success (no crea record)
- ✅ Submission legítima (elapsed_ms=5000) crea record en Airtable Contacts + notifica Telegram
- ✅ Email inválido rechazado con `{ok:false, error:"invalid_email"}`
- ✅ Test records de QA limpiados de Airtable

**Skills invocados:** `popup-cro` (UX pattern + copy + anti-annoyance rules).

### Fix de audiencia 2026-04-23 — popup reorientado a homeowners

Copy inicial habló al público equivocado (inversionistas: "off-market deals"). Pinnacle compra a homeowners en distress, no vende deals a investors. Jorge detectó el error — audiencia y nicho mal planteados.

**Re-framing correcto:**
- Audiencia: homeowners Wisconsin con situaciones de presión (pre-foreclosure, probate, herencia, taxes atrasados, mudanza, rental cansado)
- Propuesta: lead magnet educativo (guías gratis + pros/cons de cada opción de venta + market updates mensuales)
- Efecto esperado: mayor opt-in + lista caliente de homeowners investigando → warm pipeline hacia `/get-my-offer/`

**Copy final EN:** Badge "Wisconsin Homeowners" | Headline "Thinking about selling? Know your options first." | Subhead sobre foreclosure/inherited/back taxes/fast sale + monthly Wisconsin market updates | CTA "Send Me Free Guides"
**Copy final ES:** Badge "Dueños de Casa en Wisconsin" | Headline "¿Pensando en vender? Conoce tus opciones primero." | Subhead equivalente | CTA "Recibir Guías Gratis"

**Lección:** ante cualquier componente de marketing, validar AUDIENCIA (quién) y PROPUESTA DE VALOR (qué obtiene) antes de escribir copy. El error fue asumir "deals" = lenguaje universal — en real estate, "deals" pertenece al lado investor, no al lado homeowner.

**Pendiente operacional para Jorge:** el popup promete "first guide" en el success message. Hace falta configurar un email real (Mailchimp/Beehiiv/Convertkit) que mande la guía de bienvenida automáticamente cuando llega un nuevo subscriber a Airtable Contacts. Sin eso, el promise queda sin cumplir.

### Debug pattern 2026-04-23 — URL bypass para testing del popup

Jorge reportó "no está funcionando". Diagnóstico systematic-debugging:
- L1–L6 server-side todos ✓ (tags emitidos, archivos 200, JS parsea, byte-idéntico, gates intactas)
- Root cause: localStorage gate en su browser (`pnp_popup_shown` con timestamp <30d tras dismissal previo) → el IIFE hace silent return en línea 20.

**Fix aplicado:** añadido URL override `?pnp_force=1` en `pinnacle_popup.js`. Cuando la URL contiene ese query param:
1. Se saltan las 3 gates (subscribed / cooldown / mobile-width)
2. El trigger se reduce a 500ms (vs 5000ms normal) para preview rápido

**Uso:** `https://pinnaclegroupwi.com/?pnp_force=1` (o cualquier URL del sitio con `?pnp_force=1`). Regular visitors no afectados — la lógica anti-annoyance sigue para ellos.

**Lección:** toda pieza de UI con gating client-side debe tener un URL bypass para QA/preview. El 30d cooldown es correcto para usuarios reales, pero sin escape hatch el propio dueño queda atrapado tras el primer dismiss.

### Fix de audiencia 2026-04-23 — popup también en mobile

Jorge reportó por segunda vez "popup no aparece". Systematic-debugging confirmó:
- Server 100% limpio (L1–L5 verificados)
- `?pnp_force=1` funciona en móvil ✓
- Incógnito en móvil sin `?pnp_force=1` → no aparece

**Root cause:** yo mismo había puesto `if (window.innerWidth < 480) return` como "anti-annoyance on phones" siguiendo convención CRO genérica. Pero para Pinnacle Holdings (real estate lead capture) el mobile traffic es mayoría — homeowners buscan "sell my house fast Wisconsin" desde el celular. Excluir mobile = perder 60–70% del lead flow potencial.

**Fix:** removí la línea `if (window.innerWidth < MOBILE_THRESHOLD) return`. El CSS ya tenía `@media (max-width:480px)` que adapta el modal a full-width en celular, así que la experiencia estaba lista — solo faltaba dejarlo aparecer.

**Lección PERMANENTE:** NO agregar exclusiones de audiencia unilateralmente (por "mejor práctica genérica") sin validar con el Jefe. Lo que es best practice para un blog SaaS no es best practice para real estate. Siempre preguntar: "¿dónde vive tu audiencia?" antes de filtrar por viewport, device, región, o cualquier otro eje. Para Pinnacle: mobile-first, nunca mobile-excluded.

### 2026-04-23 — Herramienta instalada: Open Carrusel (Instagram carousel builder)

Jorge ordenó instalar `Hainrixz/open-carrusel` de GitHub. No es un skill `~/.claude/skills/` global — es un proyecto Next.js standalone que se lanza desde su propio directorio con Claude Code.

**Ubicación:** `/home/user/open-carrusel/`
**Licencia:** MIT | **Stack:** Next.js 16 + React 19 + TypeScript 5 + Tailwind v4 + Puppeteer
**Diseño:** local-first (todo corre en la máquina, solo llama a Anthropic API vía Claude Code)
**Output:** slides HTML/CSS generadas por Claude → screenshots PNG 1080×1350 (Instagram feed).

**Slash commands (scoped a ese repo):**
- `/start` — bootstrapa setup, arranca dev server, abre browser
- `/stop` — detiene dev server
- `/reset` — resetea data/uploads
- `/doctor` — diagnóstico de entorno

**Flujo de uso:**
```bash
cd /home/user/open-carrusel
claude
# dentro de Claude Code:
/start
```
Esto abre el browser en http://localhost:3000 con el builder. Conversás con Claude, te genera slides, exportás PNGs.

**Uso para Pinnacle:** alimenta el pipeline Social Media (FASE 2 El Creativo) con carruseles Instagram brandeados. Output 1080×1350 coincide con el aspect ratio que ya usamos.

### 2026-04-23 — PHASE 1 → PHASE 2 + ELIMINAR BLOTATO COMPLETO

**Orden directa de Jorge:**

**Phase 1 (casi cerrada):** CRM (Airtable) + Website público Pinnacle (webform, chatbot, contact 5-ways, email-capture popup) + Tools internas (Fer SMS, Tracy skip tracer, secretario email, el_polling, el_chismoso, fer_* crons). **Estado:** operativo en producción.

**Phase 2 (arrancando ahora):** Publicidad + promoción del sitio + servicios Pinnacle. Social media, paid ads, growth, lead generation channels. Jorge va a buscar y instalar nuevos skills dedicados a esta fase.

**Blotato — eliminación completa:**
- Ya no sirve para los intereses de Pinnacle
- Hay que sacarlo del stack por completo, no solo de FASE 2 (visual gen) sino también de FASE 3 (publishing a FB/IG)
- Reemplazo para publishing: opciones a evaluar cuando Jorge instale skills de Phase 2 — candidatos: Meta Graph API directo, Buffer, Later, Publer, Metricool, open-source alternatives

**Estado del trabajo de El Creativo v6 (parcial — PAUSADO):**
- ✅ `agents/creativo_runner/themes.mjs` — 5 temas T1-T5 + slide builders (hook/point/CTA) mobile-first 1080×1350. Reutilizable.
- ⏸ `pinnacle_setup.mjs` (setup brand) — NO escrito todavía
- ⏸ `creativo_runner.mjs` (orchestrator) — NO escrito todavía
- ⏸ Rewrite `agents/creativo.md` a v6 — NO hecho todavía
- **Razón del pause:** si eliminamos Blotato completo, el diseño correcto del pipeline cambia. Mejor esperar Phase 2 skills + redefinir arquitectura end-to-end (generación + publicación) antes de invertir más horas. Los themes.mjs quedan como activo reusable independiente de la decisión arquitectural.

**Aprobado por:** Jorge Cruz — 2026-04-23

### R7. MOBILE-FIRST — PRIORIDAD #1 PERMANENTE (todos los proyectos, todos los modelos, todos los entornos)
**Orden directa de Jorge, 2026-04-23 — NO NEGOCIABLE.**

Todo el trabajo que hagamos debe estar optimizado para móviles como **prioridad número 1**. El mobile traffic es la mayoría del tráfico web hoy; cualquier decisión de diseño, UX, copy, código o arquitectura debe considerar mobile PRIMERO, desktop después.

**Aplica a (no exhaustivo):**
- Popups / modals / overlays
- Formularios (webform `/get-my-offer/`, contact forms, signup, etc.)
- Páginas del sitio (home, about, services, contact, FAQ, legal)
- Chatbot flotante
- Emails (templates + imágenes responsivas)
- Social media creatives (carrousels 1080x1350 vertical, reels 9:16)
- CTAs, botones, imágenes (tap targets ≥ 44px, thumbs-zone friendly)
- Cualquier componente nuevo

**Reglas operativas:**
1. **NUNCA** excluir mobile por viewport width sin consultar al Jefe
2. **Diseñar mobile-first**: CSS base para mobile, media queries para desktop (no al revés)
3. **Tap targets ≥ 44px**, padding generoso, no hover-dependent UX
4. **Test en mobile viewport primero**, desktop después
5. **Performance-first en mobile**: imágenes optimizadas, lazy-load, mínimo JS bloqueante
6. **Cuando diagnostique algo que "no funciona"**: probar en mobile viewport antes que en desktop

**Skills recomendados para esta regla:**
- `responsive-design` — layouts fluidos + container queries
- `mobile-ios-design` — iOS HIG (Safari iPhone es dominante en Wisconsin)
- `mobile-android-design` — Material Design
- `accessibility-compliance` — WCAG 2.2 mobile a11y patterns

**Aprobado por:** Jorge Cruz — 2026-04-23

### 2026-04-23 — Herramienta instalada: MiroFish CLI (multi-agent prediction engine)

Jorge ordenó instalar MiroFish para apoyar Phase 2 (publicidad + promoción). Es motor de simulación multi-agente: toma documentos fuente (PDF/MD/TXT) → construye grafo de conocimiento → genera personas AI → simula reacciones en redes (Twitter/Reddit) → produce reporte de predicción.

**Ubicación:** `/home/user/mirofish-cli/`
**Repo:** `amadad/mirofish-cli` (fork en inglés + soporte Claude CLI de `666ghj/MiroFish` original)
**Licencia:** **AGPL-3.0** — copyleft viral: si se modifica y se ofrece como servicio, las modificaciones deben ser open-source. **Estrategia:** usar como tool externo sin modificar el source. Nuestro wrapper de integración queda libre para licencia privada.
**Stack:** Python 3.11-3.12 + uv (package manager). Heavy deps (PyTorch, transformers, unstructured). `uv sync` completó OK.

**Configuración .env:**
```
LLM_PROVIDER=claude-cli
```

**Bug resuelto:** MiroFish busca binario literal `claude-cli` vía `shutil.which("claude-cli")`, pero el binario real de Claude Code se llama `claude`. Fix: symlink `/root/.local/bin/claude-cli → /opt/node22/bin/claude`. `mirofish doctor` pasa todos los checks.

**CLI comandos:**
- `uv run mirofish run --files <archivos> --requirement "<pregunta>" [--platform parallel|twitter|reddit] [--max-rounds N] [--json]`
- `uv run mirofish runs list --json`
- `uv run mirofish runs status <run_id>`
- `uv run mirofish runs export <run_id>`
- `uv run mirofish doctor`

**Casos de uso Pinnacle para Phase 2 (publicidad + promoción):**

1. **Pre-flight de ad copy:** alimentar draft de ad → simular reacción de audiencia homeowners WI → decidir qué va a Meta Ads antes de gastar $
2. **A/B test de popup/email subject:** 2 variantes → simular → elegir ganador sin tráfico real
3. **Validación de copy del webform:** simular reactions de personas en distress (foreclosure / probate / relocation) → detectar qué les traba
4. **Stress-test de Fer-bot:** generar mensajes típicos de homeowners → ver cómo Fer responde antes de producción
5. **Análisis competitivo:** cargar contenido de competidores locales WI → simular reacción de NUESTRA audiencia → encontrar gaps
6. **Escenarios de mercado:** nuevo evento (Fed rate cut, política fiscal WI) → simular impacto en intent-to-sell → ajustar messaging

**Estrategia SaaS (per R8):** MiroFish queda como **dependencia externa use-as-is**. Nuestro wrapper (cuando se escriba) le pasa tenant config + input files, recibe JSON report, lo presenta en nuestro UI. Cliente ve "Campaign Simulator" como feature premium. No distribuimos MiroFish modificado (evita AGPL).

**Pendiente:** primer smoke test con escenario Pinnacle real (ej: simular reacción al popup copy "Thinking about selling? Know your options first."). Jorge decide cuándo arrancamos.

**SMOKE TEST 2026-04-23 — FALLIDO, diagnóstico:**
- Input: `popup_copy.md` + `wi_homeowner_persona.md` (7KB texto total) + requirement detallado
- Ontology generation: ✅ excelente (10 entity types: DistressedHomeowner, TiredLandlord, CashHomeBuyer, RealEstateAgent, Attorney, Lender, GovernmentAgency, ConsumerAdvocate, Person, Organization + 10 edge types relevantes a Wisconsin real estate)
- **Graph extraction: 0 nodos / 0 edges de 22 chunks** — el LLM subprocess no extrajo nada. Task reportó "completed" sin error, pero resultado vacío.
- Sim step falló: "Simulation is not ready, current status: failed"
- **Root cause probable:** MiroFish spawnea `claude-cli` como subprocess. Cuando lo corremos DESDE DENTRO de una sesión Claude Code (como hoy), hay nesting de Claude CLI que falla silenciosamente (auth conflicts / rate limit / stdin-stdout pipe issues).
- **Implicación arquitectural:** El Oráculo **NO puede correrse como sub-agente dentro de una sesión Claude Code**. Debe deployarse como proceso standalone en Hostinger/VPS con cron + webhook, o llamarse desde un entorno limpio (terminal dedicada, script cron).
- **Plan ajustado:** cuando construyamos El Oráculo, lo deployamos en VPS (como `secretario-email.service`), no como sub-agente inline.

### 2026-04-23 — Queue de investigación de skills (Phase 2)

Jorge pidió investigar/evaluar/ejecutar en secuencia:
1. **Marketing skills** (en curso)
2. **Claude SEO** (siguiente)
3. **Claude ADS** (después)

Protocolo por cada uno: investigar GitHub → evaluar con lente **Phase 2 ads/promo + R8 SaaS-ready + R7 mobile-first** → instalar si fit claro → grabar en memoria → reportar.

**Pendiente de Jorge** (NO bloquea la queue, pero importante resolver):
- 3 decisiones sobre arquitectura de El Oráculo (pipeline paralelo / opt-in gate / primera prueba popup-or-carrusel). Cuando responda, seguimos con wiring de El Oráculo.

### 2026-04-23 — Marketing skill instalado: ai-marketing-claude

Jorge aprobó proceder con la queue de Phase 2. Primer item: marketing skills.

**Evaluados 3 candidatos:**
- `OpenClaudia/openclaudia-skills` (62+ skills, necesita API keys externas para valor pleno)
- `kostja94/marketing-skills` (160+ skills puros MD, sin orquestación)
- `zubair-trabzada/ai-marketing-claude` (15 skills + 5 parallel subagents + PDF reports) ← **Seleccionado**

**Razones de la selección (vía R8 SaaS-ready + R7 mobile-first + Phase 2 objetivos):**
1. **Client-ready PDF reports** = deliverable vendible (audits tipo "/market audit URL" → PDF branded)
2. **Parallel subagents** = cost-efficient, mismo patrón que Scout/Matemático/Fact-Checker
3. **15 skills focused** > 160 dispersos (menos ruido, easier integration)
4. **MIT license** ✓
5. Installer auditado: git clone + file copy + dep check (sin exec raro)

**Instalado en:**
- `/root/.claude/skills/market/` + 14 `market-*` skills individuales
- `/root/.claude/agents/` — 5 parallel agents: `market-content`, `market-conversion`, `market-competitive`, `market-technical`, `market-strategy`
- Scripts: `analyze_page.py`, `competitor_scanner.py`, `social_calendar.py`, `generate_pdf_report.py`
- Python deps: `reportlab 4.4.10` + `pillow 12.2.0` (añadidos post-install porque el check del installer mintió)

**15 slash commands disponibles:**
```
/market audit <url>        Full marketing audit (5 parallel agents → PDF)
/market quick <url>        60s snapshot
/market copy <url>         Copy generation
/market emails <topic>     Email sequences
/market social <topic>     Content calendar
/market ads <url>          Ad creative + copy
/market funnel <url>       Sales funnel analysis
/market competitors <url>  Competitive intel
/market landing <url>      Landing page CRO
/market launch <product>   Launch playbook
/market proposal <client>  Client proposal generator
/market report <url>       Markdown report
/market report-pdf <url>   PDF report (requires reportlab ✓)
/market seo <url>          SEO audit
/market brand <url>        Brand voice analysis
```

**Uso inmediato para Pinnacle + SaaS:**
- `/market audit pinnaclegroupwi.com` → PDF audit propio, validar si el sitio está optimizado
- `/market audit <competitor>` → inteligencia competitiva en WI
- `/market proposal <prospecto>` → generar propuestas cuando empecemos a vender el sistema a otros investors
- `/market social "Wisconsin foreclosure tips"` → alimentar pipeline SM

**Repo:** `zubair-trabzada/ai-marketing-claude`
**Status queue:** Marketing ✅ — Siguiente: Claude SEO (buscar especialista SEO para complementar `/market seo` del suite general)

### 2026-04-23 — WhatsApp AgentKit: clonado + PAUSADO por Jorge

Repo `onehundredfortyfive-southernbaptist487/whatsapp-agentkit` clonado en `/home/user/whatsapp-agentkit/`. Licencia MIT, built for LATAM. Providers soportados: Whapi.cloud / Meta Cloud API / Twilio.

**NO ejecuté `/build-agent`** — Jorge pausó para retomar después.

**3 decisiones arquitecturales pendientes** (bloquean el build cuando retomemos):
1. **Camino A** (nuevo sub-agente WhatsApp) vs **Camino B** (extender Fer con canal WhatsApp). Mi recomendación fue B — una sola voz, contact unification, mejor SaaS bundle.
2. **Si A:** nombre del sub-agente (propuestas: Isa / El Conversador / El Embajador).
3. **Provider para arrancar:** Whapi.cloud (sandbox gratis) / Meta Cloud API (pro pero verificación Meta) / Twilio (intermedio).

Ya analicé todo, cuando Jorge elija las 3 respuestas ejecuto en ~30 min (Camino A) o ~1-2 días (Camino B).

### 2026-04-23 — Estado global de la queue Phase 2 (snapshot operativo)

| Item | Estado | Pendiente |
|---|---|---|
| MiroFish skill | ✅ instalado + doctor OK | El Oráculo sub-agent: deferred a deploy VPS standalone (falla en nesting Claude CLI inline) |
| ai-marketing-claude skill | ✅ instalado + 14 skills + 5 parallel subagents + reportlab OK | El Mercader sub-agent: pendiente build (cron + Airtable + Telegram) |
| WhatsApp AgentKit | ✅ clonado | **PAUSADO POR JORGE**, 3 decisiones arquitecturales pendientes |
| Claude SEO skill | ⏳ no buscado | Queue: buscar + evaluar + instalar + build El Posicionador |
| Claude ADS skill | ⏳ no buscado | Queue: buscar + evaluar + instalar + build El Cazador |

**Decisiones de Jorge pendientes** (bloquean construcción):
1. Oráculo — ¿deployamos en VPS o pospone hasta Phase 3? (arquitectura decidida, falta luz verde al wiring)
2. WhatsApp — 3 decisiones (Camino A/B, nombre si A, provider)
3. Mercader — luz verde para arrancar build (skill listo, falta el orchestrator)

**Próximo paso natural** (si Jorge lo habilita): continuar la queue con **Claude SEO** — buscar skill, evaluar, instalar, diseñar El Posicionador.

### 2026-04-23 — SEO + ADS + skill-creator instalados

**SEO — `AgriciDaniel/claude-seo` v1.9.0** (MIT ✓)
- Ubicación: `/root/.claude/skills/seo/` + sub-skills en `/root/.claude/skills/seo-*/`
- 20+ slash commands: `/seo audit`, `/seo page`, `/seo technical`, `/seo geo` (AI Overviews + ChatGPT search + Perplexity), `/seo content`, `/seo schema`, `/seo local`, `/seo maps` (crítico para Pinnacle WI), `/seo images`, `/seo sitemap`, `/seo hreflang`, `/seo backlinks`, `/seo ecommerce`, `/seo drift`, `/seo google` (Search Console + PageSpeed), `/seo dataforseo`, `/seo firecrawl`, `/seo image-gen`, `/seo cluster`, `/seo plan`, `/seo sxo`, `/seo competitor-pages`, `/seo programmatic`
- Python 3.11 ✓ + Playwright (opcional) + venv propio en `/root/.claude/skills/seo/.venv`
- MCP servers opcionales (DataForSEO, Firecrawl, Banana) para live data
- Google APIs opcionales (PageSpeed, GSC, GA4, CrUX)

**ADS — `AgriciDaniel/claude-ads` v1.5.1** (MIT ✓)
- Ubicación: `/root/.claude/skills/ads/` + sub-skills en `/root/.claude/skills/ads-*/`
- 20+ slash commands: `/ads audit`, `/ads plan <industry>`, `/ads google` (80 checks), `/ads meta` (50 checks), `/ads youtube`, `/ads linkedin`, `/ads tiktok`, `/ads microsoft`, `/ads apple`, `/ads creative`, `/ads landing`, `/ads budget`, `/ads competitor`, `/ads math`, `/ads test`, `/ads plan`, `/ads dna`, `/ads generate` (requires banana-claude), `/ads photoshoot`, `/ads create`
- **12 industry templates INCLUYE real-estate** ← perfecto para Pinnacle + SaaS a otros investors
- 6 audit subagents + 4 creative subagents
- 25 RAG reference files
- Local-first: NO envía data externamente sin configuración explícita de MCP
- Trabaja con exports/screenshots de dashboards — no necesita login a cuentas de ads para empezar

**skill-creator — `anthropics/skills`** (oficial Anthropic)
- Ubicación: `/root/.claude/skills/skill-creator/` (248KB)
- Propósito: crear, editar, optimizar skills propios con el estándar oficial de Anthropic
- Incluye: SKILL.md + agents/ + scripts/ + eval-viewer/ + references/ + assets/
- Uso para Pinnacle: construir El Oráculo, El Mercader, El Posicionador, El Cazador como skills formales con evals cuantitativos antes de producción
- Workflow: draft → test prompts → eval results → iterate → benchmark

**Complementariedad del trío SEO + ADS + skill-creator:**
- **SEO + ADS del mismo autor** = arquitectura unificada. Cross-reference: `/seo competitor-pages` feeds `/ads competitor`, `/seo plan` feeds `/ads plan`, `/seo content` drives copy for `/ads copy`.
- **skill-creator oficial** = plantilla estándar para wrappear SEO + ADS en sub-agentes tenant-aware (El Posicionador, El Cazador) cuando pasemos a construcción.

**R9 siguiente paso** (cuando Jorge habilite): usar `skill-creator` para armar formalmente:
- **El Oráculo** (cuando destrabemos VPS deploy) — wrapper de MiroFish
- **El Mercader** — wrapper de `/market audit` + cron semanal → Airtable + Telegram
- **El Posicionador** — wrapper de `/seo audit` + cron cada 3 días → Airtable + Telegram
- **El Cazador** — wrapper de `/ads audit` + cron diario → Airtable + Telegram

### 2026-04-23 — El Mercader v1 DRAFT COMPLETO (primer sub-agente R9)

**Archivos shipped** (10KB total, zero runtime deps):

| Archivo | Rol |
|---|---|
| `agents/tenants/_template.json` | Template tenant config R8 (copiás → llenás para cada cliente nuevo, NO código change) |
| `agents/tenants/pinnacle.json` | Tenant zero: Pinnacle Holdings. `website`, `brand`, `competitors` (3 cash-buyers WI), `schedules` (cada 3 días + semanal), `airtable.base_id=appU9s3kGkVpdrJkw`, `alert_thresholds` (crit 50 / warn 70) |
| `agents/mercader/SKILL.md` | Anthropic skill-creator format: frontmatter + workflow. Identity + 3 modes (quick_health / deep_audit / on_demand) + Airtable schema + security rules |
| `agents/mercader/mercader.mjs` | Node orchestrator (ejecutable, chmod +x). Lee tenant JSON → spawns `claude --print` subprocess → parsea output (score, issues, wins, recs) → escribe Airtable → envía Telegram. Soporta `--dry-run` para preview sin tokens |
| `agents/mercader/README.md` | Deploy guide + known limitation (nested Claude CLI) + adding-new-tenant recipe |

**Verificación (per `verification-before-completion`):**
- ✅ `node --check` limpio
- ✅ Dry-run quick_health produce prompt correcto con URL Pinnacle + skill `market-quick`
- ✅ Dry-run deep_audit produce prompt con 3 competitors interpoados + report template
- ✅ Zero npm deps (Node 22 fetch + JSON native)

**3 approvals pendientes de Jorge antes de pasar a producción:**
1. **Airtable table:** crear `Marketing_Audits` en base `appU9s3kGkVpdrJkw` con el schema descrito en `SKILL.md` (run_id, tenant_id, audit_type, status, score, top_issues, top_wins, recommendations, summary_md, report_url, tokens_used, etc.). Pegar `table_id` en `pinnacle.json.airtable.table_id`.
2. **Host del cron:** Hostinger PHP cron wrapper (simple, mismo patrón que `fer_seguimiento`) OR VPS service (más control). Pendiente decisión arquitectural.
3. **Auth `claude` CLI** en el host elegido (`claude login`). Sin auth el subprocess falla igual que El Oráculo.

**Limitación conocida:** Nested Claude CLI (correr El Mercader desde dentro de una sesión ALEX Claude Code) falla silenciosamente, mismo issue que MiroFish. Solución: correr desde terminal limpia, VPS cron, o Hostinger cron.

**SaaS-ready (R8):** 100% tenant-aware. Agregar un segundo cliente = `cp _template.json acme.json` + llenar valores + `node mercader.mjs --tenant acme --mode quick_health`. Cero código nuevo.

**Próximos R9:** mismo patrón para El Posicionador (usa `/seo audit`), El Cazador (usa `/ads audit`), El Oráculo (usa MiroFish CLI).

### 2026-04-23 — El Posicionador v1 DRAFT COMPLETO (segundo sub-agente R9)

**Especificación final Jorge (orden directa 2026-04-23):**
- **Objetivo operativo:** posicionar TODAS las páginas del tenant en #1 en TODOS los motores (Google + Bing + DuckDuckGo + Brave + ChatGPT Search + Perplexity + AI Overviews + Google SGE — la lista está en `tenant.search_engines[]` para que el tenant la ajuste)
- **Cadencia:** cada 3 días (modo `seo_health` — amplio pero lightweight) + semanal lunes (modo `seo_deep` — reporte client-ready)
- **Prioridad PRIMARIA:** local SEO state-wide Wisconsin (15 ciudades top, no solo Milwaukee)
- **Prioridad SECUNDARIA:** regional US desde estados vecinos (IL, MN, IA, MI) — peso 25%
- **Mobile-first (R7):** Core Web Vitals móviles + mobile rank = señal primaria

**Archivos shipped:**
- `agents/posicionador/SKILL.md` — Anthropic frontmatter + Identity + Objetivo operativo + 3 modes + Airtable schema SEO_Audits
- `agents/posicionador/posicionador.mjs` — Node orchestrator (chmod +x). Soporta `--mode seo_health|seo_deep|on_demand` + `--dry-run`
- `agents/posicionador/README.md` — deploy guide
- `agents/tenants/pinnacle.json` expandido con:
  - `markets[].cities_primary` = 15 ciudades top WI
  - `regional_scope` = {primary: WI, secondary: [MN,IL,IA,MI], weights 0.75/0.25}
  - `search_engines` = [google, bing, duckduckgo, brave, chatgpt-search, perplexity, ai-overviews, google-sge]
  - `seo_goals` = {per_page_target_rank: 1, primary_priority, secondary_priority}
  - `airtable.seo_table_id` — campo separado para no colisionar con Mercader's `table_id`
- `agents/tenants/_template.json` — mismas extensiones para R8 consistency

**Verificación:** `node --check` OK + dry-run `seo_health` y `seo_deep` producen prompts correctos con state-wide cities + multi-engine + per-page target.

**Prompts generados (muestra):**
- `seo_health` prompt: 53 líneas — incluye inventario sitemap, rank probe de top 10 pages en 8 engines, mobile CWV check, local health WI primario
- `seo_deep` prompt: 100+ líneas — pipeline completo `/seo sitemap → audit → technical → local → maps → content → drift → per-page rank probe → schema → competitor gaps`, con tabla Markdown de rank inventory por engine, geo-grid 15 ciudades WI, regional US check

**Airtable schema SEO_Audits extendido** (vs Marketing_Audits de Mercader):
- `technical_score`, `local_score`, `content_score` (sub-scores dedicados)
- `mobile_cwv` (LCP/CLS/INP con PASS/WARN/FAIL)
- `local_ranks` (rank per ciudad)
- `competitor_gaps`, `schema_coverage`, `score_delta` (drift)

**Airtable separation R8:** tenant JSON ahora soporta `table_id` (Mercader), `seo_table_id` (Posicionador), `ads_table_id` (Cazador future), `oracle_table_id` (Oraculo future). Cada sub-agente escribe a su tabla dedicada. Si falta, fallback al `table_id` genérico.

**3 approvals pendientes para producción (mismo set que Mercader):**
1. Crear tabla `SEO_Audits` en Airtable base `appU9s3kGkVpdrJkw` → pegar `table_id` en `pinnacle.json.airtable.seo_table_id`
2. Host del cron (Hostinger PHP o VPS) — compartido con Mercader
3. `claude login` en el host

**Estado plantel R9 al cierre 2026-04-23:**
- El Oráculo — skill ✅, sub-agente diferido a VPS
- **El Mercader v1 DRAFT** ✅ — pending approvals
- **El Posicionador v1 DRAFT** ✅ — pending approvals
- El Cazador — skill ✅, sub-agente por construir (mismo patrón)

**Nota de refactor:** `mercader.mjs` y `posicionador.mjs` comparten ~80% del código (parseArgs / loadTenant / runClaude / airtableUpsert / telegramSend). Cuando construyamos El Cazador, tendremos 3 instancias del mismo patrón — momento ideal para extraer a `agents/_shared/runner.mjs` y dejar cada sub-agente como thin wrapper con solo `buildPrompt()` + `parseAudit()` específicos. Deferred hasta entonces (R4 cost-benefit: no abstraer con 2 instancias).

### 2026-04-23 — El Escriba v1 DRAFT (sub-sub-agente bajo El Posicionador)

**Jerarquía establecida:** primer caso de agente con dependencia vertical.
```
El Posicionador (SEO monitor) cada 3d + semanal
    └── El Escriba (content writer) semanal + on-demand
```
El Posicionador identifica QUÉ falta. El Escriba escribe QUÉ llena el hueco.

**Archivos shipped:**
- `agents/escriba/SKILL.md` — Anthropic frontmatter + Identity + Jerarquía + 4 modes + Content_Queue schema
- `agents/escriba/escriba.mjs` — Node orchestrator (chmod +x). Lee Airtable SEO_Audits para context, invoca claude CLI, escribe Content_Queue
- `agents/escriba/README.md` — deploy guide + cron + token cost estimate + workflow end-to-end

**4 modos:**
1. **`atp_mine`** (mensual día 1) — genera 50-100 preguntas ATP-style desde `atp_mining.seed_queries`. Default: claude_knowledge. Fallback opcional: gstack `/browse` sobre ATP real
2. **`plan_week`** (lunes post-Posicionador) — lee último SEO_Audit + ATP questions → calendario semanal de `articles_per_week` (default 3)
3. **`draft_article`** (mar-jue) — toma artículo status=Planned → draft completo EN+ES + metadata + schema JSON-LD + internal links + external citations. Opcional: publish a WP como status=draft via bridge
4. **`on_demand`** — ALEX pasa --title + --target-keyword directo, sin pasar por plan

**Token cost/tenant/mes:** ~240-340K tokens = $2.40-3.40. Billing hook limpio para R8 SaaS.

**Extensiones a pinnacle.json + _template.json:**
- `airtable.content_queue_table_id` — tabla dedicada Content_Queue
- `content_goals` object: articles_per_week, word_count range, tone, languages, topic_pillars (8 para Pinnacle), content_types + weights, backlink_strategy, atp_mining config, publish_to_wordpress flag
- `skills.content_plan_week` / `content_draft_article` / `content_atp_mine` — qué skills activa cada modo

**Verificación:** node --check OK + dry-run `plan_week` genera prompt correcto con 8 pillars Pinnacle + 15 ciudades WI + mix EN/ES + token budget.

**3 approvals pendientes:**
1. Crear tabla `Content_Queue` en Airtable (schema en SKILL.md) → pegar `content_queue_table_id` en tenant JSON
2. Host cron (compartido con Mercader + Posicionador)
3. Decidir flow publicación: auto-draft a WP via `pinnacle_wp_bridge.php create_post` OR review-first-en-Airtable-humano-aprueba-después

**Estado plantel R9 al cierre:**
- El Oráculo — skill ✅, sub-agente diferido VPS
- El Mercader v1 DRAFT ✅
- El Posicionador v1 DRAFT ✅
- **El Escriba v1 DRAFT ✅ (sub-sub-agente bajo Posicionador)**
- El Cazador — skill ✅, sub-agente por construir

**Patrón compartido confirmado:** Mercader + Posicionador + Escriba comparten ~75% del runtime (parseArgs / loadTenant / runClaude / airtableUpsert / telegramSend). Refactor a `agents/_shared/runner.mjs` se ejecuta cuando sumemos Cazador (4 instancias = ROI claro del abstract).

### 2026-04-23 — Tramo final del día: maps_deep + fer_review_request + MCP builders + NotebookLM MCP + El Cartógrafo scaffold

**Google Maps improvements shipped:**
- `agents/posicionador/posicionador.mjs` — nuevo modo `maps_deep` (READ-only audit dedicado GBP + NAP + geo-grid + reviews + posts + Q&A + photos). Cadencia cada 3 días. Dry-run verificado.
- `hostinger/tools/fer_review_request.php` — cron diario que manda SMS bilingüe post-Closed-Won pidiendo review Google, con follow-up 7 días. **Pending:** Jorge pasa el GBP PLACE_ID para llenar `GBP_REVIEW_URL` + crear campos Airtable Deals (`review_request_sent`, `review_request_sent_at`, `review_followup_sent`, `review_followup_sent_at`, `review_received`).

**MCP toolchain instalado:**
- `mcp-builder` (ComposioHQ) — `/root/.claude/skills/mcp-builder/`, complementa el `mcp-server-builder` del superpowers pack. Guía para construir MCP servers custom.
- `notebooklm-mcp` (alfredang) — `/home/user/notebooklm-mcp/`, uv sync completo, FastMCP listo. **Pending Jorge (desde su laptop con Chrome):**
  1. `cd /home/user/notebooklm-mcp && uv run notebooklm login` (abre Chrome, auth con Google)
  2. `claude mcp add notebooklm -- uv --directory /home/user/notebooklm-mcp run python server.py`
  3. Restart Claude Code → el MCP expone 16 tools: `create_notebook`, `add_source_url`, `ask_notebook`, `generate_audio_overview`, `generate_video_overview`, `generate_slide_deck`, `generate_mind_map`, `generate_infographic`, `generate_quiz`, `generate_flashcards`, `generate_summary_report`, `generate_data_table`, etc.

**El Cartógrafo v1 SCAFFOLD (GMB write-side agent):**
- `agents/cartografo/SKILL.md` — identity + 10 operations permitidas + 3 operations hard-prohibited + rate limits table + Airtable schemas GMB_Queue + GMB_Audit_Log
- `agents/cartografo/mcp_server/server.py` (396 líneas) — FastMCP server con:
  - Circuit breaker (24h freeze en 429/403 o 3 fails seguidos)
  - Rate limiter (per_hour + per_day + per_month enforced antes del API call, total daily cap 10 writes)
  - Audit log a Airtable `GMB_Audit_Log` en cada write
  - 10 tools: `gbp_health_check`, `gbp_list_locations`, `gbp_get_location`, `gbp_list_reviews`, `gbp_list_insights`, `gbp_publish_post`, `gbp_respond_review`, `gbp_upload_photo`, `gbp_answer_qa` + 3 hard-prohibited (`gbp_update_name/address/phone` devuelven error + auditan el intento)
  - Cada tool de write requiere `approved_by` field (obligatorio para audit)
  - Todos los tools actualmente devuelven `STUB_NOT_IMPLEMENTED` — API calls reales se cablean cuando Jorge complete OAuth Step 1
- `agents/cartografo/mcp_server/pyproject.toml` — deps (fastmcp + google-auth + google-api-python-client)
- `agents/cartografo/secrets/.gitignore` — nunca commitea OAuth JSON
- `agents/cartografo/README.md` — 5-step deploy plan

**Anti-ban safety rules del Cartógrafo (hard-coded):**
- ❌ NUNCA generar reviews (ni positivos ni negativos)
- ❌ NUNCA cambiar name/address/phone automáticamente
- ❌ NUNCA >10 API calls/día por ubicación
- ❌ NUNCA publicar sin `approved_by` field en el tool call
- ❌ NUNCA bypass del circuit breaker
- ❌ Rate limits per-op:
  - publish_post: 2/semana
  - respond_review: 5/día
  - upload_photo: 2/semana (¡!)
  - update_hours / description: 1/mes
  - answer_qa: 2/día

**Pending de Jorge para activar El Cartógrafo en producción:**
1. Google Cloud project `pinnacle-gmb` + enable 5 APIs (Business Profile + My Business Business Info + My Business Account Management + My Business Q&A + My Business Posts)
2. OAuth 2.0 Client ID (Desktop) → download JSON → guardar en `agents/cartografo/secrets/pinnacle_gbp_oauth.json`
3. Pedir quota de Business Profile API si el proyecto lo requiere
4. Crear tablas Airtable: `GMB_Queue` + `GMB_Audit_Log` (schemas en SKILL.md) → pegar `table_id` de audit log en env var `AUDIT_LOG_TABLE`
5. Pasar location_id Pinnacle (formato `accounts/X/locations/Y`)
6. Registrar MCP en `~/.claude/settings.json` (template completo en `agents/cartografo/README.md`)
7. Smoke test: `gbp_health_check` → `gbp_list_locations` (solo reads) → cuando OK, habilito HTTP calls reales en cada tool

**Todo list pendiente con prioridad:**
| Item | Tipo | Prioridad |
|---|---|---|
| GBP PLACE_ID para fer_review_request | Info de Jorge | alta |
| Google Cloud OAuth setup (Cartógrafo Paso 1) | Acción Jorge | alta |
| Airtable tables (Marketing_Audits, SEO_Audits, Content_Queue, GMB_Queue, GMB_Audit_Log) | Setup Airtable | alta |
| Host del cron + `claude login` | Deploy ops | alta |
| El Remitente (email Airtable-only) | Design + build | media |
| El Cazador (Ads) | Build | media |
| El Oráculo VPS deploy | Build | media |
| El Creativo rebuild | Build (awaiting Jorge go) | baja |
| WhatsApp AgentKit | Paused by Jorge | baja |

### 2026-04-23 — Test GBP API key + provisión de 5 tablas Airtable

**Test del Google API key (confirmación honesta):**
- `GOOGLE_PLACES_API_KEY` en `.env.sandbox` tiene **referer restrictions** — solo funciona desde `pinnaclegroupwi.com`, no desde terminal/script
- Google Business Profile API rechaza API keys con **HTTP 401** — Google **solo acepta OAuth 2.0** para GBP (by design — solo el owner autenticado puede modificar su propia GBP)
- Conclusión: para El Cartógrafo, Jorge sí necesita completar el Google Cloud OAuth setup. El key de Places no sirve.
- Para El Posicionador `maps_deep` (read-only): puede usar skills `/seo maps` que internamente van vía scraping/SERP APIs, no vía GBP API directo, entonces no necesita OAuth.

**5 tablas Airtable creadas en base Pinnacle CRM `appfQbDA750Oihy9J`:**
| Tabla | Table ID | Para qué agente |
|---|---|---|
| `Marketing_Audits` | `tbl5vSf886N1WnHU7` | El Mercader |
| `SEO_Audits` | `tblobZ4d7skx8kPHK` | El Posicionador (incluye maps_deep) |
| `Content_Queue` | `tblmIlIvmBvX5mLrx` | El Escriba |
| `GMB_Queue` | `tbl8OWFFT5X9x8A0E` | El Cartógrafo (queue pending approvals) |
| `GMB_Audit_Log` | `tbl0lzGZbD71rfBzA` | El Cartógrafo (forensic audit trail) |

**Script provisión:** `agents/_setup/create_tables.py` (idempotente — safe to re-run).

**pinnacle.json actualizado** con los 5 table_ids reales + `base_id` cambiado a `appfQbDA750Oihy9J` (Pinnacle CRM es donde viven los audits ahora, junto a Contacts/Leads/Deals).

**Smoke test end-to-end:** escribí y borré record de prueba en `Marketing_Audits` con el AIRTABLE_TOKEN → confirmado que el token tiene read + write + schema.bases:write scopes. Todo conectado.

**Scope token Airtable confirmado:**
- ✅ list bases (ve solo `appfQbDA750Oihy9J` Pinnacle CRM)
- ✅ meta.bases.tables.create (puede provisionar tablas)
- ✅ read records, write records, patch, delete (todas las ops normales)
- ❌ No tiene acceso a base `appU9s3kGkVpdrJkw` (Social Media Pinnacle) — si algún día necesitamos wiring cross-base, Jorge expande el token

**Nota operativa:** quedó un test table leftover `_test_delete_me` (tblSYqybnImkJGsDQ) en Pinnacle CRM de la sonda inicial — Airtable Meta API no expone DELETE de tablas completas, Jorge puede borrarla manual desde UI si le molesta (es safe).

**Estado Cartógrafo post-test:** scaffold completo, env vars pendientes de OAuth JSON. Cuando Jorge pase el JSON, cableo las HTTP calls reales de los 10 tools (estimado: 30 min).

### 2026-04-23 — El Cartógrafo: OAuth paused (Google Cloud Console desde iPhone es demasiado fragil)

Jorge hizo el Google Cloud OAuth client (Web app, `pinnacle-alex-bot`). Pegó el JSON completo via chat, lo guardé a `agents/cartografo/secrets/pinnacle_gbp_oauth.json` (chmod 600, gitignored). **client_secret SHA256 primeros 16 = `326c82f732d22d22`** — grabar para audit.

OAuth callback PHP shipped y live (`https://pinnaclegroupwi.com/agents/oauth_gbp_callback.php`) + Jorge agregó esa URL como 2da redirect URI. Le pasé link de autorización — obtuvo 403 de Google (app en "Testing" mode, falta agregarse como Test User). **Desde iPhone no pudo navegar a la pantalla de Test Users** — la Google Cloud Console mobile es inconsistente. **Decisión:** pausar Cartógrafo hasta que Jorge tenga laptop (5 min setup vs horas peleando en mobile). Todo el scaffold + OAuth JSON + callback + URL de authorization quedan listos. State del OAuth pending en `agents/cartografo/secrets/_pending_oauth_state.txt`.

### 2026-04-23 — El Remitente v1 SHIPPED (4to sub-agente R9, email Airtable-native)

**Decisión arquitectural clave (por orden de Jorge):** email marketing 100% in-house, cero servicios externos (no Beehiiv, no ConvertKit, no Resend, no Mailchimp). Hostinger SMTP (`deals@pinnaclegroupwi.com`) + Airtable como source of truth.

**4 tablas Airtable creadas** en Pinnacle CRM (script `agents/_setup/create_email_tables.py`, idempotente):
| Table | ID |
|---|---|
| `Email_Subscribers` | `tblEiB0fBeGxxq7if` |
| `Email_Templates` | `tbljcO5b5i2SZs3ze` |
| `Email_Campaigns` | `tblBJAtH3k1IVhqqc` |
| `Email_Events` | `tblTNKwwXZTBXymOD` |

**`hostinger/agents/pinnacle_mail.php`** — endpoint público en Hostinger con 4 actions:
- `send_campaign` (privileged, X-Alex-Secret): pulls 1 campaign status=Scheduled + scheduled_at<=now → resuelve audience filter → manda vía PHP mail() con multipart text+HTML + List-Unsubscribe-Post header → logs Email_Events
- `track_open` (public): 1×1 GIF pixel, GET `?e=TRACKING_ID` → escribe event_type=opened + incrementa `Email_Campaigns.open_count`
- `track_click` (public): 302 redirect + event_type=clicked
- `unsubscribe` (public): HMAC-SHA256(email, ALEX_SECRET) token válido → marca `status=Unsubscribed` + muestra página de confirmación brandeada

**`agents/remitente/remitente.mjs`** — Node orchestrator multi-mode:
- `seed_templates` — siembra 4 templates base (welcome_en/es + nurture_market_update_en/es) con HTML mobile-first 600px max-width
- `draft_campaign` — usa El Escriba's Content_Queue entry (si existe `--content-queue-id`) o `--topic` para armar subject + preview_text + HTML + text plano via Claude CLI subprocess → escribe a Email_Campaigns status=Draft
- `weekly_report` — stats últimos 7 días desde Email_Events → Telegram resumen
- `on_demand` (alias draft_campaign)
- stub modes: `schedule_send`, `process_welcome`, `process_drip` (v2)

**`pinnacle.json` actualizado** con los 4 `email_*_table_id` cableados.

**Compliance Gmail/Yahoo 2024+ implementado:**
- From + Reply-To `deals@pinnaclegroupwi.com` (dominio autenticado)
- `List-Unsubscribe` + `List-Unsubscribe-Post: List-Unsubscribe=One-Click` headers ✅
- Multipart text+HTML ✅
- HMAC-signed unsubscribe tokens ✅
- IP hasheada SHA256 (no plain IPs en logs)
- Tracking ID opaque (12 hex)

**Verificación:** PHP syntax OK, Node syntax OK, dry-run carga tenant config + muestra los 4 table IDs correctos.

**Pendiente para producción de El Remitente:**
1. **DKIM + DMARC** — Jorge activa en Hostinger cPanel + DNS (crítico para deliverability):
   - `_dmarc.pinnaclegroupwi.com TXT "v=DMARC1; p=none; rua=mailto:deals@pinnaclegroupwi.com"`
   - DKIM cPanel → Email Deliverability → Enable
2. **Deploy de `pinnacle_mail.php`** — automático al siguiente push a master (workflow handles)
3. **Run `--mode seed_templates`** una sola vez para sembrar los 4 templates base en Airtable
4. **Cron entries** en workflow `deploy-hostinger.yml` (4 entries: send_campaign cada 5min, process_welcome daily, process_drip daily, weekly_report lunes)
5. **Popup mirror** — surgical edit a `pinnacle_public.php` action=`subscribe_email` para que además del Contacts.Email1 actualice también Email_Subscribers con status=Active + source=popup

**Estado plantel R9 al cierre:**
- El Oráculo — skill ✅, sub-agente diferido VPS
- El Mercader v1 DRAFT ✅
- El Posicionador v1 DRAFT + maps_deep mode ✅
- El Escriba v1 DRAFT ✅ (sub-sub-agente bajo Posicionador)
- **El Remitente v1 SHIPPED ✅ (email Airtable-native)**
- El Cartógrafo v1 SCAFFOLD ✅ (OAuth paused hasta laptop)
- El Cazador — por construir
- Fer (existente, outbound SMS) — `fer_review_request.php` shipped

**Patrón compartido ahora 4 instancias (Mercader/Posicionador/Escriba/Remitente):** refactor a `agents/_shared/runner.mjs` + `_shared/airtable.mjs` + `_shared/telegram.mjs` es ROI positivo ya. Siguiente build (Cazador) debería usar el shared lib. Deferred mientras Jorge prioriza otras cosas.

**Deployment stack Pinnacle ahora tiene 10 Airtable tables totales:**
| Core CRM | Contacts, Leads, Deals, Notes & Activity |
| R9 agent audits | Marketing_Audits, SEO_Audits, Content_Queue, GMB_Queue, GMB_Audit_Log |
| Email stack | Email_Subscribers, Email_Templates, Email_Campaigns, Email_Events |

Todas con schemas documentados en los SKILL.md respectivos. Zero external dependencies (cero SaaS servicios de email/marketing/SEO).

### 2026-04-23 — Cierre de día: popup mirror + DNS diagnostic + El Cazador shipped

**Popup mirror:** surgical edit a `pinnacle_public.php` action=`subscribe_email` — ahora cada subscribe del popup además de escribir a Contacts también escribe/actualiza `Email_Subscribers` con status=Active + source=popup + HMAC unsubscribe_token. Re-subscribe detection: si el email ya existía y había sido Unsubscribed, se re-activa a Active. Syntax OK, auto-committed + auto-pushed. Telegram notif ahora reporta ambos Airtable writes (Contacts + Email_Subscribers).

**DNS diagnostic deliverability** (`dns.google/resolve`):
- ✅ SPF: `v=spf1 include:_spf.mail.hostinger.com ~all` — ya configurado
- ⚠️ DMARC: `v=DMARC1; p=none` — existe pero mínimo, falta `rua=`
- ❌ DKIM: NO existe (probé selectors: default, hostingermail1, hostingermail2, google, selector1, selector2, k1, s1, dkim — todos vacíos)

Limitación honestamente reportada a Jorge: **no puedo configurar DKIM/DMARC desde este sandbox** (requiere Hostinger hPanel UI o DNS manager, no expuesto por las APIs que tengo). Jorge hace 2 taps desde Hostinger mobile app: (1) Email deliverability → Enable DKIM; (2) DNS zone editor → update `_dmarc` TXT con `rua=mailto:deals@pinnaclegroupwi.com`. Sin DKIM, Gmail/Yahoo mandan al spam.

**El Cazador v1 SHIPPED** — 5to y último sub-agente del plantel R9 core.

Archivos:
- `agents/cazador/SKILL.md` — Anthropic frontmatter + 3 modes + Ad_Performance schema + alert rules + data levels (1/2/3)
- `agents/cazador/cazador.mjs` — Node orchestrator (chmod +x), 3 modes: `ads_health` (cada 3 días), `ads_deep` (lunes semanal, wraps `/ads audit` 250+ checks 7 platforms), `on_demand` (con `--platform` + `--data` opcional)
- `agents/cazador/README.md` — deploy guide + data levels + alert thresholds
- `agents/_setup/create_ad_tables.py` — creó `Ad_Performance` table `tblxkMmNmwlrNnkmX`
- `pinnacle.json` actualizado con `ads_table_id`

**Budget waste sentinel** hard-coded: si `spend_last_7d` > $100 + `conversions_7d` == 0 → Telegram 🚨 `CRITICAL` inmediato con "pause recommended".

**Level 1 input funcional sin data:** analiza landing page CRO + competitive intel vía `/ads landing` + `/ads competitor` + `/ads dna`. Útil para Pinnacle ahora que aún no arrancó paid traffic. Level 2 (métricas pegadas) y Level 3 (CSV exports) cuando Jorge empiece a invertir en Meta/Google Ads.

**Estado PLANTEL R9 COMPLETO al cierre 2026-04-23:**

| Sub-agente | Dominio | Status |
|---|---|---|
| **El Oráculo** | Predicción/simulación pre-launch (MiroFish) | Skill ✅, deploy VPS diferido (nested Claude CLI issue) |
| **El Mercader** | Marketing ops audits | v1 DRAFT ✅ |
| **El Posicionador** | SEO monitor (incluye `maps_deep` cada 3 días) | v1 DRAFT ✅ |
| **El Escriba** | Content writer (sub-sub-agente bajo Posicionador) | v1 DRAFT ✅ |
| **El Remitente** | Email marketing (Airtable-native) | v1 SHIPPED ✅ |
| **El Cartógrafo** | GMB write-side (MCP server + anti-ban guardrails) | v1 SCAFFOLD ✅ (OAuth paused hasta laptop) |
| **El Cazador** | Ads audit + spend tracking | v1 SHIPPED ✅ |
| **Fer (existente)** | Outbound SMS + now review requests via `fer_review_request.php` | ✅ actualizado |

**11 Airtable tables totales** (4 CRM + 5 R9 + 4 email + 1 ads + 1 auditoría GMB + 1 GMB queue — 15, contando GMB = 16 realmente):
- Core CRM: Contacts, Leads, Deals, Notes & Activity
- R9 audits: Marketing_Audits, SEO_Audits, Content_Queue, Ad_Performance, GMB_Queue, GMB_Audit_Log
- Email stack: Email_Subscribers, Email_Templates, Email_Campaigns, Email_Events
- Legacy: Tracy, Fer Conversations (en base CRM)

**Patrón compartido 5 instancias** (Mercader + Posicionador + Escriba + Remitente + Cazador). Cada uno ~300-400 líneas. Refactor a `agents/_shared/` ahora con ROI muy positivo pero aplazado — no bloquea nada. Siguiente iteración.

**TODO stack de Jorge (pendientes de acción humana):**

1. **Deliverability (10 min mobile):** DKIM toggle en Hostinger hPanel + DMARC upgrade con `rua=`
2. **Google Cloud OAuth (laptop, 5 min):** completar Test User setup para El Cartógrafo — scaffold listo
3. **Cron entries:** agregar al `deploy-hostinger.yml` workflow las 4 entries (Mercader/Posicionador/Escriba/Remitente/Cazador por separado, o consolidado en un cron runner)
4. **claude login en host:** una vez decidido host cron (Hostinger PHP wrapper vs VPS), autenticar claude CLI ahí
5. **Popup → Email_Subscribers:** el edit ya está pusheado, se deploya en próxima SCP. **Smoke test recomendado:** suscribir un email de prueba en el popup + verificar que aparezca en Email_Subscribers table con status=Active
6. **Seed templates:** una vez deliverability lista, correr `node agents/remitente/remitente.mjs --tenant pinnacle --mode seed_templates` para crear los 4 templates base en Email_Templates

**Cleanup opcional:** `_test_delete_me` (tblSYqybnImkJGsDQ) sigue en Pinnacle CRM como leftover del primer probe — Jorge puede borrar desde Airtable UI.

### 2026-04-23 — Cierre final del día: deliverability email al 100%

**Jorge completó los 2 cambios DNS pendientes desde hPanel mobile + Kodee AI:**

1. **DMARC upgrade** ✅ guardado en hPanel — Jorge editó el TXT `_dmarc` de `"v=DMARC1; p=none"` a `v=DMARC1; p=none; rua=mailto:deals@pinnaclegroupwi.com; pct=100`. DNS público todavía muestra el viejo (TTL 3600s cache); verificar mañana.

2. **Custom DKIM activado** ✅ LIVE — vía Kodee (Hostinger AI chat). Selector: `hostingermail1._domainkey.pinnaclegroupwi.com`. RSA 2048-bit key publicada y verificable via `dns.google/resolve`. Full DKIM alignment con From: domain `pinnaclegroupwi.com`.

**Stack deliverability email al cierre:**
| Check | Status | Detalle |
|---|---|---|
| SPF | ✅ aligned | `v=spf1 include:_spf.mail.hostinger.com ~all` |
| DKIM | ✅ aligned (custom key) | selector `hostingermail1._domainkey` con RSA 2048-bit |
| DMARC | ⚠️ propagando | upgrade con `rua=` guardado, DNS cache pendiente (~30-60 min) |
| List-Unsubscribe | ✅ | implementado en `pinnacle_mail.php` |
| One-Click Unsubscribe HMAC | ✅ | signed tokens no-falsificables |

**Gmail/Yahoo 2024+ compliance:** ✅ Complete. Inbox rate esperado 25-35% en real estate (vs 15-20% sin DKIM custom). 40-50% con reputación construida a 1-2 meses.

**Jorge declinó smoke test de hoy** — esperamos hasta mañana (DMARC propagation + batería iphone + tiempo). Smoke test queda en TODO list (ver arriba).

**Cierre de día 2026-04-23 con plantel R9 core COMPLETO + deliverability email al 100%:**
- 7 sub-agentes R9 shipped/scaffolded (Mercader, Posicionador, Escriba, Remitente, Cazador, Cartógrafo scaffold, Oráculo diferido)
- 16 Airtable tables provisionadas + cableadas
- Email stack 100% in-house con compliance 2024+
- Popup mirror activo → Email_Subscribers auto-feed
- Fer review_request shipped para Google Review velocity
- Posicionador maps_deep mode para GBP monitoring
- Zero external SaaS deps para marketing/email/SEO/ads

**Próxima sesión (mañana):** verificar DMARC propagation → smoke test end-to-end de El Remitente (primer email real) → validar DKIM=pass + SPF=pass headers en inbox.

### 2026-04-23 23:xx — Jorge aprueba 4 nuevos agentes GAP + roadmap priorizado

Jorge revisó los 5 GAP candidates propuestos + confirmó 4 para construir + difirió 1.

**APROBADOS para build (orden de priorización recomendada por ROI):**

| Prio | Agente | Rol | Uso primario de skills |
|---|---|---|---|
| 1 | **Blotato elimination** (3 rebuilds) | El Creativo + El Director + El Programador sin Blotato | open-carrusel + Meta Graph API / Buffer alternative |
| 2 | **El Clasificador** | Lead scoring — puntúa leads por urgency + distress + property + timeline | product-analytics + experiment-designer + Matemático |
| 3 | **El Analista** | Weekly exec dashboard (Monday 9 AM CST) unificando outputs de Mercader/Posicionador/Escriba/Remitente/Cazador + CRM pipeline + revenue | kpi-dashboard-design + data-storytelling + board-deck-builder |
| 4 | **El Espía** | Daily competitor watchdog — scrape webuyuglyhouses WI + HomeVestors Milwaukee + Sell My House Fast, alert on changes | browser-automation + gstack /browse + Firecrawl + competitive-intel |
| 5 | **El Auditor** | Weekly compliance — WI wholesaler law + TCPA (SMS) + CAN-SPAM (email) + Fair Housing | security-pen-testing + gdpr-data-handling + chief-of-staff |

**DIFERIDO a "for later":**
- **El Contador** — financial agent (deal P&L, CAC, CAC:LTV, ROI per channel). Jorge: "sí es necesario pero podemos ponerlo en la to do list, para luego".

**Jorge sigue closed por hoy** — smoke test Remitente + verification DMARC espera hasta mañana. Los 5 agentes nuevos también se construirán después del smoke test + según Jorge priorice en el momento.

**Arquitectura proyectada** (aplicará R8 SaaS-ready + R7 mobile-first + patrón compartido con existentes):
- Todos usarán tenant config `agents/tenants/<slug>.json` con sus propios campos
- Cada uno crea su tabla Airtable dedicada (El Clasificador: `Lead_Scores`, El Analista: `Weekly_Dashboards`, El Espía: `Competitor_Intel`, El Auditor: `Compliance_Audits`)
- Refactor a `agents/_shared/runner.mjs` sería ROI claro antes de construir los 4 nuevos (4 existentes + 4 nuevos = 8 instancias del patrón)
- Todos alertan a Telegram + writeback a Airtable + logs locales por run

**Plantel R9 proyectado completo (post-build):**
- Core legacy: Scout, Matemático, Fact-Checker, Tracy, Fer, Social Media Agent, Secretario, Planificador
- R9 Phase 2 core: Mercader, Posicionador (+maps_deep), Escriba, Remitente, Cazador, Cartógrafo (OAuth pending), Oráculo (VPS diferido)
- **Nuevos sprint 2:** Clasificador, Analista, Espía, Auditor
- Rebuild sprint 2: Creativo (open-carrusel), Director (?), Programador (Meta Graph API?)
- **Total projected:** 18+ sub-agentes cuando todo quede shipped

**Cierre de día real 2026-04-23.** Next session: mañana.

### 2026-04-23 — SPRINT 2 BUILD: 4 GAP agents shipped (Clasificador + Analista + Espía + Auditor)

**Orden de Jorge:** "Puedes crear los 4 agentes ahora que sugeriste, y mañana seguimos con las pruebas antes de continuar con los más complicados"

**Status:** los 4 agentes shipped con SKILL.md + orchestrator.mjs + README + tabla Airtable dedicada + dry-runs validados.

**Refactor previo:** `agents/_shared/runner.mjs` — shared runtime con helpers DRY:
- `parseArgs`, `loadTenant`, `runClaude`, `airtableFetch/Create/Update/Upsert`, `telegramSend`, `extractScore/Number/Block`, `genRunId`, `isoNow`, `standardMain` (opcional — agentes nuevos usan main() propio)
- Los 4 agentes nuevos son thin wrappers (avg ~250 líneas cada uno vs los 380+ de cazador.mjs) — refactor existentes queda pendiente

**Tablas Airtable creadas** (script `agents/_setup/create_sprint2_tables.py`):
| Tabla | ID | Propósito |
|---|---|---|
| `Lead_Scores` | `tbl9JjYf4v8Yy9fPm` | Clasificador — 1 row per lead per scoring + overall_score + urgency/distress/property/timeline/motivation + heat + suggested_action/owner |
| `Weekly_Dashboards` | `tblIt71QqU7iZCKpT` | Analista — 1 row per ISO week + pipeline metrics + marketing rollup + headline wins/concerns/actions + exec summary |
| `Competitor_Intel` | `tblMSWcdvKtP62hBR` | Espía — 1 row per competitor per scan + snapshot + diff vs prior + change_severity 0-10 + recommended_action |
| `Compliance_Audits` | `tblZjJIHQm7LmudA6` | Auditor — 1 row per sweep + scores per regulación (WI wholesaler + TCPA + CAN-SPAM + Fair Housing + GDPR + ADA) + critical issues + evidence snippets |

**Wired en `pinnacle.json.airtable`:** `lead_scores_table_id`, `weekly_dashboards_table_id`, `competitor_intel_table_id`, `compliance_audits_table_id` + también se añadieron `leads_table_id`, `contacts_table_id`, `deals_table_id`, `notes_table_id` para cross-table queries.

**Los 4 agentes:**

1. **El Clasificador** (`agents/clasificador/`)
   - Modos: `score_batch` (cron 2h, top 25 leads) · `score_one` (--lead-id, on-demand) · `rescore_hot` (nightly, urgency decay)
   - 5 axes weighted composite: urgency 0.30 + distress 0.25 + property 0.20 + timeline 0.15 + motivation 0.10
   - Heat: 🔥 Hot ≥75 · 🌡 Warm 55-74 · ❄️ Cold 30-54 · 🚫 Disqualify <30
   - WI-specific signals: pre-foreclosure sheriff sale dates, probate filings, divorce filings, WI 2024 wholesale disclosure law
   - Escribe 1 row aggregate per run + 1 row per lead scored (para historial individual)

2. **El Analista** (`agents/analista/`)
   - Modos: `weekly` (cron Mon 07:00 CT) · `ad_hoc` (--week 2026-W17) · `preview` (dry-run)
   - Agrega cross-table: Marketing_Audits + SEO_Audits + Ad_Performance + Content_Queue + Email_Campaigns + Email_Events + Competitor_Intel + Compliance_Audits + Lead_Scores + Leads + Deals
   - Roll-ups automáticos: new_leads, qualified_leads (≥55), deals_closed/lost, revenue, email open/click rates, content_published
   - Output: 3-paragraph executive_summary + top 3 wins + top 3 concerns + top 3 action items
   - Dry-run preview confirmó: 3 new leads detectados en W17 (semana actual)

3. **El Espía** (`agents/espia/`)
   - Modos: `daily` (09:00 CT, todos cfg.competitors) · `weekly_deep` (Sun 10:00 CT, + FB Ad Library) · `on_demand` (--competitor URL)
   - Scrape respetuoso: User-Agent PinnacleBot identificado, 1 req/sec, robots.txt honor
   - Signals extraídos: title/h1/hero, CTAs, phones, addresses (multi-loc signal), socials, pricing $, offer keywords, JSON-LD schema, word count
   - Diff vs prior scan del mismo competitor_url → change_severity 0-10
   - Alert tiers: 🚨 ≥9 immediate, ⚠️ 6-8 Telegram, 🟡 3-5 digest, ✅ 0-2 silent
   - Dry-run confirmó: We Buy Ugly Houses scraped exitosamente (title + h1 + 4 CTAs + phone + social links)

4. **El Auditor** (`agents/auditor/`)
   - Modos: `weekly` (cron Fri 10:00 CT) · `reg_focus` (--reg tcpa|can_spam|fair_housing|gdpr|wi_wholesaler|ada_web) · `incident` (post-event)
   - 6 regulaciones con scores individuales + overall
   - Exposure $$ doc en README: TCPA ($500-1500/text × class), CAN-SPAM ($51,744/email FTC 2024), Fair Housing ($16k-79k/violation), GDPR (4% global rev), ADA web ($16k median settlement)
   - Lee últimos Email_Campaigns + Marketing_Audits + SEO_Audits + pages del site
   - Output: critical_issues + warnings + passing + recommendations + evidence_snippets (quoted)

**Dry-runs ejecutados OK:**
- ✅ `clasificador --mode score_batch --dry-run` → 25 leads reales fetched
- ✅ `analista --mode preview` → 3 new_leads W17, rollups OK
- ✅ `espia --mode daily --dry-run` → scraped We Buy Ugly Houses live (707 words, 4 CTAs, phone 866-200-6475)
- ✅ `auditor --mode weekly --dry-run` → prompt construido OK con signals

**Plantel R9 ACTUAL cierre 2026-04-23 (9 agentes always-on + legacy):**
- R9 core Phase 2: Mercader, Posicionador (+maps_deep), Escriba, Remitente, Cazador
- R9 Sprint 2 **NUEVOS**: Clasificador, Analista, Espía, Auditor ✅
- Sub-agentes especializados: Cartógrafo (GMB, OAuth paused)
- Core legacy: Scout, Matemático, Fact-Checker, Tracy, Fer, Social Media Agent, Creativo, Director, Programador, Secretario, Planificador, Oráculo (VPS diferido)

### 2026-04-23 PM — Reloj suizo (follow-up pipeline) diagnosticado + arreglado

Jorge reportó que los emails/SMS constantes de primeros 30 días NO estaban saliendo. Diagnóstico profundo + fixes:

**Flujo Contacts (5 stages):**
1. `New` (92) — backlog manual de Jorge (NO es problema, él los revisa)
2. `To Be Contacted` → cron `fer_first_contact.php` cada 15 min
3. `Contacted` step 1-4 → mismo cron, 24h entre steps (SMS a Phone1→Phone4)
4. `Seguimiento` 24 touches × 12 meses → cron `fer_seguimiento.php` daily 9:30 CT
5. `Dead` — step ≥24 o stop

**Root cause encontrado:** los cron jobs de Hostinger estaban configurados via SSH (`crontab -l | crontab -` en deploy-hostinger.yml) pero **SSH crontab NO persiste en Hostinger shared hosting**. Los PHP scripts funcionan perfectamente cuando se disparan — lo confirmé invocando manual: 17 SMS reales enviados en ~3 min, incluyendo los 12 fantasmas reseteados.

**Fixes aplicados:**
1. `FC_SMS_DELAY_SECONDS` y `SEG_SMS_DELAY_SECONDS` bajados de 15s → 5s (evita timeout 60s del cron invoker)
2. Añadido `@ignore_user_abort(true) + @ini_set('max_execution_time', 300)` en ambos scripts → PHP sigue ejecutando aunque nginx corte
3. Reset de 12 fantasmas (Robert Boyda, Makayla Kleiber, MICHAEL KEMINGER, Kerry Duquaine, Patricia Boschert, Sandra Phillips, Jessica Clayton, Penny Maresh, Brian Reignier, Stuart Enselmoz, JANE GANTENBEIN, Breeyana Trapman) → Stage=To Be Contacted + campos limpios
4. Procesé manual los 12 → TODOS recibieron primer SMS a Phone1
5. `docs/CRON_SETUP.md` creado con los 4 crons exactos para Hostinger cPanel (manual, ya que SSH no persiste)

**Acción pendiente de Jorge:** configurar los 4 crons en hPanel → Advanced → Cron Jobs:
- `*/15 * * * *` → fer_first_contact.php (crítico)
- `30 15 * * *` → fer_seguimiento.php (daily)
- `0 14 * * *` → fer_stale_cron.php
- `30 14 * * *` → fer_morning_brief.php

**Evaluación de Fer (inbound responder):** 4 inbounds hoy, 3 conversaciones guardadas, 1 se perdió (phone sin Contact match probable).
- Shashikanth Kaluvala (+1269...) respondió → Claude Sonnet escalated → Fer lo movió a Stage=Dead (clasificación autónoma correcta)
- MICHAEL KEMINGER respondió al primer SMS → Fer contestó con Haiku 3s después. OK.

### 2026-04-23 PM — EL SUPERVISOR: sistema auto-evolutivo shipped

**Orden directa de Jorge:** *"construir un agente supervisor para que todo esté funcionando bien, sistema autonomista e inteligente que sepa evolucionar por sí mismo sin que yo tenga que estar detrás de ello"*.

**Construido:** `agents/supervisor/` — 10mo agente R9, meta-watchdog.

**Tablas Airtable:**
- `Ops_Health` `tbltZWa4PiYPdnyKl` — 1 row per run (heartbeat/deep/evolve)
- `Ops_Insights` `tblPfJba7iPJBTrw6` — knowledge base de patrones aprendidos + fix proposals

**4 modos:**
| Mode | Cadencia | Qué hace |
|---|---|---|
| `heartbeat` | cada 15 min | probe endpoints Hostinger + Airtable/OpenPhone/Telegram APIs + log freshness + pipeline counters |
| `deep` | cada 1h | + ghost detection + auto-repair (reset fantasmas max 25/run) + drift metrics |
| `evolve` | weekly Sat 07:00 CT | 7-day pattern recognition vía Claude, fix proposals, some auto-applied |
| `incident` | on-demand | forensic deep-dive |

**Auto-repairs que hace solo (no molesta a Jorge):**
- Ghost reset automático
- Cron re-trigger si endpoint stale >4h en ventana (max 1/endpoint/hora)
- Exponential backoff en OpenPhone 429
- Log schema drift sin bucle

**Alert tiers Telegram (solo molesta cuando vale la pena):**
- 🚨 CRITICAL instant: API key revoked, cron muerto >6h, Hot lead no contactado, security event
- ⚠️ WARN hourly digest: un agente falló, score drop, email bounce sube
- 🟡 NOTICE daily 8am digest: auto-repairs aplicados, drift menor
- ✅ GREEN silent: todo OK

**Evolve mode — corazón de la autoevolución:**
1. Lee 7 días de Ops_Health
2. Cuenta top 10 errores recurrentes
3. Claude genera causa raíz + fix concreto (file+diff) + impacto $ por cada patrón
4. Fixes triviales+seguros → auto-aplica
5. Fixes complejos → Ops_Insights status=open + Telegram digest

**Deploy:** `.github/workflows/supervisor-cron.yml` — GitHub Actions scheduled (gratis, no VPS needed). Secrets ya existen del workflow deploy-hostinger. Primer tick automático en el siguiente `*/15` UTC.

**Dry-run ejecutado:** heartbeat funcionó end-to-end, detectó red health (2/8 checks) correctamente porque no tenía API keys locales — en GHA las tendrá todas. Pipeline stats correctos: 92 New (reconocido como backlog de Jorge, no error), 0 ghosts post-mi-reset.

**Plantel R9 cierre 2026-04-23 (10 agentes + legacy):**
- R9 core Phase 2: Mercader, Posicionador, Escriba, Remitente, Cazador
- R9 Sprint 2: Clasificador, Analista, Espía, Auditor
- R9 Sprint 3 **NUEVO:** Supervisor ✅
- Specialized: Cartógrafo (OAuth paused), Oráculo (VPS diferido)
- Legacy: Scout, Matemático, Fact-Checker, Tracy, Fer, Social Media, Creativo, Director, Programador, Secretario, Planificador

**Todo list pendiente tomorrow:**
- Smoke test end-to-end El Remitente (primer envío real)
- Verificar DMARC propagation (TTL 3600s)
- Real run de los 4 nuevos agentes (sin --dry-run) una vez Jorge confirme
- El Cartógrafo OAuth (needs laptop — Test User setup en Google Cloud Console)
- El Contador (financial, diferido)
- Rebuild Creativo + Director + Programador (Blotato elimination)
- Decidir cron host (Hostinger PHP wrapper vs VPS) + claude login

### 2026-04-23 NIGHT — Unlocks desde PC + cron host + Remitente v2

Jorge se movió a PC. Desbloqueó lo que estaba bloqueado en iPhone.

**1. El Cartógrafo OAuth COMPLETO:**
- Redirect URI configurado en Google Cloud Console → `https://pinnaclegroupwi.com/agents/oauth_gbp_callback.php`
- Test User añadido (email admin de GBP)
- Autorización completada, `code` intercambiado por:
  - `access_token` (válido 1h)
  - `refresh_token` (permanente, auto-mint de access tokens)
  - `scope: https://www.googleapis.com/auth/business.manage`
- Tokens guardados en `agents/cartografo/secrets/pinnacle_gbp_oauth.json` (gitignored)
- Refresh flow probado ✓
- Quota GBP API bloqueando calls secuenciales (HTTP 429 — default 1/min en Testing mode). Jorge debe solicitar aumento a 300/min en https://console.cloud.google.com/apis/api/mybusinessbusinessinformation.googleapis.com/quotas?project=pinnacle-alex-bot

**2. MCP Server Cartógrafo wire-up real (+208 líneas):**
- `_oauth_bearer()` con auto-refresh 10 min antes de expirar
- `_gbp_call(method, url, body)` helper genérico con error handling
- Tools live: `gbp_list_accounts`, `gbp_list_locations`, `gbp_get_location`, `gbp_list_reviews`, `gbp_list_insights` (reads) + `gbp_publish_post`, `gbp_respond_review`, `gbp_answer_qa` (writes con circuit breaker + rate limit + audit)
- Único stub restante: `gbp_upload_photo` (requiere POST multipart bytes — futuro)

**3. Supervisor threshold refinado:**
- Antes: warning si >50 New contacts (disparaba yellow falsos todo el tiempo en Pinnacle que tiene backlog normal de Jorge)
- Ahora: tenant-configurable. Pinnacle: `backlog_new_warn_threshold: 500`, `backlog_new_critical_threshold: 2000`
- Config añadido a `agents/tenants/pinnacle.json` bajo bloque `supervisor`

**4. El Remitente v2 — 3 modos críticos implementados:**
- `process_welcome` — scan Active subs sin último email → manda welcome_{lang} template con {{unsub_url}} HMAC → update last_email_sent_at + log Email_Events
- `process_drip` — scan Active subs con last_email >= 14 días atrás → manda nurture_{lang} → update
- `schedule_send` — fire campaign específica por `--campaign-id` a todos Active que matchean audience_filter
- Helpers nuevos: `sendEmailSmtp()` → POST `/Tools/send_notification.php`, `renderTemplate()` con vars (unsub_url, email, name, month, year), `fetchTemplatesByCategory()`, `logEvent()` → Email_Events
- Rate limit: 2 emails/sec pacing entre sends
- Remitente ahora integrado al workflow `agents-cron.yml`: process_welcome daily 14:30 UTC, process_drip daily 15:00 UTC, weekly_report Mon 11:00 UTC

**5. GHA workflow para 8 agentes Node (`agents-cron.yml`):**
- 16 cron triggers (con Remitente ahora 17+) cubriendo: Mercader, Posicionador, Escriba, Cazador, Clasificador, Analista, Espía, Auditor, Remitente
- Instala claude-code CLI on GHA → override binary_path a 'claude' → ejecuta agente
- Secrets reutilizados de deploy-hostinger.yml
- workflow_dispatch para invocación manual desde UI GitHub
- Artifacts uploaded para inspección post-run

**6. Email smoke test confirmado:**
- Enviado a geocarpentryllc@gmail.com vía POST `/Tools/send_notification.php`
- HTTP 200 + `{"success":true,"type":"email"}`
- Pendiente: Jorge verifica en Gmail "Show original" que DKIM:PASS + DMARC:PASS + SPF:PASS

**7. Reloj suizo Hostinger crons:**
- Jorge configuró los 4 crons en hPanel manualmente (fer_first_contact cada 15 min, fer_seguimiento daily 15:30 UTC, fer_stale_cron daily 14:00 UTC, fer_morning_brief daily 14:30 UTC)
- Verificación pendiente mañana ~11 AM CT: si los 28 Contacted avanzaron step 1→2 → cron funciona
- Supervisor GHA vigilando la freshness del log

**8. Verificación Supervisor primer tick GHA:**
- Corrió 2026-04-23T20:14:36 UTC (primer tick scheduled)
- 8/8 checks pass (endpoints Hostinger + Airtable + OpenPhone + Telegram + webhooks)
- Health: yellow → fix applied en threshold (próximo tick será green)
- Pipeline: 0 ghosts detectados, stats correctos
- GHA secrets verificados funcionales (sino checks fallarían)

**Plantel R9 cierre night 2026-04-23 (10 agentes funcionales):**
- R9 Phase 2: Mercader, Posicionador, Escriba, Remitente (✨ v2 completo), Cazador
- R9 Sprint 2 GAP: Clasificador, Analista, Espía, Auditor
- R9 Sprint 3: Supervisor (corriendo GHA scheduled)
- Specialized: Cartógrafo (OAuth ✅ + MCP wire-up ✅, quota pending Jorge)
- Legacy: Scout, Matemático, Fact-Checker, Tracy, Fer, Social Media, Creativo, Director, Programador, Secretario, Planificador, Oráculo (diferido VPS)

**17 cron triggers ahora activos en GHA + 4 en Hostinger = 21 jobs autónomos.**

**Todo list pendiente (roadmap real):**
- ✅ Jorge confirmó: email smoke test recibido OK
- ✅ Jorge confirmó: 4 crons Hostinger activos
- ✅ DMARC/SPF/DKIM propagados globalmente confirmed (DoH check 2026-04-23 night)
- ✅ Supervisor pagination fix (ahora ve los 361 reales no solo 100)
- ✅ Supervisor auto-trigger incident mode cuando heartbeat red
- ✅ Supervisor smart stale detection (no alerta cron si no hay TBC work pending)
- ✅ Remitente v2 3 modos live (process_welcome, process_drip, schedule_send)
- 🚨 **BLOQUEANTE CARTÓGRAFO: GBP listing NOT PUBLICLY VISIBLE** (Jorge screenshot 2026-04-23 night)
  - Listing "Pinnacle Holdings Group — We Buy Houses Cash Wisconsin" existe pero sin verificar
  - Google solo ofrece video verification (3-5 días review)
  - Jorge NO puede hacer video ahora → DIFERIDO
  - Descripción en proceso: 726/750 chars ES listos para pegar
  - Todo el MCP server del Cartógrafo queda listo pero **frozen** hasta que el listing sea público
  - Quota increase postergada hasta post-verificación (no sirve antes)
- Mañana 11 AM CT: validar que reloj suizo avance Contactos step 1→2
- Cuando Jorge pueda grabar video verification: desbloquear Cartógrafo completo
- Implementar Cartógrafo `gbp_upload_photo` (multipart — futuro cuando se necesite)
- Debug del 4to inbound de Fer que no se guardó en conversations
- Rebuild Creativo + Director + Programador (Blotato elimination)
- El Contador (financial, diferido)
- WhatsApp AgentKit (3 decisiones arquitectónicas)
- Refactor existentes a `_shared/runner.mjs` (reduce duplicación)
- Supervisor incident auto-trigger cuando heartbeat red
- Pagination en Supervisor pipeline check (>100 contactos)

### 2026-04-23 — NotebookLM skill instalado (Google NotebookLM wrapper)

**Repo:** `proyecto26/notebooklm-ai-plugin` (MIT ✓)
**Ubicación:** `/root/.claude/skills/notebooklm/` (174KB)
**Stack:** Bun/TypeScript (Bun 1.3.11 ya instalado) + Chrome DevTools Protocol para auth
**Scripts:** `artifact-generator.ts`, `auth.ts`, `chat.ts`, `cookie-store.ts`, `main.ts`, `notebook-manager.ts`, `notes-manager.ts`, `research-manager.ts`, `rpc-client.ts`, `source-manager.ts`, `types.ts`

**Qué hace:** wrapper programático de Google NotebookLM (gratis, rate-limited). Desde Claude Code:
- Chat con notebook (Q&A source-grounded + citations de Gemini)
- Gestionar sources (URLs, YouTube, archivos, texto)
- Generar 9 artefactos: slide decks (PDF/PPTX), audio overviews (M4A — deep-dive/brief/critique/debate), video overviews (MP4 — classic/whiteboard/kawaii/anime/watercolor), mind maps (HTML), flashcards (HTML/JSON), quizzes (HTML/JSON), infographics (PNG), reports (MD), data tables (CSV/Sheets)
- Research (fast/deep web research)
- Notes management

**Rate limits free tier (Google):** 3 audio/video overviews/día · 10 reports/flashcards/quizzes/día · 50 chats/día · 100 notebooks total · 50 sources por notebook.

**Requisito crítico:** Chrome local con sesión Google activa. El skill usa Chrome DevTools Protocol + cookie extraction. **NO funciona en este sandbox headless** — corre desde la laptop del Jefe con Chrome + Google login. La skill queda instalada para cuando Jorge la use desde su máquina.

**Casos de uso Pinnacle + R8 SaaS-ready:**
1. **Knowledge base WI real estate:** cargar reportes de mercado, leyes de probate/foreclosure WI, competitor deal history → queries citation-backed
2. **Content factory por tenant:** de un notebook con la "enciclopedia Pinnacle" sacar audios para homeowners distressed, mind maps para casos probate, infographics para redes, slides para investors
3. **Research feeder para El Oráculo:** cuando hagamos el wrapper, NotebookLM proporciona el grounded data que MiroFish/Oráculo simula reacciones sobre
4. **Deliverable vendible (R8):** cada cliente SaaS futuro recibe su propio notebook + outputs brandeados = paquete premium "Knowledge + Content Factory"

**Seguridad:** no envía data a terceros más allá de Google's NotebookLM infra. Cookie session queda local.

**Status queue Phase 2 al cierre 2026-04-23:**

| Item | Status |
|---|---|
| MiroFish / El Oráculo | Skill ✅ instalado, sub-agente diferido a VPS deploy |
| ai-marketing-claude / El Mercader | Skill ✅ + **sub-agente v1 DRAFT completo** (3 approvals pendientes para prod) |
| WhatsApp AgentKit | Clonado, **pausado por Jorge** (3 decisiones arquitecturales) |
| gstack | ✅ Instalado (42 skills, disciplina ingeniería) |
| skill-creator | ✅ Instalado (oficial Anthropic) |
| claude-seo / El Posicionador | Skill ✅ (24 skills), sub-agente por construir |
| claude-ads / El Cazador | Skill ✅ (20+ skills, template real-estate), sub-agente por construir |
| ui-ux-pro-max | ✅ Instalado (67 styles, 96 palettes, DSG) |
| **NotebookLM** | ✅ **Instalado hoy** (requiere Chrome local signed-in) |
| open-carrusel | ✅ Instalado (Instagram carousels) |

### 2026-04-23 — gstack instalado (Garry Tan's Claude Code setup)

Jorge pidió "gistak" = **gstack** (typo de autocorrect). Confirmado + instalado.

**Repo:** `garrytan/gstack` v1.6.1.0 (66K stars)
**Licencia:** MIT ✓
**Ubicación:** `/root/.claude/skills/gstack/`
**Stack:** Bun 1.3.11 + Playwright Chromium (278MB descargado)

**42 skills linkeados** (slash commands en Claude Code):
- **Planning:** `/plan-ceo-review`, `/plan-eng-review`, `/plan-design-review`, `/plan-devex-review`, `/plan-tune`, `/autoplan`, `/office-hours`, `/cso`
- **Design:** `/design-consultation`, `/design-review`, `/design-shotgun`, `/design-html`
- **QA:** `/qa`, `/qa-only`, `/browse`, `/open-gstack-browser`, `/setup-browser-cookies`
- **Review:** `/review`, `/devex-review`, `/careful`
- **Deploy:** `/ship`, `/land-and-deploy`, `/canary`, `/setup-deploy`, `/freeze`, `/guard`, `/unfreeze`
- **Ops:** `/investigate`, `/health`, `/document-release`, `/retro`, `/benchmark`, `/benchmark-models`
- **Meta:** `/context-save`, `/context-restore`, `/learn`, `/pair-agent`, `/codex`, `/gstack-upgrade`, `/make-pdf`

**Uso estratégico para Pinnacle + R8 SaaS-ready:**
- `/review` + `/qa` antes de cada `/ship` — production gates
- `/design-review` para popup/webform/chatbot UI antes de deploy — valida con ojo de diseñador
- `/investigate` + `/retro` para post-mortems tipo el bug del mobile-gate del popup de hoy
- `/canary` cuando empecemos a vender a 2º cliente — deploy gradual
- `/freeze` + `/guard` cuando hay campañas críticas en producción
- `/make-pdf` como alternativa independiente a market-report-pdf
- `/plan-ceo-review` para decisiones grandes — rethink desde visión producto

**Actualización:** `/gstack-upgrade` sync manual + `gstack-config set auto_upgrade true` para auto-sync.

**Complementariedad con skills existentes:**
- gstack aporta GATES y PIPELINES (flujos conectados) — no reemplaza los skills atomicos existentes (`code-review-excellence`, `verification-before-completion`, `systematic-debugging`), los conecta.
- No es domain agent (R9) — es capa de disciplina de ingeniería transversal. ALEX invoca cuando aplica.

### R10. CREATIVO/DIRECTOR — PUPPETEER + HTML/CSS, NUNCA AI IMAGEN PARA TEXTO (2026-04-29)
**Orden directa de Jorge tras rechazar visuales generados por Replicate Nano Banana — NO NEGOCIABLE, sin excusas, ningún agente futuro debe re-proponer este approach erróneo.**

**Contexto:** Sesión 2026-04-29 ALEX volvió a proponer Replicate Nano Banana / Imagen-4 / Flux para generar carruseles e imágenes con texto en español. Jefe rechazó las 3 muestras por errores ortográficos + branding inconsistente. Esta decisión YA se había tomado antes y se perdió por falta de persistencia en memoria.

**Razón técnica:** todos los modelos AI imagen actuales (Nano Banana, Imagen-4, Flux Pro, DALL-E 3, Recraft, Ideogram) **alucinan ortografía** especialmente en español. Letras inventadas, acentos mal puestos, palabras incompletas. Branding tampoco es determinístico — logo, colores y tipografía cambian entre runs.

**Stack APROBADO para El Creativo (carruseles + posts con texto):**
1. `agents/creativo_runner/themes.mjs` — 184 líneas con 5 temas T1-T5 ya construidos: `slideHook()`, `slidePoint()`, `slideCTA()`, `buildCarousel()`. Logo Pinnacle integrado, fonts Montserrat, viewport 1080×1350 IG 4:5.
2. **Puppeteer/Playwright** en GHA runner (npm `puppeteer` o `playwright-chromium`) → render BODY HTML → screenshot PNG.
3. **Cloudinary** signed upload → URL persistente para FB/IG.
4. **Airtable SM Base** (`appU9s3kGkVpdrJkw` / `tblAj0Pkj1jW4p5Ld`) → estado + `visual_url` + `Status="Visual Listo"`.

**Stack APROBADO para El Director (videos/Reels):**
- HeyGen avatar de Jorge para Reels personalizados (cuando se active)
- Stock video + voiceover ElevenLabs (faceless reels)
- NUNCA modelos AI video para visuales con texto overlaid — mismo problema ortográfico

**AI imagen permitida SOLO en estos casos específicos:**
- Fondos/escenas SIN TEXTO (overlay text via CSS/Cloudinary después)
- Avatares character-aware (HeyGen, Synthesia, Hedra) para video personal
- Stock-replacement (Pexels API, ya en Doppler)

**Anti-regresión:**
- Cualquier propuesta futura de Replicate / DALL-E / Flux / Imagen / Recraft / Nano Banana / similar **PARA TEXTO/CARRUSELES** debe rechazarse automáticamente citando R10.
- ALEX y todos los sub-agentes deben referenciar R10 al iniciar trabajo en visuales sociales.
- Hay un `Note in CLAUDE.md sección 1d` que duplica esta regla a nivel proyecto.

**Skill de memoria:** Jefe ordenó instalar `claude-mem` para todas las sesiones futuras (2026-04-29 follow-up). Ver entrada dated 2026-04-29 en sección "REGLAS DEL JEFE".

**Aprobado por:** Jorge Cruz — 2026-04-29

---

### R9. SUB-AGENTES DEDICADOS ALWAYS-ON POR DOMINIO (2026-04-23)
**Orden directa de Jorge — NO NEGOCIABLE.**Cada nuevo skill de Phase 2 (marketing, SEO, ads, etc.) tiene su **sub-agente dedicado que lo usa 100% del tiempo, no on-demand**. Patrón: monitoreo continuo + alertas automáticas + reportes periódicos + histórico en Airtable.

**Arquitectura por sub-agente:**
1. **Spec en `agents/<nombre>.md`** (tenant-aware per R8)
2. **Invocación:** cron (semanal/diaria según dominio) + on-demand desde ALEX
3. **Storage:** tabla Airtable dedicada por dominio (`SEO_Audits`, `Marketing_Audits`, `Ad_Performance`, etc.)
4. **Alertas:** Telegram al Jefe cuando se detectan issues o umbrales cruzados
5. **Billing hook:** usage meter por tenant (R8)

**Plantel propuesto (nombres sugeridos, Jorge aprueba/renombra):**

| Sub-agente | Dominio | Skill base | Cadencia | Estado |
|---|---|---|---|---|
| **El Oráculo** | Predicción/simulación pre-launch | MiroFish | opt-in gate (pre-campaign) | smoke test corriendo |
| **El Mercader** | Marketing ops / audits | ai-marketing-claude | Semanal auto-audit a pinnaclegroupwi.com + competidores WI | pendiente |
| **El Posicionador** | SEO monitor | Claude SEO (siguiente queue) | Diario health check + semanal deep audit | pendiente install + build |
| **El Cazador** | Ads performance | Claude ADS (último queue) | Diario monitoring de spend + CTR + ROAS | pendiente install + build |

**Default pattern para cada always-on sub-agente (cadencia aprobada por Jorge 2026-04-23):**
- **Cada 3 días 8 AM CST:** quick health check → Telegram brief si todo OK, alerta si hay issue
- **Semanal lunes 9 AM CST:** deep audit → genera reporte PDF/MD → guarda en Airtable + link en Telegram
- **On-demand:** Jorge o ALEX piden análisis puntual

**Nombres aprobados por Jorge 2026-04-23:** El Oráculo, El Mercader, El Posicionador, El Cazador.
**Orden de construcción aprobado:** Oráculo (smoke test) → Mercader (skill ya instalado) → buscar/instalar Claude SEO + Posicionador → buscar/instalar Claude ADS + Cazador.

**R9 se complementa con R6 (memoria) + R7 (mobile-first) + R8 (SaaS-ready) — los sub-agentes siguen todas las reglas anteriores.**

**Aprobado por:** Jorge Cruz — 2026-04-23

---

### R8. SAAS-READY / MULTI-TENANT-FIRST — PRINCIPIO ARQUITECTURAL PERMANENTE
**Orden directa de Jorge, 2026-04-23 — NO NEGOCIABLE, aplica a TODO lo que desarrollemos.**

Todo lo que se construya para Pinnacle debe diseñarse desde el día 1 como **producto SaaS vendible a terceros**. Pinnacle es el tenant cero — no el único tenant. Cada decisión arquitectural deja la puerta abierta a clientes futuros.

**Reglas operativas:**

1. **Configurabilidad total** — NADA hardcodeado. Todo lo específico de Pinnacle (colores, logo, nombre, teléfono, website, API keys, Airtable base ID, textos de copy, emails, horarios) va en configuración por-tenant, no en el código.

2. **Tenant isolation** — cada cliente tiene su propio espacio de datos: Airtable base propia (o tabla con `tenant_id`), credenciales propias, storage propio, branding propio. Nunca cross-pollution entre tenants.

3. **Separación de capas:**
   - **Core engine** (reusable, open-source-safe): lógica del form, chatbot, popup, SM pipeline, simulación
   - **Tenant config**: todo lo específico de un cliente en un JSON/YAML o DB record
   - **Deployment adapter**: scripts que instalan el core con la config de un tenant dado

4. **Onboarding de nuevos clientes** — debe existir un proceso definido: crear config → provisionar infra → go live. Documentado.

5. **Billing hooks** — considerar upfront dónde enchufarse Stripe / subscription management. Usage metrics (leads capturados, simulaciones corridas, popups mostrados, emails enviados) desde el día 1 en cada componente.

6. **Licencias de terceros** — siempre validar license antes de usar una dep. AGPL-3.0 y viral copyleft pueden bloquear monetización — evaluar si usar, usar-sin-modificar, o reemplazar. MIT / Apache 2.0 / BSD son safe.

7. **Documentation-first** — cada componente tiene README con: setup, configuración por-tenant, API pública, troubleshooting, cómo extender. Debe poder leerlo un cliente o dev externo sin acceso a nuestro contexto interno.

8. **Naming & branding** — código, endpoints, nombres de variables NO asumir "Pinnacle". Usar placeholders genéricos (`{TENANT_NAME}`, `{BRAND_PRIMARY}`). Pinnacle va en la config.

9. **Security defaults** — input validation, rate limiting, honeypot, auth, CORS, CSP — desde el día 1. No "lo agregamos después cuando vendamos".

10. **Mobile-first (R7) + SaaS-ready (R8) se complementan** — el producto vendible TIENE que verse bien en móvil. Es lo primero que ven los clientes cuando les demostramos.

**Ejemplos:**
- ❌ `const AIRTABLE_BASE = "appfQbDA750Oihy9J"` — hardcoded Pinnacle
- ✅ `const AIRTABLE_BASE = tenant.airtable.base_id`
- ❌ `const LOGO = "https://pinnaclegroupwi.com/..."` — hardcoded URL
- ✅ `const LOGO = tenant.brand.logo_url`
- ❌ `subject: "We Buy Houses in Wisconsin"` — hardcoded industry + region
- ✅ `subject: interpolate(tenant.copy.email_subject, tenant.vars)`

**Reconciliación con Phase 1 actual:** el código actual está lleno de hardcodes (webform, chatbot, popup, bridges). Eso se refactoriza gradualmente — no bloquea Phase 2. Regla aplica FORWARD desde 2026-04-23. Refactor retroactivo a Phase 1 se hace cuando armemos la primera venta a un segundo cliente.

**Aprobado por:** Jorge Cruz — 2026-04-23

---

### 2026-04-28 — Fix spam Supervisor + audit memoria desync

**Síntoma reportado por Jorge:** "el auditor me está enviando mensajes a cada rato y en fila".

**Diagnóstico:**
- NO era el R9 Auditor (solo 1 run en Airtable Apr-24, status=Failed).
- ERA **El Supervisor** (`supervisor-cron.yml`): heartbeat cada 15 min + deep cada 1h = ~120 ejecuciones/día.
- Bug raíz en `agents/supervisor/supervisor.mjs:467-474`: el modo `deep` mandaba Telegram **siempre que hubiera warnings**. Como el warning "Sin seg_sms_sent desde hace 40h (esperado daily)" se repite hora tras hora, generaba **24 mensajes idénticos/día**.
- El warning en sí es **falso positivo crónico** — verificado: `Last contact date: 2026-04-28`, `SMS Sent: true` en Contacts → reloj suizo Hostinger SÍ está corriendo. El campo `seg_sms_sent` que rastrea el log no se registra porque no hay contactos due en Seguimiento (solo 5 en stage, ninguno necesita toque hoy).

**Fix aplicado (`supervisor.mjs`):** Dedup 24h por warning-set NORMALIZADO.
- Antes de enviar Telegram en modo `deep`, fetch últimos 30 runs en ventana 24h.
- **Normalización crítica:** los warnings con números variables ("stale 40.6h" vs "stale 38.2h" vs "stale 32.1h") se reducen a un mismo string ("stale Nh") via regex `\d+(?:\.\d+)?` → `N` y `[a-f0-9]{8,}` → `ID`. Sin esto, el dedup nunca matchearía porque las horas cambian cada hora.
- Verificado: las 5 variaciones de warnings observadas en últimas 24h colapsan a 2 únicos normalizados.
- Si el set normalizado es idéntico a algún run alertado en últimas 24h → **suprimir alerta**.
- Si el set cambió (nuevo warning aparece o uno desaparece) → enviar.
- Campos opcionales `alerted` y `alert_reason` se persisten para auditoría (graceful fallback si Airtable no los tiene aún).
- Resultado esperado: máximo **1 mensaje/día** por warning recurrente. Cambios reales en salud siguen alertando inmediatamente.

**Memoria desync detectado:**
- `agents/shared_conversation.json` congelado en `2026-04-06T03:21:09` — última escritura del bot Telegram hace 22 días.
- Causa probable: el bot escribe el archivo en `/opt/alex-bot/` (o `/home/alexuser/alex-bot/`) en el VPS, NO hace `git push` después → el local repo queda desincronizado.
- `memoria_ALex.md` raíz SÍ tiene actualizaciones hasta 2026-04-23 (canonical). El hueco está solo en shared_conversation.
- **Pendiente arquitectural:** decidir si (a) bot auto-pushea cambios, (b) cron periódico VPS→repo sync, o (c) deprecar shared_conversation.json y basarnos solo en memoria_ALex.md. Hablar con Jorge.

**Lección:**
- Cuando un check de salud reporta el MISMO warning hora tras hora, el sistema debe deduplicar antes de notificar. Este fix establece el patrón para todos los R9 sub-agentes futuros.
- Falsos positivos crónicos en checks deben mejorarse en root cause, no aceptarse como ruido — pero mientras tanto, dedup salva la sanidad del Jefe.

**Skills invocados:** systematic-debugging (síntoma → causa raíz vía Airtable + código), simplify (fix surgical en bloque de alerta).

**Verificación pendiente Jorge:** próximo deep-run del supervisor (~1h después del push) debe alertar UNA vez, luego silencio 23h.

---

### 2026-04-28 — FASE 1 SUPERVISOR AUTÓNOMO: Learning + Recognition (aprobado por Jorge)

**Visión aprobada:** convertir El Supervisor en agente auto-curativo, auto-mejorable y autosuficiente. Roadmap por fases (1=memoria de lecciones · 2=confidence scoring + LLM diagnosis · 3=auto-fix expandido + rollback · 4=self-modification propose-only · 5=auto-merge con whitelist). Empezando por Fase 1 que es 100% no-destructiva (solo añade memoria, no toca infraestructura).

**Implementado en esta sesión (Fase 1 completa):**

1. **Tabla `Lessons_Learned` en Airtable** (id `tbloCtdxSukBI3R3j`, base appfQbDA750Oihy9J).
   Campos: lesson_id, tenant_id, symptom_normalized, symptom_raw, category {infra/pipeline/code/data/unknown}, severity {critical/warning/info}, first_seen_at, last_seen_at, occurrence_count, root_cause, attempted_fixes, last_outcome {resolved/no_effect/worsened/pending}, confidence_score (0-1), recommended_action, requires_human, last_run_id, notes.

2. **Módulo Learning en `agents/supervisor/supervisor.mjs`:**
   - `normalizeSymptom(s)` — exportable global, reemplaza el normalizer inline anterior. Quita `\d+(?:\.\d+)?` → `N` y `[a-f0-9]{8,}` → `ID`. Compartido con dedup de alertas.
   - `classifySymptom(raw)` — Recognition expandido. Regex por categoría:
     * **infra**: APIs, endpoints, crons, services (telegram, airtable, openphone, quo, anthropic, claude, firecrawl, hostinger, dns, smtp), HTTP codes, "X API no responde", "API down/offline/unreachable/timeout/invalid".
     * **pipeline**: contact, seguimiento, fer_first, tbc, ghost/fantasma, seg_sms, stage, lead, deal.
     * **code**: error, exception, failed, throw, stack trace, undefined, null pointer, syntax.
     * **data**: stale, missing, desync, mismatch, orphan, empty, no record, "sin X desde".
     * **unknown**: fallback (debe ser raro tras refinamiento).
   - `loadLessons(cfg, normalized)` — fetch lecciones por symptom_normalized exacto.
   - `recordLessonObservation(cfg, raw, severity, runId)` — upsert: si existe, increment occurrence_count + refresh last_seen_at + symptom_raw sample. Si no, create con occurrence_count=1 y last_outcome="pending".
   - `recordAllObservations(cfg, score, runId)` — paraleliza para todos los warnings + criticals del run actual. Tolerante a fallos individuales.

3. **Integración al main loop:**
   - Solo modos `deep` e `incident` registran observaciones (heartbeat es fast-path, evolve es analítico). Esto evita spam de la tabla.
   - Las observaciones se registran ANTES de la decisión de alerta — la tabla siempre tiene la verdad aunque Telegram esté silenciado por dedup.
   - Failure-tolerant: si Lessons_Learned no existe o Airtable falla, el supervisor completa su run normal.

4. **Config:** `agents/tenants/pinnacle.json` ahora tiene `lessons_learned_table_id: "tbloCtdxSukBI3R3j"`.

5. **Validación end-to-end realizada:**
   - Syntax check: ✓
   - Dry-run deep: ✓ (detectó 2 críticos + 1 warning como esperado)
   - Live test del Learning module contra Airtable: ✓ — primera observación CREATE, segunda con número diferente INCREMENT (count 1→2), tercera (síntoma distinto) CREATE.
   - Classifier: 7/8 samples bien clasificados — único "unknown" residual es el fallback default.

**Lo que el sistema ya puede hacer hoy (post-Fase-1):**
- Recordar cada warning/critical visto, con frecuencia y categoría.
- Detectar lecciones recurrentes (occurrence_count creciente = problema crónico no resuelto).
- Próxima fase puede consultar `loadLessons()` antes de actuar para ver si ya intentamos un fix antes y cómo le fue.

**Pendiente Fase 2 (próxima sesión, requiere aprobación de Jefe):**
- LLM diagnosis: Sonnet 4.6 lee Lessons + signals → propone root_cause + recommended_action por lesson.
- Confidence scoring: basado en historia de outcomes previos (si fix X resolvió este síntoma 3 veces seguidas → confidence 0.9 para volver a aplicarlo).
- Auto-fix decision: HIGH (>0.9) auto-apply | MED apply+alert | LOW propose-only.

**Pendiente Fase 3 (después de Fase 2):**
- Auto-fix expandido más allá de ghost-detection: restart cron stuck, purge cache stale, reset API connections, retry failed sends.
- Verification post-fix: re-run health check 5min después; si health degrada → rollback automático.
- Outcome recording: actualizar `last_outcome` y `attempted_fixes` después de verificación.

**Guardrails operativos NO NEGOCIABLES (recordatorio):**
- Whitelist de acciones: solo operativas, NUNCA credenciales/finanzas/comunicaciones-a-clientes/deletes.
- Circuit breaker: 3 fixes consecutivos que empeoran salud → STOP + escalar.
- Max actions per run, audit trail completo (Airtable + git).
- Self-modification = propose-only. Auto-merge nunca habilitado por el agente solo — decisión humana.

**Skills invocados esta sesión:** systematic-debugging, simplify, agent-designer (visión), brainstorming (arquitectura).

---

### 2026-04-28/29 — FASE 2 SUPERVISOR AUTÓNOMO: LLM Diagnosis + Confidence + Decision (aprobado por Jorge)

**Fase 2 implementada en la misma sesión, inmediatamente después de Fase 1.**

**1. LLM Diagnosis (Sonnet 4.6 vía Anthropic API directa):**
- `callAnthropicAPI(systemPrompt, userPrompt, model, maxTokens)` — fetch directo a `https://api.anthropic.com/v1/messages` con `claude-sonnet-4-5-20250929`. Más liviano que `runClaude` (no spawn CLI). Graceful fallback si `ANTHROPIC_API_KEY` no está disponible.
- `parseFirstJSON(text)` — extrae primer JSON object del output, tolerante a markdown fences y prosa.
- `diagnoseLesson(cfg, lessonRecord, signals)` — para cada lesson nueva o crítica o multiplo de 5 occurrences:
  - System prompt: "senior SRE diagnosing operational symptoms in real estate SaaS automation".
  - Schema JSON estricto: `{ root_cause, recommended_action, requires_human, action_category, safety_notes }`.
  - `action_category` ∈ {cron_restart, cache_purge, api_retry, data_repair, config_update, code_fix, escalate}.
  - `requires_human=true` MANDATORIO si action toca: credenciales, finanzas, comunicaciones-cliente, deletes, schema, fuera de whitelist.
  - El prompt incluye: symptom_raw, category, severity, occurrence_count, last 5 attempted_fixes, signals del run actual (health, pipeline, infra, log freshness).

**2. Confidence Scoring (determinístico, sin LLM):**
- `computeConfidence(lessonFields)` — fórmula:
  - Base: 0 si `requires_human=true` OR sin attempted_fixes.
  - 0.3 baseline cuando hay al menos 1 fix attempted.
  - +0.25 por cada outcome=resolved en últimos 3.
  - -0.1 por cada outcome=no_effect.
  - +0.1 si occurrence_count >= 5, +0.2 si >= 20 (well-known issue bonus).
  - **HARD FLOOR:** any outcome=worsened en últimos 3 → 0.0 (kill-switch absoluto).
  - Clamp [0, 1].
- Verificado con 7 casos: brand new=0, 1 resolved=0.55, 3 resolved consecutive=1.0, 1 worsened in history=0.0, 2 no_effect+1 resolved=0.55, requires_human=siempre 0.

**3. Decision Layer:**
- `decideAction(confidence)`:
  - `>= 0.9` → tier=HIGH, auto_apply=true (Phase 3 ejecutará — Phase 2 solo lo marca).
  - `>= 0.6` → tier=MED, auto_apply=false, alert=true (propone, espera aprobación).
  - `< 0.6` → tier=LOW, escalate_human=true (humano decide).
- **Phase 2 NUNCA ejecuta** — `auto_apply` es flag persistido al lesson para Phase 3.

**4. Integración al main loop:**
- `diagnoseAndDecide(cfg, observations, score, signalsText, runId)` — orquesta diagnosis + scoring + decision por lesson.
- Re-fetch cada lesson (post-recordObservation) para tener `occurrence_count` fresco.
- Skip diagnosis si ya hay `root_cause` y la lección no escaló (severity=critical o occurrence multiple de 5 forza re-diagnosis).
- Persiste a la lesson: `root_cause`, `recommended_action`, `requires_human`, `confidence_score`, `notes` (audit trail con timestamp + action_category + safety_notes).
- **Force-alert override:** si hay decisiones HIGH o MED, anula dedup y manda Telegram aunque warnings sean idénticos al run anterior. Razón: una propuesta nueva es información nueva para el operador.
- Telegram message extendido con `formatDecisionsForTelegram(decisions)`: 3 buckets HIGH/MED/LOW, top 5 por bucket, count de LOW.

**5. Runtime characteristics:**
- Heartbeat (cada 15min): NO hace diagnosis — fast-path.
- Deep (cada 1h): registra observaciones + diagnosis selectiva + decision por cada lesson tocada.
- Incident: igual que deep + spawn automático cuando heartbeat detecta red.
- Evolve (semanal): NO hace diagnosis per-lesson (eso es deep), hace análisis macro de 7 días.

**6. Costo estimado por deep-run (Pinnacle):**
- ~3-5 lessons activas en un run típico → 3-5 calls a Sonnet 4.6.
- ~600 tokens prompt + ~200 tokens output por call.
- Sonnet 4.6 input: $3/Mtok, output: $15/Mtok.
- ~$0.005 per lesson diagnosed × 5 lessons × 24 deep runs/día = ~$0.60/día por tenant. Aceptable.
- Optimización futura: cache diagnosis con hash del symptom_normalized + last_outcome para no re-diagnosticar el mismo problema sin cambios.

**7. Validación realizada:**
- Syntax check: ✓
- Dry-run deep: ✓ (no rompe el flow existente).
- Confidence scoring: 7/7 casos validados localmente.
- LLM diagnosis: pendiente validar contra prod (requiere ANTHROPIC_API_KEY que solo está en GHA secrets). Próximo deep-run scheduled (~1h en GHA) lo ejercitará automáticamente. Graceful fallback si falla.

**Lo que el sistema ya puede hacer hoy (post-Fase-2):**
- Para cada problema recurrente, propone un root_cause hipotético y una acción recomendada.
- Calcula automáticamente cuánta confianza tiene en su propia propuesta basado en historia de fixes previos.
- Decide: HIGH (listo para auto-fix en Fase 3), MED (proponer al humano), LOW (escalar — no sabe qué hacer).
- Nunca actúa solo. Persiste todo a Lessons_Learned para audit trail completo.

**Pendiente Fase 3 (próxima sesión, requiere aprobación):**
- Auto-fix expandido — lee `auto_apply=true` lessons y ejecuta acciones whitelisted (cron restart, cache purge, API retry, data repair).
- Verification post-fix: re-run health check 5min después; si health degrada → rollback automático + outcome=worsened.
- Outcome recording: si después del fix el symptom desaparece del próximo deep-run → outcome=resolved + confidence sube. Si persiste → outcome=no_effect.
- Circuit breaker: 3 fixes consecutivos worsened en cualquier categoría → freeze auto-apply global hasta que humano resetee.

**Skills invocados Fase 2:** agent-designer, error-handling-patterns (graceful fallback), prompt-engineering-patterns (system prompt + JSON schema enforcement), simplify (surgical edits).

---

### 2026-04-29 — TODOS los runs GHA bajados a cada 3 días (orden directa Jorge)

**Razón:** flujo bajo de contactos hoy. Jorge optimiza créditos.

**Cambios:**
- `agents-cron.yml`: 16 schedules R9 → todos `* * */3 * *`. Espaciados cada 30min entre 12:00-20:00 UTC (07:00-15:00 CT verano) para evitar colisiones GHA.
- `supervisor-cron.yml`:
  - **heartbeat** → cada 6 horas (`0 */6 * * *`, 4 runs/día) — watchdog liviano se mantiene activo.
  - **deep** → cada 3 días (`30 21 */3 * *`) — auto-repair + Learning + Diagnosis Fase 2.
  - **evolve** → cada 3 días (`0 22 */3 * *`).
- **Implicación:** detección de fallos críticos en 6h max (no real-time, pero no ciego 3 días). Auto-repair + diagnosis cada 3 días. Jorge consciente, aprobado.
- Hostinger crons (fer_first_contact, fer_seguimiento, fer_stale_cron, fer_morning_brief) **NO se tocaron desde aquí** — están en hPanel manual y son operacionales del pipeline real (Jorge los ajusta si quiere).

**Volumen post-cambio:**
- Antes: ~138 runs/día (~970/sem)
- Ahora: ~0 GHA/día baseline + 16 R9 runs cada 3 días + 3 supervisor cada 3 días = ~19 runs cada 3 días = ~6/día.
- Reducción ~95%.

**Lección:** SaaS cadence debe ser tenant-configurable (R8). Hardcodearlo en yml es deuda. Próxima iteración: leer schedules desde `pinnacle.json` y generar el cron yml por tenant.

---

### 2026-04-29 — FASE 3 SUPERVISOR AUTÓNOMO: Auto-fix + Verification + Rollback + Circuit Breaker

**Aprobado por Jorge — implementado misma sesión que Fases 1+2.**

**1. Whitelist conservadora de acciones automáticas:**
- `api_retry` — re-probe del endpoint que falló (read-only, sin side-effects). Mapeo automático: openphone/airtable/telegram según el symptom.
- `data_repair` — sub-case `stage_drift`: contacts con `Stage='New'` pero `First Contact Step>0` (atascados) → reset a `'To Be Contacted'`. Guarda priorState para rollback.
- **Excluidos (propose-only, no auto):** cron_restart, cache_purge (404 — endpoint pendiente), config_update, code_fix, escalate.
- `requires_human=true` = veto absoluto, jamás se ejecuta automáticamente.

**2. Verification inmediata in-run:**
- Snapshot de `score` antes del fix (warnings + critical).
- Aplica el fix.
- Re-corre `runInfrastructureChecks` + `runPipelineChecks` + `scoreHealth` post-fix.
- `detectOutcome(beforeScore, afterScore, lessonNormalized)`:
  - Si symptom estaba en before pero NO en after → `resolved`.
  - Si symptom persiste → `no_effect`.
  - Si aparecen criticals NUEVOS no presentes antes → `worsened` (trigger rollback).

**3. Rollback automático:**
- Solo para acciones con inverse definido. `data_repair_stage_drift` guarda `priorState` (Stage anterior por record) y `rollbackStageDrift()` los restaura via PATCH bulk a Airtable.
- `api_retry` no necesita rollback (read-only).
- Si rollback no es posible y outcome=worsened → registra en lesson + alert humano.

**4. Circuit Breaker:**
- `checkCircuitBreaker(cfg)` fetcha últimos 5 deeps de Ops_Health, cuenta `phase3_outcomes` que contengan "worsened".
- Si **>= 3 deeps con worsened en últimos 5** → estado OPEN (freeze global). Phase 3 no ejecuta nada en este run, registra razón.
- Reset: humano ajusta tenant config o espera hasta que historial limpio.
- Hard floor: si la query del breaker falla → asume OPEN (fail-safe pesimista).

**5. Recording outcomes:**
- `recordFixAttempt(cfg, lessonRecord, attemptData)`:
  - Append `{run_id, action_category, action, executed, outcome, details, rollback, timestamp}` al array `attempted_fixes` de la lesson.
  - Cap a últimas 20 entries (bounded growth).
  - Update `last_outcome` field.
- Estos outcomes son los que la Fase 2 lee para calcular confidence — **el loop de aprendizaje se cierra aquí**: fix se aplica → outcome registrado → próximo deep, confidence sube/baja según resultado real.

**6. Caps operativos:**
- `PHASE3_MAX_FIXES_PER_RUN = 5` — nunca más de 5 acciones por run (limita blast radius).
- Solo se ejecutan candidatos HIGH-tier (`confidence >= 0.9`) con `auto_apply=true` y action_category en whitelist.
- Modos: solo `deep` e `incident`. Nunca heartbeat (fast-path) ni evolve (analítico).

**7. Persistencia para auditoría:**
- Cada run de Phase 3 escribe a Ops_Health: `phase3_executed` (count), `phase3_outcomes` (CSV), `phase3_breaker_open` (0/1).
- Telegram alert con bloque dedicado:
  - `🔧 *Phase 3 auto-fix (N)*` + lista de actions/outcomes.
  - `⛔ *Phase 3 FROZEN*` si breaker abierto.

**8. Validación:**
- Syntax check: ✓
- Dry-run deep: ✓ (Phase 3 gated correctamente — no ejecuta en dry-run).
- Circuit breaker test contra Ops_Health real: ✓ — 0/5 recent deeps con worsened → CLOSED.
- Stage drift detection contra Airtable real: ✓ — 0 contacts atascados actualmente.
- LLM diagnosis pendiente validar en GHA con `ANTHROPIC_API_KEY`. Graceful fallback si falla.

**El loop completo de auto-curación ya cierra:**
```
1. Recognition → classify(symptom) → infra/pipeline/code/data
2. Recording → Lessons_Learned (occurrence_count, history)
3. Diagnosis → Sonnet 4.6 propone root_cause + recommended_action + safety
4. Confidence → score determinístico desde history of outcomes
5. Decision → HIGH/MED/LOW
6. Action (Phase 3) → ejecuta whitelist si HIGH+auto_apply+breaker closed
7. Verification → re-check inmediato + outcome detection
8. Rollback → si worsened y reversible
9. Recording outcome → cierra el loop, alimenta confidence next time
```

**Lo que el Supervisor YA hace solo:**
- Detecta symptoms recurrentes
- Diagnostica con LLM
- Propone action + safety notes
- Calcula su propia confianza basado en historia
- **EJECUTA fixes whitelisted con confidence alta**
- **VERIFICA que el fix funcionó**
- **HACE ROLLBACK si empeoró**
- Aprende del resultado para próxima vez
- Se congela solo si serie de errores

**Pendiente Fase 4 (next session, requiere aprobación):**
- Self-modification propose-only — el agente puede abrir PRs draft con cambios al código del Supervisor (nunca mergea solo).
- Expandir whitelist con cache_purge cuando endpoint exista en Hostinger.
- Añadir más sub-cases de data_repair (orphan ghost records, duplicate phone numbers).
- Cron restart vía endpoint Hostinger (pendiente: crear `/Tools/cron_trigger.php` con auth).

**Pendiente Fase 5 (requiere mucha confianza acumulada):**
- Auto-merge de PRs propose con whitelist de cambios safe — decisión del humano siempre.

**Skills invocados:** agent-designer, error-handling-patterns, systematic-debugging, simplify, code-review-excellence (revisé surgical edits antes de aplicar).

**Aprobado por:** Jorge Cruz — 2026-04-29

---

### 2026-04-29 — FASE 4 SUPERVISOR AUTÓNOMO: Self-Modification PROPOSE-ONLY

**Aprobado por Jorge — implementado mismo día que Fases 1+2+3.**

**Visión:** el agente puede proponer mejoras a su propio código, NUNCA mergearlas. Cada propuesta = un PR DRAFT etiquetado `human-review-required`.

**Disparador:** SOLO modo `evolve` (cada 3 días con la nueva cadencia, ~10 propuestas potenciales/mes max).

**1. Detector de oportunidades (`detectImprovementOpportunities`):**
- Class A: lessons con `category='unknown'` y `occurrence_count >= 3` → señal de que el classifier no las matchea, hay que añadir regex.
- Class B: lessons con `last_outcome='no_effect'` y `occurrence_count >= 5` → señal de que el threshold actual produce false positives crónicos, hay que ajustar.
- Top 1 opportunity (highest occurrence) se procesa por run.

**2. Patch generator (`proposeSelfPatch` con Sonnet 4.6):**
- Lee snippets actuales: bloque `supervisor + alert_thresholds` de `pinnacle.json` y función `classifySymptom` de `supervisor.mjs`.
- Sonnet propone JSON estricto: `{ file, change_type, search, replace, rationale, test_plan }`.
- `change_type` ∈ `{threshold_adjust, classifier_regex_add}`. NADA más permitido.
- Si el modelo no encuentra cambio safe → retorna `rationale: "no_safe_change"` y se aborta.

**3. Validator (`validatePatch`) — guardrails NO NEGOCIABLES:**
- File whitelist estricta: `agents/tenants/pinnacle.json` y `agents/supervisor/supervisor.mjs`. Cualquier otro = BLOCK.
- Diff size ≤ 50 líneas combinadas (search + replace).
- Para `supervisor.mjs`: forbidden patterns regex que SIEMPRE bloquean: `requires_human`, `PHASE3_WHITELIST`, `PHASE3_MAX_FIXES_PER_RUN`, `PHASE4_*`, `circuit_breaker`, `telegram|airtable|anthropic|api_key`. Y la edición DEBE estar dentro de `classifySymptom`.
- Para `pinnacle.json`: search y replace DEBEN contener un valor numérico (regex `:\s*\d+(?:\.\d+)?\b`). Cualquier cambio no numérico = BLOCK.
- **Test del validator (atajos de ataque):**
  - "Disable requires_human" → BLOCK (forbidden pattern)
  - "Edit credentials" → BLOCK (forbidden pattern)
  - "Edit workflow file" → BLOCK (file not in whitelist)
  - "Non-numeric pinnacle.json change" → BLOCK
  - Threshold adjust válido → PASS
  - Classifier extension válida → PASS

**4. Apply + validate (`applyPatchAndValidate`):**
- Verifica que `search` aparece exactamente UNA vez en el archivo (sin ambigüedad).
- Aplica el reemplazo + escribe.
- Para `.mjs`: corre `node --check`. Si falla → revierte automáticamente.
- Para `.json`: hace `JSON.parse`. Si falla → revierte.

**5. Git ops + PR creation (`gitCommitAndPushBranch` + `createDraftPR`):**
- Branch: `supervisor-autopatch-{run_id_8chars}`.
- Identity local: `supervisor-bot@pinnaclegroupwi.com` (no muta global git config).
- Commit con `change_type` + `lesson_id` + rationale.
- Push a `origin/{branch}`.
- PR via GitHub REST API (`POST /repos/{repo}/pulls` con `draft: true`).
- Body del PR incluye: lesson context, diff summary, rationale, test plan, warning de DRAFT.
- Labels best-effort: `supervisor-self-mod`, `human-review-required`.

**6. Hard caps (`runPhase4SelfModification`):**
- `PHASE4_MAX_OPEN_AUTOPRS = 3`: si ya hay 3+ auto-PRs abiertos sin revisar, FREEZE — no se proponen nuevos.
- `PHASE4_MAX_DIFF_LINES = 50`.
- 1 propuesta máxima por run.
- Solo en evolve mode.
- Requiere `GITHUB_TOKEN` (auto-disponible en GHA, ausente local → graceful skip).

**7. Telegram alert si proposed:**
- Bloque dedicado `🤖 *Phase 4 self-modification PR*` con: change_type, file, lesson_id, PR url, status (DRAFT — requires review).
- Force-alert override: cualquier PR auto rompe dedup 24h.

**8. Validación realizada:**
- Syntax check: ✓
- Validator con 6 casos (4 ataques + 2 válidos): 6/6 correctos.
- Opportunity detector live (Airtable real): 0 oportunidades hoy (esperado, sistema joven sin lessons recurrentes aún).
- Dry-run evolve: tarda por evolveAnalysis sin API key local — comportamiento esperado, en GHA con secrets corre normal.

**Lo que el sistema ya hace SOLO:**
1. Detecta su propio código tiene un bug recurrente o un threshold mal calibrado.
2. Pide a Sonnet 4.6 una propuesta de patch surgical.
3. Valida que el patch no toca guardrails críticos ni sale del whitelist.
4. Aplica localmente, valida sintaxis.
5. Crea branch, commitea, pushea.
6. Abre PR DRAFT con rationale completo.
7. Te alerta a Telegram con el link.
8. **NUNCA mergea.** Tú revisas, ajustas, mergeas o cierras.

**Defensa contra prompt injection / jailbreak:**
- Aunque el LLM proponga un patch que intente disable requires_human o tocar credenciales, el `validatePatch` lo BLOQUEA antes de aplicar.
- Aunque el LLM intente cambiar un workflow, el file whitelist lo BLOQUEA.
- El cap de 3 PRs abiertos previene que un loop runaway abra cientos de PRs.

**Pendiente Fase 5 (decisión humana, NUNCA habilitada por el agente):**
- Auto-merge de PRs auto cuando: (a) hayan acumulado N éxitos consecutivos sin reverts, (b) el cambio esté en una sub-whitelist aún más estrecha, (c) jorge habilite manualmente vía tenant config flag.
- Mientras Phase 5 no exista, todo cambio queda en DRAFT esperando revisión humana — eso es by design.

**Skills invocados Fase 4:** agent-designer, security-pen-testing (validator attack tests), prompt-engineering-patterns (system prompt + JSON schema), error-handling-patterns (graceful fallback + auto-revert), simplify.

**Aprobado por:** Jorge Cruz — 2026-04-29

---

## 2026-05-02 — SESIÓN: Telegram→GHA bridges, Skills Suite, Director v2 cherry-pick, Airtable cleanup

### 1. Telegram bot ↔ GHA bridges (Creativo + Director)

**Patrón nuevo:** los `_tool_invoke_*` del bot ya NO ejecutan pipelines en el bot. Disparan `agents-cron.yml` vía `https://pinnaclegroupwi.com/agents/github_dispatch.php` con `X-Alex-Secret` header. El cron remoto hace el trabajo pesado.

**Creativo** (`telegram_bot/alex_bot.py:1180`):
- Sin record_id → `mode=batch` (3 ideas pendientes/run, ~3-5 min)
- Con record_id → `mode=one` (regenera ese visual específico, sobreescribe `visual_url`)
- Tool description actualizada para que el LLM extraiga record_id de mensajes tipo "regenera el visual de recXXX"
- Validado end-to-end: `rec1j0KYhvNGmKTlO` regenerado, `visual_url` cambió de `cixnxrmhcv1m` → `ktoy3fwoi2mx` (nuevo Cloudinary upload).

**Director v2** (`telegram_bot/alex_bot.py:1250`):
- Mismo patrón. `mode=batch` procesa hasta 10 Reels pendientes.
- Filtro Airtable: `Formato=Reel,Status=Nueva,Visual_Prompt set,visual_url empty,Error_Reason empty`.
- HeyGen branch (Jorge habla) pendiente de API key.

**Workflow update** (`.github/workflows/agents-cron.yml`):
- Nuevo input `record_id` (opcional) en `workflow_dispatch`
- Step `run` añade `--record-id $RECORD_ID` si está presente
- Bug fix: tenía `env:` block duplicado al inicio que rompía YAML — ahora consolidado

### 2. Airtable Schema cleanup + recreate

**Tabla `Ideas de Contenido` (`appU9s3kGkVpdrJkw / tblAj0Pkj1jW4p5Ld`):**

Borrado vía UI (API no soporta DELETE de field, solo CREATE/UPDATE):
- `Branding_Spec` (12% pop, orphan en código)
- `Blotato_Template_ID` (46% pop, no usado por nuevo Creativo Puppeteer)

Re-creado vía Meta API después (director_v2 los necesita):
- `video_duration` (number precision 1) — `fldgWpORIRHdXMMW4`
- `video_cost_cents` (number precision 0) — `fld63klplj0o6O85q`
- `Error_Reason` (multilineText) — `fldiyHueGHFfmeb1h`

**Estado final**: 22 → 18 → 21 fields (neto: -1).

**Limpieza de código** (refs a campos borrados): `agents/social_media/runner.mjs`, `agents/social_media.md`, `agents/creativo.md`, `agents/director.md`, `telegram_bot/alex_bot.py:1285`. Verificado con grep — 0 refs restantes.

### 3. Skills Suite — 15 skills nuevas instaladas globalmente

Todas en `~/.agents/skills/` (symlink Claude Code), instaladas vía `npx skills add ... --yes --global`:

**Diseño/UI (14 skills — leonxlnx + pbakaus + emilkowalski):**
`impeccable`, `emil-design-eng`, `design-taste-frontend`, `gpt-taste`, `high-end-visual-design`, `minimalist-ui`, `industrial-brutalist-ui`, `redesign-existing-projects`, `stitch-design-taste`, `image-to-code`, `imagegen-frontend-web`, `imagegen-frontend-mobile`, `brandkit`, `full-output-enforcement`.

Default Pinnacle aesthetic (homeowners en distress, NO tech): editorial limpio + warmth — primarias `minimalist-ui` + `high-end-visual-design` + `impeccable`.

**Codebase intelligence (1 skill — safishamsi):**
`graphify` (`~/.claude/skills/graphify/SKILL.md`, CLI `graphify` desde PyPI `graphifyy` v0.6.2 + 25 tree-sitter parsers). Trigger `/graphify`. Output `graphify-out/` (gitignored).

**Reglas registradas en `CLAUDE.md`:**
- §1e — Design Taste Suite obligatoria para cualquier UI/HTML/CSS/JSX/popup PHP/email HTML
- §1f — graphify obligatorio para refactor/audit cross-file (3+ archivos)
- También en `agents/CLAUDE.md` para sub-agentes

### 4. Director v2 — Cherry-pick de `claude/greeting-setup-yOfqf`

**Origen**: branch nunca mergeada a master (detectada vía `git for-each-ref --contains`). 50+ archivos, 14 task commits con tests (42+ test cases).

**Extracción surgical** (NO merge total, solo subtree): `git checkout origin/claude/greeting-setup-yOfqf -- agents/director_v2/`.

**Cambios aplicados**:
- Renombrado `main.mjs` → `director_v2.mjs` (matches cron pattern `agents/${agent}/${agent}.mjs`)
- `package.json` scripts actualizados: `node main.mjs` → `node director_v2.mjs`
- `main.mjs:155` Status='Lista' → Status='Visual Listo' (reusa opción existente, evitando PATCH a singleSelect que API rechaza)
- `safePatchError` ya no escribe Status='Error' (singleSelect no lo tiene), solo `Error_Reason`
- `airtable.mjs:7` filter actualizado: añadido `{Error_Reason}=''` para evitar reintentar errores
- Wired en `agents-cron.yml`: nuevo cron `30 21 */3 * *`, install ffmpeg + npm ci en director_v2/, env aliases `AIRTABLE_SM_TOKEN/BASE_ID/TABLE_ID`

**Decisión arquitectónica — Director Hybrid (Opción 4):**
- `Tipo=Personal / "Jorge habla"` → HeyGen avatar (PENDIENTE: API key, avatar_id, voice_id_en/es)
- `Tipo=Educativo/Promocional` → director_v2 actual = silent kinetic (Pexels stock + texto + música, NO voiceover)
- ElevenLabs descartado — silent kinetic basta para faceless

**Música**: 5 tracks reales subidos por Jorge a GitHub el 2026-05-02 (chill/cinematic/tension/upbeat 1+2). 94-130s, 255kbps, max_volume 0.0 dB (verificado con ffprobe). El `LICENSES.md` original mentía sobre "stubs silenciosos" — corregido. Files con extensión doble `.mp3.mp3` por GitHub UI quirk — corregido vía `git mv`.

### 5. HeyGen integration research (Jorge confirmó querer Hybrid Opción 4)

**HeyGen tiene 2 productos para Claude (oficiales):**
1. **MCP Server** (`heygen-com/heygen-mcp`, hosted en `https://mcp.heygen.com/mcp/v1/`) — para Claude Desktop interactivo. Tools: `generate_avatar_video`, `get_avatar_video_status`, `get_avatars`, `get_voices`, `get_remaining_credits`.
2. **Skills** (`heygen-com/skills`) — para Claude Code SDK / Cursor / etc.

**Verdict para Pinnacle**: para producción (GHA cron) usamos **REST API directo**, NO el MCP/Skills (que son para uso interactivo). Solo necesitamos:
- `HEYGEN_API_KEY` (en Doppler cuando Jorge la consiga)
- `HEYGEN_AVATAR_ID_JORGE`
- `HEYGEN_VOICE_ID_JORGE_EN` + `HEYGEN_VOICE_ID_JORGE_ES`

**Plan HeyGen necesario**: Creator $29/mes (incluye Photo Avatar custom + 200 créditos = ~10 min premium o 30 min estándar). Cost API ~$1/min estándar, ~$3/min Avatar IV. Volumen Pinnacle (4-8 reels/mes) ≈ $31-33/mes total.

### 6. Otros cambios cableados

- `.gitignore`: añadido `graphify-out/` y `.graphify/`
- Workflow YAML duplicate `env:` bug fix (`agents-cron.yml`)
- B (merge work branch → master): pushed `b874b2b → 3ab209f → d38d906 → c46f6fc`

### 7. Estado al cierre de sesión

| Componente | Status |
|---|---|
| Creativo | Producción (GHA cron 3d + bot dispatch + regenerate) |
| Director v2 (faceless) | Wired completo, esperando confirmación de Doppler creds (PEXELS, GEMINI, REPLICATE, CLOUDINARY) — primer test corriendo |
| Director HeyGen (avatar Jorge) | Esperando que Jorge consiga API key + avatar entrenado |
| Programador (FB+IG publish) | Esperando Meta Page Token + IG Business ID en Doppler |
| Backlog Creativo | 7 ideas pendientes (cron las clear cada 3 días, batch manual disponible) |

### 8. Lecciones aprendidas (anti-regresión)

- **Airtable Meta API NO soporta DELETE de field** — solo UI. Para CREATE de fields y opciones de singleSelect, lo PATCH de opciones también puede fallar (422) en algunos casos — pivot a usar opciones existentes en lugar de añadir nuevas.
- **Branch huérfana con código clave**: `git for-each-ref --contains <commit>` localiza ramas que tienen un commit aunque no estén mergeadas a master. Usar para detectar trabajo perdido.
- **Cherry-pick subtree > merge total** cuando una branch tiene 100+ commits divergentes pero solo necesitamos 1 directorio.
- **Audio file naming via GitHub UI**: si subes `chill_1.mp3` desde el sistema operativo donde Windows oculta extensiones, el upload puede quedar como `chill_1.mp3.mp3`. Verificar con `ls` post-upload o usar `git mv` para corregir.
- **Auto-save hooks** del repo committea + pushea cada ~30s. Funciona, pero stop-hook puede dispararse entre commits — no es bug, es ventana transitoria.
- **`/graphify` es overkill para 1-2 archivos** — usar grep/Read directo. graphify se justifica para 3+ archivos o dependencias no obvias.

---
