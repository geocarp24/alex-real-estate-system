import { test } from 'node:test';
import assert from 'node:assert/strict';
import { THEMES, buildCarousel, ASPECTS, VALID_ASPECTS, dimsForAspect } from '../src/themes.mjs';

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

test('VALID_ASPECTS contains 4:5, 1:1, 9:16', () => {
  assert.deepEqual(VALID_ASPECTS.sort(), ['1:1', '4:5', '9:16']);
});

test('dimsForAspect returns correct dimensions', () => {
  assert.deepEqual(dimsForAspect('4:5'),  { width: 1080, height: 1350 });
  assert.deepEqual(dimsForAspect('1:1'),  { width: 1080, height: 1080 });
  assert.deepEqual(dimsForAspect('9:16'), { width: 1080, height: 1920 });
});

test('dimsForAspect defaults to 4:5 for unknown aspect', () => {
  assert.deepEqual(dimsForAspect('garbage'), { width: 1080, height: 1350 });
});

test('buildCarousel defaults to 4:5 when aspect not in spec', () => {
  const slides = buildCarousel({
    theme: 'T1', hook: { en: 'h', es: 'h' }, points: [], cta: { en: 'c', es: 'c' },
  });
  assert.ok(slides[0].includes('height:1350px'), 'slide 1 must be 1350 tall (4:5 default)');
  assert.ok(slides[0].includes('data-aspect="4:5"'));
});

test('buildCarousel propagates aspect 1:1 to all slides', () => {
  const slides = buildCarousel({
    theme: 'T1', aspect: '1:1',
    hook: { en: 'h', es: 'h' },
    points: [{ headingEn: 'a', headingEs: 'b', bodyEn: 'c', bodyEs: 'd' }],
    cta: { en: 'c', es: 'c' },
  });
  slides.forEach((s, i) => {
    assert.ok(s.includes('height:1080px'), `slide ${i+1} must be 1080 tall (1:1)`);
    assert.ok(s.includes('data-aspect="1:1"'), `slide ${i+1} must mark aspect 1:1`);
  });
});

test('buildCarousel propagates aspect 9:16 to all slides', () => {
  const slides = buildCarousel({
    theme: 'T1', aspect: '9:16',
    hook: { en: 'h', es: 'h' },
    points: [{ headingEn: 'a', headingEs: 'b', bodyEn: 'c', bodyEs: 'd' }],
    cta: { en: 'c', es: 'c' },
  });
  slides.forEach((s, i) => {
    assert.ok(s.includes('height:1920px'), `slide ${i+1} must be 1920 tall (9:16)`);
    assert.ok(s.includes('data-aspect="9:16"'), `slide ${i+1} must mark aspect 9:16`);
  });
});

test('buildCarousel with invalid aspect falls back to 4:5', () => {
  const slides = buildCarousel({
    theme: 'T1', aspect: 'garbage',
    hook: { en: 'h', es: 'h' }, points: [], cta: { en: 'c', es: 'c' },
  });
  assert.ok(slides[0].includes('height:1350px'));
  assert.ok(slides[0].includes('data-aspect="4:5"'));
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
