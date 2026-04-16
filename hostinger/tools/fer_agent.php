<?php
// ============================================================
// FER AI AGENT v5 — Pinnacle Holdings Group
// Direct Quo (OpenPhone) webhook receiver.
// Handles everything end-to-end: dedup, Airtable lookup/upsert,
// DataStore conversation history, Claude reply, SMS, Telegram
// escalation, CRM updates, logging.
//
// URL (post-deploy): https://pinnaclegroupwi.com/Tools/fer_agent.php
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/fer_logger.php';
require_once __DIR__ . '/lib/fer_deduplication.php';
require_once __DIR__ . '/lib/fer_airtable.php';
require_once __DIR__ . '/lib/fer_conversations.php';
require_once __DIR__ . '/lib/fer_claude.php';
require_once __DIR__ . '/lib/fer_quo.php';
require_once __DIR__ . '/lib/fer_telegram.php';

header('Content-Type: application/json');

// Return 200 fast on health check so Quo can validate the endpoint.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    echo json_encode(['ok' => true, 'service' => 'fer_agent', 'version' => 5]);
    exit;
}

// ── 1. Parse webhook body ───────────────────────────────────────
$raw   = file_get_contents('php://input');
$event = json_decode($raw, true);

if (!is_array($event)) {
    http_response_code(400);
    fer_log_warn('webhook_invalid_json', ['body_preview' => substr($raw, 0, 200)]);
    echo json_encode(['error' => 'invalid json']);
    exit;
}

$eventType = $event['type']    ?? '';
$eventId   = $event['id']      ?? '';
$obj       = $event['data']['object'] ?? ($event['data'] ?? []);

fer_log_info('webhook_received', [
    'event_id'   => $eventId,
    'event_type' => $eventType,
    'keys'       => array_keys($obj),
]);

// Only react to incoming SMS. Ignore delivery receipts, outbound, etc.
if ($eventType !== 'message.received' && $eventType !== 'message.delivered') {
    // pass-through for unexpected event types
    fer_log_info('webhook_ignored_type', ['type' => $eventType]);
    echo json_encode(['ok' => true, 'ignored' => $eventType]);
    exit;
}
if ($eventType === 'message.delivered') {
    echo json_encode(['ok' => true, 'ignored' => 'delivered']);
    exit;
}

$direction = $obj['direction'] ?? 'incoming';
if ($direction !== 'incoming') {
    fer_log_info('webhook_ignored_direction', ['direction' => $direction]);
    echo json_encode(['ok' => true, 'ignored' => 'outbound']);
    exit;
}

// ── 2. Extract core fields ──────────────────────────────────────
$messageId   = $obj['id']             ?? $eventId;
$fromPhone   = $obj['from']           ?? '';
$toPhoneList = $obj['to']             ?? [];
$body        = trim((string) ($obj['body'] ?? ''));
$convId      = $obj['conversationId'] ?? null;

if (empty($fromPhone) || $body === '') {
    http_response_code(400);
    fer_log_warn('webhook_missing_fields', ['messageId' => $messageId, 'from' => $fromPhone, 'body_empty' => $body === '']);
    echo json_encode(['error' => 'missing from or body']);
    exit;
}

// Self-loop protection: ignore messages we ourselves sent out.
$toFirst = is_array($toPhoneList) ? ($toPhoneList[0] ?? '') : (string) $toPhoneList;
if ($fromPhone === '+19207779886' || $fromPhone === '+19209777988') {
    fer_log_info('webhook_self_loop_ignored', ['from' => $fromPhone]);
    echo json_encode(['ok' => true, 'ignored' => 'self']);
    exit;
}

// ── 3. Deduplication ────────────────────────────────────────────
if (!fer_dedup_claim($messageId)) {
    echo json_encode(['ok' => true, 'ignored' => 'duplicate']);
    exit;
}

// ── 4. Airtable contact lookup (create if missing) ──────────────
$contact    = fer_at_find_contact_by_phone($fromPhone);
$contactId  = $contact['id']                           ?? null;
$fields     = $contact['fields']                       ?? [];
$contactName     = $fields['Full Name']                ?? $fields['First Name'] ?? 'there';
$propertyAddress = $fields['Property Address']         ?? ($fields['Address']    ?? '');
$city            = $fields['City']                     ?? 'Green Bay';
$stage           = $fields['Stage']                    ?? 'New Lead';
$negotiationNotes= $fields['Negotiation notes']        ?? '';
$language        = $fields['Lenguage']                 ?? 'English';

if ($contactId === null) {
    $created = fer_at_create_contact($fromPhone, [
        'Full Name' => 'Inbound ' . date('Y-m-d'),
        'Stage'     => 'New Lead',
    ]);
    if (is_array($created) && isset($created['id'])) {
        $contactId = $created['id'];
    }
    $contactName = 'there';
}

// ── 5. Conversation history fetch (local files) ─────────────────
$convRecord = fer_conv_get($fromPhone);
$history    = $convRecord['history']      ?? '';
$msgCount   = intval($convRecord['messageCount'] ?? 0);
$isOwner    = $convRecord['isOwner']      ?? 'unknown';
$motivation = $convRecord['motivation']   ?? 'unknown';
$timeline   = $convRecord['timeline']     ?? 'unknown';
$urgency    = $convRecord['urgency']      ?? 'unknown';

// ── 6. Ask Claude ───────────────────────────────────────────────
$claudeResult = fer_claude_decide([
    'contactName'         => $contactName,
    'propertyAddress'     => $propertyAddress,
    'city'                => $city,
    'language'            => $language,
    'stage'               => $stage,
    'negotiationNotes'    => $negotiationNotes,
    'clientMessage'       => $body,
    'conversationHistory' => $history,
    'messageCount'        => $msgCount,
    'isOwner'             => $isOwner,
    'motivation'          => $motivation,
    'timeline'            => $timeline,
    'urgency'             => $urgency,
]);
$fer = $claudeResult['fer'];

// ── 7. Send SMS back to the client ──────────────────────────────
$smsResult = null;
if (!empty($fer['responseToClient'])) {
    $smsResult = fer_quo_send_sms($fromPhone, $fer['responseToClient']);
}

// ── 8. Escalation to Jorge ──────────────────────────────────────
if (!empty($fer['escalate'])) {
    fer_telegram_alert([
        'contactName'     => $contactName,
        'clientPhone'     => $fromPhone,
        'propertyAddress' => $propertyAddress,
        'escalateReason'  => $fer['escalateReason']  ?? 'N/A',
        'isOwner'         => $fer['isOwner']    ?? $isOwner,
        'motivation'      => $fer['motivation'] ?? $motivation,
        'timeline'        => $fer['timeline']   ?? $timeline,
        'urgency'         => $fer['urgency']    ?? $urgency,
        'clientMessage'   => $body,
        'ferResponse'     => $fer['responseToClient'] ?? '',
    ]);
}

// ── 9. Persist conversation + qualification meta ────────────────
fer_conv_append_turn($fromPhone, $convRecord, $body, $fer['responseToClient'] ?? '', [
    'contactId'  => $contactId,
    'isOwner'    => $fer['isOwner']    ?? null,
    'motivation' => $fer['motivation'] ?? null,
    'timeline'   => $fer['timeline']   ?? null,
    'urgency'    => $fer['urgency']    ?? null,
    'language'   => $fer['language']   ?? null,
]);

// ── 10. Update Airtable contact ─────────────────────────────────
if ($contactId) {
    $newNotes = fer_at_append_notes($negotiationNotes, $fer['notes'] ?? '(no note)');
    fer_at_update_contact($contactId, [
        'Stage'              => $fer['newStage'] ?? 'Responded',
        'Last contact date'  => date('Y-m-d'),
        'Negotiation notes'  => $newNotes,
    ]);
}

// ── 11. Respond 200 to Quo ──────────────────────────────────────
http_response_code(200);
echo json_encode([
    'ok'              => true,
    'messageId'       => $messageId,
    'escalated'       => !empty($fer['escalate']),
    'escalated_model' => $claudeResult['escalated_model'] ?? false,
    'model_used'      => $claudeResult['model_used']      ?? null,
    'sms_sent'        => $smsResult['success'] ?? false,
    'newStage'        => $fer['newStage'] ?? null,
]);
