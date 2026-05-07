#!/usr/bin/env node
/**
 * El Reescritor — Learning loop between Oráculo and SM Manager.
 *
 * Mission (Jorge 2026-05-07): "el Reescritor rediseñe el o los prompts y le
 * enseñe al Social Media Manager, y este aprenda a reescribir pero no solo a
 * reescribir pero a escribir de una vez las ideas acorde a lo que el Oráculo
 * le ha venido enseñando."
 *
 * Pipeline:
 *   SM Manager → idea (Status=Nueva)
 *     ↓ Oráculo review
 *     ↓ REJECT → Error_Reason set with critique
 *   Reescritor (this agent):
 *     1. Reads each rejected record + Oráculo critique
 *     2. Sonnet rewrites Hook / Caption ES/EN / Visual_Prompt to address feedback
 *     3. Appends a structured lesson to oraculo_inputs/sm_lessons.md
 *     4. Patches record (new fields, Error_Reason cleared, ready for re-review)
 *
 * SM Manager reads sm_lessons.md before generating new ideas → ideas get better
 * over time → Oráculo rejects less → "el oráculo va trabajando cada vez menos".
 *
 * Cost: ~$0.005/record (Sonnet input ~2k + output ~1k).
 */

import { parseArgs, loadTenant, telegramSend, genRunId, isoNow } from "../_shared/runner.mjs";
import { readFile, writeFile, appendFile, mkdir } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const ORACULO_DIR = join(__dirname, "..", "oraculo_inputs");
const LESSONS_FILE = join(ORACULO_DIR, "sm_lessons.md");

const VALID_MODES = ["batch", "one"];

const SM_BASE  = process.env.AIRTABLE_SM_BASE_ID  || "appU9s3kGkVpdrJkw";
const SM_TABLE = process.env.AIRTABLE_SM_TABLE_ID || "tblAj0Pkj1jW4p5Ld";
const SM_TOKEN = process.env.AIRTABLE_SM_TOKEN    || "";

const ANTHROPIC_KEY = process.env.ANTHROPIC_API_KEY || "";
const SONNET_MODEL  = "claude-sonnet-4-6";

const BATCH_MAX_PER_RUN = Number(process.env.REESCRITOR_BATCH_MAX || 8);

// ─── Persona context (cached) ───
let _personaCache = null;
async function loadPersona() {
  if (_personaCache !== null) return _personaCache;
  try {
    _personaCache = (await readFile(join(ORACULO_DIR, "wi_homeowner_persona.md"), "utf8")).slice(0, 4000);
  } catch { _personaCache = ""; }
  return _personaCache;
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

// ─── Sonnet rewrite + lesson extraction ───
async function rewriteRecord(record, persona) {
  const f = record.fields || {};
  const titulo     = f["Título de Idea"] || "";
  const hook       = f.Hook || "";
  const captionEs  = f["🇲🇽 Caption ES"] || "";
  const captionEn  = f["🇺🇸 Caption EN"] || "";
  const cta        = f.CTA || "";
  const formato    = f.Formato || "";
  const tipo       = f.Tipo || "";
  const visualPrompt = f.Visual_Prompt || "";
  const errorReason  = f.Error_Reason || "";

  const isReel = String(formato).toLowerCase() === "reel";

  const systemPrompt = `You are El Reescritor — Pinnacle Holdings' content rewriter. Your job: take an idea El Oráculo rejected and rewrite it to pass the gate while preserving the original concept.

Output ONLY a JSON object — no prose, no markdown fences:
{
  "titulo": "Spanish title (max 80 chars)",
  "hook": "Spanish hook 1 line — opens curiosity to a SPECIFIC distressed segment (max 100 chars)",
  "caption_es": "Caption ES 200-400 chars, anchored to a 6-segments distressed homeowner pain point, ends with phone (920) 777-9886 + pinnaclegroupwi.com",
  "caption_en": "Caption EN 200-400 chars, mirrors ES tone, same anchor segment, same CTA",
  "cta": "Single-line Spanish CTA (max 100 chars)",
  "visual_prompt": "${isReel ? 'JSON narrative B for Director v2 (validated by validateSpec). HARD CONSTRAINTS — render fails if violated: {\"narrative\":\"B\", \"theme\":one of EXACTLY [\"T1\",\"T2\",\"T3\",\"T4\",\"T5\"] (NEVER hallucinate names like \"distressed_segment_edu\"), \"template\":one of EXACTLY [\"hybrid\",\"pip\",\"voiceover\",\"editorial\"] — DO NOT use \"talkinghead\" because it is full-screen avatar with no slides, \"aspect\":\"9:16\", \"duration\":9 (sweet spot 8-10s, NEVER 12, NEVER 30), \"locale\":\"es\", \"hook\":{\"en\":string,\"es\":string} — short 8-12 words each, \"points\":array of EXACTLY 3 items (Director v2 narrative B renders 1 hook + 3 points + 1 cta = 5 scenes total) each with {\"captionEs\":string of 8-14 words derived from the actual Caption ES message, \"captionEn\":string of 8-14 words, \"heroQuery\":Pexels search 4-6 words for portrait photo,\"heroPrompt\":FLUX2 prompt 12-20 words for cinematic image}, \"cta\":{\"en\":string,\"es\":string} — short 8-12 words each. For Tipo=Personal records, set template=\"hybrid\" so Jorge appears in hook+CTA only and FLUX2 b-roll covers the 3 points. Each captionEs MUST be a substantive line from the original Caption ES (split into 3 progressive beats), NOT generic placeholders.' : 'TITLE: <titulo> | TEMA: one of T1/T2/T3/T4/T5 EXACTLY | <Formato> Pinnacle Audiencia: distressed Wisconsin homeowner [target segment]'}",
  "lesson": {
    "rejected_pattern": "1 line — what was wrong (e.g. 'company-centric framing')",
    "oraculo_critique_summary": "1 line — Oráculo's main point",
    "rewrite_pattern": "1 line — the rule to apply going forward (e.g. 'Always frame from the homeowner POV, not Pinnacle POV')",
    "segment_anchor": "Pre-Foreclosure | Inherited Property | Divorce | Behind on Taxes | Tired Landlord | Relocation"
  }
}

REWRITE PRINCIPLES (apply ALL):
1. Anchor to ONE distressed segment from the 6 — pick the most relevant given the original concept.
2. Frame from the homeowner's POV (their pain, their fear, their relief), NOT from Pinnacle's POV (our growth, our process, our team).
3. Warm tone, no investor jargon (no ROI / cap rate / off-market / deal / flip).
4. Spanish must have perfect ortografía (acentos, ñ).
5. NO FTC red flags ("guaranteed", "no risk", "100%").
6. NO HUD Fair Housing violations.
7. NO promotion of homosexuality in visual concepts.
8. CTA must include phone (920) 777-9886 AND pinnaclegroupwi.com.
9. ${isReel ? 'visual_prompt MUST be valid JSON narrative B (Director v2 spec). HARD RULE (Jorge 2026-05-07): duration must be 7-15s — NEVER exceed 15. If the concept genuinely needs more story, COMPRESS it: tighten copy, drop redundant points, or label the title "Parte 1" and write the rewrite to be the first part of a series. NEVER set duration=30 or duration=20.' : 'visual_prompt MUST specify TEMA T1-T5 (Creativo theme code)'}

[AUDIENCE PERSONA]
${persona}`;

  const userPrompt = `ORIGINAL IDEA (rejected by Oráculo):

Título: ${titulo}
Tipo: ${tipo} | Formato: ${formato}

Hook actual:
${hook}

Caption ES actual:
${captionEs.slice(0, 500)}

Caption EN actual:
${captionEn.slice(0, 500)}

CTA actual: ${cta}

Visual_Prompt actual:
${visualPrompt.slice(0, 500)}

ORACULO REJECTION FEEDBACK:
${errorReason}

Rewrite to address the feedback. Return JSON only.`;

  const r = await fetch("https://api.anthropic.com/v1/messages", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "x-api-key": ANTHROPIC_KEY,
      "anthropic-version": "2023-06-01",
    },
    body: JSON.stringify({
      model: SONNET_MODEL,
      max_tokens: 2000,
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

// ─── Append lesson to sm_lessons.md (rolling window, last 50) ───
async function appendLesson(titulo, lesson) {
  const date = isoNow().slice(0, 10);
  const entry = [
    "",
    `### ${date} — ${titulo.slice(0, 60)}`,
    `- **Rejected pattern**: ${lesson.rejected_pattern || "—"}`,
    `- **Oráculo critique**: ${lesson.oraculo_critique_summary || "—"}`,
    `- **Rewrite pattern**: ${lesson.rewrite_pattern || "—"}`,
    `- **Segment anchor**: ${lesson.segment_anchor || "—"}`,
    "",
  ].join("\n");

  // Append to file (creates if missing).
  try {
    await mkdir(ORACULO_DIR, { recursive: true });
    await appendFile(LESSONS_FILE, entry, "utf8");
  } catch (e) {
    console.error(`[reescritor] failed to append lesson: ${e.message}`);
  }

  // Roll the window: if file has > 100 lessons, keep only the last 50.
  try {
    const text = await readFile(LESSONS_FILE, "utf8");
    const blocks = text.split(/\n### /).filter(Boolean);
    // First block is the header (no ### prefix). Re-split keeping that.
    const headerEnd = text.indexOf("\n### ");
    const header = headerEnd >= 0 ? text.slice(0, headerEnd) : text;
    const lessonBlocks = headerEnd >= 0 ? text.slice(headerEnd).split(/\n(?=### )/).filter(Boolean) : [];
    if (lessonBlocks.length > 100) {
      const kept = lessonBlocks.slice(-50);
      await writeFile(LESSONS_FILE, header + "\n" + kept.join("\n"), "utf8");
    }
  } catch {}
}

// ─── Process one rejected record ───
async function processOne(record, persona) {
  const f = record.fields || {};
  const titulo = f["Título de Idea"] || record.id;
  const errorReason = f.Error_Reason || "";

  // Only process records rejected by Oráculo (not other errors).
  if (!errorReason.includes("Oraculo REJECT")) {
    return { id: record.id, titulo, status: "skip_not_oraculo_reject" };
  }

  let rewrite;
  try {
    rewrite = await rewriteRecord(record, persona);
  } catch (e) {
    return { id: record.id, titulo, status: "rewrite_failed", error: String(e.message).slice(0, 150) };
  }

  // Apply rewrite to record + clear Error_Reason so Oráculo will re-review.
  const updateFields = {
    "Título de Idea": rewrite.titulo || titulo,
    "Hook": rewrite.hook || f.Hook,
    "🇲🇽 Caption ES": rewrite.caption_es || f["🇲🇽 Caption ES"],
    "🇺🇸 Caption EN": rewrite.caption_en || f["🇺🇸 Caption EN"],
    "CTA": rewrite.cta || f.CTA,
    "Visual_Prompt": rewrite.visual_prompt || f.Visual_Prompt,
    "Error_Reason": "",
  };

  try {
    await smUpdate(record.id, updateFields);
  } catch (e) {
    return { id: record.id, titulo, status: "update_failed", error: String(e.message).slice(0, 150) };
  }

  // Append lesson to sm_lessons.md so SM Manager learns for future ideas.
  if (rewrite.lesson) {
    await appendLesson(titulo, rewrite.lesson);
  }

  return {
    id: record.id, titulo,
    status: "rewritten",
    new_titulo: rewrite.titulo,
    segment_anchor: rewrite.lesson?.segment_anchor,
    rewrite_pattern: rewrite.lesson?.rewrite_pattern,
  };
}

// ─── Main ───
async function main() {
  const args = parseArgs(process.argv, VALID_MODES, { recordId: "" });
  const cfg = await loadTenant(args.tenant);
  const runId = genRunId();
  const startedAt = isoNow();

  console.error(`[reescritor] tenant=${cfg.tenant_id} mode=${args.mode} run_id=${runId}`);

  if (!ANTHROPIC_KEY) {
    console.error("[reescritor] ANTHROPIC_API_KEY missing — cannot rewrite without Sonnet");
    process.exit(2);
  }

  const persona = await loadPersona();

  let records = [];
  if (args.mode === "one") {
    if (!args.recordId) { console.error("[reescritor] --record-id required"); process.exit(2); }
    const rec = await smGet(args.recordId);
    if (rec.id) records = [rec];
  } else {
    // Filter: records rejected by Oráculo (Error_Reason contains 'Oraculo REJECT')
    const filter = encodeURIComponent(
      `AND(NOT({Error_Reason}=''), NOT(NOT({Error_Reason})), FIND('Oraculo REJECT',{Error_Reason})>0)`
    );
    const r = await smFetch(`filterByFormula=${filter}&maxRecords=${BATCH_MAX_PER_RUN}`);
    records = r.records || [];
  }

  if (records.length === 0) {
    console.error("[reescritor] no Oráculo-rejected records pending");
    await telegramSend(cfg, `✍️ *El Reescritor* — ${cfg.tenant_name}\nNo hay rejections pendientes de rewrite.`);
    return;
  }

  if (args.dryRun) {
    console.log(`=== DRY RUN [reescritor] ${records.length} records ===`);
    for (const r of records) console.log(`  ${r.id} | ${r.fields?.["Título de Idea"]}`);
    return;
  }

  const results = [];
  for (const rec of records) {
    let out;
    try { out = await processOne(rec, persona); }
    catch (e) {
      out = { id: rec.id, titulo: rec.fields?.["Título de Idea"] || rec.id, status: "exception", error: String(e?.message || e).slice(0, 200) };
    }
    results.push(out);
    console.error(`[reescritor] ${out.titulo}: ${out.status}${out.segment_anchor ? ` → anchor=${out.segment_anchor}` : ""}${out.error ? ` — ${out.error}` : ""}`);
  }

  const completedAt = isoNow();
  const duration = Math.round((Date.parse(completedAt) - Date.parse(startedAt)) / 1000);
  const rewritten = results.filter(r => r.status === "rewritten").length;
  const failed   = results.filter(r => /failed|exception/.test(r.status)).length;

  const lines = [
    `✍️ *El Reescritor* — ${cfg.tenant_name}`,
    `${duration}s · ✅ ${rewritten} rewritten${failed ? ` · ⚠️ ${failed} failed` : ""}`,
  ];
  for (const r of results.slice(0, 6)) {
    const icon = r.status === "rewritten" ? "✍️" : "⚠️";
    lines.push(`${icon} ${(r.titulo || "").slice(0, 50)}${r.segment_anchor ? ` → ${r.segment_anchor}` : ""}`);
    if (r.rewrite_pattern) lines.push(`   ${r.rewrite_pattern.slice(0, 110)}`);
  }
  await telegramSend(cfg, lines.join("\n").slice(0, 3800));

  console.error(`[reescritor] done — rewritten=${rewritten} failed=${failed}`);
}

main().catch((e) => { console.error("[reescritor] FATAL:", e); process.exit(1); });
