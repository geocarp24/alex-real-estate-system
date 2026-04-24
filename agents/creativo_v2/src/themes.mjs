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

function esc(s) {
  return String(s ?? "").replace(/[&<>"']/g, c => ({ "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;" })[c]);
}

function baseWrapper(theme, inner, opts = {}) {
  const pad = opts.pad ?? 72;
  return `<div style="
    width:1080px; height:1350px; background:${theme.bg}; color:${theme.text};
    font-family:'${FONT_HEADING}', system-ui, sans-serif;
    padding:${pad}px; position:relative; display:flex; flex-direction:column; overflow:hidden;">
    ${inner}
  </div>`;
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
export function slideHook(themeCode, { hookEn, hookEs, badge } = {}) {
  const theme = THEMES[themeCode] || THEMES.T1;
  const badgeChip = badge ? `
    <div style="
      align-self:center;
      background:${theme.accent};
      color:${theme.bg};
      font-size:22px; font-weight:800; letter-spacing:.1em; text-transform:uppercase;
      padding:10px 22px; border-radius:999px; margin-bottom:36px;">
      ${esc(badge)}
    </div>` : "";

  const inner = `
    <div style="flex:1; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; gap:32px;">
      ${badgeChip}
      <h1 style="
        font-size:104px; font-weight:800; line-height:1.05; letter-spacing:-0.02em;
        color:${theme.text}; max-width:920px;">
        ${esc(hookEn || "")}
      </h1>
      ${hookEs ? `<p style="
        font-size:44px; font-weight:500; line-height:1.25;
        color:${theme.accent}; max-width:900px; margin-top:12px;">
        ${esc(hookEs)}
      </p>` : ""}
      <div style="margin-top:48px; width:96px; height:4px; background:${theme.accent}; border-radius:4px;"></div>
    </div>
    ${logoWatermark(theme, 72)}
  `;
  return baseWrapper(theme, inner);
}

// ---------------------------------------------------------------------------
// POINT SLIDE — numbered body point with heading + body text, logo small
// Used as Slides 2 through N-1.
// ---------------------------------------------------------------------------
export function slidePoint(themeCode, { index, total, headingEn, bodyEs, headingEs, bodyEn } = {}) {
  const theme = THEMES[themeCode] || THEMES.T1;
  const numColor = theme.bg;  // number shown ON accent-color circle
  const heading = headingEs || headingEn || "";
  const body    = bodyEn || bodyEs || "";

  const inner = `
    <div style="display:flex; align-items:center; gap:24px; margin-bottom:48px;">
      <div style="
        width:96px; height:96px; border-radius:50%;
        background:${theme.accent}; color:${numColor};
        display:flex; align-items:center; justify-content:center;
        font-size:52px; font-weight:800; line-height:1;">
        ${esc(index ?? "1")}
      </div>
      <div style="
        flex:1; font-size:24px; color:${theme.muted};
        font-weight:500; letter-spacing:.06em; text-transform:uppercase;">
        Punto ${esc(index ?? "1")} / ${esc(total ?? "5")}
      </div>
    </div>

    <h2 style="
      font-size:78px; font-weight:800; line-height:1.12; letter-spacing:-0.015em;
      color:${theme.text}; margin-bottom:36px;">
      ${esc(heading)}
    </h2>

    <div style="width:72px; height:4px; background:${theme.accent}; border-radius:4px; margin-bottom:36px;"></div>

    <p style="
      font-size:40px; font-weight:400; line-height:1.45;
      color:${theme.muted};">
      ${esc(body)}
    </p>

    ${logoWatermark(theme, 56)}
  `;
  return baseWrapper(theme, inner);
}

// ---------------------------------------------------------------------------
// CTA SLIDE — centered logo + call to action + phone + website
// Used as the last slide of every carousel/post.
// ---------------------------------------------------------------------------
export function slideCTA(themeCode, { ctaEn, ctaEs } = {}) {
  const theme = THEMES[themeCode] || THEMES.T1;
  const cta_en = ctaEn || "We Buy Houses — Cash. Fast. Fair.";
  const cta_es = ctaEs || "Compramos Casas — Efectivo. Rápido. Justo.";

  const inner = `
    <div style="flex:1; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; gap:36px;">
      <img src="${LOGO_URL}" alt="Pinnacle Holdings" style="width:320px; height:auto; margin-bottom:18px;" />

      <h2 style="
        font-size:62px; font-weight:800; line-height:1.1; letter-spacing:-0.015em;
        color:${theme.text}; max-width:900px;">
        ${esc(cta_en)}
      </h2>
      <p style="
        font-size:34px; font-weight:500; line-height:1.3;
        color:${theme.accent}; max-width:900px;">
        ${esc(cta_es)}
      </p>

      <div style="width:96px; height:4px; background:${theme.accent}; border-radius:4px; margin:12px 0;"></div>

      <div style="display:flex; flex-direction:column; align-items:center; gap:14px; margin-top:20px;">
        <div style="font-size:52px; font-weight:800; color:${theme.text}; letter-spacing:-0.01em;">
          ${PHONE}
        </div>
        <div style="font-size:32px; font-weight:500; color:${theme.muted};">
          ${WEBSITE}
        </div>
      </div>
    </div>
  `;
  return baseWrapper(theme, inner, { pad: 96 });
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
