<?php
/**
 * ============================================================
 *  EL POLLING — Tracy Skip Trace Cron
 *  Hostinger Cron: every 5 minutes
 *  URL: https://pinnaclegroupwi.com/Tools/el_polling.php
 *
 *  Flow (ONE lead per execution):
 *   1. Query Leads: Stage='Review this Deal' AND Skip Trace Done=false
 *   2. Create Tracy record (status=pending)
 *   3. Build CSV and upload to Tracerfy
 *   4. Poll Tracerfy queue until results arrive
 *   5. Update Tracy (status=success|error)
 *   6. POST to el_chismoso.php (writes to Contacts)
 *   7. Update Lead: Skip Trace Done=true, Stage='To be Contacted'
 * ============================================================
 */

require_once 'config.php';

set_time_limit(180);       // 3 min max — plenty for 1 lead
ini_set('memory_limit', '64M');

// ── CONSTANTS ─────────────────────────────────────────────────
define('BASE_ID',          'appfQbDA750Oihy9J');
define('AIRTABLE_BASE',    'https://api.airtable.com/v0/' . BASE_ID);
define('TABLE_LEADS',      'tblxZz2EWIglOLnEd');
define('TABLE_TRACY',      'tbl6CJm4kYspOuTDB');
define('TRACERFY_BASE',    'https://tracerfy.com/v1/api');
define('CHISMOSO_URL',     'https://pinnaclegroupwi.com/Tools/el_chismoso.php');
define('CHISMOSO_TOKEN',   'pinnacle2026');

// Keep last 500 lines of log (rotate on each run)
$logFile = __DIR__ . '/el_polling.log';


// ═══════════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════════

function logMsg(string $msg): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
}

function rotateLog(): void {
    global $logFile;
    if (!file_exists($logFile)) return;
    $lines = file($logFile);
    if (count($lines) > 500) {
        file_put_contents($logFile, implode('', array_slice($lines, -500)));
    }
}

// ── Airtable GET (list with filter) ──────────────────────────
function atList(string $tableId, array $params = []): array {
    $url = AIRTABLE_BASE . '/' . $tableId;
    if ($params) $url .= '?' . http_build_query($params);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

// ── Airtable POST (create record) ────────────────────────────
function atPost(string $tableId, array $fields): array {
    $url = AIRTABLE_BASE . '/' . $tableId;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['fields' => $fields]),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

// ── Airtable PATCH (update record) ───────────────────────────
function atPatch(string $tableId, string $recordId, array $fields): array {
    $url = AIRTABLE_BASE . '/' . $tableId . '/' . $recordId;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode(['fields' => $fields]),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

// ── Upload CSV to Tracerfy ────────────────────────────────────
function tracerfyUpload(string $csvPath): array {
    $url  = TRACERFY_BASE . '/trace/';
    $file = new CURLFile($csvPath, 'text/csv', 'trace_input.csv');
    $ch   = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'csv_file'             => $file,
            'address_column'       => 'address',
            'city_column'          => 'city',
            'state_column'         => 'state',
            'first_name_column'    => 'first_name',
            'last_name_column'     => 'last_name',
            'mail_address_column'  => 'mail_address',
            'mail_city_column'     => 'mail_city',
            'mail_state_column'    => 'mail_state',
        ],
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . TRACERFY_TOKEN],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

// ── Poll Tracerfy queue until array response ──────────────────
function tracerfyPoll(int $queueId): ?array {
    $url   = TRACERFY_BASE . '/queue/' . $queueId;
    $waits = [10, 15, 15, 15, 15, 20, 20, 20, 20, 20];

    foreach ($waits as $i => $wait) {
        logMsg("  Poll " . ($i + 1) . '/' . count($waits) . " — waiting {$wait}s for queue {$queueId}");
        sleep($wait);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . TRACERFY_TOKEN],
            CURLOPT_TIMEOUT        => 20,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $trimmed = ltrim((string) $raw);
        if ($code === 200 && isset($trimmed[0]) && $trimmed[0] === '[') {
            $data = json_decode($raw, true);
            logMsg("  Queue {$queueId} complete — " . count($data) . " contact(s) found");
            return $data;
        }

        logMsg("  Queue {$queueId} still processing (HTTP {$code})");
    }

    return null;
}

// ── Convert phone to integer (raw digits only) ────────────────
function phoneToInt($phone): ?int {
    if (!$phone) return null;
    $digits = preg_replace('/[^0-9]/', '', strval(intval(floatval($phone))));
    if (strlen($digits) < 10) return null;
    return (int) $digits;
}


// ═══════════════════════════════════════════════════════════════
//  MAIN
// ═══════════════════════════════════════════════════════════════

rotateLog();
logMsg('══════════════════════════════════════');
logMsg('EL POLLING started');

// ── STEP 1: Fetch one qualifying Lead ─────────────────────────
// Only process WI leads — Tracerfy coverage is Wisconsin-based
// IL and other out-of-market states fail with "No valid rows" error
$formula = "AND({Stage}='Review this Deal',{Skip Trace Done}=FALSE(),{Estate}='WI')";
$listData = atList(TABLE_LEADS, [
    'filterByFormula'    => $formula,
    'maxRecords'         => 1,
    'sort[0][field]'     => 'Dated Added',
    'sort[0][direction]' => 'asc',
]);

if (empty($listData['records'])) {
    logMsg('No leads pending skip trace. Nothing to do.');
    logMsg('══════════════════════════════════════');
    exit(0);
}

$lead    = $listData['records'][0];
$leadId  = $lead['id'];
$lf      = $lead['fields'] ?? [];

$address   = trim($lf['Address']  ?? '');
$city      = trim($lf['City']     ?? '');
$state     = trim($lf['Estate']   ?? '');
$zipRaw    = $lf['Zip Code'] ?? '';
$zip       = $zipRaw ? strval((int) $zipRaw) : '';

if (!$address) {
    logMsg("Lead {$leadId} has no address — skipping.");
    exit(0);
}

logMsg("Lead: {$address}, {$city}, {$state} {$zip}  (ID: {$leadId})");


// ── STEP 2: Create Tracy record (pending) ─────────────────────
$tracyFields = array_filter([
    'address'       => $address,
    'city'          => $city,
    'state'         => $state,
    'zip'           => $zip,
    'fecha_rastreo' => gmdate('Y-m-d\TH:i:s.000\Z'),
    'status'        => 'pending',
    'notas'         => 'Iniciado por el_polling.php (cron)',
], fn($v) => $v !== '' && $v !== null);

$tracyResult = atPost(TABLE_TRACY, $tracyFields);
$tracyId     = $tracyResult['id'] ?? null;

if (!$tracyId) {
    logMsg('ERROR creating Tracy record: ' . json_encode($tracyResult));
    exit(1);
}
logMsg("Tracy record created: {$tracyId}");


// ── STEP 3: Build CSV ─────────────────────────────────────────
$csvPath = sys_get_temp_dir() . '/tracy_' . time() . '_' . getmypid() . '.csv';
$fh      = fopen($csvPath, 'w');
fputcsv($fh, ['address', 'city', 'state', 'zip', 'first_name', 'last_name',
               'mail_address', 'mail_city', 'mail_state']);
fputcsv($fh, [$address, $city, $state, $zip, '', '', '', '', '']);
fclose($fh);
logMsg("CSV built: {$csvPath}");


// ── STEP 4: Upload to Tracerfy ────────────────────────────────
$uploadResult = tracerfyUpload($csvPath);
@unlink($csvPath);

logMsg('Tracerfy upload: ' . json_encode($uploadResult));

if (empty($uploadResult['queue_id'])) {
    $errMsg      = 'No queue_id returned: ' . json_encode($uploadResult);
    $unsupported = strpos($errMsg, 'No valid rows') !== false;
    logMsg(($unsupported ? 'UNSUPPORTED ADDRESS: ' : 'ERROR: ') . $errMsg);

    atPatch(TABLE_TRACY, $tracyId, [
        'status'    => 'error',
        'resultado' => $errMsg,
        'notas'     => $unsupported
                       ? 'Address not supported by Tracerfy (likely outside coverage area)'
                       : 'Unexpected Tracerfy error',
    ]);

    // Mark Done=true to avoid infinite retry.
    // If unsupported address: keep Stage as-is so user can review manually.
    // If other error: keep Stage as-is for now.
    atPatch(TABLE_LEADS, $leadId, ['Skip Trace Done' => true]);
    exit(1);
}

$queueId = (int) $uploadResult['queue_id'];
logMsg("Queue ID: {$queueId} — polling...");


// ── STEP 5: Poll for results ──────────────────────────────────
$queueData = tracerfyPoll($queueId);

if ($queueData === null) {
    $errMsg = "Timeout polling queue {$queueId} after max attempts";
    logMsg('ERROR: ' . $errMsg);
    atPatch(TABLE_TRACY, $tracyId, [
        'status'    => 'error',
        'resultado' => $errMsg,
    ]);
    exit(1);
}


// ── STEP 6: Update Tracy with results ────────────────────────
if (empty($queueData)) {
    $resultSummary = 'No contacts found for this address.';
    logMsg($resultSummary);
    atPatch(TABLE_TRACY, $tracyId, [
        'status'    => 'success',
        'resultado' => $resultSummary,
        'notas'     => 'Tracerfy completed — no results.',
    ]);
} else {
    $contact    = $queueData[0];
    $ownerFirst = trim($contact['first_name'] ?? '');
    $ownerLast  = trim($contact['last_name']  ?? '');
    $ownerName  = trim("{$ownerFirst} {$ownerLast}");

    $phoneFields = ['primary_phone','mobile_1','mobile_2','mobile_3','mobile_4','mobile_5',
                    'landline_1','landline_2','landline_3'];
    $emailFields = ['email_1','email_2','email_3','email_4','email_5'];
    $phoneCount  = count(array_filter(array_map(fn($k) => $contact[$k] ?? null, $phoneFields)));
    $emailCount  = count(array_filter(array_map(fn($k) => $contact[$k] ?? null, $emailFields)));

    $resultSummary = count($queueData) . " record(s). Owner: {$ownerName}. "
                   . "Phones: {$phoneCount}. Emails: {$emailCount}.";
    logMsg("Results: {$resultSummary}");

    $tracyUpdate = array_filter([
        'status'             => 'success',
        'resultado'          => $resultSummary,
        'first_name'         => $ownerFirst,
        'last_name'          => $ownerLast,
        'state'              => $state,
        'mail_address'       => trim($contact['mail_address']       ?? ''),
        'mail_city'          => trim($contact['mail_city']          ?? ''),
        'mail_state'         => trim($contact['mail_state']         ?? ''),
        'mail_zip'           => trim($contact['mail_zip']           ?? ''),
        'primary_phone'      => trim($contact['primary_phone']      ?? ''),
        'primary_phone_type' => trim($contact['primary_phone_type'] ?? ''),
        'mobile_1'           => trim($contact['mobile_1']           ?? ''),
        'mobile_2'           => trim($contact['mobile_2']           ?? ''),
        'mobile_3'           => trim($contact['mobile_3']           ?? ''),
        'landline_1'         => trim($contact['landline_1']         ?? ''),
        'landline_2'         => trim($contact['landline_2']         ?? ''),
        'email_1'            => trim($contact['email_1']            ?? ''),
        'email_2'            => trim($contact['email_2']            ?? ''),
        'email_3'            => trim($contact['email_3']            ?? ''),
        'tracerfy_id'        => intval($contact['id']               ?? 0),
        'notas'              => 'Processed by el_polling.php',
    ], fn($v) => $v !== '' && $v !== null && $v !== 0);

    atPatch(TABLE_TRACY, $tracyId, $tracyUpdate);
}


// ── STEP 7: Notify el_chismoso.php ───────────────────────────
logMsg("Notifying el_chismoso.php for Tracy record {$tracyId}...");

$ch = curl_init(CHISMOSO_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['record_id' => $tracyId]),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Chismoso-Token: ' . CHISMOSO_TOKEN,
    ],
    CURLOPT_TIMEOUT        => 30,
]);
$chismRes  = curl_exec($ch);
$chismCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

logMsg("el_chismoso.php ({$chismCode}): " . substr((string) $chismRes, 0, 300));


// ── STEP 8: Update Lead ───────────────────────────────────────
$leadUpdate = atPatch(TABLE_LEADS, $leadId, [
    'Skip Trace Done' => true,
    'Stage'           => 'To Be Contacted',
]);

if (!empty($leadUpdate['id'])) {
    logMsg("Lead {$leadId} updated: Skip Trace Done=true, Stage='To Be Contacted'");
} else {
    logMsg("WARNING: Lead update may have failed: " . json_encode($leadUpdate));
}

logMsg("EL POLLING done: {$address}");
logMsg('══════════════════════════════════════');
exit(0);
