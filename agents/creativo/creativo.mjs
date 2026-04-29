#!/usr/bin/env node
/**
 * El Creativo R9 — generates Instagram visuals via Replicate Nano Banana.
 *
 * Pipeline:
 *   1. Read Airtable SM ideas where visual_url is empty and Visual_Prompt is set.
 *   2. For each idea, call Replicate google/nano-banana with branded prompt.
 *   3. Upload Replicate output to Cloudinary (persistent CDN).
 *   4. Update Airtable: visual_url + Status="Visual Listo".
 *   5. Telegram summary.
 *
 * Modes:
 *   batch       — process up to N pending ideas (default cron mode)
 *   one         — process a single idea by --record-id
 *   dry-run     — show what would be processed without calling APIs
 *
 * Required secrets (from Doppler or GHA):
 *   REPLICATE_API_TOKEN, AIRTABLE_SM_TOKEN, AIRTABLE_SM_BASE_ID, AIRTABLE_SM_TABLE_ID,
 *   CLOUDINARY_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET,
 *   ANTHROPIC_API_KEY (for prompt enrichment, optional)
 */
import { parseArgs, loadTenant, telegramSend, genRunId, isoNow } from "../_shared/runner.mjs";
import crypto from "node:crypto";

const VALID_MODES = ["batch", "one"];

const SM_BASE  = process.env.AIRTABLE_SM_BASE_ID  || "appU9s3kGkVpdrJkw";
const SM_TABLE = process.env.AIRTABLE_SM_TABLE_ID || "tblAj0Pkj1jW4p5Ld";
const SM_TOKEN = process.env.AIRTABLE_SM_TOKEN    || "";

const REPLICATE_TOKEN = process.env.REPLICATE_API_TOKEN || "";
const REPLICATE_MODEL = "google/nano-banana";

const CLD_NAME   = process.env.CLOUDINARY_NAME       || "";
const CLD_KEY    = process.env.CLOUDINARY_API_KEY    || "";
const CLD_SECRET = process.env.CLOUDINARY_API_SECRET || "";

const LOGO_URL = "https://pinnaclegroupwi.com/wp-content/uploads/2026/03/logo-pinnacle.png";
const BRAND_PHONE   = "(920) 777-9886";
const BRAND_WEBSITE = "pinnaclegroupwi.com";

const BATCH_MAX_PER_RUN = 3;          // up to 3 visuals per run (cost cap)
const REPLICATE_TIMEOUT_SEC = 120;    // safety timeout

const THEMES = {
  T1: { name: "Dark Premium", bg: "#0D3B2E", text: "#FFFFFF", accent: "#C9A84C" },
  T2: { name: "White Clean",  bg: "#FFFFFF", text: "#0D3B2E", accent: "#C9A84C" },
  T3: { name: "Gold & Black", bg: "#1A1A1A", text: "#FFFFFF", accent: "#C9A84C" },
  T4: { name: "Soft Cream",   bg: "#F5F0E8", text: "#0D3B2E", accent: "#C9A84C" },
  T5: { name: "Vibrant Blue", bg: "#1B2A8C", text: "#FFFFFF", accent: "#FF2D78" },
};

// ──────────────────────────────────────────────────────────────
// Airtable SM helpers
// ──────────────────────────────────────────────────────────────
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

// ──────────────────────────────────────────────────────────────
// Theme detection from Visual_Prompt
// ──────────────────────────────────────────────────────────────
function detectTheme(visualPrompt) {
  const m = String(visualPrompt || "").match(/T([1-5])\b/i);
  const code = m ? `T${m[1]}` : "T1";
  return { code, ...THEMES[code] };
}

// ──────────────────────────────────────────────────────────────
// Build Replicate prompt enriched with brand
// ──────────────────────────────────────────────────────────────
function buildBrandedPrompt({ titulo, hook, captionEn, visualPrompt, theme, formato }) {
  const isCarrusel = String(formato).toLowerCase() === "carrusel";
  const aspect = "Square 1:1 social-media-post format. 1080x1080 pixels. Mobile-first, Instagram + Facebook optimized.";
  const brand = `Pinnacle Holdings Group LLC — real estate cash home buyer in Wisconsin. Logo: small clean watermark bottom-right reading "PINNACLE HOLDINGS GROUP". Phone "${BRAND_PHONE}" subtle bottom. Brand colors: background ${theme.bg}, text ${theme.text}, accent gold ${theme.accent}.`;
  const style = `Professional, clean, modern, high-contrast typography. Real estate context. Bilingual EN/ES if specified in source prompt. NO photorealistic faces unless requested. NO blurry text. Bold readable headlines.`;
  const headline = hook ? `Primary headline (large, centered, bold, ${theme.text}): "${hook.slice(0, 100)}".` : "";
  const subline = captionEn ? `Subtle support text below: "${captionEn.slice(0, 80)}".` : "";

  return [
    `${aspect}`,
    `${brand}`,
    `${style}`,
    `${headline}`,
    `${subline}`,
    isCarrusel ? `Single carousel cover slide — high-impact hook visual.` : `Standalone post visual.`,
    `Original creative spec from Social Media Agent:`,
    `${visualPrompt}`.slice(0, 600),
    `End with subtle gold ${theme.accent} divider line and centered Pinnacle logo placeholder area.`,
  ].filter(Boolean).join("\n\n");
}

// ──────────────────────────────────────────────────────────────
// Replicate — generate via google/nano-banana
// ──────────────────────────────────────────────────────────────
async function replicateGenerate(prompt) {
  const url = `https://api.replicate.com/v1/models/${REPLICATE_MODEL}/predictions`;
  const r = await fetch(url, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${REPLICATE_TOKEN}`,
      "Content-Type": "application/json",
      "Prefer": "wait=60", // Replicate will block up to 60s waiting for completion
    },
    body: JSON.stringify({
      input: { prompt, aspect_ratio: "1:1", output_format: "png" },
    }),
  });
  if (!r.ok) {
    const errText = await r.text();
    return { error: `replicate HTTP ${r.status}: ${errText.slice(0, 200)}`, output: null };
  }
  const j = await r.json();
  if (j.error) return { error: j.error, output: null, id: j.id };
  if (j.status === "succeeded" && j.output) return { output: j.output, id: j.id };
  // If still running, poll until timeout.
  const start = Date.now();
  let pollId = j.id;
  while ((Date.now() - start) / 1000 < REPLICATE_TIMEOUT_SEC) {
    await new Promise((res) => setTimeout(res, 5000));
    const pr = await fetch(`https://api.replicate.com/v1/predictions/${pollId}`, {
      headers: { Authorization: `Bearer ${REPLICATE_TOKEN}` },
    });
    const pj = await pr.json();
    if (pj.status === "succeeded" && pj.output) return { output: pj.output, id: pollId };
    if (pj.status === "failed" || pj.status === "canceled") return { error: pj.error || pj.status, output: null, id: pollId };
  }
  return { error: "replicate timeout", output: null, id: pollId };
}

// ──────────────────────────────────────────────────────────────
// Cloudinary — upload via signed URL
// ──────────────────────────────────────────────────────────────
async function cloudinaryUpload(imageUrl, publicIdHint = "") {
  const timestamp = Math.floor(Date.now() / 1000);
  const folder = "pinnacle/social";
  // Sign params alphabetically: folder + timestamp.
  const stringToSign = `folder=${folder}&timestamp=${timestamp}`;
  const signature = crypto.createHash("sha1").update(stringToSign + CLD_SECRET).digest("hex");

  const form = new FormData();
  form.append("file", imageUrl);
  form.append("api_key", CLD_KEY);
  form.append("timestamp", String(timestamp));
  form.append("folder", folder);
  form.append("signature", signature);

  const r = await fetch(`https://api.cloudinary.com/v1_1/${CLD_NAME}/image/upload`, {
    method: "POST",
    body: form,
  });
  const j = await r.json();
  if (j.error) return { error: j.error.message, secure_url: null };
  return { secure_url: j.secure_url, public_id: j.public_id, width: j.width, height: j.height };
}

// ──────────────────────────────────────────────────────────────
// Process one idea
// ──────────────────────────────────────────────────────────────
async function processOne(record) {
  const f = record.fields || {};
  const titulo = f["Título de Idea"] || record.id;
  const visualPrompt = f.Visual_Prompt || "";
  const hook = f.Hook || "";
  const captionEn = f["🇺🇸 Caption EN"] || "";
  const formato = f.Formato || "Post";

  if (!visualPrompt) {
    return { id: record.id, titulo, status: "skip", reason: "no Visual_Prompt" };
  }

  const theme = detectTheme(visualPrompt);
  const prompt = buildBrandedPrompt({ titulo, hook, captionEn, visualPrompt, theme, formato });

  const gen = await replicateGenerate(prompt);
  if (gen.error || !gen.output) {
    return { id: record.id, titulo, status: "replicate_failed", error: String(gen.error).slice(0, 150) };
  }

  // gen.output may be a string or array of strings.
  const replicateUrl = Array.isArray(gen.output) ? gen.output[0] : gen.output;

  const cld = await cloudinaryUpload(replicateUrl);
  if (cld.error || !cld.secure_url) {
    // Fallback: store the Replicate URL directly (it's signed, lasts ~1h).
    await smUpdate(record.id, {
      visual_url: replicateUrl,
      Status: "Visual Listo",
      "Blotato_Visual_ID": `replicate:${gen.id}`,
    }).catch(() => null);
    return { id: record.id, titulo, status: "cloudinary_failed_fallback", url: replicateUrl, error: cld.error };
  }

  await smUpdate(record.id, {
    visual_url: cld.secure_url,
    Status: "Visual Listo",
    "Blotato_Visual_ID": `replicate:${gen.id}|cld:${cld.public_id}`,
  });

  return {
    id: record.id, titulo, status: "done",
    theme: theme.code, replicate_id: gen.id, cloudinary_url: cld.secure_url,
  };
}

// ──────────────────────────────────────────────────────────────
// Main
// ──────────────────────────────────────────────────────────────
async function main() {
  const args = parseArgs(process.argv, VALID_MODES, { recordId: "" });
  const cfg = await loadTenant(args.tenant);
  const runId = genRunId();
  const startedAt = isoNow();

  console.error(`[creativo] tenant=${cfg.tenant_id} mode=${args.mode} run_id=${runId} dry_run=${args.dryRun}`);

  // Sanity checks.
  for (const [k, v] of Object.entries({
    REPLICATE_API_TOKEN: REPLICATE_TOKEN,
    AIRTABLE_SM_TOKEN: SM_TOKEN,
    CLOUDINARY_NAME: CLD_NAME, CLOUDINARY_API_KEY: CLD_KEY, CLOUDINARY_API_SECRET: CLD_SECRET,
  })) {
    if (!v) { console.error(`[creativo] missing env: ${k}`); }
  }

  let records = [];
  if (args.mode === "one") {
    if (!args.recordId) { console.error("[creativo] --record-id required for mode=one"); process.exit(2); }
    const rec = await smGet(args.recordId);
    if (rec.id) records = [rec];
  } else {
    const filter = encodeURIComponent(
      `AND({Visual_Prompt}!='', OR({visual_url}='', NOT({visual_url})), NOT(OR({Formato}='Reel', {Formato}='Video')))`
    );
    const r = await smFetch(`filterByFormula=${filter}&maxRecords=${BATCH_MAX_PER_RUN}`);
    records = r.records || [];
  }

  if (records.length === 0) {
    console.error("[creativo] no pending ideas");
    await telegramSend(cfg, `🎨 *El Creativo* — ${cfg.tenant_name}\nNo hay ideas pendientes de visual.`);
    return;
  }

  if (args.dryRun) {
    console.log(`=== DRY RUN [creativo] ${records.length} ideas ===`);
    for (const r of records) {
      const f = r.fields || {};
      console.log(`  ${r.id} | ${f["Título de Idea"]} | tema=${detectTheme(f.Visual_Prompt).code}`);
    }
    return;
  }

  const results = [];
  for (const rec of records) {
    const out = await processOne(rec);
    results.push(out);
    console.error(`[creativo] ${out.titulo}: ${out.status}${out.error ? ` (${out.error})` : ""}`);
  }

  const completedAt = isoNow();
  const duration = Math.round((Date.parse(completedAt) - Date.parse(startedAt)) / 1000);
  const done = results.filter((r) => r.status === "done").length;
  const fallback = results.filter((r) => r.status === "cloudinary_failed_fallback").length;
  const failed = results.filter((r) => /failed/.test(r.status) && r.status !== "cloudinary_failed_fallback").length;

  const lines = [
    `🎨 *El Creativo* — ${cfg.tenant_name}`,
    `${duration}s · ${done} done${fallback ? ` · ${fallback} fallback` : ""}${failed ? ` · ${failed} failed` : ""}`,
  ];
  for (const r of results.slice(0, 5)) {
    const icon = r.status === "done" ? "✅" : r.status === "skip" ? "⏭" : "❌";
    lines.push(`${icon} ${r.titulo.slice(0, 50)}${r.cloudinary_url ? `\n   ${r.cloudinary_url}` : ""}`);
  }
  await telegramSend(cfg, lines.join("\n").slice(0, 3800));

  console.error(`[creativo] done — results=${JSON.stringify(results.map((r) => r.status))}`);
}

main().catch((e) => { console.error("[creativo] FATAL:", e); process.exit(1); });
