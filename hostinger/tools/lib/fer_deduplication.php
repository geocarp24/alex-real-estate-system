<?php
// ============================================================
// FER — Deduplication
// Protects against Quo retrying the same webhook.
// Uses touch-files under /Tools/fer_dedup/. One file per messageId.
// Files older than TTL are auto-pruned on each hit (cheap cron-less GC).
// ============================================================

require_once __DIR__ . '/fer_logger.php';

if (!defined('FER_DEDUP_DIR')) {
    define('FER_DEDUP_DIR', __DIR__ . '/../fer_dedup');
}
if (!defined('FER_DEDUP_TTL_SECONDS')) {
    define('FER_DEDUP_TTL_SECONDS', 24 * 60 * 60); // 24h
}

function fer_dedup_ensure_dir() {
    if (!is_dir(FER_DEDUP_DIR)) {
        @mkdir(FER_DEDUP_DIR, 0755, true);
    }
}

/**
 * Return true the first time a messageId is seen, false on repeats.
 * Also prunes expired markers opportunistically (1-in-20 requests).
 */
function fer_dedup_claim($messageId) {
    if (empty($messageId)) {
        fer_log_warn('dedup_missing_id');
        return true; // fail-open: if no id, treat as unique
    }

    fer_dedup_ensure_dir();

    $safeId = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $messageId);
    $path   = FER_DEDUP_DIR . '/' . $safeId;

    // Opportunistic GC: 5% chance per request, keeps dir tidy without cron.
    if (mt_rand(1, 20) === 1) {
        fer_dedup_gc();
    }

    if (is_file($path)) {
        $age = time() - filemtime($path);
        if ($age < FER_DEDUP_TTL_SECONDS) {
            fer_log_info('dedup_duplicate', ['id' => $messageId, 'age_s' => $age]);
            return false;
        }
    }

    // Atomic create-or-fail via fopen('x').
    $fh = @fopen($path, 'x');
    if ($fh === false) {
        // Someone beat us to it between stat and create — treat as duplicate.
        fer_log_warn('dedup_race', ['id' => $messageId]);
        return false;
    }
    fwrite($fh, (string) time());
    fclose($fh);
    return true;
}

function fer_dedup_gc() {
    if (!is_dir(FER_DEDUP_DIR)) return;
    $cutoff = time() - FER_DEDUP_TTL_SECONDS;
    foreach (glob(FER_DEDUP_DIR . '/*') ?: [] as $f) {
        if (is_file($f) && filemtime($f) < $cutoff) {
            @unlink($f);
        }
    }
}
