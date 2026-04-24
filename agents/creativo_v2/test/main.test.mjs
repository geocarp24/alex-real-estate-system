import { test } from 'node:test';
import assert from 'node:assert/strict';
import { resolveWeek, safeSummary } from '../main.mjs';

test('resolveWeek uses fields.Semana if present as number', () => {
  assert.equal(resolveWeek({ fields: { Semana: 3 } }), 3);
});

test('resolveWeek parses string week', () => {
  assert.equal(resolveWeek({ fields: { Semana: '4' } }), 4);
});

test('resolveWeek defaults to 0 when missing', () => {
  assert.equal(resolveWeek({ fields: {} }), 0);
});

test('safeSummary truncates long messages', () => {
  const s = safeSummary('a'.repeat(500));
  assert.ok(s.length <= 200);
});
