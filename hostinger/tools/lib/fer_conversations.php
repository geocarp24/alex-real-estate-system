<?php
// ============================================================
// FER — Local file-based conversation history
// Replaces Make DataStore (which has structure mismatches).
// Stores one JSON file per phone in fer_conversations/.
// Zero external API dependency.
// ============================================================

require_once __DIR__ . '/fer_logger.php';

if (!defined('FER_CONV_DIR')) {
    define('FER_CONV_DIR', __DIR__ . '/../fer_conversations');
}

function fer_conv_ensure_dir() {
    if (!is_dir(FER_CONV_DIR)) {
        @mkdir(FER_CONV_DIR, 0755, true);
    }
}

function fer_conv_path($phone) {
    $safe = preg_replace('/[^0-9]/', '', (string) $phone);
    return FER_CONV_DIR . '/' . $safe . '.json';
}

/**
 * Load conversation record for a phone number.
 * Returns array or null if no prior conversation.
 */
function fer_conv_get($phone) {
    $path = fer_conv_path($phone);
    if (!is_file($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

/**
 * Save/overwrite conversation record.
 */
function fer_conv_save($phone, array $record) {
    fer_conv_ensure_dir();
    $record['lastUpdated'] = date('c');
    $ok = file_put_contents(fer_conv_path($phone), json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $ok !== false;
}

/**
 * Append a turn and persist.
 */
function fer_conv_append_turn($phone, $existing, $clientMsg, $ferMsg, array $metaPatch = []) {
    $record = is_array($existing) ? $existing : [
        'phone'        => $phone,
        'contactId'    => null,
        'history'      => '',
        'messageCount' => 0,
        'isOwner'      => 'unknown',
        'motivation'   => 'unknown',
        'timeline'     => 'unknown',
        'urgency'      => 'unknown',
        'language'     => 'English',
    ];

    $stamp    = date('Y-m-d H:i');
    $addition = "[{$stamp}] Client: " . trim((string)$clientMsg) . "\n"
              . "[{$stamp}] Fer: "    . trim((string)$ferMsg)    . "\n";

    $record['history']      = ($record['history'] ?? '') . $addition;
    $record['messageCount'] = intval($record['messageCount'] ?? 0) + 1;

    foreach (['isOwner','motivation','timeline','urgency','language','contactId'] as $k) {
        if (array_key_exists($k, $metaPatch) && $metaPatch[$k] !== null && $metaPatch[$k] !== '' && $metaPatch[$k] !== 'unknown') {
            $record[$k] = $metaPatch[$k];
        }
    }

    return fer_conv_save($phone, $record);
}
