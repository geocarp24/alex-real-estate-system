<?php
/**
 * ============================================================
 *  CLEANUP DUPLICATES — One-shot dedup tool
 *  Pinnacle Holdings Group LLC
 *
 *  Scans Contacts table (Lead Source = "Skip Trace - Tracerfy"),
 *  groups by normalized Mail Address, and for each duplicate group:
 *   - Picks winner by completeness score
 *   - Merges missing fields from losers into winner
 *   - Deletes losers
 *
 *  URL:     https://pinnaclegroupwi.com/Tools/cleanup_duplicates.php?token=pinnacle2026
 *  Auth:    ?token=pinnacle2026 (hardcoded below)
 *  Safety:  ?dry_run=1 → only reports, no deletions
 * ============================================================
 */

require_once 'config.php';

define('CLEANUP_TOKEN',  'pinnacle2026');
define('BASE_ID',        'appfQbDA750Oihy9J');
define('TABLE_CONTACTS', 'tblacvw0Ss770x8l5');
define('AIRTABLE_BASE',  'https://api.airtable.com/v0/' . BASE_ID);

header('Content-Type: text/html; charset=utf-8');

// ── AUTH ─────────────────────────────────────────────────────
$token   = $_GET['token']   ?? '';
$dryRun  = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';
if ($token !== CLEANUP_TOKEN) {
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

function atDelete(string $table, string $id): array {
    $url = AIRTABLE_BASE . '/' . $table . '/' . $id;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

// ── MAIN ─────────────────────────────────────────────────────
?>
<html>
<head>
    <title>Cleanup Duplicates — Pinnacle Tools</title>
    <style>
        body { font-family: monospace; background: #f5f5f5; padding: 20px; }
        .section { background: white; margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; }
        .winner { border-left-color: #28a745; }
        .loser  { border-left-color: #dc3545; color: #666; text-decoration: line-through; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 6px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
        .badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; }
        .ok    { background: #d4edda; color: #155724; }
        .del   { background: #f8d7da; color: #721c24; }
        .merge { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
<h1>🧹 Cleanup Duplicates Tool</h1>
<?php if ($dryRun): ?>
    <div class="section" style="border-left-color: #ffc107;">
        <strong>🔍 DRY RUN MODE</strong> — No changes will be made. Remove <code>&dry_run=1</code> to execute.
    </div>
<?php else: ?>
    <div class="section" style="border-left-color: #dc3545;">
        <strong>⚠️ LIVE MODE</strong> — Duplicates WILL be deleted. Add <code>&dry_run=1</code> to preview first.
    </div>
<?php endif; ?>

<?php
$records = atListAll(TABLE_CONTACTS, [
    'filterByFormula' => "{Lead Source}='Skip Trace - Tracerfy'",
    'pageSize'        => 100,
]);

echo "<div class='section'><strong>Total Skip Trace contacts scanned:</strong> " . count($records) . "</div>";

// Group by normalized Mail Address
$groups = [];
foreach ($records as $r) {
    $addr = $r['fields']['Mail Address'] ?? '';
    $norm = normalizeAddress($addr);
    if ($norm === '') continue;
    $groups[$norm][] = $r;
}

$dupGroups = array_filter($groups, fn($g) => count($g) > 1);
echo "<div class='section'><strong>Addresses with duplicates:</strong> " . count($dupGroups) . "</div>";

$totalDeleted = 0;
$totalMerged  = 0;

foreach ($dupGroups as $norm => $group) {
    // Sort by score descending, tiebreaker by creation time ascending
    usort($group, function($a, $b) {
        $sa = scoreContactCompleteness($a['fields'] ?? []);
        $sb = scoreContactCompleteness($b['fields'] ?? []);
        if ($sa !== $sb) return $sb - $sa;
        $ta = $a['fields']['Created Time'] ?? $a['createdTime'] ?? '';
        $tb = $b['fields']['Created Time'] ?? $b['createdTime'] ?? '';
        return strcmp($ta, $tb);
    });

    $winner = array_shift($group);
    $losers = $group;

    // Build merge fields from losers
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

    echo "<div class='section'>";
    echo "<h3>📍 " . htmlspecialchars($norm) . "</h3>";
    echo "<table>";
    echo "<tr><th>Status</th><th>ID</th><th>Score</th><th>Full Name</th><th>Phone1</th><th>Tracerfy ID</th><th>Mail Address</th></tr>";

    // Winner row
    $wf = $winner['fields'] ?? [];
    echo "<tr>";
    echo "<td><span class='badge ok'>KEEP</span></td>";
    echo "<td>" . substr($winner['id'], 0, 12) . "...</td>";
    echo "<td>" . scoreContactCompleteness($wf) . "</td>";
    echo "<td>" . htmlspecialchars($wf['Full Name'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars(strval($wf['Phone1'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars(strval($wf['Tracerfy ID'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars($wf['Mail Address'] ?? '') . "</td>";
    echo "</tr>";

    // Loser rows
    foreach ($losers as $l) {
        $lf = $l['fields'] ?? [];
        echo "<tr style='color:#999;'>";
        echo "<td><span class='badge del'>DELETE</span></td>";
        echo "<td>" . substr($l['id'], 0, 12) . "...</td>";
        echo "<td>" . scoreContactCompleteness($lf) . "</td>";
        echo "<td>" . htmlspecialchars($lf['Full Name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars(strval($lf['Phone1'] ?? '')) . "</td>";
        echo "<td>" . htmlspecialchars(strval($lf['Tracerfy ID'] ?? '')) . "</td>";
        echo "<td>" . htmlspecialchars($lf['Mail Address'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    if ($merge) {
        echo "<p><span class='badge merge'>MERGE</span> Campos rescatados de duplicados: <code>" . htmlspecialchars(json_encode($merge)) . "</code></p>";
    }

    // Execute unless dry run
    if (!$dryRun) {
        if ($merge) {
            atPatch(TABLE_CONTACTS, $winner['id'], $merge);
            $totalMerged += count($merge);
        }
        foreach ($losers as $l) {
            atDelete(TABLE_CONTACTS, $l['id']);
            $totalDeleted++;
        }
        echo "<p style='color:#28a745;'><strong>✅ Ejecutado: " . count($losers) . " eliminado(s), " . count($merge) . " campo(s) merged</strong></p>";
    }

    echo "</div>";
}

echo "<div class='section winner'>";
echo "<h2>📊 Resumen</h2>";
echo "<ul>";
echo "<li>Grupos con duplicados: <strong>" . count($dupGroups) . "</strong></li>";
echo "<li>Contactos " . ($dryRun ? "que serían eliminados" : "eliminados") . ": <strong>{$totalDeleted}</strong></li>";
echo "<li>Campos " . ($dryRun ? "que serían rescatados" : "rescatados") . ": <strong>{$totalMerged}</strong></li>";
echo "</ul>";
if ($dryRun) {
    echo "<p>✅ DRY RUN complete — no se hicieron cambios. Quita <code>&dry_run=1</code> para ejecutar.</p>";
} else {
    echo "<p>✅ Cleanup completado.</p>";
}
echo "</div>";
?>
</body>
</html>
