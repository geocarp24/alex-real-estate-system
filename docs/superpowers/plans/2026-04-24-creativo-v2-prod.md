# El Creativo v2 — Producción (Fase 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the POC orchestrator with a production flow that reads Airtable pending records, renders carousels, uploads to Cloudinary, and updates Airtable with the visual URLs — so El Creativo v2 runs autonomously end-to-end.

**Architecture:** Three new Node modules (`airtable.mjs`, `cloudinary.mjs`, `main.mjs`) bolt onto the existing Fase 1 renderer (`themes.mjs`, `wrapper.mjs`, `render.mjs`). Secrets via Doppler — migrate Airtable token + base/table IDs during Task 1. All I/O via native `fetch` + `node:crypto` — no new npm deps.

**Tech Stack:** Node 22, Puppeteer (already installed), native `fetch`, `node:crypto` for Cloudinary signatures, Doppler CLI for secrets injection, Node built-in `node --test` for TDD.

**Spec:** `docs/superpowers/specs/2026-04-24-creativo-v2-design.md` (sections 3, 4, 7, 8)
**Branch:** `claude/greeting-setup-yOfqf`
**Phase discipline:** This plan follows `agents/PROTOCOLO_EJECUCION.md` — each task is a single commit with tests before implementation.

---

## File Structure

**Created by this plan:**
```
agents/creativo_v2/
  src/
    airtable.mjs               Airtable REST client (list + parse + update)
    cloudinary.mjs             Cloudinary signed upload
  test/
    airtable.test.mjs          TDD tests for airtable module
    cloudinary.test.mjs        TDD tests for cloudinary module
    main.test.mjs              TDD tests for orchestrator helpers
  main.mjs                     production orchestrator (replaces poc.mjs at runtime)
  logs/                        JSONL run logs (.gitignored already)
```

**Modified by this plan:**
```
agents/creativo_v2/.gitignore  add output/ (stop tracking POC JPGs)
agents/creativo_v2/package.json add "prod" script for main.mjs via doppler run
agents/creativo_v2/README.md   document production run flow
```

**Not touched:**
- `src/themes.mjs`, `src/wrapper.mjs`, `src/render.mjs` (Fase 1 — stable)
- `poc.mjs` (kept for regression testing — NOT deleted)

---

## Schema Contract — Visual_Prompt field in Airtable

Social Media Agent writes **valid JSON** to the Visual_Prompt field matching this schema:

```json
{
  "theme": "T1",
  "hook": { "en": "...", "es": "...", "badge": "WISCONSIN" },
  "points": [
    { "headingEn": "...", "headingEs": "...", "bodyEn": "...", "bodyEs": "..." }
  ],
  "cta": { "en": "...", "es": "..." }
}
```

`parseVisualPrompt` tolerates markdown code-fence wrapping (```json ... ```). If parse fails, the record is marked `Status=Error` with `Error_Reason` — it does not block the batch.

**Migration note:** existing pending records (7 in `tblAj0Pkj1jW4p5Ld` per `memoria_social_media.md`) have Visual_Prompt in the old descriptive format. They must be either (a) updated to JSON format by the Social Media Agent, or (b) left alone — they will fail parsing and be flagged `Status=Error`. Not our job in this plan; Task 8 tests with one NEW record written in the correct format.

---

## Execution Discipline (Fase 4 of PROTOCOLO_EJECUCION)

- One task = one module = one commit
- TDD: test fails → implement → test passes → commit
- Never `Write` files larger than ~300 lines — split modules
- Never paste >400 lines in chat output — write to disk and report path only
- After each task: quick status report to Jefe
- After Task 8: ask for final approval before deploying to cron

---

*Tasks follow in Parts 2-9.*
