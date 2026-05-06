// HeyGen Avatar Video API v3 client (validated 2026-05-06).
// Docs: https://developers.heygen.com (POST /v3/videos)
//
// Returns a path to a downloaded MP4 ready for ffmpeg compose.
//
// Required env (passed via env arg, set by GHA secrets):
//   HEYGEN_API_KEY
//   HEYGEN_AVATAR_ID_JORGE
//   HEYGEN_VOICE_ID_JORGE_EN | HEYGEN_VOICE_ID_JORGE_ES (auto-selected by scene.locale)

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
    headers: { 'X-API-Key': apiKey, 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  if (!res.ok) throw new HeyGenFailedError(`HeyGen ${path} HTTP ${res.status}: ${await res.text()}`);
  return res.json();
}

async function getJson(path, apiKey) {
  const res = await _fetch(`${BASE}${path}`, { headers: { 'X-API-Key': apiKey } });
  if (!res.ok) throw new HeyGenFailedError(`HeyGen ${path} HTTP ${res.status}: ${await res.text()}`);
  return res.json();
}

export async function generateAvatarVideo({
  script,
  avatarId,
  voiceId,
  apiKey,
  aspectRatio  = '9:16',
  resolution   = '1080p',
  expressiveness = 'high',  // photo_avatar only — ignored on digital_twin
  motionPrompt = 'professional confident speaker, natural subtle hand gestures, warm engaging facial expression',
  background   = { type: 'color', value: '#0d1117' },  // can also be { type: 'image', url: '...' }
  pollIntervalMs = 5000,
  pollTimeoutMs  = 600000,
}) {
  if (!script)   throw new HeyGenFailedError('script required');
  if (!avatarId) throw new HeyGenFailedError('avatarId required');
  if (!voiceId)  throw new HeyGenFailedError('voiceId required');
  if (!apiKey)   throw new HeyGenFailedError('apiKey required');

  const payload = {
    type: 'avatar',
    avatar_id: avatarId,
    script,
    voice_id: voiceId,
    aspect_ratio: aspectRatio,
    resolution,
    background,
  };
  // expressiveness + motion_prompt only apply to photo_avatars; HeyGen ignores them on digital_twin.
  if (expressiveness) payload.expressiveness = expressiveness;
  if (motionPrompt)   payload.motion_prompt  = motionPrompt;

  const create = await withRetry(
    () => postJson('/v3/videos', payload, apiKey),
    { attempts: 3, baseDelayMs: 2000 }
  );
  const videoId = create?.data?.video_id;
  if (!videoId) throw new HeyGenFailedError(`no video_id in response: ${JSON.stringify(create)}`);

  // Poll until complete
  const deadline = Date.now() + pollTimeoutMs;
  while (Date.now() < deadline) {
    await new Promise(r => setTimeout(r, pollIntervalMs));
    const status = await getJson(`/v1/video_status.get?video_id=${videoId}`, apiKey);
    const s = status?.data?.status;
    if (s === 'completed') {
      return {
        videoUrl:    status.data.video_url,
        thumbnailUrl: status.data.thumbnail_url,
        durationSec:  status.data.duration,
        videoId,
      };
    }
    if (s === 'failed') throw new HeyGenFailedError(`HeyGen rendering failed: ${JSON.stringify(status.data.error || {})}`);
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
