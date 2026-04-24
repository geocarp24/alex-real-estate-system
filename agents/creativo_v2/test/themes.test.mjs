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

test('buildCarousel maps spec.hook.en/es to hookEn/hookEs in output HTML', () => {
  const spec = {
    theme: 'T1',
    hook: { en: 'My Hook EN', es: 'Mi Hook ES', badge: 'BADGE' },
    points: [{ headingEn: 'H', bodyEs: 'b' }],
    cta: { en: 'My CTA EN', es: 'Mi CTA ES' },
  };
  const slides = buildCarousel(spec);
  assert.ok(slides[0].includes('My Hook EN'), 'slide 1 must contain hook EN');
  assert.ok(slides[0].includes('Mi Hook ES'), 'slide 1 must contain hook ES');
  assert.ok(slides[0].includes('BADGE'), 'slide 1 must contain badge');
  assert.ok(slides[slides.length - 1].includes('My CTA EN'), 'last slide must contain CTA EN');
  assert.ok(slides[slides.length - 1].includes('Mi CTA ES'), 'last slide must contain CTA ES');
});

test('slidePoint (2-col) contains BOTH EN and ES content: heading and body', () => {
  const spec = {
    theme: 'T1',
    hook: { en: 'H', es: 'h' },
    points: [{ headingEn: 'EN_HEAD', headingEs: 'ES_HEAD', bodyEn: 'EN_BODY', bodyEs: 'ES_BODY' }],
    cta: { en: 'c', es: 'c' },
  };
  const slides = buildCarousel(spec);
  const pt = slides[1];
  assert.ok(pt.includes('EN_HEAD'), 'point must contain heading EN');
  assert.ok(pt.includes('ES_HEAD'), 'point must contain heading ES');
  assert.ok(pt.includes('EN_BODY'), 'point must contain body EN');
  assert.ok(pt.includes('ES_BODY'), 'point must contain body ES');
  assert.ok(pt.includes('ENGLISH'), 'point must label the EN column');
  assert.ok(pt.includes('ESPANOL'), 'point must label the ES column');
});

test('all slides place the Pinnacle logo in top-right corner', () => {
  const spec = {
    theme: 'T1',
    hook: { en: 'H', es: 'h' },
    points: [{ headingEn: 'A', headingEs: 'a', bodyEn: 'b', bodyEs: 'b' }],
    cta: { en: 'c', es: 'c' },
  };
  const slides = buildCarousel(spec);
  slides.forEach((html, i) => {
    assert.ok(/top:48px;\s*right:48px/.test(html), `slide ${i+1} must have logo top-right`);
  });
});
