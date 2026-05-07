/**
 * backgrounds.mjs — fetch portrait photo URLs for Post/Story editorial slides.
 *
 * Strategy (cost-optimized per Jorge 2026-05-07 "reutilizar todo lo posible"):
 *   1. Pexels portrait search   — FREE, primary source.
 *   2. Curated evergreen library — fallback bank of 8 Wisconsin/home photos
 *      (rotated by content type) when Pexels fails or returns no results.
 *
 * Pexels images do not need re-uploading to Cloudinary: they already live on
 * Pexels CDN. The Creativo bake step composites them via <img src="..."> in
 * the Puppeteer render, so the Pexels URL is only fetched once per render
 * (when Chromium loads the page) and the final composite is what gets cached
 * in Cloudinary.
 *
 * NOT used: AI imagen models for Post backgrounds. Per CLAUDE.md regla 1d,
 * AI imagen is allowed ONLY when the model would not generate Spanish text
 * inside the image — for Posts, all text is overlaid via HTML/CSS, so
 * a future FLUX2 fallback is permitted but disabled by default to save cost.
 */

const PEXELS_API_KEY = process.env.PEXELS_API_KEY || "";
const PEXELS_API     = "https://api.pexels.com/v1";

// 8 evergreen Pinnacle backgrounds — used when Pexels fails. These are
// portrait-oriented, warm-tone real estate scenes that fit the editorial
// homeowner aesthetic (regla 1e: minimalist-ui + high-end-visual-design).
// All are public-domain or Pexels-licensed (no attribution needed for these).
const EVERGREEN_BACKGROUNDS = [
  // Wisconsin home exteriors (warm, golden hour)
  "https://images.pexels.com/photos/1396122/pexels-photo-1396122.jpeg?auto=compress&w=1080&h=1350&fit=crop",
  "https://images.pexels.com/photos/277667/pexels-photo-277667.jpeg?auto=compress&w=1080&h=1350&fit=crop",
  // Cozy interiors (kitchens, living rooms)
  "https://images.pexels.com/photos/1571460/pexels-photo-1571460.jpeg?auto=compress&w=1080&h=1350&fit=crop",
  "https://images.pexels.com/photos/1571463/pexels-photo-1571463.jpeg?auto=compress&w=1080&h=1350&fit=crop",
  // Hands shaking / paperwork (selling-the-house imagery)
  "https://images.pexels.com/photos/3760067/pexels-photo-3760067.jpeg?auto=compress&w=1080&h=1350&fit=crop",
  "https://images.pexels.com/photos/4427611/pexels-photo-4427611.jpeg?auto=compress&w=1080&h=1350&fit=crop",
  // Suburban neighborhoods (Wisconsin-feel)
  "https://images.pexels.com/photos/106399/pexels-photo-106399.jpeg?auto=compress&w=1080&h=1350&fit=crop",
  "https://images.pexels.com/photos/2287310/pexels-photo-2287310.jpeg?auto=compress&w=1080&h=1350&fit=crop",
];

function sanitizeQuery(q) {
  return String(q ?? "")
    .replace(/[^a-zA-Z0-9 ]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, 80);
}

// Pick a deterministic evergreen by hashing the record id / query — keeps same
// idea→same fallback image across reruns (idempotent).
function pickEvergreen(seed) {
  let h = 0;
  const s = String(seed || "default");
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
  const idx = Math.abs(h) % EVERGREEN_BACKGROUNDS.length;
  return { bgUrl: EVERGREEN_BACKGROUNDS[idx], photographer: "Pexels", source: "evergreen" };
}

/**
 * Search Pexels for a portrait photo matching the query.
 * Returns { bgUrl, photographer, source } where source is "pexels" or "evergreen".
 */
export async function fetchPostBackground(rawQuery, { seed } = {}) {
  const query = sanitizeQuery(rawQuery);

  if (!PEXELS_API_KEY || !query) {
    return pickEvergreen(seed || query);
  }

  try {
    const url = `${PEXELS_API}/search?orientation=portrait&size=large&per_page=5&query=${encodeURIComponent(query)}`;
    const r = await fetch(url, {
      headers: { Authorization: PEXELS_API_KEY },
      signal: AbortSignal.timeout(12000),
    });
    if (!r.ok) throw new Error(`Pexels HTTP ${r.status}`);
    const data = await r.json();
    if (!data.photos || data.photos.length === 0) {
      console.error(`[backgrounds] Pexels no results for "${query}" — falling back to evergreen`);
      return pickEvergreen(seed || query);
    }
    // Pick a photo deterministically by seed so the same record gets the same
    // photo across reruns (avoids accidental re-renders changing the image).
    let h = 0;
    const s = String(seed || query);
    for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
    const photo = data.photos[Math.abs(h) % data.photos.length];

    return {
      bgUrl: photo.src.portrait || photo.src.large2x || photo.src.original,
      photographer: photo.photographer || "Pexels",
      source: "pexels",
    };
  } catch (e) {
    console.error(`[backgrounds] Pexels failed (${e.message}) — falling back to evergreen`);
    return pickEvergreen(seed || query);
  }
}

/**
 * Build a Pexels-friendly query from Visual_Prompt + Tipo + Caption.
 * Maps content type → photo theme (e.g., "homeowner kitchen", "wisconsin home").
 */
export function deriveBgQuery({ visualPrompt = "", tipo = "", titulo = "", captionEn = "" }) {
  const text = `${visualPrompt} ${tipo} ${titulo} ${captionEn}`.toLowerCase();

  // Topic detection — order matters (most specific first).
  if (/foreclosure|embarg|deuda|debt|behind on|atrasado/.test(text))
    return "stressed homeowner kitchen window light";
  if (/divorce|divorc|separation|separac/.test(text))
    return "empty house living room natural light";
  if (/inherited|hered|estate|funeral/.test(text))
    return "old wooden house exterior warm";
  if (/repair|reparac|fixer|fixer-upper|damaged|repairs/.test(text))
    return "old house exterior renovation";
  if (/landlord|tenant|inquilino|propietario/.test(text))
    return "apartment building exterior warm light";
  if (/relocat|mudanza|moving|out of state/.test(text))
    return "moving boxes packed home";
  if (/cash|efectivo|fast|rapido|quick/.test(text))
    return "handshake home keys close up";
  if (/family|familia|kids|niños/.test(text))
    return "happy family home suburban";
  if (/wisconsin|milwaukee|madison|kenosha/.test(text))
    return "wisconsin suburban home autumn";
  if (/process|proceso|how it works|paso a paso|step/.test(text))
    return "wooden steps stairs home interior";
  if (/agent|realtor|comision|commission/.test(text))
    return "for sale sign yard home";
  if (/equity|valor|investment|inversion/.test(text))
    return "modern suburban home golden hour";

  // Default: warm Wisconsin home.
  return "wisconsin home exterior golden hour";
}
