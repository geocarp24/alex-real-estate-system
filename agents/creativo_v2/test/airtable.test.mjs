import { test } from 'node:test';
import assert from 'node:assert/strict';
import { listPending, parseVisualPrompt, updateRecord, __setFetch } from '../src/airtable.mjs';

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
