import { test } from 'node:test';
import assert from 'node:assert/strict';
import { THEMES, buildCarousel } from '../src/themes.mjs';

test('THEMES contains all 5 codes T1-T5', () => {
  const codes = Object.keys(THEMES);
  assert.deepEqual(codes.sort(), ['T1', 'T2', 'T3', 'T4', 'T5']);
});

test('T1 has exact Pinnacle brand hex colors', () => {
  assert.equal(THEMES.T1.bg, '#0D3B2E');
  assert.equal(THEMES.T1.text, '#FFFFFF');
  assert.equal(THEMES.T1.accent, '#C9A84C');
});

test('buildCarousel returns 6 slides for hook + 4 points + cta', () => {
  const spec = {
    theme: 'T1',
    hook: { en: 'Test Hook', es: 'Hook Prueba' },
    points: [
      { headingEn: 'A', bodyEs: 'a' },
      { headingEn: 'B', bodyEs: 'b' },
      { headingEn: 'C', bodyEs: 'c' },
      { headingEn: 'D', bodyEs: 'd' },
    ],
    cta: { en: 'CTA EN', es: 'CTA ES' },
  };
  const slides = buildCarousel(spec);
  assert.equal(slides.length, 6);
  slides.forEach(s => assert.ok(typeof s === 'string' && s.length > 50));
});

test('buildCarousel throws on missing theme', () => {
  assert.throws(() => buildCarousel({ hook: {}, points: [], cta: {} }), /theme/);
});

test('buildCarousel throws on invalid theme code', () => {
  assert.throws(() => buildCarousel({ theme: 'T99', hook: {}, points: [], cta: {} }), /theme/);
});
