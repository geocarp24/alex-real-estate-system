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

## UI/Frontend Craft (Jorge 2026-05-02 — non-negotiable, all sub-agents)
Every sub-agent that touches UI (HTML/CSS/JSX/TSX/Vue/Svelte/popups in PHP/WordPress templates/email HTML/Telegram markdown reports for humans) MUST invoke these skills:
- **impeccable** (`pbakaus/impeccable`) — production-grade frontend craft, UX review, visual hierarchy, a11y, motion, micro-interactions, design tokens.
- **emil-design-eng** (`emilkowalski/skill`) — taste, polish, animation decisions, invisible details.

Activation rules:
- New UI mockup/component: invoke `impeccable` first, then `emil-design-eng` for taste validation.
- UI audit/review: invoke `impeccable` (use Before/After table format).
- Animations/transitions/polish: invoke `emil-design-eng`.
- Stack with existing rules: composes with `responsive-design`, `mobile-ios-design`, `accessibility-compliance` — additive, not replacement.

Backend-only agents (Mercader, Posicionador, Cazador, Clasificador, Analista, Espia, Auditor, Remitente, Supervisor, Creativo runner, Director runner) do NOT need these skills unless they output human-readable formatted reports (then invoke `impeccable` for legibility review).

Anti-regression: any UI proposal that does NOT cite `impeccable` or `emil-design-eng` is rejected. See root `CLAUDE.md` rule 1e.
