// Cloudinary signed upload via native fetch + FormData.
// Secrets from env (via doppler run): CLOUDINARY_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET.

import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }

function env(name) {
  const v = process.env[name];
  if (!v) throw new Error(`Missing env ${name} (expected via doppler run)`);
  return v;
}

export function buildSignature(params, apiSecret) {
  const keys = Object.keys(params).sort();
  const str = keys.map(k => `${k}=${params[k]}`).join('&') + apiSecret;
  return createHash('sha1').update(str).digest('hex');
}

export async function uploadJpg(localPath, { publicId, folder, overwrite = true }) {
  if (!publicId) throw new Error('uploadJpg: publicId required');
  const cloudName = env('CLOUDINARY_NAME');
  const apiKey = env('CLOUDINARY_API_KEY');
  const apiSecret = env('CLOUDINARY_API_SECRET');
  const timestamp = Math.floor(Date.now() / 1000);

  const signedParams = { folder, overwrite: String(overwrite), public_id: publicId, timestamp };
  const signature = buildSignature(signedParams, apiSecret);

  const buf = await readFile(localPath);
  const blob = new Blob([buf], { type: 'image/jpeg' });
  const form = new FormData();
  form.append('file', blob, path.basename(localPath));
  form.append('api_key', apiKey);
  form.append('timestamp', String(timestamp));
  form.append('public_id', publicId);
  form.append('folder', folder);
  form.append('overwrite', String(overwrite));
  form.append('signature', signature);

  const url = `https://api.cloudinary.com/v1_1/${cloudName}/image/upload`;
  const res = await _fetch(url, { method: 'POST', body: form });
  if (!res.ok) {
    const body = typeof res.text === 'function' ? await res.text() : '';
    throw new Error(`Cloudinary upload failed: ${res.status} ${body.slice(0, 200)}`);
  }
  const data = await res.json();
  return data.secure_url;
}

export async function uploadCarousel(jpgPaths, { recordId, week = 0, folder = 'pinnacle-social-media' }) {
  const urls = [];
  for (let i = 0; i < jpgPaths.length; i++) {
    const slideIdx = i + 1;
    const publicId = `${folder}/pinnacle_s${week}_carousel_${recordId}/slide_${slideIdx}`;
    const url = await uploadJpg(jpgPaths[i], { publicId, folder });
    urls.push(url);
  }
  return urls;
}
