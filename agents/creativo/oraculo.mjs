/**
 * oraculo.mjs — Loads Pinnacle marketing context (persona + brand copy) once
 * per process and exposes it to the Creativo Sonnet pipeline so generated
 * Hook/Caption/CTA copy stays consistent with the audience persona and
 * brand voice already documented in `agents/oraculo_inputs/`.
 *
 * Per CLAUDE.md regla §5 (memoria always-on) and Jorge 2026-05-07 directive
 * "consultar al oráculo antes de ejecutar imágenes". Resource-optimized:
 *   - Files read ONCE on first import (cache stays for full process)
 *   - Truncated to ~2000 chars each to avoid bloating Sonnet token usage
 *   - If files missing, returns empty digest (graceful degrade)
 */

import { readFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const ORACULO_DIR = join(__dirname, "..", "oraculo_inputs");

const PERSONA_FILE = join(ORACULO_DIR, "wi_homeowner_persona.md");
const COPY_FILE    = join(ORACULO_DIR, "popup_copy.md");

let _digestCache = null;

async function safeRead(path, max = 2000) {
  try {
    const content = await readFile(path, "utf8");
    return content.slice(0, max);
  } catch {
    return "";
  }
}

/**
 * Returns a compact digest combining persona + brand copy guidelines, ready
 * to be injected into a Sonnet system prompt. Cached after first call.
 */
export async function loadOraculoDigest() {
  if (_digestCache !== null) return _digestCache;

  const [persona, copy] = await Promise.all([
    safeRead(PERSONA_FILE),
    safeRead(COPY_FILE),
  ]);

  if (!persona && !copy) {
    _digestCache = "";
    return _digestCache;
  }

  _digestCache = [
    "─── ORACULO BRIEF (audience + brand voice — read before generating copy) ───",
    persona ? `[AUDIENCE PERSONA]\n${persona}` : "",
    copy    ? `[BRAND VOICE / COPY GUIDELINES]\n${copy}` : "",
    "─── END ORACULO BRIEF ───",
    "",
    "Apply this context to every Hook, Caption, and CTA you write:",
    "- Audience is distressed Wisconsin homeowners (foreclosure / inheritance / divorce / back taxes / tired landlord), age 45-70, lower-middle income.",
    "- Tone: warm, no pressure, no salesy hype, no investor jargon.",
    "- Bilingual EN/ES — Spanish must be ortographically perfect (acentos, ñ).",
    "- NEVER promote homosexuality in visual concepts (Jorge 2026-05-07 — heterosexual couple imagery only for divorce/family scenarios).",
    "- Phone (920) 777-9886 + website pinnaclegroupwi.com mandatory in CTA.",
  ].filter(Boolean).join("\n");

  return _digestCache;
}

/**
 * Reset cache — used in tests or hot-reload scenarios.
 */
export function _resetOraculoCache() {
  _digestCache = null;
}
