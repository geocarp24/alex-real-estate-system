# El Creativo v2 — Producción (Fase 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the POC orchestrator with a production flow that reads Airtable pending records, renders carousels, uploads to Cloudinary, and updates Airtable with the visual URLs — so El Creativo v2 runs autonomously end-to-end.

**Architecture:** Three new Node modules (`airtable.mjs`, `cloudinary.mjs`, `main.mjs`) bolt onto the existing Fase 1 renderer (`themes.mjs`, `wrapper.mjs`, `render.mjs`). Secrets via Doppler — migrate Airtable token + base/table IDs during Task 1. All I/O via native `fetch` + `node:crypto` — no new npm deps.

**Tech Stack:** Node 22, Puppeteer (already installed), native `fetch`, `node:crypto` for Cloudinary signatures, Doppler CLI for secrets injection, Node built-in `node --test` for TDD.

**Spec:** `docs/superpowers/specs/2026-04-24-creativo-v2-design.md` (sections 3, 4, 7, 8)
**Branch:** `claude/greeting-setup-yOfqf`
**Phase discipline:** This plan follows `agents/PROTOCOLO_EJECUCION.md` — each task is a single commit with tests before implementation.

---

## File Structure

**Created by this plan:**
```
agents/creativo_v2/
  src/
    airtable.mjs               Airtable REST client (list + parse + update)
    cloudinary.mjs             Cloudinary signed upload
  test/
    airtable.test.mjs          TDD tests for airtable module
    cloudinary.test.mjs        TDD tests for cloudinary module
    main.test.mjs              TDD tests for orchestrator helpers
  main.mjs                     production orchestrator (replaces poc.mjs at runtime)
  logs/                        JSONL run logs (.gitignored already)
```

**Modified by this plan:**
```
agents/creativo_v2/.gitignore  add output/ (stop tracking POC JPGs)
agents/creativo_v2/package.json add "prod" script for main.mjs via doppler run
agents/creativo_v2/README.md   document production run flow
```

**Not touched:**
- `src/themes.mjs`, `src/wrapper.mjs`, `src/render.mjs` (Fase 1 — stable)
- `poc.mjs` (kept for regression testing — NOT deleted)

---

## Schema Contract — Visual_Prompt field in Airtable

Social Media Agent writes **valid JSON** to the Visual_Prompt field matching this schema:

```json
{
  "theme": "T1",
  "hook": { "en": "...", "es": "...", "badge": "WISCONSIN" },
  "points": [
    { "headingEn": "...", "headingEs": "...", "bodyEn": "...", "bodyEs": "..." }
  ],
  "cta": { "en": "...", "es": "..." }
}
```

`parseVisualPrompt` tolerates markdown code-fence wrapping (```json ... ```). If parse fails, the record is marked `Status=Error` with `Error_Reason` — it does not block the batch.

**Migration note:** existing pending records (7 in `tblAj0Pkj1jW4p5Ld` per `memoria_social_media.md`) have Visual_Prompt in the old descriptive format. They must be either (a) updated to JSON format by the Social Media Agent, or (b) left alone — they will fail parsing and be flagged `Status=Error`. Not our job in this plan; Task 8 tests with one NEW record written in the correct format.

---

## Execution Discipline (Fase 4 of PROTOCOLO_EJECUCION)

- One task = one module = one commit
- TDD: test fails → implement → test passes → commit
- Never `Write` files larger than ~300 lines — split modules
- Never paste >400 lines in chat output — write to disk and report path only
- After each task: quick status report to Jefe
- After Task 8: ask for final approval before deploying to cron

---

## Task 1: Migrate Airtable secrets to Doppler

**Files:**
- Modify (manual, via Doppler dashboard): add 3 secrets to `pinnacle-social-publisher` / `dev_personal`

- [ ] **Step 1: Jefe adds 3 secrets to Doppler dashboard**

In `dashboard.doppler.com` → project `pinnacle-social-publisher` → config `dev_personal` → Add Secret (x3):

```
AIRTABLE_SM_TOKEN     = patSlNwngu7SJoa52.003c83df8f6e378af5309237e310a36568a037448709d94b10739d032f9e8ef7
AIRTABLE_SM_BASE_ID   = appU9s3kGkVpdrJkw
AIRTABLE_SM_TABLE_ID  = tblAj0Pkj1jW4p5Ld
```

(Values come from existing `agents/social_media.md`. After migration, remove them from that file.)

- [ ] **Step 2: Verify Doppler sees all 8 secrets**

```bash
doppler secrets --only-names --no-check-version 2>&1 | grep -E "^\s*(CLOUDINARY|GEMINI|REPLICATE|AIRTABLE)"
```

Expected: 8 rows listing CLOUDINARY_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET, GEMINI_API_KEY, REPLICATE_API_TOKEN, AIRTABLE_SM_TOKEN, AIRTABLE_SM_BASE_ID, AIRTABLE_SM_TABLE_ID.

- [ ] **Step 3: Remove plaintext secret from social_media.md**

Edit `agents/social_media.md` section "CREDENCIALES AIRTABLE" — replace the literal token with:

```
## CREDENCIALES AIRTABLE

Secrets en Doppler project `pinnacle-social-publisher` / config `dev_personal`:
- AIRTABLE_SM_TOKEN
- AIRTABLE_SM_BASE_ID  (appU9s3kGkVpdrJkw)
- AIRTABLE_SM_TABLE_ID (tblAj0Pkj1jW4p5Ld for Ideas de Contenido)

Ejecutar con: `doppler run -- node agents/creativo_v2/main.mjs`
```

- [ ] **Step 4: Commit**

```bash
git add agents/social_media.md
git -c commit.gpgsign=false commit -m "social_media: move Airtable token to Doppler"
```

---


---

## Task 2: Airtable `listPending()` — read records ready for rendering

**Files:**
- Create: `agents/creativo_v2/src/airtable.mjs`
- Create: `agents/creativo_v2/test/airtable.test.mjs`

- [ ] **Step 1: Write failing test**

Write `agents/creativo_v2/test/airtable.test.mjs`:

```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { listPending, __setFetch } from '../src/airtable.mjs';

test('listPending builds correct URL with filterByFormula', async () => {
  let capturedUrl = '';
  let capturedHeaders = {};
  __setFetch(async (url, opts) => {
    capturedUrl = url;
    capturedHeaders = opts.headers;
    return { ok: true, status: 200, json: async () => ({ records: [] }) };
  });

  process.env.AIRTABLE_SM_TOKEN = 'tok_test';
  process.env.AIRTABLE_SM_BASE_ID = 'appTEST';
  process.env.AIRTABLE_SM_TABLE_ID = 'tblTEST';

  await listPending();

  assert.ok(capturedUrl.startsWith('https://api.airtable.com/v0/appTEST/tblTEST'));
  assert.ok(capturedUrl.includes('filterByFormula='));
  assert.ok(decodeURIComponent(capturedUrl).includes("{Status}='Nueva'"));
  assert.ok(decodeURIComponent(capturedUrl).includes("{Visual_Prompt}"));
  assert.equal(capturedHeaders.Authorization, 'Bearer tok_test');
});

test('listPending returns parsed records array', async () => {
  __setFetch(async () => ({
    ok: true,
    status: 200,
    json: async () => ({
      records: [
        { id: 'rec1', fields: { 'Visual_Prompt': '{}', 'Status': 'Nueva' } },
        { id: 'rec2', fields: { 'Visual_Prompt': '{}', 'Status': 'Nueva' } },
      ],
    }),
  }));
  const recs = await listPending();
  assert.equal(recs.length, 2);
  assert.equal(recs[0].id, 'rec1');
});

test('listPending throws on non-200', async () => {
  __setFetch(async () => ({ ok: false, status: 401, text: async () => 'Unauthorized' }));
  await assert.rejects(listPending(), /401/);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd agents/creativo_v2 && node --test test/airtable.test.mjs 2>&1 | tail -5
```

Expected: FAIL with `Cannot find module '../src/airtable.mjs'`.

- [ ] **Step 3: Implement airtable.mjs (listPending only for now)**

Write `agents/creativo_v2/src/airtable.mjs`:

```javascript
// Airtable REST client for creativo_v2. No SDK — native fetch.
// Secrets from env (inject via Doppler): AIRTABLE_SM_TOKEN, AIRTABLE_SM_BASE_ID, AIRTABLE_SM_TABLE_ID.

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }   // test seam

const PENDING_FILTER =
  "AND({Status}='Nueva',{Visual_Prompt}!='',{visual_url}='')";

function env(name) {
  const v = process.env[name];
  if (!v) throw new Error(`Missing env ${name} (expected via doppler run)`);
  return v;
}

function baseUrl() {
  return `https://api.airtable.com/v0/${env('AIRTABLE_SM_BASE_ID')}/${env('AIRTABLE_SM_TABLE_ID')}`;
}

function authHeaders() {
  return { Authorization: `Bearer ${env('AIRTABLE_SM_TOKEN')}`, 'Content-Type': 'application/json' };
}

export async function listPending() {
  const url = `${baseUrl()}?filterByFormula=${encodeURIComponent(PENDING_FILTER)}&pageSize=50`;
  const res = await _fetch(url, { headers: authHeaders() });
  if (!res.ok) {
    const body = typeof res.text === 'function' ? await res.text() : '';
    throw new Error(`Airtable list failed: ${res.status} ${body}`);
  }
  const data = await res.json();
  return data.records || [];
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd agents/creativo_v2 && node --test test/airtable.test.mjs 2>&1 | tail -8
```

Expected: `pass 3`, `fail 0`.

- [ ] **Step 5: Commit**

```bash
git add agents/creativo_v2/src/airtable.mjs agents/creativo_v2/test/airtable.test.mjs
git -c commit.gpgsign=false commit -m "creativo_v2: airtable listPending + tests"
```

---

## Task 3: Airtable `parseVisualPrompt()` — convert field text → spec JSON

**Files:**
- Modify: `agents/creativo_v2/src/airtable.mjs` (append `parseVisualPrompt`)
- Modify: `agents/creativo_v2/test/airtable.test.mjs` (append tests)

- [ ] **Step 1: Write failing tests (append to existing test file)**

Append to `agents/creativo_v2/test/airtable.test.mjs`:

```javascript
import { parseVisualPrompt } from '../src/airtable.mjs';

test('parseVisualPrompt accepts raw JSON', () => {
  const raw = '{"theme":"T1","hook":{"en":"H","es":"h"},"points":[{"headingEn":"a","headingEs":"b","bodyEn":"c","bodyEs":"d"}],"cta":{"en":"X","es":"x"}}';
  const spec = parseVisualPrompt(raw);
  assert.equal(spec.theme, 'T1');
  assert.equal(spec.hook.en, 'H');
  assert.equal(spec.points.length, 1);
});

test('parseVisualPrompt strips ```json fences', () => {
  const raw = '```json\n{"theme":"T2","hook":{"en":"a","es":"b"},"points":[],"cta":{"en":"c","es":"d"}}\n```';
  const spec = parseVisualPrompt(raw);
  assert.equal(spec.theme, 'T2');
});

test('parseVisualPrompt strips plain ``` fences', () => {
  const raw = '```\n{"theme":"T3","hook":{"en":"a","es":"b"},"points":[],"cta":{"en":"c","es":"d"}}\n```';
  const spec = parseVisualPrompt(raw);
  assert.equal(spec.theme, 'T3');
});

test('parseVisualPrompt throws on invalid JSON with clear message', () => {
  assert.throws(() => parseVisualPrompt('not json at all'), /invalid JSON/i);
});

test('parseVisualPrompt throws on missing theme', () => {
  assert.throws(() => parseVisualPrompt('{"hook":{}}'), /theme/i);
});

test('parseVisualPrompt throws on invalid theme code', () => {
  assert.throws(() => parseVisualPrompt('{"theme":"T99","hook":{"en":"a","es":"b"},"points":[],"cta":{"en":"c","es":"d"}}'), /T99|theme/i);
});

test('parseVisualPrompt throws on missing hook.en', () => {
  assert.throws(() => parseVisualPrompt('{"theme":"T1","hook":{},"points":[],"cta":{"en":"c","es":"d"}}'), /hook\.en/i);
});
```

- [ ] **Step 2: Run — expect 7 new tests failing**

```bash
cd agents/creativo_v2 && node --test test/airtable.test.mjs 2>&1 | tail -8
```

Expected: `pass 3`, `fail 7` — the parseVisualPrompt tests fail because function doesn't exist.

- [ ] **Step 3: Implement parseVisualPrompt (append to airtable.mjs)**

Append to `agents/creativo_v2/src/airtable.mjs`:

```javascript
const VALID_THEMES = new Set(['T1', 'T2', 'T3', 'T4', 'T5']);

function stripCodeFences(s) {
  const trimmed = s.trim();
  if (trimmed.startsWith('```')) {
    return trimmed.replace(/^```(?:json)?\s*\n?/, '').replace(/\n?```\s*$/, '').trim();
  }
  return trimmed;
}

export function parseVisualPrompt(raw) {
  if (typeof raw !== 'string' || !raw.trim()) {
    throw new Error('parseVisualPrompt: empty input');
  }
  const clean = stripCodeFences(raw);
  let spec;
  try {
    spec = JSON.parse(clean);
  } catch (e) {
    throw new Error(`parseVisualPrompt: invalid JSON — ${e.message}`);
  }
  if (!spec || typeof spec !== 'object') {
    throw new Error('parseVisualPrompt: spec is not an object');
  }
  if (!spec.theme || !VALID_THEMES.has(spec.theme)) {
    throw new Error(`parseVisualPrompt: invalid theme "${spec.theme}" (expected T1-T5)`);
  }
  if (!spec.hook || typeof spec.hook.en !== 'string' || !spec.hook.en.trim()) {
    throw new Error('parseVisualPrompt: missing hook.en');
  }
  if (!spec.hook.es || typeof spec.hook.es !== 'string') {
    throw new Error('parseVisualPrompt: missing hook.es');
  }
  if (!Array.isArray(spec.points)) spec.points = [];
  if (!spec.cta) spec.cta = {};
  return spec;
}
```

- [ ] **Step 4: Run — all 10 tests must pass**

```bash
cd agents/creativo_v2 && node --test test/airtable.test.mjs 2>&1 | tail -8
```

Expected: `pass 10`, `fail 0`.

- [ ] **Step 5: Commit**

```bash
git add agents/creativo_v2/src/airtable.mjs agents/creativo_v2/test/airtable.test.mjs
git -c commit.gpgsign=false commit -m "creativo_v2: airtable parseVisualPrompt (JSON + fence stripping)"
```

