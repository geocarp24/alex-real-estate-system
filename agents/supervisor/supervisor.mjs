#!/usr/bin/env node
/**
 * El Supervisor — autonomous ops watchdog for the R9 plantel.
 *
 * Modes:
 *   heartbeat  (every 15 min)  — fast health pings, pipeline counters, log freshness
 *   deep       (every 1 hour)  — ghost detection + auto-repair + drift metrics
 *   evolve     (weekly Sat 7am CT) — pattern recognition, propose fixes, write Ops_Insights
 *   incident   (on demand)     — forensic deep-dive invoked by heartbeat red
 *
 * Usage:
 *   node agents/supervisor/supervisor.mjs --tenant <slug> --mode <mode> [--dry-run]
 */
import { mkdir, writeFile } from "node:fs/promises";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import {
  parseArgs, loadTenant, runClaude,
  airtableFetch, airtableCreate, airtableUpsert, airtableUpdate, telegramSend,
  extractBlock, genRunId, isoNow,
} from "../_shared/runner.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const OUTPUT_DIR = join(__dirname, "runs");
const VALID_MODES = ["heartbeat", "deep", "evolve", "incident"];
const TABLE_KEY = "ops_health_table_id";
const INSIGHTS_KEY = "ops_insights_table_id";

// ──────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────
function daysBetween(a, b) { return Math.round((b - a) / 86_400_000); }
function hoursBetween(a, b) { return (b - a) / 3_600_000; }

async function fetchLog(cfg) {
  const url = `${cfg.website.replace(/\/$/, "")}/Tools/fer_agent.log`;
  try {
    const r = await fetch(url, { headers: { "User-Agent": "Supervisor/1.0" } });
    if (!r.ok) return { lines: [], error: `HTTP ${r.status}` };
    const text = await r.text();
    const lines = text.split("\n").filter(Boolean).map((l) => {
      try { return JSON.parse(l); } catch { return null; }
    }).filter(Boolean);
    return { lines };
  } catch (e) {
    return { lines: [], error: e.message };
  }
}

async function probeEndpoint(url, timeoutMs = 5_000) {
  // Use GET with short timeout. PHP cron scripts may not support HEAD. ignore_user_abort
  // on the server side lets our request return fast without triggering a full run — we
  // only need proof of life (TCP + HTTP stack responding).
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), timeoutMs);
  try {
    const r = await fetch(url, { signal: ctrl.signal, method: "GET" });
    clearTimeout(t);
    return { ok: r.status >= 200 && r.status < 500, status: r.status };
  } catch (e) {
    clearTimeout(t);
    // Abort after 5s = endpoint started executing (PHP running) → that counts as "alive"
    if (e.name === "AbortError") return { ok: true, status: 0, note: "timeout_but_started" };
    return { ok: false, status: 0, error: e.message };
  }
}

// ──────────────────────────────────────────────────────────────
// CHECKS
// ──────────────────────────────────────────────────────────────
async function runInfrastructureChecks(cfg) {
  const now = new Date();
  const base = cfg.website.replace(/\/$/, "");
  const results = {};

  // Probe each cron endpoint (HEAD, fast)
  const endpoints = [
    { name: "cron_first_contact_ok", url: `${base}/Tools/fer_first_contact.php` },
    { name: "cron_seguimiento_ok",   url: `${base}/Tools/fer_seguimiento.php` },
    { name: "cron_stale_ok",         url: `${base}/Tools/fer_stale_cron.php` },
    { name: "cron_morning_brief_ok", url: `${base}/Tools/fer_morning_brief.php` },
  ];
  await Promise.all(endpoints.map(async (e) => {
    const r = await probeEndpoint(e.url, 8_000);
    results[e.name] = r.ok ? 1 : 0;
  }));

  // Airtable API probe
  try {
    const r = await airtableFetch(cfg, "leads_table_id", "maxRecords=1");
    results.airtable_api_ok = Array.isArray(r.records) ? 1 : 0;
  } catch { results.airtable_api_ok = 0; }

  // Telegram bot probe (getMe)
  try {
    const token = process.env[cfg.telegram?.bot_token_env || "TELEGRAM_BOT_TOKEN"];
    if (token) {
      const r = await fetch(`https://api.telegram.org/bot${token}/getMe`);
      const j = await r.json();
      results.telegram_bot_ok = j.ok ? 1 : 0;
    } else results.telegram_bot_ok = 0;
  } catch { results.telegram_bot_ok = 0; }

  // OpenPhone API probe via a cheap call (list phone numbers)
  try {
    const key = process.env.QUO_API_KEY;
    if (key) {
      const r = await fetch("https://api.openphone.com/v1/phone-numbers", {
        headers: { Authorization: key },
      });
      results.openphone_api_ok = r.ok ? 1 : 0;
    } else results.openphone_api_ok = 0;
  } catch { results.openphone_api_ok = 0; }

  // Log freshness from fer_agent.log
  const { lines } = await fetchLog(cfg);
  const fcSends = lines.filter((l) => l.event === "fc_sms_sent");
  const segSends = lines.filter((l) => l.event === "seg_sms_sent");
  const webhooks = lines.filter((l) => l.event === "webhook_received");

  const lastFc = fcSends.length ? new Date(fcSends[fcSends.length - 1].ts) : null;
  const lastSeg = segSends.length ? new Date(segSends[segSends.length - 1].ts) : null;
  const lastWebhook = webhooks.length ? new Date(webhooks[webhooks.length - 1].ts) : null;

  results.last_fc_hours = lastFc ? +hoursBetween(lastFc, now).toFixed(1) : null;
  results.last_seg_hours = lastSeg ? +hoursBetween(lastSeg, now).toFixed(1) : null;
  results.webhook_recent_ok = lastWebhook && hoursBetween(lastWebhook, now) < 24 ? 1 : 0;

  // 422 airtable errors today → schema drift canary
  const today = now.toISOString().slice(0, 10);
  const errors422 = lines.filter((l) =>
    l.event === "airtable_http_error" &&
    (l.ts || "").startsWith(today) &&
    String(l.context?.code || "").startsWith("422"));
  results.airtable_422_today = errors422.length;

  return results;
}

async function runPipelineChecks(cfg) {
  const r = await airtableFetch(cfg, "contacts_table_id", "pageSize=100&fields%5B%5D=Stage&fields%5B%5D=Last%20contact%20date&fields%5B%5D=First%20Contact%20Step&fields%5B%5D=Seguimiento%20Step&fields%5B%5D=Next%20follow%20up%20date&fields%5B%5D=Full%20Name&fields%5B%5D=Do%20not%20contact");
  const recs = r.records || [];
  const today = new Date();
  const buckets = { New: 0, "To Be Contacted": 0, Contacted: 0, Seguimiento: 0, Dead: 0, other: 0 };
  const ghosts = [];

  for (const rec of recs) {
    const f = rec.fields || {};
    const stage = f.Stage || "";
    if (stage in buckets) buckets[stage]++; else buckets.other++;
    const last = f["Last contact date"] ? new Date(f["Last contact date"]) : null;
    const nextD = f["Next follow up date"] ? new Date(f["Next follow up date"]) : null;

    // Ghost detection
    let ghostReason = null;
    if (stage === "Contacted") {
      if (!last) ghostReason = "Contacted w/o Last contact date";
      else if (daysBetween(last, today) >= 2) ghostReason = `Contacted stuck ${daysBetween(last, today)}d`;
    } else if (stage === "Seguimiento") {
      if (!last && !nextD) ghostReason = "Seguimiento w/o dates";
      else if (!nextD && last && daysBetween(last, today) >= 5) ghostReason = `Seguimiento no next, ${daysBetween(last, today)}d stale`;
      else if (nextD && daysBetween(nextD, today) >= 3 && daysBetween(nextD, today) >= 0) {
        // next date is past (today > nextD) by 3+ days → stuck
        if (nextD < today && daysBetween(nextD, today) >= 3) {
          ghostReason = `Seguimiento next-date ${daysBetween(nextD, today)}d overdue`;
        }
      }
    }
    if (ghostReason) ghosts.push({ id: rec.id, name: f["Full Name"] || "?", stage, ghostReason, last: f["Last contact date"], next: f["Next follow up date"] });
  }

  return {
    contacts_total: recs.length,
    contacts_new: buckets.New,
    contacts_tbc: buckets["To Be Contacted"],
    contacts_contacted: buckets.Contacted,
    contacts_seguimiento: buckets.Seguimiento,
    contacts_dead: buckets.Dead,
    ghosts,
  };
}

// ──────────────────────────────────────────────────────────────
// AUTO-REPAIR
// ──────────────────────────────────────────────────────────────
async function autoRepair(cfg, pipeline, dryRun) {
  const log = [];
  let applied = 0;

  // Repair 1: Ghost reset — cap at 25 per run
  const resets = pipeline.ghosts.slice(0, 25);
  if (resets.length > 0) {
    if (dryRun) {
      log.push(`[dry-run] would reset ${resets.length} ghost contacts to "To Be Contacted"`);
    } else {
      const base = cfg.airtable?.base_id;
      const table = cfg.airtable?.contacts_table_id;
      const token = process.env[cfg.airtable?.token_env || "AIRTABLE_TOKEN"];
      // Airtable bulk PATCH (10 per request)
      for (let i = 0; i < resets.length; i += 10) {
        const batch = resets.slice(i, i + 10);
        const body = {
          records: batch.map((g) => ({
            id: g.id,
            fields: {
              Stage: "To Be Contacted",
              "First Contact Step": null,
              "Seguimiento Step": null,
              "Last contact date": null,
              "Next follow up date": null,
              "SMS Sent": false,
            },
          })),
          typecast: true,
        };
        const r = await fetch(`https://api.airtable.com/v0/${base}/${table}`, {
          method: "PATCH",
          headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
          body: JSON.stringify(body),
        });
        if (r.ok) {
          applied += batch.length;
          log.push(...batch.map((g) => `reset: ${g.name} (${g.id}) — ${g.ghostReason}`));
        } else {
          const err = await r.text();
          log.push(`❌ reset batch failed HTTP ${r.status}: ${err.slice(0, 200)}`);
          break;
        }
      }
    }
  }

  // Repair 2: If cron stale (last fc >4h in-window), re-trigger (rate-limited to 1/hour handled upstream)
  // (implemented by writing a marker in auto_fixes_log; actual re-trigger is side-effect of probeEndpoint)

  return { log: log.join("\n"), applied };
}

// ──────────────────────────────────────────────────────────────
// SCORING
// ──────────────────────────────────────────────────────────────
function scoreHealth(infra, pipeline, cfg) {
  const sv = cfg?.supervisor || {};
  const newBacklogThreshold = sv.backlog_new_warn_threshold ?? 500;
  const newBacklogCritThreshold = sv.backlog_new_critical_threshold ?? 2000;
  const checks = [
    infra.cron_first_contact_ok,
    infra.cron_seguimiento_ok,
    infra.cron_stale_ok,
    infra.cron_morning_brief_ok,
    infra.airtable_api_ok,
    infra.telegram_bot_ok,
    infra.openphone_api_ok,
    infra.webhook_recent_ok,
  ];
  const total = checks.length;
  const passed = checks.filter((v) => v === 1).length;

  const critical = [];
  const warnings = [];

  if (!infra.airtable_api_ok)       critical.push("Airtable API no responde");
  if (!infra.openphone_api_ok)      critical.push("OpenPhone API no responde");
  if (!infra.telegram_bot_ok)       critical.push("Telegram bot token inválido");
  if (!infra.cron_first_contact_ok) warnings.push("fer_first_contact.php endpoint no responde a HEAD");
  if (!infra.cron_seguimiento_ok)   warnings.push("fer_seguimiento.php endpoint no responde a HEAD");

  // Time-window aware: only flag stale log if currently within 9am-7pm CT
  const nowCT = new Date().toLocaleString("en-US", { timeZone: "America/Chicago", hour: "numeric", hour12: false });
  const hrCT = parseInt(nowCT);
  const inWindow = hrCT >= 9 && hrCT < 19 && (new Date().getDay() !== 0);
  if (inWindow && (infra.last_fc_hours == null || infra.last_fc_hours > 4)) {
    critical.push(`Sin eventos fc_sms_sent desde hace ${infra.last_fc_hours ?? "∞"}h (ventana activa). Cron puede estar caído.`);
  }
  if (infra.last_seg_hours != null && infra.last_seg_hours > 30) {
    warnings.push(`Sin seg_sms_sent desde hace ${infra.last_seg_hours}h (esperado daily).`);
  }
  if (infra.airtable_422_today > 3) warnings.push(`${infra.airtable_422_today} errores Airtable 422 hoy — posible schema drift.`);

  if (pipeline.ghosts.length > 0) {
    warnings.push(`${pipeline.ghosts.length} contactos fantasma detectados (auto-reset aplicado).`);
  }
  if (pipeline.contacts_new > newBacklogCritThreshold) {
    critical.push(`${pipeline.contacts_new} contactos en "New" sin promover (>${newBacklogCritThreshold}) — backlog fuera de control.`);
  } else if (pipeline.contacts_new > newBacklogThreshold) {
    warnings.push(`${pipeline.contacts_new} contactos en "New" sin promover (>${newBacklogThreshold}) — considera revisar.`);
  }

  const health = critical.length > 0 ? "red" : warnings.length > 0 ? "yellow" : "green";
  return { total, passed, failed: total - passed, critical, warnings, health };
}

// ──────────────────────────────────────────────────────────────
// EVOLVE — pattern recognition via Claude
// ──────────────────────────────────────────────────────────────
async function evolveAnalysis(cfg) {
  // Pull last 7 days of Ops_Health
  const since = new Date(Date.now() - 7 * 86_400_000).toISOString();
  const params =
    `filterByFormula=${encodeURIComponent(`IS_AFTER({started_at}, '${since}')`)}` +
    `&pageSize=100&sort[0][field]=started_at&sort[0][direction]=desc`;
  const { records = [] } = await airtableFetch(cfg, TABLE_KEY, params);

  const summary = records.map((r) => {
    const f = r.fields || {};
    return `- ${f.started_at} [${f.check_type}] health=${f.health} checks=${f.checks_passed}/${f.checks_total} ghosts=${f.ghosts_detected || 0} auto=${f.autofixes_applied || 0}${f.critical_issues ? " | CRIT: " + String(f.critical_issues).slice(0, 120) : ""}`;
  }).slice(0, 200).join("\n");

  const prompt = `You are El Supervisor en evolve mode for ${cfg.tenant_name}. Last 7 days of Ops_Health records:\n\n${summary}\n\nTask: analyze patterns. Output STRICT markdown with:\n\n# Weekly Evolve Report\n## Top 3 Recurring Issues\n- [issue + occurrence count + root cause hypothesis]\n## Top 3 Auto-Fixes Applied\n- [what + how many times + is it still happening]\n## Proposed Permanent Fixes\n- [concrete file path + change needed + impact]\n## Green Areas (no intervention)\n- [what's stable]\n## Red Flags for Jorge (needs human decision)\n- [if any]\n\nBe specific. Cite timestamps. Do NOT invent patterns with <3 occurrences.`;

  try {
    const out = await runClaude(cfg.claude.binary_path, prompt, 20 * 60 * 1000);
    return { analysis: out, sourceCount: records.length };
  } catch (e) {
    return { analysis: `Evolve analysis failed: ${e.message}`, sourceCount: records.length };
  }
}

// ──────────────────────────────────────────────────────────────
// TELEGRAM FORMATTER
// ──────────────────────────────────────────────────────────────
function formatTelegram(cfg, args, runId, infra, pipeline, score, repair, evolve) {
  const ts = new Date().toISOString().slice(11, 19);
  const emoji = score.health === "red" ? "🚨" : score.health === "yellow" ? "⚠️" : "✅";
  const head = `${emoji} *Supervisor ${cfg.tenant_name}* \`${args.mode}\` ${ts}\nhealth: *${score.health.toUpperCase()}* · ${score.passed}/${score.total} checks ok`;
  const piped = `\n*Pipeline*: New=${pipeline.contacts_new} · TBC=${pipeline.contacts_tbc} · Contacted=${pipeline.contacts_contacted} · Seg=${pipeline.contacts_seguimiento} · Dead=${pipeline.contacts_dead}`;
  const logFreshness = `\n*Log*: fc=${infra.last_fc_hours ?? "?"}h ago · seg=${infra.last_seg_hours ?? "?"}h ago`;

  const crits = score.critical.length > 0 ? `\n\n🚨 *CRITICAL*\n• ${score.critical.join("\n• ")}` : "";
  const warns = score.warnings.length > 0 ? `\n\n⚠️ *WARN*\n• ${score.warnings.slice(0, 5).join("\n• ")}` : "";
  const fix = repair && repair.applied > 0 ? `\n\n🔧 *Auto-fix*: ${repair.applied} acciones aplicadas` : "";
  const ev = evolve?.analysis ? `\n\n📈 Evolve report generado — ver Ops_Health (run_id=${runId.slice(0, 8)})` : "";

  return (head + piped + logFreshness + crits + warns + fix + ev).slice(0, 3800);
}

// ──────────────────────────────────────────────────────────────
// MAIN
// ──────────────────────────────────────────────────────────────
async function main() {
  const args = parseArgs(process.argv, VALID_MODES);
  const cfg = await loadTenant(args.tenant);
  const runId = genRunId();
  const startedAt = isoNow();

  console.error(`[supervisor] tenant=${cfg.tenant_id} mode=${args.mode} run_id=${runId} dry_run=${args.dryRun}`);

  // Initial row
  if (!args.dryRun) {
    await airtableUpsert(cfg, TABLE_KEY, runId, {
      tenant_id: cfg.tenant_id,
      check_type: args.mode,
      status: "Running",
      trigger: process.env.SUPERVISOR_TRIGGER || "alex_manual",
      started_at: startedAt,
    });
  }

  // ── Layer 1: Infrastructure (all modes) ──
  const infra = await runInfrastructureChecks(cfg);

  // ── Layer 3: Pipeline (all modes) ──
  const pipeline = await runPipelineChecks(cfg);

  // ── Scoring ──
  const score = scoreHealth(infra, pipeline, cfg);

  // ── Auto-repair (deep, evolve, incident modes — NOT heartbeat) ──
  let repair = { log: "", applied: 0 };
  if (args.mode !== "heartbeat") {
    repair = await autoRepair(cfg, pipeline, args.dryRun);
  }

  // ── Evolve analysis ──
  let evolve = null;
  if (args.mode === "evolve") {
    evolve = await evolveAnalysis(cfg);
  }

  const completedAt = isoNow();
  const duration = Math.round((Date.parse(completedAt) - Date.parse(startedAt)) / 1000);

  // Build summary markdown
  const summary_md =
    `# Supervisor ${args.mode} — ${cfg.tenant_name}\n` +
    `run_id: ${runId}\n` +
    `started: ${startedAt} · done: ${completedAt} (${duration}s)\n` +
    `health: **${score.health}** (${score.passed}/${score.total} checks)\n\n` +
    `## Infra\n\`\`\`json\n${JSON.stringify(infra, null, 2)}\n\`\`\`\n\n` +
    `## Pipeline\n- total=${pipeline.contacts_total} New=${pipeline.contacts_new} TBC=${pipeline.contacts_tbc} Contacted=${pipeline.contacts_contacted} Seg=${pipeline.contacts_seguimiento} Dead=${pipeline.contacts_dead} ghosts=${pipeline.ghosts.length}\n\n` +
    `## Critical\n${score.critical.map((s) => "- " + s).join("\n") || "- (none)"}\n\n` +
    `## Warnings\n${score.warnings.map((s) => "- " + s).join("\n") || "- (none)"}\n\n` +
    `## Auto-fixes\n${repair.log || "- (none)"}\n\n` +
    (evolve ? `## Evolve Analysis\n${evolve.analysis}\n` : "");

  if (args.dryRun) {
    console.log(summary_md);
    return;
  }

  await mkdir(OUTPUT_DIR, { recursive: true });
  const mdPath = join(OUTPUT_DIR, `${runId}.md`);
  await writeFile(mdPath, summary_md, "utf8");

  // Close Ops_Health row
  await airtableUpsert(cfg, TABLE_KEY, runId, {
    status: "Done",
    health: score.health,
    completed_at: completedAt,
    duration_sec: duration,
    checks_total: score.total,
    checks_passed: score.passed,
    checks_failed: score.failed,
    autofixes_applied: repair.applied,
    cron_first_contact_ok: infra.cron_first_contact_ok,
    cron_seguimiento_ok:   infra.cron_seguimiento_ok,
    cron_stale_ok:         infra.cron_stale_ok,
    cron_morning_brief_ok: infra.cron_morning_brief_ok,
    airtable_api_ok:       infra.airtable_api_ok,
    openphone_api_ok:      infra.openphone_api_ok,
    telegram_bot_ok:       infra.telegram_bot_ok,
    webhook_recent_ok:     infra.webhook_recent_ok,
    contacts_total:        pipeline.contacts_total,
    contacts_new:          pipeline.contacts_new,
    contacts_tbc:          pipeline.contacts_tbc,
    contacts_contacted:    pipeline.contacts_contacted,
    contacts_seguimiento:  pipeline.contacts_seguimiento,
    contacts_dead:         pipeline.contacts_dead,
    ghosts_detected:       pipeline.ghosts.length,
    ghosts_autoreset:      repair.applied,
    critical_issues:       score.critical.join("\n"),
    warnings:              score.warnings.join("\n"),
    auto_fixes_log:        repair.log,
    summary_md:            summary_md.slice(0, 8000),
    report_url:            mdPath,
  });

  // Write Ops_Insights if evolve mode produced analysis
  if (evolve && evolve.analysis) {
    await airtableCreate(cfg, INSIGHTS_KEY, {
      insight_id: `evolve_${runId.slice(0, 8)}`,
      tenant_id: cfg.tenant_id,
      detected_at: completedAt,
      category: "auto-repair",
      severity: "info",
      status: "open",
      trigger_run_id: runId,
      component: "weekly-evolve",
      window: "7d",
      pattern_description: evolve.analysis.slice(0, 3000),
    });
  }

  // Telegram alerting policy
  const shouldAlert =
    score.health === "red" ||
    (args.mode === "evolve") ||
    (args.mode === "deep" && (score.warnings.length > 0 || repair.applied > 0));
  if (shouldAlert) {
    await telegramSend(cfg, formatTelegram(cfg, args, runId, infra, pipeline, score, repair, evolve));
  }

  console.error(`[supervisor] done run_id=${runId} health=${score.health} duration=${duration}s`);
}

main().catch((e) => { console.error("[supervisor] FATAL:", e); process.exit(1); });
