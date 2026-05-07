#!/usr/bin/env node
// Director v2 — production orchestrator
// Usage:   doppler run -- node director_v2.mjs [--dry-run] [--record-id rec...]

import { mkdir, rm, readFile, stat, writeFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

import { listPending, fetchOne, parseVisualPrompt, updateRecord } from './src/airtable.mjs';
import { expandNarrative, validateSpec } from './src/narratives/index.mjs';
import { buildSceneHtml } from './src/scene_layout.mjs';
import { wrapSlideHtml } from './src/wrapper.mjs';
import { renderScene, closeBrowser } from './src/render.mjs';
import { searchPortrait, downloadToFile, PexelsNoResultsError } from './src/pexels.mjs';
import { generateImage, NanoBananaFailedError } from './src/nano_banana.mjs';
import { generateAvatarVideo, downloadVideo, pickVoiceId, HeyGenFailedError } from './src/heygen.mjs';
import { generateImage as toolkitGenerateImage, isAvailable as toolkitAvailable, VideoToolkitError } from './src/video_toolkit.mjs';
import { pickMusic } from './src/audio.mjs';
import { buildVideoCommand, runFfmpeg } from './src/ffmpeg.mjs';
import { uploadVideo, tryDownloadCachedVideo, buildVideoUrl } from './src/cloudinary.mjs';
import { createHash } from 'node:crypto';
import { sanitizeRecordId } from './src/util/sanitize.mjs';
import { registerNanoBananaCall, enforcePerVideoBudget, shouldForcePexelsFallback } from './src/cost_control.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const TMP  = join(HERE, 'tmp');
const SAMPLES = join(HERE, 'samples');

// Wrap caption text for 1080-wide portrait at fontsize ~62 (≈22 chars/line is the sweet spot for IG Reels readability).
export function wrapCaption(text, maxChars = 22) {
  if (!text) return '';
  const lines = [];
  for (const para of String(text).split(/\r?\n/)) {
    const words = para.trim().split(/\s+/).filter(Boolean);
    let line = '';
    for (const word of words) {
      const candidate = line ? `${line} ${word}` : word;
      if (candidate.length <= maxChars) line = candidate;
      else { if (line) lines.push(line); line = word; }
    }
    if (line) lines.push(line);
  }
  return lines.join('\n');
}

// ASS time format: H:MM:SS.cs (centiseconds). Used for Dialogue start/end fields.
function formatAssTime(secs) {
  const total = Math.max(0, Math.round(secs * 100));
  const cs = total % 100, totalSec = Math.floor(total / 100);
  const ss = totalSec % 60, totalMin = Math.floor(totalSec / 60);
  const mm = totalMin % 60, hh = Math.floor(totalMin / 60);
  return `${hh}:${String(mm).padStart(2,'0')}:${String(ss).padStart(2,'0')}.${String(cs).padStart(2,'0')}`;
}

// IG Reels-style karaoke captions via ASS + libass.
// PrimaryColour = active/spoken word color (bright yellow). SecondaryColour = un-spoken color (white).
// `\kf` (fill karaoke) sweeps each word from secondary → primary over its slice of the scene duration.
// For non-HeyGen scenes (no voice), karaoke=false → static white text for the whole duration.
export function buildAssSubtitle({ text, durationSec, karaoke = false, scriptForKaraoke = '' }) {
  if (!text || !durationSec) return '';
  const wrapped  = wrapCaption(text);                                 // visual wrap for non-karaoke fallback
  const safeText = wrapped.replace(/\n/g, '\\N').replace(/[{}]/g, '');
  const start    = formatAssTime(0);
  const end      = formatAssTime(durationSec);

  let dialogueText;
  if (karaoke && scriptForKaraoke) {
    // Distribute total duration evenly across the spoken words (linear forced-alignment estimate — accurate enough at 0.4-0.5s/word).
    const words = scriptForKaraoke.trim().split(/\s+/).filter(Boolean);
    if (!words.length) {
      dialogueText = safeText;
    } else {
      const totalCs   = Math.round(durationSec * 100);
      const perWord   = Math.floor(totalCs / words.length);
      let remainder   = totalCs - perWord * words.length;
      // Wrap karaoke output every ~3 words to keep on screen, matching ~22 char visual rule.
      const lineEvery = 3;
      const tagged = words.map((w, i) => {
        let cs = perWord; if (remainder > 0) { cs++; remainder--; }
        const sep = (i > 0 && i % lineEvery === 0) ? '\\N' : (i > 0 ? ' ' : '');
        return `${sep}{\\kf${cs}}${w.replace(/[{}]/g, '')}`;
      }).join('');
      dialogueText = tagged;
    }
  } else {
    dialogueText = safeText;
  }

  // ASS color format: &HBBGGRR&  (alpha 00 = opaque). Yellow = #FFEB3B → BGR 3BEBFF. White = #FFFFFF.
  return [
    '[Script Info]',
    'ScriptType: v4.00+',
    'PlayResX: 1080',
    'PlayResY: 1920',
    'WrapStyle: 0',
    'ScaledBorderAndShadow: yes',
    '',
    '[V4+ Styles]',
    'Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding',
    'Style: Default,DejaVu Sans,68,&H003BEBFF,&H00FFFFFF,&H00000000,&H80000000,1,0,0,0,100,100,1,0,1,5,3,2,40,40,260,1',
    '',
    '[Events]',
    'Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text',
    `Dialogue: 0,${start},${end},Default,,0,0,0,,${dialogueText}`,
    '',
  ].join('\n');
}

export function shortMessage(err) {
  const name = err?.name || 'Error';
  const msg  = String(err?.message || err || 'unknown');
  return `${name}: ${msg}`.slice(0, 200);
}

export function summarize(stats) {
  const cents = stats.nanoBananaCents | 0;
  const dollars = (cents / 100).toFixed(2);
  const mins = Math.floor(stats.durationMs / 60000);
  const secs = Math.floor((stats.durationMs % 60000) / 1000);
  return `
════════════════════════════════════════
Director v2 — Batch Summary
════════════════════════════════════════
Records procesados:  ${stats.ok + stats.error}
 ├─ Lista:           ${stats.ok}
 ├─ Error:            ${stats.error}
 └─ Fallback:         ${stats.fallback}
Nano Banana calls:  ${stats.nanoBananaCalls} ($${dollars})
Pexels calls:       ${stats.pexelsCalls}
Cloudinary uploads: ${stats.ok} (total ${stats.uploadMb.toFixed(1)} MB)
Duración total:     ${mins}m ${secs}s
════════════════════════════════════════`.trim();
}

// Stable hash of HeyGen call inputs — drives cache invalidation.
// If avatarId/voiceId/script/engine/resolution/background changes, hash changes → regenerate.
function heygenCacheKey({ avatarId, voiceId, script, engine, resolution, background }) {
  const blob = JSON.stringify({ avatarId, voiceId, script, engine, resolution, bg: background?.value || background?.url || '' });
  return createHash('sha1').update(blob).digest('hex').slice(0, 10);
}

async function resolveHero(scene, { pexelsKey, geminiKey, replicateKey, heygenEnv, tmpDir, stats, forcePexels, recordId }) {
  const heroPath = join(tmpDir, `hero_${scene.index}.bin`);
  let effectiveSource = scene.heroSource;
  if (forcePexels && (effectiveSource === 'nano_banana' || effectiveSource === 'flux_schnell' || effectiveSource === 'heygen_avatar')) {
    effectiveSource = 'pexels';
  }

  if (effectiveSource === 'heygen_avatar') {
    if (!heygenEnv?.HEYGEN_API_KEY || !heygenEnv?.HEYGEN_AVATAR_ID_JORGE) {
      console.error(`[scene ${scene.index}] heygen_avatar requested but HEYGEN_API_KEY/AVATAR_ID missing — falling back to pexels`);
      stats.fallback++;
      effectiveSource = 'pexels';
    } else {
      try {
        const voiceId = pickVoiceId(scene.locale || 'en', heygenEnv);
        if (!voiceId) throw new HeyGenFailedError(`HEYGEN_VOICE_ID_JORGE_${(scene.locale || 'en').toUpperCase()} missing`);
        const videoPath = join(tmpDir, `hero_${scene.index}.mp4`);

        // Cache lookup BEFORE calling HeyGen — saves $$$ when re-rendering same record.
        const heygenInputs = {
          avatarId:   heygenEnv.HEYGEN_AVATAR_ID_JORGE,
          voiceId,
          script:     scene.heyScript || scene.text || scene.heroPrompt,
          engine:     scene.heyEngine || 'v3',
          resolution: scene.heyResolution || '1080p',
          background: scene.heyBackground || { type: 'color', value: '#0d1117' },
        };
        const cacheKey = heygenCacheKey(heygenInputs);
        const cachePublicId = `cache/${recordId}_scene_${scene.index}_heygen_${cacheKey}`;
        const cacheFolder   = 'pinnacle-social-media/videos/directorv2';
        if (heygenEnv.CLOUDINARY_NAME) {
          const cacheUrl = buildVideoUrl({ cloudName: heygenEnv.CLOUDINARY_NAME, folder: cacheFolder, publicId: cachePublicId });
          const cached = await tryDownloadCachedVideo(cacheUrl, videoPath);
          if (cached.hit) {
            console.log(`[scene ${scene.index}] HeyGen cache HIT (${(cached.sizeBytes/1024).toFixed(0)}KB) — skipping API call`);
            stats.heygenCacheHits = (stats.heygenCacheHits || 0) + 1;
            return { path: videoPath, sourceActual: 'heygen_avatar_cached', isVideo: true, durationSec: null };
          }
        }

        // motion_prompt + expressiveness are photo_avatar-only — only forward when explicitly set.
        const { videoUrl, durationSec } = await generateAvatarVideo({
          ...heygenInputs,
          apiKey:        heygenEnv.HEYGEN_API_KEY,
          aspectRatio:   '9:16',
          expressiveness: scene.heyExpressiveness,
          motionPrompt:   scene.heyMotionPrompt,
          speed:          typeof scene.heySpeed === 'number' ? scene.heySpeed : 1.1,
        });
        await downloadVideo(videoUrl, videoPath);
        stats.heygenCalls = (stats.heygenCalls || 0) + 1;
        stats.heygenSeconds = (stats.heygenSeconds || 0) + (durationSec || 0);

        // Upload to Cloudinary cache so future re-renders skip the HeyGen call entirely.
        if (heygenEnv.CLOUDINARY_NAME && heygenEnv.CLOUDINARY_API_KEY && heygenEnv.CLOUDINARY_API_SECRET) {
          try {
            await uploadVideo(videoPath, {
              publicId: cachePublicId, folder: cacheFolder,
              cloudName: heygenEnv.CLOUDINARY_NAME, apiKey: heygenEnv.CLOUDINARY_API_KEY, apiSecret: heygenEnv.CLOUDINARY_API_SECRET,
            });
            console.log(`[scene ${scene.index}] HeyGen MP4 cached at ${cachePublicId}`);
          } catch (e) { console.error(`[scene ${scene.index}] cache upload failed: ${e.message} — continuing`); }
        }
        return { path: videoPath, sourceActual: 'heygen_avatar', isVideo: true, durationSec };
      } catch (err) {
        if (!(err instanceof HeyGenFailedError)) throw err;
        // HeyGen failures: fall back to flux2 (uses heroPrompt) before pexels (which needs heroQuery,
        // not set on hook/cta scenes). flux2 → nano_banana → pexels chain handles all spec shapes.
        console.error(`[scene ${scene.index}] HeyGen failed: ${err.message} — falling back to flux2`);
        stats.fallback++;
        effectiveSource = 'flux2';
      }
    }
  }

  if (effectiveSource === 'flux2') {
    if (!toolkitAvailable('image')) {
      console.error(`[scene ${scene.index}] flux2 requested but MODAL_FLUX2_ENDPOINT_URL missing — falling back to nano_banana`);
      stats.fallback++;
      effectiveSource = 'nano_banana';
    } else {
      try {
        const heroPng = join(tmpDir, `hero_${scene.index}.png`);
        // Narrative B point scenes have heroPrompt=null and use heroQuery for Pexels.
        // When the Tipo routing forces flux2 we synthesize a prompt from the caption + query.
        const promptFromHeading = scene.heroPrompt
          || `Pinnacle Holdings real estate scene matching "${scene.captionEn || scene.heroQuery || 'wisconsin home'}", cinematic, golden hour, warm light, 9:16 vertical, no text in image`;
        await toolkitGenerateImage({ prompt: promptFromHeading, outputPath: heroPng, width: 1080, height: 1920 });
        stats.flux2Calls = (stats.flux2Calls || 0) + 1;
        return { path: heroPng, sourceActual: 'flux2' };
      } catch (err) {
        if (!(err instanceof VideoToolkitError)) throw err;
        console.error(`[scene ${scene.index}] flux2 failed: ${err.message} — falling back to nano_banana`);
        stats.fallback++;
        effectiveSource = 'nano_banana';
      }
    }
  }

  if (effectiveSource === 'flux_schnell') {
    try {
      const replicateMod = await import('./src/replicate_image.mjs');
      const { imageBuffer, costCents } = await replicateMod.generateImage(scene.heroPrompt, { apiKey: replicateKey });
      stats.replicateCalls = (stats.replicateCalls || 0) + 1;
      stats.replicateCents = (stats.replicateCents || 0) + costCents;
      await writeFile(heroPath, imageBuffer);
      return { path: heroPath, sourceActual: 'flux_schnell' };
    } catch (err) {
      if (!(err instanceof (await import('./src/replicate_image.mjs')).ReplicateFailedError)) throw err;
      stats.fallback++;
      effectiveSource = 'nano_banana';
    }
  }

  if (effectiveSource === 'nano_banana') {
    try {
      const { imageBuffer, costCents } = await generateImage(scene.heroPrompt, { apiKey: geminiKey });
      stats.nanoBananaCalls++;
      stats.nanoBananaCents += costCents;
      await registerNanoBananaCall(costCents);
      await writeFile(heroPath, imageBuffer);
      return { path: heroPath, sourceActual: 'nano_banana' };
    } catch (err) {
      if (!(err instanceof NanoBananaFailedError)) throw err;
      stats.fallback++;
    }
    effectiveSource = 'pexels';
    scene.heroQuery = scene.heroQuery || 'real estate wisconsin';
  }

  if (effectiveSource === 'pexels') {
    try {
      const photo = await searchPortrait(scene.heroQuery, { apiKey: pexelsKey });
      stats.pexelsCalls++;
      await downloadToFile(photo.downloadUrl, heroPath);
      return { path: heroPath, sourceActual: 'pexels' };
    } catch (err) {
      if (err instanceof PexelsNoResultsError) {
        stats.fallback++;
        effectiveSource = 'theme_solid';
      } else throw err;
    }
  }

  return { path: null, sourceActual: 'theme_solid' };
}

// Override scene.heroSource based on Airtable Tipo field and available endpoints.
// Plan A+B (Jorge 2026-05-04, refined 2026-05-06):
//   Personal → hybrid: hook+CTA = HeyGen Jorge talking; points = flux2 cinematic (variety + cost).
//   Educativo/Tip/Caso/Brand → all flux2 premium AI.
//   default → keep spec value (Pexels/nano_banana per spec).
function applyTipoContenidoRouting(scenes, tipo, env, locale = 'es') {
  const t = String(tipo || '').toLowerCase();
  const lang = locale === 'en' ? 'en' : 'es';
  if (!t) return;
  if (t === 'personal' && env.HEYGEN_API_KEY && env.HEYGEN_AVATAR_ID_JORGE) {
    for (const s of scenes) {
      // Hook + CTA scenes get Jorge talking via HeyGen; point scenes stay on flux2 cinematic.
      if (s.layoutType === 'hook' || s.layoutType === 'cta') {
        s.heroSource = 'heygen_avatar';
        s.locale = lang;                                              // drives pickVoiceId() inside resolveHero
        // Honor locale: EN scripts → captionEn first; ES scripts → captionEs first.
        s.heyScript = s.heyScript || (lang === 'en'
          ? (s.captionEn || s.captionEs)
          : (s.captionEs || s.captionEn));
      } else if (s.layoutType === 'point' && env.MODAL_FLUX2_ENDPOINT_URL) {
        s.heroSource = 'flux2';
      }
    }
  } else if (['educativo','tip','caso','brand'].includes(t) && env.MODAL_FLUX2_ENDPOINT_URL) {
    for (const s of scenes) if (!s.heroSource || s.heroSource === 'pexels' || s.heroSource === 'nano_banana') s.heroSource = 'flux2';
  }
}

async function processRecord(record, { env, dryRun, stats }) {
  const recordId = sanitizeRecordId(record.id);
  const recordTmp = join(TMP, recordId);
  await mkdir(recordTmp, { recursive: true });

  const spec = parseVisualPrompt(record.fields.Visual_Prompt);
  validateSpec(spec);
  const scenes = expandNarrative(spec);
  applyTipoContenidoRouting(scenes, record.fields.Tipo, env);
  enforcePerVideoBudget(scenes);
  const forcePexels = await shouldForcePexelsFallback(scenes);

  const captionLocale = spec.locale === 'en' ? 'en' : 'es';
  const captionField  = captionLocale === 'en' ? 'captionEn' : 'captionEs';
  const frameOutputs = [];
  for (const scene of scenes) {
    const hero = await resolveHero(scene, {
      pexelsKey: env.PEXELS_API_KEY, geminiKey: env.GEMINI_API_KEY, replicateKey: env.REPLICATE_API_TOKEN,
      heygenEnv: env, tmpDir: recordTmp, stats, forcePexels, recordId,
    });

    const isHeyGen = hero.isVideo;
    const captionText = scene[captionField] || scene.captionEs || scene.captionEn || '';
    // Karaoke ASS for HeyGen scenes (Jorge speaks → highlight word-by-word). Plain ASS for FLUX2 scenes (no voice).
    // ASS via libass `subtitles` filter renders TikTok/IG-style highlighted captions natively — no per-word frame rendering.
    const sceneDur = isHeyGen ? (hero.durationSec || scene.duration) : scene.duration;
    const assBody  = buildAssSubtitle({ text: captionText, durationSec: sceneDur, karaoke: isHeyGen, scriptForKaraoke: scene.heyScript || captionText });
    const assFile  = join(recordTmp, `caption_${scene.index}.ass`);
    if (assBody) await writeFile(assFile, assBody, 'utf8');

    if (isHeyGen) {
      frameOutputs.push({ index: scene.index, duration: sceneDur, videoPath: hero.path, transitionOut: scene.transitionOut, captionFile: assBody ? assFile : null });
      continue;
    }
    const body = buildSceneHtml(scene, hero.path, spec.theme, spec.aspect);
    const html = wrapSlideHtml(body, spec.theme, spec.aspect);
    const files = await renderScene(html, scene, recordTmp);
    frameOutputs.push({ index: scene.index, duration: scene.duration, imagePaths: files, zoompan: scene.zoompan, transitionOut: scene.transitionOut, kinetic: scene.kinetic, captionFile: assBody ? assFile : null });
  }

  const musicPath = pickMusic(spec.mood || 'upbeat', scenes.reduce((t, s) => t + s.duration, 0));
  const outputPath = dryRun ? join(SAMPLES, `dry_run_${recordId}.mp4`) : join(recordTmp, `${recordId}.mp4`);
  await mkdir(dirname(outputPath), { recursive: true });

  const cmd = buildVideoCommand({ scenes: frameOutputs, musicPath, outputPath });
  await runFfmpeg(cmd);
  const { size } = await stat(outputPath);
  stats.uploadMb += size / 1_048_576;

  if (dryRun) {
    console.log(`[dry-run] ${recordId} → ${outputPath}`);
    return;
  }

  const upload = await uploadVideo(outputPath, {
    publicId: `directorv2/${recordId}`,
    folder: 'pinnacle-social-media/videos',
    cloudName: env.CLOUDINARY_NAME,
    apiKey: env.CLOUDINARY_API_KEY,
    apiSecret: env.CLOUDINARY_API_SECRET,
  });

  await updateRecord(recordId, {
    visual_url: upload.secure_url,
    video_duration: upload.duration || (scenes.reduce((t, s) => t + s.duration, 0) - 0.3 * (scenes.length - 1)),
    video_cost_cents: stats.nanoBananaCents,
    Status: 'Visual Listo',
    Error_Reason: '',
  }, env);
}

async function safePatchError(recordId, reason, env) {
  try {
    await updateRecord(sanitizeRecordId(recordId), { Error_Reason: reason }, env);
  } catch (e) {
    console.error(`[patch_failed] ${recordId}: ${shortMessage(e)}`);
  }
}

function parseArgs(argv) {
  const out = { dryRun: false, recordId: null };
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--dry-run') out.dryRun = true;
    else if (a === '--record-id') out.recordId = argv[++i] || null;
    else if (a.startsWith('--record-id=')) out.recordId = a.slice('--record-id='.length);
  }
  return out;
}

async function main() {
  const { dryRun, recordId } = parseArgs(process.argv);
  console.log(`[director_v2] boot pid=${process.pid} node=${process.version} dryRun=${dryRun} recordId=${recordId || 'none'}`);

  const env = {
    token: process.env.AIRTABLE_SM_TOKEN,
    baseId: process.env.AIRTABLE_SM_BASE_ID,
    tableId: process.env.AIRTABLE_SM_TABLE_ID,
    PEXELS_API_KEY: process.env.PEXELS_API_KEY,
    GEMINI_API_KEY: process.env.GEMINI_API_KEY,
    REPLICATE_API_TOKEN: process.env.REPLICATE_API_TOKEN,
    CLOUDINARY_NAME: process.env.CLOUDINARY_NAME,
    CLOUDINARY_API_KEY: process.env.CLOUDINARY_API_KEY,
    CLOUDINARY_API_SECRET: process.env.CLOUDINARY_API_SECRET,
    HEYGEN_API_KEY: process.env.HEYGEN_API_KEY,
    HEYGEN_AVATAR_ID_JORGE: process.env.HEYGEN_AVATAR_ID_JORGE,
    HEYGEN_VOICE_ID_JORGE_EN: process.env.HEYGEN_VOICE_ID_JORGE_EN,
    HEYGEN_VOICE_ID_JORGE_ES: process.env.HEYGEN_VOICE_ID_JORGE_ES,
    MODAL_QWEN3_TTS_ENDPOINT_URL: process.env.MODAL_QWEN3_TTS_ENDPOINT_URL,
    MODAL_FLUX2_ENDPOINT_URL:     process.env.MODAL_FLUX2_ENDPOINT_URL,
    MODAL_LTX2_ENDPOINT_URL:      process.env.MODAL_LTX2_ENDPOINT_URL,
    MODAL_IMAGE_EDIT_ENDPOINT_URL: process.env.MODAL_IMAGE_EDIT_ENDPOINT_URL,
  };

  // Diagnostic: print env presence (not values) so silent failures show which secret is missing.
  const presence = Object.fromEntries(Object.entries(env).map(([k, v]) => [k, v ? 'set' : 'MISSING']));
  console.log(`[director_v2] env presence: ${JSON.stringify(presence)}`);

  // Hard-required: Airtable + Pexels + Cloudinary (no fallback).
  // Soft-optional: Gemini (Nano Banana premium AI) + Replicate (Flux Schnell standard AI).
  // If soft are missing, runner falls back to Pexels-only stock for hero scenes.
  const HARD_REQUIRED = ['token', 'baseId', 'tableId', 'PEXELS_API_KEY', 'CLOUDINARY_NAME', 'CLOUDINARY_API_KEY', 'CLOUDINARY_API_SECRET'];
  const missing = HARD_REQUIRED.filter(k => !env[k]);
  if (missing.length) {
    console.error(`ERROR: HARD_REQUIRED env missing: ${missing.join(', ')} — check Doppler/GHA secrets`);
    process.exit(1);
  }
  if (!env.GEMINI_API_KEY)      console.error('WARN: GEMINI_API_KEY missing — Nano Banana premium tier disabled, will use Replicate or Pexels fallback');
  if (!env.REPLICATE_API_TOKEN) console.error('WARN: REPLICATE_API_TOKEN missing — Flux Schnell standard tier disabled, will use Pexels fallback');
  if (!env.HEYGEN_API_KEY)      console.error('WARN: HEYGEN_API_KEY missing — heygen_avatar scenes will fall back to faceless pipeline');

  await rm(TMP, { recursive: true, force: true });
  await mkdir(TMP, { recursive: true });
  await mkdir(SAMPLES, { recursive: true });

  const start = Date.now();
  const stats = { ok: 0, error: 0, fallback: 0, nanoBananaCalls: 0, nanoBananaCents: 0, pexelsCalls: 0, uploadMb: 0, durationMs: 0 };

  let queue;
  if (recordId) {
    console.log(`[director_v2] single-record mode: fetching ${recordId}`);
    try {
      const record = await fetchOne(recordId, env);
      queue = [record];
    } catch (err) {
      console.error(`[director_v2] fetchOne failed: ${shortMessage(err)}`);
      if (!dryRun) await safePatchError(recordId, shortMessage(err), env);
      process.exit(1);
    }
  } else {
    queue = await listPending(env);
  }
  console.log(`[director_v2] ${queue.length} record(s) to process${dryRun ? ' (dry-run)' : ''}`);

  for (const record of queue) {
    console.log(`[director_v2] → ${record.id}`);
    try {
      await processRecord(record, { env, dryRun, stats });
      stats.ok++;
      console.log(`[director_v2] ✓ ${record.id}`);
    } catch (err) {
      stats.error++;
      const msg = shortMessage(err);
      console.error(`[director_v2] ✗ ${record.id}: ${msg}`);
      if (err?.stack) console.error(err.stack);
      if (!dryRun) await safePatchError(record.id, msg, env);
    }
  }

  stats.durationMs = Date.now() - start;
  await closeBrowser();
  console.log(summarize(stats));
}

if (import.meta.url === `file://${process.argv[1]}`) {
  process.on('uncaughtException', (err) => {
    console.error(`[director_v2] uncaughtException: ${err?.stack || err}`);
    process.exit(1);
  });
  process.on('unhandledRejection', (reason) => {
    console.error(`[director_v2] unhandledRejection: ${reason?.stack || reason}`);
    process.exit(1);
  });
  main().catch(err => { console.error('FAIL:', err?.stack || err); process.exit(1); });
}
