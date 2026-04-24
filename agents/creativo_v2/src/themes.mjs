// Pinnacle Holdings — 5 brand themes + slide HTML builders.
// Target: Instagram 4:5 portrait = 1080 x 1350 (mobile-native).
// Each builder returns BODY-only HTML (open-carrusel's wrapSlideHtml injects <html>/<head>/fonts).

const LOGO_URL = "https://pinnaclegroupwi.com/wp-content/uploads/2026/03/logo-pinnacle.png";
const PHONE    = "(920) 777-9886";
const WEBSITE  = "pinnaclegroupwi.com";
const FONT_HEADING = "Montserrat";
const FONT_BODY    = "Montserrat";

export const THEMES = {
  T1: { name: "Dark Premium",  bg: "#0D3B2E", text: "#FFFFFF", accent: "#C9A84C", muted: "#E6E1D2", subtle: "rgba(255,255,255,.12)" },
  T2: { name: "White Clean",   bg: "#FFFFFF", text: "#0D3B2E", accent: "#C9A84C", muted: "#5A6B65", subtle: "rgba(13,59,46,.08)"  },
  T3: { name: "Gold & Black",  bg: "#1A1A1A", text: "#FFFFFF", accent: "#C9A84C", muted: "#C2C2C2", subtle: "rgba(201,168,76,.16)"},
  T4: { name: "Soft Cream",    bg: "#F5F0E8", text: "#0D3B2E", accent: "#C9A84C", muted: "#2C2C2C", subtle: "rgba(13,59,46,.08)"  },
  T5: { name: "Vibrant Blue",  bg: "#1B2A8C", text: "#FFFFFF", accent: "#FF2D78", muted: "#9BB3FF", subtle: "rgba(255,255,255,.14)", accent2: "#00E676" },
};

export const VALID_THEME_CODES = Object.keys(THEMES);

// Aspect ratios supported — width x height in pixels at 1080 width.
export const ASPECTS = {
  "4:5":  { width: 1080, height: 1350 },   // IG/FB feed vertical — default
  "1:1":  { width: 1080, height: 1080 },   // cross-platform square
  "9:16": { width: 1080, height: 1920 },   // Stories / Reel cover
};

export const VALID_ASPECTS = Object.keys(ASPECTS);

export function dimsForAspect(aspect) {
  return ASPECTS[aspect] || ASPECTS["4:5"];
}

function esc(s) {
  return String(s ?? "").replace(/[&<>"']/g, c => ({ "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;" })[c]);
}

function baseWrapper(theme, inner, opts = {}) {
  const pad = opts.pad ?? 72;
  const aspect = opts.aspect || "4:5";
  const dims = dimsForAspect(aspect);
  return `<div data-aspect="${aspect}" style="
    width:${dims.width}px; height:${dims.height}px; background:${theme.bg}; color:${theme.text};
    font-family:'${FONT_HEADING}', system-ui, sans-serif;
    padding:${pad}px; position:relative; display:flex; flex-direction:column; overflow:hidden;">
    ${inner}
  </div>`;
}

function logoCorner(theme, width = 140) {
  const isLight = theme.name === "White Clean" || theme.name === "Soft Cream";
  return `<img src="${LOGO_URL}" alt="Pinnacle Holdings Group" style="
    position:absolute; top:48px; right:48px; width:${width}px; height:auto; z-index:5;
    opacity:${isLight ? ".95" : "1"};" />`;
}

function logoWatermark(theme, size = 64) {
  return `<img src="${LOGO_URL}" alt="Pinnacle Holdings" style="
    position:absolute; bottom:48px; right:48px; width:${size}px; height:auto;
    opacity:${theme.name === "White Clean" || theme.name === "Soft Cream" ? ".85" : ".9"};
    filter:${theme.name === "White Clean" || theme.name === "Soft Cream" ? "none" : "brightness(1.05)"};" />`;
}

// ---------------------------------------------------------------------------
// HOOK SLIDE — large centered hook text, bilingual EN/ES, logo watermark
// Used as Slide 1 of every carousel/post.
// ---------------------------------------------------------------------------
export function slideHook(themeCode, { hookEn, hookEs, badge, aspect = "4:5" } = {}) {
  const theme = THEMES[themeCode] || THEMES.T1;
  const badgeChip = badge ? `
    <div style="
      background:${theme.accent};
      color:${theme.bg};
      font-size:28px; font-weight:800; letter-spacing:.14em; text-transform:uppercase;
      padding:14px 30px; border-radius:999px; margin-bottom:42px;">
      ${esc(badge)}
    </div>` : "";

  const inner = `
    <div style="position:absolute; inset:0; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; padding:72px;">
      ${badgeChip}
      <h1 style="
        font-size:132px; font-weight:800; line-height:1.02; letter-spacing:-0.025em;
        color:${theme.text}; max-width:960px; margin:0;">
        ${esc(hookEn || "")}
      </h1>
      ${hookEs ? `<p style="
        font-size:56px; font-weight:500; line-height:1.22;
        color:${theme.accent}; max-width:920px; margin-top:36px;">
        ${esc(hookEs)}
      </p>` : ""}
      <div style="margin-top:56px; width:120px; height:5px; background:${theme.accent}; border-radius:4px;"></div>
    </div>
    ${logoCorner(theme, 200)}
  `;
  return baseWrapper(theme, inner, { pad: 0, aspect });
}

// ---------------------------------------------------------------------------
// POINT SLIDE — numbered body point with heading + body text, logo small
// Used as Slides 2 through N-1.
// ---------------------------------------------------------------------------
export function slidePoint(themeCode, { index, total, headingEn, bodyEn, headingEs, bodyEs } = {}) {
  const theme = THEMES[themeCode] || THEMES.T1;
  const numColor = theme.bg;
  const idx = String(index ?? "1").padStart(2, "0");

  const inner = `
    <!-- Ghost number decoration: big faded number in background -->
    <div style="
      position:absolute; top:46%; left:-40px; transform:translateY(-50%);
      font-size:560px; font-weight:900; line-height:.85; color:${theme.subtle || "rgba(255,255,255,.06)"};
      letter-spacing:-0.05em; pointer-events:none; user-select:none; z-index:0;">
      ${esc(idx)}
    </div>

    <!-- Top-left index badge -->
    <div style="position:absolute; top:48px; left:48px; display:flex; align-items:center; gap:22px; z-index:2;">
      <div style="
        width:88px; height:88px; border-radius:50%;
        background:${theme.accent}; color:${numColor};
        display:flex; align-items:center; justify-content:center;
        font-size:46px; font-weight:800; line-height:1;">
        ${esc(index ?? "1")}
      </div>
      <div style="
        font-size:22px; color:${theme.muted};
        font-weight:700; letter-spacing:.14em; text-transform:uppercase;">
        Punto ${esc(index ?? "1")} / ${esc(total ?? "5")}
      </div>
    </div>

    <!-- Main content: 2-column bilingual -->
    <div style="position:absolute; inset:0; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:72px; z-index:1;">
      <div style="display:flex; gap:56px; align-items:stretch; width:100%; max-width:920px;">
        <!-- EN column -->
        <div style="flex:1; display:flex; flex-direction:column; text-align:left;">
          <div style="font-size:18px; color:${theme.accent}; font-weight:800; letter-spacing:.22em; margin-bottom:20px;">ENGLISH</div>
          <h2 style="font-size:52px; font-weight:800; line-height:1.05; letter-spacing:-0.015em; color:${theme.text}; margin:0 0 20px 0;">
            ${esc(headingEn || "")}
          </h2>
          <div style="width:56px; height:4px; background:${theme.accent}; border-radius:4px; margin-bottom:24px;"></div>
          <p style="font-size:26px; font-weight:400; line-height:1.4; color:${theme.muted}; margin:0;">
            ${esc(bodyEn || "")}
          </p>
        </div>
        <!-- Divider -->
        <div style="width:2px; background:${theme.subtle || "rgba(255,255,255,.12)"};"></div>
        <!-- ES column -->
        <div style="flex:1; display:flex; flex-direction:column; text-align:left;">
          <div style="font-size:18px; color:${theme.accent}; font-weight:800; letter-spacing:.22em; margin-bottom:20px;">ESPANOL</div>
          <h2 style="font-size:52px; font-weight:800; line-height:1.05; letter-spacing:-0.015em; color:${theme.text}; margin:0 0 20px 0;">
            ${esc(headingEs || headingEn || "")}
          </h2>
          <div style="width:56px; height:4px; background:${theme.accent}; border-radius:4px; margin-bottom:24px;"></div>
          <p style="font-size:26px; font-weight:400; line-height:1.4; color:${theme.muted}; margin:0;">
            ${esc(bodyEs || bodyEn || "")}
          </p>
        </div>
      </div>
    </div>

    ${logoCorner(theme, 140)}
  `;
  return baseWrapper(theme, inner, { pad: 0 });
}

// ---------------------------------------------------------------------------
// CTA SLIDE — centered logo + call to action + phone + website
// Used as the last slide of every carousel/post.
// ---------------------------------------------------------------------------
export function slideCTA(themeCode, { ctaEn, ctaEs } = {}) {
  const theme = THEMES[themeCode] || THEMES.T1;
  const cta_en = ctaEn || "We Buy Houses. Cash. Fast. Fair.";
  const cta_es = ctaEs || "Compramos Casas. Efectivo. Rapido. Justo.";

  const inner = `
    <div style="position:absolute; inset:0; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; padding:72px;">
      <h2 style="
        font-size:82px; font-weight:800; line-height:1.08; letter-spacing:-0.02em;
        color:${theme.text}; max-width:940px; margin:0 0 24px 0;">
        ${esc(cta_en)}
      </h2>
      <p style="
        font-size:44px; font-weight:500; line-height:1.22;
        color:${theme.accent}; max-width:920px; margin:0 0 42px 0;">
        ${esc(cta_es)}
      </p>

      <div style="width:120px; height:5px; background:${theme.accent}; border-radius:4px; margin-bottom:48px;"></div>

      <div style="display:flex; flex-direction:column; align-items:center; gap:18px;">
        <div style="font-size:72px; font-weight:800; color:${theme.text}; letter-spacing:-0.01em;">
          ${PHONE}
        </div>
        <div style="font-size:40px; font-weight:500; color:${theme.muted};">
          ${WEBSITE}
        </div>
      </div>
    </div>
    ${logoCorner(theme, 160)}
  `;
  return baseWrapper(theme, inner, { pad: 0 });
}

// ---------------------------------------------------------------------------
// buildCarousel — convenience: takes a spec object and returns [html, html, ...]
//   spec = {
//     theme: "T1",                              // required
//     hook: { en: "...", es: "...", badge? },
//     points: [{ headingEn, bodyEs }, ...]      // 0 or more
//     cta: { en?, es? }
//   }
// ---------------------------------------------------------------------------
export function buildCarousel(spec) {
  if (!spec || !spec.theme || !VALID_THEME_CODES.includes(spec.theme)) {
    throw new Error(`buildCarousel: invalid or missing theme, expected one of ${VALID_THEME_CODES.join(",")}`);
  }
  const slides = [];
  const hook = spec.hook || {};
  slides.push(slideHook(spec.theme, {
    hookEn: hook.hookEn ?? hook.en,
    hookEs: hook.hookEs ?? hook.es,
    badge:  hook.badge,
  }));
  const pts = Array.isArray(spec.points) ? spec.points : [];
  const total = pts.length;
  pts.forEach((p, i) => {
    slides.push(slidePoint(spec.theme, { ...p, index: i + 1, total }));
  });
  const cta = spec.cta || {};
  slides.push(slideCTA(spec.theme, {
    ctaEn: cta.ctaEn ?? cta.en,
    ctaEs: cta.ctaEs ?? cta.es,
  }));
  return slides;
}
