import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildSignature, uploadJpg, __setFetch } from '../src/cloudinary.mjs';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

test('buildSignature sorts params alphabetically and SHA1s with secret', () => {
  const sig = buildSignature({ timestamp: 1700000000, public_id: 'foo/bar', overwrite: 'true' }, 'SECRET');
  assert.equal(sig, '3cbc2194ff47efffae56eb4989f5f3a9f53795e4');
});

test('uploadJpg posts multipart with correct fields and returns secure_url', async () => {
  let capturedUrl, capturedMethod;
  __setFetch(async (url, opts) => {
    capturedUrl = url; capturedMethod = opts.method;
    assert.ok(opts.body, 'body must be set');
    return {
      ok: true, status: 200,
      json: async () => ({ secure_url: 'https://res.cloudinary.com/dzzlhhk0m/image/upload/v1/foo.jpg', public_id: 'foo', bytes: 12345 }),
    };
  });
  process.env.CLOUDINARY_NAME = 'dzzlhhk0m';
  process.env.CLOUDINARY_API_KEY = 'keytest';
  process.env.CLOUDINARY_API_SECRET = 'secrettest';

  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), 'clou-'));
  const p = path.join(tmp, 't.jpg');
  await fs.writeFile(p, Buffer.from([0xff, 0xd8, 0xff, 0xd9]));

  const url = await uploadJpg(p, { publicId: 'pinnacle/test_slide_1', folder: 'pinnacle-social-media' });

  assert.equal(capturedMethod, 'POST');
  assert.ok(capturedUrl.includes('dzzlhhk0m'));
  assert.ok(url.startsWith('https://res.cloudinary.com/'));
  await fs.rm(tmp, { recursive: true });
});

test('uploadJpg throws on non-2xx with status in error', async () => {
  __setFetch(async () => ({ ok: false, status: 401, text: async () => '{"error":"invalid sig"}' }));
  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), 'clou-'));
  const p = path.join(tmp, 't.jpg');
  await fs.writeFile(p, Buffer.from([0xff, 0xd8]));
  await assert.rejects(uploadJpg(p, { publicId: 'x', folder: 'y' }), /401/);
  await fs.rm(tmp, { recursive: true });
});
