import { spawn } from 'node:child_process';

const FPS = 30;
const XFADE_OVERLAP = 0.6;   // 2026-05-07: bumped from 0.3 → 0.6 for smoother cinematic transitions (Jorge feedback "muy robotico")

// IG Reels-style caption: heavy bold sans, white with black outline + semi-transparent box, lower-third safe zone.
// Uses textfile= so caption text bypasses ffmpeg's drawtext escape rules entirely (quotes/colons/commas safe).
// DejaVu Sans Bold is preinstalled on Ubuntu GHA runners; falls back to system default if absent.
const CAPTION_FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
function buildCaptionDrawtext(captionFile) {
  if (!captionFile) return '';
  const opts = [
    `fontfile=${CAPTION_FONT}`,
    `textfile=${captionFile}`,
    'reload=0',
    'fontsize=62',
    'fontcolor=white',
    'borderw=5',
    'bordercolor=black@0.95',
    'shadowcolor=black@0.7',
    'shadowx=2',
    'shadowy=3',
    'box=1',
    'boxcolor=black@0.45',
    'boxborderw=24',
    'line_spacing=12',
    'x=(w-text_w)/2',
    'y=h-text_h-220',           // ~11% from bottom — clear of IG/FB UI controls
  ].join(':');
  return `,drawtext=${opts}`;
}
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
    if (s.videoPath) {
      // HeyGen-style video clip: native input, no -loop.
      args.push('-i', s.videoPath);
    } else {
      args.push('-loop', '1', '-framerate', String(FPS), '-i', s.imagePaths[0]);
    }
  }
  args.push('-i', musicPath);

  const filterParts = [];
  scenes.forEach((s, i) => {
    if (s.videoPath) {
      // Video clip: scale/crop to canvas, trim to duration. No zoompan (avatar is the focal element).
      filterParts.push(
        `[${i}:v]scale=${width}:${height}:force_original_aspect_ratio=increase,crop=${width}:${height},setsar=1,fps=${FPS},trim=duration=${s.duration}[v${i}]`
      );
      return;
    }
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

  // Voice tracks from HeyGen scenes — each delayed to its timeline start so Jorge speaks at the right time.
  const sceneStarts = [];
  let acc = 0;
  scenes.forEach((s, i) => {
    sceneStarts.push(acc);
    acc += s.duration - (i < scenes.length - 1 ? XFADE_OVERLAP : 0);
  });

  const voiceLabels = [];
  scenes.forEach((s, i) => {
    if (!s.videoPath) return;
    const delayMs = Math.max(0, Math.round(sceneStarts[i] * 1000));
    const fadeOut = Math.min(XFADE_OVERLAP, s.duration / 4);
    const fadeOutStart = Math.max(0, s.duration - fadeOut).toFixed(2);
    filterParts.push(
      `[${i}:a]atrim=duration=${s.duration},asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.05,afade=t=out:st=${fadeOutStart}:d=${fadeOut.toFixed(2)},adelay=${delayMs}|${delayMs},volume=1.6[va${i}]`
    );
    voiceLabels.push(`[va${i}]`);
  });

  // Music: looped, full volume (sidechain compressor handles dynamic ducking when voice present).
  filterParts.push(`[${scenes.length}:a]volume=0.35,aloop=loop=-1:size=2e+09[amusic]`);

  if (voiceLabels.length === 0) {
    filterParts.push(`[amusic]anull[aout]`);
  } else {
    // Combine all voice tracks into one signal.
    let voiceLabel;
    if (voiceLabels.length === 1) {
      voiceLabel = voiceLabels[0];
    } else {
      filterParts.push(`${voiceLabels.join('')}amix=inputs=${voiceLabels.length}:duration=longest:dropout_transition=0:normalize=0[vall]`);
      voiceLabel = '[vall]';
    }
    // Split voice for sidechain trigger (broadcast-grade auto-ducking — music drops under speech, restores in pauses).
    filterParts.push(`${voiceLabel}asplit=2[vsig][vtrigger]`);
    filterParts.push(`[amusic][vtrigger]sidechaincompress=threshold=0.04:ratio=8:attack=10:release=300:makeup=1[mducked]`);
    filterParts.push(`[vsig][mducked]amix=inputs=2:duration=longest:dropout_transition=0:normalize=0[aout]`);
  }

  const filterComplex = filterParts.join(';');
  const totalDuration = scenes.reduce((t, s) => t + s.duration, 0) - XFADE_OVERLAP * (scenes.length - 1);

  args.push('-filter_complex', filterComplex);
  args.push('-map', scenes.length === 1 ? '[v0]' : '[vout]');
  args.push('-map', '[aout]');
  args.push('-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-r', String(FPS), '-preset', 'medium', '-crf', '20', '-profile:v', 'high', '-level', '4.0', '-movflags', '+faststart');
  args.push('-c:a', 'aac', '-b:a', '192k', '-ar', '48000');
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
