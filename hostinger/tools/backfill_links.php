<?php
/**
 * ============================================================
 *  BACKFILL LINKS — One-shot linkage tool
 *  Pinnacle Holdings Group LLC
 *
 *  Restores bidirectional linked-record relations:
 *   - Tracy.'Leads 2'         ↔ Leads.Tracy
 *   - Contacts.'Property Address' ↔ Leads.Contacts
 *
 *  Matches by normalized property address (Leads.Address).
 *  Supports dry_run preview mode.
 *
 *  URL: https://pinnaclegroupwi.com/Tools/backfill_links.php?token=pinnacle2026
 *  Dry run: &dry_run=1
 * ============================================================
 */

require_once 'config.php';

define('BACKFILL_TOKEN', 'pinnacle2026');
define('BASE_ID',        'appfQbDA750Oihy9J');
define('TABLE_LEADS',    'tblxZz2EWIglOLnEd');
define('TABLE_TRACY',    'tbl6CJm4kYspOuTDB');
define('TABLE_CONTACTS', 'tblacvw0Ss770x8l5');
define('AIRTABLE_BASE',  'https://api.airtable.com/v0/' . BASE_ID);

header('Content-Type: text/html; charset=utf-8');

// ── AUTH ─────────────────────────────────────────────────────
$token  = $_GET['token']  ?? '';
$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';
if ($token !== BACKFILL_TOKEN) {
    http_response_code(401);
    echo '<h1>401 Unauthorized</h1>';
    exit;
}

// ── HELPERS ──────────────────────────────────────────────────
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

function atListAll(string $table, array $params = []): array {
    $all    = [];
    $offset = null;
    do {
        $q = $params;
        if ($offset) $q['offset'] = $offset;
        $url = AIRTABLE_BASE . '/' . $table . '?' . http_build_query($q);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $res  = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($res, true) ?? [];
        $all  = array_merge($all, $data['records'] ?? []);
        $offset = $data['offset'] ?? null;
    } while ($offset);
    return $all;
}

function atPatch(string $table, string $id, array $fields): array {
    $url = AIRTABLE_BASE . '/' . $table . '/' . $id;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode(['fields' => $fields]),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json',
        ],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

?>
<html>
<head>
    <title>Backfill Links — Pinnacle Tools</title>
    <style>
        body { font-family: monospace; background: #f5f5f5; padding: 20px; }
        .section { background: white; margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; }
        .ok { border-left-color: #28a745; }
        .warn { border-left-color: #ffc107; }
        .err { border-left-color: #dc3545; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 6px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
        .badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; }
        .link { background: #d4edda; color: #155724; }
        .skip { background: #e2e3e5; color: #495057; }
        .miss { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<h1>🔗 Backfill Links Tool</h1>
<?php if ($dryRun): ?>
    <div class="section warn"><strong>🔍 DRY RUN MODE</strong> — no changes will be made.</div>
<?php else: ?>
    <div class="section err"><strong>⚠️ LIVE MODE</strong> — links will be written to Airtable.</div>
<?php endif; ?>

<?php
// ── Step 1: Load all Leads, index by normalized address ────────
echo "<div class='section'><h2>1. Loading Leads</h2>";
$leads = atListAll(TABLE_LEADS, ['pageSize' => 100]);
$leadByAddr = [];
foreach ($leads as $l) {
    $norm = normalizeAddress($l['fields']['Address'] ?? '');
    if ($norm === '') continue;
    $leadByAddr[$norm][] = $l['id'];
}
echo "<p>Loaded <strong>" . count($leads) . "</strong> leads, <strong>" . count($leadByAddr) . "</strong> unique addresses</p></div>";

// ── Step 2: Process Tracy records ───────────────────────────────
echo "<div class='section'><h2>2. Tracy → Leads (field: Leads 2)</h2>";
$tracys = atListAll(TABLE_TRACY, ['pageSize' => 100]);
echo "<p>Total Tracy records: <strong>" . count($tracys) . "</strong></p>";
echo "<table><tr><th>Status</th><th>Tracy ID</th><th>Address</th><th>Action</th></tr>";

$tracyLinked   = 0;
$tracySkipped  = 0;
$tracyNoMatch  = 0;
// Map tracerfy_id → leadIds (for step 3)
$tracerfyIdToLeadIds = [];

foreach ($tracys as $t) {
    $tAddr = $t['fields']['address'] ?? '';
    $norm  = normalizeAddress($tAddr);
    $currentLinks = $t['fields']['Leads 2'] ?? [];
    $matchedLeadIds = $leadByAddr[$norm] ?? [];

    // Store for step 3
    $tid = $t['fields']['tracerfy_id'] ?? null;
    if ($tid) {
        $tracerfyIdToLeadIds[$tid] = array_values(array_unique(array_merge(
            $tracerfyIdToLeadIds[$tid] ?? [], $matchedLeadIds
        )));
    }

    if (empty($matchedLeadIds)) {
        $tracyNoMatch++;
        echo "<tr><td><span class='badge miss'>NO MATCH</span></td><td>" . substr($t['id'],0,12) . "</td><td>" . htmlspecialchars($tAddr) . "</td><td>No lead with matching address</td></tr>";
        continue;
    }

    $union = array_values(array_unique(array_merge($currentLinks, $matchedLeadIds)));
    if ($union === $currentLinks) {
        $tracySkipped++;
        echo "<tr><td><span class='badge skip'>ALREADY</span></td><td>" . substr($t['id'],0,12) . "</td><td>" . htmlspecialchars($tAddr) . "</td><td>Already linked (" . count($currentLinks) . ")</td></tr>";
        continue;
    }

    if (!$dryRun) {
        atPatch(TABLE_TRACY, $t['id'], ['Leads 2' => $union]);
    }
    $tracyLinked++;
    echo "<tr><td><span class='badge link'>LINK</span></td><td>" . substr($t['id'],0,12) . "</td><td>" . htmlspecialchars($tAddr) . "</td><td>→ " . count($matchedLeadIds) . " lead(s)</td></tr>";
}
echo "</table>";
echo "<p>✅ Linked: <strong>{$tracyLinked}</strong> | ⏭ Already: <strong>{$tracySkipped}</strong> | ❌ No match: <strong>{$tracyNoMatch}</strong></p>";
echo "</div>";

// ── Step 3: Process Contacts ────────────────────────────────────
echo "<div class='section'><h2>3. Contacts → Leads (field: Property Address)</h2>";
$contacts = atListAll(TABLE_CONTACTS, [
    'filterByFormula' => "{Lead Source}='Skip Trace - Tracerfy'",
    'pageSize' => 100,
]);
echo "<p>Skip Trace contacts: <strong>" . count($contacts) . "</strong></p>";
echo "<table><tr><th>Status</th><th>Contact ID</th><th>Full Name</th><th>Match via</th><th>Action</th></tr>";

$contactLinked   = 0;
$contactSkipped  = 0;
$contactNoMatch  = 0;

foreach ($contacts as $c) {
    $cf       = $c['fields'] ?? [];
    $current  = $cf['Property Address'] ?? [];
    $fullName = $cf['Full Name'] ?? '';
    $matchSource = '';
    $matchedLeadIds = [];

    // Primary: match via Tracerfy ID → Tracy → Lead (pre-computed in step 2)
    $tid = $cf['Tracerfy ID'] ?? null;
    if ($tid && !empty($tracerfyIdToLeadIds[$tid])) {
        $matchedLeadIds = $tracerfyIdToLeadIds[$tid];
        $matchSource    = 'Tracerfy ID';
    }

    // Fallback: match by Owner Address (parse first part as street)
    if (empty($matchedLeadIds) && !empty($cf['Owner Address'])) {
        // Owner Address is "515 N HURON ST, DE PERE, WI, 54115" — take the part before first comma
        $parts = explode(',', $cf['Owner Address']);
        $street = trim($parts[0] ?? '');
        $norm   = normalizeAddress($street);
        if (isset($leadByAddr[$norm])) {
            $matchedLeadIds = $leadByAddr[$norm];
            $matchSource    = 'Owner Address';
        }
    }

    // Fallback 2: match by Mail Address
    if (empty($matchedLeadIds) && !empty($cf['Mail Address'])) {
        $norm = normalizeAddress($cf['Mail Address']);
        if (isset($leadByAddr[$norm])) {
            $matchedLeadIds = $leadByAddr[$norm];
            $matchSource    = 'Mail Address';
        }
    }

    if (empty($matchedLeadIds)) {
        $contactNoMatch++;
        echo "<tr><td><span class='badge miss'>NO MATCH</span></td><td>" . substr($c['id'],0,12) . "</td><td>" . htmlspecialchars($fullName) . "</td><td>—</td><td>No lead found</td></tr>";
        continue;
    }

    $union = array_values(array_unique(array_merge($current, $matchedLeadIds)));
    if ($union === $current) {
        $contactSkipped++;
        echo "<tr><td><span class='badge skip'>ALREADY</span></td><td>" . substr($c['id'],0,12) . "</td><td>" . htmlspecialchars($fullName) . "</td><td>{$matchSource}</td><td>Already linked</td></tr>";
        continue;
    }

    if (!$dryRun) {
        atPatch(TABLE_CONTACTS, $c['id'], ['Property Address' => $union]);
    }
    $contactLinked++;
    echo "<tr><td><span class='badge link'>LINK</span></td><td>" . substr($c['id'],0,12) . "</td><td>" . htmlspecialchars($fullName) . "</td><td>{$matchSource}</td><td>→ " . count($matchedLeadIds) . " lead(s)</td></tr>";
}
echo "</table>";
echo "<p>✅ Linked: <strong>{$contactLinked}</strong> | ⏭ Already: <strong>{$contactSkipped}</strong> | ❌ No match: <strong>{$contactNoMatch}</strong></p>";
echo "</div>";

// ── Final summary ───────────────────────────────────────────────
echo "<div class='section ok'>";
echo "<h2>📊 Resumen Final</h2>";
echo "<ul>";
echo "<li>Tracy records " . ($dryRun ? "a linkear" : "linkeados") . ": <strong>{$tracyLinked}</strong></li>";
echo "<li>Tracy ya linkeados: <strong>{$tracySkipped}</strong></li>";
echo "<li>Tracy sin match: <strong>{$tracyNoMatch}</strong></li>";
echo "<li>Contacts " . ($dryRun ? "a linkear" : "linkeados") . ": <strong>{$contactLinked}</strong></li>";
echo "<li>Contacts ya linkeados: <strong>{$contactSkipped}</strong></li>";
echo "<li>Contacts sin match: <strong>{$contactNoMatch}</strong></li>";
echo "</ul>";
if ($dryRun) {
    echo "<p>✅ DRY RUN complete — quita <code>&dry_run=1</code> para ejecutar.</p>";
} else {
    echo "<p>✅ Backfill completado.</p>";
}
echo "</div>";
?>
</body>
</html>
