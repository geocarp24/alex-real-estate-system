<?php
/**
 * El Remitente — Hostinger email endpoint.
 * Public URL: https://pinnaclegroupwi.com/agents/pinnacle_mail.php
 *
 * Actions:
 *   ?action=send_campaign      — privileged, requires X-Alex-Secret header.
 *                                 Pulls Email_Campaigns where status=Scheduled AND scheduled_at<=now,
 *                                 resolves audience, sends via mail(), logs Email_Events.
 *   ?action=track_open&e=TID   — public 1x1 GIF pixel. Logs 'opened' event.
 *   ?action=track_click&e=TID&u=URL — public redirect. Logs 'clicked' event + 302 to URL.
 *   ?action=unsubscribe&t=TOK  — public. Validates signed token, marks subscriber Unsubscribed.
 *
 * Security:
 *   - send_campaign requires ALEX_SECRET (env via Tools/config.php).
 *   - track_* are public (tracking_id is opaque, no secret data leaks).
 *   - unsubscribe validates HMAC-SHA256(email, ALEX_SECRET) to prevent malicious unsubs.
 *   - All Airtable writes use AIRTABLE_TOKEN from config.
 *
 * Deliverability:
 *   - From: deals@pinnaclegroupwi.com (domain authenticated, SPF/DKIM via Hostinger).
 *   - List-Unsubscribe + List-Unsubscribe-Post headers for Gmail/Yahoo.
 *   - Multipart (text + HTML).
 *
 * This file is deployed to /home/u433637438/.../public_html/agents/pinnacle_mail.php
 * via the deploy-hostinger.yml workflow (same as other /agents/ files).
 */

require_once __DIR__ . '/alex_config.php'; // GITHUB_TOKEN + ALEX_SECRET
$tools_cfg = __DIR__ . '/../Tools/config.php';
if (file_exists($tools_cfg)) { require_once $tools_cfg; }

// ---- Required constants ----
if (!defined('AIRTABLE_TOKEN'))     { http_response_code(500); exit('config: AIRTABLE_TOKEN missing'); }
if (!defined('ALEX_SECRET'))        { http_response_code(500); exit('config: ALEX_SECRET missing'); }
$AIRTABLE_BASE      = 'appfQbDA750Oihy9J';
$TBL_SUBSCRIBERS    = 'tblEiB0fBeGxxq7if';
$TBL_CAMPAIGNS      = 'tblBJAtH3k1IVhqqc';
$TBL_EVENTS         = 'tblTNKwwXZTBXymOD';
$FROM_EMAIL         = 'deals@pinnaclegroupwi.com';
$FROM_NAME          = 'Pinnacle Holdings';
$PUBLIC_BASE        = 'https://pinnaclegroupwi.com/agents/pinnacle_mail.php';

// ---- HTTP helpers ----
function pm_airtable_get($table, $params = '') {
    global $AIRTABLE_BASE;
    $url = "https://api.airtable.com/v0/$AIRTABLE_BASE/$table" . ($params ? '?' . $params : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $r = curl_exec($ch); curl_close($ch);
    return json_decode((string) $r, true) ?: [];
}
function pm_airtable_post($table, $fields) {
    global $AIRTABLE_BASE;
    $url = "https://api.airtable.com/v0/$AIRTABLE_BASE/$table";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS     => json_encode(['fields' => $fields, 'typecast' => true]),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $r = curl_exec($ch); curl_close($ch);
    return json_decode((string) $r, true) ?: [];
}
function pm_airtable_patch($table, $record_id, $fields) {
    global $AIRTABLE_BASE;
    $url = "https://api.airtable.com/v0/$AIRTABLE_BASE/$table/$record_id";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode(['fields' => $fields, 'typecast' => true]),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $r = curl_exec($ch); curl_close($ch);
    return json_decode((string) $r, true) ?: [];
}

function pm_log_event($campaign_id, $email, $event_type, $tracking_id = '', $extra = []) {
    global $TBL_EVENTS;
    pm_airtable_post($TBL_EVENTS, [
        'event_id'         => bin2hex(random_bytes(8)),
        'tenant_id'        => 'pinnacle',
        'campaign_id'      => $campaign_id,
        'subscriber_email' => $email,
        'event_type'       => $event_type,
        'event_at'         => gmdate('c'),
        'tracking_id'      => $tracking_id,
        'clicked_url'      => $extra['clicked_url'] ?? '',
        'user_agent'       => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
        'ip_hash'          => hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '')),
        'metadata_json'    => !empty($extra) ? json_encode($extra) : '',
    ]);
}

function pm_unsub_token($email) {
    return hash_hmac('sha256', strtolower(trim($email)), ALEX_SECRET);
}
function pm_unsub_verify($email, $token) {
    return hash_equals(pm_unsub_token($email), $token);
}

function pm_tracking_id() {
    return bin2hex(random_bytes(6)); // 12 hex chars
}

function pm_render_html_with_tracking($html, $tracking_id, $campaign_id) {
    global $PUBLIC_BASE;
    // Rewrite <a href=X> to click-tracker that redirects to X
    $html = preg_replace_callback('/<a\s+([^>]*?)href=([\'"])([^\'"]+)\2([^>]*)>/i', function($m) use ($tracking_id, $campaign_id, $PUBLIC_BASE) {
        $orig = $m[3];
        // Don't rewrite mailto: or unsubscribe or tracking URLs themselves
        if (strpos($orig, 'mailto:') === 0 || strpos($orig, $PUBLIC_BASE) === 0) {
            return $m[0];
        }
        $enc = urlencode($orig);
        $new = "$PUBLIC_BASE?action=track_click&c=$campaign_id&e=$tracking_id&u=$enc";
        return "<a {$m[1]}href=\"$new\"{$m[4]}>";
    }, $html);

    // Append tracking pixel before </body>
    $pixel = "<img src=\"$PUBLIC_BASE?action=track_open&c=$campaign_id&e=$tracking_id\" width=\"1\" height=\"1\" alt=\"\" style=\"display:block;width:1px;height:1px;\" />";
    if (stripos($html, '</body>') !== false) {
        $html = str_ireplace('</body>', $pixel . '</body>', $html);
    } else {
        $html .= $pixel;
    }
    return $html;
}

function pm_replace_placeholders($str, $subscriber) {
    $fields = $subscriber['fields'] ?? [];
    $vars = [
        '{{email}}'     => $fields['email'] ?? '',
        '{{lang}}'      => $fields['lang']  ?? 'en',
        '{{tags}}'      => $fields['tags']  ?? '',
        '{{unsub_url}}' => '', // filled by caller
    ];
    return strtr($str, $vars);
}

function pm_send_mail_multipart($to, $subject, $html, $text_body, $unsub_url) {
    global $FROM_EMAIL, $FROM_NAME;
    $boundary = 'pm_' . bin2hex(random_bytes(8));
    $headers  = "From: $FROM_NAME <$FROM_EMAIL>\r\n";
    $headers .= "Reply-To: $FROM_EMAIL\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $headers .= "List-Unsubscribe: <$unsub_url>\r\n";
    $headers .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
    $headers .= "X-Mailer: Pinnacle-ALEX-Remitente\r\n";

    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $text_body . "\r\n\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $html . "\r\n\r\n";
    $body .= "--$boundary--";

    return @mail($to, $subject, $body, $headers, "-f$FROM_EMAIL");
}

// ============================================================
// ACTION ROUTER
// ============================================================

$action = $_GET['action'] ?? '';

// ---- TRACK_OPEN: GET ?action=track_open&c=CAMPAIGN_ID&e=TRACKING_ID (public) ----
if ($action === 'track_open') {
    $cid = $_GET['c'] ?? '';
    $tid = $_GET['e'] ?? '';
    // Find subscriber by tracking_id
    $events = pm_airtable_get($TBL_EVENTS,
        'filterByFormula=' . urlencode("AND({tracking_id}='$tid',{event_type}='sent')") . '&maxRecords=1');
    $email = $events['records'][0]['fields']['subscriber_email'] ?? '';
    if ($email && $tid) {
        pm_log_event($cid, $email, 'opened', $tid);
        // increment campaign open_count
        $camp = pm_airtable_get($TBL_CAMPAIGNS,
            'filterByFormula=' . urlencode("{campaign_id}='$cid'") . '&maxRecords=1');
        if (!empty($camp['records'])) {
            $rec = $camp['records'][0];
            $cur = (int) ($rec['fields']['open_count'] ?? 0);
            pm_airtable_patch($TBL_CAMPAIGNS, $rec['id'], ['open_count' => $cur + 1]);
        }
    }
    // Serve 1x1 transparent GIF
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, max-age=0');
    echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
    exit;
}

// ---- TRACK_CLICK: GET ?action=track_click&c=CAMPAIGN_ID&e=TRACKING_ID&u=URL (public) ----
if ($action === 'track_click') {
    $cid = $_GET['c'] ?? '';
    $tid = $_GET['e'] ?? '';
    $url = $_GET['u'] ?? '/';
    if (!filter_var($url, FILTER_VALIDATE_URL)) { $url = 'https://pinnaclegroupwi.com/'; }
    $events = pm_airtable_get($TBL_EVENTS,
        'filterByFormula=' . urlencode("AND({tracking_id}='$tid',{event_type}='sent')") . '&maxRecords=1');
    $email = $events['records'][0]['fields']['subscriber_email'] ?? '';
    if ($email && $tid) {
        pm_log_event($cid, $email, 'clicked', $tid, ['clicked_url' => $url]);
        $camp = pm_airtable_get($TBL_CAMPAIGNS,
            'filterByFormula=' . urlencode("{campaign_id}='$cid'") . '&maxRecords=1');
        if (!empty($camp['records'])) {
            $rec = $camp['records'][0];
            $cur = (int) ($rec['fields']['click_count'] ?? 0);
            pm_airtable_patch($TBL_CAMPAIGNS, $rec['id'], ['click_count' => $cur + 1]);
        }
    }
    header('Location: ' . $url, true, 302);
    exit;
}

// ---- UNSUBSCRIBE: GET/POST ?action=unsubscribe&email=E&t=TOKEN (public) ----
if ($action === 'unsubscribe') {
    $email = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));
    $token = $_GET['t'] ?? $_POST['t'] ?? '';
    header('Content-Type: text/html; charset=utf-8');
    if (!$email || !$token || !pm_unsub_verify($email, $token)) {
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:30px;">';
        echo '<h2>Invalid unsubscribe link</h2><p>Contact deals@pinnaclegroupwi.com for help.</p></body></html>';
        exit;
    }
    // Find + update subscriber
    $subs = pm_airtable_get($TBL_SUBSCRIBERS,
        'filterByFormula=' . urlencode("{email}='$email'") . '&maxRecords=1');
    if (!empty($subs['records'])) {
        $rec = $subs['records'][0];
        pm_airtable_patch($TBL_SUBSCRIBERS, $rec['id'], [
            'status' => 'Unsubscribed',
            'unsubscribed_at' => gmdate('c'),
        ]);
        pm_log_event('', $email, 'unsubscribed');
    }
    echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Unsubscribed</title>';
    echo '<style>body{font-family:-apple-system,sans-serif;padding:40px 20px;max-width:480px;margin:0 auto;background:#F5F0E8;color:#0D3B2E;}';
    echo '.card{background:#fff;border-radius:14px;padding:28px;border:2px solid #E5DFD4;}</style></head><body>';
    echo '<div class="card"><h2>You\'re unsubscribed</h2>';
    echo '<p>We won\'t send you any more emails. If you change your mind, just reply to any previous email and we\'ll re-add you.</p>';
    echo '<p style="color:#6B6455;font-size:13px;">— Pinnacle Holdings, Wisconsin</p></div></body></html>';
    exit;
}

// ---- SEND_CAMPAIGN: POST (privileged) ----
if ($action === 'send_campaign') {
    $secret = $_SERVER['HTTP_X_ALEX_SECRET'] ?? '';
    if (!hash_equals(ALEX_SECRET, $secret)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'unauthorized']);
        exit;
    }

    // Pull campaigns ready to send
    $q = 'filterByFormula=' . urlencode("AND({status}='Scheduled',IS_BEFORE({scheduled_at},NOW()))") . '&maxRecords=1';
    $camps = pm_airtable_get($TBL_CAMPAIGNS, $q);
    $records = $camps['records'] ?? [];
    if (empty($records)) { echo json_encode(['ok' => true, 'sent' => 0, 'note' => 'no campaigns ready']); exit; }

    $campaign = $records[0];
    $cfields  = $campaign['fields'];
    $cid      = $cfields['campaign_id'] ?? '';

    // Mark Sending
    pm_airtable_patch($TBL_CAMPAIGNS, $campaign['id'], ['status' => 'Sending']);

    // Resolve audience
    $audience_filter = $cfields['audience_filter'] ?? '';
    if (!$audience_filter) { $audience_filter = "{status}='Active'"; }
    $subs = pm_airtable_get($TBL_SUBSCRIBERS, 'filterByFormula=' . urlencode($audience_filter) . '&maxRecords=500');
    $subscribers = $subs['records'] ?? [];

    $sent_ok = 0; $sent_fail = 0;
    $subject_tpl = $cfields['subject'] ?? '(no subject)';
    $html_tpl    = $cfields['body_html'] ?? '';
    $text_tpl    = $cfields['body_text'] ?? '';

    foreach ($subscribers as $sub) {
        $sf  = $sub['fields'];
        $to  = $sf['email'] ?? '';
        if (!$to) { continue; }
        $tid = pm_tracking_id();
        $unsub_token = pm_unsub_token($to);
        $unsub_url = "https://pinnaclegroupwi.com/agents/pinnacle_mail.php?action=unsubscribe&email=" . urlencode($to) . "&t=$unsub_token";

        $ctx = ['{{email}}' => $to, '{{lang}}' => $sf['lang'] ?? 'en', '{{unsub_url}}' => $unsub_url];
        $subject = strtr($subject_tpl, $ctx);
        $html    = strtr($html_tpl,    $ctx);
        $text    = strtr($text_tpl,    $ctx) . "\n\n--\nUnsubscribe: $unsub_url\n";
        $html    = pm_render_html_with_tracking($html, $tid, $cid);

        // Log 'sent' FIRST so that open/click handlers can look up subscriber_email by tracking_id
        pm_log_event($cid, $to, 'sent', $tid);

        $ok = pm_send_mail_multipart($to, $subject, $html, $text, $unsub_url);
        if ($ok) {
            $sent_ok++;
            pm_airtable_patch($TBL_SUBSCRIBERS, $sub['id'], ['last_email_sent_at' => gmdate('c')]);
        } else {
            $sent_fail++;
            pm_log_event($cid, $to, 'failed', $tid);
        }
    }

    // Mark Sent
    pm_airtable_patch($TBL_CAMPAIGNS, $campaign['id'], [
        'status'      => 'Sent',
        'sent_at'     => gmdate('c'),
        'sent_count'  => $sent_ok,
        'bounce_count'=> $sent_fail,
    ]);

    echo json_encode([
        'ok'       => true,
        'campaign' => $cfields['name'] ?? '',
        'sent'     => $sent_ok,
        'failed'   => $sent_fail,
        'audience' => count($subscribers),
    ]);
    exit;
}

// ---- DEFAULT: info page ----
http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unknown or missing action', 'allowed' => ['send_campaign','track_open','track_click','unsubscribe']]);
