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
define('TABLE_TRACY',    'tbl6CJm4kYspOuTDB');
define('TABLE_CONTACTS', 'tblacvw0Ss770x8l5');
define('TABLE_LEADS',    'tblxZz2EWIglOLnEd');
define('TABLE_NOTES',    'tbleOBXJl7sDhwj5w');

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

// Extract linked Lead IDs from Tracy.Leads 2 (set by el_polling).
// Fallback: if empty, search Leads by normalized address match.
$leadLinks = $tf['Leads 2'] ?? [];
if (empty($leadLinks) && !empty($tf['address'])) {
    $leadLinks = findLeadIdsByAddress($tf['address']);
}

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
// Capture up to 4 phones. Priority: primary → mobile_1 → mobile_2 → mobile_3 → landline_1 → landline_2
$phoneCandidates = array_values(array_filter([
    phoneToInt($tf['primary_phone'] ?? ''),
    phoneToInt($tf['mobile_1']      ?? ''),
    phoneToInt($tf['mobile_2']      ?? ''),
    phoneToInt($tf['mobile_3']      ?? ''),
    phoneToInt($tf['landline_1']    ?? ''),
    phoneToInt($tf['landline_2']    ?? ''),
]));
$phone1 = $phoneCandidates[0] ?? null;
$phone2 = $phoneCandidates[1] ?? null;
$phone3 = $phoneCandidates[2] ?? null;
$phone4 = $phoneCandidates[3] ?? null;

// Consolidated Owner Address: "515 N Huron St, De Pere, WI 54115"
$mailStreet = trim($tf['mail_address'] ?? '');
$mailCity   = trim($tf['mail_city']    ?? '');
$mailState  = trim($tf['mail_state']   ?? '');
$mailZip    = trim($tf['mail_zip']     ?? '');
$ownerAddrParts = array_values(array_filter([
    $mailStreet,
    $mailCity,
    trim($mailState . ' ' . $mailZip),
]));
$ownerAddress = implode(', ', $ownerAddrParts);

$contactFields = [
    'Full Name'         => $fullName,
    'Phone1 Type'       => $tf['primary_phone_type']    ?? '',
    'Email1'            => $tf['email_1']               ?? '',
    'Email2'            => $tf['email_2']               ?? '',
    'Email3'            => $tf['email_3']               ?? '',
    'Mail Address'      => $mailStreet,
    'Mail City'         => $mailCity,
    'Mail State'        => $mailState,
    'Mail Zip'          => $mailZip,
    'Owner Address'     => $ownerAddress,
    'Lead Source'       => 'Skip Trace - Tracerfy',
    'Stage'             => 'To Be Contacted',
    'Tracerfy ID'       => intval($tf['tracerfy_id']    ?? 0),
    'Last contact date' => gmdate('Y-m-d'),
];

// Only add phone fields if they have valid values
if ($phone1) $contactFields['Phone1'] = $phone1;
if ($phone2) $contactFields['Phone2'] = $phone2;
if ($phone3) $contactFields['Phone3'] = $phone3;
if ($phone4) $contactFields['Phone4'] = $phone4;

// Category = Single Select
if ($fullName) {
    $contactFields['Category'] = 'Seller';
}

// Remove empty string fields to avoid overwriting with blanks
$contactFields = array_filter($contactFields, function($v) {
    return $v !== '' && $v !== null && $v !== false;
});

// ── STEP 3: Search existing Contact (dedup by Tracerfy ID → Mail Address) ──
$tracerfyId  = intval($tf['tracerfy_id'] ?? 0);
$existingId    = null;
$mergeFields   = [];  // extra fields to merge from duplicate losers
$dedupDeleted  = 0;   // how many duplicate contacts were deleted
$loserAudit    = [];  // snapshot of loser contacts for audit trail

// PRIORITY 1: exact match by Tracerfy ID (most reliable)
if ($tracerfyId) {
    $formula   = rawurlencode("{Tracerfy ID}={$tracerfyId}");
    $searchUrl = 'https://api.airtable.com/v0/' . BASE_ID . '/' . TABLE_CONTACTS
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

// PRIORITY 2: dedup search by normalized Mail Address (catches Tracerfy ID drift)
if (!$existingId) {
    $mailAddr = trim($tf['mail_address'] ?? '');
    if ($mailAddr) {
        $dedup = findOrDedupeContactByMailAddress($mailAddr);
        if ($dedup['winner_id']) {
            $existingId   = $dedup['winner_id'];
            $mergeFields  = $dedup['merge_fields'];
            $dedupDeleted = $dedup['deleted'];
            $loserAudit   = $dedup['loser_audit'];
        }
    }
}

// ── STEP 4: Upsert Contact ────────────────────────────────────
// Merge any fields rescued from deleted duplicates (only fill gaps)
if ($mergeFields) {
    foreach ($mergeFields as $k => $v) {
        if (!isset($contactFields[$k]) || $contactFields[$k] === '' || $contactFields[$k] === null) {
            $contactFields[$k] = $v;
        }
    }
}

// Link Contact → Lead(s) via Property Address field (append, don't overwrite)
if (!empty($leadLinks)) {
    $currentLinks = [];
    if ($existingId) {
        // Fetch current Contact to read existing Property Address links
        $existingContact = airtableGet(TABLE_CONTACTS, $existingId);
        $currentLinks    = $existingContact['fields']['Property Address'] ?? [];
    }
    // Union: combine current + new leads, dedupe
    $unionLinks = array_values(array_unique(array_merge($currentLinks, $leadLinks)));
    if ($unionLinks !== $currentLinks) {
        $contactFields['Property Address'] = $unionLinks;
    }
}

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

// ── STEP 5b: Audit trail — log dedup deletions to Notes & Activity ──
if (!empty($loserAudit) && $contactRecordId) {
    logDedupeAudit($contactRecordId, $loserAudit, $mergeFields);
}

// ── SUCCESS RESPONSE ─────────────────────────────────────────
echo json_encode([
    'success'          => true,
    'action'           => $action,         // "created" or "updated"
    'contact_id'       => $contactRecordId,
    'tracy_id'         => $tracyId,
    'tracerfy_id'      => $tracerfyId,
    'name'             => $fullName,
    'duplicates_merged'=> !empty($mergeFields) ? count($mergeFields) : 0,
    'duplicates_deleted'=> $dedupDeleted,
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

/**
 * Find Lead record IDs matching a property address (normalized).
 * Used as fallback when Tracy.Leads 2 is empty (legacy records).
 */
function findLeadIdsByAddress(string $address): array {
    $target = normalizeAddress($address);
    if ($target === '') return [];

    // Broad server-side filter with SEARCH for partial matches
    $esc     = addslashes($target);
    $formula = "OR(LOWER({Address})='{$esc}',SEARCH('{$esc}',LOWER({Address}))>0)";
    $url = 'https://api.airtable.com/v0/' . BASE_ID . '/' . TABLE_LEADS
         . '?filterByFormula=' . rawurlencode($formula) . '&pageSize=50';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true) ?? [];

    // Client-side precise match
    $matches = [];
    foreach ($data['records'] ?? [] as $r) {
        if (normalizeAddress($r['fields']['Address'] ?? '') === $target) {
            $matches[] = $r['id'];
        }
    }
    return $matches;
}

/**
 * DELETE an Airtable record (used for dedup cleanup)
 */
function airtableDelete($table, $recordId) {
    $url = 'https://api.airtable.com/v0/' . BASE_ID . '/' . rawurlencode($table) . '/' . $recordId;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

/**
 * Aggressive address normalization for dedup matching.
 * Catches variations like "1219 Chicago St." vs "1219 chicago street" vs "1219 CHICAGO ST"
 */
function normalizeAddress(string $addr): string {
    $s = strtolower(trim($addr));
    // Strip punctuation
    $s = str_replace(['.', ',', '#', "'"], '', $s);
    // Common street suffix normalization
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
    // Collapse whitespace
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

/**
 * Score a Contact record by completeness (used to pick winner in dedup).
 * Higher = more complete = keep this one.
 */
function scoreContactCompleteness(array $fields): int {
    $score = 0;
    if (!empty($fields['Full Name']))     $score += 3;
    if (!empty($fields['Tracerfy ID']))   $score += 2;
    if (!empty($fields['Phone1']))        $score += 1;
    if (!empty($fields['Phone2']))        $score += 1;
    if (!empty($fields['Phone3']))        $score += 1;
    if (!empty($fields['Phone4']))        $score += 1;
    if (!empty($fields['Email1']))        $score += 1;
    if (!empty($fields['Email2']))        $score += 1;
    if (!empty($fields['Email3']))        $score += 1;
    if (!empty($fields['Mail City']))     $score += 1;
    if (!empty($fields['Mail State']))    $score += 1;
    if (!empty($fields['Mail Zip']))      $score += 1;
    if (!empty($fields['Phone1 Type']))   $score += 1;
    if (!empty($fields['Owner Address'])) $score += 1;
    return $score;
}

/**
 * Find or dedupe contacts matching a normalized Mail Address.
 * - Finds ALL contacts whose normalized Mail Address matches
 * - If ≥2, keeps the most complete as winner, merges fields from losers, deletes losers
 * - Returns ['winner_id' => ..., 'merge_fields' => [...], 'deleted' => N]
 */
function findOrDedupeContactByMailAddress(string $mailAddr): array {
    $result = ['winner_id' => null, 'merge_fields' => [], 'deleted' => 0, 'loser_audit' => []];
    $target = normalizeAddress($mailAddr);
    if ($target === '') return $result;

    // Fetch candidates with broad filter (LOWER + SEARCH — catches common variations)
    // We fetch then normalize client-side for accurate matching
    $escapedTarget = addslashes($target);
    $formula = "OR(LOWER({Mail Address})='{$escapedTarget}',SEARCH('{$escapedTarget}',LOWER({Mail Address}))>0)";
    $url = 'https://api.airtable.com/v0/' . BASE_ID . '/' . TABLE_CONTACTS
         . '?filterByFormula=' . rawurlencode($formula) . '&pageSize=100';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true) ?? [];
    $records = $data['records'] ?? [];

    // Client-side precise filter using normalizeAddress
    $matches = [];
    foreach ($records as $r) {
        $candidate = normalizeAddress($r['fields']['Mail Address'] ?? '');
        if ($candidate === $target) {
            $matches[] = $r;
        }
    }

    if (count($matches) === 0) return $result;

    if (count($matches) === 1) {
        $result['winner_id'] = $matches[0]['id'];
        return $result;
    }

    // Multiple matches → dedupe
    usort($matches, function($a, $b) {
        $sa = scoreContactCompleteness($a['fields'] ?? []);
        $sb = scoreContactCompleteness($b['fields'] ?? []);
        if ($sa !== $sb) return $sb - $sa;  // higher score first
        // Tiebreaker: older (lower Created Time) wins
        $ta = $a['fields']['Created Time'] ?? $a['createdTime'] ?? '';
        $tb = $b['fields']['Created Time'] ?? $b['createdTime'] ?? '';
        return strcmp($ta, $tb);
    });

    $winner = array_shift($matches);
    $losers = $matches;
    $result['winner_id'] = $winner['id'];

    // Merge: pull non-empty fields from losers that winner is missing
    $winnerFields = $winner['fields'] ?? [];
    $mergeable    = ['Full Name','Phone1','Phone2','Phone3','Phone4','Email1','Email2','Email3',
                     'Tracerfy ID','Phone1 Type','Mail City','Mail State','Mail Zip',
                     'Mail Address','Owner Address','Category'];
    $merge = [];
    foreach ($losers as $l) {
        $lf = $l['fields'] ?? [];
        foreach ($mergeable as $k) {
            if (!empty($lf[$k]) && empty($winnerFields[$k]) && !isset($merge[$k])) {
                $merge[$k] = $lf[$k];
            }
        }
    }
    $result['merge_fields'] = $merge;

    // Snapshot losers for audit trail BEFORE deleting
    foreach ($losers as $l) {
        $lf = $l['fields'] ?? [];
        $result['loser_audit'][] = [
            'id'          => $l['id'],
            'full_name'   => $lf['Full Name']   ?? '(sin nombre)',
            'phone1'      => $lf['Phone1']      ?? '',
            'tracerfy_id' => $lf['Tracerfy ID'] ?? '',
            'score'       => scoreContactCompleteness($lf),
        ];
    }

    // Delete losers
    foreach ($losers as $l) {
        airtableDelete(TABLE_CONTACTS, $l['id']);
        $result['deleted']++;
    }

    return $result;
}

/**
 * Create an audit-trail Note & Activity record linked to the winner Contact.
 * Called when dedup merges/deletes duplicates, so Jorge can see what happened.
 */
function logDedupeAudit(string $winnerContactId, array $loserAudit, array $mergeFields): void {
    if (empty($loserAudit)) return;

    $lines = [];
    $lines[] = "Dedup executed on " . gmdate('Y-m-d H:i') . " UTC";
    $lines[] = "Deleted " . count($loserAudit) . " duplicate contact(s):";
    foreach ($loserAudit as $l) {
        $lines[] = sprintf(
            "  - %s | id=%s | Phone1=%s | Tracerfy ID=%s | score=%d",
            $l['full_name'],
            substr($l['id'], 0, 14),
            $l['phone1'],
            $l['tracerfy_id'],
            $l['score']
        );
    }
    if (!empty($mergeFields)) {
        $lines[] = "Rescued fields merged into winner: " . implode(', ', array_keys($mergeFields));
    }

    $noteFields = [
        'Note Title'   => 'Contact Dedup — ' . count($loserAudit) . ' duplicate(s) merged',
        'Date'         => gmdate('Y-m-d'),
        'Call Logs'    => implode("\n", $lines),
        'Contact Name' => [$winnerContactId],
    ];

    $url = 'https://api.airtable.com/v0/' . BASE_ID . '/' . TABLE_NOTES;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['fields' => $noteFields, 'typecast' => true]),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
