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
