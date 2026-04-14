<?php
// ============================================================
// FER — Unified logger
// Writes structured entries to fer_agent.log in the Tools dir.
// Falls back to error_log() if the file is unwritable.
// ============================================================

if (!defined('FER_LOG_PATH')) {
    define('FER_LOG_PATH', __DIR__ . '/../fer_agent.log');
}
if (!defined('FER_LOG_MAX_BYTES')) {
    define('FER_LOG_MAX_BYTES', 5 * 1024 * 1024); // 5 MB rotation threshold
}

/**
 * Log a structured line.
 *
 * @param string $level   DEBUG | INFO | WARN | ERROR
 * @param string $event   short event tag e.g. "webhook_received"
 * @param array  $context key-value data, will be JSON-encoded
 */
function fer_log($level, $event, array $context = []) {
    $line = json_encode([
        'ts'      => date('c'),
        'level'   => strtoupper($level),
        'event'   => $event,
        'context' => $context,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

    // Rotate if the file got too big.
    if (is_file(FER_LOG_PATH) && filesize(FER_LOG_PATH) > FER_LOG_MAX_BYTES) {
        @rename(FER_LOG_PATH, FER_LOG_PATH . '.1');
    }

    $written = @file_put_contents(FER_LOG_PATH, $line, FILE_APPEND | LOCK_EX);
    if ($written === false) {
        error_log('[FER] ' . rtrim($line));
    }
}

function fer_log_debug($event, array $ctx = []) { fer_log('DEBUG', $event, $ctx); }
function fer_log_info ($event, array $ctx = []) { fer_log('INFO',  $event, $ctx); }
function fer_log_warn ($event, array $ctx = []) { fer_log('WARN',  $event, $ctx); }
function fer_log_error($event, array $ctx = []) { fer_log('ERROR', $event, $ctx); }
