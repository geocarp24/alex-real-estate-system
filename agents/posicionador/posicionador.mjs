#!/usr/bin/env node
/**
 * El Posicionador — SEO monitoring orchestrator.
 * Usage:
 *   node agents/posicionador/posicionador.mjs --tenant <slug> --mode seo_health|seo_deep|on_demand [--dry-run]
 *
 * Reads agents/tenants/<slug>.json, spawns claude CLI with SEO skills, parses output,
 * persists to Airtable SEO_Audits, sends Telegram summary.
 *
 * Same architecture as El Mercader. SaaS multi-tenant per R8, mobile-first per R7.
 */

import { readFile, mkdir, writeFile } from "node:fs/promises";
import { spawn } from "node:child_process";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { randomUUID } from "node:crypto";

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, "..", "..");
const TENANTS_DIR = join(ROOT, "agents", "tenants");
const OUTPUT_DIR = join(__dirname, "runs");

const VALID_MODES = ["seo_health", "seo_deep", "on_demand"];

function parseArgs(argv) {
  const args = { mode: "seo_health", dryRun: false, tenant: null };
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === "--tenant" || a === "-t") args.tenant = argv[++i];
    else if (a === "--mode" || a === "-m") args.mode = argv[++i];
    else if (a === "--dry-run") args.dryRun = true;
    else if (a === "--help" || a === "-h") {
      console.log(`Usage: posicionador.mjs --tenant <slug> --mode ${VALID_MODES.join("|")} [--dry-run]`);
      process.exit(0);
    }
  }
  if (!args.tenant) { console.error("ERROR: --tenant <slug> required"); process.exit(2); }
  if (!/^[a-z0-9_-]+$/.test(args.tenant)) { console.error("ERROR: tenant slug must match [a-z0-9_-]+"); process.exit(2); }
  if (!VALID_MODES.includes(args.mode)) { console.error(`ERROR: invalid --mode; must be one of ${VALID_MODES.join(", ")}`); process.exit(2); }
  return args;
}

async function loadTenant(slug) {
  const p = join(TENANTS_DIR, `${slug}.json`);
  const raw = await readFile(p, "utf8");
  const cfg = JSON.parse(raw);
  for (const k of ["tenant_id", "website", "claude"]) if (cfg[k] == null) throw new Error(`tenant.${k} missing in ${p}`);
  return cfg;
}

function runClaude(binary, prompt, timeoutMs = 20 * 60 * 1000) {
  return new Promise((resolve, reject) => {
    const child = spawn(binary, ["--print", "--permission-mode", "acceptEdits", prompt], { stdio: ["ignore", "pipe", "pipe"] });
    let out = "", err = "";
    const timer = setTimeout(() => { child.kill("SIGKILL"); reject(new Error("timeout")); }, timeoutMs);
    child.stdout.on("data", (d) => (out += d));
    child.stderr.on("data", (d) => (err += d));
    child.on("close", (code) => {
      clearTimeout(timer);
      if (code !== 0) return reject(new Error(`claude exit ${code}: ${err.slice(0, 500)}`));
      resolve(out);
    });
    child.on("error", (e) => { clearTimeout(timer); reject(e); });
  });
}

async function airtableUpsert(cfg, runId, fields) {
  // Use `seo_table_id` if defined on tenant config, else fall back to the shared `airtable.table_id`.
  const baseId = cfg.airtable?.base_id;
  const tableId = cfg.airtable?.seo_table_id || cfg.airtable?.table_id;
  const token = process.env[cfg.airtable?.token_env || "AIRTABLE_TOKEN"];
  if (!baseId || !tableId || !token) {
    console.error("[posicionador] airtable not configured (need base_id + seo_table_id + token); skipping write");
    return null;
  }
  const url = `https://api.airtable.com/v0/${baseId}/${tableId}`;
  const existing = await fetch(`${url}?filterByFormula=${encodeURIComponent(`{run_id}='${runId}'`)}&maxRecords=1`, {
    headers: { Authorization: `Bearer ${token}` },
  }).then(r => r.json()).catch(() => ({ records: [] }));

  if (existing.records && existing.records.length > 0) {
    const recId = existing.records[0].id;
    const r = await fetch(`${url}/${recId}`, {
      method: "PATCH",
      headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
      body: JSON.stringify({ fields, typecast: true }),
    });
    return await r.json();
  } else {
    const r = await fetch(url, {
      method: "POST",
      headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
      body: JSON.stringify({ fields: { run_id: runId, ...fields }, typecast: true }),
    });
    return await r.json();
  }
}

async function telegramSend(cfg, text) {
  const token = process.env[cfg.telegram?.bot_token_env || "TELEGRAM_BOT_TOKEN"];
  const chat  = process.env[cfg.telegram?.chat_id_env   || "TELEGRAM_CHAT_ID"];
  if (!token || !chat) { console.error("[posicionador] telegram not configured; skipping"); return; }
  try {
    await fetch(`https://api.telegram.org/bot${token}/sendMessage`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({ chat_id: chat, text, parse_mode: "Markdown" }).toString(),
    });
  } catch (e) { console.error("[posicionador] telegram error:", e.message); }
}

function extractBlock(text, header) {
  // Pull the first block after a heading that matches the given regex fragment.
  const re = new RegExp(`(?:${header})[^\\n]*\\n([\\s\\S]{1,2000}?)(?:\\n\\s*\\n|\\n#{1,3}\\s|$)`, "i");
  return (text.match(re) || [,""])[1].trim();
}
function extractScore(text, label) {
  // Accepts "Overall: 82/100", "Technical Score: 73", "Local score: 91", "Content: 85 / 100"
  const re = new RegExp(`${label}[^\\n]*?(?:score|:)[:\\s]+(\\d{1,3})(?:\\s*\\/\\s*100)?`, "i");
  const m = text.match(re);
  if (!m) return null;
  const n = Number.parseInt(m[1], 10);
  return Number.isFinite(n) ? Math.min(100, Math.max(0, n)) : null;
}

function parseAudit(text) {
  const overall   = extractScore(text, "(?:overall|global|total|seo)") ?? null;
  const technical = extractScore(text, "technical");
  const local     = extractScore(text, "local");
  const content   = extractScore(text, "(?:content|e-?e-?a-?t)");
  return {
    overall_score:   overall,
    technical_score: technical,
    local_score:     local,
    content_score:   content,
    top_issues:       extractBlock(text, "(?:top\\s+)?(?:critical\\s+)?issues?").slice(0, 1500),
    top_wins:         extractBlock(text, "(?:top\\s+)?(?:wins?|strengths?)").slice(0, 1500),
    recommendations:  extractBlock(text, "(?:recommendations?|next\\s+steps?|priority\\s+actions?)").slice(0, 2000),
    mobile_cwv:       extractBlock(text, "(?:core\\s+web\\s+vitals?|mobile\\s+cwv|LCP|CLS|INP)").slice(0, 800),
    local_ranks:      extractBlock(text, "(?:local\\s+ranks?|geo-?grid|ranking\\s+by\\s+city)").slice(0, 1500),
    competitor_gaps:  extractBlock(text, "(?:competitor\\s+gaps?|competitive\\s+position)").slice(0, 1500),
    schema_coverage:  extractBlock(text, "(?:schema(?:\\s+markup)?|structured\\s+data)").slice(0, 800),
  };
}

function buildPrompt(cfg, mode) {
  const site = cfg.website;
  const tenant = cfg.tenant_name;
  const cities = (cfg.markets || []).flatMap(m => m.cities || []).slice(0, 7);
  const citiesList = cities.length ? cities.join(", ") : "(none configured)";
  const competitors = (cfg.competitors || []).map(c => `- ${c.name}: ${c.url}`).join("\n") || "(none configured)";
  const industry = cfg.industry || "generic";

  if (mode === "seo_health") {
    return `You are El Posicionador, an always-on SEO monitoring sub-agent. Run a mobile-first SEO health check for tenant "${tenant}" on ${site}.

Use skill: /seo audit ${site}

Focus on:
- OVERALL SCORE: N/100 (weight mobile 60% / desktop 40%)
- Mobile Core Web Vitals (LCP, CLS, INP) with PASS/WARN/FAIL per metric
- Top 3 critical issues (prioritize mobile + local visibility)
- Top 3 wins
- 3 priority recommendations

Output: concise markdown with those sections. One line per bullet. Do NOT drift into deep analysis — this is a health check.`;
  }

  if (mode === "seo_deep") {
    return `You are El Posicionador, an always-on SEO monitoring sub-agent. Run a full mobile-first, local-priority SEO deep audit for tenant "${tenant}" (industry: ${industry}) on ${site}.

Markets: ${citiesList}
Competitors:
${competitors}

Run in sequence:
1) /seo audit ${site}            — overall score
2) /seo technical ${site}         — 9 categories (crawlability, indexability, rendering, schema, CWV mobile, etc.)
3) /seo local ${site}             — GBP, citations NAP, reviews velocity, local rank tracking
4) /seo maps ${site}              — geo-grid rank across these cities: ${citiesList}
5) /seo content ${site}           — E-E-A-T + AI citation readiness (GEO/AEO for AI Overviews, ChatGPT search, Perplexity)

Aggregate into a single client-ready report with this structure:

# ${tenant} — Weekly SEO Audit (${new Date().toISOString().slice(0,10)})

## Overall Score: N/100
## Technical Score: N/100
## Local Score: N/100
## Content Score: N/100

## Mobile Core Web Vitals
- LCP: <value> (PASS/WARN/FAIL)
- CLS: <value> (PASS/WARN/FAIL)
- INP: <value> (PASS/WARN/FAIL)

## Top Critical Issues
- ...

## Top Wins
- ...

## Priority Recommendations
- ...

## Local Ranks (by city)
- Milwaukee: position for "we buy houses milwaukee" / "sell my house fast milwaukee" / etc.
- Madison: ...

## Competitor Gaps
- Where competitors outrank us and on what queries.

## Schema Coverage
- Present / missing schema.org types.

Be specific and actionable. All scores 0-100. Mobile data weighted heavier.`;
  }

  // on_demand default
  const skills = (cfg.skills && cfg.skills.seo_deep) || ["seo-audit"];
  return `You are El Posicionador. Run on-demand SEO analysis for ${site} (tenant: ${tenant}). Skills: ${skills.join(", ")}. Produce markdown with Overall Score, Issues, Wins, Recommendations, Mobile CWV.`;
}

async function main() {
  const args = parseArgs(process.argv);
  const cfg = await loadTenant(args.tenant);
  const runId = randomUUID();
  const startedAt = new Date().toISOString();

  console.error(`[posicionador] tenant=${cfg.tenant_id} mode=${args.mode} run_id=${runId} dry_run=${args.dryRun}`);

  if (!args.dryRun) {
    await airtableUpsert(cfg, runId, {
      tenant_id: cfg.tenant_id,
      audit_type: args.mode,
      status: "Running",
      trigger: process.env.POSICIONADOR_TRIGGER || "alex_manual",
      started_at: startedAt,
    });
  }

  await mkdir(OUTPUT_DIR, { recursive: true });
  const prompt = buildPrompt(cfg, args.mode);

  if (args.dryRun) {
    console.log("=== DRY RUN — prompt that would be sent to claude ===");
    console.log(prompt);
    console.log("\n=== No subprocess, no Airtable, no Telegram. ===");
    return;
  }

  let claudeOut = "";
  try {
    claudeOut = await runClaude(cfg.claude.binary_path, prompt);
  } catch (e) {
    await airtableUpsert(cfg, runId, {
      status: "Failed",
      completed_at: new Date().toISOString(),
      summary_md: `Run failed: ${e.message}`,
    });
    await telegramSend(cfg, `❌ *El Posicionador — ${cfg.tenant_name}*\nmode: \`${args.mode}\`\nerror: \`${e.message}\``);
    process.exit(1);
  }

  const parsed = parseAudit(claudeOut);
  const completedAt = new Date().toISOString();
  const duration = Math.round((Date.parse(completedAt) - Date.parse(startedAt)) / 1000);

  const mdPath = join(OUTPUT_DIR, `${runId}.md`);
  await writeFile(mdPath, claudeOut, "utf8");

  await airtableUpsert(cfg, runId, {
    status: "Done",
    completed_at: completedAt,
    duration_sec: duration,
    ...parsed,
    summary_md: claudeOut.slice(0, 100000),
    report_url: mdPath,
  });

  // Alert emoji picker
  const thr = cfg.alert_thresholds || {};
  const score = parsed.overall_score;
  const emoji = score == null ? "🔍" : score < (thr.critical_score ?? 50) ? "🚨" : score < (thr.warn_score ?? 70) ? "⚠️" : "✅";

  const scoreLine = [
    score != null ? `Overall: *${score}/100*` : null,
    parsed.technical_score != null ? `Tech: ${parsed.technical_score}` : null,
    parsed.local_score != null ? `Local: ${parsed.local_score}` : null,
    parsed.content_score != null ? `Content: ${parsed.content_score}` : null,
  ].filter(Boolean).join(" · ");

  const head = `${emoji} *El Posicionador — ${cfg.tenant_name}*\nmode: \`${args.mode}\`\n${scoreLine}`;
  const body = [
    parsed.mobile_cwv ? `\n*Mobile CWV:*\n${parsed.mobile_cwv.split("\n").slice(0, 4).join("\n")}` : "",
    parsed.top_issues ? `\n*Top issues:*\n${parsed.top_issues.split("\n").slice(0, 5).join("\n")}` : "",
    parsed.recommendations ? `\n*Next:*\n${parsed.recommendations.split("\n").slice(0, 4).join("\n")}` : "",
  ].join("");

  await telegramSend(cfg, (head + body).slice(0, 3900));

  console.error(`[posicionador] done run_id=${runId} overall=${score} duration=${duration}s`);
}

main().catch((e) => { console.error("[posicionador] FATAL:", e); process.exit(1); });
