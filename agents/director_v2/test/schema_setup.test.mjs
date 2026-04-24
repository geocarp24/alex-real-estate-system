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
