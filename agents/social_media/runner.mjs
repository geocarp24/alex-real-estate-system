#!/usr/bin/env node
/**
 * Social Media — full pipeline orchestrator (Pinnacle Holdings).
 *
 * Replaces the manual Claude-Code-only Social Media Agent + Creativo + Director
 * + Programador chain with a single runner that hits Blotato REST API directly.
 *
 * Modes:
 *   generate_ideas    — Sonnet 4.6 generates N new ideas, stores in Airtable
 *   process_visuals   — for ideas with Visual_Prompt and no visual_url, call
 *                       Blotato AI Slide Generator, poll, store URL.
 *   process_posts     — for ideas with visual_url and no Blotato_Post_IDs,
 *                       schedule on FB + IG via Blotato.
 *   full_pipeline     — generate_ideas → process_visuals → process_posts
 *
 * Cron: */3 days from agents-cron.yml. Each run aims to publish 1-3 posts.
 */
import { parseArgs, loadTenant, telegramSend, genRunId, isoNow } from "../_shared/runner.mjs";

const VALID_MODES = ["generate_ideas", "process_visuals", "process_posts", "full_pipeline"];

// ── Pinnacle SM Airtable (separate base from CRM) ──
const SM_BASE  = "appU9s3kGkVpdrJkw";
const SM_TABLE = "tblAj0Pkj1jW4p5Ld";  // Ideas de Contenido
const SM_TOKEN = process.env.SM_AIRTABLE_TOKEN
  || "patSlNwngu7SJoa52.003c83df8f6e378af5309237e310a36568a037448709d94b10739d032f9e8ef7";

// ── Blotato config ──
const BLOTATO_BASE = "https://backend.blotato.com/v2";
const BLOTATO_KEY  = process.env.BLOTATO_API_KEY || "";
const FB_ACCOUNT_ID  = "25638";
const FB_PAGE_ID     = "965320503341457";
const IG_ACCOUNT_ID  = "39285";
const TEMPLATE_CARRUSEL = "53cfec04-2500-41cf-8cc1-ba670d2c341a";  // AI Slide Generator

// ── Pinnacle brand ──
const LOGO_URL = "https://pinnaclegroupwi.com/wp-content/uploads/2026/03/logo-pinnacle.png";

// ── Caps ──
const IDEAS_PER_RUN  = 3;
const VISUALS_PER_RUN = 5;
const POSTS_PER_RUN  = 5;
const POLL_MAX_SEC   = 300;
const POLL_INTERVAL  = 15;

// ──────────────────────────────────────────────────────────────
// Airtable helpers (SM base — separate token from CRM)
// ──────────────────────────────────────────────────────────────
async function smFetch(params = "") {
  const r = await fetch(`https://api.airtable.com/v0/${SM_BASE}/${SM_TABLE}?${params}`, {
    headers: { Authorization: `Bearer ${SM_TOKEN}` },
  });
  return r.json();
}

async function smCreate(fields) {
  const r = await fetch(`https://api.airtable.com/v0/${SM_BASE}/${SM_TABLE}`, {
    method: "POST",
    headers: { Authorization: `Bearer ${SM_TOKEN}`, "Content-Type": "application/json" },
    body: JSON.stringify({ fields, typecast: true }),
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

// ──────────────────────────────────────────────────────────────
// Anthropic API (re-implement here so this runner is self-contained)
// ──────────────────────────────────────────────────────────────
async function callAnthropic(systemPrompt, userPrompt, maxTokens = 1500) {
  const apiKey = process.env.ANTHROPIC_API_KEY;
  if (!apiKey) return { error: "ANTHROPIC_API_KEY missing", text: null };
  try {
    const r = await fetch("https://api.anthropic.com/v1/messages", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "x-api-key": apiKey,
        "anthropic-version": "2023-06-01",
      },
      body: JSON.stringify({
        model: "claude-sonnet-4-6",
        max_tokens: maxTokens,
        system: systemPrompt,
        messages: [{ role: "user", content: userPrompt }],
      }),
    });
    if (!r.ok) {
      const errText = await r.text();
      return { error: `HTTP ${r.status}: ${errText.slice(0, 200)}`, text: null };
    }
    const j = await r.json();
    return { text: j.content?.[0]?.text || "", error: null };
  } catch (e) {
    return { error: e.message, text: null };
  }
}

function parseFirstJSON(text) {
  if (!text) return null;
  const cleaned = text.replace(/```(?:json)?\s*/g, "").replace(/```/g, "").trim();
  const start = cleaned.indexOf("{");
  if (start === -1) return null;
  let depth = 0;
  for (let i = start; i < cleaned.length; i++) {
    if (cleaned[i] === "{") depth++;
    else if (cleaned[i] === "}") {
      depth--;
      if (depth === 0) {
        try { return JSON.parse(cleaned.slice(start, i + 1)); } catch { return null; }
      }
    }
  }
  return null;
}

function parseAllJSON(text) {
  // Extract array of JSON objects from a wrapper like {"ideas": [...]}.
  const obj = parseFirstJSON(text);
  if (obj?.ideas && Array.isArray(obj.ideas)) return obj.ideas;
  if (Array.isArray(obj)) return obj;
  return obj ? [obj] : [];
}

// ──────────────────────────────────────────────────────────────
// Blotato REST helpers
// ──────────────────────────────────────────────────────────────
function blotatoHeaders() {
  return { "blotato-api-key": BLOTATO_KEY, "Content-Type": "application/json" };
}

async function blotatoCreateVisual(templateId, prompt, inputs = {}) {
  const r = await fetch(`${BLOTATO_BASE}/videos/from-templates`, {
    method: "POST",
    headers: blotatoHeaders(),
    body: JSON.stringify({ templateId, prompt, inputs, render: true }),
  });
  return r.json();
}

async function blotatoGetVisual(visualId) {
  const r = await fetch(`${BLOTATO_BASE}/videos/creations/${visualId}`, {
    headers: blotatoHeaders(),
  });
  return r.json();
}

async function blotatoPollVisual(visualId, maxSec = POLL_MAX_SEC, intervalSec = POLL_INTERVAL) {
  const start = Date.now();
  while ((Date.now() - start) / 1000 < maxSec) {
    await new Promise((res) => setTimeout(res, intervalSec * 1000));
    const status = await blotatoGetVisual(visualId);
    if (status.status === "done") return status;
    if (status.status === "failed" || status.error) return status;
  }
  return { status: "timeout", id: visualId };
}

async function blotatoCreatePost({ accountId, platform, text, mediaUrls, scheduledTime, pageId, mediaType }) {
  const payload = {
    post: { text, mediaUrls },
    target: { accountId, platform },
    scheduledTime,
  };
  if (pageId) payload.target.pageId = pageId;
  if (mediaType) payload.post.mediaType = mediaType;
  const r = await fetch(`${BLOTATO_BASE}/posts`, {
    method: "POST",
    headers: blotatoHeaders(),
    body: JSON.stringify(payload),
  });
  return r.json();
}

// ──────────────────────────────────────────────────────────────
// Mode 1 — Generate ideas (Anthropic)
// ──────────────────────────────────────────────────────────────
async function generateIdeas(cfg, runId) {
  const systemPrompt = `You are the Social Media Agent for Pinnacle Holdings Group LLC, a real estate cash home buyer in Wisconsin. Owner: Jorge Cruz. Phone: (920) 777-9886. Web: pinnaclegroupwi.com.

You generate post ideas optimized for Instagram + Facebook. Audience: distressed homeowners (foreclosure, inherited property, divorce, back taxes, relocation). 70% educational, 20% promotional, 10% personal.

Output ONLY a JSON object: { "ideas": [ {idea1}, {idea2}, ... ] }. No prose outside JSON.

Each idea schema:
{
  "title_es": "string — short title in Spanish",
  "title_en": "string — short title in English",
  "tipo": "Educativo" | "Promocional" | "Testimonio" | "Mito" | "Pregunta",
  "formato": "Post" | "Carrusel",
  "hook_es": "1 line, opens curiosity",
  "caption_es": "200-400 chars, with emojis, ends with CTA + phone (920) 777-9886",
  "caption_en": "200-400 chars, English equivalent",
  "hashtags": "5 hashtags max, no #",
  "cta": "1 short imperative line",
  "visual_prompt": "TITLE: [es title]\\nTEMA: T1 Dark Premium\\n\\n[6-8 lines describing slides for AI Slide Generator: Slide 1 hook + slide 2-5 content + final CTA slide]",
  "tema_color": "T1" | "T2" | "T3" | "T4" | "T5"
}

Themes: T1 Dark Premium (default educational), T2 White Clean (data/FAQ), T3 Gold Black (urgency/foreclosure), T4 Soft Cream (testimonios/herencia), T5 Vibrant Blue (high engagement young).`;

  const userPrompt = `Generate ${IDEAS_PER_RUN} fresh post ideas for this week. Mix formats. Cover topics like:
- Foreclosure help Wisconsin
- Inherited property / probate
- Cash vs realtor comparison
- Selling rental property
- Quick relocation sale
- Common myths

Avoid duplicating these recent titles (last 14 days):
${(await getRecentTitles()).join(" / ") || "(none)"}

Return JSON only.`;

  const { text, error } = await callAnthropic(systemPrompt, userPrompt, 3000);
  if (error) return { created: 0, error };
  const ideas = parseAllJSON(text);
  if (ideas.length === 0) return { created: 0, error: "no ideas parsed", raw: text.slice(0, 200) };

  const created = [];
  for (const idea of ideas.slice(0, IDEAS_PER_RUN)) {
    try {
      const fields = {
        "Título de Idea": idea.title_es || idea.title_en || "Untitled",
        "Mensaje Principal": idea.caption_es || "",
        "🇺🇸 Caption EN": idea.caption_en || "",
        "🇲🇽 Caption ES": idea.caption_es || "",
        "Hook": idea.hook_es || "",
        "CTA": idea.cta || "",
        "Hashtags": idea.hashtags || "",
        "Tipo": idea.tipo || "Educativo",
        "Formato": idea.formato || "Post",
        "Plataforma": "AMBAS",
        "Visual_Prompt": idea.visual_prompt || "",
        "Blotato_Template_ID": TEMPLATE_CARRUSEL,
        "Status": "Nueva",
      };
      const result = await smCreate(fields);
      if (result.id) created.push(result.id);
    } catch (e) {
      console.error(`[social_media] create failed: ${e.message}`);
    }
  }
  return { created: created.length, ids: created };
}

async function getRecentTitles() {
  // Past 14 days of titles to avoid duplicates.
  const since = new Date(Date.now() - 14 * 86400000).toISOString().slice(0, 10);
  const filter = encodeURIComponent(`IS_AFTER(CREATED_TIME(), '${since}')`);
  const r = await smFetch(`filterByFormula=${filter}&maxRecords=20&fields%5B%5D=Título de Idea`).catch(() => ({}));
  return (r.records || []).map((rec) => rec.fields?.["Título de Idea"]).filter(Boolean);
}

// ──────────────────────────────────────────────────────────────
// Mode 2 — Process visuals (Blotato create + poll)
// ──────────────────────────────────────────────────────────────
async function processVisuals(cfg, runId) {
  // Find ideas with Visual_Prompt set but no completed Blotato_Visual_ID
  // (i.e., the field is empty OR ends with "|||pending").
  const filter = encodeURIComponent(
    `AND({Visual_Prompt}!='', OR({Blotato_Visual_ID}='', FIND('pending', {Blotato_Visual_ID})>0))`
  );
  const r = await smFetch(`filterByFormula=${filter}&maxRecords=${VISUALS_PER_RUN}`);
  const ideas = r.records || [];
  if (ideas.length === 0) return { processed: 0, reason: "no pending visuals" };

  const results = [];
  for (const idea of ideas) {
    const f = idea.fields || {};
    const prompt = f.Visual_Prompt || "";
    const templateId = f.Blotato_Template_ID || TEMPLATE_CARRUSEL;
    if (!prompt || !BLOTATO_KEY) {
      results.push({ id: idea.id, status: "skip", reason: "no prompt or no key" });
      continue;
    }

    // Create visual.
    const create = await blotatoCreateVisual(templateId, prompt, { logo: LOGO_URL });
    const visualId = create.id;
    if (!visualId) {
      results.push({ id: idea.id, status: "create_failed", error: JSON.stringify(create).slice(0, 150) });
      continue;
    }

    // Mark as pending in Airtable so future runs see it.
    await smUpdate(idea.id, { "Blotato_Visual_ID": `${visualId}|||pending` }).catch(() => null);

    // Poll until done.
    const final = await blotatoPollVisual(visualId);
    if (final.status === "done") {
      const url = final.url || final.outputs?.[0]?.url || "";
      await smUpdate(idea.id, {
        "Blotato_Visual_ID": visualId,
        "visual_url": url,
        "Status": "Visual Listo",
      });
      results.push({ id: idea.id, status: "done", url });
    } else {
      results.push({ id: idea.id, status: final.status || "unknown", visual_id: visualId });
    }
  }
  return { processed: results.filter((r) => r.status === "done").length, total: results.length, results };
}

// ──────────────────────────────────────────────────────────────
// Mode 3 — Process posts (schedule on FB + IG)
// ──────────────────────────────────────────────────────────────
function nextSlotISO(offsetHours = 0) {
  // Next Tue/Thu/Sat 10am-12pm CST (= 15-17 UTC summer / 16-18 winter).
  // Simple: schedule at 16:00 UTC + offset on a future Tue/Thu/Sat.
  const now = new Date();
  const target = new Date(now.getTime() + (offsetHours + 24) * 3_600_000);
  // Move forward to next Tue (2), Thu (4) or Sat (6).
  const allowedDays = [2, 4, 6];
  while (!allowedDays.includes(target.getUTCDay())) {
    target.setUTCDate(target.getUTCDate() + 1);
  }
  target.setUTCHours(16, 0, 0, 0);
  return target.toISOString();
}

async function processPosts(cfg, runId) {
  // Find ideas with visual_url set and no Blotato_Post_IDs yet.
  const filter = encodeURIComponent(
    `AND({visual_url}!='', OR({Blotato_Post_IDs}='', NOT({Blotato_Post_IDs})))`
  );
  const r = await smFetch(`filterByFormula=${filter}&maxRecords=${POSTS_PER_RUN}`);
  const ideas = r.records || [];
  if (ideas.length === 0) return { posted: 0, reason: "no ideas with visual ready" };

  const results = [];
  let slotOffset = 0;
  for (const idea of ideas) {
    const f = idea.fields || {};
    const visualUrl = f.visual_url || "";
    const captionEn = f["🇺🇸 Caption EN"] || "";
    const captionEs = f["🇲🇽 Caption ES"] || f["Mensaje Principal"] || "";
    const hashtags = f.Hashtags || "";
    if (!visualUrl) {
      results.push({ id: idea.id, status: "skip", reason: "no visual_url" });
      continue;
    }

    const text = `${captionEs}\n\n${captionEn}\n\n${hashtags}`.trim();
    const scheduledTime = nextSlotISO(slotOffset * 24);
    slotOffset++;

    const fbResult = await blotatoCreatePost({
      accountId: FB_ACCOUNT_ID, platform: "facebook", pageId: FB_PAGE_ID,
      text, mediaUrls: [visualUrl], scheduledTime,
    });
    const igResult = await blotatoCreatePost({
      accountId: IG_ACCOUNT_ID, platform: "instagram",
      text, mediaUrls: [visualUrl], scheduledTime,
    });

    const fbId = fbResult.id || null;
    const igId = igResult.id || null;
    const ids = [fbId && `fb:${fbId}`, igId && `ig:${igId}`].filter(Boolean).join(",");

    await smUpdate(idea.id, {
      "Blotato_Post_IDs": ids,
      "Status": ids ? "Programado" : "Error",
    }).catch(() => null);

    results.push({ id: idea.id, status: ids ? "scheduled" : "failed", fb: fbId, ig: igId, when: scheduledTime });
  }
  return { posted: results.filter((r) => r.status === "scheduled").length, total: results.length, results };
}

// ──────────────────────────────────────────────────────────────
// Main
// ──────────────────────────────────────────────────────────────
async function main() {
  const args = parseArgs(process.argv, VALID_MODES);
  const cfg = await loadTenant(args.tenant);
  const runId = genRunId();
  const startedAt = isoNow();

  console.error(`[social_media] tenant=${cfg.tenant_id} mode=${args.mode} run_id=${runId} dry_run=${args.dryRun}`);

  if (args.dryRun) {
    console.log(`=== DRY RUN [social_media ${args.mode}] ===`);
    console.log(`Would call Blotato + Anthropic + Airtable SM (${SM_BASE}/${SM_TABLE}).`);
    console.log(`Caps: ideas=${IDEAS_PER_RUN}, visuals=${VISUALS_PER_RUN}, posts=${POSTS_PER_RUN}.`);
    return;
  }

  if (!BLOTATO_KEY && args.mode !== "generate_ideas") {
    console.error("[social_media] BLOTATO_API_KEY missing — visuals/posts will skip");
  }

  const summary = {};
  if (args.mode === "generate_ideas" || args.mode === "full_pipeline") {
    summary.ideas = await generateIdeas(cfg, runId).catch((e) => ({ error: e.message }));
  }
  if (args.mode === "process_visuals" || args.mode === "full_pipeline") {
    summary.visuals = await processVisuals(cfg, runId).catch((e) => ({ error: e.message }));
  }
  if (args.mode === "process_posts" || args.mode === "full_pipeline") {
    summary.posts = await processPosts(cfg, runId).catch((e) => ({ error: e.message }));
  }

  const completedAt = isoNow();
  const duration = Math.round((Date.parse(completedAt) - Date.parse(startedAt)) / 1000);

  // Telegram summary.
  const lines = [`📱 *Social Media* — ${cfg.tenant_name}`, `mode: \`${args.mode}\` · ${duration}s`];
  if (summary.ideas)   lines.push(`💡 ideas creadas: ${summary.ideas.created ?? 0}${summary.ideas.error ? ` (err: ${summary.ideas.error})` : ""}`);
  if (summary.visuals) lines.push(`🎨 visuales done: ${summary.visuals.processed ?? 0}/${summary.visuals.total ?? 0}${summary.visuals.error ? ` (err: ${summary.visuals.error})` : ""}`);
  if (summary.posts)   lines.push(`📅 posts programados: ${summary.posts.posted ?? 0}/${summary.posts.total ?? 0}${summary.posts.error ? ` (err: ${summary.posts.error})` : ""}`);
  await telegramSend(cfg, lines.join("\n"));

  console.error(`[social_media] done summary=${JSON.stringify(summary).slice(0, 300)}`);
}

main().catch((e) => { console.error("[social_media] FATAL:", e); process.exit(1); });
