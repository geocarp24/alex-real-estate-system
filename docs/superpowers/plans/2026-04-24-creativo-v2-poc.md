# El Creativo v2 — POC Implementation Plan (Fase 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Render one complete 6-slide T1 "Dark Premium" carousel (1080x1350 JPG q90) from a hardcoded sample spec, commit the JPGs to the repo, and get Jefe visual approval before Fase 2.

**Architecture:** Node + Puppeteer. Reuse existing `agents/creativo_runner/themes.mjs` (move to `agents/creativo_v2/src/themes.mjs`). Add three new modules: `wrapper.mjs` (HTML shell + Google Fonts), `render.mjs` (Puppeteer HTML→JPG), and `poc.mjs` (orchestrator). No Airtable, no Cloudinary, no Nano Banana — all Fase 2+.

**Tech Stack:** Node 22, Puppeteer (bundled chromium), Node built-in test runner (`node --test`), ES modules (.mjs).

**Spec:** `docs/superpowers/specs/2026-04-24-creativo-v2-design.md`
**Branch:** `claude/greeting-setup-yOfqf`

---

## File Structure

**Created by this plan:**
```
agents/creativo_v2/
  package.json                       npm module w/ puppeteer dep
  README.md                          quick-start
  .gitignore                         node_modules/, logs/
  src/
    themes.mjs                       moved from creativo_runner
    wrapper.mjs                      HTML shell + Montserrat fonts
    render.mjs                       Puppeteer renderer
  spec/
    poc_t1_5reasons.json             hardcoded sample
  test/
    themes.test.mjs                  smoke test for buildCarousel
    wrapper.test.mjs                 wrapSlideHtml output shape
    render.test.mjs                  renderJpg produces valid JPG
  output/                            6 JPGs (.jpg committed for POC)
  poc.mjs                            POC entry point
```

**Deleted by this plan:**
```
agents/creativo_runner/themes.mjs    moved to creativo_v2/src/
agents/creativo_runner/              directory removed (was only holding themes.mjs)
```

**Responsibilities (one per file):**
- `src/themes.mjs` — brand theme definitions + slide HTML builders (hook/point/CTA) + `buildCarousel`
- `src/wrapper.mjs` — single function `wrapSlideHtml(bodyHtml, themeCode)` returning full HTML doc
- `src/render.mjs` — single function `renderJpg(html, outputPath)` producing a 1080x1350 JPG q90
- `spec/poc_t1_5reasons.json` — input data for POC (no code)
- `poc.mjs` — orchestrator: load spec, build slides, wrap, render 6 JPGs to `output/`

---

## Task 1: Scaffold the `creativo_v2` module

**Files:**
- Create: `agents/creativo_v2/package.json`
- Create: `agents/creativo_v2/README.md`
- Create: `agents/creativo_v2/.gitignore`

- [ ] **Step 1: Create directory and package.json**

```bash
mkdir -p agents/creativo_v2/src agents/creativo_v2/test agents/creativo_v2/spec agents/creativo_v2/output
```

Write `agents/creativo_v2/package.json`:

```json
{
  "name": "creativo_v2",
  "version": "0.1.0",
  "description": "Pinnacle Holdings static carousel renderer (replaces Blotato)",
  "type": "module",
  "private": true,
  "scripts": {
    "poc": "node poc.mjs",
    "test": "node --test test/"
  },
  "dependencies": {
    "puppeteer": "^23.0.0"
  },
  "engines": {
    "node": ">=20"
  }
}
```

- [ ] **Step 2: Write README.md**

Write `agents/creativo_v2/README.md`:

```markdown
# El Creativo v2 — Pinnacle Holdings Carousel Renderer

Replaces Blotato. Renders 1080x1350 JPG carousels (4:5 vertical IG) from a spec.

## Quick start (POC)

```
cd agents/creativo_v2
npm install
npm run poc
```

Outputs 6 JPGs to `output/poc_t1_slide_{N}.jpg`.

## Tests

```
npm test
```

## Design

See `docs/superpowers/specs/2026-04-24-creativo-v2-design.md`.
```

- [ ] **Step 3: Write .gitignore**

Write `agents/creativo_v2/.gitignore`:

```
node_modules/
logs/
*.log
```

**Note:** `output/` is NOT in .gitignore during POC — we commit JPGs for Jefe review. In Fase 2 we add `output/` to .gitignore.

- [ ] **Step 4: Install puppeteer**

```bash
cd agents/creativo_v2 && npm install 2>&1 | tail -5
```

Expected: "added N packages" with puppeteer + chromium (~170MB download).

- [ ] **Step 5: Verify puppeteer launches headless**

```bash
cd agents/creativo_v2 && node -e "
import('puppeteer').then(async (pp) => {
  const browser = await pp.default.launch({ args: ['--no-sandbox'] });
  const v = await browser.version();
  console.log('OK:', v);
  await browser.close();
});"
```

Expected: `OK: HeadlessChrome/XXX.0.XXXX.XX`

If error about missing libs (e.g. `libatk-1.0.so.0`), install system deps:
```bash
apt-get install -y libatk1.0-0 libcups2 libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2 libpango-1.0-0 libcairo2 libnss3 2>/dev/null || sudo apt-get install -y libatk1.0-0 libcups2 libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2 libpango-1.0-0 libcairo2 libnss3
```

- [ ] **Step 6: Commit**

```bash
git add agents/creativo_v2/package.json agents/creativo_v2/README.md agents/creativo_v2/.gitignore
git -c commit.gpgsign=false commit -m "creativo_v2: scaffold module with puppeteer dep"
```

---

## Task 2: Move `themes.mjs` to new location (with passing test)

**Files:**
- Create: `agents/creativo_v2/test/themes.test.mjs`
- Create: `agents/creativo_v2/src/themes.mjs` (moved)
- Delete: `agents/creativo_runner/themes.mjs`

- [ ] **Step 1: Write the failing test**

Write `agents/creativo_v2/test/themes.test.mjs`:

```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { THEMES, buildCarousel } from '../src/themes.mjs';

test('THEMES contains all 5 codes T1-T5', () => {
  const codes = Object.keys(THEMES);
  assert.deepEqual(codes.sort(), ['T1', 'T2', 'T3', 'T4', 'T5']);
});

test('T1 has exact Pinnacle brand hex colors', () => {
  assert.equal(THEMES.T1.bg, '#0D3B2E');
  assert.equal(THEMES.T1.text, '#FFFFFF');
  assert.equal(THEMES.T1.accent, '#C9A84C');
});

test('buildCarousel returns 6 slides for hook + 4 points + cta', () => {
  const spec = {
    theme: 'T1',
    hook: { en: 'Test Hook', es: 'Hook Prueba' },
    points: [
      { headingEn: 'A', bodyEs: 'a' },
      { headingEn: 'B', bodyEs: 'b' },
      { headingEn: 'C', bodyEs: 'c' },
      { headingEn: 'D', bodyEs: 'd' },
    ],
    cta: { en: 'CTA EN', es: 'CTA ES' },
  };
  const slides = buildCarousel(spec);
  assert.equal(slides.length, 6);
  slides.forEach(s => assert.ok(typeof s === 'string' && s.length > 50));
});

test('buildCarousel throws on missing theme', () => {
  assert.throws(() => buildCarousel({ hook: {}, points: [], cta: {} }), /theme/);
});

test('buildCarousel throws on invalid theme code', () => {
  assert.throws(() => buildCarousel({ theme: 'T99', hook: {}, points: [], cta: {} }), /theme/);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd agents/creativo_v2 && node --test test/themes.test.mjs 2>&1 | tail -15
```

Expected: FAIL with `Cannot find module '../src/themes.mjs'`

- [ ] **Step 3: Move themes.mjs to new location**

```bash
mv agents/creativo_runner/themes.mjs agents/creativo_v2/src/themes.mjs
rmdir agents/creativo_runner 2>/dev/null || true
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd agents/creativo_v2 && node --test test/themes.test.mjs 2>&1 | tail -15
```

Expected: `pass 5`, `fail 0`.

- [ ] **Step 5: Commit**

```bash
git add agents/creativo_v2/src/themes.mjs agents/creativo_v2/test/themes.test.mjs
git rm -rf agents/creativo_runner 2>/dev/null || git add -A agents/creativo_runner
git -c commit.gpgsign=false commit -m "creativo_v2: move themes.mjs from creativo_runner + smoke tests"
```

---

## Task 3: Add `wrapper.mjs` (HTML shell with Google Fonts)

**Files:**
- Create: `agents/creativo_v2/test/wrapper.test.mjs`
- Create: `agents/creativo_v2/src/wrapper.mjs`

- [ ] **Step 1: Write the failing test**

Write `agents/creativo_v2/test/wrapper.test.mjs`:

```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { wrapSlideHtml } from '../src/wrapper.mjs';

test('wrapSlideHtml produces valid full HTML doc', () => {
  const body = '<div>hello</div>';
  const out = wrapSlideHtml(body, 'T1');
  assert.ok(out.startsWith('<!DOCTYPE html>'));
  assert.ok(out.includes('<html'));
  assert.ok(out.includes('<head>'));
  assert.ok(out.includes('</html>'));
  assert.ok(out.includes(body));
});

test('wrapSlideHtml includes Montserrat Google Fonts preconnect + link', () => {
  const out = wrapSlideHtml('<div/>', 'T1');
  assert.ok(out.includes('fonts.googleapis.com'));
  assert.ok(out.includes('Montserrat'));
  assert.ok(out.includes('preconnect'));
});

test('wrapSlideHtml includes viewport meta for 1080 width', () => {
  const out = wrapSlideHtml('<div/>', 'T1');
  assert.ok(out.includes('viewport'));
  assert.ok(/width=1080|width=device-width/.test(out));
});

test('wrapSlideHtml sets body background to match theme (no FOUC gap)', () => {
  const out = wrapSlideHtml('<div/>', 'T1');
  assert.ok(out.includes('#0D3B2E'));
});

test('wrapSlideHtml applies zero-margin reset', () => {
  const out = wrapSlideHtml('<div/>', 'T1');
  assert.ok(out.includes('margin:0') || out.includes('margin: 0'));
  assert.ok(out.includes('box-sizing'));
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd agents/creativo_v2 && node --test test/wrapper.test.mjs 2>&1 | tail -10
```

Expected: FAIL with `Cannot find module '../src/wrapper.mjs'`

- [ ] **Step 3: Implement wrapper.mjs**

Write `agents/creativo_v2/src/wrapper.mjs`:

```javascript
// Wraps a slide body HTML in a full HTML document with Montserrat fonts loaded.
// The returned string is Puppeteer-ready.

import { THEMES } from './themes.mjs';

export function wrapSlideHtml(bodyHtml, themeCode = 'T1') {
  const theme = THEMES[themeCode] || THEMES.T1;
  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=1080" />
<title>Pinnacle Slide</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700;800&display=swap" />
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  html, body { width:1080px; height:1350px; background:${theme.bg}; }
  body { font-family: 'Montserrat', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; -webkit-font-smoothing: antialiased; }
  img { max-width:100%; display:block; }
</style>
</head>
<body>${bodyHtml}</body>
</html>`;
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd agents/creativo_v2 && node --test test/wrapper.test.mjs 2>&1 | tail -10
```

Expected: `pass 5`, `fail 0`.

- [ ] **Step 5: Commit**

```bash
git add agents/creativo_v2/src/wrapper.mjs agents/creativo_v2/test/wrapper.test.mjs
git -c commit.gpgsign=false commit -m "creativo_v2: add HTML wrapper with Montserrat fonts + tests"
```

---

## Task 4: Add `render.mjs` (Puppeteer renderer)

**Files:**
- Create: `agents/creativo_v2/test/render.test.mjs`
- Create: `agents/creativo_v2/src/render.mjs`

- [ ] **Step 1: Write the failing test**

Write `agents/creativo_v2/test/render.test.mjs`:

```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { renderJpg } from '../src/render.mjs';
import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';

const MIN_HTML = `<!DOCTYPE html><html><head><style>
  html,body{margin:0;width:1080px;height:1350px;background:#0D3B2E;}
  h1{color:#fff;font:800 120px sans-serif;padding:400px 60px;}
  </style></head><body><h1>HELLO</h1></body></html>`;

test('renderJpg produces a JPG file > 10KB', async () => {
  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), 'creativo-'));
  const out = path.join(tmp, 'test.jpg');
  await renderJpg(MIN_HTML, out);
  const stat = await fs.stat(out);
  assert.ok(stat.size > 10_000, `expected > 10KB, got ${stat.size}`);
  await fs.rm(tmp, { recursive: true });
});

test('renderJpg output is valid JPEG (starts with SOI marker FFD8)', async () => {
  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), 'creativo-'));
  const out = path.join(tmp, 'test.jpg');
  await renderJpg(MIN_HTML, out);
  const buf = await fs.readFile(out);
  assert.equal(buf[0], 0xff);
  assert.equal(buf[1], 0xd8);
  await fs.rm(tmp, { recursive: true });
});

test('renderJpg produces 1080x1350 image (parse SOF0 marker)', async () => {
  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), 'creativo-'));
  const out = path.join(tmp, 'test.jpg');
  await renderJpg(MIN_HTML, out);
  const buf = await fs.readFile(out);
  // Find SOF0 marker (0xFFC0) and read dimensions (2 bytes height at +5, 2 bytes width at +7)
  let i = 0;
  let h = 0, w = 0;
  while (i < buf.length - 10) {
    if (buf[i] === 0xff && buf[i+1] === 0xc0) {
      h = (buf[i+5] << 8) | buf[i+6];
      w = (buf[i+7] << 8) | buf[i+8];
      break;
    }
    i++;
  }
  assert.equal(w, 1080, `width ${w}`);
  assert.equal(h, 1350, `height ${h}`);
  await fs.rm(tmp, { recursive: true });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd agents/creativo_v2 && node --test test/render.test.mjs 2>&1 | tail -10
```

Expected: FAIL with `Cannot find module '../src/render.mjs'`

- [ ] **Step 3: Implement render.mjs**

Write `agents/creativo_v2/src/render.mjs`:

```javascript
// Puppeteer HTML -> JPG at 1080x1350 quality 90.
// Launches a single shared browser per process for efficiency.

import puppeteer from 'puppeteer';
import path from 'node:path';
import fs from 'node:fs/promises';

const VIEWPORT = { width: 1080, height: 1350, deviceScaleFactor: 1 };
const JPEG_QUALITY = 90;

let _browser = null;

async function getBrowser() {
  if (_browser && _browser.isConnected()) return _browser;
  _browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  });
  return _browser;
}

export async function renderJpg(html, outputPath) {
  await fs.mkdir(path.dirname(outputPath), { recursive: true });
  const browser = await getBrowser();
  const page = await browser.newPage();
  try {
    await page.setViewport(VIEWPORT);
    await page.setContent(html, { waitUntil: 'networkidle0', timeout: 30000 });
    // small extra wait for webfonts to fully apply
    await page.evaluate(() => document.fonts && document.fonts.ready);
    await page.screenshot({
      path: outputPath,
      type: 'jpeg',
      quality: JPEG_QUALITY,
      clip: { x: 0, y: 0, width: 1080, height: 1350 },
      omitBackground: false,
    });
  } finally {
    await page.close();
  }
  return outputPath;
}

export async function closeBrowser() {
  if (_browser) {
    await _browser.close();
    _browser = null;
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd agents/creativo_v2 && node --test test/render.test.mjs 2>&1 | tail -15
```

Expected: `pass 3`, `fail 0`. First run may take 10-20s (browser startup).

- [ ] **Step 5: Commit**

```bash
git add agents/creativo_v2/src/render.mjs agents/creativo_v2/test/render.test.mjs
git -c commit.gpgsign=false commit -m "creativo_v2: add Puppeteer JPG renderer + dimension tests"
```

---

## Task 5: Add the sample spec JSON

**Files:**
- Create: `agents/creativo_v2/spec/poc_t1_5reasons.json`

- [ ] **Step 1: Write the sample spec**

Write `agents/creativo_v2/spec/poc_t1_5reasons.json`:

```json
{
  "theme": "T1",
  "hook": {
    "en": "5 Reasons to Sell for Cash",
    "es": "5 Razones para Vender por Efectivo",
    "badge": "WISCONSIN"
  },
  "points": [
    {
      "headingEn": "No Repairs Needed",
      "headingEs": "Sin Reparaciones",
      "bodyEn": "We buy your house as-is. No upgrades, no cleaning, no stress.",
      "bodyEs": "Compramos tu casa como esta. Sin arreglos, sin limpieza, sin estres."
    },
    {
      "headingEn": "Close in 7 Days",
      "headingEs": "Cierre en 7 Dias",
      "bodyEn": "From offer to cash in your hand in one week.",
      "bodyEs": "De la oferta al efectivo en tu mano en una semana."
    },
    {
      "headingEn": "No Commissions",
      "headingEs": "Sin Comisiones",
      "bodyEn": "You keep 100% of the offer. No realtor fees, no closing costs.",
      "bodyEs": "Te quedas con el 100% de la oferta. Sin comisiones ni costos de cierre."
    },
    {
      "headingEn": "No Showings",
      "headingEs": "Sin Visitas",
      "bodyEn": "Skip open houses, strangers walking through, and staging costs.",
      "bodyEs": "Sin visitas, sin extranos en tu casa, sin costos de presentacion."
    }
  ],
  "cta": {
    "en": "We Buy Houses. Cash. Fast. Fair.",
    "es": "Compramos Casas. Efectivo. Rapido. Justo."
  }
}
```

- [ ] **Step 2: Verify JSON parses**

```bash
node -e "const s=require('./agents/creativo_v2/spec/poc_t1_5reasons.json'); console.log('theme:',s.theme,'points:',s.points.length,'ok')"
```

Expected: `theme: T1 points: 4 ok`

- [ ] **Step 3: Commit**

```bash
git add agents/creativo_v2/spec/poc_t1_5reasons.json
git -c commit.gpgsign=false commit -m "creativo_v2: add POC sample spec (5 reasons, T1)"
```

---

## Task 6: Add `poc.mjs` orchestrator

**Files:**
- Create: `agents/creativo_v2/poc.mjs`

- [ ] **Step 1: Implement poc.mjs**

Write `agents/creativo_v2/poc.mjs`:

```javascript
#!/usr/bin/env node
// POC orchestrator: reads spec/poc_t1_5reasons.json, renders 6 JPGs to output/.

import { readFile, mkdir, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildCarousel } from './src/themes.mjs';
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
```

- [ ] **Step 2: Commit poc.mjs (without output yet)**

```bash
git add agents/creativo_v2/poc.mjs
git -c commit.gpgsign=false commit -m "creativo_v2: add POC orchestrator (poc.mjs)"
```

---

## Task 7: Run POC and commit the output JPGs

**Files:**
- Create: `agents/creativo_v2/output/poc_t1_slide_{1..6}.jpg` (6 files)

- [ ] **Step 1: Run the POC**

```bash
cd agents/creativo_v2 && node poc.mjs 2>&1
```

Expected output:
```
Creativo v2 POC — rendering T1 carousel
Spec: .../spec/poc_t1_5reasons.json
Theme: T1 | Hook: 5 Reasons to Sell for Cash
Points: 4 | Total slides: 6
  Rendering slide 1/6... 150.X KB
  Rendering slide 2/6... 120.X KB
  Rendering slide 3/6... 120.X KB
  Rendering slide 4/6... 120.X KB
  Rendering slide 5/6... 120.X KB
  Rendering slide 6/6... 140.X KB

Done. 6 JPGs written to .../output
Total: 8XX.X KB | Time: 15-30 s
```

- [ ] **Step 2: Verify output files exist and have expected properties**

```bash
ls -la agents/creativo_v2/output/ && echo "---" && for f in agents/creativo_v2/output/poc_t1_slide_*.jpg; do
  size=$(stat -c%s "$f")
  dim=$(python3 -c "from PIL import Image; i=Image.open('$f'); print(f'{i.width}x{i.height}')" 2>/dev/null || echo "dimension check skipped")
  echo "$f | ${size} bytes | $dim"
done
```

Expected: 6 files, each 80_000-300_000 bytes, dimensions 1080x1350.

If PIL not available, skip dimension check (render.test.mjs already validated via SOF0 marker).

- [ ] **Step 3: Display each JPG to Jefe via Read tool**

(This step is Claude reading the JPGs inline in chat for Jefe to see. No shell command — done in conversation.)

- [ ] **Step 4: Commit the 6 JPGs (POC deliverable)**

```bash
git add agents/creativo_v2/output/poc_t1_slide_*.jpg
git -c commit.gpgsign=false commit -m "creativo_v2: POC output — T1 carousel (6 slides, 5 Reasons)"
```

- [ ] **Step 5: Push to branch for Jefe review**

```bash
git push -u origin claude/greeting-setup-yOfqf 2>&1 | tail -5
```

On network failure, retry up to 4 times with exponential backoff (2s, 4s, 8s, 16s).

- [ ] **Step 6: Announce POC is ready**

Tell Jefe:
- 6 JPGs rendered successfully
- Show each inline in chat via Read tool
- Ask for: approve (proceed to Fase 2) | iterate (specify changes) | reject (change approach)

---

## Verification Checklist (post-Task-7)

Before declaring POC done:

- [ ] `cd agents/creativo_v2 && npm test` — all tests pass (11+ tests across 3 files)
- [ ] `ls agents/creativo_v2/output/ | wc -l` — outputs `6`
- [ ] Each JPG between 80KB and 300KB
- [ ] All 6 JPGs are 1080x1350 (verified by render.test.mjs SOF0 parser or PIL)
- [ ] Git log shows 7 clean commits (one per task)
- [ ] No `output/` entries in `.gitignore` yet (they stay committed until Fase 2)
- [ ] Jefe has seen all 6 JPGs inline in chat

---

## Out of Scope (explicitly deferred to Fase 2+)

- Airtable integration (`src/airtable.mjs`)
- Cloudinary upload (`src/cloudinary.mjs`)
- Production orchestrator (`src/main.mjs`)
- Nano Banana hero images (`src/nano_banana.mjs`)
- Cron/GitHub Actions deployment
- Multi-theme testing (T2-T5 only after T1 approved)
- Error alerting to Telegram

---

*End of plan.*
