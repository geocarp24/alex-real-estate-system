import { spawn } from 'node:child_process';

const FPS = 30;
const XFADE_OVERLAP = 0.3;
const TRANSITION_MAP = {
  crossfade: 'fade',
  wipeleft:  'wipeleft',
  slideup:   'slideup',
  cut:       'fade',
  none:      null,
};

export function buildVideoCommand({ scenes, musicPath, outputPath, width = 1080, height = 1920 }) {
  const args = ['-y'];

  for (const s of scenes) {
    args.push('-loop', '1', '-framerate', String(FPS), '-i', s.imagePaths[0]);
  }
  args.push('-i', musicPath);

  const filterParts = [];
  scenes.forEach((s, i) => {
    const z0 = s.zoompan?.from ?? 1.0;
    const z1 = s.zoompan?.to   ?? 1.0;
    const frames = Math.max(1, Math.round(FPS * s.duration));
    const zExpr = `min(${z0}+(${z1}-${z0})*on/${frames-1 || 1},${Math.max(z0, z1)})`;
    filterParts.push(
      `[${i}:v]scale=${width}:${height}:force_original_aspect_ratio=increase,crop=${width}:${height},zoompan=z='${zExpr}':d=${frames}:s=${width}x${height}:fps=${FPS}[v${i}]`
    );
  });

  let lastLabel = 'v0';
  let offset = scenes[0].duration - XFADE_OVERLAP;
  for (let i = 1; i < scenes.length; i++) {
    const prev = scenes[i - 1];
    const transition = TRANSITION_MAP[prev.transitionOut] || 'fade';
    const inLabel = `v${i}`;
    const outLabel = i === scenes.length - 1 ? 'vout' : `x${i}`;
    filterParts.push(
      `[${lastLabel}][${inLabel}]xfade=transition=${transition}:duration=${XFADE_OVERLAP}:offset=${offset.toFixed(2)}[${outLabel}]`
    );
    lastLabel = outLabel;
    offset += scenes[i].duration - XFADE_OVERLAP;
  }
  if (scenes.length === 1) lastLabel = 'v0';

  filterParts.push(`[${scenes.length}:a]volume=0.35,aloop=loop=-1:size=2e+09[aout]`);

  const filterComplex = filterParts.join(';');
  const totalDuration = scenes.reduce((t, s) => t + s.duration, 0) - XFADE_OVERLAP * (scenes.length - 1);

  args.push('-filter_complex', filterComplex);
  args.push('-map', scenes.length === 1 ? '[v0]' : '[vout]');
  args.push('-map', '[aout]');
  args.push('-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-r', String(FPS), '-movflags', '+faststart');
  args.push('-c:a', 'aac', '-b:a', '128k');
  args.push('-t', totalDuration.toFixed(2));
  args.push(outputPath);

  return { bin: 'ffmpeg', args };
}

export function runFfmpeg(cmd, { onStderr } = {}) {
  return new Promise((resolve, reject) => {
    const proc = spawn(cmd.bin, cmd.args, { stdio: ['ignore', 'pipe', 'pipe'] });
    let stderrBuf = '';
    proc.stderr.on('data', chunk => {
      const text = chunk.toString();
      stderrBuf += text;
      if (onStderr) onStderr(text);
    });
    proc.on('close', code => {
      if (code === 0) resolve({ stderr: stderrBuf });
      else {
        const lastLine = stderrBuf.trim().split('\n').pop() || 'ffmpeg failed';
        reject(new Error(`ffmpeg exit=${code}: ${lastLine}`));
      }
    });
    proc.on('error', reject);
  });
}
