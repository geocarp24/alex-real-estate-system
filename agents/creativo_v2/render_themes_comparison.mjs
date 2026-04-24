#!/usr/bin/env node
// Renders hook slide for each of T1-T5 using the same sample content.
// Output: agents/creativo_v2/samples/theme_{N}_hook.jpg (5 files).

import { readFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildCarousel, THEMES } from './src/themes.mjs';
import { wrapSlideHtml } from './src/wrapper.mjs';
import { renderJpg, closeBrowser } from './src/render.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SPEC_PATH = path.join(__dirname, 'spec', 'poc_t1_5reasons.json');
const OUTPUT_DIR = path.join(__dirname, 'samples');

async function main() {
  const baseSpec = JSON.parse(await readFile(SPEC_PATH, 'utf8'));
  await mkdir(OUTPUT_DIR, { recursive: true });

  const themeCodes = Object.keys(THEMES);
  console.log(`Rendering hook slide for ${themeCodes.length} themes`);

  for (const code of themeCodes) {
    const spec = { ...baseSpec, theme: code };
    const slides = buildCarousel(spec);
    const html = wrapSlideHtml(slides[0], code);
    const out = path.join(OUTPUT_DIR, `theme_${code}_hook.jpg`);
    process.stdout.write(`  ${code} ${THEMES[code].name}... `);
    await renderJpg(html, out);
    console.log(`${out}`);
  }

  await closeBrowser();
  console.log('Done. 5 hook slides rendered in samples/');
}

main().catch(err => { console.error('fail:', err); process.exit(1); });
