#!/usr/bin/env node
/**
 * El Creativo R9 — generates Pinnacle visuals via HTML/CSS render (NO AI imagen).
 *
 * Stack (per CLAUDE.md regla 1d + memoria_ALex.md regla R10):
 *   1. Sonnet 4.6 maps Visual_Prompt + Caption EN/ES → carousel spec object.
 *   2. themes.mjs builds BODY HTML (5 themes T1-T5, hook + N points + CTA).
 *   3. render.mjs renders HTML → PNG via Playwright headless Chromium.
 *   4. Cloudinary signed upload → persistent CDN URL.
 *   5. Airtable SM Base updated: visual_url, Status="Visual Listo".
 *
 * Output rules:
 *   - For Carrusel format: render N slides (hook + 3-4 points + CTA), upload all,
 *     join URLs with "|" in visual_url; first URL is the cover.
 *   - For Post format: render single hook slide, upload, set visual_url.
 *
 * Why HTML/CSS instead of AI imagen models: AI hallucinates Spanish text and
 * branding. Puppeteer/Playwright renders text perfectly. Decided 2026-04-29
 * after Replicate Nano Banana attempt failed Jorge's review.
 */
import { parseArgs, loadTenant, telegramSend, genRunId, isoNow } from "../_shared/runner.mjs";
import { THEMES, VALID_THEME_CODES, slideHook, slidePoint, slideCTA, buildCarousel } from "../creativo_runner/themes.mjs";
import { renderHtmlToPng, closeBrowser } from "./render.mjs";
import crypto from "node:crypto";

const VALID_MODES = ["batch", "one"];

const SM_BASE  = process.env.AIRTABLE_SM_BASE_ID  || "appU9s3kGkVpdrJkw";
const SM_TABLE = process.env.AIRTABLE_SM_TABLE_ID || "tblAj0Pkj1jW4p5Ld";
const SM_TOKEN = process.env.AIRTABLE_SM_TOKEN    || "";

const CLD_NAME   = process.env.CLOUDINARY_NAME       || "";
const CLD_KEY    = process.env.CLOUDINARY_API_KEY    || "";
const CLD_SECRET = process.env.CLOUDINARY_API_SECRET || "";

const ANTHROPIC_KEY = process.env.ANTHROPIC_API_KEY || "";
const SONNET_MODEL  = "claude-sonnet-4-6";

const BATCH_MAX_PER_RUN = 3;

// ─── Airtable SM helpers (separate base from CRM) ───
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

// ─── Deterministic spec builder from Airtable fields (no LLM required) ───
function buildSpecDeterministic(fields) {
  const visualPrompt = String(fields.Visual_Prompt || "");
  const titulo = fields["Título de Idea"] || "";
  const hook = fields.Hook || "";
  const captionEs = fields["🇲🇽 Caption ES"] || "";
  const captionEn = fields["🇺🇸 Caption EN"] || "";
  const mensaje = fields["Mensaje Principal"] || captionEs;
  const cta = fields.CTA || "";
  const formato = fields.Formato || "Post";
  const isCarrusel = String(formato).toLowerCase() === "carrusel";

  const themeMatch = visualPrompt.match(/T([1-5])\b/i);
  const theme = themeMatch ? `T${themeMatch[1]}` : "T1";

  // Hook: prefer field Hook (Spanish). Generate English version from caption EN's first sentence.
  const hookEs = (hook || titulo).split(/\n|\.|!|\?/)[0].trim().slice(0, 80);
  const hookEn = (captionEn || titulo).split(/\n|\.|!|\?/)[0].trim().slice(0, 80);

  // Points: parse from "Mensaje Principal" or Visual_Prompt — split by "Paso N:", "•", "-", or numbered lines.
  const points = [];
  if (isCarrusel) {
    const text = (mensaje + "\n" + visualPrompt).trim();
    // Match "Paso N:" / "N." / "N)" / "•" / "-" / emoji-numbered (1️⃣) wherever they appear.
    const re = /(?:Paso\s+(\d+)|(\d+)[.\)]|[•\-—]|[0-9]⃣)\s*[:\-—]?\s+([^\n]{8,300}?)(?=\s+(?:Paso\s+\d+|\d+[.\)]|[•\-—]|[0-9]⃣)|\n\n|\n[A-ZÁÉÍÓÚÑ]|$)/gs;
    let m;
    while ((m = re.exec(text)) !== null && points.length < 4) {
      const raw = m[3].trim().replace(/\s+/g, " ");
      if (raw.length < 8) continue;
      // Split into headingEs (first segment up to ':' or '.') and bodyEs (rest).
      const colonIdx = raw.indexOf(":");
      let headingEs, bodyEs;
      if (colonIdx > 0 && colonIdx < 60) {
        headingEs = raw.slice(0, colonIdx).trim().slice(0, 60);
        bodyEs = raw.slice(colonIdx + 1).trim().slice(0, 200);
      } else {
        const dotIdx = raw.indexOf(".");
        if (dotIdx > 0 && dotIdx < 80) {
          headingEs = raw.slice(0, dotIdx).trim().slice(0, 60);
          bodyEs = raw.slice(dotIdx + 1).trim().slice(0, 200);
        } else {
          headingEs = raw.slice(0, 60);
          bodyEs = raw.slice(60, 240) || raw;
        }
      }
      points.push({ headingEs, bodyEs });
    }
    // Fallback: if no points parsed, split caption ES into 2-3 sentences.
    if (points.length === 0 && captionEs) {
      const sentences = captionEs.split(/(?<=[.!?])\s+/).filter((s) => s.length > 15).slice(0, 4);
      sentences.forEach((s, i) => {
        const colonIdx = s.indexOf(":");
        if (colonIdx > 0 && colonIdx < 60) {
          points.push({ headingEs: s.slice(0, colonIdx).trim(), bodyEs: s.slice(colonIdx + 1).trim().slice(0, 200) });
        } else {
          points.push({ headingEs: `Punto ${i + 1}`, bodyEs: s.trim().slice(0, 200) });
        }
      });
    }
  }

  return {
    theme,
    hook: { hookEn, hookEs, badge: "" },
    points,
    cta: {
      ctaEn: "We Buy Houses — Cash. Fast. Fair.",
      ctaEs: cta || "Compramos Casas — Efectivo. Rápido. Justo.",
    },
  };
}

// ─── Anthropic enrichment (optional — only if ANTHROPIC_API_KEY available) ───
async function buildSpecWithSonnet(fields) {
  const visualPrompt = fields.Visual_Prompt || "";
  const titulo = fields["Título de Idea"] || "";
  const hook = fields.Hook || "";
  const captionEs = fields["🇲🇽 Caption ES"] || "";
  const captionEn = fields["🇺🇸 Caption EN"] || "";
  const cta = fields.CTA || "";
  const formato = fields.Formato || "Post";

  const themeMatch = String(visualPrompt).match(/T([1-5])\b/i);
  const detectedTheme = themeMatch ? `T${themeMatch[1]}` : "T1";

  const isCarrusel = String(formato).toLowerCase() === "carrusel";

  const systemPrompt = `You build social-media slide specs for Pinnacle Holdings (real estate cash buyer in Wisconsin). Output ONLY a JSON object — no prose, no markdown fences.

Schema:
{
  "theme": "T1"|"T2"|"T3"|"T4"|"T5",
  "hook": { "hookEn": "string short bold headline (max 8 words)", "hookEs": "Spanish version (max 8 words)", "badge": "optional 1-2 word chip" },
  "points": [{ "headingEn": "section title", "headingEs": "Spanish title", "bodyEs": "1-2 sentences Spanish body" }],
  "cta": { "ctaEn": "We Buy Houses — Cash. Fast. Fair.", "ctaEs": "Compramos Casas — Efectivo. Rápido. Justo." }
}

Rules:
- Bilingual (EN headlines + ES body), text MUST be ortographically perfect Spanish (acentos, ñ).
- Carrusel format → 3 to 4 points (slides 2-5). Post format → 0 points (just hook + CTA).
- Use the theme already specified in source (T1 default Dark Premium, T2 White Clean, T3 Gold/Black, T4 Cream, T5 Vibrant Blue).
- Keep headings under 8 words. Body sentences under 18 words.`;

  const userPrompt = `Build the spec for this Pinnacle Holdings idea:

Title (ES): ${titulo}
Hook source: ${hook}
Caption ES: ${captionEs.slice(0, 400)}
Caption EN: ${captionEn.slice(0, 400)}
CTA: ${cta}
Format: ${formato}
Detected theme code: ${detectedTheme}

Original Visual_Prompt from Social Media Agent:
${visualPrompt.slice(0, 800)}

Format: ${isCarrusel ? "CARRUSEL — 3-4 points" : "POST — 0 points (hook + CTA only)"}

Return JSON only.`;

  const r = await fetch("https://api.anthropic.com/v1/messages", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "x-api-key": ANTHROPIC_KEY,
      "anthropic-version": "2023-06-01",
    },
    body: JSON.stringify({
      model: SONNET_MODEL, max_tokens: 1500,
      system: systemPrompt,
      messages: [{ role: "user", content: userPrompt }],
    }),
  });
  if (!r.ok) {
    const t = await r.text();
    throw new Error(`Anthropic HTTP ${r.status}: ${t.slice(0, 200)}`);
  }
  const j = await r.json();
  const text = j.content?.[0]?.text || "";
  // Parse first JSON object.
  const cleaned = text.replace(/```(?:json)?\s*/g, "").replace(/```/g, "").trim();
  const start = cleaned.indexOf("{");
  if (start === -1) throw new Error(`Sonnet did not return JSON: ${text.slice(0, 100)}`);
  let depth = 0;
  for (let i = start; i < cleaned.length; i++) {
    if (cleaned[i] === "{") depth++;
    else if (cleaned[i] === "}") {
      depth--;
      if (depth === 0) {
        const obj = JSON.parse(cleaned.slice(start, i + 1));
        // Sanity: ensure theme is valid.
        if (!VALID_THEME_CODES.includes(obj.theme)) obj.theme = detectedTheme;
        if (!Array.isArray(obj.points)) obj.points = [];
        return obj;
      }
    }
  }
  throw new Error("Sonnet output: unbalanced JSON");
}

// ─── Cloudinary signed upload (file = Buffer base64-prefixed data URI) ───
async function cloudinaryUploadBuffer(buffer, publicIdHint = "") {
  const timestamp = Math.floor(Date.now() / 1000);
  const folder = "pinnacle/social";
  const stringToSign = `folder=${folder}&timestamp=${timestamp}`;
  const signature = crypto.createHash("sha1").update(stringToSign + CLD_SECRET).digest("hex");

  const dataUri = `data:image/png;base64,${buffer.toString("base64")}`;
  const form = new FormData();
  form.append("file", dataUri);
  form.append("api_key", CLD_KEY);
  form.append("timestamp", String(timestamp));
  form.append("folder", folder);
  form.append("signature", signature);

  const r = await fetch(`https://api.cloudinary.com/v1_1/${CLD_NAME}/image/upload`, {
    method: "POST", body: form,
  });
  const j = await r.json();
  if (j.error) throw new Error(`Cloudinary: ${j.error.message}`);
  return j.secure_url;
}

// ─── Process one idea ───
async function processOne(record) {
  const f = record.fields || {};
  const titulo = f["Título de Idea"] || record.id;
  const formato = f.Formato || "Post";
  const isCarrusel = String(formato).toLowerCase() === "carrusel";

  if (!f.Visual_Prompt) {
    return { id: record.id, titulo, status: "skip", reason: "no Visual_Prompt" };
  }

  // 1. Build spec — try Sonnet for quality, fall back to deterministic.
  let spec;
  if (ANTHROPIC_KEY) {
    try { spec = await buildSpecWithSonnet(f); }
    catch (e) {
      console.error(`[creativo] Sonnet failed (${e.message.slice(0, 80)}), falling back to deterministic`);
      spec = buildSpecDeterministic(f);
    }
  } else {
    spec = buildSpecDeterministic(f);
  }

  // 2. themes.mjs builds BODY HTML for each slide.
  let slidesHtml;
  try {
    if (isCarrusel) {
      slidesHtml = buildCarousel(spec); // hook + N points + CTA
    } else {
      slidesHtml = [
        slideHook(spec.theme, spec.hook || {}),
        slideCTA(spec.theme, spec.cta || {}),
      ];
    }
  } catch (e) {
    return { id: record.id, titulo, status: "build_failed", error: String(e.message).slice(0, 150) };
  }

  // 3. Render each slide → PNG Buffer.
  const buffers = [];
  for (const html of slidesHtml) {
    try {
      buffers.push(await renderHtmlToPng(html, { width: 1080, height: 1350 }));
    } catch (e) {
      return { id: record.id, titulo, status: "render_failed", error: String(e.message).slice(0, 150) };
    }
  }

  // 4. Upload each PNG → Cloudinary.
  const urls = [];
  for (const buf of buffers) {
    try {
      urls.push(await cloudinaryUploadBuffer(buf));
    } catch (e) {
      return { id: record.id, titulo, status: "upload_failed", error: String(e.message).slice(0, 150) };
    }
  }

  // 5. Update Airtable: cover URL + all slide URLs joined.
  // Field "Blotato_Visual_ID" holds the carousel slide URLs (legacy field name from Blotato era — Jorge will rename in Airtable UI to Carousel_URLs).
  // Format: "puppeteer:N_slides|url1|url2|..." — parsed by social_media/runner.mjs::parseCarouselSlides().
  const coverUrl = urls[0];
  const allUrls  = urls.join("|");
  await smUpdate(record.id, {
    visual_url: coverUrl,
    Blotato_Visual_ID: `puppeteer:${urls.length}_slides|${allUrls.slice(0, 800)}`,
    Status: "Visual Listo",
  });

  return {
    id: record.id, titulo, status: "done",
    theme: spec.theme,
    slides: urls.length,
    cover: coverUrl,
    all: urls,
  };
}

// ─── Main ───
async function main() {
  const args = parseArgs(process.argv, VALID_MODES, { recordId: "" });
  const cfg = await loadTenant(args.tenant);
  const runId = genRunId();
  const startedAt = isoNow();

  console.error(`[creativo] tenant=${cfg.tenant_id} mode=${args.mode} run_id=${runId} dry_run=${args.dryRun}`);

  for (const [k, v] of Object.entries({
    AIRTABLE_SM_TOKEN: SM_TOKEN, ANTHROPIC_API_KEY: ANTHROPIC_KEY,
    CLOUDINARY_NAME: CLD_NAME, CLOUDINARY_API_KEY: CLD_KEY, CLOUDINARY_API_SECRET: CLD_SECRET,
  })) if (!v) console.error(`[creativo] missing env: ${k}`);

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
      console.log(`  ${r.id} | ${r.fields?.["Título de Idea"]}`);
    }
    return;
  }

  const results = [];
  for (const rec of records) {
    let out;
    try { out = await processOne(rec); }
    catch (e) {
      out = { id: rec.id, titulo: rec.fields?.["Título de Idea"] || rec.id, status: "exception", error: String(e?.message || e).slice(0, 200) };
    }
    results.push(out);
    console.error(`[creativo] ${out.titulo}: ${out.status}${out.error ? ` (${out.error})` : ""}${out.cover ? ` → ${out.cover}` : ""}`);
  }
  await closeBrowser();

  const completedAt = isoNow();
  const duration = Math.round((Date.parse(completedAt) - Date.parse(startedAt)) / 1000);
  const done = results.filter((r) => r.status === "done").length;
  const failed = results.filter((r) => /failed|exception/.test(r.status)).length;

  const lines = [
    `🎨 *El Creativo* — ${cfg.tenant_name}`,
    `${duration}s · ${done} done${failed ? ` · ${failed} failed` : ""}`,
  ];
  for (const r of results.slice(0, 5)) {
    const icon = r.status === "done" ? "✅" : "❌";
    lines.push(`${icon} ${(r.titulo || "").slice(0, 50)}${r.cover ? `\n   ${r.cover}` : r.error ? `\n   ${r.error}` : ""}`);
  }
  await telegramSend(cfg, lines.join("\n").slice(0, 3800));

  console.error(`[creativo] done — results=${JSON.stringify(results.map((r) => r.status))}`);
}

main().catch((e) => { console.error("[creativo] FATAL:", e); process.exit(1); });
