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

**Goal:** Before any Director v2 code runs, ensure the Airtable base has the 2 new fields and the `reel` option. Script is idempotent — can be re-run safely.

**Files:**
- Create: `agents/director_v2/scripts/airtable_schema_setup.mjs`
- Create: `agents/director_v2/test/schema_setup.test.mjs`

**Dependencies:** `AIRTABLE_SM_SCHEMA_TOKEN` in Doppler (see Prerequisites), `AIRTABLE_SM_BASE_ID`, `AIRTABLE_SM_TABLE_ID`.

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
          { id: 'tblAj0Pkj1jW4p5Ld', name: 'SocialMedia', fields: [
            { id: 'fldMT', name: 'Media_Type', type: 'singleSelect', options: { choices: [{ name: 'carousel' }] } },
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

test('diffSchema returns list of pending changes when fields missing', () => {
  const table = {
    fields: [
      { name: 'Media_Type', type: 'singleSelect', options: { choices: [{ name: 'carousel' }] } },
      { name: 'visual_url', type: 'url' },
    ]
  };
  const changes = diffSchema(table);
  assert.equal(changes.length, 3);
  assert.ok(changes.find(c => c.action === 'add_option' && c.option === 'reel'));
  assert.ok(changes.find(c => c.action === 'add_field' && c.name === 'video_duration'));
  assert.ok(changes.find(c => c.action === 'add_field' && c.name === 'video_cost_cents'));
});

test('diffSchema returns empty when all 3 changes already applied', () => {
  const table = {
    fields: [
      { name: 'Media_Type', type: 'singleSelect', options: { choices: [{ name: 'carousel' }, { name: 'reel' }] } },
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
// Adds: Media_Type option 'reel', fields 'video_duration' (number), 'video_cost_cents' (number).
// Requires AIRTABLE_SM_SCHEMA_TOKEN (scope: schema.bases:write) — delete after run.

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
  const mediaType = table.fields.find(f => f.name === 'Media_Type');
  if (mediaType && !mediaType.options?.choices?.some(c => c.name === 'reel')) {
    changes.push({ action: 'add_option', fieldName: 'Media_Type', fieldId: mediaType.id, option: 'reel' });
  }
  if (!table.fields.some(f => f.name === 'video_duration')) {
    changes.push({ action: 'add_field', name: 'video_duration', type: 'number', options: { precision: 1 } });
  }
  if (!table.fields.some(f => f.name === 'video_cost_cents')) {
    changes.push({ action: 'add_field', name: 'video_cost_cents', type: 'number', options: { precision: 0 } });
  }
  return changes;
}

async function applyChange(baseId, tableId, change, token, currentChoices) {
  const headers = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' };
  if (change.action === 'add_option') {
    const newChoices = [...currentChoices, { name: change.option }];
    const res = await _fetch(`${BASE}/meta/bases/${baseId}/tables/${tableId}/fields/${change.fieldId}`, {
      method: 'PATCH',
      headers,
      body: JSON.stringify({ options: { choices: newChoices } }),
    });
    if (!res.ok) throw new Error(`add_option failed: ${res.status} ${await res.text()}`);
  } else if (change.action === 'add_field') {
    const res = await _fetch(`${BASE}/meta/bases/${baseId}/tables/${tableId}/fields`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ name: change.name, type: change.type, options: change.options }),
    });
    if (!res.ok) throw new Error(`add_field ${change.name} failed: ${res.status} ${await res.text()}`);
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
  changes.forEach((c, i) => console.log(`  ${i+1}. ${c.action} ${c.name || c.option}`));

  if (dryRun) { console.log('--dry-run — not applying.'); return; }

  const mediaType = table.fields.find(f => f.name === 'Media_Type');
  for (const change of changes) {
    console.log(`Applying: ${change.action} ${change.name || change.option} ...`);
    await applyChange(baseId, tableId, change, token, mediaType?.options?.choices || []);
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
Expected output: lists the 3 pending changes (or says "no changes needed" if already applied).

- [ ] **Step 7: Apply schema changes**

```bash
cd agents/director_v2 && doppler run -- node scripts/airtable_schema_setup.mjs
```
Expected: 3 × "OK" lines + "Done. Delete AIRTABLE_SM_SCHEMA_TOKEN..."

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
git commit -m "feat(director_v2): Task 0 — idempotent Airtable schema setup for reel media type"
```

**Acceptance criteria:**
- `node --test test/schema_setup.test.mjs` passes 3 tests
- Airtable base `appU9s3kGkVpdrJkw` has: `Media_Type` option `reel`, field `video_duration` (number, precision 1), field `video_cost_cents` (number, precision 0)
- `AIRTABLE_SM_SCHEMA_TOKEN` deleted from Doppler

---

<!-- PLAN_PART_2_END -->
