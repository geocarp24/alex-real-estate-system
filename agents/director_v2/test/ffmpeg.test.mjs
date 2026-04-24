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

test('buildVideoCommand includes audio codec aac 128k and loops music', () => {
  const cmd = buildVideoCommand({ scenes: sampleScenes(), musicPath: '/tmp/m.mp3', outputPath: '/tmp/out.mp4' });
  const s = cmd.args.join(' ');
  assert.ok(s.includes('aac'));
  assert.ok(s.includes('128k'));
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
  // sum(2.5+2.0+2.0+2.0+2.5)=11, minus 4×0.3 overlap = 9.8. Allow ±0.5
  assert.ok(durArg > 9.0 && durArg < 10.5, `expected ~9.8, got ${durArg}`);
});
