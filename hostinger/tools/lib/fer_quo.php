<?php
// ============================================================
// FER — Quo (OpenPhone) SMS sender
// ============================================================

require_once __DIR__ . '/fer_logger.php';

if (!defined('FER_QUO_FROM_NUMBER_ID')) {
    // Phone Number ID, not the E.164 number.
    define('FER_QUO_FROM_NUMBER_ID', 'PNNlYlSvAb');
}

/**
 * Normalize a phone to E.164 (+1XXXXXXXXXX) for US numbers.
 */
function fer_quo_normalize_phone($raw) {
    $digits = preg_replace('/[^0-9]/', '', (string) $raw);
    if (strlen($digits) === 10) $digits = '1' . $digits;
    return '+' . $digits;
}

/**
 * Send an SMS through the Quo (OpenPhone) API.
 *
 * @return array { success: bool, http_code: int, body: string|null, error: string|null }
 */
function fer_quo_send_sms($to, $content) {
    if (!defined('QUO_API_KEY')) {
        fer_log_error('quo_missing_key');
        return ['success' => false, 'http_code' => 0, 'body' => null, 'error' => 'QUO_API_KEY undefined'];
    }
    if (empty($to) || empty($content)) {
        return ['success' => false, 'http_code' => 0, 'body' => null, 'error' => 'empty to or content'];
    }

    $toE164 = fer_quo_normalize_phone($to);

    $payload = json_encode([
        'content' => $content,
        'from'    => FER_QUO_FROM_NUMBER_ID,
        'to'      => [$toE164],
    ]);

    $ch = curl_init('https://api.openphone.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: ' . QUO_API_KEY,
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $success = ($code >= 200 && $code < 300);
    if ($success) {
        fer_log_info('sms_sent', ['to' => $toE164, 'len' => strlen($content)]);
    } else {
        fer_log_error('sms_failed', ['code' => $code, 'err' => $err, 'body' => substr((string)$body, 0, 500)]);
    }

    return [
        'success'   => $success,
        'http_code' => $code,
        'body'      => $body,
        'error'     => $success ? null : ($err ?: 'HTTP ' . $code),
    ];
}
