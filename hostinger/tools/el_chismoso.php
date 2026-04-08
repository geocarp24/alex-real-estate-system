<?php
/**
 * ============================================================
 *  EL CHISMOSO — Pinnacle Holdings Group LLC
 *  Webhook: Tracy skip trace → Contacts (upsert)
 *
 *  Triggered by: Airtable Automation when Tracy.status = "Found"
 *  Logic: Busca por Tracerfy ID → Actualiza si existe → Crea si no
 *
 *  URL: https://pinnaclegroupwi.com/Tools/el_chismoso.php
 *  Secret: Send header X-Chismoso-Token: pinnacle2026
 * ============================================================
 */

require_once 'config.php';

header('Content-Type: application/json');

// ── SECURITY TOKEN ───────────────────────────────────────────
define('CHISMOSO_TOKEN', 'pinnacle2026');
define('BASE_ID',        'appfQbDA750Oihy9J');
define('TABLE_TRACY',    'Tracy');
define('TABLE_CONTACTS', 'Contacts');

// ── VERIFY TOKEN ─────────────────────────────────────────────
$token = $_SERVER['HTTP_X_CHISMOSO_TOKEN'] ?? '';
if ($token !== CHISMOSO_TOKEN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── GET PAYLOAD ───────────────────────────────────────────────
$body      = json_decode(file_get_contents('php://input'), true);
$tracyId   = $body['record_id'] ?? '';  // Airtable Automation sends the Tracy record ID

if (!$tracyId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing record_id']);
    exit;
}

// ── STEP 1: Fetch Tracy record ────────────────────────────────
$tracyRecord = airtableGet(TABLE_TRACY, $tracyId);
if (!$tracyRecord) {
    http_response_code(404);
    echo json_encode(['error' => 'Tracy record not found: ' . $tracyId]);
    exit;
}

$tf = $tracyRecord['fields'] ?? [];

// Safety check — only process "success" records
$status = strtolower($tf['status'] ?? '');
if ($status !== 'success') {
    echo json_encode(['skipped' => true, 'reason' => 'Status is not success', 'status' => $status]);
    exit;
}

// ── STEP 2: Build Contact fields from Tracy data ──────────────
// Contacts table uses "Full Name" (not First/Last Name separately)
$firstName = trim($tf['first_name'] ?? '');
$lastName  = trim($tf['last_name']  ?? '');
$fullName  = trim("{$firstName} {$lastName}");

// Phone fields: Number type in Airtable → send as integer (raw digits only)
// Contacts table has Phone1, Phone2, Phone3 only (no Phone4)
$phone1 = phoneToInt($tf['primary_phone'] ?? '');
$phone2 = phoneToInt($tf['mobile_1']      ?? '');
// Phone3: use mobile_2, fallback to landline_1
$phone3 = phoneToInt($tf['mobile_2'] ?? '') ?: phoneToInt($tf['landline_1'] ?? '');

$contactFields = [
    'Full Name'     => $fullName,
    'Phone1 Type'   => $tf['primary_phone_type']    ?? '',
    'Email1'        => $tf['email_1']               ?? '',
    'Email2'        => $tf['email_2']               ?? '',
    'Mail Address'  => $tf['mail_address']          ?? '',
    'Mail City'     => $tf['mail_city']             ?? '',
    'Mail State'    => $tf['mail_state']            ?? '',
    'Mail Zip'      => $tf['mail_zip']              ?? '',
    'Lead Source'   => 'Skip Trace - Tracerfy',
    'Stage'         => 'To Be Contacted',
    'Tracerfy ID'   => intval($tf['tracerfy_id']    ?? 0),
];

// Only add phone fields if they have valid values
if ($phone1) $contactFields['Phone1'] = $phone1;
if ($phone2) $contactFields['Phone2'] = $phone2;
if ($phone3) $contactFields['Phone3'] = $phone3;

// Category = Single Select
if ($fullName) {
    $contactFields['Category'] = 'Seller';
}

// Remove empty string fields to avoid overwriting with blanks
$contactFields = array_filter($contactFields, function($v) {
    return $v !== '' && $v !== null && $v !== 0;
});

// ── STEP 3: Search existing Contact by Tracerfy ID ────────────
$tracerfyId  = intval($tf['tracerfy_id'] ?? 0);
$existingId  = null;

if ($tracerfyId) {
    $formula     = rawurlencode("{Tracerfy ID}={$tracerfyId}");
    $searchUrl   = 'https://api.airtable.com/v0/' . BASE_ID . '/' . rawurlencode(TABLE_CONTACTS)
                 . '?filterByFormula=' . $formula . '&maxRecords=1';

    $ch = curl_init($searchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN]
    ]);
    $res  = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);

    if (!empty($data['records'][0]['id'])) {
        $existingId = $data['records'][0]['id'];
    }
}

// FALLBACK: si no encontró por Tracerfy ID, buscar por Mail Address
if (!$existingId) {
    $mailAddr = trim($tf['mail_address'] ?? '');
    if ($mailAddr) {
        $formula2   = rawurlencode("LOWER({Mail Address})=LOWER('" . addslashes($mailAddr) . "')");
        $searchUrl2 = 'https://api.airtable.com/v0/' . BASE_ID . '/' . rawurlencode(TABLE_CONTACTS)
                    . '?filterByFormula=' . $formula2 . '&maxRecords=1';
        $ch2 = curl_init($searchUrl2);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN]
        ]);
        $res2  = curl_exec($ch2);
        curl_close($ch2);
        $data2 = json_decode($res2, true);
        if (!empty($data2['records'][0]['id'])) {
            $existingId = $data2['records'][0]['id'];
        }
    }
}

// ── STEP 4: Upsert Contact ────────────────────────────────────
if ($existingId) {
    // UPDATE existing contact
    $result = airtablePatch(TABLE_CONTACTS, $existingId, $contactFields);
    $action = 'updated';
    $contactRecordId = $existingId;
} else {
    // CREATE new contact
    $result = airtablePost(TABLE_CONTACTS, $contactFields);
    $action = 'created';
    $contactRecordId = $result['id'] ?? null;
}

if (!$contactRecordId) {
    http_response_code(500);
    echo json_encode([
        'error'  => 'Failed to upsert Contact',
        'action' => $action,
        'result' => $result
    ]);
    exit;
}

// ── STEP 5: Mark Tracy record as pushed ──────────────────────
airtablePatch(TABLE_TRACY, $tracyId, [
    'pushed_to_contacts' => true,
]);

// ── SUCCESS RESPONSE ─────────────────────────────────────────
echo json_encode([
    'success'          => true,
    'action'           => $action,         // "created" or "updated"
    'contact_id'       => $contactRecordId,
    'tracy_id'         => $tracyId,
    'tracerfy_id'      => $tracerfyId,
    'name'             => $fullName,
]);

// ─────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────

/**
 * Convert any phone format to integer (raw digits only)
 * Handles: "8594757302", "859-475-7302", "8594757302.0", "+18594757302"
 */
function phoneToInt($phone) {
    if (!$phone) return null;
    // Handle float notation from Airtable Number field (e.g., 8594757302.0)
    $digits = preg_replace('/[^0-9]/', '', strval(intval(floatval($phone))));
    if (strlen($digits) < 10) return null;
    return intval($digits);
}

/**
 * GET single Airtable record
 */
function airtableGet($table, $recordId) {
    $url = 'https://api.airtable.com/v0/' . BASE_ID . '/' . rawurlencode($table) . '/' . $recordId;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

/**
 * PATCH (update) Airtable record
 */
function airtablePatch($table, $recordId, $fields) {
    $url     = 'https://api.airtable.com/v0/' . BASE_ID . '/' . rawurlencode($table) . '/' . $recordId;
    $payload = json_encode(['fields' => $fields]);
    $ch      = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json'
        ]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

/**
 * POST (create) new Airtable record
 */
function airtablePost($table, $fields) {
    $url     = 'https://api.airtable.com/v0/' . BASE_ID . '/' . rawurlencode($table);
    $payload = json_encode(['fields' => $fields]);
    $ch      = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json'
        ]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}
