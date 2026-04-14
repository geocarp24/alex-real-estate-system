<?php
// ============================================================
// FER — Telegram alerts to Jorge
// Non-blocking: failures are logged but do not abort the flow.
// ============================================================

require_once __DIR__ . '/fer_logger.php';

/**
 * Send a formatted alert to Jorge's Telegram.
 *
 * @param array $payload {
 *   contactName, clientPhone, propertyAddress,
 *   escalateReason, isOwner, motivation, timeline, urgency,
 *   clientMessage, ferResponse
 * }
 * @return bool success
 */
function fer_telegram_alert(array $payload) {
    if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
        fer_log_error('telegram_missing_creds');
        return false;
    }

    $msg = "FER ESCALA A JORGE\n\n"
         . "Nombre: "     . ($payload['contactName']     ?? 'N/A') . "\n"
         . "Tel: "        . ($payload['clientPhone']     ?? 'N/A') . "\n"
         . "Propiedad: "  . ($payload['propertyAddress'] ?? 'N/A') . "\n"
         . "Razon: "      . ($payload['escalateReason']  ?? 'N/A') . "\n\n"
         . "Dueno: "      . ($payload['isOwner']    ?? 'N/A') . "\n"
         . "Motivacion: " . ($payload['motivation'] ?? 'N/A') . "\n"
         . "Timeline: "   . ($payload['timeline']   ?? 'N/A') . "\n"
         . "Urgencia: "   . ($payload['urgency']    ?? 'N/A') . "\n\n"
         . "Ultimo msg cliente:\n" . ($payload['clientMessage'] ?? '') . "\n\n"
         . "Fer respondio:\n" . ($payload['ferResponse'] ?? '(vacio)') . "\n\n"
         . "Tu turno.";

    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'chat_id' => TELEGRAM_CHAT_ID,
            'text'    => $msg,
        ]),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code !== 200) {
        fer_log_error('telegram_failed', ['code' => $code, 'err' => $err, 'body' => substr((string)$resp, 0, 500)]);
        return false;
    }
    fer_log_info('telegram_sent', ['contact' => $payload['contactName'] ?? null]);
    return true;
}
