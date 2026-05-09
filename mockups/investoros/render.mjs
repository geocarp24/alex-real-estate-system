#!/usr/bin/env node
/**
 * Render the 4 InvestorOS mockup HTML files to PNG via Playwright.
 * Pinnacle aesthetic — full-page screenshot at 1440 width.
 */
import { chromium } from "playwright-chromium";
import { fileURLToPath, pathToFileURL } from "node:url";
import path from "node:path";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const PAGES = [
  { html: "01-landing.html",   out: "out/01-landing.png",   width: 1440, height: 900,  fullPage: true },
  { html: "02-dashboard.html", out: "out/02-dashboard.png", width: 1440, height: 1000, fullPage: true },
  { html: "03-kanban.html",    out: "out/03-kanban.png",    width: 1600, height: 1000, fullPage: true },
  { html: "04-pricing.html",   out: "out/04-pricing.png",   width: 1440, height: 900,  fullPage: true },
];

const browser = await chromium.launch({
  executablePath: "/opt/pw-browsers/chromium-1217/chrome-linux64/chrome",
  args: ["--no-sandbox"],
});

for (const p of PAGES) {
  const ctx = await browser.newContext({
    viewport: { width: p.width, height: p.height },
    deviceScaleFactor: 2,
  });
  const page = await ctx.newPage();
  const url = pathToFileURL(path.join(__dirname, p.html)).toString();
  await page.goto(url, { waitUntil: "networkidle" });
  await page.waitForTimeout(800); // ensure fonts settled
  await page.screenshot({
    path: path.join(__dirname, p.out),
    fullPage: p.fullPage,
    type: "png",
  });
  console.log(`✓ ${p.out}`);
  await ctx.close();
}

await browser.close();
console.log("done");
