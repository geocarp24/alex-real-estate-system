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
