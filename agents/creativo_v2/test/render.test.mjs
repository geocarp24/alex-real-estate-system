import { test } from 'node:test';
import assert from 'node:assert/strict';
import { renderJpg, closeBrowser } from '../src/render.mjs';
import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';

const MIN_HTML = `<!DOCTYPE html><html><head><style>
  html,body{margin:0;width:1080px;height:1350px;background:#0D3B2E;}
  h1{color:#fff;font:800 120px sans-serif;padding:400px 60px;}
  </style></head><body><h1>HELLO</h1></body></html>`;

test('renderJpg produces a JPG file > 10KB', async () => {
  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), 'creativo-'));
  const out = path.join(tmp, 'test.jpg');
  await renderJpg(MIN_HTML, out);
  const stat = await fs.stat(out);
  assert.ok(stat.size > 10_000, `expected > 10KB, got ${stat.size}`);
  await fs.rm(tmp, { recursive: true });
});

test('renderJpg output is valid JPEG (starts with SOI marker FFD8)', async () => {
  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), 'creativo-'));
  const out = path.join(tmp, 'test.jpg');
  await renderJpg(MIN_HTML, out);
  const buf = await fs.readFile(out);
  assert.equal(buf[0], 0xff);
  assert.equal(buf[1], 0xd8);
  await fs.rm(tmp, { recursive: true });
});

test('renderJpg produces 1080x1350 image (parse SOF0 marker)', async () => {
  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), 'creativo-'));
  const out = path.join(tmp, 'test.jpg');
  await renderJpg(MIN_HTML, out);
  const buf = await fs.readFile(out);
  let i = 0;
  let h = 0, w = 0;
  while (i < buf.length - 10) {
    if (buf[i] === 0xff && buf[i + 1] === 0xc0) {
      h = (buf[i + 5] << 8) | buf[i + 6];
      w = (buf[i + 7] << 8) | buf[i + 8];
      break;
    }
    i++;
  }
  assert.equal(w, 1080, `width ${w}`);
  assert.equal(h, 1350, `height ${h}`);
  await fs.rm(tmp, { recursive: true });
});

test.after(async () => {
  await closeBrowser();
});
