#!/usr/bin/env node
// Renders slide 1 (hook) of T1 in each of the 3 aspect ratios for comparison.
// Output: samples/aspect_{4_5|1_1|9_16}_hook.jpg

import { readFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildCarousel, dimsForAspect, VALID_ASPECTS } from './src/themes.mjs';
import { wrapSlideHtml } from './src/wrapper.mjs';
import { renderJpg, closeBrowser } from './src/render.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SPEC_PATH = path.join(__dirname, 'spec', 'poc_t1_5reasons.json');
const OUTPUT_DIR = path.join(__dirname, 'samples');

async function main() {
  const baseSpec = JSON.parse(await readFile(SPEC_PATH, 'utf8'));
  await mkdir(OUTPUT_DIR, { recursive: true });

  for (const aspect of VALID_ASPECTS) {
    const spec = { ...baseSpec, aspect };
    const slides = buildCarousel(spec);
    const dims = dimsForAspect(aspect);
    const html = wrapSlideHtml(slides[0], spec.theme, aspect);
    const safe = aspect.replace(':', '_');
    const out = path.join(OUTPUT_DIR, `aspect_${safe}_hook.jpg`);
    process.stdout.write(`  ${aspect} (${dims.width}x${dims.height})... `);
    await renderJpg(html, out, dims);
    console.log(out);
  }

  await closeBrowser();
  console.log('Done.');
}

main().catch(err => { console.error('fail:', err); process.exit(1); });
