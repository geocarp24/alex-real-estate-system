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

## Task 1: Migrate Airtable secrets to Doppler

**Files:**
- Modify (manual, via Doppler dashboard): add 3 secrets to `pinnacle-social-publisher` / `dev_personal`

- [ ] **Step 1: Jefe adds 3 secrets to Doppler dashboard**

In `dashboard.doppler.com` → project `pinnacle-social-publisher` → config `dev_personal` → Add Secret (x3):

```
AIRTABLE_SM_TOKEN     = patSlNwngu7SJoa52.003c83df8f6e378af5309237e310a36568a037448709d94b10739d032f9e8ef7
AIRTABLE_SM_BASE_ID   = appU9s3kGkVpdrJkw
AIRTABLE_SM_TABLE_ID  = tblAj0Pkj1jW4p5Ld
```

(Values come from existing `agents/social_media.md`. After migration, remove them from that file.)

- [ ] **Step 2: Verify Doppler sees all 8 secrets**

```bash
doppler secrets --only-names --no-check-version 2>&1 | grep -E "^\s*(CLOUDINARY|GEMINI|REPLICATE|AIRTABLE)"
```

Expected: 8 rows listing CLOUDINARY_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET, GEMINI_API_KEY, REPLICATE_API_TOKEN, AIRTABLE_SM_TOKEN, AIRTABLE_SM_BASE_ID, AIRTABLE_SM_TABLE_ID.

- [ ] **Step 3: Remove plaintext secret from social_media.md**

Edit `agents/social_media.md` section "CREDENCIALES AIRTABLE" — replace the literal token with:

```
## CREDENCIALES AIRTABLE

Secrets en Doppler project `pinnacle-social-publisher` / config `dev_personal`:
- AIRTABLE_SM_TOKEN
- AIRTABLE_SM_BASE_ID  (appU9s3kGkVpdrJkw)
- AIRTABLE_SM_TABLE_ID (tblAj0Pkj1jW4p5Ld for Ideas de Contenido)

Ejecutar con: `doppler run -- node agents/creativo_v2/main.mjs`
```

- [ ] **Step 4: Commit**

```bash
git add agents/social_media.md
git -c commit.gpgsign=false commit -m "social_media: move Airtable token to Doppler"
```

---

