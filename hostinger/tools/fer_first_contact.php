<?php
// ============================================================
// FER — First Contact SMS Cron
// Replaces Make scenario "First Contact SMS v2" (4723767)
//
// Cron: every 15 minutes via Hostinger
// URL: https://pinnaclegroupwi.com/Tools/fer_first_contact.php
//
// Logic:
//   Step 0 → SMS to Phone1 → Step 1
//   Step 1 (24h later) → SMS to Phone2 → Step 2
//   Step 2 (24h later) → SMS to Phone3 → Step 3
//   Step 3 (24h later) → SMS to Phone4 → Step 4
//   Step 4 (24h later) → Stage "Seguimiento", Step 0
//
//   DNC contacts: only Phone1 (empathetic msg), then Seguimiento
//   Time window: 9am-7pm CST, Mon-Sat only
//   Max 6 contacts per execution, 15s pacing between sends
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/fer_logger.php';
require_once __DIR__ . '/lib/fer_quo.php';

header('Content-Type: application/json');

define('FC_BASE', 'appfQbDA750Oihy9J');
define('FC_CONTACTS', 'tblacvw0Ss770x8l5');
define('FC_LEADS', 'tblxZz2EWIglOLnEd');
define('FC_MAX_PER_RUN', 6);
define('FC_HOURS_BETWEEN', 24);
define('FC_SMS_DELAY_SECONDS', 15);  // Human-like pacing: avoid carrier rate-limit / spam filters

// Give ourselves enough headroom for throttled sending.
@set_time_limit(300);  // 5 min wall clock (6 msgs × 15s = 90s + overhead)

// Propagate a Contact.Stage change to every linked Lead record.
// Keeps Leads table in sync with the Contacts pipeline.
function fc_sync_lead_stage($contactFields, $newStage) {
    $linked = $contactFields['Property Address'] ?? [];
    if (!is_array($linked) || empty($linked)) return;
    foreach ($linked as $leadId) {
        if (!is_string($leadId) || strlen($leadId) < 10) continue;
        $url = 'https://api.airtable.com/v0/' . FC_BASE . '/' . FC_LEADS . '/' . rawurlencode($leadId);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PATCH',
            CURLOPT_POSTFIELDS     => json_encode(['fields' => ['Stage' => $newStage], 'typecast' => true]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . AIRTABLE_TOKEN,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            fer_log_info('fc_lead_synced', ['lead_id' => $leadId, 'new_stage' => $newStage]);
        } else {
            fer_log_error('fc_lead_sync_failed', ['lead_id' => $leadId, 'code' => $code, 'resp' => substr((string)$resp, 0, 300)]);
        }
    }
}

// ── Time window check: 9am-7pm CST, Mon-Sat ────────────────────
$now = new DateTime('now', new DateTimeZone('America/Chicago'));
$hour = (int) $now->format('G');
$dow  = (int) $now->format('N'); // 1=Mon, 7=Sun

if ($hour < 9 || $hour >= 19 || $dow === 7) {
    echo json_encode(['ok' => true, 'skipped' => 'outside_hours', 'hour' => $hour, 'dow' => $dow]);
    exit;
}

// ── Airtable helpers ────────────────────────────────────────────
function fc_at_request($method, $url, $body = null) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $code >= 200 && $code < 300, 'data' => json_decode($resp, true), 'code' => $code];
}

function fc_at_url($path = '') {
    return 'https://api.airtable.com/v0/' . FC_BASE . '/' . FC_CONTACTS . $path;
}

function fc_phone_e164($raw) {
    if (!$raw) return '';
    $digits = preg_replace('/[^0-9]/', '', strval(intval(floatval($raw))));
    if (strlen($digits) === 10) return '+1' . $digits;
    if (strlen($digits) === 11 && $digits[0] === '1') return '+' . $digits;
    return '';
}

// ── SMS Templates ───────────────────────────────────────────────
function fc_get_message($step, $name, $address, $city, $lang, $isDNC) {
    $name = $name ?: 'there';
    $city = $city ?: 'your area';
    $addr = $address ?: 'your property';
    $phone = '(920) 777-9886';

    // DNC always gets empathetic version
    if ($isDNC) {
        if ($lang === 'Spanish') {
            return "Hola {$name}, soy Jorge de Green Bay. Sé que recibes muchos mensajes, pero me enteré que tu propiedad en {$addr} podría estar pasando por un momento difícil y quizás pueda ayudarte. ¿Estarías abierto a platicar? Solo responde SÍ o NO. O llámame al {$phone}.";
        }
        return "Hey {$name}, this is Jorge from Green Bay. I know you probably get a lot of messages, but I noticed your property at {$addr} might be going through a tough moment and I may be able to help. Would you be open to a quick chat? Just reply YES or NO. Or call {$phone}.";
    }

    if ($lang === 'Spanish') {
        switch ($step) {
            case 0: return "Hola {$name}, soy Jorge, inversionista local aquí en {$city}. Vi tu propiedad en {$addr} y me interesa genuinamente. ¿Estarías abierto a platicar? Solo responde SÍ o NO, sin compromiso. O llámame al {$phone}.";
            case 1: return "Hola {$name}, soy Jorge de nuevo — comprador local en {$city}. Intenté contactarte a otro número sobre tu propiedad en {$addr}. Sin presión, solo quería ver si puedo ayudarte. Un simple SÍ o NO funciona. {$phone}.";
            case 2: return "{$name}, soy Jorge. ¿Eres el dueño de {$addr}? Compro propiedades en {$city} y la tuya me llamó la atención. Un SÍ o NO rápido — es todo lo que necesito. {$phone}.";
            case 3: return "Hola {$name}, última vez que te escribo — entiendo perfectamente si no es buen momento. Si algún día quieres explorar tus opciones para {$addr}, aquí estoy. Solo responde o llama al {$phone}. Cuídate.";
        }
    }

    switch ($step) {
        case 0: return "Hey {$name}, this is Jorge, a local investor here in {$city}. I came across your property at {$addr} and I'm genuinely interested. Would you be open to a quick chat? Just reply YES or NO — no pressure at all. Or call me direct at {$phone}.";
        case 1: return "Hi {$name}, this is Jorge again — local buyer in {$city}. I tried reaching you at another number about your property at {$addr}. No pressure, just wanted to see if I can help. A simple YES or NO works. {$phone}.";
        case 2: return "{$name}, Jorge here. Are you the owner of {$addr}? I buy properties in {$city} and yours caught my eye. Quick YES or NO — that's all I need. {$phone}.";
        case 3: return "Hey {$name}, last time reaching out — I completely understand if now isn't the right time. If you ever want to explore your options for {$addr}, I'm here. Just text back or call {$phone}. Take care.";
    }

    return '';
}

// ── MAIN ────────────────────────────────────────────────────────
$cutoff = gmdate('Y-m-d\TH:i:s.000\Z', strtotime("-" . FC_HOURS_BETWEEN . " hours"));
$results = ['sent' => 0, 'moved_to_seguimiento' => 0, 'skipped' => 0, 'errors' => 0];

// Process each step: 0 (new), 1-3 (waiting 24h), 4 (exhausted)
for ($step = 0; $step <= 4; $step++) {

    if ($results['sent'] >= FC_MAX_PER_RUN) break;

    if ($step === 0) {
        // New contacts ready for first SMS
        $formula = "AND({Stage}='To Be Contacted',OR({First Contact Step}=0,{First Contact Step}=BLANK()))";
    } elseif ($step <= 3) {
        // Contacts waiting 24h after previous step
        $formula = "AND({Stage}='Contacted',{First Contact Step}={$step},IS_BEFORE({Last contact date},'" . date('Y-m-d', strtotime('-1 day')) . "'))";
    } else {
        // Step 4: all phones exhausted, move to Seguimiento
        $formula = "AND({Stage}='Contacted',{First Contact Step}=4,IS_BEFORE({Last contact date},'" . date('Y-m-d', strtotime('-1 day')) . "'))";
    }

    $url = fc_at_url('?' . http_build_query([
        'filterByFormula' => $formula,
        'maxRecords'      => FC_MAX_PER_RUN - $results['sent'],
    ]));
    $res = fc_at_request('GET', $url);
    if (!$res['ok']) continue;

    $records = $res['data']['records'] ?? [];

    foreach ($records as $rec) {
        if ($results['sent'] >= FC_MAX_PER_RUN) break;

        $id = $rec['id'];
        $f  = $rec['fields'] ?? [];
        $name    = $f['Full Name'] ?? '';
        $lang    = $f['Lenguage']  ?? 'English';
        $isDNC   = !empty($f['Do not contact']);
        $city    = $f['Mail City'] ?? '';
        $curStep = intval($f['First Contact Step'] ?? 0);

        // Resolve property address from lookup
        $propLookup = $f['Property Address (from Property Address)'] ?? null;
        $address = '';
        if (is_array($propLookup) && !empty($propLookup)) $address = $propLookup[0];
        elseif (is_string($propLookup)) $address = $propLookup;
        else $address = $f['Owner Address'] ?? '';

        // Step 4: move to Seguimiento
        if ($step === 4) {
            fc_at_request('PATCH', fc_at_url('/' . $id), ['fields' => [
                'Stage'              => 'Seguimiento',
                'Seguimiento Step'   => 0,
                'First Contact Step' => 0,
            ]]);
            fc_sync_lead_stage($f, 'Seguimiento');
            fer_log_info('fc_moved_seguimiento', ['id' => $id, 'name' => $name]);
            $results['moved_to_seguimiento']++;
            continue;
        }

        // DNC: only Phone1, then straight to Seguimiento
        if ($isDNC && $curStep > 0) {
            fc_at_request('PATCH', fc_at_url('/' . $id), ['fields' => [
                'Stage'              => 'Seguimiento',
                'Seguimiento Step'   => 0,
                'First Contact Step' => 0,
            ]]);
            fc_sync_lead_stage($f, 'Seguimiento');
            fer_log_info('fc_dnc_to_seguimiento', ['id' => $id, 'name' => $name]);
            $results['moved_to_seguimiento']++;
            continue;
        }

        // Get the phone for this step
        $phoneFields = ['Phone1', 'Phone2', 'Phone3', 'Phone4'];
        $phoneField  = $phoneFields[$curStep] ?? null;
        $phoneRaw    = $f[$phoneField] ?? '';
        $phoneE164   = fc_phone_e164($phoneRaw);

        // Skip if no phone for this step → advance to next step
        if (!$phoneE164) {
            $nextStep = $curStep + 1;
            // Check if there are more phones ahead
            $hasMore = false;
            for ($j = $nextStep; $j < 4; $j++) {
                if (!empty($f[$phoneFields[$j]])) { $hasMore = true; break; }
            }
            if ($hasMore) {
                fc_at_request('PATCH', fc_at_url('/' . $id), ['fields' => [
                    'First Contact Step' => $nextStep,
                    'Last contact date'  => date('Y-m-d', strtotime('-2 days')), // trigger immediate retry
                ]]);
            } else {
                fc_at_request('PATCH', fc_at_url('/' . $id), ['fields' => [
                    'Stage'              => 'Seguimiento',
                    'Seguimiento Step'   => 0,
                    'First Contact Step' => 0,
                ]]);
                fc_sync_lead_stage($f, 'Seguimiento');
                $results['moved_to_seguimiento']++;
            }
            $results['skipped']++;
            continue;
        }

        // Send SMS
        $msg = fc_get_message($curStep, $name ? explode(' ', $name)[0] : '', $address, $city, $lang, $isDNC);
        $smsResult = fer_quo_send_sms($phoneE164, $msg);

        if ($smsResult['success']) {
            $newStep = $isDNC ? 4 : ($curStep + 1); // DNC: mark as exhausted after Phone1
            $newStage = 'Contacted';

            fc_at_request('PATCH', fc_at_url('/' . $id), ['fields' => [
                'Stage'              => $newStage,
                'First Contact Step' => $newStep,
                'Last contact date'  => date('Y-m-d'),
                'SMS Sent'           => true,
            ]]);

            // Sync linked Lead(s) Stage — keeps Leads table aligned with Contacts pipeline
            fc_sync_lead_stage($f, $newStage);

            fer_log_info('fc_sms_sent', [
                'id' => $id, 'name' => $name, 'step' => $curStep,
                'phone_field' => $phoneField, 'to' => $phoneE164, 'lang' => $lang, 'dnc' => $isDNC,
            ]);
            $results['sent']++;
        } else {
            fer_log_error('fc_sms_failed', ['id' => $id, 'phone' => $phoneE164, 'error' => $smsResult['error']]);
            $results['errors']++;
        }

        // Human-like pacing between sends — avoids carrier spam filters.
        if (FC_SMS_DELAY_SECONDS > 0) {
            sleep(FC_SMS_DELAY_SECONDS);
        }
    }
}

echo json_encode(array_merge(['ok' => true], $results));
