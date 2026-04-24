#!/usr/bin/env node
// Production orchestrator for El Creativo v2.
// Reads pending Airtable records, renders carousels, uploads to Cloudinary, updates Airtable.
// Run via: doppler run -- node agents/creativo_v2/main.mjs [--dry-run]

import { mkdir, rm } from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import { buildCarousel, dimsForAspect } from './src/themes.mjs';
import { wrapSlideHtml } from './src/wrapper.mjs';
import { renderJpg, closeBrowser } from './src/render.mjs';
import { listPending, parseVisualPrompt, updateRecord } from './src/airtable.mjs';
import { uploadCarousel } from './src/cloudinary.mjs';

export function resolveWeek(record) {
  const raw = record?.fields?.Semana;
  if (raw == null || raw === '') return 0;
  const n = typeof raw === 'number' ? raw : parseInt(raw, 10);
  return Number.isFinite(n) ? n : 0;
}

export function safeSummary(s) {
  return String(s || '').slice(0, 200);
}

async function renderToTmp(spec, recordId) {
  const dir = path.join(os.tmpdir(), `creativo_v2_${recordId}_${Date.now()}`);
  await mkdir(dir, { recursive: true });
  const slides = buildCarousel(spec);
  const paths = [];
  for (let i = 0; i < slides.length; i++) {
    const out = path.join(dir, `slide_${i + 1}.jpg`);
    const html = wrapSlideHtml(slides[i], spec.theme);
    await renderJpg(html, out);
    paths.push(out);
  }
  return { dir, paths };
}

async function processRecord(record, { dryRun }) {
  const id = record.id;
  const log = (msg) => console.log(`[${id}] ${msg}`);
  log('starting');

  let spec;
  try {
    spec = parseVisualPrompt(record.fields?.Visual_Prompt || '');
  } catch (e) {
    log(`parse failed: ${e.message}`);
    if (!dryRun) {
      try { await updateRecord(id, { Status: 'Error', Error_Reason: safeSummary(e.message) }); } catch {}
    }
    return { id, ok: false, reason: 'parse' };
  }

  let render;
  try {
    render = await renderToTmp(spec, id);
    log(`rendered ${render.paths.length} slides to ${render.dir}`);
  } catch (e) {
    log(`render failed: ${e.message}`);
    if (!dryRun) {
      try { await updateRecord(id, { Status: 'Error', Error_Reason: safeSummary('render: ' + e.message) }); } catch {}
    }
    return { id, ok: false, reason: 'render' };
  }

  if (dryRun) {
    log(`DRY-RUN ok — not uploading or updating Airtable. JPGs in: ${render.dir}`);
    return { id, ok: true, dryRun: true, dir: render.dir };
  }

  let urls;
  try {
    urls = await uploadCarousel(render.paths, { recordId: id, week: resolveWeek(record) });
    log(`uploaded ${urls.length} to Cloudinary`);
  } catch (e) {
    log(`upload failed: ${e.message}`);
    try { await updateRecord(id, { Status: 'Error Upload', Error_Reason: safeSummary(e.message) }); } catch {}
    return { id, ok: false, reason: 'upload' };
  }

  try {
    await updateRecord(id, { visual_url: urls[0], Status: 'Lista para Publicar' });
    log('Airtable updated Status=Lista para Publicar');
  } catch (e) {
    log(`airtable update failed: ${e.message}`);
    return { id, ok: false, reason: 'patch' };
  }

  try { await rm(render.dir, { recursive: true, force: true }); } catch {}

  return { id, ok: true, urls };
}

async function main() {
  const dryRun = process.argv.includes('--dry-run');
  console.log(`Creativo v2 main${dryRun ? ' (DRY-RUN)' : ''}`);
  const records = await listPending();
  console.log(`Pending records: ${records.length}`);

  const results = [];
  for (const r of records) {
    results.push(await processRecord(r, { dryRun }));
  }
  await closeBrowser();

  const ok = results.filter(r => r.ok).length;
  const fail = results.length - ok;
  console.log(`\nSummary: ${ok} ok, ${fail} fail (of ${results.length})`);
  if (fail > 0) process.exit(1);
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main().catch(err => { console.error('fatal:', err); process.exit(1); });
}
