// Wraps a slide body HTML in a full HTML document with Montserrat fonts loaded.
// The returned string is Puppeteer-ready.

import { THEMES } from './themes.mjs';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const REMOTE_LOGO_URL = 'https://pinnaclegroupwi.com/wp-content/uploads/2026/03/logo-pinnacle.png';
const LOGO_DATA_URI = (() => {
  const buf = readFileSync(path.join(__dirname, 'assets', 'logo-pinnacle.png'));
  return `data:image/png;base64,${buf.toString('base64')}`;
})();

function inlineLogo(html) {
  return html.split(REMOTE_LOGO_URL).join(LOGO_DATA_URI);
}

export function wrapSlideHtml(bodyHtml, themeCode = 'T1') {
  const theme = THEMES[themeCode] || THEMES.T1;
  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=1080" />
<title>Pinnacle Slide</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700;800&display=swap" />
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  html, body { width:1080px; height:1350px; background:${theme.bg}; }
  body { font-family: 'Montserrat', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; -webkit-font-smoothing: antialiased; }
  img { max-width:100%; display:block; }
</style>
</head>
<body>${bodyHtml}</body>
</html>`;
}
