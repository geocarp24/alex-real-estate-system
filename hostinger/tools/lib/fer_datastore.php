<?php
// ============================================================
// FER — Make.com Data Store wrapper
// Stores per-contact conversation history keyed by phone.
//
// DataStore: "Fer Conversations"  (id 91636, structure 338257)
// Expected record shape (structure):
//   {
//     "phone":        "+19205551234",
//     "contactId":    "recXXXXXXXXXXXXXX",
//     "history":      "string (concatenated transcript)",
//     "messageCount": 0,
//     "isOwner":      "unknown|yes|no",
//     "motivation":   "unknown|foreclosure|divorce|...",
//     "timeline":     "unknown|ASAP|30d|...",
//     "urgency":      "unknown|hot|warm|cold",
//     "language":     "English|Spanish",
//     "lastUpdated":  "ISO8601"
//   }
// ============================================================

require_once __DIR__ . '/fer_logger.php';

if (!defined('FER_DS_ID')) {
    define('FER_DS_ID', '91636');
}
if (!defined('FER_DS_BASE')) {
    // Make US2 region based on hook URL seen in handoff.
    define('FER_DS_BASE', 'https://us2.make.com/api/v2/data-stores/' . FER_DS_ID . '/data');
}

function fer_ds_headers() {
    if (!defined('MAKE_API_TOKEN')) {
        fer_log_error('datastore_missing_token');
        return null;
    }
    return [
        'Authorization: Token ' . MAKE_API_TOKEN,
        'Content-Type: application/json',
    ];
}

function fer_ds_request($method, $url, $body = null) {
    $headers = fer_ds_headers();
    if ($headers === null) return ['ok' => false, 'error' => 'no_token'];

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code < 200 || $code >= 300) {
        fer_log_error('datastore_http_error', [
            'method' => $method, 'code' => $code, 'err' => $err,
            'body' => substr((string)$resp, 0, 500),
        ]);
        return ['ok' => false, 'error' => "HTTP $code", 'raw' => $resp];
    }
    return ['ok' => true, 'data' => json_decode($resp, true)];
}

/**
 * Fetch a conversation record by phone (used as the DataStore key).
 * Returns the record payload or null if not found.
 */
function fer_ds_get($phone) {
    $key = rawurlencode($phone);
    $res = fer_ds_request('GET', FER_DS_BASE . '/' . $key);
    if (!$res['ok']) return null;

    // Make returns { key, data: {...} } on hit.
    $data = $res['data']['data'] ?? null;
    return is_array($data) ? $data : null;
}

/**
 * Upsert a conversation record. Creates if missing, overwrites on hit.
 */
function fer_ds_upsert($phone, array $record) {
    $record['lastUpdated'] = date('c');
    $payload = ['key' => $phone, 'data' => $record];

    // POST /data creates; if key exists, Make returns 409 → fall back to PUT.
    $res = fer_ds_request('POST', FER_DS_BASE, $payload);
    if ($res['ok']) return true;

    // Try update if already exists.
    $key = rawurlencode($phone);
    $res2 = fer_ds_request('PATCH', FER_DS_BASE . '/' . $key, ['data' => $record]);
    return $res2['ok'];
}

/**
 * Append a turn to the transcript and persist.
 *
 * @param string $phone
 * @param array  $existing  current record (or null for fresh)
 * @param string $clientMsg
 * @param string $ferMsg
 * @param array  $metaPatch qualification fields to merge in (isOwner, motivation, ...)
 */
function fer_ds_append_turn($phone, $existing, $clientMsg, $ferMsg, array $metaPatch = []) {
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

    $history  = (string) ($record['history'] ?? '');
    $stamp    = date('Y-m-d H:i');
    $addition = "[{$stamp}] Client: " . trim((string)$clientMsg) . "\n"
              . "[{$stamp}] Fer: "    . trim((string)$ferMsg)    . "\n";

    $record['history']      = $history . $addition;
    $record['messageCount'] = intval($record['messageCount'] ?? 0) + 1;

    foreach (['isOwner','motivation','timeline','urgency','language','contactId'] as $k) {
        if (array_key_exists($k, $metaPatch) && $metaPatch[$k] !== null && $metaPatch[$k] !== '') {
            $record[$k] = $metaPatch[$k];
        }
    }

    return fer_ds_upsert($phone, $record);
}
