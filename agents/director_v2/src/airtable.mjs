import { withRetry } from './util/retry.mjs';

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }

const BASE = 'https://api.airtable.com/v0';
// New Reels schema (Jorge 2026-05-07): Reels live in their own table with
// Slide_N fields. Director v2 only renders records that El Oráculo approved
// (Status='Oraculo OK') and that don't yet have a visual.
const PENDING_FILTER = "AND({Status}='Oraculo OK', OR({visual_url}='', NOT({visual_url})))";

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

export async function fetchOne(recordId, { token, baseId, tableId, baseDelayMs = 1000 }) {
  const url = `${BASE}/${baseId}/${tableId}/${recordId}`;
  const record = await withRetry(
    async () => {
      const res = await _fetch(url, { headers: { Authorization: `Bearer ${token}` } });
      if (!res.ok) throw new Error(`Airtable HTTP ${res.status} fetching ${recordId}`);
      return res.json();
    },
    { attempts: 3, baseDelayMs }
  );
  return record;
}

export function parseVisualPrompt(raw) {
  if (!raw) throw new Error('parseVisualPrompt: empty input');
  let text = String(raw).trim();
  // Strip [ORACULO_OK score=N src=...] prefix line if present (Jorge 2026-05-07 gate).
  text = text.replace(/^\[ORACULO_OK[^\]]*\]\s*\n?/, '').trim();
  const fence = text.match(/^```(?:json)?\s*([\s\S]*?)\s*```$/);
  if (fence) text = fence[1].trim();
  try { return JSON.parse(text); }
  catch (err) { throw new Error(`parseVisualPrompt: JSON parse failed: ${err.message}`); }
}

// ─── New Reels schema → narrative B spec builder (Jorge 2026-05-07) ───
// Builds the internal spec object from explicit Slide_N_Hook / Slide_N_Text /
// Slide_N_Visual / Slide_5_CTA fields. No more JSON parsing of Visual_Prompt
// for new-schema Reels — everything is structured columns in Airtable.
//
// Slide_N_Visual format: "<pexels_query> | flux: <flux_prompt>" — split here.
function parseSlideVisual(raw) {
  const s = String(raw || '').trim();
  if (!s) return { heroQuery: 'wisconsin home golden hour', heroPrompt: 'cinematic photo of a Wisconsin suburban home, golden hour, no text' };
  const parts = s.split('| flux:');
  const heroQuery  = parts[0].trim() || 'wisconsin home golden hour';
  const heroPrompt = (parts[1] || '').trim() || `cinematic photo, ${heroQuery}, warm wisconsin home, no text, no logos`;
  return { heroQuery, heroPrompt };
}

export function buildSpecFromReelRecord(record) {
  const f = record.fields || {};
  const lang = String(f.Language || 'ES').toUpperCase();
  const localeKey = lang === 'EN' ? 'en' : 'es';

  // Slide_N_Text/Hook/CTA contain the SAME content for both en and es internally
  // (since each record is single-language). Director v2's narrative B uses
  // hook.{en,es} and points[].caption{En,Es} based on locale, so we mirror
  // the record's text into both keys.
  const hookText = f.Slide_1_Hook || f.Title || '';
  const ctaText  = f.Slide_5_CTA  || '';
  const slide2 = parseSlideVisual(f.Slide_2_Visual);
  const slide3 = parseSlideVisual(f.Slide_3_Visual);
  const slide4 = parseSlideVisual(f.Slide_4_Visual);

  return {
    narrative: 'B',
    theme:     f.Theme_Code || 'T1',
    template:  f.Template   || 'voiceover',
    aspect:    '9:16',
    duration:  10,                        // 5 slides x 2s = 10s (Jorge rule)
    locale:    localeKey,
    hook: {
      [localeKey]: hookText,
      [localeKey === 'es' ? 'en' : 'es']: hookText,    // mirror so Director v2 has both keys defined
    },
    points: [
      { captionEs: f.Slide_2_Text || '', captionEn: f.Slide_2_Text || '', heroQuery: slide2.heroQuery, heroPrompt: slide2.heroPrompt },
      { captionEs: f.Slide_3_Text || '', captionEn: f.Slide_3_Text || '', heroQuery: slide3.heroQuery, heroPrompt: slide3.heroPrompt },
      { captionEs: f.Slide_4_Text || '', captionEn: f.Slide_4_Text || '', heroQuery: slide4.heroQuery, heroPrompt: slide4.heroPrompt },
    ],
    cta: {
      [localeKey]: ctaText,
      [localeKey === 'es' ? 'en' : 'es']: ctaText,
    },
    music_track:  f.Music_Track || 'cinematic',
    avatar_mode:  f.Avatar_Mode || 'NO_avatar',
    avatar_script: f.Avatar_Script || '',
  };
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
