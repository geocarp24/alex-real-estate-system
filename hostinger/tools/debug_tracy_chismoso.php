<?php
/**
 * DEBUG: Analizar qué está pasando en Tracy→Contacts
 * Ejecutar: https://pinnaclegroupwi.com/Tools/debug_tracy_chismoso.php
 */

require_once 'config.php';

define('BASE_ID',        'appfQbDA750Oihy9J');
define('AIRTABLE_BASE',  'https://api.airtable.com/v0/' . BASE_ID);
define('TABLE_TRACY',    'tbl6CJm4kYspOuTDB');
define('TABLE_CONTACTS', 'tblacvw0Ss770x8l5');

function atList($tableId, $params = []) {
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

header('Content-Type: text/html; charset=utf-8');
?>
<html>
<head>
    <title>DEBUG: Tracy → Contacts Migration</title>
    <style>
        body { font-family: monospace; background: #f5f5f5; padding: 20px; }
        .section { background: white; margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; }
        .section.error { border-left-color: #dc3545; }
        .section.success { border-left-color: #28a745; }
        .section.warning { border-left-color: #ffc107; }
        h2 { margin-top: 0; color: #333; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .empty { color: #999; font-style: italic; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>

<h1>🔍 DEBUG: Tracy → Contacts Migration Analysis</h1>

<?php

// 1. OBTENER TRACY RECORDS CON STATUS=SUCCESS Y pushed_to_contacts=TRUE
echo '<div class="section success">';
echo '<h2>1. Tracy Records (status=success, pushed=true)</h2>';
$tracyData = atList(TABLE_TRACY, [
    'filterByFormula' => "AND({status}='success',{pushed_to_contacts}=TRUE())",
    'maxRecords'      => 10,
    'sort[0][field]'  => 'fecha_rastreo',
    'sort[0][direction]' => 'desc',
]);

if (empty($tracyData['records'])) {
    echo '<p class="empty">No Tracy records found with status=success and pushed=true</p>';
} else {
    echo '<table>';
    echo '<tr><th>Tracy ID</th><th>First Name</th><th>Last Name</th><th>Address</th><th>Tracerfy ID</th><th>Pushed</th></tr>';
    foreach ($tracyData['records'] as $r) {
        $f = $r['fields'] ?? [];
        $firstName = $f['first_name'] ?? '—';
        $lastName = $f['last_name'] ?? '—';
        $tracerfyId = $f['tracerfy_id'] ?? '—';

        // Check si son arrays (bug potencial)
        if (is_array($firstName)) $firstName .= ' [ARRAY!]';
        if (is_array($lastName)) $lastName .= ' [ARRAY!]';

        echo '<tr>';
        echo '<td>' . substr($r['id'], 0, 8) . '...</td>';
        echo '<td>' . htmlspecialchars($firstName) . '</td>';
        echo '<td>' . htmlspecialchars($lastName) . '</td>';
        echo '<td>' . htmlspecialchars($f['address'] ?? '—') . '</td>';
        echo '<td>' . htmlspecialchars(strval($tracerfyId)) . '</td>';
        echo '<td>' . ($f['pushed_to_contacts'] ? '✅' : '❌') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}
echo '</div>';

// 2. OBTENER CONTACTS RECORDS CREADOS RECIENTEMENTE
echo '<div class="section">';
echo '<h2>2. Contacts Records (últimos 10 creados)</h2>';
$contactsData = atList(TABLE_CONTACTS, [
    'maxRecords'      => 10,
    'sort[0][field]'  => 'Created Time',
    'sort[0][direction]' => 'desc',
]);

if (empty($contactsData['records'])) {
    echo '<p class="empty">No Contacts found</p>';
} else {
    echo '<table>';
    echo '<tr><th>Contact ID</th><th>Full Name</th><th>Phone1</th><th>Tracerfy ID</th><th>Lead Source</th><th>Mail Addr</th></tr>';
    foreach ($contactsData['records'] as $r) {
        $f = $r['fields'] ?? [];
        echo '<tr>';
        echo '<td>' . substr($r['id'], 0, 8) . '...</td>';
        echo '<td>' . (empty($f['Full Name']) ? '<span class="empty">(empty)</span>' : htmlspecialchars($f['Full Name'])) . '</td>';
        echo '<td>' . (empty($f['Phone1']) ? '<span class="empty">(empty)</span>' : $f['Phone1']) . '</td>';
        echo '<td>' . (empty($f['Tracerfy ID']) ? '<span class="empty">(empty)</span>' : $f['Tracerfy ID']) . '</td>';
        echo '<td>' . htmlspecialchars($f['Lead Source'] ?? '—') . '</td>';
        echo '<td>' . htmlspecialchars($f['Mail Address'] ?? '—') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}
echo '</div>';

// 3. COMPARAR: TRACY SIN PUSHEAR vs CONTACTS VACÍOS
echo '<div class="section warning">';
echo '<h2>3. Mismatch Analysis: Tracy (success) vs Contacts (empty Full Name)</h2>';

$tracySuccess = atList(TABLE_TRACY, [
    'filterByFormula' => "{status}='success'",
    'maxRecords'      => 100,
]);

$tracyIds = [];
$tracyWithNames = [];
foreach ($tracySuccess['records'] as $r) {
    $tracyIds[] = $r['id'];
    $f = $r['fields'] ?? [];
    if (!empty($f['first_name']) || !empty($f['last_name'])) {
        $tracyWithNames[$r['id']] = $f['first_name'] . ' ' . $f['last_name'];
    }
}

echo '<p>Tracy records with status=success: <strong>' . count($tracySuccess['records']) . '</strong></p>';
echo '<p>Tracy records WITH first/last name: <strong>' . count($tracyWithNames) . '</strong></p>';

// Buscar Contacts creados desde "Skip Trace - Tracerfy"
$contactsSkipTrace = atList(TABLE_CONTACTS, [
    'filterByFormula' => "{Lead Source}='Skip Trace - Tracerfy'",
    'maxRecords'      => 100,
]);

$contactsEmpty = 0;
$contactsWithData = 0;
foreach ($contactsSkipTrace['records'] as $r) {
    $f = $r['fields'] ?? [];
    if (empty($f['Full Name']) && empty($f['Phone1'])) {
        $contactsEmpty++;
    } else {
        $contactsWithData++;
    }
}

echo '<p>Contacts from Skip Trace: <strong>' . count($contactsSkipTrace['records']) . '</strong></p>';
echo '<p>  └─ WITH data (Full Name OR Phone): ' . $contactsWithData . '</p>';
echo '<p>  └─ EMPTY (no Full Name, no Phone): <span class="' . ($contactsEmpty > 0 ? 'error' : 'success') . '">' . $contactsEmpty . '</span></p>';

if ($contactsEmpty > 0) {
    echo '<p style="color: #dc3545; font-weight: bold;">⚠️ PROBLEMA DETECTADO: ' . $contactsEmpty . ' Contacts vacíos creados por Skip Trace!</p>';
    echo '<p>Esto indica que el_chismoso está creando Contacts pero NO está copiando los datos de Tracy.</p>';
}

echo '</div>';

// 4. TEST: Intenta procesar UN Tracy record manualmente
echo '<div class="section">';
echo '<h2>4. Manual Test: Simulate el_chismoso for one Tracy record</h2>';

$testTracy = atList(TABLE_TRACY, [
    'filterByFormula' => "{status}='success'",
    'maxRecords'      => 1,
    'sort[0][field]'  => 'fecha_rastreo',
    'sort[0][direction]' => 'desc',
]);

if (empty($testTracy['records'])) {
    echo '<p class="empty">No Tracy records to test</p>';
} else {
    $r = $testTracy['records'][0];
    $tf = $r['fields'] ?? [];

    echo '<p>Selected Tracy record: <strong>' . $r['id'] . '</strong></p>';
    echo '<pre>';
    echo "first_name: " . var_export($tf['first_name'] ?? 'NOT SET', true) . "\n";
    echo "last_name: " . var_export($tf['last_name'] ?? 'NOT SET', true) . "\n";
    echo "primary_phone: " . var_export($tf['primary_phone'] ?? 'NOT SET', true) . "\n";
    echo "mail_address: " . var_export($tf['mail_address'] ?? 'NOT SET', true) . "\n";
    echo "tracerfy_id: " . var_export($tf['tracerfy_id'] ?? 'NOT SET', true) . "\n";
    echo "</pre>";

    // Simular lo que haría el_chismoso
    $firstName = trim($tf['first_name'] ?? '');
    $lastName = trim($tf['last_name'] ?? '');
    $fullName = trim("{$firstName} {$lastName}");

    echo '<p><strong>After trim() in el_chismoso:</strong></p>';
    echo '<pre>';
    echo "firstName = \"" . htmlspecialchars($firstName) . "\"\n";
    echo "lastName = \"" . htmlspecialchars($lastName) . "\"\n";
    echo "fullName = \"" . htmlspecialchars($fullName) . "\"\n";
    echo "</pre>";

    if (empty($fullName)) {
        echo '<p class="error">❌ PROBLEMA: fullName está VACÍO después de trim()!</p>';
        echo '<p>Esto significa que first_name y last_name están vacíos o son spaces/nulls.</p>';
    } else {
        echo '<p class="success">✅ fullName tiene valor: ' . htmlspecialchars($fullName) . '</p>';
    }
}

echo '</div>';

?>

</body>
</html>
