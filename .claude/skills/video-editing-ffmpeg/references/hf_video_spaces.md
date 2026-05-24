# Hugging Face Spaces — Curated for Pinnacle Video Pipeline

This is the catalog of Hugging Face Spaces verified as relevant for ALEX's video production needs. All are invocable via the `mcp__claude_ai_Hugging_Face__dynamic_space` MCP tool with `operation: "invoke"`. Authenticated as `Geocarp`.

**Discovery commands:**
```
mcp__claude_ai_Hugging_Face__dynamic_space(operation: "discover")
mcp__claude_ai_Hugging_Face__dynamic_space(operation: "view_parameters", space_name: "<id>")
mcp__claude_ai_Hugging_Face__dynamic_space(operation: "invoke", space_name: "<id>", parameters: "<json>")
```

ZeroGPU spaces = $0 cost but rate-limited at peak hours. `mcp-tools/*` namespace is optimized for programmatic invocation.

---

## 🎬 Image-to-Video (animate MLS property photos)

Use when: you have a static photo of a property and want a cinematic 3-5s motion clip (dolly-in, slow pan, parallax) for use as a Reel intro or background asset.

| Space | Likes | Notes |
|---|---|---|
| `zerogpu-aoti/wan2-2-fp8da-aoti-faster` | 3,118 | **PRIMARY CHOICE** — Wan 2.2 14B FP8 quantized + Lightning LoRA. 4-8 steps. Default 3.5s @ 24fps. |
| `r3gm/wan2-2-fp8da-aoti-preview-2` | 1,309 | Higher-fidelity preview variant. |
| `cbensimon/wan2-2-fp8da-aoti-preview2` | 130 | Same engine, smaller queue. |
| `mcp-tools/wan-2-2-first-last-frame` | — | Interpolates between a start image and an end image — perfect for **before/after renovation reveals**. |
| `multimodalart/wan2-1-fast` | 1,607 | Older Wan 2.1, faster, lower quality fallback. |
| `alexnasa/ltx-2-TURBO` | 482 | LTX-2 Turbo — generates video **+ audio together**. |

**Wan 2.2 parameters cheat sheet** (from `view_parameters`):
- `input_image` — URL (http/https), must be publicly accessible
- `prompt` — motion description (default: "make this image come alive, cinematic motion, smooth animation")
- `duration_seconds` — 1.0–5.0 (default 3.5)
- `steps` — 4–8 (default 6; more = slower + higher quality)
- `randomize_seed` — true (recommended)

**Output:** mp4 path + seed. Pipe through ffmpeg (`video-editing-ffmpeg` Pattern 5) to reformat to 1080×1920.

---

## 👄 Talking-Head & Lip-Sync (HeyGen alternative for Jorge avatars)

Use when: you have an audio file of Jorge speaking + a portrait photo, and want a lip-synced talking-head video for a Reel. **Free alternative to HeyGen.**

| Space | Likes | Use case |
|---|---|---|
| `fffiloni/LatentSync` | 594 | **PRIMARY** — Audio-conditioned LipSync via Latent Diffusion. Highest fidelity for Spanish audio. |
| `fffiloni/MEMO` | 51 | Memory-Guided Diffusion — most expressive talking video. |
| `fffiloni/KDTalker` | 154 | Single photo + audio → talking-head. Lighter weight. |
| `fffiloni/EchoMimic` | 158 | Audio-driven portrait animations, more stylized. |

**Workflow:**
1. Record Jorge's audio in Spanish (regla 1h — mono-language)
2. Pick a clean portrait photo (1:1 or 9:16 framing)
3. Invoke `fffiloni/LatentSync` with audio + image
4. Output mp4 → ffmpeg reformat to 1080×1920 + crop/scale
5. Upload to Cloudinary, store URL in Airtable `Reels` table

**Limitation:** lip-sync models trained mostly on English. Spanish phonemes work but may show occasional articulation slips on /ll/, /ñ/, rolled /rr/. Test before scaling.

---

## ✨ Restoration & Upscale (improve old MLS photos / low-light testimonials)

Use when: source footage is grainy, low-res, or has compression artifacts.

| Space | Likes | Best for |
|---|---|---|
| `fffiloni/SVFR-demo` | 189 | **Face restoration in video** — homeowner testimonials shot with phone |
| `Fabrice-TIERCELIN/RealESRGAN` | 14 | Generic 4× upscale for **images AND video** |
| `fffiloni/PASD` | 245 | Magnify subject details (image-only, but useful for thumbnails) |
| `leonelhs/superface` | 15 | Face-specific restoration (image) |

**Pipeline:** Run upscale **before** the ffmpeg Pattern 5 reformat — avoid double-resampling artifacts.

---

## 🎭 Background Removal & Greenscreen

Use when: you want to composite Jorge over a custom background (a Pinnacle-branded background, a property photo, a map).

| Space | Likes | Notes |
|---|---|---|
| `Luminia/CorridorKey` | 0 | **Removes green/blue screen even with glass and hair** — handles tough cases |
| `not-lain/background-removal` | — | Image-only, BiRefNet |
| `luca115/background-removal` | 6 | BiRefNet, image-only |

**Workflow for Reels with Jorge:**
1. Record Jorge in front of greenscreen
2. `Luminia/CorridorKey` → transparent video (alpha channel)
3. ffmpeg `overlay` filter to composite over custom bg
4. See `video-editing-ffmpeg` SKILL.md Pattern 6 for audio mix

---

## 🛠️ Auxiliary Image Tools (for slide PNG generation)

These don't generate video but feed the slide pipeline (Puppeteer + ffmpeg `overlay`):

| Space | Use |
|---|---|
| `mcp-tools/FLUX.1-Krea-dev` | Beautiful high-quality natural images for slide backgrounds (NO text — regla 1d) |
| `mcp-tools/Qwen-Image` | High-quality image gen, excels at text placement — STILL avoid for Spanish text per regla 1d |
| `mcp-tools/FLUX.1-Kontext-Dev` | Edit existing images with text prompts (NON-text edits only) |
| `prithivMLmods/Photo-Mate-i2i` | Watermark/object removal, restoration |
| `fffiloni/diffusers-image-outpaint` | Outpainting — extend a photo's background |
| `fffiloni/InstantIR` | Restore low-quality photos + creative mode |

---

## ❌ NOT for Pinnacle (avoid)

| Space type | Why excluded |
|---|---|
| Any image-to-image that adds text | Hallucinates Spanish spelling (regla 1d) |
| `prithivMLmods/Qwen-Image-Edit-2509-LoRAs-Fast` for text edits | Same reason |
| Long-form video generators (>15s) | Output >18s violates regla 1g — split into Parte 1/2/3 instead |
| Anime / stylized character generators | Off-brand for Pinnacle homeowner audience |

---

## Invocation Template

```javascript
// Animate property photo
const result = await mcp__claude_ai_Hugging_Face__dynamic_space({
  operation: "invoke",
  space_name: "zerogpu-aoti/wan2-2-fp8da-aoti-faster",
  parameters: JSON.stringify({
    input_image: "https://res.cloudinary.com/pinnacle/property_123.jpg",
    prompt: "slow cinematic dolly-in, warm afternoon light, smooth pan, real estate showcase",
    duration_seconds: 3.5,
    steps: 6,
    randomize_seed: true
  })
});
// → result.video_path (mp4 file)
// Pipe through ffmpeg Pattern 5 to reformat to 1080×1920 for Reels
```

---

## Cost & Rate Limit Notes

- **ZeroGPU spaces** = free with HF account (`Geocarp` logged in). Quota resets daily.
- **Peak hours** (US daytime) have longer queues — schedule batch renders in cron at 02:00–06:00 UTC.
- **Auth header**: handled automatically by the MCP server. No token needed in `parameters`.
- **Fallback chain**: if `zerogpu-aoti/wan2-2-fp8da-aoti-faster` is overloaded, try `cbensimon/wan2-2-fp8da-aoti-preview2` (same model, smaller queue).

---

## Updating This Catalog

Re-run discovery monthly to pick up new Spaces:
```
mcp__claude_ai_Hugging_Face__space_search(query: "video editing OR image-to-video OR lip sync", mcp: true, limit: 20)
mcp__claude_ai_Hugging_Face__dynamic_space(operation: "discover")
```

Last refresh: 2026-05-23.
