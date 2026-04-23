<?php
// ============================================================
// FER — Review Request Engine
// Sends SMS to homeowners after deal closes asking for Google review.
// Cron: daily at 10:00 AM CST (16:00 UTC)
//
// Workflow:
//   1. Read Airtable Deals where Stage == "Closed Won" AND review_request_sent != true
//   2. For each: pull Contact (phone + preferred language)
//   3. Send bilingual SMS with GBP review link
//   4. Mark Deal.review_request_sent = true + log timestamp
//   5. 7 days later (separate run), follow-up if no review posted yet (soft nudge)
//
// Safety: only one review_request per deal ever. Opt-out respected.
// Runs AFTER fer_seguimiento so they don't clash.
//
// Config via hostinger/Tools/config.php (AIRTABLE_TOKEN, QUO_API_KEY, TELEGRAM_BOT_TOKEN)
// ============================================================

require_once __DIR__ . '/config.php';

date_default_timezone_set('America/Chicago');

// --- Constants ---
define('AIRTABLE_BASE',    'appfQbDA750Oihy9J');
define('DEALS_TABLE',      'tbliaEKxBHKBx7ZK2');
define('CONTACTS_TABLE',   'tblacvw0Ss770x8l5');
define('GBP_REVIEW_URL',   'https://g.page/r/YOUR_GBP_PLACE_ID/review'); // TODO: Jorge fills in with actual GBP Place ID
define('FOLLOWUP_DAYS',    7);
define('MAX_PER_RUN',      10);

// --- Bilingual copy ---
$COPY = [
    'en' => [
        'first' => "Hi %NAME%, this is Fer from Pinnacle Holdings. Glad we got your home sold quickly! Quick favor — if you're happy with the experience, would you share a short Google review? It really helps other Wisconsin homeowners find us. %URL% — Takes 30 seconds. Thanks!",
        'followup' => "Hi %NAME%, just following up on that review ask — no pressure, but if you'd drop us a quick star rating on Google it means the world. %URL% — Thank you again!",
    ],
    'es' => [
        'first' => "Hola %NAME%, soy Fer de Pinnacle Holdings. ¡Qué bueno que cerramos la venta de tu casa rápido! Pequeño favor — si quedaste conforme, ¿podrías dejarnos una reseña corta en Google? Ayuda a otros dueños de Wisconsin a encontrarnos. %URL% — Son 30 segundos. ¡Gracias!",
        'followup' => "Hola %NAME%, siguiendo con el tema de la reseña — sin presión, pero si nos dejas una estrella en Google nos ayuda muchísimo. %URL% — ¡Gracias de nuevo!",
    ],
];

// --- Helpers ---

function airtable_get(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
        CURLOPT_TIMEOUT => 15,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode((string) $r, true) ?: [];
}

function airtable_patch(string $url, array $fields): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode(['fields' => $fields, 'typecast' => true]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode((string) $r, true) ?: [];
}

function send_sms(string $to_e164, string $body): bool {
    // Uses Quo (existing SMS channel)
    $ch = curl_init('https://api.quo.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'to' => $to_e164,
            'from' => 'deals',
            'text' => $body,
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . QUO_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

function telegram_alert(string $text): void {
    if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) return;
    $ch = curl_init('https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => TELEGRAM_CHAT_ID,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]),
        CURLOPT_TIMEOUT => 8,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// --- Phase 1: First request (Closed Won + not yet requested) ---

function find_pending_first_request(): array {
    $filter = rawurlencode("AND({Stage}='Closed Won', OR({review_request_sent}=BLANK(), {review_request_sent}=FALSE()))");
    $url = 'https://api.airtable.com/v0/' . AIRTABLE_BASE . '/' . DEALS_TABLE . '?filterByFormula=' . $filter . '&maxRecords=' . MAX_PER_RUN;
    $data = airtable_get($url);
    return $data['records'] ?? [];
}

// --- Phase 2: 7-day follow-up (sent but no review after N days) ---

function find_pending_followup(): array {
    $cutoff = date('c', strtotime('-' . FOLLOWUP_DAYS . ' days'));
    $filter = rawurlencode("AND({review_request_sent}=TRUE(), {review_followup_sent}=BLANK(), IS_BEFORE({review_request_sent_at}, DATETIME_PARSE('$cutoff')), {review_received}=BLANK())");
    $url = 'https://api.airtable.com/v0/' . AIRTABLE_BASE . '/' . DEALS_TABLE . '?filterByFormula=' . $filter . '&maxRecords=' . MAX_PER_RUN;
    $data = airtable_get($url);
    return $data['records'] ?? [];
}

// --- Process a single deal ---

function process_deal(array $deal, string $phase /* first | followup */): array {
    global $COPY;
    $fields = $deal['fields'] ?? [];
    $contactIds = $fields['Contact'] ?? [];
    $contactId = is_array($contactIds) ? ($contactIds[0] ?? null) : null;
    if (!$contactId) return ['ok' => false, 'reason' => 'no_contact_link'];

    // Fetch contact
    $cUrl = 'https://api.airtable.com/v0/' . AIRTABLE_BASE . '/' . CONTACTS_TABLE . '/' . $contactId;
    $contact = airtable_get($cUrl);
    $cf = $contact['fields'] ?? [];
    $phone = $cf['Phone1'] ?? $cf['Phone2'] ?? '';
    $name  = trim($cf['Full Name'] ?? 'there');
    $firstName = strtok($name, ' ');
    $lang  = strtolower($cf['Preferred Language'] ?? 'en');
    if (!in_array($lang, ['en','es'], true)) $lang = 'en';

    if (!$phone) return ['ok' => false, 'reason' => 'no_phone'];
    if (!empty($cf['sms_opt_out'])) return ['ok' => false, 'reason' => 'opted_out'];

    $template = $COPY[$lang][$phase];
    $body = str_replace(['%NAME%', '%URL%'], [$firstName, GBP_REVIEW_URL], $template);

    $ok = send_sms($phone, $body);
    if (!$ok) return ['ok' => false, 'reason' => 'sms_failed'];

    // Update deal
    $dUrl = 'https://api.airtable.com/v0/' . AIRTABLE_BASE . '/' . DEALS_TABLE . '/' . $deal['id'];
    $update = $phase === 'first'
        ? ['review_request_sent' => true, 'review_request_sent_at' => date('c')]
        : ['review_followup_sent' => true, 'review_followup_sent_at' => date('c')];
    airtable_patch($dUrl, $update);

    return ['ok' => true, 'phase' => $phase, 'deal_id' => $deal['id'], 'phone' => $phone, 'lang' => $lang];
}

// --- Main ---

$results = [];

foreach (find_pending_first_request() as $deal) {
    $results[] = process_deal($deal, 'first');
}
foreach (find_pending_followup() as $deal) {
    $results[] = process_deal($deal, 'followup');
}

$sent = array_filter($results, fn($r) => $r['ok'] ?? false);
$failed = array_filter($results, fn($r) => !($r['ok'] ?? false));

$summary = "🌟 *Fer — review requests*\n"
         . "sent: " . count($sent) . "\n"
         . "failed: " . count($failed) . "\n"
         . (count($failed) > 0 ? "fails: " . implode(", ", array_unique(array_map(fn($r) => $r['reason'] ?? 'unknown', $failed))) : "");
telegram_alert($summary);

echo json_encode([
    'ok' => true,
    'sent' => count($sent),
    'failed' => count($failed),
    'results' => $results,
], JSON_PRETTY_PRINT);
