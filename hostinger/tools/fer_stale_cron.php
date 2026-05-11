<?php
// ============================================================
// FER — Stale Contact Cron
// Moves contacts stuck in "Contacted" with no response for 5+ days
// to "Seguimiento" (Step=0) so the Make Engine picks them up.
//
// Run via Hostinger Cron: daily at 8:00 AM CST
// URL: https://pinnaclegroupwi.com/Tools/fer_stale_cron.php
// ============================================================

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// ── KILL-SWITCH stale cron (2026-05-11) ────────────────────────────
// Pausado por Jorge: outreach stack en quiet period (Quo carrier + Telnyx setup).
// Bloquea la transición Contacted→Seguimiento mientras los crons de Fer estén OFF.
// Reactivar: definir FER_STALE_ENABLED=true en config.php.
if (!defined('FER_STALE_ENABLED') || FER_STALE_ENABLED !== true) {
    error_log('[fer_stale_cron] paused via kill-switch (outreach_quiet_period_2026-05-11)');
    echo json_encode(['ok' => true, 'paused' => true, 'reason' => 'outreach_quiet_period', 'since' => '2026-05-11']);
    exit;
}

define('BASE_ID',     'appfQbDA750Oihy9J');
define('TABLE_CONTACTS', 'tblacvw0Ss770x8l5');
define('STALE_DAYS', 5);

$cutoff = date('Y-m-d', strtotime('-' . STALE_DAYS . ' days'));

$formula = "AND("
    . "{Stage} = 'Contacted',"
    . "IS_BEFORE({Last contact date}, '" . $cutoff . "')"
    . ")";

$url = 'https://api.airtable.com/v0/' . BASE_ID . '/' . TABLE_CONTACTS
     . '?' . http_build_query([
         'filterByFormula' => $formula,
         'maxRecords'      => 50,
         'fields[]'        => 'Full Name',
     ]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
    CURLOPT_TIMEOUT        => 15,
]);
$resp = curl_exec($ch);
curl_close($ch);
$data = json_decode($resp, true);
$records = $data['records'] ?? [];

$moved = 0;
foreach ($records as $rec) {
    $recId = $rec['id'];
    $name  = $rec['fields']['Full Name'] ?? '?';

    $ch = curl_init('https://api.airtable.com/v0/' . BASE_ID . '/' . TABLE_CONTACTS . '/' . $recId);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode(['fields' => [
            'Stage'            => 'Seguimiento',
            'Seguimiento Step' => 0,
        ]]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        $moved++;
    }
}

echo json_encode([
    'ok'      => true,
    'checked' => count($records),
    'moved'   => $moved,
    'cutoff'  => $cutoff,
]);
