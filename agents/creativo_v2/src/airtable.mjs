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
