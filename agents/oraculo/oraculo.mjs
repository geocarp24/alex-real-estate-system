#!/usr/bin/env node
/**
 * El Oráculo — Quality gate between Social Media Manager and El Creativo.
 *
 * Mission (Jorge 2026-05-07): "el Oráculo debe ir de la mano con el Social
 * Media Manager primero, y después que el Oráculo avala que el Social Media
 * hizo un buen trabajo a través del research especializado, entonces se
 * proceden a realizar los creativos. Esto se transforma en $0 desperdicios."
 *
 * Pipeline:
 *   SM Manager → record (Status=Nueva, Visual_Prompt set)
 *     ↓
 *   El Oráculo → reviews each record against persona + brand voice
 *     ↓ approved: prepends "[ORACULO_OK score=N]\n" to Visual_Prompt
 *     ↓ rejected: writes notes to Error_Reason field, leaves Visual_Prompt unchanged
 *   El Creativo → filters for "[ORACULO_OK]" prefix in Visual_Prompt
 *
 * Why prefix instead of Status field: Airtable Meta API does not support
 * adding new singleSelect options programmatically (only via UI). Prefix
 * approach works with existing schema, no manual field config needed.
 *
 * Inputs (read once per run):
 *   - agents/oraculo_inputs/wi_homeowner_persona.md
 *   - agents/oraculo_inputs/popup_copy.md
 *
 * Sonnet 4.6 evaluates:
 *   1. Persona fit — does the idea speak to a Wisconsin distressed homeowner?
 *   2. Brand voice — warm, no pressure, bilingual, no investor jargon?
 *   3. Compliance — no FTC/HUD violations? no homosexuality promotion?
 *   4. Hook quality — does it open curiosity?
 *   5. CTA presence — phone (920) 777-9886 + website?
 *   6. Visual_Prompt clarity — actionable for Creativo?
 *
 * Verdict: score 1-10. Approve threshold = 7.
 */

import { parseArgs, loadTenant, telegramSend, genRunId, isoNow } from "../_shared/runner.mjs";
import { readFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const ORACULO_DIR = join(__dirname, "..", "oraculo_inputs");

const VALID_MODES = ["batch", "one"];
const APPROVE_THRESHOLD = 7;

const SM_BASE  = process.env.AIRTABLE_SM_BASE_ID  || "appU9s3kGkVpdrJkw";
const SM_TABLE = process.env.AIRTABLE_SM_TABLE_ID || "tblAj0Pkj1jW4p5Ld";
const SM_TOKEN = process.env.AIRTABLE_SM_TOKEN    || "";

const ANTHROPIC_KEY = process.env.ANTHROPIC_API_KEY || "";
const SONNET_MODEL  = "claude-sonnet-4-6";

const BATCH_MAX_PER_RUN = Number(process.env.ORACULO_BATCH_MAX || 10);

// ─── Persona + brand context (cached after first read) ───
let _contextCache = null;
async function loadContext() {
  if (_contextCache) return _contextCache;
  const safeRead = async (path, max = 3000) => {
    try { return (await readFile(path, "utf8")).slice(0, max); }
    catch { return ""; }
  };
  const [persona, copy] = await Promise.all([
    safeRead(join(ORACULO_DIR, "wi_homeowner_persona.md"), 4000),
    safeRead(join(ORACULO_DIR, "popup_copy.md"), 2500),
  ]);
  _contextCache = { persona, copy };
  return _contextCache;
}

// ─── Airtable SM helpers ───
async function smFetch(params = "") {
  const r = await fetch(`https://api.airtable.com/v0/${SM_BASE}/${SM_TABLE}?${params}`, {
    headers: { Authorization: `Bearer ${SM_TOKEN}` },
  });
  return r.json();
}
async function smUpdate(recordId, fields) {
  const r = await fetch(`https://api.airtable.com/v0/${SM_BASE}/${SM_TABLE}/${recordId}`, {
    method: "PATCH",
    headers: { Authorization: `Bearer ${SM_TOKEN}`, "Content-Type": "application/json" },
    body: JSON.stringify({ fields, typecast: true }),
  });
  return r.json();
}
async function smGet(recordId) {
  const r = await fetch(`https://api.airtable.com/v0/${SM_BASE}/${SM_TABLE}/${recordId}`, {
    headers: { Authorization: `Bearer ${SM_TOKEN}` },
  });
  return r.json();
}

// ─── Deterministic fallback (no Sonnet) ───
// Used when Anthropic API is unavailable (e.g. credit balance too low).
// Rule-based scoring against the same 6 criteria. Lower confidence than
// Sonnet but lets the pipeline keep moving without blocking on billing.
function reviewIdeaDeterministic(record) {
  const f = record.fields || {};
  const titulo     = (f["Título de Idea"] || "").toLowerCase();
  const hook       = (f.Hook || "").toLowerCase();
  const captionEs  = (f["🇲🇽 Caption ES"] || "").toLowerCase();
  const captionEn  = (f["🇺🇸 Caption EN"] || "").toLowerCase();
  const cta        = (f.CTA || "").toLowerCase();
  const tipo       = (f.Tipo || "").toLowerCase();
  const visualPrompt = (f.Visual_Prompt || "");
  const allText    = `${titulo} ${hook} ${captionEs} ${captionEn} ${cta} ${tipo}`;

  let score = 0;
  const notes = [];
  let compliance = "OK";

  // 1. Persona fit (0–3) — 6 distressed segments
  const segments = [
    { re: /foreclosure|embargo|atrasado|behind on/, label: "Pre-Foreclosure" },
    { re: /divorce|divorc|separation|separac/, label: "Divorce" },
    { re: /inherited|hered|estate|funeral/, label: "Inherited Property" },
    { re: /back tax|taxes|impuestos|lien|tax lien/, label: "Behind on Taxes" },
    { re: /landlord|tenant|inquilino|propietario cansado/, label: "Tired Landlord" },
    { re: /relocat|mudanza|moving|out of state|job loss|relocaliza/, label: "Relocation" },
  ];
  const segmentMatch = segments.find(s => s.re.test(allText));
  let personaFit;
  if (segmentMatch) {
    score += 3;
    personaFit = `Segmento detectado: ${segmentMatch.label}`;
  } else if (/wisconsin|cash buyer|sell my house|vender|distressed|homeowner/.test(allText)) {
    score += 1.5;
    personaFit = "Real estate genérico — falta segment específico";
    notes.push("Refinar para hablar a un segment específico (foreclosure / inherited / divorce / back taxes / tired landlord / relocation)");
  } else {
    score += 0;
    personaFit = "Sin match a persona Pinnacle";
    notes.push("Idea no parece dirigirse a homeowner WI distressed — re-enfocar");
  }

  // 2. Brand voice (0–2)
  let brandVoice = "OK";
  // Spanish accents present (ortografía perfecta)
  const hasAccents = /[áéíóúñ]/i.test(captionEs);
  if (hasAccents || captionEs.length < 30) score += 1;
  else { brandVoice = "Spanish sin acentos"; notes.push("Caption ES debe tener acentos perfectos (á é í ó ú ñ)"); }

  // No investor jargon
  const hasJargon = /\broi\b|\bcap rate\b|off-market|wholesaler|deal flow|flip margin/i.test(allText);
  if (!hasJargon) score += 1;
  else { brandVoice = (brandVoice === "OK" ? "" : brandVoice + " | ") + "Investor jargon"; notes.push("Eliminar jerga de inversor (ROI/cap rate/off-market) — audiencia es homeowner, no investor"); }

  // 3. Compliance (deduct on red flags)
  const ftcRed = /guaranteed|garantizado|no risk|sin riesgo|100%|never lose|nunca perder/i.test(allText);
  if (ftcRed) {
    score -= 2;
    compliance = "RISK: FTC red flag (guaranteed/no risk)";
    notes.push("Eliminar 'guaranteed' / 'no risk' / '100%' — FTC violation");
  }
  const hudRed = /only (white|black|hispanic|christian|jewish)|no kids|no families|no disabled/i.test(allText);
  if (hudRed) {
    score -= 3;
    compliance = "RISK: HUD Fair Housing violation (discriminatory targeting)";
    notes.push("CRITICAL — discriminatory targeting detectada — HUD Fair Housing");
  }
  // Homosexuality promotion (Jorge 2026-05-07)
  const lgbtPromote = /lgbt|gay couple|same-sex|pareja gay|pareja del mismo/i.test(allText);
  if (lgbtPromote) {
    score -= 1;
    compliance = (compliance === "OK" ? "" : compliance + " | ") + "Homosexuality promotion flagged";
    notes.push("Pinnacle no promueve homosexualidad — usar pareja heterosexual tradicional");
  }
  if (compliance === "OK") score += 1;

  // 4. Hook quality (0–1)
  if (hook && hook.length >= 10 && hook.length <= 120) {
    score += 1;
    if (/[?¿]/.test(hook)) score += 0.5;
  } else if (!hook) {
    notes.push("Hook vacío — añadir 1 línea que abra curiosidad");
  } else if (hook.length > 120) {
    score += 0.3;
    notes.push("Hook muy largo (>120 chars) — recortar");
  }

  // 5. CTA presence (0–1.5)
  const phoneInCta = /920.*777.*9886|9207779886|\(920\) 777/.test(captionEs + " " + captionEn + " " + cta);
  const webInCta   = /pinnaclegroupwi\.com/i.test(captionEs + " " + captionEn + " " + cta);
  if (phoneInCta) score += 0.75; else notes.push("Falta teléfono (920) 777-9886 en CTA");
  if (webInCta)   score += 0.75; else notes.push("Falta pinnaclegroupwi.com en CTA");

  // 6. Visual_Prompt clarity (0–1)
  if (/T[1-5]\b/i.test(visualPrompt)) score += 1;
  else notes.push("Visual_Prompt debe especificar TEMA T1-T5");

  // Clamp to 0–10 and round
  score = Math.max(0, Math.min(10, Math.round(score * 10) / 10));
  const verdict = score >= APPROVE_THRESHOLD ? "APPROVE" : "REJECT";

  return {
    score,
    verdict,
    persona_fit: personaFit,
    brand_voice: brandVoice,
    compliance,
    improvement_notes: notes.join(" · ").slice(0, 400) || (verdict === "APPROVE" ? "OK — listo para Creativo" : "Revisar criterios"),
    _source: "deterministic",
  };
}

// ─── Sonnet review call ───
async function reviewIdea(record, ctx) {
  const f = record.fields || {};
  const titulo     = f["Título de Idea"] || "";
  const hook       = f.Hook || "";
  const captionEs  = f["🇲🇽 Caption ES"] || "";
  const captionEn  = f["🇺🇸 Caption EN"] || "";
  const hashtags   = f.Hashtags || "";
  const cta        = f.CTA || "";
  const formato    = f.Formato || "";
  const tipo       = f.Tipo || "";
  const visualPrompt = f.Visual_Prompt || "";

  const systemPrompt = `You are El Oráculo — quality gate for Pinnacle Holdings Group LLC's social media pipeline. Your job: review one idea generated by El Social Media Manager and decide if El Creativo should render it.

Output ONLY a JSON object — no prose, no markdown fences:
{
  "score": 1-10,
  "verdict": "APPROVE" | "REJECT",
  "persona_fit": "1-2 sentence assessment",
  "brand_voice": "1-2 sentence assessment",
  "compliance": "OK" | "RISK: <issue>",
  "improvement_notes": "string — what SM Manager should fix if rejected, OR brief praise if approved"
}

REVIEW CRITERIA:
1. PERSONA FIT — Does the idea speak directly to one of the 6 Wisconsin distressed homeowner segments (foreclosure / inherited / divorce / back taxes / tired landlord / relocation)? Generic real estate content = REJECT.
2. BRAND VOICE — Warm, no-pressure, no investor jargon, no salesy hype. "Cash buyer" OK. "ROI / cap rate / off-market deal" = REJECT.
3. COMPLIANCE — No FTC red flags ("guaranteed", "no risk"). No HUD Fair Housing violations (race/family/disability targeting). No promotion of homosexuality in visual concepts.
4. HOOK QUALITY — Opens curiosity in <12 words. Should mention pain point or benefit.
5. CTA PRESENCE — phone (920) 777-9886 + pinnaclegroupwi.com mandatory in caption ES + EN.
6. VISUAL_PROMPT CLARITY — Should specify TEMA T1-T5 and be actionable for Creativo.

APPROVE if score >= 7. Otherwise REJECT with concrete improvement_notes.

${ctx.persona ? `\n[AUDIENCE PERSONA]\n${ctx.persona}\n` : ""}
${ctx.copy ? `\n[BRAND VOICE / COPY GUIDELINES]\n${ctx.copy}\n` : ""}`;

  const userPrompt = `Review this idea:

Título: ${titulo}
Tipo: ${tipo} | Formato: ${formato}

Hook:
${hook}

Caption ES:
${captionEs.slice(0, 600)}

Caption EN:
${captionEn.slice(0, 600)}

Hashtags: ${hashtags.slice(0, 200)}
CTA: ${cta.slice(0, 200)}

Visual_Prompt:
${visualPrompt.slice(0, 600)}

Return JSON only.`;

  const r = await fetch("https://api.anthropic.com/v1/messages", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "x-api-key": ANTHROPIC_KEY,
      "anthropic-version": "2023-06-01",
    },
    body: JSON.stringify({
      model: SONNET_MODEL,
      max_tokens: 1000,
      system: systemPrompt,
      messages: [{ role: "user", content: userPrompt }],
    }),
  });
  if (!r.ok) {
    const t = await r.text();
    throw new Error(`Sonnet HTTP ${r.status}: ${t.slice(0, 200)}`);
  }
  const j = await r.json();
  const text = j.content?.[0]?.text || "";
  const cleaned = text.replace(/```(?:json)?\s*/g, "").replace(/```/g, "").trim();
  const start = cleaned.indexOf("{");
  if (start === -1) throw new Error(`Sonnet: no JSON in response: ${text.slice(0, 100)}`);
  let depth = 0;
  for (let i = start; i < cleaned.length; i++) {
    if (cleaned[i] === "{") depth++;
    else if (cleaned[i] === "}") {
      depth--;
      if (depth === 0) {
        return JSON.parse(cleaned.slice(start, i + 1));
      }
    }
  }
  throw new Error("Sonnet: unbalanced JSON");
}

// ─── Process one record ───
async function processOne(record, ctx) {
  const f = record.fields || {};
  const titulo = f["Título de Idea"] || record.id;
  const visualPrompt = f.Visual_Prompt || "";

  // Skip if already approved (idempotent — safe to re-run).
  if (visualPrompt.startsWith("[ORACULO_OK")) {
    return { id: record.id, titulo, status: "skip_already_approved" };
  }
  // Skip if no Visual_Prompt yet (SM Manager hasn't enriched it).
  if (!visualPrompt) {
    return { id: record.id, titulo, status: "skip_no_visual_prompt" };
  }

  // Try Sonnet first (best quality). If it fails (no credits, network, etc),
  // fall back to deterministic rule-based scoring so the pipeline keeps moving.
  let review;
  let reviewSource = "sonnet";
  if (ANTHROPIC_KEY) {
    try {
      review = await reviewIdea(record, ctx);
    } catch (e) {
      const msg = String(e.message).slice(0, 150);
      console.error(`[oraculo] Sonnet failed for ${record.id} (${msg}) — falling back to deterministic`);
      review = reviewIdeaDeterministic(record);
      reviewSource = "deterministic";
    }
  } else {
    console.error(`[oraculo] no ANTHROPIC_API_KEY — using deterministic review`);
    review = reviewIdeaDeterministic(record);
    reviewSource = "deterministic";
  }

  const score = Number(review.score) || 0;
  const verdict = String(review.verdict || "").toUpperCase();
  const approved = verdict === "APPROVE" && score >= APPROVE_THRESHOLD;

  if (approved) {
    // Prepend [ORACULO_OK score=N] marker to Visual_Prompt — this is what
    // Creativo's filter checks for.
    const stamp = `[ORACULO_OK score=${score} src=${reviewSource}]`;
    const newVp = `${stamp}\n${visualPrompt}`;
    await smUpdate(record.id, {
      Visual_Prompt: newVp,
      // Clear any previous Error_Reason if the record was previously rejected.
      Error_Reason: "",
    });
    return { id: record.id, titulo, status: "approved", score, source: reviewSource, notes: review.improvement_notes };
  }

  // Rejected: write notes to Error_Reason — SM Manager (or Jorge) reviews.
  const reason = [
    `Oraculo REJECT score=${score}`,
    review.persona_fit ? `Persona: ${review.persona_fit}` : "",
    review.brand_voice ? `Voice: ${review.brand_voice}` : "",
    review.compliance && review.compliance !== "OK" ? `Compliance: ${review.compliance}` : "",
    review.improvement_notes ? `Fix: ${review.improvement_notes}` : "",
  ].filter(Boolean).join(" | ").slice(0, 500);

  await smUpdate(record.id, { Error_Reason: reason });
  return { id: record.id, titulo, status: "rejected", score, reason };
}

// ─── Main ───
async function main() {
  const args = parseArgs(process.argv, VALID_MODES, { recordId: "" });
  const cfg = await loadTenant(args.tenant);
  const runId = genRunId();
  const startedAt = isoNow();

  console.error(`[oraculo] tenant=${cfg.tenant_id} mode=${args.mode} run_id=${runId}`);

  for (const [k, v] of Object.entries({
    AIRTABLE_SM_TOKEN: SM_TOKEN, ANTHROPIC_API_KEY: ANTHROPIC_KEY,
  })) if (!v) console.error(`[oraculo] missing env: ${k}`);

  const ctx = await loadContext();
  if (!ctx.persona && !ctx.copy) {
    console.error("[oraculo] WARNING: oraculo_inputs/ files empty or missing — review will be generic");
  }

  let records = [];
  if (args.mode === "one") {
    if (!args.recordId) { console.error("[oraculo] --record-id required for mode=one"); process.exit(2); }
    const rec = await smGet(args.recordId);
    if (rec.id) records = [rec];
  } else {
    // Filter: Visual_Prompt set, no [ORACULO_OK] prefix, no recent Error_Reason
    // (rejected records stay rejected until SM Manager fixes them and clears Error_Reason).
    const filter = encodeURIComponent(
      `AND({Visual_Prompt}!='', NOT(FIND('[ORACULO_OK', {Visual_Prompt})>0), OR({Error_Reason}='', NOT({Error_Reason})))`
    );
    const r = await smFetch(`filterByFormula=${filter}&maxRecords=${BATCH_MAX_PER_RUN}`);
    records = r.records || [];
  }

  if (records.length === 0) {
    console.error("[oraculo] no records pending review");
    await telegramSend(cfg, `🔮 *El Oráculo* — ${cfg.tenant_name}\nNo hay ideas pendientes de review.`);
    return;
  }

  if (args.dryRun) {
    console.log(`=== DRY RUN [oraculo] ${records.length} records ===`);
    for (const r of records) console.log(`  ${r.id} | ${r.fields?.["Título de Idea"]}`);
    return;
  }

  const results = [];
  for (const rec of records) {
    let out;
    try { out = await processOne(rec, ctx); }
    catch (e) {
      out = { id: rec.id, titulo: rec.fields?.["Título de Idea"] || rec.id, status: "exception", error: String(e?.message || e).slice(0, 200) };
    }
    results.push(out);
    const tail = out.score ? ` (${out.score}/10)` : "";
    console.error(`[oraculo] ${out.titulo}: ${out.status}${tail}${out.error ? ` — ${out.error}` : ""}${out.reason ? ` — ${out.reason.slice(0, 100)}` : ""}`);
  }

  const completedAt = isoNow();
  const duration = Math.round((Date.parse(completedAt) - Date.parse(startedAt)) / 1000);
  const approved = results.filter(r => r.status === "approved").length;
  const rejected = results.filter(r => r.status === "rejected").length;
  const failed   = results.filter(r => /failed|exception/.test(r.status)).length;

  const lines = [
    `🔮 *El Oráculo* — ${cfg.tenant_name}`,
    `${duration}s · ✅ ${approved} approved · ❌ ${rejected} rejected${failed ? ` · ⚠️ ${failed} failed` : ""}`,
  ];
  for (const r of results.slice(0, 8)) {
    const icon = r.status === "approved" ? "✅" : r.status === "rejected" ? "❌" : "⚠️";
    const tail = r.score ? ` ${r.score}/10` : "";
    lines.push(`${icon}${tail} ${(r.titulo || "").slice(0, 50)}`);
    if (r.reason) lines.push(`   ${r.reason.slice(0, 120)}`);
  }
  await telegramSend(cfg, lines.join("\n").slice(0, 3800));

  console.error(`[oraculo] done — approved=${approved} rejected=${rejected} failed=${failed}`);
}

main().catch((e) => { console.error("[oraculo] FATAL:", e); process.exit(1); });
