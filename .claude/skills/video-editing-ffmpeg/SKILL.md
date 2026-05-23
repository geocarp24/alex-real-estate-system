---
name: "video-editing-ffmpeg"
description: "When the user wants to edit, render, compose, transcode, or process video files using ffmpeg — including Reels production for Pinnacle's Director v2 agent (5 slides × 3s = 15s output), slideshow generation from images, xfade transitions, drawtext overlays, concat/trim/scale, audio mixing, vertical 9:16 reformatting, GIF conversion, and frame extraction. Also use when the user mentions 'ffmpeg,' 'video render,' 'video compose,' 'slideshow,' 'xfade,' 'concat videos,' 'overlay text on video,' 'reel render,' 'crop video,' 'vertical video,' 'mp4 output,' 'mobile video for IG/FB,' or 'Director v2.' Covers Director v2 specs (narrative_B 5×3s budget, duration 7-18 cap, ffmpeg with xfade overlap 0.6s producing ~15s output), Pinnacle bilingual mono-language constraint (no ES+EN in same render), and Cloudinary upload integration. NOT for video generation from text/image via AI (use Hugging Face Spaces wan2-2 / LatentSync / KDTalker — see references/hf_video_spaces.md). NOT for live streaming or RTMP."
license: MIT
metadata:
  version: 1.0.0
  author: ALEX (Pinnacle)
  category: video-production
  updated: 2026-05-23
  related-rules: ["CLAUDE.md regla 1g (Video Length — 15s output)", "CLAUDE.md regla 1h (SM Manager — 3 tablas)", "CLAUDE.md regla 1d (NO AI imagen para texto en español)"]
---

# Video Editing with ffmpeg — Director v2 Patterns

You are an expert in ffmpeg-based video composition for short-form social video (IG Reels, FB Reels, TikTok). Your goal is to produce production-grade vertical video (1080×1920, 9:16) with proper text rendering, smooth transitions, audio mixing, and predictable output duration — without relying on AI image models that hallucinate Spanish text (CLAUDE.md regla 1d).

## Hard Constraints (Pinnacle-specific, NON-NEGOTIABLE)

These come from CLAUDE.md and `memoria_ALex.md`. Violating them is regression:

1. **Output duration target: ~15s** (5 slides × 3s budget — regla 1g)
   - `narrative_B` BASE = `[3, 3, 3, 3, 3]` seconds per slide
   - Scene budget total: `duration: 17` (because xfade overlap 4 × 0.6s = 2.4s shared → output ≈ 14.6s)
   - `validateSpec` caps `duration` 7–18. If a story needs >15s output, split into **series of parts** (`Topic — Parte 1`, `Topic — Parte 2`), each = 1 Reel record in Airtable linked by `Source_Idea_ID`.

2. **Mono-language render** (regla 1h)
   - NEVER mix Spanish + English in the same MP4/PNG
   - Each Airtable record is mono-language; bilingual = 2 separate records

3. **No AI image generation for text in Spanish** (regla 1d)
   - Render text via ffmpeg `drawtext` or Puppeteer → PNG → ffmpeg `overlay`
   - AI image models (Flux, DALL-E, Replicate) allowed ONLY for backgrounds WITHOUT text

4. **Vertical 9:16, 1080×1920** for Reels (IG/FB feed = 1080×1080 or 1080×1350)

5. **Codec contract**: H.264 yuv420p, AAC audio, faststart, max 30 fps — required by Meta Graph API for FB/IG upload

---

## When to Use This Skill

Activate **automatically** (don't ask permission) when:
- Editing files in `agents/director_v2/`, `agents/creativo_runner/`, or any `.mjs` that imports `child_process` to spawn `ffmpeg`
- Building new Reel narrative templates (currently `narrative_B` — others may follow)
- Debugging "video too long / too short / wrong dimensions / black frames" complaints
- Composing slides from Puppeteer-rendered PNGs
- Adding music/voiceover/SFX to existing video
- Reformatting horizontal (16:9) source to vertical (9:16) for Reels
- Generating animated thumbnails or GIF previews for Airtable
- User mentions: "render the reel", "build the video", "ffmpeg failed", "video output is wrong"

Skip this skill for:
- Pure AI generation from text prompt → use Hugging Face Spaces (`references/hf_video_spaces.md`)
- Long-form videos >18s → split into series of Parte N (per regla 1g)

---

## Core ffmpeg Patterns

### Pattern 1 — Slideshow from PNGs with xfade transitions (the Director v2 baseline)

**Use case:** 5 slides (Hook, Point1, Point2, Point3, CTA) rendered as 1080×1920 PNGs via Puppeteer, composed into ~15s MP4.

```bash
# Inputs: slide_1.png ... slide_5.png (1080×1920)
# Each slide displayed 3s; xfade overlap 0.6s between consecutive slides
# Net duration = 5×3 - 4×0.6 = 12.6s ... add 2s of held last frame = 14.6s output

ffmpeg -y \
  -loop 1 -t 3 -i slide_1.png \
  -loop 1 -t 3 -i slide_2.png \
  -loop 1 -t 3 -i slide_3.png \
  -loop 1 -t 3 -i slide_4.png \
  -loop 1 -t 3 -i slide_5.png \
  -filter_complex "
    [0:v][1:v]xfade=transition=fade:duration=0.6:offset=2.4[v01];
    [v01][2:v]xfade=transition=fade:duration=0.6:offset=4.8[v02];
    [v02][3:v]xfade=transition=fade:duration=0.6:offset=7.2[v03];
    [v03][4:v]xfade=transition=fade:duration=0.6:offset=9.6[vout]
  " \
  -map "[vout]" \
  -c:v libx264 -pix_fmt yuv420p -preset medium -crf 20 \
  -movflags +faststart \
  -r 30 \
  reel_output.mp4
```

**Offset math (CRITICAL — easy to break):**
- xfade offset[N] = (N+1) × per_slide_duration − N × transition_duration
- For 3s slides and 0.6s transitions: offsets = 2.4, 4.8, 7.2, 9.6
- If you change `narrative_B` per-slide budget, **recalculate offsets** — never guess

### Pattern 2 — Slide with drawtext overlay (in-ffmpeg text, no Puppeteer)

```bash
ffmpeg -y -loop 1 -t 3 -i background.jpg \
  -vf "drawtext=fontfile='C\\:/Windows/Fonts/Inter-Bold.ttf':\
       text='SELL MY HOUSE FAST':\
       fontcolor=white:fontsize=72:\
       box=1:boxcolor=black@0.5:boxborderw=20:\
       x=(w-text_w)/2:y=h*0.4" \
  -c:v libx264 -pix_fmt yuv420p -preset fast -crf 20 \
  slide_drawtext.mp4
```

**Windows path escape:** `C\\:/Windows/Fonts/...` — the backslash before colon is required.
**Linux/GHA runner:** use `/usr/share/fonts/.../Inter-Bold.ttf` — no escape needed.

### Pattern 3 — Concat without re-encoding (when chunks share codec)

```bash
# Create concat list (file paths must NOT contain quotes; use forward slashes)
cat > concat_list.txt <<EOF
file 'chunk_1.mp4'
file 'chunk_2.mp4'
file 'chunk_3.mp4'
EOF

ffmpeg -y -f concat -safe 0 -i concat_list.txt -c copy joined.mp4
```

**Gotcha:** all chunks must share **identical** resolution, codec, fps, pixel format, audio sample rate. Otherwise → re-encode with concat filter (Pattern 4).

### Pattern 4 — Concat with re-encode (different codecs/sizes)

```bash
ffmpeg -y \
  -i clip_a.mp4 -i clip_b.mp4 -i clip_c.mp4 \
  -filter_complex "
    [0:v]scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2,setsar=1[v0];
    [1:v]scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2,setsar=1[v1];
    [2:v]scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2,setsar=1[v2];
    [v0][0:a][v1][1:a][v2][2:a]concat=n=3:v=1:a=1[vout][aout]
  " \
  -map "[vout]" -map "[aout]" \
  -c:v libx264 -pix_fmt yuv420p -c:a aac -ar 44100 \
  combined.mp4
```

### Pattern 5 — Horizontal (16:9) source → Vertical (9:16) for Reels

```bash
# Option A: crop center (loses left/right content)
ffmpeg -i horiz_1920x1080.mp4 -vf "crop=ih*9/16:ih,scale=1080:1920" vert_crop.mp4

# Option B: scale + blurred background (Instagram-style)
ffmpeg -i horiz.mp4 -filter_complex "
  [0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,boxblur=20:5[bg];
  [0:v]scale=1080:-2[fg];
  [bg][fg]overlay=(W-w)/2:(H-h)/2
" -c:a copy vert_blurred.mp4
```

### Pattern 6 — Audio mixing (voiceover + background music)

```bash
ffmpeg -y -i video_silent.mp4 -i voiceover.wav -i music.mp3 \
  -filter_complex "
    [1:a]volume=1.0[vo];
    [2:a]volume=0.15[bg];
    [vo][bg]amix=inputs=2:duration=first[aout]
  " \
  -map 0:v -map "[aout]" \
  -c:v copy -c:a aac -shortest \
  video_with_audio.mp4
```

### Pattern 7 — Burn-in captions from SRT (for accessibility / silent autoplay)

```bash
ffmpeg -i video.mp4 -vf "subtitles=captions.srt:force_style='Fontname=Inter,Fontsize=20,PrimaryColour=&H00FFFFFF,OutlineColour=&H80000000,Outline=2,Alignment=2,MarginV=100'" -c:a copy video_captioned.mp4
```

### Pattern 8 — Thumbnail / GIF preview for Airtable

```bash
# Single thumbnail at 1s
ffmpeg -i reel.mp4 -ss 00:00:01.000 -vframes 1 -q:v 2 thumb.jpg

# Animated GIF preview (3s, low res)
ffmpeg -i reel.mp4 -t 3 -vf "fps=10,scale=320:-1:flags=lanczos" -loop 0 preview.gif
```

---

## Validation Before Render (run BEFORE spending Cloudinary/storage)

Always probe inputs and validate spec before render:

```bash
# Check duration, codec, dimensions
ffprobe -v error -select_streams v:0 -show_entries stream=width,height,r_frame_rate,duration -of csv=p=0 input.mp4

# Validate slide PNG dimensions match 1080×1920
ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 slide_1.png
# expect: 1080,1920
```

**Spec validation contract** (matches `agents/director_v2/src/narratives/index.mjs::validateSpec`):
- `duration` must be 7–18 (else throw)
- `slides.length === 5` (per regla 1g)
- Each slide PNG must exist on disk before invoking ffmpeg
- Total per-slide budget × N must equal `spec.duration` ± 1s

---

## Common Failure Modes & Fixes

| Symptom | Root cause | Fix |
|---|---|---|
| Output is black/blank | Wrong xfade offset math | Recalculate: offset[N] = (N+1)*duration − N*transition |
| "moov atom not found" | Missing `-movflags +faststart` | Add `-movflags +faststart` for web playback |
| Meta Graph API rejects MP4 | Wrong pixel format or H.265 | Force `-pix_fmt yuv420p -c:v libx264` |
| Text shows boxes/squares | Font file not found | Use absolute path; on Windows escape colon `C\\:/...` |
| Audio out of sync after concat | Different sample rates | Add `-ar 44100` to all inputs before concat |
| Video too long (>15s) | Per-slide budget creep | Reset `narrative_B` BASE to `[3,3,3,3,3]`, never exceed |
| Vertical render shows letterbox bars | `scale` without crop+pad | Use Pattern 5 Option B (blurred bg) |
| GHA runner OOM | `-preset slow` on large files | Use `-preset medium` + `-crf 23` |

---

## Integration Points (where this skill fires in the Pinnacle stack)

| File | Skill activation |
|---|---|
| `agents/director_v2/src/render.mjs` (if/when implemented) | Pattern 1 + 2 + Validation |
| `agents/director_v2/src/narratives/narrative_B.mjs` | Hard constraint: BASE = `[3,3,3,3,3]` |
| `agents/director_v2/src/narratives/index.mjs::validateSpec` | duration cap 7–18 |
| `agents/director_v2/src/airtable.mjs::buildSpecFromReelRecord` | `duration: 17` |
| `.github/workflows/agents-cron.yml` Director v2 step | ffmpeg installed via `apt-get install -y ffmpeg` |
| `agents/creativo_runner/themes.mjs` | NO ffmpeg here — Puppeteer-only (regla 1d) |

---

## Composition with Other Skills

When working on video, also invoke (per CLAUDE.md regla 1e):
- `responsive-design` — confirm 9:16 aspect ratio
- `mobile-ios-design` — Reels are mobile-first (regla 1b)
- `accessibility-compliance` + `a11y-audit` — captions burn-in (Pattern 7)
- `senior-frontend` — when text overlay involves HTML/CSS via Puppeteer
- `simplify` — after every ffmpeg pipeline change (regla 1)
- `verification-before-completion` — never declare "rendered" without `ffprobe` confirming output spec

For AI-generated source clips (image-to-video from Wan, talking-head from LatentSync):
- See `references/hf_video_spaces.md` for the curated Hugging Face Spaces catalog
- Always pass HF output through this skill's Pattern 5 to enforce 9:16 + Meta codec contract

---

## Anti-Regression Rules

1. NEVER hardcode per-slide duration in ffmpeg commands — read from `narrative_B.mjs::BASE`
2. NEVER produce >18s output — if narrative needs more, split into series of Parte N
3. NEVER mix ES + EN in same render — enforce mono-language at Airtable record level
4. NEVER use AI image models for slides with Spanish text — Puppeteer/HTML+CSS or ffmpeg drawtext only
5. NEVER skip `ffprobe` validation before reporting "rendered" — `verification-before-completion`
6. NEVER commit `graphify-out/` or rendered MP4s to master — they regenerate

---

## Quick Reference Card

```
Per-slide budget:       3.0 seconds
Transition:             xfade fade, 0.6 seconds
Slide count:            5 (Hook, P1, P2, P3, CTA)
Scene budget:           17 seconds (allows 0.6×4 overlap)
Output duration:        ~14.6 seconds
Resolution (Reel):      1080 × 1920 (9:16)
Resolution (Feed):      1080 × 1080 or 1080 × 1350 (4:5)
Codec video:            libx264 + yuv420p
Codec audio:            aac, 44.1kHz
fps cap:                30
Required flag:          -movflags +faststart
Max duration cap:       18s (validateSpec)
Min duration cap:       7s  (validateSpec)
```

Full Hugging Face Spaces catalog for AI video generation: see `references/hf_video_spaces.md`.
