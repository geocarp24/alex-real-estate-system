<?php
// ============================================================
// FER — Airtable wrapper for Contacts table
// Base:  appfQbDA750Oihy9J
// Table: tblacvw0Ss770x8l5 (Contacts)
// Fields of interest:
//   Phone1, Phone2, Phone3, Phone4 (11 digits, no +)
//   fldj0XPPR1LUk4Ydp = Stage
//   fldNHfz1RISdAnf4I = Last contact date
//   fldsiQ32VTT2NMT4W = Negotiation notes
//   Lenguage (typo intentional in schema)
// ============================================================

require_once __DIR__ . '/fer_logger.php';

if (!defined('FER_AT_BASE')) {
    define('FER_AT_BASE',  'appfQbDA750Oihy9J');
}
if (!defined('FER_AT_CONTACTS')) {
    define('FER_AT_CONTACTS', 'tblacvw0Ss770x8l5');
}

function fer_at_url($table, $path = '') {
    return 'https://api.airtable.com/v0/' . FER_AT_BASE . '/' . $table . $path;
}

function fer_at_headers() {
    if (!defined('AIRTABLE_TOKEN')) {
        fer_log_error('airtable_missing_token');
        return null;
    }
    return [
        'Authorization: Bearer ' . AIRTABLE_TOKEN,
        'Content-Type: application/json',
    ];
}

function fer_at_request($method, $url, $body = null) {
    $headers = fer_at_headers();
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
        fer_log_error('airtable_http_error', [
            'method' => $method, 'url' => $url, 'code' => $code,
            'err' => $err, 'body' => substr((string)$resp, 0, 500),
        ]);
        return ['ok' => false, 'error' => "HTTP $code", 'raw' => $resp];
    }
    $data = json_decode($resp, true);
    return ['ok' => true, 'data' => $data];
}

/**
 * Normalize a phone to 11-digit US form matching Airtable storage.
 */
function fer_at_phone_11($phone) {
    $digits = preg_replace('/[^0-9]/', '', (string) $phone);
    if (strlen($digits) === 10) $digits = '1' . $digits;
    return $digits;
}

/**
 * Find the first Contact whose Phone1..Phone4 matches.
 * Returns an Airtable record array or null.
 */
function fer_at_find_contact_by_phone($phone) {
    $needle = fer_at_phone_11($phone);
    if (!$needle) return null;

    $formula = sprintf(
        "OR({Phone1}='%s',{Phone2}='%s',{Phone3}='%s',{Phone4}='%s')",
        $needle, $needle, $needle, $needle
    );
    $url = fer_at_url(FER_AT_CONTACTS, '?' . http_build_query([
        'filterByFormula' => $formula,
        'maxRecords'      => 1,
    ]));

    $res = fer_at_request('GET', $url);
    if (!$res['ok']) return null;

    $records = $res['data']['records'] ?? [];
    return !empty($records) ? $records[0] : null;
}

/**
 * Create a minimal contact for an unknown inbound number.
 * Caller should update Stage/notes afterwards if desired.
 */
function fer_at_create_contact($phone, $extra = []) {
    $fields = array_merge([
        'Phone1'   => fer_at_phone_11($phone),
        'Stage'    => 'New Lead',
        'Lenguage' => 'English', // schema typo preserved
    ], $extra);

    $res = fer_at_request('POST', fer_at_url(FER_AT_CONTACTS), [
        'fields' => $fields,
    ]);
    if (!$res['ok']) return null;
    fer_log_info('airtable_contact_created', ['phone' => $phone]);
    return $res['data'];
}

/**
 * Patch Stage / Last contact date / Negotiation notes on an existing contact.
 *
 * @param string $recordId
 * @param array  $updates e.g. ['Stage'=>'Responded','Negotiation notes'=>'...']
 */
function fer_at_update_contact($recordId, array $updates) {
    if (empty($recordId) || empty($updates)) return false;

    $res = fer_at_request(
        'PATCH',
        fer_at_url(FER_AT_CONTACTS, '/' . rawurlencode($recordId)),
        ['fields' => $updates]
    );
    if ($res['ok']) {
        fer_log_info('airtable_contact_updated', ['id' => $recordId, 'keys' => array_keys($updates)]);
    }
    return $res['ok'];
}

/**
 * Append a short note to the existing Negotiation notes, preserving history.
 */
function fer_at_append_notes($existingNotes, $newLine) {
    $existingNotes = trim((string) $existingNotes);
    $stamp = '[' . date('Y-m-d H:i') . '] ';
    $line  = $stamp . $newLine;
    return $existingNotes === '' ? $line : ($existingNotes . "\n" . $line);
}
