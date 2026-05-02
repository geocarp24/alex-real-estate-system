import { withRetry } from './util/retry.mjs';

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }

const BASE = 'https://api.airtable.com/v0';
const PENDING_FILTER = "AND({Formato}='Reel',{Status}='Nueva',{Visual_Prompt}!='',{visual_url}='',{Error_Reason}='')";

export async function listPending({ token, baseId, tableId, baseDelayMs = 1000 }) {
  const url = `${BASE}/${baseId}/${tableId}?filterByFormula=${encodeURIComponent(PENDING_FILTER)}&pageSize=10`;
  const data = await withRetry(
    async () => {
      const res = await _fetch(url, { headers: { Authorization: `Bearer ${token}` } });
      if (!res.ok) throw new Error(`Airtable HTTP ${res.status}`);
      return res.json();
    },
    { attempts: 3, baseDelayMs }
  );
  return data.records || [];
}

export function parseVisualPrompt(raw) {
  if (!raw) throw new Error('parseVisualPrompt: empty input');
  let text = String(raw).trim();
  const fence = text.match(/^```(?:json)?\s*([\s\S]*?)\s*```$/);
  if (fence) text = fence[1].trim();
  try { return JSON.parse(text); }
  catch (err) { throw new Error(`parseVisualPrompt: JSON parse failed: ${err.message}`); }
}

export async function updateRecord(recordId, fields, { token, baseId, tableId, baseDelayMs = 1000 }) {
  const url = `${BASE}/${baseId}/${tableId}/${recordId}`;
  return withRetry(
    async () => {
      const res = await _fetch(url, {
        method: 'PATCH',
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ fields }),
      });
      if (!res.ok) throw new Error(`Airtable PATCH ${res.status}: ${await res.text()}`);
      return res.json();
    },
    { attempts: 3, baseDelayMs }
  );
}
