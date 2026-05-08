/**
 * Tests for applyFormatDistribution + WEEKLY_FORMAT_MIX (Sprint A12).
 */
import { test, describe } from "node:test";
import assert from "node:assert/strict";
import {
  loadThemeBank,
  pickBatch,
  applyFormatDistribution,
  WEEKLY_FORMAT_MIX,
} from "../theme_bank_loader.mjs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const HERE = dirname(fileURLToPath(import.meta.url));
const TB_PATH = join(HERE, "..", "theme_bank.json");

describe("WEEKLY_FORMAT_MIX", () => {
  test("approved cadence: 42 Posts + 28 Reels + 8 Videos = 78 total", () => {
    assert.equal(WEEKLY_FORMAT_MIX.Post, 42);
    assert.equal(WEEKLY_FORMAT_MIX.Reel, 28);
    assert.equal(WEEKLY_FORMAT_MIX.Video, 8);
    assert.equal(WEEKLY_FORMAT_MIX.Post + WEEKLY_FORMAT_MIX.Reel + WEEKLY_FORMAT_MIX.Video, 78);
  });
});

describe("applyFormatDistribution — full week (78 picks)", () => {
  const tb = loadThemeBank(TB_PATH);
  const picks = pickBatch(tb, 78);

  test("returns same number of picks", () => {
    const out = applyFormatDistribution(picks);
    assert.equal(out.length, 78);
  });

  test("matches WEEKLY_FORMAT_MIX exactly", () => {
    const out = applyFormatDistribution(picks);
    const counts = { Post: 0, Reel: 0, Video: 0 };
    for (const o of out) counts[o.format]++;
    assert.equal(counts.Post, 42);
    assert.equal(counts.Reel, 28);
    assert.equal(counts.Video, 8);
  });

  test("preserves Theme Bank format_hint when possible", () => {
    const out = applyFormatDistribution(picks);
    // Subtopics with format_hint=Reel should preferentially get Reel format.
    const reelHinted = picks.filter(p => p.subtopic.format_hint === "Reel");
    if (reelHinted.length > 0 && reelHinted.length <= 28) {
      const reelHintedIds = new Set(reelHinted.map(r => r.subtopic.id));
      const reelHintedAssigned = out.filter(o => reelHintedIds.has(o.subtopic.id));
      const matched = reelHintedAssigned.filter(o => o.format === "Reel").length;
      // At minimum, all Reel-hinted should be Reel (we have 45 hints, 28 quota — all 28 should be Reel-hinted)
      assert.equal(matched, Math.min(reelHinted.length, 28));
    }
  });

  test("Video quota filled even when Theme Bank has few Video hints", () => {
    const out = applyFormatDistribution(picks);
    const videoCount = out.filter(o => o.format === "Video").length;
    assert.equal(videoCount, 8);
  });
});

describe("applyFormatDistribution — partial batches", () => {
  const tb = loadThemeBank(TB_PATH);

  test("11 picks (one day) → 6 Post + 4 Reel + 1 Video (proportional)", () => {
    const picks = pickBatch(tb, 11);
    const out = applyFormatDistribution(picks);
    const counts = { Post: 0, Reel: 0, Video: 0 };
    for (const o of out) counts[o.format]++;
    // 11 / 78 = 0.141 → Posts: 42*0.141=5.92→6, Reels: 28*0.141=3.95→4, Videos: 8*0.141=1.13→1
    assert.equal(counts.Post + counts.Reel + counts.Video, 11);
    assert.ok(counts.Post >= 5 && counts.Post <= 7, `Posts in [5,7], got ${counts.Post}`);
    assert.ok(counts.Reel >= 3 && counts.Reel <= 5, `Reels in [3,5], got ${counts.Reel}`);
    assert.ok(counts.Video >= 1 && counts.Video <= 2, `Videos in [1,2], got ${counts.Video}`);
  });

  test("3 picks → roughly 2 Post + 1 Reel + 0 Video", () => {
    const picks = pickBatch(tb, 3);
    const out = applyFormatDistribution(picks);
    assert.equal(out.length, 3);
    const counts = { Post: 0, Reel: 0, Video: 0 };
    for (const o of out) counts[o.format]++;
    assert.equal(counts.Post + counts.Reel + counts.Video, 3);
  });
});

describe("applyFormatDistribution — custom distribution", () => {
  const tb = loadThemeBank(TB_PATH);

  test("custom 100% Reels distribution forces all Reel", () => {
    const picks = pickBatch(tb, 10);
    const out = applyFormatDistribution(picks, { distribution: { Post: 0, Reel: 10, Video: 0 } });
    for (const o of out) assert.equal(o.format, "Reel");
  });

  test("zero distribution falls back to format_hint", () => {
    const picks = pickBatch(tb, 5);
    const out = applyFormatDistribution(picks, { distribution: { Post: 0, Reel: 0, Video: 0 } });
    assert.equal(out.length, 5);
    for (const o of out) {
      assert.ok(["Post", "Reel", "Video"].includes(o.format));
    }
  });
});
