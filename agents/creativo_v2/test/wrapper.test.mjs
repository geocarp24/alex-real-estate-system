import { test } from 'node:test';
import assert from 'node:assert/strict';
import { wrapSlideHtml } from '../src/wrapper.mjs';

test('wrapSlideHtml produces valid full HTML doc', () => {
  const body = '<div>hello</div>';
  const out = wrapSlideHtml(body, 'T1');
  assert.ok(out.startsWith('<!DOCTYPE html>'));
  assert.ok(out.includes('<html'));
  assert.ok(out.includes('<head>'));
  assert.ok(out.includes('</html>'));
  assert.ok(out.includes(body));
});

test('wrapSlideHtml includes Montserrat Google Fonts preconnect + link', () => {
  const out = wrapSlideHtml('<div/>', 'T1');
  assert.ok(out.includes('fonts.googleapis.com'));
  assert.ok(out.includes('Montserrat'));
  assert.ok(out.includes('preconnect'));
});

test('wrapSlideHtml includes viewport meta for 1080 width', () => {
  const out = wrapSlideHtml('<div/>', 'T1');
  assert.ok(out.includes('viewport'));
  assert.ok(/width=1080|width=device-width/.test(out));
});

test('wrapSlideHtml sets body background to match theme (no FOUC gap)', () => {
  const out = wrapSlideHtml('<div/>', 'T1');
  assert.ok(out.includes('#0D3B2E'));
});

test('wrapSlideHtml applies zero-margin reset', () => {
  const out = wrapSlideHtml('<div/>', 'T1');
  assert.ok(out.includes('margin:0') || out.includes('margin: 0'));
  assert.ok(out.includes('box-sizing'));
});
