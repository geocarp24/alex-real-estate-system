<?php
// ============================================================
// FER — Morning Brief + System Health Check
// Sends daily summary to Jorge via Telegram at 8:30 AM CST
//
// Cron: daily at 8:30 AM CST (14:30 UTC)
// URL: https://pinnaclegroupwi.com/Tools/fer_morning_brief.php
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/fer_logger.php';

header('Content-Type: application/json');

define('MB_BASE', 'appfQbDA750Oihy9J');
define('MB_CONTACTS', 'tblacvw0Ss770x8l5');
define('MB_LEADS', 'tblxZz2EWIglOLnEd');
define('MB_DEALS', 'tbliaEKxBHKBx7ZK2');
define('MB_FER_CONVOS', 'tbleausFNpHhqLfsm');

function mb_at($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true) ?? [];
}

function mb_count($tableId, $formula) {
    $url = 'https://api.airtable.com/v0/' . MB_BASE . '/' . $tableId
         . '?' . http_build_query(['filterByFormula' => $formula, 'maxRecords' => 100, 'fields[]' => 'Stage']);
    $data = mb_at($url);
    return count($data['records'] ?? []);
}

// ── Pipeline counts ─────────────────────────────────────────────
$toBeContacted = mb_count(MB_CONTACTS, "{Stage}='To Be Contacted'");
$contacted     = mb_count(MB_CONTACTS, "{Stage}='Contacted'");
$responded     = mb_count(MB_CONTACTS, "{Stage}='Responded'");
$negotiation   = mb_count(MB_CONTACTS, "{Stage}='Negotiation'");
$seguimiento   = mb_count(MB_CONTACTS, "{Stage}='Seguimiento'");
$dead          = mb_count(MB_CONTACTS, "{Stage}='Dead'");

$leadsReview   = mb_count(MB_LEADS, "{Stage}='Review this Deal'");
$totalDeals    = mb_count(MB_DEALS, "TRUE()");

// ── Recent activity (last 24h) ──────────────────────────────────
$yesterday = date('Y-m-d', strtotime('-1 day'));
$recentConvos = mb_count(MB_FER_CONVOS, "IS_AFTER({Last Contact},'{$yesterday}')");
$recentEscalated = mb_count(MB_FER_CONVOS, "AND(IS_AFTER({Last Contact},'{$yesterday}'),{Escalated}=TRUE())");

// ── System health checks ────────────────────────────────────────
$health = [];

// Check Quo credits
$quoOk = true;
$quoMsg = '';
if (defined('QUO_API_KEY')) {
    $ch = curl_init('https://api.openphone.com/v1/phone-numbers');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . QUO_API_KEY],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $quoResp = curl_exec($ch);
    $quoCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($quoCode === 200) {
        $health[] = '✅ Quo API: conectado';
    } else {
        $health[] = '🔴 Quo API: error (HTTP ' . $quoCode . ')';
        $quoOk = false;
    }
} else {
    $health[] = '🔴 Quo API: key no configurada';
    $quoOk = false;
}

// Check Anthropic
if (defined('ANTHROPIC_API_KEY') && strlen(ANTHROPIC_API_KEY) > 10) {
    $health[] = '✅ Claude API: configurada';
} else {
    $health[] = '🔴 Claude API: no configurada';
}

// Check log file writable
$logPath = __DIR__ . '/fer_agent.log';
if (is_writable($logPath) || is_writable(dirname($logPath))) {
    $health[] = '✅ Logs: escribiendo';
} else {
    $health[] = '⚠️ Logs: no se puede escribir';
}

// Check conversation files directory
$convDir = __DIR__ . '/fer_conversations';
$convCount = count(glob($convDir . '/*.json') ?: []);
$health[] = "✅ Conversaciones locales: {$convCount} archivos";

// Check last fer_agent.log activity
if (is_file($logPath)) {
    $lastMod = filemtime($logPath);
    $hoursAgo = round((time() - $lastMod) / 3600, 1);
    if ($hoursAgo > 24) {
        $health[] = "⚠️ Último log: hace {$hoursAgo}h (sin actividad)";
    } else {
        $health[] = "✅ Último log: hace {$hoursAgo}h";
    }
}

// ── Build message ───────────────────────────────────────────────
$date = date('l, M j Y');
$msg = "PINNACLE — Resumen del día\n"
     . "{$date}\n"
     . "━━━━━━━━━━━━━━━━━━━━\n\n"
     . "Pipeline Contacts:\n"
     . "  {$toBeContacted} listos para primer SMS\n"
     . "  {$contacted} contactados (esperando respuesta)\n"
     . "  {$responded} respondieron (Fer calificando)\n"
     . "  {$negotiation} en negociación\n"
     . "  {$seguimiento} en seguimiento\n"
     . "  {$dead} cerrados/dead\n\n"
     . "Leads: {$leadsReview} pendientes de skip trace\n"
     . "Deals: {$totalDeals} en pipeline\n\n"
     . "Últimas 24h:\n"
     . "  {$recentConvos} conversaciones activas\n"
     . "  {$recentEscalated} escaladas a Jorge\n\n"
     . "Sistema:\n"
     . implode("\n", array_map(function($h) { return "  {$h}"; }, $health));

// ── Send via Telegram ───────────────────────────────────────────
if (defined('TELEGRAM_BOT_TOKEN') && defined('TELEGRAM_CHAT_ID')) {
    $ch = curl_init('https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'chat_id' => TELEGRAM_CHAT_ID,
            'text'    => $msg,
        ]),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $tgResp = curl_exec($ch);
    $tgCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    fer_log_info('morning_brief_sent', ['tg_code' => $tgCode]);
}

echo json_encode([
    'ok' => true,
    'pipeline' => [
        'to_be_contacted' => $toBeContacted,
        'contacted'        => $contacted,
        'responded'        => $responded,
        'negotiation'      => $negotiation,
        'seguimiento'      => $seguimiento,
        'dead'             => $dead,
    ],
    'leads_pending' => $leadsReview,
    'deals'         => $totalDeals,
    'recent_24h'    => $recentConvos,
    'escalated_24h' => $recentEscalated,
    'health'        => $health,
]);
