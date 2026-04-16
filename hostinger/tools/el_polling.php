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
 *   7. Update Lead: Skip Trace Done=true, Stage='To Be Contacted'
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
// $typecast=true auto-creates missing select options (used for new Lead Stages)
function atPatch(string $tableId, string $recordId, array $fields, bool $typecast = false): array {
    $url     = AIRTABLE_BASE . '/' . $tableId . '/' . $recordId;
    $payload = ['fields' => $fields];
    if ($typecast) $payload['typecast'] = true;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode($payload),
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
            'zip_column'           => 'zip',
            'first_name_column'    => 'first_name',
            'last_name_column'     => 'last_name',
            'mail_address_column'  => 'mail_address',
            'mail_city_column'     => 'mail_city',
            'mail_state_column'    => 'mail_state',
            'trace_type'           => 'advanced',  // Find owner by address only (2 credits/lead)
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
        logMsg("  Queue {$queueId} raw (HTTP {$code}): " . substr($raw, 0, 400));
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

// ── Aggressive address normalization (matches el_chismoso.php) ──
function normalizeAddress(string $addr): string {
    $s = strtolower(trim($addr));
    $s = str_replace(['.', ',', '#', "'"], '', $s);
    $replacements = [
        '/\bstreet\b/'    => 'st',
        '/\bavenue\b/'    => 'ave',
        '/\broad\b/'      => 'rd',
        '/\bdrive\b/'     => 'dr',
        '/\bboulevard\b/' => 'blvd',
        '/\blane\b/'      => 'ln',
        '/\bcourt\b/'     => 'ct',
        '/\bcircle\b/'    => 'cir',
        '/\bplace\b/'     => 'pl',
        '/\bhighway\b/'   => 'hwy',
        '/\bnorth\b/'     => 'n',
        '/\bsouth\b/'     => 's',
        '/\beast\b/'      => 'e',
        '/\bwest\b/'      => 'w',
    ];
    $s = preg_replace(array_keys($replacements), array_values($replacements), $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

// ── Convert phone to E.164 format (e.g. +12625551234) ─────────
function phoneToE164($phone): string {
    if (!$phone) return '';
    // Handle float notation from Tracerfy (e.g., 8594757302.0)
    $asInt  = (int) floatval($phone);
    $digits = $asInt > 0
        ? preg_replace('/[^0-9]/', '', strval($asInt))
        : preg_replace('/[^0-9]/', '', strval($phone));
    if (strlen($digits) === 10) return '+1' . $digits;
    if (strlen($digits) === 11 && $digits[0] === '1') return '+' . $digits;
    return '';
}


// ═══════════════════════════════════════════════════════════════
//  MAIN
// ═══════════════════════════════════════════════════════════════

rotateLog();
logMsg('══════════════════════════════════════');
logMsg('EL POLLING started');

// ===== CAMBIO 4: Rate limiting — máximo 10 traces por día =====
$counterFile = '/tmp/tracerfy_daily_count.txt';
$today       = date('Y-m-d');
$dailyCount  = 0;
if (file_exists($counterFile)) {
    $parts = explode(':', trim(file_get_contents($counterFile)));
    if (count($parts) === 2 && $parts[0] === $today) {
        $dailyCount = (int) $parts[1];
    }
}
if ($dailyCount >= 10) {
    logMsg("Rate limit alcanzado — {$dailyCount} traces hoy. Esperando mañana.");
    logMsg('══════════════════════════════════════');
    exit(0);
}
logMsg("Créditos Tracerfy usados hoy: {$dailyCount}/10");
// ===== FIN CAMBIO 4 =====

// ── STEP 1: Fetch one qualifying Lead ─────────────────────────
// Only process WI leads — Tracerfy coverage is Wisconsin-based
// IL and other out-of-market states fail with "No valid rows" error
$formula = "AND(OR({Stage}='Review this Deal',{Stage}='Review This Deal'),{Skip Trace Done}=FALSE(),{Estate}='WI')";
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

// Lock immediately — prevents re-pick on next 5-min cycle.
// On timeout: we reset Done=false so the lead retries.
atPatch(TABLE_LEADS, $leadId, ['Skip Trace Done' => true]);
logMsg("Lead {$leadId} locked (Skip Trace Done=true)");
// ===== FIN CAMBIO 3 parte 1 =====


// ===== CAMBIO 2: Deduplicación con normalización + ventana 14 días =====
// Busca Tracy records exitosos recientes, filtra client-side por address normalizada
$normalizedTarget = normalizeAddress($address);
$cutoffDate       = gmdate('Y-m-d\TH:i:s.000\Z', strtotime('-14 days'));
$dedupeCheck = atList(TABLE_TRACY, [
    'filterByFormula' => "AND({status}='success',IS_AFTER({fecha_rastreo},'{$cutoffDate}'))",
    'maxRecords'      => 100,
    'sort[0][field]'  => 'fecha_rastreo',
    'sort[0][direction]' => 'desc',
]);

$existingTracy = null;
foreach ($dedupeCheck['records'] ?? [] as $r) {
    if (normalizeAddress($r['fields']['address'] ?? '') === $normalizedTarget) {
        $existingTracy = $r;
        break;
    }
}

if ($existingTracy) {
    logMsg("Lead {$leadId} — dirección ya trazada en últimos 14 días ({$existingTracy['id']}), reutilizando (no gasta crédito)");

    // Append current leadId to Tracy's Leads 2 link (union of existing + new)
    $existingLeadLinks = $existingTracy['fields']['Leads 2'] ?? [];
    if (!in_array($leadId, $existingLeadLinks, true)) {
        $existingLeadLinks[] = $leadId;
        atPatch(TABLE_TRACY, $existingTracy['id'], ['Leads 2' => $existingLeadLinks]);
        logMsg("  Tracy {$existingTracy['id']} linkeado al nuevo Lead {$leadId}");
    }

    $chDedup = curl_init(CHISMOSO_URL);
    curl_setopt_array($chDedup, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['record_id' => $existingTracy['id']]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Chismoso-Token: ' . CHISMOSO_TOKEN,
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $chDedupRes  = curl_exec($chDedup);
    $chDedupCode = curl_getinfo($chDedup, CURLINFO_HTTP_CODE);
    curl_close($chDedup);
    logMsg("  el_chismoso dedup response ({$chDedupCode}): " . substr((string)$chDedupRes, 0, 200));

    // Validación: si chismoso falló, revertir Skip Trace Done y reintentar en próximo cron
    $chDedupJson = json_decode($chDedupRes, true);
    if ($chDedupCode !== 200 || empty($chDedupJson['success'])) {
        logMsg("  ❌ el_chismoso dedup FAILED — revirtiendo Skip Trace Done para retry");
        atPatch(TABLE_LEADS, $leadId, ['Skip Trace Done' => false]);
        logMsg('══════════════════════════════════════');
        exit(1);
    }

    atPatch(TABLE_LEADS, $leadId, [
        'Skip Trace Done'   => true,
        'Stage'             => 'To Be Contacted',
        'Last Contact Date' => gmdate('Y-m-d'),
    ]);
    logMsg("Lead {$leadId} actualizado: Done=true, Stage='To Be Contacted' (dedup sin gastar crédito)");
    logMsg("EL POLLING done (dedup): {$address}");
    logMsg('══════════════════════════════════════');
    exit(0);
}
// ===== FIN CAMBIO 2 =====

// ── STEP 2: Create Tracy record (pending) ─────────────────────
// 'Leads 2' is a linked-record field → Airtable expects array of record IDs
$tracyFields = array_filter([
    'address'       => $address,
    'city'          => $city,
    'state'         => $state,
    'zip'           => $zip,
    'fecha_rastreo' => gmdate('Y-m-d\TH:i:s.000\Z'),
    'status'        => 'pending',
    'notas'         => 'Iniciado por el_polling.php (cron)',
    'Leads 2'       => [$leadId],  // bidirectional link back to Lead
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

// ===== CAMBIO 1: Validar dirección completa antes de gastar créditos =====
$hasAddress = !empty($address) && !empty($city) && !empty($state) && !empty($zip);
if (!$hasAddress) {
    logMsg("Lead {$leadId} — dirección incompleta (addr={$address} city={$city} state={$state} zip={$zip}), skip sin gastar créditos");
    @unlink($csvPath);
    atPatch(TABLE_LEADS, $leadId, [
        'Skip Trace Done'   => true,
        'Stage'             => 'Skip Trace - Error',
        'Last Contact Date' => gmdate('Y-m-d'),
    ], true);
    atPatch(TABLE_TRACY, $tracyId, ['status' => 'error', 'notas' => 'Dirección incompleta — skip sin crédito']);
    logMsg('══════════════════════════════════════');
    exit(0);
}

// ===== CAMBIO 1: Lead ya tiene teléfono — no gastar crédito =====
$existingPhone = $lf['Phone1'] ?? $lf['Phone'] ?? '';
if (!empty($existingPhone)) {
    logMsg("Lead {$leadId} — ya tiene teléfono ({$existingPhone}), skip sin gastar créditos");
    @unlink($csvPath);
    atPatch(TABLE_LEADS, $leadId, [
        'Skip Trace Done'   => true,
        'Stage'             => 'To Be Contacted',
        'Last Contact Date' => gmdate('Y-m-d'),
    ]);
    atPatch(TABLE_TRACY, $tracyId, ['status' => 'success', 'notas' => 'Teléfono preexistente — crédito no consumido']);
    logMsg("EL POLLING done (phone exists): {$address}");
    logMsg('══════════════════════════════════════');
    exit(0);
}
// ===== FIN CAMBIO 1 =====

// ── STEP 4: Upload to Tracerfy ────────────────────────────────
// ===== CAMBIO 5: Log de créditos antes del request =====
logMsg("=== TRACERFY REQUEST === Lead: {$leadId} | Dirección: {$address}, {$city}, {$state} {$zip} | Créditos usados hoy: {$dailyCount}/10");
// ===== FIN CAMBIO 5 =====
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

    // Mark Done=true to avoid infinite retry (address error is permanent).
    atPatch(TABLE_LEADS, $leadId, [
        'Skip Trace Done'   => true,
        'Stage'             => 'Skip Trace - Error',
        'Last Contact Date' => gmdate('Y-m-d'),
    ], true);
    exit(1);
}

$queueId = (int) $uploadResult['queue_id'];
// ===== CAMBIO 5: Incrementar contador tras crédito consumido =====
file_put_contents($counterFile, $today . ':' . ($dailyCount + 1));
logMsg("Crédito consumido — total hoy: " . ($dailyCount + 1) . "/10");
// ===== FIN CAMBIO 5 =====
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
    // Reset Done=false on timeout so the lead retries next cycle.
    atPatch(TABLE_LEADS, $leadId, ['Skip Trace Done' => false]);
    logMsg("Lead {$leadId} unlocked (timeout) — Skip Trace Done=false, se reintentará próximo cron");
    exit(1);
}


// ── STEP 6: Update Tracy with results ────────────────────────
if (empty($queueData)) {
    $resultSummary = 'No contacts found for this address.';
    logMsg($resultSummary);
    atPatch(TABLE_TRACY, $tracyId, [
        'status'    => 'no_results',
        'resultado' => $resultSummary,
        'notas'     => 'Tracerfy completed — no results.',
    ]);
    // No contacts → skip el_chismoso, just update Stage and exit.
    atPatch(TABLE_LEADS, $leadId, [
        'Skip Trace Done'   => true,
        'Stage'             => 'Skip Trace - No Results',
        'Last Contact Date' => gmdate('Y-m-d'),
    ], true);
    logMsg("No contacts — Stage='Skip Trace - No Results', skipping el_chismoso.");
    logMsg("EL POLLING done: {$address}");
    logMsg('══════════════════════════════════════');
    exit(0);
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
        'primary_phone'      => phoneToE164($contact['primary_phone'] ?? ''),
        'primary_phone_type' => trim($contact['primary_phone_type'] ?? ''),
        'mobile_1'           => phoneToE164($contact['mobile_1']    ?? ''),
        'mobile_2'           => phoneToE164($contact['mobile_2']    ?? ''),
        'mobile_3'           => phoneToE164($contact['mobile_3']    ?? ''),
        'landline_1'         => phoneToE164($contact['landline_1']  ?? ''),
        'landline_2'         => phoneToE164($contact['landline_2']  ?? ''),
        'email_1'            => trim($contact['email_1']            ?? ''),
        'email_2'            => trim($contact['email_2']            ?? ''),
        'email_3'            => trim($contact['email_3']            ?? ''),
        'tracerfy_id'        => intval($contact['id']               ?? 0),
        'notas'              => 'Processed by el_polling.php',
    ], fn($v) => $v !== '' && $v !== null && $v !== false);

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

// ── VALIDATION: retry on failure ─────────────────────────────
$chismJson = json_decode($chismRes, true);
if ($chismCode !== 200 || empty($chismJson['success'])) {
    logMsg("❌ el_chismoso FAILED (HTTP {$chismCode}) — revirtiendo flags para retry en próximo cron");
    atPatch(TABLE_TRACY, $tracyId, ['pushed_to_contacts' => false]);
    atPatch(TABLE_LEADS, $leadId, ['Skip Trace Done' => false]);
    logMsg('══════════════════════════════════════');
    exit(1);
}

// Log dedup info si hubo limpieza
if (!empty($chismJson['duplicates_deleted'])) {
    logMsg("  ✓ Dedup: {$chismJson['duplicates_deleted']} contacto(s) duplicado(s) eliminado(s)");
}
if (!empty($chismJson['duplicates_merged'])) {
    logMsg("  ✓ Merge: {$chismJson['duplicates_merged']} campo(s) rescatado(s) de duplicados");
}


// ── STEP 8: Update Lead Stage ─────────────────────────────────
$leadUpdate = atPatch(TABLE_LEADS, $leadId, [
    'Skip Trace Done'   => true,
    'Stage'             => 'To Be Contacted',
    'Last Contact Date' => gmdate('Y-m-d'),
]);

if (!empty($leadUpdate['id'])) {
    logMsg("Lead {$leadId} updated: Skip Trace Done=true, Stage='To Be Contacted'");
} else {
    logMsg("WARNING: Lead update may have failed: " . json_encode($leadUpdate));
}

logMsg("EL POLLING done: {$address}");
logMsg('══════════════════════════════════════');
exit(0);
