<?php
// Fer diagnostic — temporary, delete after debugging
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$checks = [
    'ANTHROPIC_API_KEY' => defined('ANTHROPIC_API_KEY') && strlen(ANTHROPIC_API_KEY) > 10,
    'QUO_API_KEY'       => defined('QUO_API_KEY') && strlen(QUO_API_KEY) > 10,
    'MAKE_API_TOKEN'    => defined('MAKE_API_TOKEN') && strlen(MAKE_API_TOKEN) > 10,
    'TELEGRAM_BOT_TOKEN'=> defined('TELEGRAM_BOT_TOKEN') && strlen(TELEGRAM_BOT_TOKEN) > 10,
    'TELEGRAM_CHAT_ID'  => defined('TELEGRAM_CHAT_ID') && strlen(TELEGRAM_CHAT_ID) > 3,
    'AIRTABLE_TOKEN'    => defined('AIRTABLE_TOKEN') && strlen(AIRTABLE_TOKEN) > 10,
];

$logPath = __DIR__ . '/../fer_agent.log';
$lastLines = [];
if (is_file($logPath)) {
    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lastLines = array_slice($lines, -20);
}

echo json_encode([
    'secrets_ok' => $checks,
    'all_ok'     => !in_array(false, $checks, true),
    'log_lines'  => count($lastLines),
    'last_logs'  => $lastLines,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
