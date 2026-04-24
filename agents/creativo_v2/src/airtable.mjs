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
