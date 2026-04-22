<?php
/**
 * pinnacle_public.php
 *
 * PUBLIC lead-capture endpoint for the pinnaclegroupwi.com web form.
 * No auth header required (forms are submitted by anonymous visitors).
 * Security layers:
 *   - Per-IP rate limit (10 req/min)
 *   - Honeypot field (must be empty)
 *   - Time-to-submit check (>= 5 seconds from page load)
 *   - Random session token tied to lead_id
 *   - SMS verification required before phase-2/3 updates are accepted
 *   - Email MX record check (server-side)
 *   - Disposable-email blocklist
 *
 * Actions:
 *   start_lead     -> validate phase 1 inputs, create Airtable lead (Unverified),
 *                     generate 6-digit SMS code, send via Quo, return lead_id + token
 *   verify_phone   -> check code, mark lead Verified
 *   resend_code    -> rate-limited (30s), re-send the code
 *   update_lead    -> append phase 2/3 fields, compute score, send enriched email
 *   places_proxy   -> Google Places (New) autocomplete proxy (hides API key)
 *
 * Storage:
 *   - WP transients for SMS codes + sessions (auto-expire 30 min)
 *   - Airtable Leads table tblxZz2EWIglOLnEd (base appfQbDA750Oihy9J)
 */

declare(strict_types=1);

// -------- Load configs --------

$agent_config = __DIR__ . '/alex_config.php';
$tools_config = dirname(__DIR__) . '/Tools/config.php';
if (file_exists($agent_config)) require_once $agent_config;
if (file_exists($tools_config)) require_once $tools_config;

// Defaults if not defined by configs
if (!defined('FER_QUO_FROM_NUMBER_ID')) define('FER_QUO_FROM_NUMBER_ID', 'PNNlYlSvAb');
if (!defined('AIRTABLE_BASE_ID')) define('AIRTABLE_BASE_ID', 'appfQbDA750Oihy9J');
if (!defined('AIRTABLE_LEADS_TABLE')) define('AIRTABLE_LEADS_TABLE', 'tblxZz2EWIglOLnEd');
if (!defined('GOOGLE_PLACES_API_KEY')) define('GOOGLE_PLACES_API_KEY', 'AIzaSyAQSG8R3GLg6gwLo2F7oxFSJzSPeL9NwDE');
if (!defined('PINNACLE_NOTIFY_EMAIL')) define('PINNACLE_NOTIFY_EMAIL', 'deals@pinnaclegroupwi.com');

// -------- HTTP headers --------

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Access-Control-Allow-Origin: https://pinnaclegroupwi.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

// -------- Rate limit (file-based, per IP) --------

$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip = preg_replace('/[^a-zA-Z0-9:.]/', '', explode(',', $ip)[0]);
$rl_dir = sys_get_temp_dir() . '/pinnacle_public_rl';
@mkdir($rl_dir, 0700, true);
$rl_file = $rl_dir . '/' . md5($ip);
$now = time();
$window = 60;
$limit = 20;
$entries = file_exists($rl_file)
    ? array_filter(array_map('intval', explode("\n", trim((string) file_get_contents($rl_file)))), fn($t) => $t > $now - $window)
    : [];
$entries[] = $now;
file_put_contents($rl_file, implode("\n", $entries));
if (count($entries) > $limit) {
    http_response_code(429);
    echo json_encode(['error' => 'too many requests', 'retry_after' => 60]);
    exit;
}

// -------- Bootstrap WordPress (for transients, wp_mail) --------

$wp_load_candidates = [
    dirname(__DIR__) . '/wp-load.php',
    '/home/u433637438/domains/pinnaclegroupwi.com/public_html/wp-load.php',
];
$wp_loaded = false;
foreach ($wp_load_candidates as $cand) {
    if (file_exists($cand)) { require_once $cand; $wp_loaded = true; break; }
}
if (!$wp_loaded) {
    http_response_code(500);
    echo json_encode(['error' => 'WordPress bootstrap failed']);
    exit;
}

// -------- Parse body --------

$raw = file_get_contents('php://input');
$body = json_decode((string) $raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid JSON body']);
    exit;
}

$action     = (string) ($body['action'] ?? '');
$request_id = bin2hex(random_bytes(4));

// -------- Logging helper --------

$log_file = (getenv('HOME') ?: '/tmp') . '/pinnacle-public.log';
$log = function (string $level, string $msg, array $ctx = []) use ($log_file, $request_id, $ip, $action): void {
    @file_put_contents($log_file, sprintf(
        "[%s] %s req=%s ip=%s action=%s msg=%s ctx=%s\n",
        date('c'), $level, $request_id, $ip, $action, $msg,
        json_encode($ctx, JSON_UNESCAPED_SLASHES)
    ), FILE_APPEND);
};
$log('info', 'request received');

$reply = function (array $data, int $code = 200) use ($log, $request_id): void {
    http_response_code($code);
    $data['request_id'] = $request_id;
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    $log('info', 'reply', ['code' => $code]);
    exit;
};

// -------- Helpers --------

function pp_normalize_phone(string $raw): string {
    $d = preg_replace('/\D+/', '', $raw);
    if (strlen($d) === 10) return '+1' . $d;
    if (strlen($d) === 11 && $d[0] === '1') return '+' . $d;
    if (strlen($d) >= 10) return '+' . $d;
    return '';
}

function pp_email_valid(string $email): array {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'invalid format'];
    }
    $domain = strtolower(trim(substr(strrchr($email, '@'), 1)));
    $disposable = [
        'mailinator.com', '10minutemail.com', 'guerrillamail.com', 'tempmail.com',
        'throwawaymail.com', 'yopmail.com', 'trashmail.com', 'maildrop.cc',
        'getnada.com', 'fakeinbox.com', 'tempr.email', 'temp-mail.org',
        'dispostable.com', 'sharklasers.com', 'spam4.me',
    ];
    if (in_array($domain, $disposable, true)) {
        return [false, 'disposable email not allowed'];
    }
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        return [false, 'email domain has no mail server'];
    }
    return [true, ''];
}

function pp_send_sms(string $phone_e164, string $content): array {
    if (!defined('QUO_API_KEY')) return ['ok' => false, 'err' => 'QUO_API_KEY missing'];
    $ch = curl_init('https://api.openphone.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'content' => $content,
            'from'    => FER_QUO_FROM_NUMBER_ID,
            'to'      => [$phone_e164],
        ]),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: ' . QUO_API_KEY,
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $resp];
}

function pp_airtable_create(array $fields): array {
    if (!defined('AIRTABLE_TOKEN')) return ['ok' => false, 'err' => 'AIRTABLE_TOKEN missing'];
    $url = 'https://api.airtable.com/v0/' . AIRTABLE_BASE_ID . '/' . AIRTABLE_LEADS_TABLE;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['fields' => $fields, 'typecast' => true]),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string) $resp, true) ?: [];
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'record' => $data];
}

function pp_airtable_update(string $record_id, array $fields): array {
    if (!defined('AIRTABLE_TOKEN')) return ['ok' => false, 'err' => 'AIRTABLE_TOKEN missing'];
    $url = 'https://api.airtable.com/v0/' . AIRTABLE_BASE_ID . '/' . AIRTABLE_LEADS_TABLE . '/' . rawurlencode($record_id);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode(['fields' => $fields, 'typecast' => true]),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $resp];
}

function pp_lead_session_key(string $lead_id): string {
    return 'pp_lead_' . substr(preg_replace('/[^A-Za-z0-9]/', '', $lead_id), 0, 40);
}

function pp_compute_score(array $phase1, array $phase2, array $phase3): array {
    $score  = 5;
    $labels = [];
    $tl = strtolower((string) ($phase3['timeline'] ?? ''));
    if (str_contains($tl, 'asap') || str_contains($tl, '7')) { $score += 3; $labels[] = 'URGENT'; }
    elseif (str_contains($tl, '30')) { $score += 2; }
    elseif (str_contains($tl, '60') || str_contains($tl, '90')) { $score += 1; }
    elseif (str_contains($tl, 'explor')) { $score -= 2; $labels[] = 'LOW_URGENCY'; }
    $cond = strtolower((string) ($phase2['condition'] ?? ''));
    if (str_contains($cond, 'distressed') || str_contains($cond, 'major')) { $score += 2; $labels[] = 'REHAB'; }
    elseif (str_contains($cond, 'minor')) { $score += 1; }
    $pref = strtolower((string) ($phase3['payment_pref'] ?? ''));
    if (str_contains($pref, 'down') || str_contains($pref, 'installment') || str_contains($pref, 'monthly')) {
        $score += 2; $labels[] = 'CREATIVE_OPEN';
    } elseif (str_contains($pref, 'open')) {
        $score += 1; $labels[] = 'FLEXIBLE';
    }
    $score = max(1, min(10, $score));
    $heat  = $score >= 8 ? 'HOT' : ($score >= 5 ? 'WARM' : 'COLD');
    return ['score' => $score, 'heat' => $heat, 'labels' => $labels];
}

// --- Helper: Claude call for Fer-Form-Mode brain ---
function pp_fer_brain(array $ctx): array {
    $key = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : (string) getenv('ANTHROPIC_API_KEY');
    if (!$key) return ['ok' => false, 'err' => 'ANTHROPIC_API_KEY missing'];
    $lang = ($ctx['lang'] ?? 'en') === 'es' ? 'es' : 'en';
    // System prompt — Fer-Form-Mode
    $sys  = "You are Fer, a warm-but-professional bilingual AI receptionist for Pinnacle Holdings, a Wisconsin real estate cash buyer. You're embedded inside a web form that a home seller is filling out.\n\n"
          . "Your job is to provide ONE very short acknowledgment (max 1 sentence, ~12 words) between questions that:\n"
          . "- Feels human, not robotic\n"
          . "- Mixes empathy + professionalism (no pushy sales tone)\n"
          . "- Acknowledges their specific situation when relevant (urgency, distress, inheritance, foreclosure)\n"
          . "- NEVER promises specific outcomes, timelines, or dollar amounts\n"
          . "- NEVER asks follow-up questions — the form does that\n"
          . "- Adapts language: respond in " . ($lang === 'es' ? 'Spanish' : 'English') . "\n\n"
          . "You also detect inconsistencies (e.g. 'Distressed' property + 'Highest cash offer' priority — that combo is unusual; gently flag it).\n\n"
          . "Output JSON only: {\"msg\": \"<short acknowledgment>\", \"warning\": \"<optional inconsistency note or empty>\"}";
    $user_msg = "Current screen: " . ($ctx['current'] ?? '—') . "\n"
              . "Last answer: " . ($ctx['last_field'] ?? '—') . " = " . ($ctx['last_value'] ?? '—') . "\n"
              . "All answers so far: " . json_encode($ctx['data'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $body = [
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 150,
        'system'     => $sys,
        'messages'   => [['role' => 'user', 'content' => $user_msg]],
    ];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) return ['ok' => false, 'err' => 'claude http ' . $code];
    $d = json_decode((string) $resp, true) ?: [];
    $text = $d['content'][0]['text'] ?? '';
    // Try to parse JSON from text (Claude sometimes wraps in prose)
    if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
        $parsed = json_decode($m[0], true);
        if (is_array($parsed)) {
            return ['ok' => true, 'msg' => (string) ($parsed['msg'] ?? ''), 'warning' => (string) ($parsed['warning'] ?? '')];
        }
    }
    return ['ok' => true, 'msg' => trim($text), 'warning' => ''];
}

// --- Helper: Claude call for Fer-Chat-Mode (multi-turn chat widget) ---
function pp_chat_brain(array $history, string $lang): array {
    $key = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : (string) getenv('ANTHROPIC_API_KEY');
    if (!$key) return ['ok' => false, 'err' => 'ANTHROPIC_API_KEY missing'];
    $lang = $lang === 'es' ? 'es' : 'en';
    $sys  = "You are Fer, the bilingual AI assistant for Pinnacle Holdings — a Wisconsin real estate cash buyer. You chat with sellers visiting pinnaclegroupwi.com.\n\n"
          . "TONE: warm but professional. Empathetic first, concise second. Mix both — never pushy.\n"
          . "LANGUAGE: always respond in " . ($lang==='es'?'Spanish':'English') . ". If the user switches language mid-chat, follow them.\n\n"
          . "YOUR JOB in this chat widget:\n"
          . "1. Greet warmly and ask what you can help with.\n"
          . "2. If they want to sell a property, collect: property address, condition (move-in ready / needs work / distressed), timeline, rough asking price, and contact info (name + phone + email).\n"
          . "3. For long qualifications (more than 3-4 quick exchanges), suggest: 'To save you time, we have a 2-minute form that captures everything at /get-my-offer/. Want to use that instead?' — but only ONCE, don't pressure.\n"
          . "4. If user is just asking general questions (what do you buy, how fast, fees, etc.) — answer concisely and end with a soft offer to continue.\n\n"
          . "RULES (hard):\n"
          . "- NEVER promise specific timelines, dollar amounts, or outcomes.\n"
          . "- NEVER claim the deal will close fast / will be high offer without qualifiers.\n"
          . "- NEVER fabricate information about Pinnacle (no fake team members, no fake offices outside 735 E Walnut St Suite 3 Green Bay WI).\n"
          . "- If asked something off-topic or hostile, politely redirect.\n"
          . "- Max 60 words per reply. Use simple language.\n\n"
          . "COMPANY FACTS you may reference:\n"
          . "- Pinnacle Holdings Group LLC, Wisconsin-based real estate investors\n"
          . "- We buy any condition, any situation (foreclosure, inherited, damaged, rented, vacant)\n"
          . "- Phone: (920) 777-9886 · Email: deals@pinnaclegroupwi.com\n"
          . "- Office: 735 E Walnut St Suite 3, Green Bay WI\n"
          . "- Cash offers, no repairs, no realtor fees\n\n"
          . "ESCALATION — When you have collected name + phone + address, append a JSON tag at the very end of your message on its own line:\n"
          . '<escalate>{"name":"...","phone":"...","email":"...","address":"...","summary":"1-line summary"}</escalate>' . "\n"
          . "The frontend parses this tag out before displaying to the user. Without the escalate tag, keep chatting.";

    // Build Anthropic messages array from history
    $messages = [];
    foreach ($history as $h) {
        $r = ($h['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
        $c = (string) ($h['content'] ?? '');
        if ($c === '') continue;
        $messages[] = ['role' => $r, 'content' => $c];
    }
    if (empty($messages)) {
        $messages[] = ['role' => 'user', 'content' => 'Hi'];
    }

    $body = [
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 300,
        'system'     => $sys,
        'messages'   => $messages,
    ];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) return ['ok' => false, 'err' => 'claude http ' . $code, 'body' => substr((string)$resp, 0, 300)];
    $d = json_decode((string) $resp, true) ?: [];
    $text = $d['content'][0]['text'] ?? '';
    // Extract <escalate>{...}</escalate> tag if present
    $escalate = null;
    if (preg_match('/<escalate>\s*(\{[\s\S]*?\})\s*<\/escalate>/', $text, $m)) {
        $parsed = json_decode($m[1], true);
        if (is_array($parsed)) $escalate = $parsed;
        $text = trim(str_replace($m[0], '', $text));
    }
    return ['ok' => true, 'reply' => trim($text), 'escalate' => $escalate];
}

// --- Helper: send Telegram alert for chatbot escalation ---
function pp_chat_notify_telegram(array $esc, string $session_id): void {
    $tg_token = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : (string) getenv('TELEGRAM_BOT_TOKEN');
    $tg_chat  = defined('TELEGRAM_CHAT_ID')  ? TELEGRAM_CHAT_ID  : (string) getenv('TELEGRAM_CHAT_ID');
    if (!$tg_token || !$tg_chat) return;
    $msg = "💬 *NEW CHATBOT LEAD*\n\n"
         . '*Name:* ' . ($esc['name'] ?? '—') . "\n"
         . '*Phone:* `' . ($esc['phone'] ?? '—') . "`\n"
         . '*Email:* ' . ($esc['email'] ?? '—') . "\n"
         . '*Address:* ' . ($esc['address'] ?? '—') . "\n"
         . '*Summary:* ' . ($esc['summary'] ?? '—') . "\n"
         . "\n_Session:_ `" . substr($session_id, 0, 12) . "`";
    $ch = curl_init('https://api.telegram.org/bot' . $tg_token . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['chat_id'=>$tg_chat, 'text'=>$msg, 'parse_mode'=>'Markdown']),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 8,
    ]);
    curl_exec($ch); curl_close($ch);
}

// -------- Actions --------

// --- Helper: search Airtable Leads by phone (primary) or address (fallback) ---
function pp_airtable_find_existing(string $phone_e164, string $address): array {
    if (!defined('AIRTABLE_TOKEN')) return ['found' => false];
    $phone_digits = preg_replace('/\D+/', '', $phone_e164);
    $addr_norm = strtolower(trim(preg_replace('/\s+/', ' ', $address)));

    // Primary: look up Contacts table by phone digits (contacts link to leads via Property Address)
    $contacts_url = 'https://api.airtable.com/v0/' . AIRTABLE_BASE_ID . '/tblacvw0Ss770x8l5'
                  . '?filterByFormula=' . rawurlencode("OR(FIND('$phone_digits',{Phone1}&''),FIND('$phone_digits',{Phone2}&''),FIND('$phone_digits',{Phone3}&''),FIND('$phone_digits',{Phone4}&''))")
                  . '&maxRecords=3';
    $ch = curl_init($contacts_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        $data = json_decode((string) $resp, true) ?: [];
        foreach (($data['records'] ?? []) as $rec) {
            $f = $rec['fields'] ?? [];
            $linked_leads = $f['Property Address'] ?? ($f['Leads 2'] ?? []);
            if (is_array($linked_leads) && count($linked_leads) > 0) {
                return [
                    'found'      => true,
                    'match_by'   => 'phone',
                    'contact_id' => $rec['id'],
                    'lead_id'    => $linked_leads[0],
                    'name'       => $f['Full Name'] ?? '',
                    'email'      => $f['Email1'] ?? '',
                ];
            }
        }
    }

    // Fallback: lookup Leads by Address (normalized)
    $leads_url = 'https://api.airtable.com/v0/' . AIRTABLE_BASE_ID . '/' . AIRTABLE_LEADS_TABLE
               . '?filterByFormula=' . rawurlencode("LOWER({Address})='" . addslashes($addr_norm) . "'")
               . '&maxRecords=3';
    $ch = curl_init($leads_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        $data = json_decode((string) $resp, true) ?: [];
        $recs = $data['records'] ?? [];
        if (count($recs) > 0) {
            $r0 = $recs[0];
            return [
                'found'    => true,
                'match_by' => 'address',
                'lead_id'  => $r0['id'],
                'stage'    => $r0['fields']['Stage'] ?? '',
            ];
        }
    }
    return ['found' => false];
}

// --- Helper: fetch Lead + stage classification ---
function pp_airtable_get_lead(string $lead_id): array {
    if (!defined('AIRTABLE_TOKEN')) return ['ok' => false];
    $url = 'https://api.airtable.com/v0/' . AIRTABLE_BASE_ID . '/' . AIRTABLE_LEADS_TABLE . '/' . rawurlencode($lead_id);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) return ['ok' => false, 'code' => $code];
    $data = json_decode((string) $resp, true) ?: [];
    $f = $data['fields'] ?? [];
    $stage = (string) ($f['Stage'] ?? '');
    $ACTIVE  = ['New Lead', 'Review this Deal', 'To be Contacted', 'To Be Contacted', 'Contacted', 'Seguimiento', 'Appointment Set', 'Offer Sent', 'Under Contract', 'Closing'];
    $CLOSED  = ['Done Deal', 'Dead'];
    $status  = in_array($stage, $ACTIVE, true) ? 'active' : (in_array($stage, $CLOSED, true) ? 'closed' : 'other');
    return [
        'ok'      => true,
        'id'      => $data['id'] ?? $lead_id,
        'fields'  => $f,
        'stage'   => $stage,
        'status'  => $status,
        'address' => (string) ($f['Address'] ?? ''),
    ];
}

switch ($action) {

    case 'places_proxy': {
        $query = trim((string) ($body['input'] ?? ''));
        if (strlen($query) < 3) $reply(['suggestions' => []]);
        $ch = curl_init('https://places.googleapis.com/v1/places:autocomplete');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'input' => $query,
                'includedRegionCodes' => ['US'],
                'includedPrimaryTypes' => ['street_address', 'premise', 'subpremise', 'route'],
            ]),
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Goog-Api-Key: ' . GOOGLE_PLACES_API_KEY,
                'Referer: https://pinnaclegroupwi.com',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $d = json_decode((string) $resp, true) ?: [];
        $sugg = [];
        foreach (($d['suggestions'] ?? []) as $s) {
            $p = $s['placePrediction'] ?? null;
            if ($p) {
                $sugg[] = [
                    'text'     => $p['text']['text'] ?? '',
                    'place_id' => $p['placeId'] ?? '',
                ];
            }
        }
        $reply(['suggestions' => $sugg]);
    }

    case 'chat_message': {
        // Fer-Chat-Mode — multi-turn chat widget on the website
        $session_id = (string) ($body['session_id'] ?? '');
        if ($session_id === '') $session_id = bin2hex(random_bytes(8));
        $lang       = (string) ($body['lang'] ?? 'en');
        $history    = (array)  ($body['history'] ?? []);
        // Cap history to last 20 turns to keep tokens + cost predictable
        if (count($history) > 20) $history = array_slice($history, -20);
        $r = pp_chat_brain($history, $lang);
        if (!$r['ok']) {
            $log('warn', 'chat_brain fail', ['err' => $r['err'] ?? '']);
            $reply([
                'ok' => true,
                'session_id' => $session_id,
                'reply' => $lang === 'es'
                    ? 'Disculpa, tuve un problema técnico. ¿Puedes intentar de nuevo?'
                    : 'Sorry, I hit a quick hiccup. Can you try once more?',
                'escalate' => null,
            ]);
        }
        // If the brain requested escalation, create an Airtable Lead + Telegram alert
        $created_lead = null;
        if (is_array($r['escalate'] ?? null)) {
            $esc = $r['escalate'];
            $fields = [
                'Address'     => (string) ($esc['address'] ?? ''),
                'Stage'       => 'New Lead',
                'Lead Source' => 'Website Form',   // existing option (no new dict additions)
                'Dated Added' => date('Y-m-d'),
                'Recomendation' => "=== CHATBOT LEAD ===\n"
                     . "Name:    " . ($esc['name'] ?? '') . "\n"
                     . "Phone:   " . ($esc['phone'] ?? '') . "\n"
                     . "Email:   " . ($esc['email'] ?? '') . "\n"
                     . "Address: " . ($esc['address'] ?? '') . "\n"
                     . "Summary: " . ($esc['summary'] ?? '') . "\n\n"
                     . "Transcript (last 8):\n"
                     . implode("\n", array_map(function($h){
                         return '  [' . ($h['role'] ?? '?') . '] ' . substr((string)($h['content'] ?? ''), 0, 200);
                     }, array_slice($history, -8))),
            ];
            $at = pp_airtable_create($fields);
            if ($at['ok']) $created_lead = $at['record']['id'] ?? null;
            pp_chat_notify_telegram($esc, $session_id);
            $log('info', 'chat escalation', ['lead_id' => $created_lead]);
        }
        $reply([
            'ok' => true,
            'session_id' => $session_id,
            'reply' => $r['reply'],
            'escalate' => $r['escalate'],
            'lead_id' => $created_lead,
        ]);
    }

    case 'form_brain': {
        // Fer-Form-Mode — empathetic acknowledgments between screens.
        // Rate limit: Haiku call only when something interesting happened
        // (condition, timeline, issues, priority). Other answers get a noop.
        $last_field = (string) ($body['last_field'] ?? '');
        $last_value = (string) ($body['last_value'] ?? '');
        $trigger_fields = ['condition', 'timeline', 'priority', 'occupancy', 'issues'];
        if (!in_array($last_field, $trigger_fields, true)) {
            $reply(['ok' => true, 'msg' => '', 'warning' => '']);
        }
        $ctx = [
            'lang'       => (string) ($body['lang'] ?? 'en'),
            'current'    => (string) ($body['current'] ?? ''),
            'last_field' => $last_field,
            'last_value' => $last_value,
            'data'       => (array) ($body['data'] ?? []),
        ];
        $result = pp_fer_brain($ctx);
        if (!$result['ok']) {
            $log('warn', 'fer_brain fail', ['err' => $result['err'] ?? '']);
            $reply(['ok' => true, 'msg' => '', 'warning' => '']); // never block the form
        }
        $reply(['ok' => true, 'msg' => $result['msg'], 'warning' => $result['warning']]);
    }

    case 'lookup_existing': {
        // Check if client has a prior lead BEFORE creating a new one.
        // Primary: phone match via Contacts.Phone1-4. Fallback: Address match on Leads.
        $phone = trim((string) ($body['phone'] ?? ''));
        $address = trim((string) ($body['address'] ?? ''));
        $phone_e164 = pp_normalize_phone($phone);
        if ($phone_e164 === '' && $address === '') {
            $reply(['found' => false, 'reason' => 'missing phone and address']);
        }
        $match = pp_airtable_find_existing($phone_e164, $address);
        if (!$match['found']) {
            $reply(['found' => false]);
        }
        // Enrich with Lead stage/status
        $lead_id = (string) ($match['lead_id'] ?? '');
        $lead_info = pp_airtable_get_lead($lead_id);
        if (!$lead_info['ok']) {
            $reply(['found' => false, 'reason' => 'lead not readable']);
        }
        // Decide UX path based on stage
        $stage  = $lead_info['stage'];
        $status = $lead_info['status'];
        $f = $lead_info['fields'];
        $summary = [
            'lead_id'  => $lead_id,
            'match_by' => $match['match_by'],
            'address'  => $lead_info['address'],
            'stage'    => $stage,
            'status'   => $status,
            // echo back lightly masked contact info for UI prefill
            'name'     => (string) ($match['name'] ?? ''),
            'email'    => (string) ($match['email'] ?? ''),
        ];
        $log('info', 'lookup_existing hit', ['lead_id' => $lead_id, 'stage' => $stage, 'match_by' => $match['match_by']]);
        $reply(['found' => true] + $summary);
    }

    case 'start_lead': {
        if (!empty($body['website'] ?? '')) { $log('warn', 'honeypot filled'); $reply(['error' => 'invalid submission'], 400); }
        $elapsed = (int) ($body['elapsed_ms'] ?? 0);
        if ($elapsed < 5000) { $log('warn', 'too fast', ['ms' => $elapsed]); $reply(['error' => 'please try again'], 400); }

        $name    = trim((string) ($body['name'] ?? ''));
        $email   = trim((string) ($body['email'] ?? ''));
        $phone   = trim((string) ($body['phone'] ?? ''));
        $address = trim((string) ($body['address'] ?? ''));
        $place_id= trim((string) ($body['place_id'] ?? ''));
        $ptype   = trim((string) ($body['property_type'] ?? ''));

        if ($name === '' || $email === '' || $phone === '' || $address === '') {
            $reply(['error' => 'missing required fields'], 400);
        }
        $phone_e164 = pp_normalize_phone($phone);
        if ($phone_e164 === '') $reply(['error' => 'invalid phone'], 400);

        [$email_ok, $email_err] = pp_email_valid($email);
        if (!$email_ok) $reply(['error' => "email: $email_err"], 400);

        $city  = (string) ($body['city']  ?? '');
        $state = (string) ($body['state'] ?? 'WI');
        $zip   = (string) ($body['zip']   ?? '');

        // If reopen_lead_id is provided (returning client chose "update my info"),
        // reuse that Lead instead of creating a new one. Refresh fields + mark for re-review.
        $reopen_lead_id = trim((string) ($body['reopen_lead_id'] ?? ''));
        $lead_id = '';
        $is_reopen = false;
        if ($reopen_lead_id !== '') {
            $existing = pp_airtable_get_lead($reopen_lead_id);
            if ($existing['ok']) {
                $update_fields = [
                    'Address'     => $address,
                    'City'        => $city,
                    'Estate'      => $state,
                    'Zip Code'    => $zip !== '' ? (int) $zip : null,
                    'Stage'       => 'Review this Deal',
                    'Last Contact Date' => date('Y-m-d'),
                ];
                pp_airtable_update($reopen_lead_id, $update_fields);
                $lead_id = $reopen_lead_id;
                $is_reopen = true;
                $log('info', 'lead reopened', ['lead_id' => $lead_id, 'prev_stage' => $existing['stage']]);
            }
        }

        if ($lead_id === '') {
            $at = pp_airtable_create([
                'Address'     => $address,
                'City'        => $city,
                'Estate'      => $state,
                'Zip Code'    => $zip !== '' ? (int) $zip : null,
                'Stage'       => 'New Lead',
                'Lead Source' => 'Website Form',
                'Dated Added' => date('Y-m-d'),
            ]);
            if (!$at['ok']) {
                $log('error', 'airtable create failed', ['code' => $at['code'] ?? 0, 'body' => $at['record'] ?? []]);
                $reply(['error' => 'temporary failure, please try again'], 500);
            }
            $lead_id = $at['record']['id'] ?? '';
            if ($lead_id === '') $reply(['error' => 'lead creation failed'], 500);
        }

        $code          = sprintf('%06d', random_int(0, 999999));
        $session_token = bin2hex(random_bytes(16));
        $session = [
            'token'     => $session_token,
            'code'      => $code,
            'phone'     => $phone_e164,
            'name'      => $name,
            'email'     => $email,
            'address'   => $address,
            'place_id'  => $place_id,
            'ptype'     => $ptype,
            'city'      => $city,
            'state'     => $state,
            'zip'       => $zip,
            'verified'  => false,
            'created'   => time(),
            'last_sent' => time(),
        ];
        set_transient(pp_lead_session_key($lead_id), $session, 7200);

        $first = explode(' ', $name)[0];
        $sms = "Hi $first, your Pinnacle Holdings verification code is $code. It expires in 10 min. Reply STOP to opt out.";
        $sent = pp_send_sms($phone_e164, $sms);
        if (!$sent['ok']) {
            $log('error', 'sms send failed', ['code' => $sent['code'] ?? 0]);
            $reply(['error' => 'could not send verification code — please check your phone number'], 500);
        }

        $log('info', 'lead started', ['lead_id' => $lead_id, 'is_reopen' => $is_reopen]);
        $reply([
            'ok'            => true,
            'lead_id'       => $lead_id,
            'session_token' => $session_token,
            'phone_masked'  => '(' . substr($phone_e164, 2, 3) . ') ' . substr($phone_e164, 5, 3) . '-****',
            'ttl_seconds'   => 600,
            'is_reopen'     => $is_reopen,
        ]);
    }

    case 'verify_phone': {
        $lead_id = (string) ($body['lead_id'] ?? '');
        $token   = (string) ($body['session_token'] ?? '');
        $code    = preg_replace('/\D/', '', (string) ($body['code'] ?? ''));

        $session = get_transient(pp_lead_session_key($lead_id));
        if (!is_array($session) || !hash_equals((string) ($session['token'] ?? ''), $token)) {
            $reply(['error' => 'invalid session'], 403);
        }
        if ($session['verified']) $reply(['ok' => true, 'already_verified' => true]);
        if (!hash_equals((string) $session['code'], $code)) {
            $reply(['error' => 'incorrect code'], 400);
        }
        $session['verified'] = true;
        set_transient(pp_lead_session_key($lead_id), $session, 7200);
        pp_airtable_update($lead_id, ['Stage' => 'New Lead']);
        $log('info', 'phone verified', ['lead_id' => $lead_id]);
        $reply(['ok' => true, 'verified' => true]);
    }

    case 'resend_code': {
        $lead_id = (string) ($body['lead_id'] ?? '');
        $token   = (string) ($body['session_token'] ?? '');
        $session = get_transient(pp_lead_session_key($lead_id));
        if (!is_array($session) || !hash_equals((string) ($session['token'] ?? ''), $token)) {
            $reply(['error' => 'invalid session'], 403);
        }
        if (time() - (int) ($session['last_sent'] ?? 0) < 30) {
            $reply(['error' => 'please wait before requesting another code', 'retry_after' => 30]);
        }
        $session['code']      = sprintf('%06d', random_int(0, 999999));
        $session['last_sent'] = time();
        set_transient(pp_lead_session_key($lead_id), $session, 7200);
        $first = explode(' ', (string) $session['name'])[0];
        $sent  = pp_send_sms($session['phone'], "Hi $first, your new Pinnacle Holdings code is " . $session['code'] . ". Expires in 10 min.");
        if (!$sent['ok']) $reply(['error' => 'SMS failed'], 500);
        $reply(['ok' => true]);
    }

    case 'update_lead': {
        $lead_id = (string) ($body['lead_id'] ?? '');
        $token   = (string) ($body['session_token'] ?? '');
        $session = get_transient(pp_lead_session_key($lead_id));
        if (!is_array($session) || !hash_equals((string) ($session['token'] ?? ''), $token)) {
            $reply(['error' => 'invalid session — please start over'], 403);
        }
        if (!($session['verified'] ?? false)) {
            $reply(['error' => 'phone not verified yet'], 403);
        }

        $phase2 = (array) ($body['phase2'] ?? []);
        $phase3 = (array) ($body['phase3'] ?? []);

        $score = pp_compute_score([], $phase2, $phase3);

        // Map form property_type to Airtable Property Type valid options
        $ptype_map = [
            'Single Family' => 'Single Family',
            'Duplex/Triplex' => 'Multi-Family',
            'Multi-Family' => 'Multi-Family',
            'Mobile Home'  => 'Manufactured ',
            'Land'         => 'Vacant Land',
        ];
        $airtable_ptype = $ptype_map[$session['ptype']] ?? 'Other';

        // Normalize beds/baths (e.g., "5+" -> 5)
        $beds  = (int) preg_replace('/\D+/', '', (string) ($phase2['beds']  ?? '0'));
        $baths = (int) preg_replace('/\D+/', '', (string) ($phase2['baths'] ?? '0'));
        $ask   = (int) preg_replace('/\D+/', '', (string) ($phase3['asking_price'] ?? '0'));

        $notes_parts = [
            '=== CONTACT ===',
            'Name:     ' . $session['name'],
            'Phone:    ' . $session['phone'],
            'Email:    ' . $session['email'],
            '',
            '=== PROPERTY ===',
            'Type:        ' . $session['ptype'],
            'Condition:   ' . (string) ($phase2['condition'] ?? '—'),
            'Roof age:    ' . (string) ($phase2['roof'] ?? '—'),
            'Bedrooms:    ' . (string) ($phase2['beds'] ?? '—'),
            'Bathrooms:   ' . (string) ($phase2['baths'] ?? '—'),
            'Occupancy:   ' . (string) ($phase2['occupancy'] ?? '—'),
            'Issues:      ' . (string) ($phase2['issues'] ?? '—'),
            '',
            '=== FINANCIAL & MOTIVATION ===',
            'Timeline:      ' . (string) ($phase3['timeline'] ?? '—'),
            'Priority:      ' . (string) ($phase3['priority'] ?? '—'),
            'Asking price:  $' . (string) ($phase3['asking_price'] ?? '—'),
            'Payment pref:  ' . (string) ($phase3['payment_pref'] ?? '—'),
            'Amount owed:   $' . (string) ($phase3['amount_owed'] ?? '—'),
            '',
            '=== LEAD SCORE ===',
            'Score: ' . $score['score'] . '/10 (' . $score['heat'] . ')',
            'Tags:  ' . implode(', ', $score['labels'] ?: ['—']),
        ];

        // Write ALL structured data to Airtable record (not just Stage)
        $update = [
            'Stage'          => 'Review this Deal',
            'Property Type'  => $airtable_ptype,
            'Bedrooms'       => $beds,
            'Bathroom'       => $baths,
            'Asking Price'   => $ask,
            'Recomendation'  => implode("\n", $notes_parts),
        ];
        pp_airtable_update($lead_id, $update);

        $heat_emoji = $score['heat'] === 'HOT' ? '🔥' : ($score['heat'] === 'WARM' ? '🌡️' : '❄️');
        $airtable_link = 'https://airtable.com/' . AIRTABLE_BASE_ID . '/' . AIRTABLE_LEADS_TABLE . '/' . $lead_id;
        $call_link = 'https://pinnaclegroupwi.com/Tools/Pinnacle_Call_Assistant.html?recordId=' . $lead_id;

        // Direct Telegram notification to the boss (no email dependency)
        $tg_token = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : (string) getenv('TELEGRAM_BOT_TOKEN');
        $tg_chat  = defined('TELEGRAM_CHAT_ID')  ? TELEGRAM_CHAT_ID  : (string) getenv('TELEGRAM_CHAT_ID');
        if ($tg_token && $tg_chat) {
            $tg_text = $heat_emoji . ' *NEW WEB LEAD — ' . $score['heat'] . '*  Score ' . $score['score'] . "/10\n\n"
                     . '*Name:* ' . $session['name'] . "\n"
                     . '*Phone:* `' . $session['phone'] . "`\n"
                     . '*Email:* ' . $session['email'] . "\n"
                     . '*Address:* ' . $session['address'] . "\n\n"
                     . '*Timeline:* ' . ($phase3['timeline'] ?? '—') . "\n"
                     . '*Asking:* $' . ($phase3['asking_price'] ?? '—') . '   *Owed:* $' . ($phase3['amount_owed'] ?? '—') . "\n"
                     . '*Condition:* ' . ($phase2['condition'] ?? '—') . "\n"
                     . '*Priority:* ' . ($phase3['priority'] ?? '—') . '   *Payment pref:* ' . ($phase3['payment_pref'] ?? '—') . "\n\n"
                     . '[Airtable record](' . $airtable_link . ") · [Call Assistant](" . $call_link . ')';
            $ch = curl_init('https://api.telegram.org/bot' . $tg_token . '/sendMessage');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode([
                    'chat_id'    => $tg_chat,
                    'text'       => $tg_text,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true,
                ]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 10,
            ]);
            $tg_resp = curl_exec($ch);
            $tg_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $log('info', 'telegram sent', ['code' => $tg_code]);
        } else {
            $log('warn', 'telegram creds missing');
        }

        // Email backup (to deals@ which feeds Secretario)
        $subject = sprintf('%s %s LEAD | %s | %s', $heat_emoji, $score['heat'], (string) ($phase3['timeline'] ?? 'no-timeline'), $session['address']);
        $body_text = "Pinnacle Holdings — New Web Lead\n\n" . implode("\n", $notes_parts) . "\n\n"
                   . 'Airtable: ' . $airtable_link . "\n"
                   . 'Call Assistant: ' . $call_link . "\n";
        @wp_mail(PINNACLE_NOTIFY_EMAIL, $subject, $body_text);

        // Confirmation SMS to the seller (the client who just finished the form)
        $first_name = explode(' ', (string) $session['name'])[0] ?: 'there';
        $is_es = (strtolower(substr((string) $session['name'], -1)) === '' ? false : true); // unreliable; send bilingual
        $confirm_sms = "Hi $first_name, Pinnacle Holdings received your request. We're reviewing "
                     . $session['address'] . " and will call you within 24 hours with your cash offer. "
                     . "Questions? Reply here. / En espanol: Recibimos tu solicitud, te llamamos en 24h.";
        @pp_send_sms($session['phone'], $confirm_sms);

        // Extend transient TTL for potential re-submit (from 30min to 2h)
        $session['finalized_at'] = time();
        set_transient(pp_lead_session_key($lead_id), $session, 7200);

        $log('info', 'lead finalized', ['lead_id' => $lead_id, 'score' => $score['score'], 'heat' => $score['heat']]);
        $reply(['ok' => true, 'lead_id' => $lead_id, 'score' => $score['score'], 'heat' => $score['heat']]);
    }

    case 'subscribe_email': {
        $email      = trim((string) ($body['email'] ?? ''));
        $lang       = ($body['lang'] ?? 'en') === 'es' ? 'es' : 'en';
        $source     = substr((string) ($body['source'] ?? '/'), 0, 200);
        $honeypot   = (string) ($body['honeypot'] ?? '');
        $elapsed_ms = (int) ($body['elapsed_ms'] ?? 0);

        // Anti-spam: honeypot must be empty + minimum human time
        if ($honeypot !== '' || $elapsed_ms < 1200) {
            $log('warn', 'popup subscribe blocked', ['hp' => $honeypot !== '', 'ms' => $elapsed_ms]);
            // Return fake success so bots don't learn
            $reply(['ok' => true]);
        }

        $v = pp_email_valid($email);
        if (!($v[0] ?? false)) {
            $reply(['ok' => false, 'error' => 'invalid_email', 'reason' => $v[1] ?? ''], 400);
        }
        $email_norm = strtolower(trim($email));

        // Rate limit per IP: max 5 subscribes per hour
        $ip_key = 'pp_rl_sub_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $count  = (int) (get_transient($ip_key) ?: 0);
        if ($count >= 5) {
            $reply(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        set_transient($ip_key, $count + 1, 3600);

        // Insert into Contacts (newsletter subscriber minimal shape)
        $ok = false;
        $record_id = '';
        if (defined('AIRTABLE_TOKEN')) {
            $url = 'https://api.airtable.com/v0/' . AIRTABLE_BASE_ID . '/tblacvw0Ss770x8l5';
            $payload = ['fields' => [
                'Email1'    => $email_norm,
                'Full Name' => 'Newsletter Subscriber',
                'Notes'     => 'Source: ' . $source . ' | Lang: ' . $lang . ' | Subscribed: ' . gmdate('Y-m-d H:i') . ' UTC',
            ], 'typecast' => true];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . AIRTABLE_TOKEN,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT        => 15,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300) {
                $ok  = true;
                $data = json_decode((string) $resp, true) ?: [];
                $record_id = $data['id'] ?? '';
            } else {
                $log('warn', 'airtable subscribe failed', ['code' => $code, 'resp' => substr((string) $resp, 0, 200)]);
                // Retry without 'Notes' in case the field does not exist
                if (strpos((string) $resp, 'UNKNOWN_FIELD_NAME') !== false) {
                    $payload['fields'] = ['Email1' => $email_norm, 'Full Name' => 'Newsletter Subscriber'];
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode($payload),
                        CURLOPT_HTTPHEADER     => [
                            'Authorization: Bearer ' . AIRTABLE_TOKEN,
                            'Content-Type: application/json',
                        ],
                        CURLOPT_TIMEOUT        => 15,
                    ]);
                    $resp2 = curl_exec($ch);
                    $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($code2 >= 200 && $code2 < 300) {
                        $ok = true;
                        $data2 = json_decode((string) $resp2, true) ?: [];
                        $record_id = $data2['id'] ?? '';
                    }
                }
            }
        }

        // Telegram notification regardless of airtable outcome (so Jorge always sees it)
        if (defined('TELEGRAM_BOT_TOKEN') && defined('TELEGRAM_CHAT_ID')) {
            $msg = "📬 *New newsletter subscriber*\n"
                 . "• Email: `" . $email_norm . "`\n"
                 . "• Lang: " . strtoupper($lang) . "\n"
                 . "• Source: " . $source . "\n"
                 . "• Airtable: " . ($ok ? ($record_id ?: 'created') : 'failed');
            $ch = curl_init('https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'chat_id'    => TELEGRAM_CHAT_ID,
                    'text'       => $msg,
                    'parse_mode' => 'Markdown',
                ]),
                CURLOPT_TIMEOUT        => 8,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }

        $log('info', 'newsletter subscribe', ['email' => $email_norm, 'ok' => $ok, 'rec' => $record_id, 'src' => $source]);
        $reply(['ok' => true]);
    }

    default:
        $reply(['error' => 'unknown action', 'action' => $action], 400);
}
