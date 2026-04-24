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

---

## Task 4: Airtable `updateRecord()` — PATCH record with results

**Files:**
- Modify: `agents/creativo_v2/src/airtable.mjs` (append `updateRecord`)
- Modify: `agents/creativo_v2/test/airtable.test.mjs` (append tests)

- [ ] **Step 1: Append failing tests**

Append to `agents/creativo_v2/test/airtable.test.mjs`:

```javascript
import { updateRecord } from '../src/airtable.mjs';

test('updateRecord sends PATCH to correct URL with fields body', async () => {
  let method, url, body;
  __setFetch(async (u, opts) => {
    url = u; method = opts.method; body = opts.body;
    return { ok: true, status: 200, json: async () => ({ id: 'recABC', fields: {} }) };
  });
  process.env.AIRTABLE_SM_TOKEN = 'tok_test';
  process.env.AIRTABLE_SM_BASE_ID = 'appTEST';
  process.env.AIRTABLE_SM_TABLE_ID = 'tblTEST';

  await updateRecord('recABC', { visual_url: 'https://cdn/a.jpg', Status: 'Lista para Publicar' });

  assert.equal(method, 'PATCH');
  assert.ok(url.endsWith('/appTEST/tblTEST/recABC'));
  const payload = JSON.parse(body);
  assert.equal(payload.fields.visual_url, 'https://cdn/a.jpg');
  assert.equal(payload.fields.Status, 'Lista para Publicar');
});

test('updateRecord throws on non-2xx with body snippet', async () => {
  __setFetch(async () => ({ ok: false, status: 422, text: async () => 'Invalid field name' }));
  await assert.rejects(updateRecord('recX', { foo: 'bar' }), /422/);
});
```

- [ ] **Step 2: Run — expect 2 new fails**

```bash
cd agents/creativo_v2 && node --test test/airtable.test.mjs 2>&1 | tail -5
```

Expected: `pass 10`, `fail 2`.

- [ ] **Step 3: Implement updateRecord (append to airtable.mjs)**

Append to `agents/creativo_v2/src/airtable.mjs`:

```javascript
export async function updateRecord(recordId, fields) {
  if (!recordId) throw new Error('updateRecord: recordId required');
  const url = `${baseUrl()}/${recordId}`;
  const res = await _fetch(url, {
    method: 'PATCH',
    headers: authHeaders(),
    body: JSON.stringify({ fields }),
  });
  if (!res.ok) {
    const body = typeof res.text === 'function' ? await res.text() : '';
    throw new Error(`Airtable update failed: ${res.status} ${body.slice(0, 200)}`);
  }
  return await res.json();
}
```

- [ ] **Step 4: Run — all 12 pass**

```bash
cd agents/creativo_v2 && node --test test/airtable.test.mjs 2>&1 | tail -5
```

Expected: `pass 12`, `fail 0`.

- [ ] **Step 5: Commit**

```bash
git add agents/creativo_v2/src/airtable.mjs agents/creativo_v2/test/airtable.test.mjs
git -c commit.gpgsign=false commit -m "creativo_v2: airtable updateRecord (PATCH) + tests"
```

---

## Task 5: Cloudinary `uploadJpg()` + `uploadCarousel()` — signed upload

**Files:**
- Create: `agents/creativo_v2/src/cloudinary.mjs`
- Create: `agents/creativo_v2/test/cloudinary.test.mjs`

**Background — Cloudinary signed upload:**
- POST multipart to `https://api.cloudinary.com/v1_1/{cloud_name}/image/upload`
- Signature = `sha1(paramString + api_secret)` where `paramString` is `&`-joined sorted `key=value` pairs of all non-file params EXCEPT `api_key`, `file`, `cloud_name`, `resource_type`, `signature`
- Standard params: `timestamp`, `public_id`, `folder`, `overwrite`

- [ ] **Step 1: Write failing tests**

Write `agents/creativo_v2/test/cloudinary.test.mjs`:

```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildSignature, uploadJpg, __setFetch } from '../src/cloudinary.mjs';

test('buildSignature sorts params alphabetically and SHA1s with secret', () => {
  const sig = buildSignature({ timestamp: 1700000000, public_id: 'foo/bar', overwrite: 'true' }, 'SECRET');
  // sha1 of "overwrite=true&public_id=foo/bar&timestamp=1700000000SECRET"
  assert.equal(sig, '3cbc2194ff47efffae56eb4989f5f3a9f53795e4');
});

test('uploadJpg posts multipart with correct fields and returns secure_url', async () => {
  let capturedUrl, capturedMethod;
  const captured = { fields: {} };
  __setFetch(async (url, opts) => {
    capturedUrl = url; capturedMethod = opts.method;
    // parse multipart body is complex; for test just confirm FormData used + headers
    assert.ok(opts.body, 'body must be set');
    return {
      ok: true, status: 200,
      json: async () => ({ secure_url: 'https://res.cloudinary.com/dzzlhhk0m/image/upload/v1/foo.jpg', public_id: 'foo', bytes: 12345 }),
    };
  });
  process.env.CLOUDINARY_NAME = 'dzzlhhk0m';
  process.env.CLOUDINARY_API_KEY = 'keytest';
  process.env.CLOUDINARY_API_SECRET = 'secrettest';

  // write a dummy file for the upload
  const fs = await import('node:fs/promises');
  const os = await import('node:os');
  const path = await import('node:path');
  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), 'clou-'));
  const p = path.join(tmp, 't.jpg');
  await fs.writeFile(p, Buffer.from([0xff, 0xd8, 0xff, 0xd9]));  // minimal JPG

  const url = await uploadJpg(p, { publicId: 'pinnacle/test_slide_1', folder: 'pinnacle-social-media' });

  assert.equal(capturedMethod, 'POST');
  assert.ok(capturedUrl.includes('dzzlhhk0m'));
  assert.ok(url.startsWith('https://res.cloudinary.com/'));
  await fs.rm(tmp, { recursive: true });
});

test('uploadJpg throws on non-2xx with status in error', async () => {
  __setFetch(async () => ({ ok: false, status: 401, text: async () => '{"error":"invalid sig"}' }));
  const fs = await import('node:fs/promises');
  const os = await import('node:os');
  const path = await import('node:path');
  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), 'clou-'));
  const p = path.join(tmp, 't.jpg');
  await fs.writeFile(p, Buffer.from([0xff, 0xd8]));
  await assert.rejects(uploadJpg(p, { publicId: 'x', folder: 'y' }), /401/);
  await fs.rm(tmp, { recursive: true });
});
```

- [ ] **Step 2: Run — expect 3 fails (module missing)**

```bash
cd agents/creativo_v2 && node --test test/cloudinary.test.mjs 2>&1 | tail -5
```

- [ ] **Step 3: Implement cloudinary.mjs**

Write `agents/creativo_v2/src/cloudinary.mjs`:

```javascript
// Cloudinary signed upload via native fetch + FormData.
// Secrets from env (via doppler run): CLOUDINARY_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET.

import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }

function env(name) {
  const v = process.env[name];
  if (!v) throw new Error(`Missing env ${name} (expected via doppler run)`);
  return v;
}

export function buildSignature(params, apiSecret) {
  const keys = Object.keys(params).sort();
  const str = keys.map(k => `${k}=${params[k]}`).join('&') + apiSecret;
  return createHash('sha1').update(str).digest('hex');
}

export async function uploadJpg(localPath, { publicId, folder, overwrite = true }) {
  if (!publicId) throw new Error('uploadJpg: publicId required');
  const cloudName = env('CLOUDINARY_NAME');
  const apiKey = env('CLOUDINARY_API_KEY');
  const apiSecret = env('CLOUDINARY_API_SECRET');
  const timestamp = Math.floor(Date.now() / 1000);

  const signedParams = { folder, overwrite: String(overwrite), public_id: publicId, timestamp };
  const signature = buildSignature(signedParams, apiSecret);

  const buf = await readFile(localPath);
  const blob = new Blob([buf], { type: 'image/jpeg' });
  const form = new FormData();
  form.append('file', blob, path.basename(localPath));
  form.append('api_key', apiKey);
  form.append('timestamp', String(timestamp));
  form.append('public_id', publicId);
  form.append('folder', folder);
  form.append('overwrite', String(overwrite));
  form.append('signature', signature);

  const url = `https://api.cloudinary.com/v1_1/${cloudName}/image/upload`;
  const res = await _fetch(url, { method: 'POST', body: form });
  if (!res.ok) {
    const body = typeof res.text === 'function' ? await res.text() : '';
    throw new Error(`Cloudinary upload failed: ${res.status} ${body.slice(0, 200)}`);
  }
  const data = await res.json();
  return data.secure_url;
}

export async function uploadCarousel(jpgPaths, { recordId, week = 0, folder = 'pinnacle-social-media' }) {
  const urls = [];
  for (let i = 0; i < jpgPaths.length; i++) {
    const slideIdx = i + 1;
    const publicId = `${folder}/pinnacle_s${week}_carousel_${recordId}/slide_${slideIdx}`;
    const url = await uploadJpg(jpgPaths[i], { publicId, folder });
    urls.push(url);
  }
  return urls;
}
```

- [ ] **Step 4: Run — 3 pass**

```bash
cd agents/creativo_v2 && node --test test/cloudinary.test.mjs 2>&1 | tail -5
```

Expected: `pass 3`, `fail 0`.

- [ ] **Step 5: Commit**

```bash
git add agents/creativo_v2/src/cloudinary.mjs agents/creativo_v2/test/cloudinary.test.mjs
git -c commit.gpgsign=false commit -m "creativo_v2: cloudinary signed upload + carousel helper"
```

---

## Task 6: `main.mjs` — production orchestrator

**Files:**
- Create: `agents/creativo_v2/main.mjs`
- Create: `agents/creativo_v2/test/main.test.mjs`
- Modify: `agents/creativo_v2/package.json` (add `"prod"` script)

- [ ] **Step 1: Write failing test for processRecord helper**

Write `agents/creativo_v2/test/main.test.mjs`:

```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { resolveWeek, safeSummary } from '../main.mjs';

test('resolveWeek uses fields.Semana if present as number', () => {
  assert.equal(resolveWeek({ fields: { Semana: 3 } }), 3);
});

test('resolveWeek parses string week', () => {
  assert.equal(resolveWeek({ fields: { Semana: '4' } }), 4);
});

test('resolveWeek defaults to 0 when missing', () => {
  assert.equal(resolveWeek({ fields: {} }), 0);
});

test('safeSummary truncates long messages', () => {
  const s = safeSummary('a'.repeat(500));
  assert.ok(s.length <= 200);
});
```

- [ ] **Step 2: Run — fails (module missing)**

```bash
cd agents/creativo_v2 && node --test test/main.test.mjs 2>&1 | tail -5
```

- [ ] **Step 3: Implement main.mjs**

Write `agents/creativo_v2/main.mjs`:

```javascript
#!/usr/bin/env node
// Production orchestrator for El Creativo v2.
// Reads pending Airtable records, renders carousels, uploads to Cloudinary, updates Airtable.
// Run via: doppler run -- node agents/creativo_v2/main.mjs [--dry-run]

import { mkdir, rm, stat } from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';
import { buildCarousel } from './src/themes.mjs';
import { wrapSlideHtml } from './src/wrapper.mjs';
import { renderJpg, closeBrowser } from './src/render.mjs';
import { listPending, parseVisualPrompt, updateRecord } from './src/airtable.mjs';
import { uploadCarousel } from './src/cloudinary.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

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
  const dir = await (async () => {
    const d = path.join(os.tmpdir(), `creativo_v2_${recordId}_${Date.now()}`);
    await mkdir(d, { recursive: true });
    return d;
  })();
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
      await updateRecord(id, { Status: 'Error', Error_Reason: safeSummary(e.message) });
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
      await updateRecord(id, { Status: 'Error', Error_Reason: safeSummary('render: ' + e.message) });
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
    await updateRecord(id, { Status: 'Error Upload', Error_Reason: safeSummary(e.message) });
    return { id, ok: false, reason: 'upload' };
  }

  try {
    await updateRecord(id, { visual_url: urls[0], Status: 'Lista para Publicar' });
    log('Airtable updated Status=Lista para Publicar');
  } catch (e) {
    log(`airtable update failed: ${e.message}`);
    return { id, ok: false, reason: 'patch' };
  }

  // cleanup tmp
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
```

- [ ] **Step 4: Run — 4 tests pass**

```bash
cd agents/creativo_v2 && node --test test/main.test.mjs 2>&1 | tail -5
```

Expected: `pass 4`, `fail 0`.

- [ ] **Step 5: Update package.json with prod script**

Edit `agents/creativo_v2/package.json` — add to `scripts`:

```json
"prod": "doppler run --project pinnacle-social-publisher --config dev_personal -- node main.mjs",
"prod:dry": "doppler run --project pinnacle-social-publisher --config dev_personal -- node main.mjs --dry-run"
```

- [ ] **Step 6: Commit**

```bash
git add agents/creativo_v2/main.mjs agents/creativo_v2/test/main.test.mjs agents/creativo_v2/package.json
git -c commit.gpgsign=false commit -m "creativo_v2: main.mjs production orchestrator + dry-run"
```

