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
// Quo puts fields directly in data (with data.object = "message" string).
// Our test payloads may nest them in data.object as an array.
$dataObj = $event['data']['object'] ?? null;
$obj = (is_array($dataObj)) ? $dataObj : ($event['data'] ?? []);

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
// Property Address is a linked record — read the lookup field for actual text
$propertyAddress = '';
$propLookup = $fields['Property Address (from Property Address)'] ?? null;
if (is_array($propLookup) && !empty($propLookup)) {
    $propertyAddress = $propLookup[0];
} elseif (is_string($propLookup)) {
    $propertyAddress = $propLookup;
} else {
    $propertyAddress = $fields['Address'] ?? '';
}
$city            = $fields['City']                     ?? 'Green Bay';
$stage           = $fields['Stage']                    ?? 'New Lead';
$negotiationNotes= $fields['Negotiation notes']        ?? '';
$language        = $fields['Lenguage']                 ?? 'English';
$seguimientoStep = intval($fields['Seguimiento Step']  ?? -1);
$isDNC           = !empty($fields['Do not contact']);
$isReturning     = in_array($stage, ['Seguimiento', 'Contacted', 'Negotiation']);

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
    'isReturning'         => $isReturning,
    'seguimientoStep'     => $seguimientoStep,
]);
$fer = $claudeResult['fer'];

// ── 6b. Smart stage transitions ─────────────────────────────────
// If client was in Seguimiento and responds → move to Negotiation (stops Make follow-ups)
if ($stage === 'Seguimiento' && !empty($fer['responseToClient']) && ($fer['newStage'] ?? '') !== 'Dead') {
    $fer['newStage'] = 'Negotiation';
    fer_log_info('stage_reengagement', ['from' => 'Seguimiento', 'to' => 'Negotiation']);
}

// DNC guard: if contact is marked Do Not Contact, NEVER set Seguimiento (no outbound)
// Fer can respond to inbound (TCPA allows) but no automated follow-ups
if ($isDNC && ($fer['newStage'] ?? '') === 'Seguimiento') {
    $fer['newStage'] = 'Responded';
    fer_log_warn('dnc_blocked_seguimiento', ['contact' => $contactId, 'phone' => $fromPhone]);
}

// ── 7. Send SMS back to the client ──────────────────────────────
$smsResult = null;
if (!empty($fer['responseToClient'])) {
    $smsResult = fer_quo_send_sms($fromPhone, $fer['responseToClient']);
}

// ── 8. Escalation to Jorge ──────────────────────────────────────
if (!empty($fer['escalate'])) {
    // Calculate Fer Score: +3 owner, +2 motivation, +1 timeline, +2 urgency hot, +1 owed, +1 price
    $ferScore = 0;
    $fIsOwner    = $fer['isOwner']    ?? $isOwner;
    $fMotivation = $fer['motivation'] ?? $motivation;
    $fTimeline   = $fer['timeline']   ?? $timeline;
    $fUrgency    = $fer['urgency']    ?? $urgency;
    if ($fIsOwner === 'yes')                                    $ferScore += 3;
    if ($fMotivation !== 'unknown' && $fMotivation !== null)    $ferScore += 2;
    if ($fTimeline !== 'unknown' && $fTimeline !== null)        $ferScore += 2;
    if ($fUrgency === 'hot')                                    $ferScore += 2;
    elseif ($fUrgency === 'warm')                               $ferScore += 1;
    $ferScore = min($ferScore, 10);

    fer_telegram_alert([
        'contactName'     => $contactName,
        'clientPhone'     => $fromPhone,
        'propertyAddress' => $propertyAddress,
        'escalateReason'  => $fer['escalateReason']  ?? 'N/A',
        'isOwner'         => $fIsOwner,
        'motivation'      => $fMotivation,
        'timeline'        => $fTimeline,
        'urgency'         => $fUrgency,
        'clientMessage'   => $body,
        'ferResponse'     => $fer['responseToClient'] ?? '',
        'ferScore'        => $ferScore,
        'messageCount'    => $msgCount + 1,
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
    $newStage = $fer['newStage'] ?? 'Responded';
    $newNotes = fer_at_append_notes($negotiationNotes, $fer['notes'] ?? '(no note)');
    $contactUpdate = [
        'Stage'              => $newStage,
        'Last contact date'  => date('Y-m-d'),
        'Negotiation notes'  => $newNotes,
    ];
    // When Fer marks Seguimiento → set Step=0 to activate Make Engine
    if ($newStage === 'Seguimiento' && $stage !== 'Seguimiento') {
        $contactUpdate['Seguimiento Step'] = 0;
        fer_log_info('seguimiento_activated', ['contact' => $contactId]);
    }
    fer_at_update_contact($contactId, $contactUpdate);
}

// ── 10b. Log full conversation to Fer Conversations table (QC) ──
$existingConvo = fer_at_find_convo($fromPhone);
fer_at_log_conversation($fromPhone, $fer, $body, $contactName, $propertyAddress, $existingConvo);

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
