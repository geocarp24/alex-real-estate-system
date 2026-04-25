#!/usr/bin/env node
// Director v2 — production orchestrator
// Usage:   doppler run -- node main.mjs [--dry-run]

import { mkdir, rm, readFile, stat, writeFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

import { listPending, parseVisualPrompt, updateRecord } from './src/airtable.mjs';
import { expandNarrative, validateSpec } from './src/narratives/index.mjs';
import { buildSceneHtml } from './src/scene_layout.mjs';
import { wrapSlideHtml } from './src/wrapper.mjs';
import { renderScene, closeBrowser } from './src/render.mjs';
import { searchPortrait, downloadToFile, PexelsNoResultsError } from './src/pexels.mjs';
import { generateImage, NanoBananaFailedError } from './src/nano_banana.mjs';
import { pickMusic } from './src/audio.mjs';
import { buildVideoCommand, runFfmpeg } from './src/ffmpeg.mjs';
import { uploadVideo } from './src/cloudinary.mjs';
import { sanitizeRecordId } from './src/util/sanitize.mjs';
import { registerNanoBananaCall, enforcePerVideoBudget, shouldForcePexelsFallback } from './src/cost_control.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const TMP  = join(HERE, 'tmp');
const SAMPLES = join(HERE, 'samples');

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

async function resolveHero(scene, { pexelsKey, geminiKey, replicateKey, tmpDir, stats, forcePexels }) {
  const heroPath = join(tmpDir, `hero_${scene.index}.bin`);
  let effectiveSource = scene.heroSource;
  if (forcePexels && (effectiveSource === 'nano_banana' || effectiveSource === 'flux_schnell')) {
    effectiveSource = 'pexels';
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

async function processRecord(record, { env, dryRun, stats }) {
  const recordId = sanitizeRecordId(record.id);
  const recordTmp = join(TMP, recordId);
  await mkdir(recordTmp, { recursive: true });

  const spec = parseVisualPrompt(record.fields.Visual_Prompt);
  validateSpec(spec);
  const scenes = expandNarrative(spec);
  enforcePerVideoBudget(scenes);
  const forcePexels = await shouldForcePexelsFallback(scenes);

  const frameOutputs = [];
  for (const scene of scenes) {
    const hero = await resolveHero(scene, {
      pexelsKey: env.PEXELS_API_KEY, geminiKey: env.GEMINI_API_KEY, replicateKey: env.REPLICATE_API_TOKEN, tmpDir: recordTmp, stats, forcePexels,
    });
    const body = buildSceneHtml(scene, hero.path, spec.theme, spec.aspect);
    const html = wrapSlideHtml(body, spec.theme, spec.aspect);
    const files = await renderScene(html, scene, recordTmp);
    frameOutputs.push({ index: scene.index, duration: scene.duration, imagePaths: files, zoompan: scene.zoompan, transitionOut: scene.transitionOut, kinetic: scene.kinetic });
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
    Status: 'Lista',
    Error_Reason: '',
  }, env);
}

async function safePatchError(recordId, reason, env) {
  try {
    await updateRecord(sanitizeRecordId(recordId), { Status: 'Error', Error_Reason: reason }, env);
  } catch (e) {
    console.error(`[patch_failed] ${recordId}: ${shortMessage(e)}`);
  }
}

async function main() {
  const dryRun = process.argv.includes('--dry-run');
  const env = {
    token: process.env.AIRTABLE_SM_TOKEN,
    baseId: process.env.AIRTABLE_SM_BASE_ID,
    tableId: process.env.AIRTABLE_SM_TABLE_ID,
    PEXELS_API_KEY: process.env.PEXELS_API_KEY,
    GEMINI_API_KEY: process.env.GEMINI_API_KEY,
    CLOUDINARY_NAME: process.env.CLOUDINARY_NAME,
    CLOUDINARY_API_KEY: process.env.CLOUDINARY_API_KEY,
    CLOUDINARY_API_SECRET: process.env.CLOUDINARY_API_SECRET,
  };
  for (const [k, v] of Object.entries(env)) {
    if (!v) { console.error(`ERROR: env ${k} missing (Doppler)`); process.exit(1); }
  }

  await rm(TMP, { recursive: true, force: true });
  await mkdir(TMP, { recursive: true });
  await mkdir(SAMPLES, { recursive: true });

  const start = Date.now();
  const stats = { ok: 0, error: 0, fallback: 0, nanoBananaCalls: 0, nanoBananaCents: 0, pexelsCalls: 0, uploadMb: 0, durationMs: 0 };

  const pending = await listPending(env);
  console.log(`Director v2 — ${pending.length} pending reel record(s)${dryRun ? ' (dry-run)' : ''}`);

  for (const record of pending) {
    try {
      await processRecord(record, { env, dryRun, stats });
      stats.ok++;
    } catch (err) {
      stats.error++;
      const msg = shortMessage(err);
      console.error(`[error] ${record.id}: ${msg}`);
      if (!dryRun) await safePatchError(record.id, msg, env);
    }
  }

  stats.durationMs = Date.now() - start;
  await closeBrowser();
  console.log(summarize(stats));
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main().catch(err => { console.error('FAIL:', err); process.exit(1); });
}
