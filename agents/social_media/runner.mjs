#!/usr/bin/env node
/**
 * Social Media — full pipeline orchestrator (Pinnacle Holdings).
 *
 * Modes:
 *   generate_ideas    — Sonnet 4.6 generates N new ideas, stores in Airtable.
 *   process_posts     — for ideas with visual_url + Status=Visual Listo and no
 *                       Published_Post_IDs, schedule on FB + IG via Meta Graph API.
 *   full_pipeline     — generate_ideas → process_posts.
 *
 * Visual generation (was process_visuals via Blotato) is now handled by:
 *   • El Creativo runner (Puppeteer carousels)
 *   • El Director v2 runner (Reels via HeyGen + FLUX2)
 * Both have their own crons and read Airtable directly.
 *
 * Cron: every 3 days from agents-cron.yml. Each run aims to publish 1-3 posts.
 *
 * 2026-05-07: Blotato deprecated by Jorge — all publishing migrated to direct
 *             Meta Graph API. See `graph_api.mjs` for the publisher functions.
 */
import { parseArgs, loadTenant, telegramSend, genRunId, isoNow } from "../_shared/runner.mjs";
import {
  publishFacebookPhotoPost, publishFacebookReel,
  publishInstagramReel, publishInstagramCarousel, publishInstagramImage,
  getInstagramUserId, getPageAccessToken,
} from "./graph_api.mjs";

const VALID_MODES = ["generate_ideas", "process_posts", "full_pipeline"];

// ── Pinnacle SM Airtable (separate base from CRM) ──
const SM_BASE  = "appU9s3kGkVpdrJkw";
const SM_TABLE = "tblAj0Pkj1jW4p5Ld";  // Ideas de Contenido
const SM_TOKEN = process.env.SM_AIRTABLE_TOKEN
  || "patSlNwngu7SJoa52.003c83df8f6e378af5309237e310a36568a037448709d94b10739d032f9e8ef7";

// ── Meta Graph API config ──
// META_USER_TOKEN: long-lived User Access Token from "Pinnacle Social Publisher" app.
// META_PAGE_ACCESS_TOKEN (optional): pre-resolved Page token. If absent, derived from User token via /me/accounts.
const META_USER_TOKEN = process.env.META_USER_TOKEN || "";
const META_PAGE_TOKEN = process.env.META_PAGE_ACCESS_TOKEN || "";
const FB_PAGE_ID      = "965320503341457";  // Pinnacle Holdings Group

// ── Airtable field names (source of truth, kept in one place for easy rename) ──
// NOTE 2026-05-07: legacy field names "Blotato_*" still hold the data — Jorge will rename in Airtable UI.
//   Blotato_Visual_ID  → carousel slide URLs (pipe-separated, prefix `puppeteer:N_slides|`)
//   Blotato_Post_IDs   → published media IDs after FB/IG publish (`fb:<id>,ig:<id>`)
const FIELD_CAROUSEL_URLS      = "Blotato_Visual_ID";
const FIELD_PUBLISHED_POST_IDS = "Blotato_Post_IDs";

// ── Caps ──
const IDEAS_PER_RUN  = 3;
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
// Lessons learned from Oráculo (curated by Reescritor) — read at every run
// so SM Manager improves over time and Oráculo rejects less.
// Jorge 2026-05-07: "el oráculo va trabajando cada vez menos".
// ──────────────────────────────────────────────────────────────
async function loadLessons() {
  try {
    const { readFile } = await import("node:fs/promises");
    const { fileURLToPath } = await import("node:url");
    const { dirname, join } = await import("node:path");
    const __dirname = dirname(fileURLToPath(import.meta.url));
    const path = join(__dirname, "..", "oraculo_inputs", "sm_lessons.md");
    const text = await readFile(path, "utf8");
    // Truncate to last 4000 chars (most recent lessons matter most).
    return text.length > 4000 ? "..." + text.slice(-4000) : text;
  } catch { return ""; }
}

// ──────────────────────────────────────────────────────────────
// Mode 1 — Generate ideas (Anthropic)
// ──────────────────────────────────────────────────────────────
async function generateIdeas(cfg, runId) {
  const lessons = await loadLessons();
  const lessonsBlock = lessons
    ? `\n\n[LESSONS LEARNED FROM ORACULO — apply these on every idea you generate]\n${lessons}\n[END LESSONS]\n\nFollow the rewrite_pattern from each lesson above. Do NOT repeat any rejected_pattern.`
    : "";

  const systemPrompt = `You are the Social Media Agent for Pinnacle Holdings Group LLC, a real estate cash home buyer in Wisconsin. Owner: Jorge Cruz. Phone: (920) 777-9886. Web: pinnaclegroupwi.com.

You generate post ideas optimized for Instagram + Facebook. Audience: distressed homeowners (foreclosure, inherited property, divorce, back taxes, relocation). 70% educational, 20% promotional, 10% personal.

VIDEO LENGTH RULE (Jorge 2026-05-07 — non-negotiable): Reels MUST be 7-15 seconds — NEVER more. If a concept genuinely needs more time, split it into a SERIES across multiple Reel records: title "Topic — Parte 1", "Topic — Parte 2", etc. Each part max 15s. Set "tipo": "Educativo" with title prefix "Parte N — " when it's part of a series.${lessonsBlock}

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
// Mode 2 — Process posts (Meta Graph API publish to FB + IG)
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

// Parse `Blotato_Visual_ID` legacy field for carousel slide URLs.
// Two historical shapes:
//   modern Cloudinary:  "puppeteer:6_slides|<url1>|<url2>|..."
//   legacy Blotato:     "<id>|||<url1>|<url2>|..."
// Returns array of media URLs, or empty array if no slides parseable.
function parseCarouselSlides(raw) {
  if (!raw || typeof raw !== "string") return [];
  let body = raw;
  if (body.includes("|||")) body = body.split("|||")[1] || "";       // legacy Blotato format
  else if (body.startsWith("puppeteer:")) body = body.split("|").slice(1).join("|");
  const urls = body.split("|").map(s => s.trim()).filter(u => u.startsWith("http"));
  return urls;
}

function isVideo(url) {
  return /\.(mp4|mov|webm)(\?|$)/i.test(url || "");
}

async function processPosts(cfg, runId) {
  if (!META_USER_TOKEN && !META_PAGE_TOKEN) {
    return { posted: 0, reason: "META_USER_TOKEN / META_PAGE_ACCESS_TOKEN not configured — set in Doppler/secrets" };
  }

  // Safety layer (Jorge 2026-05-07 "si nos banean estamos acabados").
  // Lazy import to avoid breaking generate_ideas mode if safety.mjs is missing.
  const { safetyCheckBeforePublish, classifyError, alertTelegram, CURRENT_PHASE } = await import("./safety.mjs");
  console.error(`[social_media] safety phase=${CURRENT_PHASE}`);

  // Resolve Page Access Token (cached for the run).
  let pageToken = META_PAGE_TOKEN;
  if (!pageToken) {
    pageToken = await getPageAccessToken({ userAccessToken: META_USER_TOKEN, pageId: FB_PAGE_ID });
    if (!pageToken) return { posted: 0, reason: `Page ${FB_PAGE_ID} not found in /me/accounts — token may lack pages_show_list scope` };
  }

  // Resolve IG Business Account once (cached).
  const igUserId = await getInstagramUserId({ pageId: FB_PAGE_ID, pageAccessToken: pageToken }).catch(() => null);
  if (!igUserId) {
    console.error("[social_media] IG Business Account not linked to FB Page — IG publishing will be skipped");
  }

  // Find ideas with visual_url set, Status=Visual Listo, and no published IDs yet.
  const filter = encodeURIComponent(
    `AND({visual_url}!='', {Status}='Visual Listo', OR({${FIELD_PUBLISHED_POST_IDS}}='', NOT({${FIELD_PUBLISHED_POST_IDS}})))`
  );
  const r = await smFetch(`filterByFormula=${filter}&maxRecords=${POSTS_PER_RUN}`);
  const ideas = r.records || [];
  if (ideas.length === 0) return { posted: 0, reason: "no ideas with Visual Listo + missing published IDs" };

  const results = [];
  let slotOffset = 0;
  let halted = false;  // Set true when classifyError returns { action: 'halt' } — stops batch.
  for (const idea of ideas) {
    if (halted) {
      results.push({ id: idea.id, formato: idea.fields?.Formato, status: "halted_by_safety" });
      continue;
    }
    const f = idea.fields || {};
    const visualUrl = f.visual_url || "";
    const formato   = f.Formato || "";
    const captionEn = f["🇺🇸 Caption EN"] || "";
    const captionEs = f["🇲🇽 Caption ES"] || f["Mensaje Principal"] || "";
    const hashtags  = f.Hashtags || "";
    const caption   = `${captionEs}\n\n${captionEn}\n\n${hashtags}`.trim();
    const scheduledTime = Math.floor(new Date(nextSlotISO(slotOffset * 24)).getTime() / 1000);  // Unix seconds for Meta
    slotOffset++;

    // ── SAFETY GATE — caption audit + visual audit + rate budget ──
    const safety = await safetyCheckBeforePublish({
      caption, visualUrl, formato,
      durationSec: f.video_duration || 0,
      platform: "both",
      smFetch,
      fieldPublishedIds: FIELD_PUBLISHED_POST_IDS,
    });
    if (!safety.ok) {
      const reason = `safety blocked (${safety.blockReason}): ${(safety.details || []).join("; ")}`.slice(0, 500);
      console.error(`[social_media] ${idea.id} ${reason}`);
      await smUpdate(idea.id, { Error_Reason: reason }).catch(() => null);
      results.push({ id: idea.id, formato, status: "safety_blocked", reason: safety.blockReason });
      continue;
    }

    let fbResult = null, igResult = null, fbErr = null, igErr = null;

    try {
      if (formato === "Reel" || isVideo(visualUrl)) {
        fbResult = await publishFacebookReel({ pageId: FB_PAGE_ID, pageAccessToken: pageToken, videoUrl: visualUrl, caption, scheduledPublishTime: scheduledTime });
      } else if (formato === "Carrusel") {
        const slides = parseCarouselSlides(f[FIELD_CAROUSEL_URLS]);
        const imgs = slides.length >= 2 ? slides : [visualUrl];
        fbResult = await publishFacebookPhotoPost({ pageId: FB_PAGE_ID, pageAccessToken: pageToken, imageUrls: imgs, caption, scheduledPublishTime: scheduledTime });
      } else {
        // Post / Story → single image.
        fbResult = await publishFacebookPhotoPost({ pageId: FB_PAGE_ID, pageAccessToken: pageToken, imageUrls: [visualUrl], caption, scheduledPublishTime: scheduledTime });
      }
    } catch (e) {
      fbErr = e.message;
      const cls = classifyError(e);
      if (cls.alert) await alertTelegram(`FB publish error on ${idea.id}: ${e.message}`, 'WARN').catch(() => null);
      if (cls.action === 'halt') { halted = true; await alertTelegram(`HALT triggered: ${cls.reason}`, 'CRITICAL').catch(() => null); }
    }

    if (igUserId && !halted) {
      try {
        if (formato === "Reel" || isVideo(visualUrl)) {
          igResult = await publishInstagramReel({ igUserId, pageAccessToken: pageToken, videoUrl: visualUrl, caption });
        } else if (formato === "Carrusel") {
          const slides = parseCarouselSlides(f[FIELD_CAROUSEL_URLS]);
          if (slides.length >= 2) igResult = await publishInstagramCarousel({ igUserId, pageAccessToken: pageToken, imageUrls: slides, caption });
          else                    igResult = await publishInstagramImage({ igUserId, pageAccessToken: pageToken, imageUrl: visualUrl, caption });
        } else {
          igResult = await publishInstagramImage({ igUserId, pageAccessToken: pageToken, imageUrl: visualUrl, caption });
        }
      } catch (e) {
        igErr = e.message;
        const cls = classifyError(e);
        if (cls.alert) await alertTelegram(`IG publish error on ${idea.id}: ${e.message}`, 'WARN').catch(() => null);
        if (cls.action === 'halt') { halted = true; await alertTelegram(`HALT triggered: ${cls.reason}`, 'CRITICAL').catch(() => null); }
      }
    }

    const fbId = fbResult?.id || fbResult?.video_id || null;
    const igId = igResult?.media_id || igResult?.id || null;
    const ids = [fbId && `fb:${fbId}`, igId && `ig:${igId}`].filter(Boolean).join(",");

    await smUpdate(idea.id, {
      [FIELD_PUBLISHED_POST_IDS]: ids,
      "Status": ids ? "Programado" : "Error",
      ...(fbErr || igErr ? { "Error_Reason": [fbErr && `FB: ${fbErr}`, igErr && `IG: ${igErr}`].filter(Boolean).join(" | ").slice(0, 500) } : {}),
    }).catch(() => null);

    results.push({ id: idea.id, formato, status: ids ? "scheduled" : "failed", fb: fbId, ig: igId, fbErr, igErr, when: scheduledTime });
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
    console.log(`Would call Anthropic + Meta Graph API + Airtable SM (${SM_BASE}/${SM_TABLE}).`);
    console.log(`Caps: ideas=${IDEAS_PER_RUN}, posts=${POSTS_PER_RUN}.`);
    return;
  }

  if (!META_USER_TOKEN && !META_PAGE_TOKEN && args.mode !== "generate_ideas") {
    console.error("[social_media] META_USER_TOKEN / META_PAGE_ACCESS_TOKEN missing — posts will skip");
  }

  const summary = {};
  if (args.mode === "generate_ideas" || args.mode === "full_pipeline") {
    summary.ideas = await generateIdeas(cfg, runId).catch((e) => ({ error: e.message }));
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
