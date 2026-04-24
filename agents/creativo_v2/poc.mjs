#!/usr/bin/env node
// POC orchestrator: reads spec/poc_t1_5reasons.json, renders 6 JPGs to output/.

import { readFile, mkdir, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildCarousel, dimsForAspect } from './src/themes.mjs';
import { wrapSlideHtml } from './src/wrapper.mjs';
import { renderJpg, closeBrowser } from './src/render.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SPEC_PATH = path.join(__dirname, 'spec', 'poc_t1_5reasons.json');
const OUTPUT_DIR = path.join(__dirname, 'output');

async function main() {
  const t0 = Date.now();
  console.log('Creativo v2 POC — rendering T1 carousel');
  console.log('Spec:', SPEC_PATH);

  const spec = JSON.parse(await readFile(SPEC_PATH, 'utf8'));
  console.log(`Theme: ${spec.theme} | Hook: ${spec.hook.en}`);
  console.log(`Points: ${spec.points.length} | Total slides: ${1 + spec.points.length + 1}`);

  const slides = buildCarousel(spec);
  if (slides.length !== 6) {
    throw new Error(`Expected 6 slides, got ${slides.length}`);
  }

  await mkdir(OUTPUT_DIR, { recursive: true });

  const results = [];
  for (let i = 0; i < slides.length; i++) {
    const idx = i + 1;
    const outPath = path.join(OUTPUT_DIR, `poc_t1_slide_${idx}.jpg`);
    const html = wrapSlideHtml(slides[i], spec.theme);
    process.stdout.write(`  Rendering slide ${idx}/6... `);
    await renderJpg(html, outPath);
    const s = await stat(outPath);
    results.push({ idx, outPath, bytes: s.size });
    console.log(`${(s.size / 1024).toFixed(1)} KB`);
  }

  await closeBrowser();

  const totalKb = results.reduce((a, r) => a + r.bytes, 0) / 1024;
  const totalMs = Date.now() - t0;
  console.log('');
  console.log(`Done. 6 JPGs written to ${OUTPUT_DIR}`);
  console.log(`Total: ${totalKb.toFixed(1)} KB | Time: ${totalMs} ms`);
}

main().catch(err => {
  console.error('POC failed:', err);
  process.exit(1);
});
