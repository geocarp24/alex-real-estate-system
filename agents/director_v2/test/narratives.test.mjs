import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { expandNarrative, validateSpec } from '../src/narratives/index.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const B_VALID = JSON.parse(readFileSync(join(HERE, 'fixtures/spec_narrative_B_valid.json'), 'utf8'));

test('narrative B expands to 5 scenes with correct durations', () => {
  const scenes = expandNarrative(B_VALID);
  assert.equal(scenes.length, 5);
  const total = scenes.reduce((s, sc) => s + sc.duration, 0);
  assert.ok(total >= 7 && total <= 15, `total duration ${total} must be 7-15s`);
  assert.equal(scenes[0].layoutType, 'hook');
  assert.equal(scenes[1].layoutType, 'layout_d');
  assert.equal(scenes[2].layoutType, 'layout_d');
  assert.equal(scenes[3].layoutType, 'layout_d');
  assert.equal(scenes[4].layoutType, 'cta');
});

test('narrative B uses Nano Banana only on scene 1 and scene 5 (cost cap)', () => {
  const scenes = expandNarrative(B_VALID);
  const nanoCount = scenes.filter(s => s.heroSource === 'nano_banana').length;
  assert.equal(nanoCount, 2, 'exactly 2 Nano Banana calls per narrative B video');
});

test('narrative B maps points[i].headingEn to scene captionEn', () => {
  const scenes = expandNarrative(B_VALID);
  assert.equal(scenes[1].captionEn, 'Faster Than Banks');
  assert.equal(scenes[1].captionEs, 'Más Rápido Que Los Bancos');
  assert.equal(scenes[2].captionEn, 'No Commissions');
  assert.equal(scenes[3].captionEn, 'No Showings');
});

test('narrative B derives pexels query from heading', () => {
  const scenes = expandNarrative(B_VALID);
  assert.equal(scenes[1].heroSource, 'pexels');
  assert.ok(scenes[1].heroQuery.length > 0);
});

test('validateSpec passes on valid narrative B', () => {
  assert.equal(validateSpec(B_VALID), true);
});

test('validateSpec throws on missing points in narrative B', () => {
  const bad = { ...B_VALID, points: [{ headingEn: 'x' }] };
  assert.throws(() => validateSpec(bad), /points\[3\+\]/);
});

test('validateSpec throws on unknown narrative', () => {
  const bad = { ...B_VALID, narrative: 'Z' };
  assert.throws(() => validateSpec(bad), /narrative must be A\|B\|C/);
});

test('validateSpec throws on invalid aspect for Director', () => {
  const bad = { ...B_VALID, aspect: '4:5' };
  assert.throws(() => validateSpec(bad), /aspect must be 9:16/);
});

test('validateSpec throws on duration out of 7-15', () => {
  const bad = { ...B_VALID, duration: 30 };
  assert.throws(() => validateSpec(bad), /duration must be 7-15/);
});

test('dispatcher throws on unknown narrative code', () => {
  assert.throws(() => expandNarrative({ narrative: 'X' }), /Unknown narrative/);
});
