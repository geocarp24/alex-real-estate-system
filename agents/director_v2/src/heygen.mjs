// HeyGen Avatar Video API v2 client
// Docs: https://docs.heygen.com/reference/create-an-avatar-video-v2
//
// Usage:
//   const { videoUrl, durationSec } = await generateAvatarVideo({
//     script: 'Hi, this is Jorge from Pinnacle...',
//     avatarId: env.HEYGEN_AVATAR_ID_JORGE,
//     voiceId: env.HEYGEN_VOICE_ID_JORGE_EN,
//     apiKey:  env.HEYGEN_API_KEY,
//     dimension: { width: 1080, height: 1920 },  // 9:16 Reel default
//     background: { type: 'color', value: '#000000' },
//   });
//   await downloadToFile(videoUrl, '/tmp/scene.mp4');

import { writeFile } from 'node:fs/promises';
import { withRetry } from './util/retry.mjs';

const BASE = 'https://api.heygen.com';

export class HeyGenFailedError extends Error {
  constructor(msg) { super(msg); this.name = 'HeyGenFailedError'; }
}

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }

async function postJson(path, body, apiKey) {
  const res = await _fetch(`${BASE}${path}`, {
    method: 'POST',
    headers: { 'X-Api-Key': apiKey, 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  if (!res.ok) throw new HeyGenFailedError(`HeyGen ${path} HTTP ${res.status}: ${await res.text()}`);
  return res.json();
}

async function getJson(path, apiKey) {
  const res = await _fetch(`${BASE}${path}`, { headers: { 'X-Api-Key': apiKey } });
  if (!res.ok) throw new HeyGenFailedError(`HeyGen ${path} HTTP ${res.status}: ${await res.text()}`);
  return res.json();
}

export async function generateAvatarVideo({
  script,
  avatarId,
  voiceId,
  apiKey,
  dimension = { width: 1080, height: 1920 },
  background = { type: 'color', value: '#000000' },
  avatarStyle = 'normal',
  pollIntervalMs = 10000,
  pollTimeoutMs = 600000,
}) {
  if (!script) throw new HeyGenFailedError('script required');
  if (!avatarId) throw new HeyGenFailedError('avatarId required');
  if (!voiceId) throw new HeyGenFailedError('voiceId required');
  if (!apiKey) throw new HeyGenFailedError('apiKey required');

  const create = await withRetry(
    () => postJson('/v2/video/generate', {
      video_inputs: [{
        character: { type: 'avatar', avatar_id: avatarId, avatar_style: avatarStyle },
        voice:     { type: 'text',   input_text: script,  voice_id: voiceId },
        background,
      }],
      dimension,
    }, apiKey),
    { attempts: 3, baseDelayMs: 2000 }
  );
  const videoId = create?.data?.video_id;
  if (!videoId) throw new HeyGenFailedError(`no video_id in response: ${JSON.stringify(create)}`);

  const deadline = Date.now() + pollTimeoutMs;
  while (Date.now() < deadline) {
    await new Promise(r => setTimeout(r, pollIntervalMs));
    const status = await getJson(`/v1/video_status.get?video_id=${videoId}`, apiKey);
    const s = status?.data?.status;
    if (s === 'completed') {
      return {
        videoUrl: status.data.video_url,
        thumbnailUrl: status.data.thumbnail_url,
        durationSec: status.data.duration,
        videoId,
      };
    }
    if (s === 'failed') throw new HeyGenFailedError(`HeyGen rendering failed: ${status.data.error?.message || 'unknown'}`);
  }
  throw new HeyGenFailedError(`HeyGen polling timed out after ${pollTimeoutMs}ms (video_id=${videoId})`);
}

export async function downloadVideo(url, destPath) {
  const res = await _fetch(url);
  if (!res.ok) throw new HeyGenFailedError(`download HTTP ${res.status}: ${url}`);
  const buf = Buffer.from(await res.arrayBuffer());
  await writeFile(destPath, buf);
  return { path: destPath, sizeBytes: buf.length };
}

// Pick voice_id by language. Spec.locale === 'es' → ES voice, else EN.
export function pickVoiceId(locale, env) {
  return locale === 'es' ? env.HEYGEN_VOICE_ID_JORGE_ES : env.HEYGEN_VOICE_ID_JORGE_EN;
}
