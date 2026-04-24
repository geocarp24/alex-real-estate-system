# Director v2 — Music Track Licenses

## ⚠️ STUBS — REPLACE BEFORE PRODUCTION POC

The 5 `.mp3` files in this directory are **silent placeholder stubs** generated with ffmpeg `anullsrc`. They are 30 seconds of digital silence at 128kbps stereo, used so the pipeline tests + ffmpeg audio mix work end-to-end.

**Before generating the Task 14 POC video for Jorge's review, these stubs MUST be replaced with real royalty-free instrumental tracks** matching the moods. Procedure:

1. Visit https://pixabay.com/music/ and search each phrase below
2. Pick the top result that matches the mood and is 15-60 seconds
3. Verify the page shows "Pixabay Content License" or "CC0" — both are royalty-free for commercial use, no attribution required
4. Download and save to this directory with the exact filename listed below

| Filename | Search phrase | Mood | Status |
|---|---|---|---|
| upbeat_1.mp3   | "upbeat corporate" | upbeat | ⚠️ STUB (silent) |
| upbeat_2.mp3   | "upbeat energy" | upbeat | ⚠️ STUB (silent) |
| chill_1.mp3    | "chill lofi real estate" | chill | ⚠️ STUB (silent) |
| cinematic_1.mp3| "cinematic inspirational" | cinematic | ⚠️ STUB (silent) |
| tension_1.mp3  | "dramatic build up" | tension | ⚠️ STUB (silent) |

After replacing each, update the row to:

| Filename | Source URL | License | Mood |
|---|---|---|---|
| upbeat_1.mp3 | https://pixabay.com/music/<actual-slug>/ | Pixabay Content License | upbeat |

The Director v2 pipeline does not care whether the file is silent or contains music — it just looks up by mood prefix and concatenates with the rendered scenes via ffmpeg's `amix` filter. Real tracks improve perceived quality but do not change the test results.
