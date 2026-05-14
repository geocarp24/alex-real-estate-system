<?php
/**
 * ============================================================
 *  EL CHISMOSO — Pinnacle Holdings Group LLC
 *  Webhook: Tracy skip trace → Contacts (upsert)
 *
 *  URL: https://pinnaclegroupwi.com/Tools/el_chismoso.php
 *  Secret: Send header X-Chismoso-Token: pinnacle2026
 * ============================================================
 */

require_once 'config.php';

header('Content-Type: application/json');

define('CHISMOSO_TOKEN', 'pinnacle2026');
define('BASE_ID',        '[REDACTED_AIRTABLE_BASE_ID]');
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
$body    = json_decode(file_get_contents('php://input'), true);
$tracyId = $body['record_id'] ?? '';

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

// Only process "success" records
$status = strtolower($tf['status'] ?? '');
if ($status !== 'success') {
    echo json_encode(['skipped' => true, 'reason' => 'Status is not success', 'status' => $status]);
    exit;
}

// ── STEP 2: Build Contact fields from Tracy data ──────────────
// Full Name = first_name + last_name combinados (campo correcto en Airtable)
$firstName = trim($tf['first_name'] ?? '');
$lastName  = trim($tf['last_name']  ?? '');
$fullName  = trim("{$firstName} {$lastName}");

// Teléfonos: formato E.164 sin "+" → número entero (ej: 19204991818)
// Airtable Phone1/Phone2 son tipo Number → no acepta "+"
$phone1 = phoneToInt($tf['primary_phone'] ?? '');
$phone2 = phoneToInt($tf['mobile_1']      ?? '');

$contactFields = [
    'Full Name'     => $fullName,
    'Owner Address' => $tf['mail_address'] ?? '',
    'Mail City'     => $tf['mail_city']    ?? '',
    'Mail State'    => $tf['mail_state']   ?? '',
    'Email1'        => $tf['email_1']      ?? '',
    'Email2'        => $tf['email_2']      ?? '',
    'Lead Source'   => 'Skip Trace - Tracerfy',
    'Stage'         => 'To Be Contacted',
    'Tracerfy ID'   => intval($tf['tracerfy_id'] ?? 0),
    'Category'      => 'Seller',
];

// Solo agrega teléfonos si tienen valor válido
if ($phone1) $contactFields['Phone1'] = $phone1;
if ($phone2) $contactFields['Phone2'] = $phone2;

// Limpia campos vacíos para no sobreescribir con blancos
$contactFields = array_filter($contactFields, function($v) {
    return $v !== '' && $v !== null && $v !== 0;
});

// ── STEP 3: Busca si ya existe el Contact por Tracerfy ID ─────
$tracerfyId = intval($tf['tracerfy_id'] ?? 0);
$existingId = null;

if ($tracerfyId) {
    $formula   = rawurlencode("{Tracerfy ID}={$tracerfyId}");
    $searchUrl = 'https://api.airtable.com/v0/' . BASE_ID . '/' . rawurlencode(TABLE_CONTACTS)
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

// ── STEP 4: Upsert Contact ────────────────────────────────────
if ($existingId) {
    $result          = airtablePatch(TABLE_CONTACTS, $existingId, $contactFields);
    $action          = 'updated';
    $contactRecordId = $existingId;
} else {
    $result          = airtablePost(TABLE_CONTACTS, $contactFields);
    $action          = 'created';
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

// ── STEP 5: Marca Tracy como pushed ──────────────────────────
airtablePatch(TABLE_TRACY, $tracyId, [
    'pushed_to_contacts' => true,
]);

// ── RESPUESTA EXITOSA ─────────────────────────────────────────
echo json_encode([
    'success'     => true,
    'action'      => $action,
    'contact_id'  => $contactRecordId,
    'tracy_id'    => $tracyId,
    'tracerfy_id' => $tracerfyId,
    'name'        => $fullName,
]);


// ═══════════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════════

/**
 * Convierte teléfono a entero E.164 sin "+"
 * Ejemplos: "920-499-1818" → 19204991818
 *           "9204991818"   → 19204991818
 *           "+19204991818" → 19204991818
 */
function phoneToInt($phone): ?int {
    if (!$phone) return null;
    // Maneja notación float de Airtable (ej: 9204991818.0)
    $digits = preg_replace('/[^0-9]/', '', strval(intval(floatval($phone))));
    if (strlen($digits) < 10) return null;
    // Agrega el 1 de país si es número de 10 dígitos
    if (strlen($digits) === 10) $digits = '1' . $digits;
    return intval($digits);
}

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
