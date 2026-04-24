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
