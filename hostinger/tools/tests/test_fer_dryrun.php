<?php
// ============================================================
// FER — Dry-run test harness (CLI)
//
// Simulates a Quo webhook locally without hitting production APIs
// except Claude (and only if ANTHROPIC_API_KEY is set).
// SMS + Telegram + Airtable + DataStore calls are stubbed.
//
// Usage:
//   php tests/test_fer_dryrun.php
//   DRY=1 php tests/test_fer_dryrun.php        # (default — no external writes)
//   DRY=0 php tests/test_fer_dryrun.php        # call real APIs (USE WITH CARE)
// ============================================================

$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/lib/fer_logger.php';
require_once $root . '/lib/fer_claude.php';

$DRY = getenv('DRY') !== '0';

echo "FER dry-run harness\n";
echo "DRY mode: " . ($DRY ? 'ON (no external writes)' : 'OFF (real calls)') . "\n";
echo "----\n";

$scenarios = [
    [
        'label' => '1. Cold first message — polite interest',
        'ctx' => [
            'contactName'         => 'Mary',
            'propertyAddress'     => '1234 Lake Shore Dr',
            'city'                => 'Green Bay',
            'language'            => 'English',
            'stage'               => 'New Lead',
            'negotiationNotes'    => '',
            'clientMessage'       => 'Hi, I saw your letter. Might be interested.',
            'conversationHistory' => '',
            'messageCount'        => 0,
            'isOwner'             => 'unknown',
            'motivation'          => 'unknown',
            'timeline'            => 'unknown',
            'urgency'             => 'unknown',
        ],
    ],
    [
        'label' => '2. HOT — court date next week',
        'ctx' => [
            'contactName'         => 'Bob',
            'propertyAddress'     => '567 Oak St',
            'city'                => 'Appleton',
            'language'            => 'English',
            'stage'               => 'Responded',
            'negotiationNotes'    => 'Owner confirmed, pre-foreclosure.',
            'clientMessage'       => 'Bank scheduled my court date for next Tuesday.',
            'conversationHistory' => "[2026-04-12 09:00] Client: yes I own the house\n[2026-04-12 09:02] Fer: Thanks for confirming — can I ask what is going on?\n[2026-04-12 09:05] Client: behind on payments\n",
            'messageCount'        => 3,
            'isOwner'             => 'yes',
            'motivation'          => 'pre-foreclosure',
            'timeline'            => 'unknown',
            'urgency'             => 'unknown',
        ],
    ],
    [
        'label' => '3. Objection — "bring me a buyer first"',
        'ctx' => [
            'contactName'         => 'Teresa',
            'propertyAddress'     => '89 Maple Ave',
            'city'                => 'Green Bay',
            'language'            => 'English',
            'stage'               => 'Negotiation',
            'negotiationNotes'    => '',
            'clientMessage'       => "I'll talk when you bring me a buyer.",
            'conversationHistory' => '',
            'messageCount'        => 1,
            'isOwner'             => 'yes',
            'motivation'          => 'tired landlord',
            'timeline'            => 'unknown',
            'urgency'             => 'unknown',
        ],
    ],
    [
        'label' => '4. DNC — STOP',
        'ctx' => [
            'contactName'         => 'Unknown',
            'propertyAddress'     => '',
            'city'                => 'Green Bay',
            'language'            => 'English',
            'stage'               => 'New Lead',
            'negotiationNotes'    => '',
            'clientMessage'       => 'STOP texting me',
            'conversationHistory' => '',
            'messageCount'        => 1,
            'isOwner'             => 'unknown',
            'motivation'          => 'unknown',
            'timeline'            => 'unknown',
            'urgency'             => 'unknown',
        ],
    ],
    [
        'label' => '5. Probate complexity (expects Sonnet escalation on model)',
        'ctx' => [
            'contactName'         => 'Luis',
            'propertyAddress'     => '45 River Rd',
            'city'                => 'Green Bay',
            'language'            => 'Spanish',
            'stage'               => 'Negotiation',
            'negotiationNotes'    => '',
            'clientMessage'       => 'Mi hermano y yo heredamos la casa y tenemos un lien del IRS. Hay probate abierto.',
            'conversationHistory' => '',
            'messageCount'        => 2,
            'isOwner'             => 'yes',
            'motivation'          => 'inherited',
            'timeline'            => 'unknown',
            'urgency'             => 'unknown',
        ],
    ],
];

foreach ($scenarios as $i => $s) {
    echo "\n==== " . $s['label'] . " ====\n";
    $t0 = microtime(true);
    $res = fer_claude_decide($s['ctx']);
    $dt = round((microtime(true) - $t0) * 1000);

    echo "Model used:      " . ($res['model_used'] ?? '?') . "\n";
    echo "Escalated model: " . ($res['escalated_model'] ? 'YES' : 'no') . "\n";
    echo "Latency ms:      $dt\n";
    echo "Fer output:\n";
    echo json_encode($res['fer'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n----\nDry-run complete. Log file: " . FER_LOG_PATH . "\n";
