// Puppeteer HTML -> JPG at 1080x1350 quality 90.
// Launches a single shared browser per process for efficiency.

import puppeteer from 'puppeteer';
import path from 'node:path';
import fs from 'node:fs/promises';

const DEFAULT_VIEWPORT = { width: 1080, height: 1350, deviceScaleFactor: 1 };
const JPEG_QUALITY = 90;

let _browser = null;

async function getBrowser() {
  if (_browser && _browser.isConnected()) return _browser;
  _browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  });
  return _browser;
}

export async function renderJpg(html, outputPath, opts = {}) {
  await fs.mkdir(path.dirname(outputPath), { recursive: true });
  const width = opts.width ?? DEFAULT_VIEWPORT.width;
  const height = opts.height ?? DEFAULT_VIEWPORT.height;
  const browser = await getBrowser();
  const page = await browser.newPage();
  try {
    await page.setViewport({ width, height, deviceScaleFactor: 1 });
    await page.setContent(html, { waitUntil: 'networkidle0', timeout: 30000 });
    await page.evaluate(() => document.fonts && document.fonts.ready);
    await page.screenshot({
      path: outputPath,
      type: 'jpeg',
      quality: JPEG_QUALITY,
      clip: { x: 0, y: 0, width, height },
      omitBackground: false,
    });
  } finally {
    await page.close();
  }
  return outputPath;
}

export async function closeBrowser() {
  if (_browser) {
    await _browser.close();
    _browser = null;
  }
}
