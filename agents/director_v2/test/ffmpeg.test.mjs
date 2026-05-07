import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildVideoCommand } from '../src/ffmpeg.mjs';

function sampleScenes() {
  return [
    { index: 1, duration: 2.5, imagePaths: ['/tmp/s1.jpg'], zoompan: { from: 1.0, to: 1.05 }, transitionOut: 'crossfade', kinetic: false },
    { index: 2, duration: 2.0, imagePaths: ['/tmp/s2.jpg'], zoompan: { from: 1.0, to: 1.03 }, transitionOut: 'wipeleft',  kinetic: false },
    { index: 3, duration: 2.0, imagePaths: ['/tmp/s3.jpg'], zoompan: { from: 1.0, to: 1.03 }, transitionOut: 'crossfade', kinetic: false },
    { index: 4, duration: 2.0, imagePaths: ['/tmp/s4.jpg'], zoompan: { from: 1.0, to: 1.03 }, transitionOut: 'slideup',   kinetic: false },
    { index: 5, duration: 2.5, imagePaths: ['/tmp/s5.jpg'], zoompan: { from: 1.0, to: 1.05 }, transitionOut: 'none',      kinetic: false },
  ];
}

test('buildVideoCommand returns argv ARRAY (not string) — zero shell injection', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  assert.ok(Array.isArray(cmd.args), 'args must be an array');
  assert.equal(cmd.bin, 'ffmpeg');
  for (const a of cmd.args) assert.equal(typeof a, 'string', `every arg must be string, got ${typeof a}`);
});

test('buildVideoCommand output args include H.264 + faststart + 1080x1920', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const s = cmd.args.join(' ');
  assert.ok(s.includes('libx264'));
  assert.ok(s.includes('yuv420p'));
  assert.ok(s.includes('+faststart'));
  assert.ok(s.includes('1080') && s.includes('1920'));
});

test('buildVideoCommand includes AAC audio codec and loops music', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const s = cmd.args.join(' ');
  assert.ok(s.includes('aac'));
  assert.ok(s.includes('aloop'));
});

test('buildVideoCommand uses xfade with correct transitions from scene.transitionOut', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const filter = cmd.args[cmd.args.indexOf('-filter_complex') + 1];
  assert.ok(filter.includes('xfade=transition=fade'));
  assert.ok(filter.includes('xfade=transition=wipeleft'));
  assert.ok(filter.includes('xfade=transition=slideup'));
});

test('buildVideoCommand uses zoompan filter per scene', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const filter = cmd.args[cmd.args.indexOf('-filter_complex') + 1];
  const zoompanCount = (filter.match(/zoompan/g) || []).length;
  assert.ok(zoompanCount >= 5, `expected at least 5 zoompan filters, got ${zoompanCount}`);
});

test('buildVideoCommand duration roughly matches sum of scenes minus xfade overlap', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const tIdx = cmd.args.indexOf('-t');
  const durArg = parseFloat(cmd.args[tIdx + 1]);
  // sum(2.5+2.0+2.0+2.0+2.5)=11, minus 4×0.6 overlap = 8.6. Allow ±0.5
  assert.ok(durArg > 8.0 && durArg < 9.5, `expected ~8.6, got ${durArg}`);
});

test('buildVideoCommand mixes HeyGen voice audio with music — Jorge must be heard (regression: 2026-05-06)', () => {
  // Hybrid Personal Reel: scene 1 hook (HeyGen), scenes 2-4 points (FLUX2 images), scene 5 cta (HeyGen).
  const hybridScenes = [
    { index: 1, duration: 2.5, videoPath: '/tmp/heygen_hook.mp4', transitionOut: 'crossfade' },
    { index: 2, duration: 2.0, imagePaths: ['/tmp/s2.jpg'], zoompan: { from: 1.0, to: 1.03 }, transitionOut: 'wipeleft',  kinetic: false },
    { index: 3, duration: 2.0, imagePaths: ['/tmp/s3.jpg'], zoompan: { from: 1.0, to: 1.03 }, transitionOut: 'crossfade', kinetic: false },
    { index: 4, duration: 2.0, imagePaths: ['/tmp/s4.jpg'], zoompan: { from: 1.0, to: 1.03 }, transitionOut: 'slideup',   kinetic: false },
    { index: 5, duration: 2.5, videoPath: '/tmp/heygen_cta.mp4', transitionOut: 'none' },
  ];
  const cmd = buildVideoCommand({ scenes: hybridScenes, musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const filter = cmd.args[cmd.args.indexOf('-filter_complex') + 1];
  // Voice tracks must be extracted from each HeyGen scene's audio stream.
  assert.ok(filter.includes('[0:a]'), 'must consume audio of scene 0 (HeyGen hook)');
  assert.ok(filter.includes('[4:a]'), 'must consume audio of scene 4 (HeyGen cta)');
  assert.ok(filter.includes('[va0]') && filter.includes('[va4]'), 'must label per-scene voice tracks');
  // Voice tracks must be delayed to their timeline positions (scene 4 lands well after t=0).
  assert.ok(/adelay=\d+\|\d+/.test(filter), 'must delay voice tracks via adelay');
  // Music must be dynamically ducked under voice via sidechain compression (broadcast-grade).
  assert.ok(filter.includes('sidechaincompress'), 'must use sidechaincompress for dynamic music ducking under voice');
  assert.ok(filter.includes('asplit=2'), 'voice must be split for sidechain trigger');
  assert.ok(filter.includes('volume=0.35'), 'music keeps full volume — sidechain compressor handles ducking');
});

test('buildVideoCommand keeps music-only path when no HeyGen scenes present', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const filter = cmd.args[cmd.args.indexOf('-filter_complex') + 1];
  assert.ok(filter.includes('volume=0.35'), 'music keeps full volume when no voice');
  assert.ok(!filter.includes('sidechaincompress'), 'no sidechain ducking needed when only music');
});

test('buildVideoCommand uses high-quality output codecs (CRF 20, AAC 192k @ 48kHz)', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const s = cmd.args.join(' ');
  assert.ok(s.includes('-crf 20'), 'visual quality CRF 20');
  assert.ok(s.includes('-preset medium'), 'medium preset for quality/speed balance');
  assert.ok(s.includes('-b:a 192k'), 'audio bitrate 192k');
  assert.ok(s.includes('-ar 48000'), 'audio sample rate 48kHz');
});
