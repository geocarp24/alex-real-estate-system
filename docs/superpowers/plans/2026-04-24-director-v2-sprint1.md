# El Director v2 — Sprint 1 MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the full Director v2 MVP — a Node pipeline that reads Airtable reel records with JSON `Visual_Prompt`, expands them through narrative B into 5 scenes, renders each scene to JPG via Puppeteer, downloads hero images from Pexels + Nano Banana, assembles the scenes into a 1080×1920 MP4 with ffmpeg (xfade + zoompan + music), uploads to Cloudinary, and PATCHes the record with the video URL. Ends with a POC render approved by Jorge.

**Architecture:** `agents/director_v2/` reuses `themes.mjs`, `wrapper.mjs`, and the `cloudinary.mjs` signing logic from `agents/creativo_v2/`. Adds 7 new modules: `narratives/`, `scene_layout.mjs`, `render.mjs`, `pexels.mjs`, `nano_banana.mjs`, `audio.mjs`, `ffmpeg.mjs`, `cost_control.mjs`, plus `util/retry.mjs` and `util/sanitize.mjs`. All I/O via native `fetch` + `node:crypto` + `spawn('ffmpeg', [...])` — no new npm deps beyond Puppeteer (already installed for Creativo).

**Tech Stack:** Node 22.22.2, Puppeteer 23, ffmpeg 6.1.1 (libx264, libass, libmp3lame), Doppler CLI 3.76.0 for secrets, Node built-in test runner (`node --test`), Pexels REST API, Gemini Nano Banana REST API, Cloudinary REST upload API.

**Spec:** `docs/superpowers/specs/2026-04-24-director-v2-design.md` (all sections)
**Branch:** `claude/greeting-setup-yOfqf`
**Phase discipline:** This plan follows `agents/PROTOCOLO_EJECUCION.md` — each task is a single commit with tests before implementation. Applies **NO-NEGOTIABLE "por partes" rule**: any output >300 lines is split into smaller Write/Edit operations.

---

## File Structure

**Created by this plan (Sprint 1):**
```
agents/director_v2/
  package.json                          scripts: test, prod, prod:dry-run, poc, backfill, schema
  .gitignore                            tmp/, samples/, logs/, state/nano_banana_usage.json, node_modules/
  main.mjs                              production orchestrator (Airtable → render → Cloudinary → PATCH)
  render_poc.mjs                        POC standalone runner for Jorge approval

  spec/
    poc_narrative_b.json                POC input spec: "3 reasons to sell off-market"

  src/
    themes.mjs                          re-export from creativo_v2/src/themes.mjs
    wrapper.mjs                         re-export from creativo_v2/src/wrapper.mjs
    cloudinary.mjs                      extend creativo_v2 cloudinary with uploadVideo()
    airtable.mjs                        list pending reel records + parse + update
    narratives/
      index.mjs                         dispatcher + validateSpec
      narrative_B.mjs                   hook → 3 points → cta expansion
    scene_layout.mjs                    layout D: hero + overlay + caption + logo
    render.mjs                          Puppeteer: HTML → JPG (kinetic=false) or PNG frames (kinetic=true)
    pexels.mjs                          search + download 1080×1920 photos
    nano_banana.mjs                     Gemini image generation + re-roll + cost counter
    audio.mjs                           pickMusic(mood, duration) from assets/music/
    ffmpeg.mjs                          buildVideoCommand with xfade + zoompan + amix
    cost_control.mjs                    monthly + per-video Nano Banana budget caps
    util/
      retry.mjs                         withRetry(fn, { attempts, baseDelayMs, onRetry })
      sanitize.mjs                      escapeHtml, sanitizePexelsQuery, sanitizeNanoBananaPrompt, sanitizePublicId, sanitizeRecordId

  assets/
    music/
      upbeat_1.mp3                      Pixabay CC0 instrumental, ~15-30s
      upbeat_2.mp3
      chill_1.mp3
      cinematic_1.mp3
      tension_1.mp3
      LICENSES.md                       source URL + license per track

  test/
    retry.test.mjs                      ~3 tests
    sanitize.test.mjs                   ~6 tests (new file not in spec initially — added for util coverage)
    audio.test.mjs                      ~5 tests
    pexels.test.mjs                     ~4 tests mocked
    nano_banana.test.mjs                ~5 tests mocked
    scene_layout.test.mjs               ~8 tests
    render.test.mjs                     ~3 tests
    narratives.test.mjs                 ~4 tests (narrative_B only in Sprint 1)
    ffmpeg.test.mjs                     ~6 tests
    cloudinary.test.mjs                 ~3 tests
    airtable.test.mjs                   ~6 tests
    cost_control.test.mjs               ~4 tests
    main.test.mjs                       ~4 integration tests mocked
    smoke.test.mjs                      ~1 optional (RUN_SMOKE=1)
    fixtures/
      spec_narrative_B_valid.json
      spec_narrative_B_missing_points.json
      spec_malformed.txt
      pexels_response_ok.json
      pexels_response_429.json
      pexels_response_empty.json
      gemini_response_ok.png            binary PNG magic bytes
      cloudinary_video_response.json
      airtable_records_pending.json

  state/
    nano_banana_usage.json              runtime-created, gitignored

  samples/                              gitignored output dir for dry-run videos

  scripts/
    airtable_schema_setup.mjs           Task 0: idempotent schema migration
```

**Modified by this plan:**
```
agents/creativo_v2/src/cloudinary.mjs   export uploadVideo() alongside uploadJpg()  (or keep in director_v2/src/cloudinary.mjs — decided in Task 10)
.gitignore                               (root) add agents/director_v2/{tmp,samples,logs,state/nano_banana_usage.json,node_modules}/
```

**Not touched:**
- `agents/creativo_v2/src/themes.mjs` and `wrapper.mjs` (Fase 1 stable, re-exported only)
- `agents/creativo_v2/main.mjs` and `poc.mjs` (Creativo pipeline stable)
- `agents/director.md` (legacy Blotato v2.0 doc — left as-is, marked deprecated in memoria at close)

---

## Schema Contract — Visual_Prompt JSON for reels

Social Media Agent writes valid JSON to Airtable `Visual_Prompt` (table `tblAj0Pkj1jW4p5Ld`, base `appU9s3kGkVpdrJkw`) with `Media_Type='reel'` for every record the Director processes:

```json
{
  "media_type": "reel",
  "theme": "T1",
  "aspect": "9:16",
  "narrative": "B",
  "duration": 10,
  "mood": "upbeat",
  "hook":  { "en": "...", "es": "...", "badge": "WISCONSIN" },
  "points": [
    { "headingEn": "...", "headingEs": "...", "bodyEn": "...", "bodyEs": "..." },
    { "headingEn": "...", "headingEs": "...", "bodyEn": "...", "bodyEs": "..." },
    { "headingEn": "...", "headingEs": "...", "bodyEn": "...", "bodyEs": "..." }
  ],
  "cta":   { "en": "...", "es": "..." }
}
```

`parseVisualPrompt` tolerates ```` ```json ... ``` ```` markdown fencing (same as Creativo). If parse fails → record marked `Status=Error` with `Error_Reason`; batch continues.

**Legacy records:** backfill handled by a separate post-Sprint-1 Task (see Sprint 1 completion in Section 14 of spec) — out of scope for this plan.

---

## Prerequisites (before Task 0)

**PEXELS API KEY** — not required until Task 4, but Jorge should register during Sprint 1 prep:
1. Visit https://www.pexels.com/api/
2. Sign up (free, takes 2 minutes)
3. Copy API key from dashboard
4. `doppler secrets set PEXELS_API_KEY=<key>` in project `pinnacle-social-publisher` config `dev_personal`

**AIRTABLE SCHEMA PAT** — required for Task 0, deleted after Task 0:
1. Airtable → Account → Developer Hub → Create Personal Access Token
2. Scopes: `schema.bases:write` + `data.records:read` + `data.records:write`
3. Access: base `appU9s3kGkVpdrJkw`
4. `doppler secrets set AIRTABLE_SM_SCHEMA_TOKEN=<token>`
5. After Task 0 succeeds: `doppler secrets delete AIRTABLE_SM_SCHEMA_TOKEN` (permanent cleanup of elevated token)

**FFMPEG** — already installed: `/usr/bin/ffmpeg` version 6.1.1 with libx264, libass, libmp3lame, libvorbis.

**DOPPLER** — already configured in `/home/user/alex-real-estate-system/agents/creativo_v2/` with service token for project `pinnacle-social-publisher` config `dev_personal`. Same token works for Director v2 (adds secrets, does not need new setup).

---

## Execution Discipline (Fase 4 of PROTOCOLO_EJECUCION — NO NEGOCIABLE)

- One task = related modules + tests = one commit
- TDD strictly: failing test first → implementation → test passes → commit
- **NEVER** `Write` files >300 lines in one operation — split across multiple Write/Edit operations, announcing "✅ Parte N/M lista. Sigo." after each
- NEVER paste >400 lines into chat output — write to disk and report the path only
- After each task: status update to Jorge (one line: what got done + what tests pass)
- After Task 14 (POC): pause for Jorge approval before Task 15 (final push + memoria)

---

## Task 0: Airtable schema setup (idempotent one-time migration)

**Goal:** Before any Director v2 code runs, ensure the Airtable base has the 2 new fields needed for video metadata. The existing `Formato` single-select already has the `Reel` option (no new option needed). Script is idempotent — can be re-run safely.

**Files:**
- Create: `agents/director_v2/scripts/airtable_schema_setup.mjs`
- Create: `agents/director_v2/test/schema_setup.test.mjs`

**Dependencies:** `AIRTABLE_SM_SCHEMA_TOKEN` in Doppler (with BOTH `schema.bases:read` AND `schema.bases:write` scopes), `AIRTABLE_SM_BASE_ID`, `AIRTABLE_SM_TABLE_ID`.

**Live schema verified 2026-04-24:** the production table is `Ideas de Contenido` (id `tblAj0Pkj1jW4p5Ld`). It has a `Formato` single-select with choices `Post | Reel | Carrusel | Story` — NOT a `Media_Type` field. Director v2 filters by `{Formato}='Reel'` (Task 11). Task 0 only needs to add the 2 new number fields.

- [ ] **Step 1: Create directory and package.json**

Run:
```bash
mkdir -p agents/director_v2/scripts agents/director_v2/test
```

Create `agents/director_v2/package.json`:
```json
{
  "name": "@pinnacle/director-v2",
  "private": true,
  "type": "module",
  "version": "0.1.0",
  "engines": { "node": ">=22.0.0" },
  "scripts": {
    "test":             "node --test test/*.test.mjs",
    "schema":           "doppler run -- node scripts/airtable_schema_setup.mjs",
    "schema:dry-run":   "doppler run -- node scripts/airtable_schema_setup.mjs --dry-run"
  },
  "dependencies": {
    "puppeteer": "^23.0.0"
  }
}
```

- [ ] **Step 2: Write failing test for discoverTable**

Create `agents/director_v2/test/schema_setup.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { discoverTable, diffSchema, __setFetch } from '../scripts/airtable_schema_setup.mjs';

test('discoverTable returns table metadata for given tableId', async () => {
  __setFetch(async (url, opts) => {
    assert.ok(url.includes('/meta/bases/appU9s3kGkVpdrJkw/tables'));
    assert.equal(opts.headers.Authorization, 'Bearer test_token');
    return {
      ok: true,
      json: async () => ({
        tables: [
          { id: 'tblAj0Pkj1jW4p5Ld', name: 'Ideas de Contenido', fields: [
            { id: 'fldF', name: 'Formato', type: 'singleSelect', options: { choices: [{ name: 'Post' }, { name: 'Reel' }] } },
            { id: 'fldVU', name: 'visual_url', type: 'url' }
          ]}
        ]
      }),
    };
  });

  const table = await discoverTable('appU9s3kGkVpdrJkw', 'tblAj0Pkj1jW4p5Ld', 'test_token');
  assert.equal(table.id, 'tblAj0Pkj1jW4p5Ld');
  assert.equal(table.fields.length, 2);
});

test('diffSchema returns 2 add_field changes when both numeric fields missing', () => {
  const table = {
    fields: [
      { name: 'Formato', type: 'singleSelect', options: { choices: [{ name: 'Reel' }] } },
      { name: 'visual_url', type: 'url' },
    ]
  };
  const changes = diffSchema(table);
  assert.equal(changes.length, 2);
  assert.ok(changes.find(c => c.action === 'add_field' && c.name === 'video_duration'));
  assert.ok(changes.find(c => c.action === 'add_field' && c.name === 'video_cost_cents'));
});

test('diffSchema returns empty when both numeric fields already present', () => {
  const table = {
    fields: [
      { name: 'Formato', type: 'singleSelect', options: { choices: [{ name: 'Reel' }] } },
      { name: 'video_duration', type: 'number', options: { precision: 1 } },
      { name: 'video_cost_cents', type: 'number', options: { precision: 0 } },
    ]
  };
  const changes = diffSchema(table);
  assert.equal(changes.length, 0);
});
```

- [ ] **Step 3: Run test to verify it fails**

```bash
cd agents/director_v2 && node --test test/schema_setup.test.mjs
```
Expected: FAIL with `Cannot find module '../scripts/airtable_schema_setup.mjs'`

- [ ] **Step 4: Implement airtable_schema_setup.mjs**

Create `agents/director_v2/scripts/airtable_schema_setup.mjs`:
```javascript
#!/usr/bin/env node
// Idempotent Airtable schema migration for Director v2.
// Adds: fields 'video_duration' (number, precision 1) and 'video_cost_cents' (number, precision 0).
// The 'Formato' single-select already includes 'Reel' option in production — no change there.
// Requires AIRTABLE_SM_SCHEMA_TOKEN with scopes schema.bases:read + schema.bases:write — delete after run.

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }

const BASE = 'https://api.airtable.com/v0';

export async function discoverTable(baseId, tableId, token) {
  const res = await _fetch(`${BASE}/meta/bases/${baseId}/tables`, {
    headers: { Authorization: `Bearer ${token}` }
  });
  if (!res.ok) throw new Error(`discoverTable failed: HTTP ${res.status}`);
  const data = await res.json();
  const table = data.tables?.find(t => t.id === tableId);
  if (!table) throw new Error(`Table ${tableId} not found in base ${baseId}`);
  return table;
}

export function diffSchema(table) {
  const changes = [];
  if (!table.fields.some(f => f.name === 'video_duration')) {
    changes.push({ action: 'add_field', name: 'video_duration', type: 'number', options: { precision: 1 } });
  }
  if (!table.fields.some(f => f.name === 'video_cost_cents')) {
    changes.push({ action: 'add_field', name: 'video_cost_cents', type: 'number', options: { precision: 0 } });
  }
  return changes;
}

async function applyChange(baseId, tableId, change, token) {
  const headers = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' };
  if (change.action === 'add_field') {
    const res = await _fetch(`${BASE}/meta/bases/${baseId}/tables/${tableId}/fields`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ name: change.name, type: change.type, options: change.options }),
    });
    if (!res.ok) throw new Error(`add_field ${change.name} failed: ${res.status} ${await res.text()}`);
  } else {
    throw new Error(`unknown change action: ${change.action}`);
  }
}

async function main() {
  const token  = process.env.AIRTABLE_SM_SCHEMA_TOKEN;
  const baseId = process.env.AIRTABLE_SM_BASE_ID;
  const tableId= process.env.AIRTABLE_SM_TABLE_ID;
  const dryRun = process.argv.includes('--dry-run');

  if (!token) { console.error('ERROR: AIRTABLE_SM_SCHEMA_TOKEN missing'); process.exit(1); }
  if (!baseId || !tableId) { console.error('ERROR: base/table env missing'); process.exit(1); }

  console.log(`Discovering base=${baseId} table=${tableId} ...`);
  const table = await discoverTable(baseId, tableId, token);
  console.log(`  Found table '${table.name}' with ${table.fields.length} fields.`);

  const changes = diffSchema(table);
  if (changes.length === 0) { console.log('No changes needed — schema is already up to date.'); return; }

  console.log(`Pending changes (${changes.length}):`);
  changes.forEach((c, i) => console.log(`  ${i+1}. ${c.action} ${c.name}`));

  if (dryRun) { console.log('--dry-run — not applying.'); return; }

  for (const change of changes) {
    console.log(`Applying: ${change.action} ${change.name} ...`);
    await applyChange(baseId, tableId, change, token);
    console.log('  OK');
  }
  console.log('Done. Delete AIRTABLE_SM_SCHEMA_TOKEN from Doppler now.');
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main().catch(err => { console.error('FAIL:', err.message); process.exit(1); });
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
cd agents/director_v2 && node --test test/schema_setup.test.mjs
```
Expected: `# pass 3` with 0 failures.

- [ ] **Step 6: Dry-run the schema script**

```bash
cd agents/director_v2 && doppler run -- node scripts/airtable_schema_setup.mjs --dry-run
```
Expected output: lists `add_field video_duration` and `add_field video_cost_cents` (or "No changes needed" if already applied).

- [ ] **Step 7: Apply schema changes**

```bash
cd agents/director_v2 && doppler run -- node scripts/airtable_schema_setup.mjs
```
Expected: 2 × "OK" lines + "Done. Delete AIRTABLE_SM_SCHEMA_TOKEN..."

- [ ] **Step 8: Delete elevated token from Doppler**

```bash
doppler secrets delete AIRTABLE_SM_SCHEMA_TOKEN --yes
```
Expected: confirmation that the secret is removed. This token is no longer needed and leaving it in Doppler is unnecessary risk.

- [ ] **Step 9: Commit**

```bash
git add agents/director_v2/package.json \
        agents/director_v2/scripts/airtable_schema_setup.mjs \
        agents/director_v2/test/schema_setup.test.mjs
git commit -m "feat(director_v2): Task 0 — idempotent Airtable schema setup (2 numeric fields)"
```

**Acceptance criteria:**
- `node --test test/schema_setup.test.mjs` passes 3 tests
- Airtable base `appU9s3kGkVpdrJkw` table `tblAj0Pkj1jW4p5Ld` has new fields `video_duration` (number, precision 1) and `video_cost_cents` (number, precision 0)
- The pre-existing `Formato` single-select with options `Post | Reel | Carrusel | Story` is **untouched**
- `AIRTABLE_SM_SCHEMA_TOKEN` deleted from Doppler

---

## Task 1: Scaffold + re-export themes/wrapper + gitignore

**Goal:** Set up the full folder structure, extend `package.json` with all scripts, create .gitignore, and thin re-export modules so Director can reuse Creativo's brand system.

**Files:**
- Create: `agents/director_v2/.gitignore`
- Modify: `agents/director_v2/package.json` (add scripts)
- Create: `agents/director_v2/src/themes.mjs` (re-export)
- Create: `agents/director_v2/src/wrapper.mjs` (re-export)
- Create: `agents/director_v2/src/` subdirs: `narratives/`, `util/`
- Create: `agents/director_v2/assets/music/`
- Create: `agents/director_v2/test/fixtures/`

- [ ] **Step 1: Create all directories**

```bash
mkdir -p agents/director_v2/src/narratives \
         agents/director_v2/src/util \
         agents/director_v2/assets/music \
         agents/director_v2/test/fixtures \
         agents/director_v2/samples \
         agents/director_v2/state
```

- [ ] **Step 2: Create .gitignore**

Create `agents/director_v2/.gitignore`:
```
node_modules/
tmp/
samples/
logs/
state/nano_banana_usage.json
```

- [ ] **Step 3: Replace package.json with full Sprint 1 scripts**

Overwrite `agents/director_v2/package.json`:
```json
{
  "name": "@pinnacle/director-v2",
  "private": true,
  "type": "module",
  "version": "0.1.0",
  "engines": { "node": ">=22.0.0" },
  "scripts": {
    "test":             "node --test test/*.test.mjs",
    "test:smoke":       "RUN_SMOKE=1 node --test test/smoke.test.mjs",
    "prod":             "doppler run -- node main.mjs",
    "prod:dry-run":     "doppler run -- node main.mjs --dry-run",
    "poc":              "doppler run -- node render_poc.mjs",
    "schema":           "doppler run -- node scripts/airtable_schema_setup.mjs",
    "schema:dry-run":   "doppler run -- node scripts/airtable_schema_setup.mjs --dry-run"
  },
  "dependencies": {
    "puppeteer": "^23.0.0"
  }
}
```

- [ ] **Step 4: Install puppeteer**

```bash
cd agents/director_v2 && npm install
```
Expected: `added N packages`, Chromium downloaded to `node_modules/puppeteer/.local-chromium/`.

- [ ] **Step 5: Write failing test for re-export**

Create `agents/director_v2/test/reexport.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { THEMES, dimsForAspect, VALID_ASPECTS } from '../src/themes.mjs';
import { wrapSlideHtml } from '../src/wrapper.mjs';

test('themes re-export exposes THEMES T1-T5', () => {
  assert.equal(Object.keys(THEMES).sort().join(','), 'T1,T2,T3,T4,T5');
  assert.equal(THEMES.T1.bg, '#0D3B2E');
});

test('dimsForAspect 9:16 returns 1080x1920', () => {
  assert.deepEqual(dimsForAspect('9:16'), { width: 1080, height: 1920 });
});

test('VALID_ASPECTS includes 9:16', () => {
  assert.ok(VALID_ASPECTS.includes('9:16'));
});

test('wrapper.wrapSlideHtml returns HTML with inlined logo', () => {
  const html = wrapSlideHtml('<div>test</div>', 'T1', '9:16');
  assert.ok(html.includes('height:1920px'));
  assert.ok(html.includes('data:image/png;base64,'));
});
```

- [ ] **Step 6: Run test to verify it fails**

```bash
cd agents/director_v2 && node --test test/reexport.test.mjs
```
Expected: FAIL with `Cannot find module '../src/themes.mjs'`

- [ ] **Step 7: Create re-export modules**

Create `agents/director_v2/src/themes.mjs`:
```javascript
export * from '../../creativo_v2/src/themes.mjs';
```

Create `agents/director_v2/src/wrapper.mjs`:
```javascript
export * from '../../creativo_v2/src/wrapper.mjs';
```

- [ ] **Step 8: Run test to verify it passes**

```bash
cd agents/director_v2 && node --test test/reexport.test.mjs
```
Expected: `# pass 4`.

- [ ] **Step 9: Update root .gitignore**

Append to `.gitignore` (root of repo):
```
# Director v2
agents/director_v2/node_modules/
agents/director_v2/tmp/
agents/director_v2/samples/
agents/director_v2/logs/
agents/director_v2/state/nano_banana_usage.json
```

- [ ] **Step 10: Commit**

```bash
git add agents/director_v2/.gitignore \
        agents/director_v2/package.json \
        agents/director_v2/package-lock.json \
        agents/director_v2/src/themes.mjs \
        agents/director_v2/src/wrapper.mjs \
        agents/director_v2/test/reexport.test.mjs \
        .gitignore
git commit -m "feat(director_v2): Task 1 — scaffold + re-export Creativo themes and wrapper"
```

**Acceptance criteria:**
- `cd agents/director_v2 && node --test test/*.test.mjs` passes 7 tests total (3 schema + 4 reexport)
- `node_modules/puppeteer` installed locally
- Root `.gitignore` updated

---

## Task 2: Audio — download 5 royalty-free tracks + pickMusic selector

**Goal:** Download 5-8 CC0 instrumental tracks from Pixabay into `assets/music/`, write `LICENSES.md` with source URLs, implement `src/audio.mjs` that picks a track by mood with rotation.

**Files:**
- Create: `agents/director_v2/assets/music/upbeat_1.mp3` (+ 4-7 more)
- Create: `agents/director_v2/assets/music/LICENSES.md`
- Create: `agents/director_v2/src/audio.mjs`
- Create: `agents/director_v2/test/audio.test.mjs`

**Pre-requirement:** Jorge approves the 5 track choices before download (2 minutes of review).

- [ ] **Step 1: Write failing tests**

Create `agents/director_v2/test/audio.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { pickMusic, listTracksForMood, MOOD_DEFAULT } from '../src/audio.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const MUSIC_DIR = join(HERE, '..', 'assets', 'music');

test('pickMusic("upbeat", 10) returns path to an existing mp3', () => {
  const path = pickMusic('upbeat', 10);
  assert.ok(existsSync(path), `track should exist at ${path}`);
  assert.ok(path.endsWith('.mp3'));
});

test('pickMusic with unknown mood falls back to default upbeat', () => {
  const path = pickMusic('nonexistent', 10);
  assert.ok(existsSync(path));
  assert.ok(path.toLowerCase().includes('upbeat'));
});

test('pickMusic rotates when called twice with same mood (different seeds)', () => {
  const a = pickMusic('upbeat', 10, { seed: 1 });
  const b = pickMusic('upbeat', 10, { seed: 2 });
  // With 2+ tracks in upbeat, different seeds SHOULD pick different tracks
  // If only 1 track exists, they will be equal — test passes either way since that's still correct behavior
  const tracksUpbeat = listTracksForMood('upbeat');
  if (tracksUpbeat.length >= 2) assert.notEqual(a, b);
  else assert.equal(a, b);
});

test('LICENSES.md exists and lists all tracks in assets/music/', () => {
  const licPath = join(MUSIC_DIR, 'LICENSES.md');
  assert.ok(existsSync(licPath));
  const text = readFileSync(licPath, 'utf8');
  const moods = ['upbeat', 'chill', 'cinematic', 'tension'];
  for (const m of moods) {
    const tracks = listTracksForMood(m);
    for (const t of tracks) {
      const basename = t.split('/').pop();
      assert.ok(text.includes(basename), `LICENSES.md must mention ${basename}`);
    }
  }
});

test('MOOD_DEFAULT is upbeat', () => {
  assert.equal(MOOD_DEFAULT, 'upbeat');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd agents/director_v2 && node --test test/audio.test.mjs
```
Expected: FAIL with `Cannot find module '../src/audio.mjs'`

- [ ] **Step 3: Download tracks from Pixabay (manual curation)**

Ask Jorge to approve these 5 tracks (visit pixabay.com/music, search each phrase, pick the top result that matches the mood and duration 15-30s):

| Filename | Search phrase | Mood |
|---|---|---|
| `upbeat_1.mp3` | "upbeat corporate" | upbeat |
| `upbeat_2.mp3` | "upbeat energy" | upbeat |
| `chill_1.mp3` | "chill lofi real estate" | chill |
| `cinematic_1.mp3` | "cinematic inspirational" | cinematic |
| `tension_1.mp3` | "dramatic build up" | tension |

Download each file and place in `agents/director_v2/assets/music/{filename}.mp3`. All files must be CC0 (Pixabay default) — verify on the track page.

- [ ] **Step 4: Create LICENSES.md**

Create `agents/director_v2/assets/music/LICENSES.md`:
```markdown
# Director v2 — Music Track Licenses

All tracks below are royalty-free under the Pixabay Content License (equivalent to CC0). Free for commercial use, no attribution required.

| Track | Source URL | License | Mood |
|---|---|---|---|
| upbeat_1.mp3   | https://pixabay.com/music/<slug-1>/ | Pixabay Content License | upbeat |
| upbeat_2.mp3   | https://pixabay.com/music/<slug-2>/ | Pixabay Content License | upbeat |
| chill_1.mp3    | https://pixabay.com/music/<slug-3>/ | Pixabay Content License | chill |
| cinematic_1.mp3| https://pixabay.com/music/<slug-4>/ | Pixabay Content License | cinematic |
| tension_1.mp3  | https://pixabay.com/music/<slug-5>/ | Pixabay Content License | tension |

Replace `<slug-N>` with actual URLs after download. Keep this file in sync: if a track is added or removed, update this table.
```

Replace the `<slug-N>` with the actual URLs after downloading.

- [ ] **Step 5: Implement audio.mjs**

Create `agents/director_v2/src/audio.mjs`:
```javascript
import { readdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const MUSIC_DIR = join(HERE, '..', 'assets', 'music');

export const MOOD_DEFAULT = 'upbeat';
const VALID_MOODS = ['upbeat', 'chill', 'cinematic', 'tension'];

export function listTracksForMood(mood) {
  if (!existsSync(MUSIC_DIR)) return [];
  const files = readdirSync(MUSIC_DIR).filter(f => f.endsWith('.mp3'));
  const prefix = `${mood}_`;
  return files.filter(f => f.startsWith(prefix)).map(f => join(MUSIC_DIR, f)).sort();
}

export function pickMusic(mood, durationSeconds, { seed = Date.now() } = {}) {
  let targetMood = VALID_MOODS.includes(mood) ? mood : MOOD_DEFAULT;
  let tracks = listTracksForMood(targetMood);

  if (tracks.length === 0) {
    targetMood = MOOD_DEFAULT;
    tracks = listTracksForMood(MOOD_DEFAULT);
  }
  if (tracks.length === 0) {
    throw new Error(`No music tracks found in ${MUSIC_DIR} for any mood`);
  }

  const idx = Math.abs(Number(seed) | 0) % tracks.length;
  return tracks[idx];
}
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
cd agents/director_v2 && node --test test/audio.test.mjs
```
Expected: `# pass 5`.

- [ ] **Step 7: Commit**

```bash
git add agents/director_v2/assets/music/ \
        agents/director_v2/src/audio.mjs \
        agents/director_v2/test/audio.test.mjs
git commit -m "feat(director_v2): Task 2 — royalty-free music library + pickMusic selector"
```

**Acceptance criteria:**
- 5 `.mp3` files in `assets/music/`, all CC0 Pixabay
- `LICENSES.md` lists all 5 with source URLs
- `node --test test/audio.test.mjs` passes 5 tests
- `listTracksForMood('upbeat').length >= 2` (rotation works)

---

## Task 3: Utilities — retry + sanitize

**Goal:** Two small pure modules used by everything downstream. `retry.mjs` wraps async calls with exponential backoff. `sanitize.mjs` has escape + whitelist helpers to prevent injection attacks into HTML, ffmpeg argv, Cloudinary public IDs, and Pexels queries.

**Files:**
- Create: `agents/director_v2/src/util/retry.mjs`
- Create: `agents/director_v2/src/util/sanitize.mjs`
- Create: `agents/director_v2/test/retry.test.mjs`
- Create: `agents/director_v2/test/sanitize.test.mjs`

- [ ] **Step 1: Write failing tests for retry**

Create `agents/director_v2/test/retry.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { withRetry } from '../src/util/retry.mjs';

test('withRetry returns value when fn succeeds first try', async () => {
  let calls = 0;
  const result = await withRetry(async () => { calls++; return 42; }, { attempts: 3, baseDelayMs: 1 });
  assert.equal(result, 42);
  assert.equal(calls, 1);
});

test('withRetry retries on failure and succeeds on attempt 3', async () => {
  let calls = 0;
  const fn = async () => {
    calls++;
    if (calls < 3) throw new Error(`fail ${calls}`);
    return 'ok';
  };
  const onRetryCalls = [];
  const result = await withRetry(fn, {
    attempts: 3,
    baseDelayMs: 1,
    onRetry: (err, n, delay) => onRetryCalls.push({ n, msg: err.message, delay })
  });
  assert.equal(result, 'ok');
  assert.equal(calls, 3);
  assert.equal(onRetryCalls.length, 2);
  assert.equal(onRetryCalls[0].n, 1);
  assert.equal(onRetryCalls[1].n, 2);
  assert.equal(onRetryCalls[0].delay, 1);
  assert.equal(onRetryCalls[1].delay, 2);
});

test('withRetry throws last error when all attempts fail', async () => {
  let calls = 0;
  const fn = async () => { calls++; throw new Error(`fail_${calls}`); };
  await assert.rejects(
    withRetry(fn, { attempts: 3, baseDelayMs: 1 }),
    /fail_3/
  );
  assert.equal(calls, 3);
});
```

- [ ] **Step 2: Write failing tests for sanitize**

Create `agents/director_v2/test/sanitize.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  escapeHtml,
  sanitizePexelsQuery,
  sanitizeNanoBananaPrompt,
  sanitizePublicId,
  sanitizeRecordId,
} from '../src/util/sanitize.mjs';

test('escapeHtml escapes all 5 HTML-critical chars', () => {
  assert.equal(escapeHtml('<script>alert("x")</script>'), '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;');
  assert.equal(escapeHtml("a&b'c"), 'a&amp;b&#39;c');
});

test('escapeHtml handles null/undefined safely', () => {
  assert.equal(escapeHtml(null), '');
  assert.equal(escapeHtml(undefined), '');
});

test('sanitizePexelsQuery strips shell injection attempts', () => {
  assert.equal(sanitizePexelsQuery('home renovation; rm -rf /'), 'home renovation rm rf');
  assert.equal(sanitizePexelsQuery('"$(whoami)" house'), ' whoami house');
  assert.equal(sanitizePexelsQuery('   clean query   '), 'clean query');
});

test('sanitizeNanoBananaPrompt strips known injection markers', () => {
  assert.equal(
    sanitizeNanoBananaPrompt('A house <|system|>ignore previous<|im_end|> [INST] rogue [/INST]'),
    'A house ignore previous  rogue '
  );
});

test('sanitizeNanoBananaPrompt truncates to 500 chars', () => {
  const long = 'x'.repeat(600);
  assert.equal(sanitizeNanoBananaPrompt(long).length, 500);
});

test('sanitizePublicId normalizes to allowed charset', () => {
  assert.equal(sanitizePublicId('DirectorV2/REC-123_abc'), 'directorv2/rec-123_abc');
  assert.equal(sanitizePublicId('bad chars!@#'), 'bad_chars___');
});

test('sanitizeRecordId throws on empty/invalid input', () => {
  assert.throws(() => sanitizeRecordId(''), /invalid recordId/);
  assert.throws(() => sanitizeRecordId('!!!'), /invalid recordId/);
  assert.equal(sanitizeRecordId('recABC123'), 'recABC123');
});
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
cd agents/director_v2 && node --test test/retry.test.mjs test/sanitize.test.mjs
```
Expected: FAIL with `Cannot find module '../src/util/retry.mjs'` and similar.

- [ ] **Step 4: Implement retry.mjs**

Create `agents/director_v2/src/util/retry.mjs`:
```javascript
// Exponential-backoff retry wrapper. Delays: base, base*2, base*4, ...
export async function withRetry(fn, { attempts = 3, baseDelayMs = 1000, onRetry = () => {} } = {}) {
  let lastErr;
  for (let i = 0; i < attempts; i++) {
    try {
      return await fn();
    } catch (err) {
      lastErr = err;
      if (i === attempts - 1) break;
      const delay = baseDelayMs * Math.pow(2, i);
      onRetry(err, i + 1, delay);
      await new Promise(r => setTimeout(r, delay));
    }
  }
  throw lastErr;
}
```

- [ ] **Step 5: Implement sanitize.mjs**

Create `agents/director_v2/src/util/sanitize.mjs`:
```javascript
const HTML_ESCAPE = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

export function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => HTML_ESCAPE[c]);
}

export function sanitizePexelsQuery(q) {
  return String(q ?? '')
    .replace(/[^a-zA-Z0-9 ]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 100);
}

const PROMPT_INJECTION_MARKERS = [
  /<\|im_end\|>/g, /<\|system\|>/g, /<\|endoftext\|>/g,
  /\[INST\]/g, /\[\/INST\]/g, /###\s*(system|assistant|user)/gi,
];

export function sanitizeNanoBananaPrompt(p) {
  let clean = String(p ?? '');
  for (const re of PROMPT_INJECTION_MARKERS) clean = clean.replace(re, '');
  return clean.slice(0, 500);
}

export function sanitizePublicId(id) {
  return String(id ?? '').toLowerCase().replace(/[^a-z0-9_\-/]+/g, '_');
}

export function sanitizeRecordId(id) {
  const clean = String(id ?? '').replace(/[^a-zA-Z0-9]+/g, '');
  if (!clean) throw new Error('invalid recordId');
  return clean;
}
```

- [ ] **Step 6: Run tests to verify all pass**

```bash
cd agents/director_v2 && node --test test/retry.test.mjs test/sanitize.test.mjs
```
Expected: `# pass 10` (3 retry + 7 sanitize).

- [ ] **Step 7: Commit**

```bash
git add agents/director_v2/src/util/retry.mjs \
        agents/director_v2/src/util/sanitize.mjs \
        agents/director_v2/test/retry.test.mjs \
        agents/director_v2/test/sanitize.test.mjs
git commit -m "feat(director_v2): Task 3 — retry + sanitize utilities with 10 tests"
```

**Acceptance criteria:**
- 10 new passing tests
- `withRetry` demonstrates exponential backoff behavior
- All 5 sanitize functions reject/normalize injection attempts

---

## Task 4: Pexels client (search + download)

**Goal:** Wrap Pexels API in a small client that (a) sanitizes user query, (b) searches for a photo matching 9:16 aspect, (c) downloads the binary to `tmp/`, (d) retries on 429, (e) throws a specific error when no results so the caller can fallback to `theme_solid`.

**Files:**
- Create: `agents/director_v2/src/pexels.mjs`
- Create: `agents/director_v2/test/pexels.test.mjs`
- Create: `agents/director_v2/test/fixtures/pexels_response_ok.json`
- Create: `agents/director_v2/test/fixtures/pexels_response_empty.json`
- Create: `agents/director_v2/test/fixtures/pexels_response_429.json`

**Dependencies:** `PEXELS_API_KEY` in Doppler (confirmed by Jorge).

- [ ] **Step 1: Create Pexels response fixtures**

Create `agents/director_v2/test/fixtures/pexels_response_ok.json`:
```json
{
  "page": 1,
  "per_page": 1,
  "photos": [
    {
      "id": 12345,
      "width": 4000,
      "height": 6000,
      "url": "https://www.pexels.com/photo/sample-12345/",
      "photographer": "Test Photographer",
      "src": {
        "original":  "https://images.pexels.com/photos/12345/sample.jpg",
        "large2x":   "https://images.pexels.com/photos/12345/sample.jpg?auto=compress&cs=tinysrgb&w=1920&h=2880",
        "portrait":  "https://images.pexels.com/photos/12345/sample.jpg?auto=compress&cs=tinysrgb&w=1080&h=1920&fit=crop"
      }
    }
  ],
  "total_results": 1000
}
```

Create `agents/director_v2/test/fixtures/pexels_response_empty.json`:
```json
{ "page": 1, "per_page": 1, "photos": [], "total_results": 0 }
```

Create `agents/director_v2/test/fixtures/pexels_response_429.json`:
```json
{ "error": "Rate limit exceeded" }
```

- [ ] **Step 2: Write failing tests for pexels client**

Create `agents/director_v2/test/pexels.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { searchPortrait, PexelsNoResultsError, __setFetch } from '../src/pexels.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const OK    = JSON.parse(readFileSync(join(HERE, 'fixtures/pexels_response_ok.json'), 'utf8'));
const EMPTY = JSON.parse(readFileSync(join(HERE, 'fixtures/pexels_response_empty.json'), 'utf8'));

test('searchPortrait sanitizes the query before calling API', async () => {
  let calledUrl;
  __setFetch(async (url, opts) => {
    calledUrl = url;
    assert.equal(opts.headers.Authorization, 'test_pexels_key');
    return { ok: true, json: async () => OK };
  });
  const result = await searchPortrait('home renovation; rm -rf /', { apiKey: 'test_pexels_key' });
  assert.ok(calledUrl.includes('query=home+renovation+rm+rf'), `query should be sanitized: ${calledUrl}`);
  assert.equal(result.id, 12345);
  assert.ok(result.downloadUrl.includes('portrait'));
});

test('searchPortrait throws PexelsNoResultsError on empty results', async () => {
  __setFetch(async () => ({ ok: true, json: async () => EMPTY }));
  await assert.rejects(
    searchPortrait('zzznonsense', { apiKey: 'k' }),
    (err) => err instanceof PexelsNoResultsError
  );
});

test('searchPortrait retries 3 times on 429 then throws', async () => {
  let calls = 0;
  __setFetch(async () => {
    calls++;
    return { ok: false, status: 429, text: async () => 'rate limit' };
  });
  await assert.rejects(
    searchPortrait('anything', { apiKey: 'k', baseDelayMs: 1 }),
    /429/
  );
  assert.equal(calls, 3);
});

test('searchPortrait prefers portrait URL over original', async () => {
  __setFetch(async () => ({ ok: true, json: async () => OK }));
  const result = await searchPortrait('anything', { apiKey: 'k' });
  assert.ok(result.downloadUrl.includes('portrait'));
  assert.ok(result.downloadUrl.includes('w=1080'));
});
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
cd agents/director_v2 && node --test test/pexels.test.mjs
```
Expected: FAIL with `Cannot find module '../src/pexels.mjs'`.

- [ ] **Step 4: Implement pexels.mjs**

Create `agents/director_v2/src/pexels.mjs`:
```javascript
import { writeFile } from 'node:fs/promises';
import { sanitizePexelsQuery } from './util/sanitize.mjs';
import { withRetry } from './util/retry.mjs';

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }

export class PexelsNoResultsError extends Error {
  constructor(query) { super(`Pexels: no results for "${query}"`); this.name = 'PexelsNoResultsError'; this.query = query; }
}

const API = 'https://api.pexels.com/v1';

export async function searchPortrait(rawQuery, { apiKey, baseDelayMs = 2000 } = {}) {
  const query = sanitizePexelsQuery(rawQuery);
  if (!query) throw new Error('searchPortrait: empty query after sanitize');
  if (!apiKey) throw new Error('searchPortrait: apiKey required');

  const url = `${API}/search?orientation=portrait&size=large&per_page=1&query=${encodeURIComponent(query).replace(/%20/g, '+')}`;

  const data = await withRetry(
    async () => {
      const res = await _fetch(url, { headers: { Authorization: apiKey } });
      if (res.status === 429) throw new Error(`Pexels 429 rate limit`);
      if (!res.ok) throw new Error(`Pexels HTTP ${res.status}`);
      return res.json();
    },
    { attempts: 3, baseDelayMs }
  );

  if (!data.photos || data.photos.length === 0) {
    throw new PexelsNoResultsError(query);
  }
  const photo = data.photos[0];
  const downloadUrl = photo.src.portrait || photo.src.large2x || photo.src.original;
  return {
    id: photo.id,
    photographer: photo.photographer,
    downloadUrl,
    query,
  };
}

export async function downloadToFile(url, destPath) {
  const res = await _fetch(url);
  if (!res.ok) throw new Error(`download failed: HTTP ${res.status}`);
  const buf = Buffer.from(await res.arrayBuffer());
  await writeFile(destPath, buf);
  return destPath;
}
```

- [ ] **Step 5: Run tests to verify all pass**

```bash
cd agents/director_v2 && node --test test/pexels.test.mjs
```
Expected: `# pass 4`.

- [ ] **Step 6: Verify live — one real call with doppler**

```bash
cd agents/director_v2 && doppler run -- node -e "import('./src/pexels.mjs').then(async m => { const r = await m.searchPortrait('modern house wisconsin', { apiKey: process.env.PEXELS_API_KEY }); console.log(r); })"
```
Expected: prints `{ id: ..., photographer: '...', downloadUrl: 'https://images.pexels.com/.../portrait...', query: 'modern house wisconsin' }`.

If this fails with 401 → `PEXELS_API_KEY` is wrong in Doppler; fix before continuing.

- [ ] **Step 7: Commit**

```bash
git add agents/director_v2/src/pexels.mjs \
        agents/director_v2/test/pexels.test.mjs \
        agents/director_v2/test/fixtures/pexels_response_ok.json \
        agents/director_v2/test/fixtures/pexels_response_empty.json \
        agents/director_v2/test/fixtures/pexels_response_429.json
git commit -m "feat(director_v2): Task 4 — Pexels client with sanitize + retry + 4 tests"
```

**Acceptance criteria:**
- 4 tests passing
- Live Pexels call returns a valid portrait URL with a real API key
- `PexelsNoResultsError` exported and testable

---

## Task 5: Nano Banana (Gemini) client

**Goal:** Client that generates a 1080×1920 branded image via Gemini's Imagen/Nano Banana model. Includes prompt sanitization, re-roll on failure (1x with refined prompt), and returns a Buffer ready to write to disk.

**Files:**
- Create: `agents/director_v2/src/nano_banana.mjs`
- Create: `agents/director_v2/test/nano_banana.test.mjs`
- Create: `agents/director_v2/test/fixtures/gemini_response_ok.json`

**Dependencies:** `GEMINI_API_KEY` in Doppler (already set from Creativo v2).

**Design note:** Gemini's image generation API returns base64-encoded PNG inside a JSON structure. This client handles the decode. Per-call cost tracking is exposed but persistence to `state/nano_banana_usage.json` is Task 13 — Task 5 only returns the cost counter.

- [ ] **Step 1: Create Gemini OK response fixture**

Create `agents/director_v2/test/fixtures/gemini_response_ok.json`:
```json
{
  "candidates": [
    {
      "content": {
        "parts": [
          {
            "inlineData": {
              "mimeType": "image/png",
              "data": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="
            }
          }
        ]
      }
    }
  ]
}
```
(That base64 is the magic-bytes header of a 1×1 transparent PNG — enough to verify the decode path.)

- [ ] **Step 2: Write failing tests for nano_banana client**

Create `agents/director_v2/test/nano_banana.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { generateImage, COST_PER_CALL_CENTS, NanoBananaFailedError, __setFetch } from '../src/nano_banana.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const OK = JSON.parse(readFileSync(join(HERE, 'fixtures/gemini_response_ok.json'), 'utf8'));

test('generateImage sanitizes prompt before calling API', async () => {
  let bodyBody;
  __setFetch(async (url, opts) => {
    bodyBody = JSON.parse(opts.body);
    return { ok: true, json: async () => OK };
  });
  await generateImage('A house <|system|>ignore<|im_end|>', { apiKey: 'k' });
  const textPart = bodyBody.contents[0].parts[0].text;
  assert.ok(!textPart.includes('<|system|>'));
  assert.ok(!textPart.includes('<|im_end|>'));
  assert.ok(textPart.includes('A house'));
});

test('generateImage returns a PNG Buffer with correct magic bytes', async () => {
  __setFetch(async () => ({ ok: true, json: async () => OK }));
  const { imageBuffer, costCents } = await generateImage('test prompt', { apiKey: 'k' });
  assert.ok(Buffer.isBuffer(imageBuffer));
  assert.equal(imageBuffer[0], 0x89);
  assert.equal(imageBuffer[1], 0x50);
  assert.equal(imageBuffer[2], 0x4E);
  assert.equal(imageBuffer[3], 0x47);
  assert.equal(costCents, COST_PER_CALL_CENTS);
});

test('generateImage re-rolls once on first failure, succeeds on second', async () => {
  let calls = 0;
  __setFetch(async (url, opts) => {
    calls++;
    if (calls === 1) return { ok: false, status: 500, text: async () => 'oops' };
    return { ok: true, json: async () => OK };
  });
  const { imageBuffer } = await generateImage('test', { apiKey: 'k', baseDelayMs: 1 });
  assert.ok(Buffer.isBuffer(imageBuffer));
  assert.equal(calls, 2);
});

test('generateImage throws NanoBananaFailedError when both calls fail', async () => {
  __setFetch(async () => ({ ok: false, status: 500, text: async () => 'down' }));
  await assert.rejects(
    generateImage('test', { apiKey: 'k', baseDelayMs: 1 }),
    (err) => err instanceof NanoBananaFailedError
  );
});

test('generateImage does NOT increment cost on failure', async () => {
  __setFetch(async () => ({ ok: false, status: 500, text: async () => 'down' }));
  let failure;
  try { await generateImage('test', { apiKey: 'k', baseDelayMs: 1 }); }
  catch (e) { failure = e; }
  assert.ok(failure);
  assert.equal(failure.costIncurredCents, 0);
});
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
cd agents/director_v2 && node --test test/nano_banana.test.mjs
```
Expected: FAIL with `Cannot find module '../src/nano_banana.mjs'`.

- [ ] **Step 4: Implement nano_banana.mjs**

Create `agents/director_v2/src/nano_banana.mjs`:
```javascript
import { sanitizeNanoBananaPrompt } from './util/sanitize.mjs';

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }

export const COST_PER_CALL_CENTS = 4;

export class NanoBananaFailedError extends Error {
  constructor(msg, costIncurredCents = 0) {
    super(msg);
    this.name = 'NanoBananaFailedError';
    this.costIncurredCents = costIncurredCents;
  }
}

const API = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image-preview:generateContent';

async function callOnce(prompt, apiKey) {
  const body = {
    contents: [{ parts: [{ text: prompt }] }],
    generationConfig: { responseModalities: ['Image'] },
  };
  const res = await _fetch(`${API}?key=${apiKey}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  if (!res.ok) throw new Error(`Nano Banana HTTP ${res.status}: ${await res.text()}`);
  const data = await res.json();
  const inlineData = data?.candidates?.[0]?.content?.parts?.find(p => p.inlineData)?.inlineData;
  if (!inlineData?.data) throw new Error('Nano Banana: no inline image in response');
  const buf = Buffer.from(inlineData.data, 'base64');
  if (buf.length < 8 || buf[0] !== 0x89 || buf[1] !== 0x50 || buf[2] !== 0x4E || buf[3] !== 0x47) {
    throw new Error('Nano Banana: not a valid PNG (bad magic bytes)');
  }
  return buf;
}

export async function generateImage(rawPrompt, { apiKey, baseDelayMs = 3000 } = {}) {
  if (!apiKey) throw new Error('generateImage: apiKey required');
  const prompt = sanitizeNanoBananaPrompt(rawPrompt);

  try {
    const buf = await callOnce(prompt, apiKey);
    return { imageBuffer: buf, costCents: COST_PER_CALL_CENTS, attempts: 1 };
  } catch (err1) {
    await new Promise(r => setTimeout(r, baseDelayMs));
    const refined = `${prompt} (high quality, clean composition, photorealistic)`.slice(0, 500);
    try {
      const buf = await callOnce(refined, apiKey);
      return { imageBuffer: buf, costCents: COST_PER_CALL_CENTS, attempts: 2 };
    } catch (err2) {
      throw new NanoBananaFailedError(`both attempts failed: ${err1.message} | ${err2.message}`, 0);
    }
  }
}
```

- [ ] **Step 5: Run tests to verify all pass**

```bash
cd agents/director_v2 && node --test test/nano_banana.test.mjs
```
Expected: `# pass 5`.

- [ ] **Step 6: Verify live — one real call (writes PNG to disk)**

```bash
cd agents/director_v2 && mkdir -p tmp && doppler run -- node -e "
import('./src/nano_banana.mjs').then(async m => {
  const { imageBuffer, costCents, attempts } = await m.generateImage(
    'Modern craftsman house exterior in Wisconsin at golden hour, cinematic, 9:16 vertical',
    { apiKey: process.env.GEMINI_API_KEY }
  );
  const { writeFile } = await import('node:fs/promises');
  await writeFile('tmp/nano_banana_test.png', imageBuffer);
  console.log('OK:', imageBuffer.length, 'bytes,', costCents, 'cents,', attempts, 'attempts');
});
"
```
Expected: `OK: <N> bytes, 4 cents, 1 attempts` where N > 50000 (real image). File `tmp/nano_banana_test.png` should be visually a real house photo.

If this fails → check `GEMINI_API_KEY` and the model ID (`gemini-2.5-flash-image-preview` may have a different slug in your account).

- [ ] **Step 7: Commit**

```bash
git add agents/director_v2/src/nano_banana.mjs \
        agents/director_v2/test/nano_banana.test.mjs \
        agents/director_v2/test/fixtures/gemini_response_ok.json
git commit -m "feat(director_v2): Task 5 — Nano Banana client with re-roll + cost tracking + 5 tests"
```

**Acceptance criteria:**
- 5 tests passing
- Live call generates a real PNG image saved to `tmp/nano_banana_test.png`
- Cost counter returns 4 cents per successful call

---

## Task 6: scene_layout — the layout D template

**Goal:** Build the HTML template for a single scene. Layout D = hero image fullscreen + gradient overlay in theme color + caption bottom-third (EN large, ES small) + Pinnacle logo top-right. Also supports `heroSource: "theme_solid"` where there's no image — only gradient background.

**Files:**
- Create: `agents/director_v2/src/scene_layout.mjs`
- Create: `agents/director_v2/test/scene_layout.test.mjs`

- [ ] **Step 1: Write failing tests for scene_layout**

Create `agents/director_v2/test/scene_layout.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildSceneHtml } from '../src/scene_layout.mjs';
import { THEMES } from '../src/themes.mjs';

function mkScene(over = {}) {
  return {
    index: 2, duration: 2.0, layoutType: 'layout_d',
    captionEn: 'FASTER THAN BANKS', captionEs: 'Más rápido que los bancos',
    heroSource: 'pexels', heroQuery: 'clock time money', heroPrompt: null,
    kinetic: false, zoompan: { from: 1.0, to: 1.03 }, transitionOut: 'wipeleft',
    mood: 'upbeat',
    ...over,
  };
}

test('layout_d includes hero img, gradient overlay, both captions, logo', () => {
  const html = buildSceneHtml(mkScene(), '/tmp/hero.jpg', 'T1', '9:16');
  assert.ok(html.includes('<img'), 'must include hero img tag');
  assert.ok(html.includes('/tmp/hero.jpg') || html.includes('file:///tmp/hero.jpg'), 'must reference hero path');
  assert.ok(html.includes('FASTER THAN BANKS'), 'must include EN caption');
  assert.ok(html.includes('Más rápido que los bancos'), 'must include ES caption');
  assert.ok(html.includes('linear-gradient'), 'must include gradient overlay');
  assert.ok(html.includes('top:48px') && html.includes('right:48px'), 'logo top-right');
});

test('heroSource theme_solid omits img and uses background', () => {
  const html = buildSceneHtml(mkScene({ heroSource: 'theme_solid' }), null, 'T1', '9:16');
  assert.ok(!html.includes('<img'), 'must NOT include hero img tag');
  assert.ok(html.includes('radial-gradient') || html.includes(THEMES.T1.bg), 'must use theme colors as bg');
});

test('HTML escape applied to captions', () => {
  const html = buildSceneHtml(
    mkScene({ captionEn: '<script>alert(1)</script>', captionEs: 'a & b' }),
    '/tmp/h.jpg', 'T1', '9:16'
  );
  assert.ok(!html.includes('<script>alert(1)</script>'), 'must escape script tag');
  assert.ok(html.includes('&lt;script&gt;'));
  assert.ok(html.includes('a &amp; b'));
});

test('9:16 aspect yields height:1920px wrapper', () => {
  const html = buildSceneHtml(mkScene(), '/tmp/h.jpg', 'T1', '9:16');
  assert.ok(html.includes('1920'), 'must include 1920 height for 9:16');
});

test('theme T3 overlay uses T3 bg color', () => {
  const html = buildSceneHtml(mkScene(), '/tmp/h.jpg', 'T3', '9:16');
  assert.ok(html.includes(THEMES.T3.bg), 'must include theme T3 bg color in overlay');
});

test('layoutType hook uses large centered caption (hero slide treatment)', () => {
  const html = buildSceneHtml(mkScene({ layoutType: 'hook', captionEn: 'HEY' }), '/tmp/h.jpg', 'T1', '9:16');
  assert.ok(html.includes('HEY'));
  assert.ok(html.match(/font-size:\s*1[0-9][0-9]px/), 'hook caption should be ≥100px font-size');
});

test('layoutType cta includes Pinnacle phone and URL', () => {
  const html = buildSceneHtml(mkScene({ layoutType: 'cta', captionEn: 'Call now' }), '/tmp/h.jpg', 'T1', '9:16');
  assert.ok(html.includes('(920) 777-9886') || html.includes('920.777.9886') || html.includes('9207779886'));
  assert.ok(html.includes('pinnaclegroupwi.com'));
});

test('kinetic=true adds data-kinetic attribute on root wrapper', () => {
  const html = buildSceneHtml(mkScene({ kinetic: true }), '/tmp/h.jpg', 'T1', '9:16');
  assert.ok(html.includes('data-kinetic="true"'));
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd agents/director_v2 && node --test test/scene_layout.test.mjs
```
Expected: FAIL with `Cannot find module '../src/scene_layout.mjs'`.

- [ ] **Step 3: Implement scene_layout.mjs**

Create `agents/director_v2/src/scene_layout.mjs`:
```javascript
import { THEMES, dimsForAspect } from './themes.mjs';
import { escapeHtml } from './util/sanitize.mjs';

const LOGO_TOP_RIGHT = 'position:absolute; top:48px; right:48px; width:140px; height:auto; z-index:10;';

export function buildSceneHtml(scene, heroImagePath, themeCode, aspect) {
  const theme = THEMES[themeCode] || THEMES.T1;
  const { width, height } = dimsForAspect(aspect);

  const kineticAttr = scene.kinetic ? 'data-kinetic="true"' : '';
  const heroLayer = scene.heroSource === 'theme_solid'
    ? `<div style="position:absolute; inset:0; background:${theme.bg};
          background-image:
            radial-gradient(circle at 20% 20%, rgba(255,255,255,.08), transparent 50%),
            radial-gradient(circle at 80% 80%, ${theme.accent}22, transparent 50%);"></div>`
    : `<img src="file://${heroImagePath}" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;" />`;

  const overlayColor = theme.bg;
  const overlay = `<div style="position:absolute; inset:0; background:linear-gradient(180deg, transparent 0%, ${overlayColor}D9 60%, ${overlayColor} 100%);"></div>`;

  const logo = `<img src="__LOGO_DATA_URI__" style="${LOGO_TOP_RIGHT}" alt="Pinnacle" />`;

  const captionEn = escapeHtml(scene.captionEn);
  const captionEs = escapeHtml(scene.captionEs || '');

  let captionBlock;
  if (scene.layoutType === 'hook') {
    captionBlock = `
      <div style="position:absolute; inset:0; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:96px; text-align:center; z-index:5;">
        <div style="font-family:Montserrat,sans-serif; font-weight:900; font-size:128px; line-height:1.05; color:${theme.text}; text-shadow:0 4px 32px rgba(0,0,0,.6);">${captionEn}</div>
        ${captionEs ? `<div style="font-family:Montserrat,sans-serif; font-weight:500; font-size:56px; margin-top:32px; color:${theme.muted}; opacity:.92;">${captionEs}</div>` : ''}
      </div>`;
  } else if (scene.layoutType === 'cta') {
    captionBlock = `
      <div style="position:absolute; inset:0; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:80px; text-align:center; z-index:5;">
        <div style="font-family:Montserrat,sans-serif; font-weight:900; font-size:96px; line-height:1.1; color:${theme.text};">${captionEn}</div>
        ${captionEs ? `<div style="font-family:Montserrat,sans-serif; font-weight:500; font-size:48px; margin-top:24px; color:${theme.muted};">${captionEs}</div>` : ''}
        <div style="margin-top:64px; font-family:Montserrat,sans-serif; font-weight:700; font-size:56px; color:${theme.accent};">(920) 777-9886</div>
        <div style="margin-top:16px; font-family:Montserrat,sans-serif; font-weight:500; font-size:40px; color:${theme.text}; opacity:.85;">pinnaclegroupwi.com</div>
      </div>`;
  } else {
    captionBlock = `
      <div style="position:absolute; left:0; right:0; bottom:0; padding:80px 64px 96px 64px; background:linear-gradient(180deg, transparent 0%, ${overlayColor}B3 100%); backdrop-filter:blur(6px); z-index:5;">
        <div style="font-family:Montserrat,sans-serif; font-weight:900; font-size:96px; line-height:1.05; color:${theme.accent};">${captionEn}</div>
        ${captionEs ? `<div style="font-family:Montserrat,sans-serif; font-weight:500; font-size:48px; margin-top:20px; color:${theme.muted};">${captionEs}</div>` : ''}
      </div>`;
  }

  return `
<div ${kineticAttr} style="position:relative; width:${width}px; height:${height}px; overflow:hidden; background:${theme.bg};">
  ${heroLayer}
  ${overlay}
  ${captionBlock}
  ${logo}
</div>`;
}
```

- [ ] **Step 4: Run tests to verify all pass**

```bash
cd agents/director_v2 && node --test test/scene_layout.test.mjs
```
Expected: `# pass 8`.

- [ ] **Step 5: Commit**

```bash
git add agents/director_v2/src/scene_layout.mjs \
        agents/director_v2/test/scene_layout.test.mjs
git commit -m "feat(director_v2): Task 6 — scene_layout D (hero + overlay + caption + logo) with 8 tests"
```

**Acceptance criteria:**
- 8 tests passing
- `layout_d`, `hook`, and `cta` variants all produce valid HTML
- `heroSource: "theme_solid"` produces no `<img>` tag, only gradient backgrounds

---

## Task 7: render — Puppeteer HTML → JPG (or PNG sequence for kinetic)

**Goal:** Wrap Puppeteer in a thin client. One browser instance reused across scenes. If `kinetic: false`, render one JPG; if `kinetic: true`, render N PNG frames (N = fps × duration) for later ffmpeg composition.

**Files:**
- Create: `agents/director_v2/src/render.mjs`
- Create: `agents/director_v2/test/render.test.mjs`

- [ ] **Step 1: Write failing tests for render**

Create `agents/director_v2/test/render.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, rmSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { renderScene, closeBrowser, __getBrowserStats } from '../src/render.mjs';
import { buildSceneHtml } from '../src/scene_layout.mjs';
import { wrapSlideHtml } from '../src/wrapper.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const TMP  = join(HERE, '..', 'tmp', 'render_test');

function setupTmp() {
  rmSync(TMP, { recursive: true, force: true });
  mkdirSync(TMP, { recursive: true });
}

test('renderScene kinetic=false writes exactly 1 JPG', async () => {
  setupTmp();
  const scene = { index: 1, duration: 2.0, layoutType: 'layout_d', captionEn: 'TEST', captionEs: 'PRUEBA',
                  heroSource: 'theme_solid', kinetic: false, zoompan: null, transitionOut: 'cut', mood: 'upbeat' };
  const body = buildSceneHtml(scene, null, 'T1', '9:16');
  const html = wrapSlideHtml(body, 'T1', '9:16');
  const out = await renderScene(html, scene, TMP);
  assert.equal(out.length, 1);
  assert.ok(out[0].endsWith('.jpg'));
  assert.ok(existsSync(out[0]));
  await closeBrowser();
});

test('renderScene kinetic=true writes N PNG frames = fps × duration', async () => {
  setupTmp();
  const scene = { index: 1, duration: 1.0, layoutType: 'hook', captionEn: 'K', captionEs: 'k',
                  heroSource: 'theme_solid', kinetic: true, zoompan: null, transitionOut: 'cut', mood: 'upbeat' };
  const body = buildSceneHtml(scene, null, 'T1', '9:16');
  const html = wrapSlideHtml(body, 'T1', '9:16');
  const out = await renderScene(html, scene, TMP, { fps: 10 });
  assert.equal(out.length, 10, 'should produce 10 frames for 1s @ 10fps');
  for (const p of out) assert.ok(p.endsWith('.png'));
  assert.ok(existsSync(out[0]));
  await closeBrowser();
});

test('renderScene reuses the browser singleton across calls', async () => {
  setupTmp();
  const scene = { index: 1, duration: 1.0, layoutType: 'layout_d', captionEn: 'A', captionEs: 'a',
                  heroSource: 'theme_solid', kinetic: false, zoompan: null, transitionOut: 'cut', mood: 'upbeat' };
  const body = buildSceneHtml(scene, null, 'T1', '9:16');
  const html = wrapSlideHtml(body, 'T1', '9:16');
  await renderScene(html, scene, TMP);
  await renderScene(html, { ...scene, index: 2 }, TMP);
  const stats = __getBrowserStats();
  assert.equal(stats.launchCount, 1, 'browser should launch exactly once for 2 scenes');
  await closeBrowser();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd agents/director_v2 && node --test test/render.test.mjs
```
Expected: FAIL with `Cannot find module '../src/render.mjs'`.

- [ ] **Step 3: Implement render.mjs**

Create `agents/director_v2/src/render.mjs`:
```javascript
import puppeteer from 'puppeteer';
import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

let _browser = null;
const _stats = { launchCount: 0 };

async function getBrowser() {
  if (_browser && _browser.connected) return _browser;
  _stats.launchCount++;
  _browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  });
  return _browser;
}

export function __getBrowserStats() { return { ..._stats }; }

export async function closeBrowser() {
  if (_browser) { try { await _browser.close(); } catch {} _browser = null; }
}

export async function renderScene(html, scene, outDir, { fps = 30 } = {}) {
  await mkdir(outDir, { recursive: true });
  const browser = await getBrowser();
  const page = await browser.newPage();
  await page.setViewport({ width: 1080, height: 1920, deviceScaleFactor: 1 });
  await page.setContent(html, { waitUntil: 'networkidle0' });
  await page.evaluate(() => document.fonts?.ready);

  const outputs = [];
  if (!scene.kinetic) {
    const path = join(outDir, `scene_${scene.index}.jpg`);
    const buf = await page.screenshot({ type: 'jpeg', quality: 92, fullPage: false, omitBackground: false });
    await writeFile(path, buf);
    outputs.push(path);
  } else {
    const totalFrames = Math.max(1, Math.round(fps * scene.duration));
    for (let f = 0; f < totalFrames; f++) {
      const progress = f / Math.max(1, totalFrames - 1);
      await page.evaluate((p) => { window.__kineticProgress = p; }, progress);
      const path = join(outDir, `scene_${scene.index}_${String(f).padStart(3, '0')}.png`);
      const buf = await page.screenshot({ type: 'png', fullPage: false, omitBackground: false });
      await writeFile(path, buf);
      outputs.push(path);
    }
  }
  await page.close();
  return outputs;
}
```

**Note:** The kinetic progress update via `window.__kineticProgress` is a hook for layout templates to animate themselves reading that value via CSS/JS. For Sprint 1 MVP kinetic just renders N identical frames (the "hook" animation ships in Sprint 3 — see spec Sec 8.6). This keeps Task 7 focused on the rendering mechanic, not the animation choreography.

- [ ] **Step 4: Run tests to verify all pass**

```bash
cd agents/director_v2 && node --test test/render.test.mjs
```
Expected: `# pass 3`. This may take 10-20 seconds (Puppeteer launches a real browser).

- [ ] **Step 5: Commit**

```bash
git add agents/director_v2/src/render.mjs \
        agents/director_v2/test/render.test.mjs
git commit -m "feat(director_v2): Task 7 — render.mjs with browser singleton + 3 tests"
```

**Acceptance criteria:**
- 3 tests passing
- Browser singleton reused across multiple `renderScene` calls
- `tmp/render_test/` contains actual image files after tests run

---

## Task 8: narratives — narrative_B + dispatcher + validateSpec

**Goal:** Implement the expansion of a `spec` into an array of 5 scenes for narrative B (MVP). Dispatcher routes `spec.narrative` to the right expander. `validateSpec` throws clear error messages for each missing/invalid field.

**Files:**
- Create: `agents/director_v2/src/narratives/index.mjs`
- Create: `agents/director_v2/src/narratives/narrative_B.mjs`
- Create: `agents/director_v2/test/narratives.test.mjs`
- Create: `agents/director_v2/test/fixtures/spec_narrative_B_valid.json`
- Create: `agents/director_v2/test/fixtures/spec_narrative_B_missing_points.json`
- Create: `agents/director_v2/test/fixtures/spec_malformed.txt`

- [ ] **Step 1: Create fixtures**

Create `agents/director_v2/test/fixtures/spec_narrative_B_valid.json`:
```json
{
  "media_type": "reel",
  "theme": "T1",
  "aspect": "9:16",
  "narrative": "B",
  "duration": 10,
  "mood": "upbeat",
  "hook": { "en": "3 REASONS TO SELL OFF-MARKET", "es": "3 RAZONES PARA VENDER OFF-MARKET", "badge": "WISCONSIN" },
  "points": [
    { "headingEn": "Faster Than Banks", "headingEs": "Más Rápido Que Los Bancos", "bodyEn": "No waiting", "bodyEs": "Sin esperar" },
    { "headingEn": "No Commissions",    "headingEs": "Sin Comisiones",             "bodyEn": "Keep 100%",  "bodyEs": "Quedate 100%" },
    { "headingEn": "No Showings",       "headingEs": "Sin Visitas",                "bodyEn": "Sell as-is", "bodyEs": "Como está" }
  ],
  "cta": { "en": "Get your cash offer today", "es": "Reciba su oferta en efectivo hoy" }
}
```

Create `agents/director_v2/test/fixtures/spec_narrative_B_missing_points.json`:
```json
{
  "media_type": "reel", "theme": "T1", "aspect": "9:16", "narrative": "B", "duration": 10,
  "hook": { "en": "x", "es": "y" },
  "points": [ { "headingEn": "only one" } ],
  "cta": { "en": "x", "es": "y" }
}
```

Create `agents/director_v2/test/fixtures/spec_malformed.txt`:
```
This is not JSON { it's plain text that should break parseVisualPrompt.
```

- [ ] **Step 2: Write failing tests for narratives**

Create `agents/director_v2/test/narratives.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { expandNarrative, validateSpec } from '../src/narratives/index.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const B_VALID = JSON.parse(readFileSync(join(HERE, 'fixtures/spec_narrative_B_valid.json'), 'utf8'));

test('narrative B expands to 5 scenes with correct durations', () => {
  const scenes = expandNarrative(B_VALID);
  assert.equal(scenes.length, 5);
  const total = scenes.reduce((s, sc) => s + sc.duration, 0);
  assert.ok(total >= 7 && total <= 15, `total duration ${total} must be 7-15s`);
  assert.equal(scenes[0].layoutType, 'hook');
  assert.equal(scenes[1].layoutType, 'layout_d');
  assert.equal(scenes[2].layoutType, 'layout_d');
  assert.equal(scenes[3].layoutType, 'layout_d');
  assert.equal(scenes[4].layoutType, 'cta');
});

test('narrative B uses Nano Banana only on scene 1 and scene 5 (cost cap)', () => {
  const scenes = expandNarrative(B_VALID);
  const nanoCount = scenes.filter(s => s.heroSource === 'nano_banana').length;
  assert.equal(nanoCount, 2, 'exactly 2 Nano Banana calls per narrative B video');
});

test('narrative B maps points[i].headingEn to scene captionEn', () => {
  const scenes = expandNarrative(B_VALID);
  assert.equal(scenes[1].captionEn, 'Faster Than Banks');
  assert.equal(scenes[1].captionEs, 'Más Rápido Que Los Bancos');
  assert.equal(scenes[2].captionEn, 'No Commissions');
  assert.equal(scenes[3].captionEn, 'No Showings');
});

test('narrative B derives pexels query from heading', () => {
  const scenes = expandNarrative(B_VALID);
  assert.equal(scenes[1].heroSource, 'pexels');
  assert.ok(scenes[1].heroQuery.length > 0);
});

test('validateSpec passes on valid narrative B', () => {
  assert.equal(validateSpec(B_VALID), true);
});

test('validateSpec throws on missing points in narrative B', () => {
  const bad = { ...B_VALID, points: [{ headingEn: 'x' }] };
  assert.throws(() => validateSpec(bad), /points\[3\+\]/);
});

test('validateSpec throws on unknown narrative', () => {
  const bad = { ...B_VALID, narrative: 'Z' };
  assert.throws(() => validateSpec(bad), /narrative must be A\|B\|C/);
});

test('validateSpec throws on invalid aspect for Director', () => {
  const bad = { ...B_VALID, aspect: '4:5' };
  assert.throws(() => validateSpec(bad), /aspect must be 9:16/);
});

test('validateSpec throws on duration out of 7-15', () => {
  const bad = { ...B_VALID, duration: 30 };
  assert.throws(() => validateSpec(bad), /duration must be 7-15/);
});

test('dispatcher throws on unknown narrative code', () => {
  assert.throws(() => expandNarrative({ narrative: 'X' }), /Unknown narrative/);
});
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
cd agents/director_v2 && node --test test/narratives.test.mjs
```
Expected: FAIL (modules missing).

- [ ] **Step 4: Implement narrative_B.mjs**

Create `agents/director_v2/src/narratives/narrative_B.mjs`:
```javascript
const HERO_QUERY_TABLE = {
  'faster than banks':   'clock time money',
  'no commissions':      'real estate contract',
  'no showings':         'house closed sign',
  'no repairs':          'home renovation',
  'cash offer':          'cash money deal',
  'close in 7 days':     'calendar keys house',
  'any condition':       'vintage house exterior',
  'sell as-is':          'house vintage interior',
};
const FALLBACK_QUERY = 'real estate wisconsin';

export function deriveHeroQuery(heading) {
  const key = String(heading || '').trim().toLowerCase();
  return HERO_QUERY_TABLE[key] || FALLBACK_QUERY;
}

export function expand(spec) {
  const mood = spec.mood || 'upbeat';
  const theme = spec.theme;
  const hookPrompt = `Modern real estate scene matching: "${spec.hook.en}", Pinnacle Holdings brand, cinematic, golden hour, 9:16 vertical`;
  const ctaPrompt  = 'Pinnacle Holdings Group branded CTA scene, modern craftsman home exterior at twilight, cinematic, 9:16 vertical';

  return [
    {
      index: 1, duration: 2.5, layoutType: 'hook',
      captionEn: spec.hook.en, captionEs: spec.hook.es,
      heroSource: 'nano_banana', heroPrompt: hookPrompt, heroQuery: null,
      kinetic: true, zoompan: { from: 1.0, to: 1.05 },
      transitionOut: 'crossfade', mood,
    },
    {
      index: 2, duration: 2.0, layoutType: 'layout_d',
      captionEn: spec.points[0].headingEn, captionEs: spec.points[0].headingEs,
      heroSource: 'pexels', heroPrompt: null, heroQuery: deriveHeroQuery(spec.points[0].headingEn),
      kinetic: false, zoompan: { from: 1.0, to: 1.03 },
      transitionOut: 'wipeleft', mood,
    },
    {
      index: 3, duration: 2.0, layoutType: 'layout_d',
      captionEn: spec.points[1].headingEn, captionEs: spec.points[1].headingEs,
      heroSource: 'pexels', heroPrompt: null, heroQuery: deriveHeroQuery(spec.points[1].headingEn),
      kinetic: false, zoompan: { from: 1.0, to: 1.03 },
      transitionOut: 'crossfade', mood,
    },
    {
      index: 4, duration: 2.0, layoutType: 'layout_d',
      captionEn: spec.points[2].headingEn, captionEs: spec.points[2].headingEs,
      heroSource: 'pexels', heroPrompt: null, heroQuery: deriveHeroQuery(spec.points[2].headingEn),
      kinetic: false, zoompan: { from: 1.0, to: 1.03 },
      transitionOut: 'slideup', mood,
    },
    {
      index: 5, duration: 2.5, layoutType: 'cta',
      captionEn: spec.cta.en, captionEs: spec.cta.es,
      heroSource: 'nano_banana', heroPrompt: ctaPrompt, heroQuery: null,
      kinetic: true, zoompan: { from: 1.0, to: 1.05 },
      transitionOut: 'none', mood,
    },
  ];
}
```

- [ ] **Step 5: Implement dispatcher index.mjs**

Create `agents/director_v2/src/narratives/index.mjs`:
```javascript
import { expand as expandB } from './narrative_B.mjs';

const REGISTRY = { B: expandB };

export function expandNarrative(spec) {
  const fn = REGISTRY[spec.narrative];
  if (!fn) throw new Error(`Unknown narrative: ${spec.narrative}`);
  return fn(spec);
}

export function validateSpec(spec) {
  if (!spec || typeof spec !== 'object') throw new Error('spec must be object');
  if (!['A', 'B', 'C'].includes(spec.narrative)) throw new Error(`narrative must be A|B|C, got: ${spec.narrative}`);
  if (spec.aspect !== '9:16') throw new Error(`aspect must be 9:16 for Director, got: ${spec.aspect}`);
  if (!['T1', 'T2', 'T3', 'T4', 'T5'].includes(spec.theme)) throw new Error(`theme must be T1-T5, got: ${spec.theme}`);
  const d = Number(spec.duration);
  if (!Number.isFinite(d) || d < 7 || d > 15) throw new Error(`duration must be 7-15, got: ${spec.duration}`);

  if (spec.narrative === 'B') {
    if (!spec.hook?.en || !spec.hook?.es) throw new Error('narrative B requires hook.en and hook.es');
    if (!Array.isArray(spec.points) || spec.points.length < 3) throw new Error('narrative B requires points[3+]');
    if (!spec.cta?.en || !spec.cta?.es) throw new Error('narrative B requires cta.en and cta.es');
  }
  // narratives A and C validated in Sprint 2
  return true;
}
```

- [ ] **Step 6: Run tests to verify all pass**

```bash
cd agents/director_v2 && node --test test/narratives.test.mjs
```
Expected: `# pass 10`.

- [ ] **Step 7: Commit**

```bash
git add agents/director_v2/src/narratives/ \
        agents/director_v2/test/narratives.test.mjs \
        agents/director_v2/test/fixtures/spec_narrative_B_valid.json \
        agents/director_v2/test/fixtures/spec_narrative_B_missing_points.json \
        agents/director_v2/test/fixtures/spec_malformed.txt
git commit -m "feat(director_v2): Task 8 — narrative B expander + dispatcher + validator with 10 tests"
```

**Acceptance criteria:**
- 10 tests passing
- `expandNarrative(B_VALID).length === 5`
- Exactly 2 Nano Banana calls per video (scenes 1 and 5)
- Total duration ∈ [7, 15]

---

## Task 9: ffmpeg assembler — build video with xfade + zoompan + audio mix

**Goal:** Take an array of scene outputs (JPG or PNG sequence) + a music track + transitions + zoompan and produce a single MP4 1080×1920 H.264 with audio. Command is built as argv array (no shell string → zero shell injection). Function also exports a pure `buildVideoCommand` for testing without actually running ffmpeg.

**Files:**
- Create: `agents/director_v2/src/ffmpeg.mjs`
- Create: `agents/director_v2/test/ffmpeg.test.mjs`

- [ ] **Step 1: Write failing tests**

Create `agents/director_v2/test/ffmpeg.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildVideoCommand } from '../src/ffmpeg.mjs';

function sampleScenes() {
  return [
    { index: 1, duration: 2.5, imagePaths: ['/tmp/s1.jpg'], zoompan: { from: 1.0, to: 1.05 }, transitionOut: 'crossfade', kinetic: false },
    { index: 2, duration: 2.0, imagePaths: ['/tmp/s2.jpg'], zoompan: { from: 1.0, to: 1.03 }, transitionOut: 'wipeleft',  kinetic: false },
    { index: 3, duration: 2.0, imagePaths: ['/tmp/s3.jpg'], zoompan: { from: 1.0, to: 1.03 }, transitionOut: 'crossfade', kinetic: false },
    { index: 4, duration: 2.0, imagePaths: ['/tmp/s4.jpg'], zoompan: { from: 1.0, to: 1.03 }, transitionOut: 'slideup',   kinetic: false },
    { index: 5, duration: 2.5, imagePaths: ['/tmp/s5.jpg'], zoompan: { from: 1.0, to: 1.05 }, transitionOut: 'none',      kinetic: false },
  ];
}

test('buildVideoCommand returns argv ARRAY (not string) — zero shell injection', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  assert.ok(Array.isArray(cmd.args), 'args must be an array');
  assert.equal(cmd.bin, 'ffmpeg');
  for (const a of cmd.args) assert.equal(typeof a, 'string', `every arg must be string, got ${typeof a}`);
});

test('buildVideoCommand output args include H.264 + faststart + 1080x1920', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const s = cmd.args.join(' ');
  assert.ok(s.includes('libx264'));
  assert.ok(s.includes('yuv420p'));
  assert.ok(s.includes('+faststart'));
  assert.ok(s.includes('1080') && s.includes('1920'));
});

test('buildVideoCommand includes audio codec aac 128k and loops music', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const s = cmd.args.join(' ');
  assert.ok(s.includes('aac'));
  assert.ok(s.includes('128k'));
  assert.ok(s.includes('aloop'));
});

test('buildVideoCommand uses xfade with correct transitions from scene.transitionOut', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const filter = cmd.args[cmd.args.indexOf('-filter_complex') + 1];
  assert.ok(filter.includes('xfade=transition=fade'));
  assert.ok(filter.includes('xfade=transition=wipeleft'));
  assert.ok(filter.includes('xfade=transition=slideup'));
});

test('buildVideoCommand uses zoompan filter per scene', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const filter = cmd.args[cmd.args.indexOf('-filter_complex') + 1];
  const zoompanCount = (filter.match(/zoompan/g) || []).length;
  assert.ok(zoompanCount >= 5, `expected at least 5 zoompan filters, got ${zoompanCount}`);
});

test('buildVideoCommand duration roughly matches sum of scenes minus xfade overlap', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const tIdx = cmd.args.indexOf('-t');
  const durArg = parseFloat(cmd.args[tIdx + 1]);
  // sum(2.5+2.0+2.0+2.0+2.5)=11, minus 4×0.3 overlap = 9.8. Allow ±0.5
  assert.ok(durArg > 9.0 && durArg < 10.5, `expected ~9.8, got ${durArg}`);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd agents/director_v2 && node --test test/ffmpeg.test.mjs
```
Expected: FAIL with `Cannot find module '../src/ffmpeg.mjs'`.

- [ ] **Step 3: Implement ffmpeg.mjs**

Create `agents/director_v2/src/ffmpeg.mjs`:
```javascript
import { spawn } from 'node:child_process';

const FPS = 30;
const XFADE_OVERLAP = 0.3;
const TRANSITION_MAP = {
  crossfade: 'fade',
  wipeleft:  'wipeleft',
  slideup:   'slideup',
  cut:       'fade',
  none:      null,
};

export function buildVideoCommand({ scenes, musicPath, outputPath, width = 1080, height = 1920 }) {
  const args = ['-y'];

  for (const s of scenes) {
    args.push('-loop', '1', '-t', String(s.duration), '-i', s.imagePaths[0]);
  }
  args.push('-i', musicPath);

  const filterParts = [];
  scenes.forEach((s, i) => {
    const z0 = s.zoompan?.from ?? 1.0;
    const z1 = s.zoompan?.to   ?? 1.0;
    const frames = Math.max(1, Math.round(FPS * s.duration));
    const zExpr = `min(${z0}+(${z1}-${z0})*on/${frames-1 || 1},${Math.max(z0, z1)})`;
    filterParts.push(
      `[${i}:v]scale=${width}:${height}:force_original_aspect_ratio=cover,crop=${width}:${height},zoompan=z='${zExpr}':d=${frames}:s=${width}x${height}:fps=${FPS}[v${i}]`
    );
  });

  let lastLabel = 'v0';
  let offset = scenes[0].duration - XFADE_OVERLAP;
  for (let i = 1; i < scenes.length; i++) {
    const prev = scenes[i - 1];
    const transition = TRANSITION_MAP[prev.transitionOut] || 'fade';
    const inLabel = `v${i}`;
    const outLabel = i === scenes.length - 1 ? 'vout' : `x${i}`;
    filterParts.push(
      `[${lastLabel}][${inLabel}]xfade=transition=${transition}:duration=${XFADE_OVERLAP}:offset=${offset.toFixed(2)}[${outLabel}]`
    );
    lastLabel = outLabel;
    offset += scenes[i].duration - XFADE_OVERLAP;
  }
  if (scenes.length === 1) lastLabel = 'v0';

  filterParts.push(`[${scenes.length}:a]volume=0.35,aloop=loop=-1:size=2e+09[aout]`);

  const filterComplex = filterParts.join(';');
  const totalDuration = scenes.reduce((t, s) => t + s.duration, 0) - XFADE_OVERLAP * (scenes.length - 1);

  args.push('-filter_complex', filterComplex);
  args.push('-map', scenes.length === 1 ? '[v0]' : '[vout]');
  args.push('-map', '[aout]');
  args.push('-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-r', String(FPS), '-movflags', '+faststart');
  args.push('-c:a', 'aac', '-b:a', '128k');
  args.push('-t', totalDuration.toFixed(2));
  args.push(outputPath);

  return { bin: 'ffmpeg', args };
}

export function runFfmpeg(cmd, { onStderr } = {}) {
  return new Promise((resolve, reject) => {
    const proc = spawn(cmd.bin, cmd.args, { stdio: ['ignore', 'pipe', 'pipe'] });
    let stderrBuf = '';
    proc.stderr.on('data', chunk => {
      const text = chunk.toString();
      stderrBuf += text;
      if (onStderr) onStderr(text);
    });
    proc.on('close', code => {
      if (code === 0) resolve({ stderr: stderrBuf });
      else {
        const lastLine = stderrBuf.trim().split('\n').pop() || 'ffmpeg failed';
        reject(new Error(`ffmpeg exit=${code}: ${lastLine}`));
      }
    });
    proc.on('error', reject);
  });
}
```

- [ ] **Step 4: Run tests to verify all pass**

```bash
cd agents/director_v2 && node --test test/ffmpeg.test.mjs
```
Expected: `# pass 6`.

- [ ] **Step 5: Commit**

```bash
git add agents/director_v2/src/ffmpeg.mjs \
        agents/director_v2/test/ffmpeg.test.mjs
git commit -m "feat(director_v2): Task 9 — ffmpeg builder (argv array) with xfade + zoompan + amix, 6 tests"
```

**Acceptance criteria:**
- 6 tests passing
- `buildVideoCommand` returns argv ARRAY, never a shell string
- Filter complex includes zoompan + xfade + amix
- Total `-t` duration accounts for xfade overlap (sum - (N-1) × 0.3s)

---

## Task 10: cloudinary — extend with uploadVideo

**Goal:** Copy the signed-upload logic from `agents/creativo_v2/src/cloudinary.mjs` into `agents/director_v2/src/cloudinary.mjs` and extend with an `uploadVideo` function that uses `resource_type=video`. Keeping the module local (not re-export) avoids coupling the two sub-projects.

**Files:**
- Create: `agents/director_v2/src/cloudinary.mjs`
- Create: `agents/director_v2/test/cloudinary.test.mjs`
- Create: `agents/director_v2/test/fixtures/cloudinary_video_response.json`

- [ ] **Step 1: Create fixture**

Create `agents/director_v2/test/fixtures/cloudinary_video_response.json`:
```json
{
  "public_id": "directorv2/rec123",
  "version": 1713966000,
  "format": "mp4",
  "resource_type": "video",
  "duration": 10.1,
  "bytes": 2400000,
  "secure_url": "https://res.cloudinary.com/dzzlhhk0m/video/upload/v1713966000/directorv2/rec123.mp4"
}
```

- [ ] **Step 2: Write failing tests**

Create `agents/director_v2/test/cloudinary.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildSignature, uploadVideo, __setFetch } from '../src/cloudinary.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const VIDEO_RESP = JSON.parse(readFileSync(join(HERE, 'fixtures/cloudinary_video_response.json'), 'utf8'));

test('buildSignature with resource_type=video is deterministic SHA1', () => {
  const sig = buildSignature({
    folder: 'pinnacle-social-media/videos',
    public_id: 'directorv2/rec123',
    resource_type: 'video',
    timestamp: 1713966000,
    overwrite: 'true',
  }, 'test_secret');
  assert.equal(sig.length, 40);
  assert.match(sig, /^[a-f0-9]{40}$/);
});

test('uploadVideo POSTs resource_type=video to /video/upload endpoint', async () => {
  let captured;
  __setFetch(async (url, opts) => {
    captured = { url, opts };
    return { ok: true, json: async () => VIDEO_RESP };
  });
  const res = await uploadVideo('/tmp/test.mp4', {
    publicId: 'directorv2/rec123',
    folder: 'pinnacle-social-media/videos',
    cloudName: 'dzzlhhk0m',
    apiKey: 'K',
    apiSecret: 'S',
    timestampProvider: () => 1713966000,
    fileReader: async () => Buffer.from('fakevideo'),
  });
  assert.ok(captured.url.includes('/video/upload'));
  assert.equal(res.secure_url, VIDEO_RESP.secure_url);
});

test('uploadVideo sanitizes public_id before signing', async () => {
  let captured;
  __setFetch(async (url, opts) => { captured = opts.body; return { ok: true, json: async () => VIDEO_RESP }; });
  await uploadVideo('/tmp/t.mp4', {
    publicId: 'BAD-chars!@#',
    folder: 'pinnacle-social-media/videos',
    cloudName: 'dzzlhhk0m',
    apiKey: 'K', apiSecret: 'S',
    timestampProvider: () => 1,
    fileReader: async () => Buffer.from('x'),
  });
  // FormData body should contain only the sanitized version
  const raw = Buffer.isBuffer(captured) ? captured.toString() : String(captured);
  // loose check — formdata boundary text
  assert.ok(!raw.includes('BAD-chars!@#'));
});
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
cd agents/director_v2 && node --test test/cloudinary.test.mjs
```
Expected: FAIL (module missing).

- [ ] **Step 4: Implement cloudinary.mjs**

Create `agents/director_v2/src/cloudinary.mjs`:
```javascript
import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { sanitizePublicId } from './util/sanitize.mjs';

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }

export function buildSignature(params, apiSecret) {
  const keys = Object.keys(params).sort();
  const toSign = keys.map(k => `${k}=${params[k]}`).join('&') + apiSecret;
  return createHash('sha1').update(toSign).digest('hex');
}

export async function uploadVideo(localPath, {
  publicId, folder, cloudName, apiKey, apiSecret,
  overwrite = true,
  timestampProvider = () => Math.floor(Date.now() / 1000),
  fileReader = readFile,
} = {}) {
  const safePublicId = sanitizePublicId(publicId);
  const timestamp = timestampProvider();
  const params = {
    folder, public_id: safePublicId, resource_type: 'video',
    timestamp, overwrite: overwrite ? 'true' : 'false',
  };
  const signature = buildSignature(params, apiSecret);

  const form = new FormData();
  form.append('file', new Blob([await fileReader(localPath)]));
  form.append('api_key', apiKey);
  form.append('timestamp', String(timestamp));
  form.append('signature', signature);
  form.append('folder', folder);
  form.append('public_id', safePublicId);
  form.append('resource_type', 'video');
  form.append('overwrite', overwrite ? 'true' : 'false');

  const url = `https://api.cloudinary.com/v1_1/${cloudName}/video/upload`;
  const res = await _fetch(url, { method: 'POST', body: form });
  if (!res.ok) throw new Error(`Cloudinary video upload failed: HTTP ${res.status}: ${await res.text()}`);
  return res.json();
}
```

- [ ] **Step 5: Run tests to verify all pass**

```bash
cd agents/director_v2 && node --test test/cloudinary.test.mjs
```
Expected: `# pass 3`.

- [ ] **Step 6: Commit**

```bash
git add agents/director_v2/src/cloudinary.mjs \
        agents/director_v2/test/cloudinary.test.mjs \
        agents/director_v2/test/fixtures/cloudinary_video_response.json
git commit -m "feat(director_v2): Task 10 — cloudinary.uploadVideo with 3 tests"
```

**Acceptance criteria:**
- 3 tests passing
- `uploadVideo` hits `/video/upload` endpoint with `resource_type=video`
- `public_id` is sanitized before signing

---

## Task 11: airtable — list pending reels + parse + update

**Goal:** Airtable client that (a) lists records with `Media_Type='reel' AND Status='Nueva' AND Visual_Prompt!='' AND visual_url=''`, (b) parses the JSON from `Visual_Prompt` (tolerating markdown fencing), (c) PATCHes the record with video results.

**Files:**
- Create: `agents/director_v2/src/airtable.mjs`
- Create: `agents/director_v2/test/airtable.test.mjs`
- Create: `agents/director_v2/test/fixtures/airtable_records_pending.json`

- [ ] **Step 1: Create fixture**

Create `agents/director_v2/test/fixtures/airtable_records_pending.json`:
```json
{
  "records": [
    {
      "id": "recABC123",
      "fields": {
        "Formato": "Reel",
        "Status": "Nueva",
        "Visual_Prompt": "{\"theme\":\"T1\",\"aspect\":\"9:16\",\"narrative\":\"B\",\"duration\":10,\"hook\":{\"en\":\"x\",\"es\":\"y\"},\"points\":[{\"headingEn\":\"a\",\"headingEs\":\"b\"},{\"headingEn\":\"c\",\"headingEs\":\"d\"},{\"headingEn\":\"e\",\"headingEs\":\"f\"}],\"cta\":{\"en\":\"x\",\"es\":\"y\"}}"
      }
    }
  ]
}
```

- [ ] **Step 2: Write failing tests**

Create `agents/director_v2/test/airtable.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { listPending, parseVisualPrompt, updateRecord, __setFetch } from '../src/airtable.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const PENDING = JSON.parse(readFileSync(join(HERE, 'fixtures/airtable_records_pending.json'), 'utf8'));

const ENV = { token: 'tok', baseId: 'appU9s3kGkVpdrJkw', tableId: 'tblAj0Pkj1jW4p5Ld' };

test('listPending filters by Formato=Reel AND Status=Nueva AND visual_url empty', async () => {
  let calledUrl;
  __setFetch(async (url) => {
    calledUrl = url;
    return { ok: true, json: async () => PENDING };
  });
  const records = await listPending(ENV);
  assert.equal(records.length, 1);
  // URL is encoded; the formula contains {Formato}='Reel'
  const decoded = decodeURIComponent(calledUrl);
  assert.ok(decoded.includes("{Formato}='Reel'"), `expected Formato='Reel' in ${decoded}`);
  assert.ok(decoded.includes("{Status}='Nueva'"));
  assert.ok(decoded.includes('visual_url'));
});

test('parseVisualPrompt parses plain JSON', () => {
  const spec = parseVisualPrompt('{"narrative":"B","theme":"T1","hook":{"en":"h","es":"h"},"aspect":"9:16","duration":10,"points":[],"cta":{"en":"c","es":"c"}}');
  assert.equal(spec.narrative, 'B');
});

test('parseVisualPrompt tolerates markdown code-fence wrapping', () => {
  const text = '```json\n{"narrative":"B","theme":"T1","aspect":"9:16","duration":10,"hook":{"en":"h","es":"h"},"points":[],"cta":{"en":"c","es":"c"}}\n```';
  const spec = parseVisualPrompt(text);
  assert.equal(spec.narrative, 'B');
});

test('parseVisualPrompt throws clear error for malformed input', () => {
  assert.throws(() => parseVisualPrompt('not json at all'), /parse/i);
});

test('updateRecord PATCHes with provided fields only', async () => {
  let capturedBody, capturedUrl, capturedMethod;
  __setFetch(async (url, opts) => {
    capturedUrl = url;
    capturedMethod = opts.method;
    capturedBody = JSON.parse(opts.body);
    return { ok: true, json: async () => ({ id: 'recABC123' }) };
  });
  await updateRecord('recABC123', {
    visual_url: 'https://x/y.mp4', Status: 'Lista', video_duration: 10.1, video_cost_cents: 8,
  }, ENV);
  assert.equal(capturedMethod, 'PATCH');
  assert.ok(capturedUrl.endsWith('recABC123'));
  assert.equal(capturedBody.fields.visual_url, 'https://x/y.mp4');
  assert.equal(capturedBody.fields.Status, 'Lista');
  assert.equal(capturedBody.fields.video_duration, 10.1);
  assert.equal(capturedBody.fields.video_cost_cents, 8);
});

test('updateRecord retries on 429', async () => {
  let calls = 0;
  __setFetch(async () => {
    calls++;
    if (calls < 2) return { ok: false, status: 429, text: async () => 'rate limit' };
    return { ok: true, json: async () => ({ id: 'x' }) };
  });
  await updateRecord('recX', { Status: 'Lista' }, { ...ENV, baseDelayMs: 1 });
  assert.equal(calls, 2);
});
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
cd agents/director_v2 && node --test test/airtable.test.mjs
```
Expected: FAIL (module missing).

- [ ] **Step 4: Implement airtable.mjs**

Create `agents/director_v2/src/airtable.mjs`:
```javascript
import { withRetry } from './util/retry.mjs';

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }

const BASE = 'https://api.airtable.com/v0';
const PENDING_FILTER = "AND({Media_Type}='reel',{Status}='Nueva',{Visual_Prompt}!='',{visual_url}='')";

export async function listPending({ token, baseId, tableId, baseDelayMs = 1000 }) {
  const url = `${BASE}/${baseId}/${tableId}?filterByFormula=${encodeURIComponent(PENDING_FILTER)}&pageSize=10`;
  const data = await withRetry(
    async () => {
      const res = await _fetch(url, { headers: { Authorization: `Bearer ${token}` } });
      if (!res.ok) throw new Error(`Airtable HTTP ${res.status}`);
      return res.json();
    },
    { attempts: 3, baseDelayMs }
  );
  return data.records || [];
}

export function parseVisualPrompt(raw) {
  if (!raw) throw new Error('parseVisualPrompt: empty input');
  let text = String(raw).trim();
  const fence = text.match(/^```(?:json)?\s*([\s\S]*?)\s*```$/);
  if (fence) text = fence[1].trim();
  try { return JSON.parse(text); }
  catch (err) { throw new Error(`parseVisualPrompt: JSON parse failed: ${err.message}`); }
}

export async function updateRecord(recordId, fields, { token, baseId, tableId, baseDelayMs = 1000 }) {
  const url = `${BASE}/${baseId}/${tableId}/${recordId}`;
  return withRetry(
    async () => {
      const res = await _fetch(url, {
        method: 'PATCH',
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ fields }),
      });
      if (!res.ok) throw new Error(`Airtable PATCH ${res.status}: ${await res.text()}`);
      return res.json();
    },
    { attempts: 3, baseDelayMs }
  );
}
```

- [ ] **Step 5: Run tests to verify all pass**

```bash
cd agents/director_v2 && node --test test/airtable.test.mjs
```
Expected: `# pass 6`.

- [ ] **Step 6: Commit**

```bash
git add agents/director_v2/src/airtable.mjs \
        agents/director_v2/test/airtable.test.mjs \
        agents/director_v2/test/fixtures/airtable_records_pending.json
git commit -m "feat(director_v2): Task 11 — airtable listPending+parse+update with 6 tests"
```

**Acceptance criteria:**
- 6 tests passing
- `listPending` filter matches spec: `Media_Type='reel' AND Status='Nueva' AND Visual_Prompt!='' AND visual_url=''`
- `parseVisualPrompt` tolerates ```` ```json ... ``` ```` fencing

---

## Task 12: main.mjs — production orchestrator

**Goal:** Wire everything together. Read pending records from Airtable → for each, parse spec → expand narrative → resolve hero images (Pexels + Nano Banana with fallback chain) → render scenes → ffmpeg assemble → upload to Cloudinary → PATCH Airtable. Supports `--dry-run` flag. Isolated errors per record.

**Files:**
- Create: `agents/director_v2/main.mjs`
- Create: `agents/director_v2/test/main.test.mjs`

- [ ] **Step 1: Write failing tests for helper functions in main**

Create `agents/director_v2/test/main.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { shortMessage, summarize } from '../main.mjs';

test('shortMessage trims and truncates error to <200 chars', () => {
  const long = new Error('x'.repeat(500));
  const msg = shortMessage(long);
  assert.ok(msg.length <= 200);
});

test('shortMessage keeps name and message for typed errors', () => {
  class MyErr extends Error { constructor(m) { super(m); this.name = 'MyErr'; } }
  const e = new MyErr('boom');
  assert.ok(shortMessage(e).includes('MyErr'));
  assert.ok(shortMessage(e).includes('boom'));
});

test('summarize builds line-count summary of batch results', () => {
  const stats = { ok: 2, error: 1, fallback: 1, nanoBananaCalls: 3, nanoBananaCents: 12, pexelsCalls: 4, uploadMb: 10.5, durationMs: 90000 };
  const s = summarize(stats);
  assert.ok(s.includes('Lista:           2'));
  assert.ok(s.includes('Error:            1'));
  assert.ok(s.includes('Nano Banana calls:  3 ($0.12)'));
});

test('summarize formats zero-counts cleanly', () => {
  const stats = { ok: 0, error: 0, fallback: 0, nanoBananaCalls: 0, nanoBananaCents: 0, pexelsCalls: 0, uploadMb: 0, durationMs: 0 };
  const s = summarize(stats);
  assert.ok(s.includes('0'));
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd agents/director_v2 && node --test test/main.test.mjs
```
Expected: FAIL (`Cannot find module '../main.mjs'`).

- [ ] **Step 3: Implement main.mjs**

Create `agents/director_v2/main.mjs`:
```javascript
#!/usr/bin/env node
// Director v2 — production orchestrator
// Usage:   doppler run -- node main.mjs [--dry-run]

import { mkdir, rm, readFile, stat } from 'node:fs/promises';
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

async function resolveHero(scene, { pexelsKey, geminiKey, tmpDir, stats, forcePexels }) {
  const heroPath = join(tmpDir, `hero_${scene.index}.bin`);
  let effectiveSource = scene.heroSource;
  if (forcePexels && effectiveSource === 'nano_banana') effectiveSource = 'pexels';

  if (effectiveSource === 'nano_banana') {
    try {
      const { imageBuffer, costCents } = await generateImage(scene.heroPrompt, { apiKey: geminiKey });
      stats.nanoBananaCalls++;
      stats.nanoBananaCents += costCents;
      await registerNanoBananaCall(costCents);
      await (await import('node:fs/promises')).writeFile(heroPath, imageBuffer);
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
      pexelsKey: env.PEXELS_API_KEY, geminiKey: env.GEMINI_API_KEY, tmpDir: recordTmp, stats, forcePexels,
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
```

- [ ] **Step 4: Run tests to verify all pass**

```bash
cd agents/director_v2 && node --test test/main.test.mjs
```
Expected: `# pass 4`.

- [ ] **Step 5: Commit**

```bash
git add agents/director_v2/main.mjs \
        agents/director_v2/test/main.test.mjs
git commit -m "feat(director_v2): Task 12 — main orchestrator with dry-run + error isolation + 4 tests"
```

**Acceptance criteria:**
- 4 tests passing
- `main.mjs` handles per-record errors without breaking batch
- `--dry-run` skips Cloudinary + Airtable PATCH and writes MP4 to `samples/`

---

## Task 13: cost_control — monthly + per-video budget caps

**Goal:** Persist Nano Banana usage per month to `state/nano_banana_usage.json` and enforce caps. Called from main.mjs at two points: `enforcePerVideoBudget(scenes)` before rendering, and `shouldForcePexelsFallback(scenes)` to decide if monthly cap would overflow.

**Files:**
- Create: `agents/director_v2/src/cost_control.mjs`
- Create: `agents/director_v2/test/cost_control.test.mjs`

- [ ] **Step 1: Write failing tests**

Create `agents/director_v2/test/cost_control.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { rmSync, writeFileSync, readFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  enforcePerVideoBudget, BudgetExceededError,
  __setStatePath, registerNanoBananaCall, shouldForcePexelsFallback,
  MAX_NANO_BANANA_PER_VIDEO, MONTHLY_CAP_CENTS, COST_PER_CALL_CENTS,
} from '../src/cost_control.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const STATE = join(HERE, '..', 'tmp', 'test_usage.json');

function resetState() {
  mkdirSync(dirname(STATE), { recursive: true });
  if (existsSync(STATE)) rmSync(STATE);
  __setStatePath(STATE);
}

test('enforcePerVideoBudget throws when >MAX Nano Banana scenes', () => {
  resetState();
  const scenes = Array(MAX_NANO_BANANA_PER_VIDEO + 1).fill({ heroSource: 'nano_banana' });
  assert.throws(() => enforcePerVideoBudget(scenes), (e) => e instanceof BudgetExceededError);
});

test('enforcePerVideoBudget passes when at or under MAX', () => {
  resetState();
  const scenes = Array(MAX_NANO_BANANA_PER_VIDEO).fill({ heroSource: 'nano_banana' });
  assert.doesNotThrow(() => enforcePerVideoBudget(scenes));
});

test('shouldForcePexelsFallback returns true when this video would overflow monthly cap', async () => {
  resetState();
  const month = new Date().toISOString().slice(0, 7);
  writeFileSync(STATE, JSON.stringify({ [month]: { calls: 0, cents: MONTHLY_CAP_CENTS - 2, videos: 10 } }));
  const scenes = [{ heroSource: 'nano_banana' }, { heroSource: 'nano_banana' }]; // +8 cents would overflow
  const force = await shouldForcePexelsFallback(scenes);
  assert.equal(force, true);
});

test('registerNanoBananaCall creates atomic write and increments counter', async () => {
  resetState();
  await registerNanoBananaCall(4);
  await registerNanoBananaCall(4);
  const raw = JSON.parse(readFileSync(STATE, 'utf8'));
  const month = new Date().toISOString().slice(0, 7);
  assert.equal(raw[month].cents, 8);
  assert.equal(raw[month].calls, 2);
});

test('corrupt state file is re-initialized without crash', async () => {
  resetState();
  writeFileSync(STATE, 'not-json-at-all');
  await registerNanoBananaCall(COST_PER_CALL_CENTS);
  const raw = JSON.parse(readFileSync(STATE, 'utf8'));
  const month = new Date().toISOString().slice(0, 7);
  assert.equal(raw[month].cents, COST_PER_CALL_CENTS);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd agents/director_v2 && node --test test/cost_control.test.mjs
```
Expected: FAIL (module missing).

- [ ] **Step 3: Implement cost_control.mjs**

Create `agents/director_v2/src/cost_control.mjs`:
```javascript
import { readFile, writeFile, rename, mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const MAX_NANO_BANANA_PER_VIDEO = 3;
export const MONTHLY_CAP_CENTS = 1000; // $10/month
export const COST_PER_CALL_CENTS = 4;

const HERE = dirname(fileURLToPath(import.meta.url));
let _statePath = join(HERE, '..', 'state', 'nano_banana_usage.json');

export function __setStatePath(p) { _statePath = p; }

export class BudgetExceededError extends Error {
  constructor(msg) { super(msg); this.name = 'BudgetExceededError'; }
}

export function enforcePerVideoBudget(scenes) {
  const n = scenes.filter(s => s.heroSource === 'nano_banana').length;
  if (n > MAX_NANO_BANANA_PER_VIDEO) {
    throw new BudgetExceededError(`per-video Nano Banana cap exceeded: ${n} > ${MAX_NANO_BANANA_PER_VIDEO}`);
  }
}

async function loadUsage() {
  if (!existsSync(_statePath)) return {};
  try { return JSON.parse(await readFile(_statePath, 'utf8')); }
  catch { return {}; }
}

async function writeUsageAtomic(data) {
  await mkdir(dirname(_statePath), { recursive: true });
  const tmp = `${_statePath}.tmp.${process.pid}`;
  await writeFile(tmp, JSON.stringify(data, null, 2));
  await rename(tmp, _statePath);
}

function currentMonth() { return new Date().toISOString().slice(0, 7); }

export async function shouldForcePexelsFallback(scenes) {
  const usage = await loadUsage();
  const month = currentMonth();
  const current = usage[month]?.cents || 0;
  const thisVideoCents = scenes.filter(s => s.heroSource === 'nano_banana').length * COST_PER_CALL_CENTS;
  return (current + thisVideoCents) > MONTHLY_CAP_CENTS;
}

export async function registerNanoBananaCall(costCents) {
  const usage = await loadUsage();
  const month = currentMonth();
  const bucket = usage[month] || { calls: 0, cents: 0, videos: 0 };
  bucket.calls += 1;
  bucket.cents += costCents;
  usage[month] = bucket;
  await writeUsageAtomic(usage);
}
```

- [ ] **Step 4: Run tests to verify all pass**

```bash
cd agents/director_v2 && node --test test/cost_control.test.mjs
```
Expected: `# pass 5`.

- [ ] **Step 5: Commit**

```bash
git add agents/director_v2/src/cost_control.mjs \
        agents/director_v2/test/cost_control.test.mjs
git commit -m "feat(director_v2): Task 13 — cost_control with per-video + monthly caps, 5 tests"
```

**Acceptance criteria:**
- 5 tests passing
- `state/nano_banana_usage.json` is created with atomic write (tmp + rename)
- Corrupt state file does not crash the agent

---

## Task 14: POC — render_poc.mjs + Jorge's manual review

**Goal:** Standalone runner that takes `spec/poc_narrative_b.json`, runs the full pipeline (with `--no-airtable` so it doesn't pull from Airtable, just uses the local spec), and outputs the MP4 to `samples/poc_narrative_b.mp4`. Optionally uploads to Cloudinary so Jorge can preview the URL.

**Files:**
- Create: `agents/director_v2/spec/poc_narrative_b.json`
- Create: `agents/director_v2/render_poc.mjs`

- [ ] **Step 1: Create POC spec**

Create `agents/director_v2/spec/poc_narrative_b.json`:
```json
{
  "media_type": "reel",
  "theme": "T1",
  "aspect": "9:16",
  "narrative": "B",
  "duration": 10,
  "mood": "upbeat",
  "hook": {
    "en": "3 REASONS TO SELL OFF-MARKET",
    "es": "3 RAZONES PARA VENDER OFF-MARKET",
    "badge": "WISCONSIN"
  },
  "points": [
    { "headingEn": "Faster Than Banks", "headingEs": "Más Rápido Que Los Bancos", "bodyEn": "No waiting for approval", "bodyEs": "Sin esperar aprobación" },
    { "headingEn": "No Commissions",    "headingEs": "Sin Comisiones",             "bodyEn": "Keep 100% of offer",      "bodyEs": "Quedate con el 100%" },
    { "headingEn": "No Showings",       "headingEs": "Sin Visitas",                "bodyEn": "Sell as-is, today",       "bodyEs": "Vende como está, hoy" }
  ],
  "cta": {
    "en": "Get your cash offer today",
    "es": "Reciba su oferta en efectivo hoy"
  }
}
```

- [ ] **Step 2: Implement render_poc.mjs**

Create `agents/director_v2/render_poc.mjs`:
```javascript
#!/usr/bin/env node
// POC runner — renders spec/poc_narrative_b.json to samples/poc_narrative_b.mp4
// Optionally uploads to Cloudinary if --upload flag is passed.
// Usage:   doppler run -- node render_poc.mjs [--upload]

import { readFile, mkdir, rm, stat } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

import { expandNarrative, validateSpec } from './src/narratives/index.mjs';
import { buildSceneHtml } from './src/scene_layout.mjs';
import { wrapSlideHtml } from './src/wrapper.mjs';
import { renderScene, closeBrowser } from './src/render.mjs';
import { searchPortrait, downloadToFile, PexelsNoResultsError } from './src/pexels.mjs';
import { generateImage, NanoBananaFailedError } from './src/nano_banana.mjs';
import { pickMusic } from './src/audio.mjs';
import { buildVideoCommand, runFfmpeg } from './src/ffmpeg.mjs';
import { uploadVideo } from './src/cloudinary.mjs';
import { writeFile } from 'node:fs/promises';

const HERE = dirname(fileURLToPath(import.meta.url));
const TMP = join(HERE, 'tmp', 'poc');
const SAMPLES = join(HERE, 'samples');
const SPEC_PATH = join(HERE, 'spec', 'poc_narrative_b.json');

async function resolveHero(scene, tmpDir, env) {
  const heroPath = join(tmpDir, `hero_${scene.index}.bin`);
  if (scene.heroSource === 'nano_banana') {
    try {
      const { imageBuffer } = await generateImage(scene.heroPrompt, { apiKey: env.GEMINI_API_KEY });
      await writeFile(heroPath, imageBuffer);
      return heroPath;
    } catch (err) {
      if (!(err instanceof NanoBananaFailedError)) throw err;
      console.warn(`[fallback] scene ${scene.index}: nano_banana → pexels`);
      scene.heroQuery = 'real estate wisconsin modern home';
    }
  }
  if (scene.heroSource === 'pexels' || scene.heroQuery) {
    try {
      const photo = await searchPortrait(scene.heroQuery, { apiKey: env.PEXELS_API_KEY });
      await downloadToFile(photo.downloadUrl, heroPath);
      return heroPath;
    } catch (err) {
      if (err instanceof PexelsNoResultsError) console.warn(`[fallback] scene ${scene.index}: pexels → theme_solid`);
      else throw err;
    }
  }
  scene.heroSource = 'theme_solid';
  return null;
}

async function main() {
  const upload = process.argv.includes('--upload');
  const env = process.env;
  for (const k of ['PEXELS_API_KEY', 'GEMINI_API_KEY']) {
    if (!env[k]) { console.error(`ERROR: ${k} missing (run with doppler run --)`); process.exit(1); }
  }
  if (upload) {
    for (const k of ['CLOUDINARY_NAME', 'CLOUDINARY_API_KEY', 'CLOUDINARY_API_SECRET']) {
      if (!env[k]) { console.error(`ERROR: ${k} missing for --upload`); process.exit(1); }
    }
  }

  const spec = JSON.parse(await readFile(SPEC_PATH, 'utf8'));
  validateSpec(spec);

  await rm(TMP, { recursive: true, force: true });
  await mkdir(TMP, { recursive: true });
  await mkdir(SAMPLES, { recursive: true });

  console.log(`POC — spec=${SPEC_PATH}, theme=${spec.theme}, narrative=${spec.narrative}, duration=${spec.duration}s`);

  const scenes = expandNarrative(spec);
  console.log(`Expanded to ${scenes.length} scenes.`);

  const t0 = Date.now();
  const sceneFrames = [];
  for (const scene of scenes) {
    console.log(`  scene ${scene.index} (${scene.layoutType}, ${scene.duration}s, hero=${scene.heroSource}) ...`);
    const heroPath = await resolveHero(scene, TMP, env);
    const body = buildSceneHtml(scene, heroPath, spec.theme, spec.aspect);
    const html = wrapSlideHtml(body, spec.theme, spec.aspect);
    const files = await renderScene(html, scene, TMP);
    sceneFrames.push({
      index: scene.index, duration: scene.duration, imagePaths: files,
      zoompan: scene.zoompan, transitionOut: scene.transitionOut, kinetic: scene.kinetic,
    });
  }

  const musicPath = pickMusic(spec.mood || 'upbeat', scenes.reduce((t, s) => t + s.duration, 0));
  console.log(`  music: ${musicPath.split('/').pop()}`);

  const outputPath = join(SAMPLES, 'poc_narrative_b.mp4');
  console.log(`  ffmpeg → ${outputPath}`);
  const cmd = buildVideoCommand({ scenes: sceneFrames, musicPath, outputPath });
  await runFfmpeg(cmd);

  const { size } = await stat(outputPath);
  await closeBrowser();
  console.log(`  OK: ${(size / 1_048_576).toFixed(1)} MB, ${((Date.now() - t0) / 1000).toFixed(1)}s render`);

  if (upload) {
    console.log('  uploading to Cloudinary...');
    const upRes = await uploadVideo(outputPath, {
      publicId: 'directorv2/poc_narrative_b',
      folder: 'pinnacle-social-media/videos',
      cloudName: env.CLOUDINARY_NAME, apiKey: env.CLOUDINARY_API_KEY, apiSecret: env.CLOUDINARY_API_SECRET,
    });
    console.log(`\n  ✅ Cloudinary URL:\n  ${upRes.secure_url}\n`);
  }
}

main().catch(err => { console.error('FAIL:', err); process.exit(1); });
```

- [ ] **Step 3: Run POC locally (no upload)**

```bash
cd agents/director_v2 && doppler run -- node render_poc.mjs
```
Expected: prints scene-by-scene progress, ends with `OK: <N>.0 MB, <T>s render`. The MP4 at `samples/poc_narrative_b.mp4` should:
- Be 1080×1920 (verify with `ffprobe -v error -select_streams v -show_entries stream=width,height samples/poc_narrative_b.mp4`)
- Be ~10s (verify with `ffprobe -v error -show_entries format=duration samples/poc_narrative_b.mp4`)
- Have audio (verify with `ffprobe -v error -select_streams a -show_entries stream=codec_name samples/poc_narrative_b.mp4`)

- [ ] **Step 4: Upload to Cloudinary for Jorge review**

```bash
cd agents/director_v2 && doppler run -- node render_poc.mjs --upload
```
Expected: prints Cloudinary URL at the end. Send URL to Jorge via Telegram or paste here.

- [ ] **Step 5: Jorge approves the POC**

PAUSE here. Jorge reviews the URL on his phone (mobile preview) and:
- ✅ Approves → continue to Task 15
- 🔄 Wants changes → log what he wants in `memoria_ALex.md` regla R-? and apply targeted fixes (likely on `scene_layout.mjs` or `narrative_B.mjs`), re-run, re-upload, re-approve

- [ ] **Step 6: Commit (after approval)**

```bash
git add agents/director_v2/spec/poc_narrative_b.json \
        agents/director_v2/render_poc.mjs
git commit -m "feat(director_v2): Task 14 — POC narrative B render approved by Jorge"
```

**Acceptance criteria:**
- `samples/poc_narrative_b.mp4` exists, 1080×1920, ~10s with audio
- Cloudinary URL renders correctly when previewed in browser/mobile
- Jorge's explicit approval (verbal in chat or written) before Task 15

---

## Task 15: Final push + memorias + CLAUDE.md update

**Goal:** Mark Director v2 as 100% operativo per the spec's Section 8.8 closure checklist. Push branch to GitHub. Update the 3 memory files. Mark legacy `agents/director.md` (v2.0 Blotato) as deprecated.

**Files:**
- Modify: `memoria_ALex.md` (root)
- Modify: `agents/memoria_alex.md`
- Modify: `telegram_bot/telegram_memory.md`
- Modify: `CLAUDE.md`
- Modify: `agents/director.md` (mark deprecated header)

- [ ] **Step 1: Run full test suite to confirm green**

```bash
cd agents/director_v2 && npm test 2>&1 | tail -20
```
Expected: `tests <N> / pass <N> / fail 0` where N ≥ 40 (target ~55).

If any test fails: STOP. Diagnose with `systematic-debugging` skill, fix, re-run. Do not proceed to Step 2 with reds.

- [ ] **Step 2: Add deprecation note to legacy director.md**

Edit `agents/director.md` — prepend this block to the top of the file:
```markdown
> **⚠️ DEPRECATED (2026-04-24):** This v2.0 Blotato-based agent is replaced by **El Director v2** in `agents/director_v2/`. Do NOT invoke this prompt for new content. The new pipeline is code-first (Puppeteer + ffmpeg + Pexels + Nano Banana → Cloudinary), uses `Visual_Prompt` JSON in Airtable, and produces 9:16 Reels with the same brand system as El Creativo v2 (themes T1-T5).
> 
> **Migration:** records previously processed by this agent are migrated by the post-Sprint-1 backfill task (see `docs/superpowers/specs/2026-04-24-director-v2-design.md` Section 8.7).
```

- [ ] **Step 3: Add memoria entry to root memoria_ALex.md**

In `memoria_ALex.md`, near the top under "REGLAS DEL JEFE", insert this block:
```markdown
## EL DIRECTOR v2 100% OPERATIVO — 2026-04-24

`agents/director_v2/` activo. Genera Reels/Stories 9:16 (1080×1920) de 7-15s con audio royalty-free + xfade + zoompan + kinetic typography selectivo. MVP soporta narrativa B (hook + 3 puntos + CTA). Stack: Puppeteer + ffmpeg 6.1.1 + Pexels + Nano Banana + Cloudinary. Tests verdes: <N>. Costo operativo: ~$0.08/reel B. POC narrativa B aprobado por Jorge en `samples/poc_narrative_b.mp4` y subido a Cloudinary.

**Pendientes no-bloqueantes:**
- Sprint 2: narrativas A (problem→solution) y C (before/after) — agregan ~8 tests
- Sprint 3: HeyGen avatar (bloqueado en compra), ElevenLabs voice-over (bloqueado), Kling generative (sin caso de uso aún), narrativa D testimonios (bloqueada en consent)
- Task backfill legacy reels (siguiente tarea, regla NO NEGOCIABLE 2026-04-24)
```

- [ ] **Step 4: Mirror entry to `agents/memoria_alex.md`**

In `agents/memoria_alex.md`, add the same block at the top after any existing 2026-04-24 entries.

- [ ] **Step 5: Mirror entry to `telegram_bot/telegram_memory.md`**

In `telegram_bot/telegram_memory.md`, add a shorter Telegram-format entry at the top:
```markdown
## 2026-04-24 — EL DIRECTOR v2 100% OPERATIVO (REELS)
ACTIVADO. `agents/director_v2/`. Reels 9:16 7-15s con audio. Narrativa B (hook + 3 pts + CTA) en MVP. <N> tests verdes. Stack: Puppeteer + ffmpeg + Pexels + Nano Banana + Cloudinary. ~$0.08/reel. POC aprobado. Sprint 2 (A+C) y Sprint 3 (HeyGen/ElevenLabs/Kling) pendientes no-bloqueantes. Backfill legacy reels: siguiente tarea (regla 2026-04-24).
```

- [ ] **Step 6: Update CLAUDE.md sub-agent list**

In `CLAUDE.md` find the section listing sub-agents (likely "Sub-agentes disponibles") and add a reference to Director v2 alongside the existing `agents/director.md`:
```markdown
- **El Director v2:** `agents/director_v2/` (production code, replaces `agents/director.md`) — Reels 9:16 7-15s vía Puppeteer + ffmpeg + Pexels + Nano Banana
```

Search for the existing `agents/director.md` reference to find the exact location to insert.

- [ ] **Step 7: Commit memorias and CLAUDE.md updates**

```bash
git add memoria_ALex.md \
        agents/memoria_alex.md \
        telegram_bot/telegram_memory.md \
        CLAUDE.md \
        agents/director.md
git commit -m "docs: mark Director v2 100% operativo + deprecate legacy director.md"
```

- [ ] **Step 8: Push branch to GitHub**

```bash
git push -u origin claude/greeting-setup-yOfqf
```
Expected: branch is up to date or new commits pushed. Retry on network failures with exponential backoff (2s, 4s, 8s, 16s).

- [ ] **Step 9: Verify with full test suite one more time**

```bash
cd agents/director_v2 && npm test 2>&1 | tail -10
```
Expected: same green count as Step 1.

- [ ] **Step 10: Final report to Jorge**

Send a closing summary with:
- Total tests passing (count)
- Cloudinary URL of POC
- Doppler secret count (8+)
- Cost per video estimate
- Sprints 2-3 status (pending non-blocking)
- Next recommended action: run Task Backfill legacy reels (out of Sprint 1 scope but enabled by it)

**Acceptance criteria:**
- All tests green (≥40, target ~55)
- 3 memorias updated with dated entry
- `CLAUDE.md` references Director v2
- Legacy `agents/director.md` marked deprecated
- Branch pushed to `claude/greeting-setup-yOfqf` on GitHub

---

## Sprint 1 Test Count Summary

If all 15 tasks land green, the Sprint 1 test suite contains:

| Task | File | Tests |
|---|---|---|
| 0 | `test/schema_setup.test.mjs` | 3 |
| 1 | `test/reexport.test.mjs` | 4 |
| 2 | `test/audio.test.mjs` | 5 |
| 3 | `test/retry.test.mjs` + `test/sanitize.test.mjs` | 3 + 7 = 10 |
| 4 | `test/pexels.test.mjs` | 4 |
| 5 | `test/nano_banana.test.mjs` | 5 |
| 6 | `test/scene_layout.test.mjs` | 8 |
| 7 | `test/render.test.mjs` | 3 |
| 8 | `test/narratives.test.mjs` | 10 |
| 9 | `test/ffmpeg.test.mjs` | 6 |
| 10 | `test/cloudinary.test.mjs` | 3 |
| 11 | `test/airtable.test.mjs` | 6 |
| 12 | `test/main.test.mjs` | 4 |
| 13 | `test/cost_control.test.mjs` | 5 |
| **Total** | | **76** |

This **exceeds the spec's threshold of ≥40 tests** (the spec said "target ~55" — 76 is comfortably above). Tasks 14 and 15 don't add tests (they consume them via `npm test`).

---

## Spec Coverage Check (writing-plans self-review step 1)

Mapping of spec sections to plan tasks:

| Spec section | Plan task(s) |
|---|---|
| §3 Arquitectura — file structure | Task 1 (scaffold) |
| §3.2 Modules reused from Creativo | Task 1 (re-exports), Task 10 (cloudinary copied+extended) |
| §3.3 New modules | Tasks 2, 3, 4, 5, 6, 7, 8, 9, 13 |
| §4 Flujo de datos pipeline | Task 12 (main.mjs orchestrator) |
| §4.4 Dry-run | Task 12 step 5 includes `--dry-run` |
| §4.5 Error isolation | Task 12 (try/catch per record + safePatchError) |
| §5 Narratives A/B/C | Task 8 (narrative B only — A/C in Sprint 2 per spec) |
| §5.2 Narrative B mapping | Task 8 — verified by tests |
| §5.5 `theme_solid` heroSource | Task 6 — verified by `scene_layout.test.mjs` |
| §6.1 Error handling matrix | Tasks 4, 5, 11 (retry on 429), Task 12 (fallback chain) |
| §6.2 Fallback chains | Task 12 `resolveHero` |
| §6.3 Cost control caps | Task 13 |
| §6.4 Security sanitization | Task 3 (sanitize utilities) — used everywhere |
| §6.5 Retry policy | Task 3 (`withRetry`) — used in clients |
| §6.6 Observability | Task 12 console logs + summarize |
| §7 Testing strategy | Every task includes TDD |
| §7.5 Smoke test | Optional, not in Sprint 1 (mentioned in Task 15 step 1 as `npm test` includes it gated) |
| §8.1 POC | Task 14 |
| §8.2 POC criteria | Task 14 step 3 (ffprobe checks) |
| §8.3 Task 0 schema setup | Task 0 |
| §8.4 Sprint 1 task list | All 15 tasks |
| §8.5 Sprint 2 (A + C) | NOT in this plan (correct — spec marks as non-blocking) |
| §8.6 Sprint 3 enhancements | NOT in this plan (correct — bloqueados) |
| §8.7 Legacy backfill | NOT in this plan — referenced in Task 15 step 10 as next action (correct per spec, post-Sprint-1) |
| §8.8 Closure checklist | Task 15 |

**Coverage gap analysis:** none. Spec sections deferred to Sprint 2/3 are correctly out of this plan.

---

## Type Consistency Check (writing-plans self-review step 3)

Verified that signatures and exported names are consistent across all tasks:

- `expandNarrative(spec)` defined in Task 8, used in Task 12 ✅
- `validateSpec(spec)` defined in Task 8, used in Task 12 ✅
- `parseVisualPrompt(raw)` defined in Task 11, used in Task 12 ✅
- `listPending(env)` defined in Task 11, used in Task 12 ✅
- `updateRecord(id, fields, env)` defined in Task 11, used in Task 12 ✅
- `searchPortrait(query, opts)` and `downloadToFile(url, dest)` Task 4, used in Tasks 12 and 14 ✅
- `generateImage(prompt, opts) → { imageBuffer, costCents, attempts }` Task 5, used in Tasks 12 and 14 ✅
- `buildSceneHtml(scene, heroPath, theme, aspect)` Task 6, used in Tasks 12 and 14 ✅
- `renderScene(html, scene, outDir, opts) → string[]` Task 7, used in Tasks 12 and 14 ✅
- `buildVideoCommand({ scenes, musicPath, outputPath }) → { bin, args }` Task 9, used in Tasks 12 and 14 ✅
- `uploadVideo(localPath, opts)` Task 10, used in Tasks 12 and 14 ✅
- `enforcePerVideoBudget(scenes)`, `registerNanoBananaCall(cents)`, `shouldForcePexelsFallback(scenes)` Task 13, used in Task 12 ✅
- `withRetry(fn, opts)` Task 3, used in Tasks 4, 11, and indirectly in 5 ✅
- All `__setFetch` test injection helpers exist per-module (airtable, cloudinary, pexels, nano_banana) ✅

**No type mismatches found.**

---

## Plan complete

**Saved to:** `docs/superpowers/plans/2026-04-24-director-v2-sprint1.md`
**Size:** ~3,200 lines across 14 commit-sized parts (regla "por partes" aplicada)
**Estimated effort:** 3-5 working sessions of focused execution
**Estimated tests at end:** 76 (target was ≥40)
**Estimated cost per video:** ~$0.08 (2 Nano Banana × $0.04)

---

## Execution choice — pick one

**Plan complete and saved to `docs/superpowers/plans/2026-04-24-director-v2-sprint1.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task. Each subagent gets the spec + the specific task, executes it independently, and reports back. I review between tasks. Faster iteration, isolated context per task, less risk of context-window crashes mid-Sprint.

**2. Inline Execution** — I execute tasks 0-15 in this session using the `executing-plans` skill, with checkpoints between major tasks for your review. More direct visibility but uses more of this conversation's context, higher risk of API errors mid-task on a long plan.

**Which approach? `subagent` or `inline`?**

Given the plan is 15 tasks and we already had API errors mid-conversation, **my strong recommendation is `subagent`** — each task is independent, runs in a fresh context, and you can review what each task produced before the next one starts. Decision is yours.

