<?php
/**
 * ============================================================
 *  TRACY RECOVERY — Pinnacle Holdings Group LLC
 *  Ejecutar UNA SOLA VEZ desde el navegador
 *
 *  Qué hace:
 *  Busca todos los registros de Tracy con:
 *    - status = "success"
 *    - pushed_to_contacts = vacío (false)
 *  Y los empuja a el_chismoso.php uno por uno
 *
 *  URL: https://pinnaclegroupwi.com/Tools/tracy_recovery.php
 *  BORRAR DEL SERVIDOR después de ejecutar
 * ============================================================
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

define('BASE_ID',        'appfQbDA750Oihy9J');
define('TABLE_TRACY',    'tbl6CJm4kYspOuTDB');
define('CHISMOSO_URL',   'https://pinnaclegroupwi.com/Tools/el_chismoso.php');
define('CHISMOSO_TOKEN', 'pinnacle2026');

echo "<h2>Tracy Recovery — Pinnacle Holdings</h2>";
echo "<pre>";

// ── Busca todos los Tracy con success y sin pushed ────────────
$formula = "AND({status}='success',{pushed_to_contacts}=FALSE())";
$url     = 'https://api.airtable.com/v0/' . BASE_ID . '/' . TABLE_TRACY
         . '?filterByFormula=' . rawurlencode($formula)
         . '&maxRecords=100';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
    CURLOPT_TIMEOUT        => 20,
]);
$res  = curl_exec($ch);
curl_close($ch);
$data = json_decode($res, true);

$records = $data['records'] ?? [];

if (empty($records)) {
    echo "No hay registros pendientes. Todo está al día.\n";
    echo "</pre>";
    exit(0);
}

echo "Encontrados: " . count($records) . " registros para procesar\n";
echo "═══════════════════════════════════════\n\n";

$success = 0;
$errors  = 0;

foreach ($records as $record) {
    $tracyId  = $record['id'];
    $fields   = $record['fields'] ?? [];
    $address  = $fields['address'] ?? 'sin dirección';

    echo "Procesando: {$address} (Tracy: {$tracyId})\n";

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

    $chismData = json_decode($chismRes, true);

    if ($chismCode === 200 && !empty($chismData['success'])) {
        $action = $chismData['action'] ?? 'unknown';
        $name   = $chismData['name']   ?? 'sin nombre';
        echo "  OK {$action}: {$name}\n\n";
        $success++;
    } else {
        $errorMsg = $chismData['result']['error']['message'] ?? $chismRes;
        echo "  ERROR (HTTP {$chismCode}): {$errorMsg}\n\n";
        $errors++;
    }

    sleep(1);
}

echo "═══════════════════════════════════════\n";
echo "Exitosos : {$success}\n";
echo "Errores  : {$errors}\n";
echo "Total    : " . count($records) . "\n\n";
echo "IMPORTANTE: Borra este archivo del servidor cuando termines.\n";
echo "</pre>";
