/**
 * Render BODY HTML (from themes.mjs) to PNG Buffer using Playwright headless Chromium.
 * Used by El Creativo runner. Server-side rendering = perfect text, deterministic branding.
 */
import { chromium } from "playwright-chromium";

const LOGO_URL_REMOTE = "https://pinnaclegroupwi.com/wp-content/uploads/2026/03/logo-pinnacle.png";
let _logoDataUri = null;

// Pre-fetch logo once, convert to base64 data URI so HTML render doesn't depend
// on external network during page.setContent (avoids invisible logo).
async function getLogoDataUri() {
  if (_logoDataUri) return _logoDataUri;
  try {
    const r = await fetch(LOGO_URL_REMOTE);
    if (!r.ok) throw new Error(`logo fetch HTTP ${r.status}`);
    const buf = Buffer.from(await r.arrayBuffer());
    _logoDataUri = `data:image/png;base64,${buf.toString("base64")}`;
    return _logoDataUri;
  } catch (e) {
    console.error(`[render] logo prefetch failed: ${e.message} — falling back to remote URL`);
    return LOGO_URL_REMOTE;
  }
}

// Replace any remote logo URL in HTML with the embedded data URI.
function inlineLogo(bodyHtml, logoUri) {
  return bodyHtml.replaceAll(LOGO_URL_REMOTE, logoUri);
}

// Wrap BODY HTML with full <html><head> including Montserrat fonts and reset.
export function wrapSlideHtml(bodyHtml) {
  return `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700;800;900&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;}
  html,body{margin:0;padding:0;width:1080px;height:1350px;background:#000;
    font-family:'Montserrat',system-ui,-apple-system,sans-serif;
    -webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;}
  body{overflow:hidden;}
  img{display:block;}
</style>
</head>
<body>${bodyHtml}</body>
</html>`;
}

let _browser = null;
async function getBrowser() {
  if (_browser && _browser.isConnected()) return _browser;
  _browser = await chromium.launch({
    headless: true,
    args: ["--no-sandbox", "--disable-setuid-sandbox", "--font-render-hinting=none"],
  });
  return _browser;
}

export async function closeBrowser() {
  if (_browser) {
    try { await _browser.close(); } catch {}
    _browser = null;
  }
}

/**
 * Render a single BODY HTML to PNG.
 * @param {string} bodyHtml - HTML body fragment from themes.mjs builders
 * @param {object} opts - { width, height, waitForFonts }
 * @returns {Buffer} PNG bytes
 */
export async function renderHtmlToPng(bodyHtml, opts = {}) {
  const width  = opts.width  || 1080;
  const height = opts.height || 1350;
  const waitFonts = opts.waitForFonts !== false;

  const browser = await getBrowser();
  const context = await browser.newContext({
    viewport: { width, height },
    deviceScaleFactor: 1,
  });
  const page = await context.newPage();
  try {
    await page.setContent(wrapSlideHtml(bodyHtml), { waitUntil: "networkidle", timeout: 20000 });
    if (waitFonts) {
      // Wait for Google Fonts to load to avoid FOUT in screenshots.
      await page.evaluate(() => document.fonts.ready);
    }
    const buffer = await page.screenshot({
      type: "png",
      fullPage: false,
      clip: { x: 0, y: 0, width, height },
    });
    return buffer;
  } finally {
    await context.close();
  }
}

/**
 * Render a sequence of slides (carousel) to an array of PNG Buffers.
 * @param {string[]} slidesHtml - array of BODY HTML strings
 * @returns {Buffer[]}
 */
export async function renderCarouselToPngs(slidesHtml, opts = {}) {
  const buffers = [];
  for (const html of slidesHtml) {
    buffers.push(await renderHtmlToPng(html, opts));
  }
  return buffers;
}
