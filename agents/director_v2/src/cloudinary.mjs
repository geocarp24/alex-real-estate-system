import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { sanitizePublicId } from './util/sanitize.mjs';

let _fetch = globalThis.fetch;
export function __setFetch(fn) { _fetch = fn; }

export function buildSignature(params, apiSecret) {
  const keys = Object.keys(params).sort();
  const toSign = keys.map(k => `${k}=${params[k]}`).join('&') + apiSecret;
  return createHash('sha1').update(toSign).digest('hex');
}

export async function uploadVideo(localPath, {
  publicId, folder, cloudName, apiKey, apiSecret,
  overwrite = true,
  timestampProvider = () => Math.floor(Date.now() / 1000),
  fileReader = readFile,
} = {}) {
  const safePublicId = sanitizePublicId(publicId);
  const timestamp = timestampProvider();
  const params = {
    folder, public_id: safePublicId, resource_type: 'video',
    timestamp, overwrite: overwrite ? 'true' : 'false',
  };
  const signature = buildSignature(params, apiSecret);

  const form = new FormData();
  form.append('file', new Blob([await fileReader(localPath)]));
  form.append('api_key', apiKey);
  form.append('timestamp', String(timestamp));
  form.append('signature', signature);
  form.append('folder', folder);
  form.append('public_id', safePublicId);
  form.append('resource_type', 'video');
  form.append('overwrite', overwrite ? 'true' : 'false');

  const url = `https://api.cloudinary.com/v1_1/${cloudName}/video/upload`;
  const res = await _fetch(url, { method: 'POST', body: form });
  if (!res.ok) throw new Error(`Cloudinary video upload failed: HTTP ${res.status}: ${await res.text()}`);
  return res.json();
}
