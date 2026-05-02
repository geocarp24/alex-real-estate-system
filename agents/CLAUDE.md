# CLAUDE.md - Agents Profile
# Best for: automation pipelines, multi-agent systems, bots, scheduled tasks
# Extends: Universal CLAUDE.md rules

---

## Output
- Structured output only: JSON, bullets, tables.
- No prose unless the downstream consumer is a human reader.
- Every output must be parseable without post-processing.

## Agent Behavior
- Execute the task. Do not narrate what you are doing.
- No status updates like "Now I will..." or "I have completed..."
- No asking for confirmation on clearly defined tasks. Use defaults.
- If a step fails: state what failed, why, and what was attempted. Stop.

## Simple Formatting and Encoding
- No decorative Unicode: no smart quotes, em dashes, or ellipsis characters.
- Natural language characters (accented letters, CJK, etc.) are fine when the content requires them.
- All strings must be safe for JSON serialization.

## Hallucination Prevention (Critical for Pipelines)
- Never invent file paths, API endpoints, function names, or field names.
- If a value is unknown: return null or "UNKNOWN". Never guess.
- If a file or resource was not read: do not reference its contents.
- Downstream systems break on hallucinated values. Accuracy over completeness.

## Token Efficiency
- Pipeline calls compound. Every token saved per call multiplies across runs.
- No explanatory text in agent output unless a human will read it.
- Return the minimum viable output that satisfies the task spec.

## UI/Frontend Craft — Design Taste Suite (Jorge 2026-05-02 — non-negotiable, all sub-agents)
Every sub-agent that touches UI (HTML/CSS/JSX/TSX/Vue/Svelte/popups in PHP/WordPress templates/email HTML/MJML/Telegram markdown reports for humans/mockups) MUST invoke the matching skills from the Design Taste Suite (14 skills installed globally at `~/.agents/skills/`):

Core (always relevant):
- `impeccable` (pbakaus) — production frontend craft
- `emil-design-eng` (emilkowalski) — taste + polish
- `design-taste-frontend` (leonxlnx) — Senior UI/UX engineer rules
- `full-output-enforcement` (leonxlnx) — anti-truncation

Style/aesthetic (pick by context):
- `minimalist-ui` — editorial monochrome (default for Pinnacle homeowner-facing)
- `high-end-visual-design` — premium agency look
- `industrial-brutalist-ui` — data-heavy / portfolios
- `gpt-taste` — GSAP motion + AIDA structure

Workflow-specific:
- `redesign-existing-projects` — upgrades existing UI without breaking
- `image-to-code` — visual-first implementation
- `imagegen-frontend-web` / `imagegen-frontend-mobile` — mockups
- `brandkit` — brand guidelines
- `stitch-design-taste` — DESIGN.md generation

Activation rules:
- New UI / component: `impeccable` + `design-taste-frontend` + `emil-design-eng` (default `minimalist-ui` for Pinnacle).
- UI audit/review: `impeccable` (Before/After table) + `redesign-existing-projects`.
- Animations/scroll/motion: `emil-design-eng` + `gpt-taste` (GSAP).
- Mockups/wireframes: `image-to-code` + `imagegen-frontend-web` or `imagegen-frontend-mobile`.
- Long output that risks truncation: add `full-output-enforcement`.
- Stacks WITH existing rules: `responsive-design`, `mobile-ios-design`, `accessibility-compliance`, `senior-frontend` — additive.

Default Pinnacle aesthetic (homeowner-facing, NOT tech audience): editorial + warmth. Primary skills: `minimalist-ui` + `high-end-visual-design` + `impeccable`.

Backend-only agents (Mercader, Posicionador, Cazador, Clasificador, Analista, Espia, Auditor, Remitente, Supervisor, Creativo runner, Director runner) do NOT need these skills unless they output human-readable formatted reports (then invoke `impeccable` + `full-output-enforcement`).

Anti-regression: any UI proposal that does NOT cite at least one Design Taste Suite skill is rejected. See root `CLAUDE.md` rule 1e.
