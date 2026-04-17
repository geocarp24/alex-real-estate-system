<?php
// ============================================================
// FER — Seguimiento Engine + Cold (replaces Make scenarios 4656571 + 4656574)
//
// Cron: daily at 9:30 AM CST (15:30 UTC)
// URL: https://pinnaclegroupwi.com/Tools/fer_seguimiento.php
//
// 24 touches over 12 months. SMS + Email.
// Schedule: Month 1 (days 1,3,7,14,21) → Month 2-3 (bi-weekly)
//           → Month 4-12 (bi-weekly/monthly) → Step 24 = Dead
//
// Respects DNC, time window, and no false promises.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/fer_logger.php';
require_once __DIR__ . '/lib/fer_quo.php';

header('Content-Type: application/json');

define('SEG_BASE', 'appfQbDA750Oihy9J');
define('SEG_CONTACTS', 'tblacvw0Ss770x8l5');
define('SEG_MAX_PER_RUN', 20);

// Days until next follow-up per step
function seg_days_for_step($step) {
    $schedule = [
        0=>2, 1=>4, 2=>7, 3=>7, 4=>7,       // Month 1: days 1,3,7,14,21 (5 touches)
        5=>14, 6=>14, 7=>14, 8=>14, 9=>14,   // Month 2-3: bi-weekly (5 touches)
        10=>14, 11=>14, 12=>14, 13=>14, 14=>14, // Month 4-6: bi-weekly (5 touches)
        15=>21, 16=>21, 17=>21, 18=>21, 19=>21, // Month 7-9: every 3 weeks (5 touches)
        20=>30, 21=>30, 22=>30, 23=>30,       // Month 10-12: monthly (4 touches)
    ];
    return $schedule[$step] ?? 30;
}

// ── SMS Messages (24 unique, bilingual) ─────────────────────────
function seg_sms($step, $name, $lang) {
    $name = $name ? explode(' ', $name)[0] : 'there';
    $p = '(920) 777-9886';

    $en = [
        // Month 1: Intensive — different angles
        "Hey {$name}, this is Jorge — local buyer in Wisconsin. Sent you info about your property. Not a pitch, just a question. Hope you're doing well!",
        "Hey {$name}, Jorge here — just making sure you got my message. No pressure at all. Hope you're having a good week!",
        "Quick note {$name} — just a reminder, you don't need to fix anything to sell. Jorge buys as-is. Questions? {$p}",
        "{$name}, Jorge here. Just checking in — still interested in exploring your options? A quick YES or NO works. {$p}",
        "Hi {$name}, last message for a bit — if now isn't the right time, I completely understand. I'm here whenever you're ready. - Jorge {$p}",
        // Month 2-3: Value-driven
        "{$name}, quick update — properties in your area are moving. Want to know what yours could be worth? - Jorge {$p}",
        "Hey {$name}, Jorge here. Just wanted you to know — no commissions, no agent fees, no repairs on your end. That's how I work. {$p}",
        "{$name}, sometimes timing is everything. If your situation has changed, I'm just a text away. - Jorge",
        "Hi {$name} — I help homeowners in all kinds of situations. Divorce, inheritance, financial pressure — no judgment, just solutions. {$p}",
        "{$name}, just a friendly check-in from Jorge. No pitch — just wanted to see how things are going with your property.",
        // Month 4-6: Market context
        "Hey {$name}, market update: some homeowners in your area are selling off-market to avoid the hassle. Curious? {$p}",
        "{$name}, Jorge here. If you've been thinking about selling but don't know where to start — that's exactly where I help. {$p}",
        "Quick question {$name} — if you could sell your property with zero repairs and zero fees, would that interest you? - Jorge",
        "Hi {$name}, just circling back. I buy properties in your area and yours still interests me. No rush — just keeping the door open. {$p}",
        "{$name}, selling doesn't have to be stressful. Jorge handles everything — paperwork, timeline, closing. Just a thought. {$p}",
        // Month 7-9: Gentle check-ins
        "Hey {$name}, been a while — Jorge here. Just checking if anything has changed with your property situation. No pressure. {$p}",
        "{$name}, quick note — I'm still buying in your area. If you ever want to chat, I'm here. - Jorge {$p}",
        "Hi {$name}, hope you're well. If you know anyone looking to sell their property quickly, send them my way? - Jorge {$p}",
        "{$name}, sometimes the right time comes when you least expect it. If that day comes, I'm one text away. - Jorge",
        "Hey {$name} — not trying to be a bother, just genuinely interested in helping if you ever need it. Take care. {$p}",
        // Month 10-12: Final touches
        "{$name}, it's been a while since we connected. If your situation has changed, I'd love to hear from you. - Jorge {$p}",
        "Hi {$name}, Jorge here one more time. Your property still caught my eye. If you're open to a conversation, I'm here. {$p}",
        "{$name}, this is my second to last message — I don't want to overstay my welcome. But if you ever need a fast, fair solution, call me. {$p}",
        "Hey {$name}, last check-in from Jorge. I wish you all the best. If you ever need help with your property, you know where to find me. {$p} Take care.",
    ];

    $es = [
        // Mes 1: Intensivo
        "Hola {$name}, soy Jorge — comprador local en Wisconsin. Te envié info sobre tu propiedad. No es presión, solo una pregunta. ¡Espero que estés bien!",
        "Hola {$name}, Jorge de nuevo — solo asegurarme que recibiste mi mensaje. Sin presión. ¡Que tengas buena semana!",
        "Nota rápida {$name} — recuerda, no necesitas arreglar nada para vender. Jorge compra como está. ¿Preguntas? {$p}",
        "{$name}, soy Jorge. Solo chequeo — ¿todavía interesado en explorar tus opciones? Un SÍ o NO funciona. {$p}",
        "Hola {$name}, último mensaje por un rato — si no es buen momento, lo entiendo. Aquí estoy cuando quieras. - Jorge {$p}",
        // Mes 2-3: Valor
        "{$name}, actualización — propiedades en tu zona se están moviendo. ¿Quieres saber cuánto podría valer la tuya? - Jorge {$p}",
        "Hola {$name}, soy Jorge. Solo recordarte — sin comisiones, sin agentes, sin reparaciones de tu parte. Así trabajo. {$p}",
        "{$name}, a veces el momento lo es todo. Si tu situación cambió, estoy a un mensaje de distancia. - Jorge",
        "Hola {$name} — ayudo a propietarios en todo tipo de situaciones. Divorcio, herencia, presión financiera — sin juicios, solo soluciones. {$p}",
        "{$name}, solo un saludo de Jorge. Sin pitch — solo quería saber cómo van las cosas con tu propiedad.",
        // Mes 4-6: Contexto
        "Hola {$name}, algunos propietarios en tu zona están vendiendo directamente para evitar complicaciones. ¿Te interesa? {$p}",
        "{$name}, soy Jorge. Si has pensado en vender pero no sabes por dónde empezar — exactamente ahí es donde ayudo. {$p}",
        "Pregunta rápida {$name} — si pudieras vender tu propiedad sin reparaciones y sin comisiones, ¿te interesaría? - Jorge",
        "Hola {$name}, vuelvo a contactarte. Sigo comprando en tu zona y tu propiedad me interesa. Sin prisa. {$p}",
        "{$name}, vender no tiene que ser estresante. Jorge se encarga de todo — papeles, tiempos, cierre. Solo una idea. {$p}",
        // Mes 7-9: Check-ins
        "Hola {$name}, ha pasado un tiempo — soy Jorge. Solo chequeando si algo cambió con tu propiedad. Sin presión. {$p}",
        "{$name}, nota rápida — sigo comprando en tu zona. Si algún día quieres platicar, aquí estoy. - Jorge {$p}",
        "Hola {$name}, espero que estés bien. Si conoces a alguien que quiera vender rápido, mándamelo. - Jorge {$p}",
        "{$name}, a veces el momento correcto llega cuando menos lo esperas. Si ese día llega, estoy a un mensaje. - Jorge",
        "Hola {$name} — no quiero molestar, solo genuinamente interesado en ayudar si algún día lo necesitas. Cuídate. {$p}",
        // Mes 10-12: Finales
        "{$name}, ha pasado un buen tiempo. Si tu situación cambió, me encantaría saber de ti. - Jorge {$p}",
        "Hola {$name}, Jorge una vez más. Tu propiedad me sigue interesando. Si estás abierto a una conversación, aquí estoy. {$p}",
        "{$name}, este es mi penúltimo mensaje — no quiero abusar. Pero si algún día necesitas una solución rápida y justa, llámame. {$p}",
        "Hola {$name}, último mensaje de Jorge. Te deseo lo mejor. Si algún día necesitas ayuda con tu propiedad, ya sabes dónde encontrarme. {$p} Cuídate.",
    ];

    $msgs = ($lang === 'Spanish') ? $es : $en;
    return $msgs[$step] ?? $msgs[count($msgs) - 1];
}

// ── Email subject + body ────────────────────────────────────────
function seg_email($step, $name, $address, $lang) {
    $name = $name ? explode(' ', $name)[0] : 'there';
    $addr = $address ?: 'your property';

    // Rotate between 4 email themes
    $theme = $step % 4;

    if ($lang === 'Spanish') {
        $subjects = [
            "¿Pensando en vender {$addr}?",
            "Una opción para tu propiedad",
            "Jorge de Pinnacle — actualización",
            "Tu propiedad en {$addr}",
        ];
        $bodies = [
            "Hola {$name},\n\nSoy Jorge Cruz de Pinnacle Holdings Group en Green Bay. Sigo interesado en tu propiedad en {$addr}.\n\nCompro propiedades directamente — sin comisiones, sin agentes, sin reparaciones de tu parte. El proceso es sencillo y rápido cuando todo está en orden.\n\nSi quieres explorar tus opciones, solo responde a este email o llámame al (920) 777-9886.\n\nSin compromiso,\nJorge Cruz\nPinnacle Holdings Group\n(920) 777-9886\ndeals@pinnaclegroupwi.com",
            "Hola {$name},\n\nSolo quería darte una actualización — sigo activamente comprando propiedades en tu zona.\n\nSi tu situación ha cambiado o estás considerando vender, me encantaría platicar. Sin presión, sin compromiso.\n\nJorge Cruz\nPinnacle Holdings Group\n(920) 777-9886",
            "Hola {$name},\n\nEspero que estés bien. Solo un recordatorio de que estoy aquí si algún día decides explorar tus opciones para {$addr}.\n\nJorge se encarga de todo el proceso para que sea lo más sencillo posible para ti.\n\n¿Tienes preguntas? Responde a este email o llama al (920) 777-9886.\n\nJorge Cruz\nPinnacle Holdings Group",
            "Hola {$name},\n\nÚltima actualización por un tiempo — tu propiedad en {$addr} sigue interesándome.\n\nSi el momento es correcto para ti, aquí estoy. Si no, te deseo lo mejor.\n\nJorge Cruz\n(920) 777-9886\npinnaclegroupwi.com",
        ];
    } else {
        $subjects = [
            "Thinking about selling {$addr}?",
            "An option for your property",
            "Jorge from Pinnacle — quick update",
            "Your property at {$addr}",
        ];
        $bodies = [
            "Hi {$name},\n\nThis is Jorge Cruz from Pinnacle Holdings Group in Green Bay. I'm still interested in your property at {$addr}.\n\nI buy properties directly — no commissions, no agents, no repairs on your end. The process is straightforward and fast when everything is in order.\n\nIf you'd like to explore your options, just reply to this email or call me at (920) 777-9886.\n\nNo pressure,\nJorge Cruz\nPinnacle Holdings Group\n(920) 777-9886\ndeals@pinnaclegroupwi.com",
            "Hi {$name},\n\nJust wanted to give you a quick update — I'm still actively buying properties in your area.\n\nIf your situation has changed or you're considering selling, I'd love to chat. No pressure, no obligation.\n\nJorge Cruz\nPinnacle Holdings Group\n(920) 777-9886",
            "Hi {$name},\n\nHope you're doing well. Just a reminder that I'm here if you ever decide to explore your options for {$addr}.\n\nI handle the entire process to make it as simple as possible for you.\n\nQuestions? Reply to this email or call (920) 777-9886.\n\nJorge Cruz\nPinnacle Holdings Group",
            "Hi {$name},\n\nLast update for a while — your property at {$addr} still interests me.\n\nIf the timing is right for you, I'm here. If not, I wish you all the best.\n\nJorge Cruz\n(920) 777-9886\npinnaclegroupwi.com",
        ];
    }

    return ['subject' => $subjects[$theme], 'body' => $bodies[$theme]];
}

// ── Airtable helpers ────────────────────────────────────────────
function seg_at($method, $url, $body = null) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ];
    if ($body) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $code >= 200 && $code < 300, 'data' => json_decode($resp, true)];
}

function seg_url($path = '') {
    return 'https://api.airtable.com/v0/' . SEG_BASE . '/' . SEG_CONTACTS . $path;
}

function seg_phone_e164($raw) {
    if (!$raw) return '';
    $digits = preg_replace('/[^0-9]/', '', strval(intval(floatval($raw))));
    if (strlen($digits) === 10) return '+1' . $digits;
    if (strlen($digits) === 11 && $digits[0] === '1') return '+' . $digits;
    return '';
}

// ── Send email via send_notification.php ─────────────────────────
function seg_send_email($to, $subject, $body) {
    if (!$to) return false;
    $ch = curl_init('https://pinnaclegroupwi.com/Tools/send_notification.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['type' => 'email', 'to' => $to, 'subject' => $subject, 'body' => $body]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

// ── MAIN ────────────────────────────────────────────────────────
$today = date('Y-m-d');
$results = ['sms_sent' => 0, 'email_sent' => 0, 'moved_dead' => 0, 'errors' => 0];

// ── PART 1: Cold — Step >= 24 → Dead ────────────────────────────
$coldFormula = "AND({Stage}='Seguimiento',{Seguimiento Step}>=24)";
$coldRes = seg_at('GET', seg_url('?' . http_build_query([
    'filterByFormula' => $coldFormula,
    'maxRecords'      => 50,
    'fields[]'        => 'Full Name',
])));
foreach (($coldRes['data']['records'] ?? []) as $rec) {
    seg_at('PATCH', seg_url('/' . $rec['id']), ['fields' => ['Stage' => 'Dead']]);
    fer_log_info('seg_cold_dead', ['id' => $rec['id'], 'name' => $rec['fields']['Full Name'] ?? '?']);
    $results['moved_dead']++;
}

// ── PART 2: Follow-ups — Stage=Seguimiento, Step<24, due today ──
$formula = "AND({Stage}='Seguimiento',{Do not contact}!=TRUE(),{Seguimiento Step}<24,OR({Seguimiento Step}=0,IS_BEFORE({Next follow up date},DATEADD(TODAY(),1,'days'))))";
$res = seg_at('GET', seg_url('?' . http_build_query([
    'filterByFormula' => $formula,
    'maxRecords'      => SEG_MAX_PER_RUN,
])));

foreach (($res['data']['records'] ?? []) as $rec) {
    $id = $rec['id'];
    $f  = $rec['fields'] ?? [];
    $name    = $f['Full Name'] ?? '';
    $lang    = $f['Lenguage']  ?? 'English';
    $step    = intval($f['Seguimiento Step'] ?? 0);
    $phone1  = seg_phone_e164($f['Phone1'] ?? '');
    $email1  = $f['Email1'] ?? '';

    // Property address from lookup
    $propLookup = $f['Property Address (from Property Address)'] ?? null;
    $address = '';
    if (is_array($propLookup) && !empty($propLookup)) $address = $propLookup[0];
    elseif (is_string($propLookup)) $address = $propLookup;

    // Send SMS
    if ($phone1) {
        $msg = seg_sms($step, $name, $lang);
        $smsResult = fer_quo_send_sms($phone1, $msg);
        if ($smsResult['success']) {
            $results['sms_sent']++;
            fer_log_info('seg_sms_sent', ['id' => $id, 'step' => $step, 'to' => $phone1]);
        } else {
            fer_log_error('seg_sms_failed', ['id' => $id, 'step' => $step, 'error' => $smsResult['error']]);
            $results['errors']++;
        }
    }

    // Send Email (every other step to avoid overload)
    if ($email1 && $step % 2 === 0) {
        $emailData = seg_email($step, $name, $address, $lang);
        $emailOk = seg_send_email($email1, $emailData['subject'], $emailData['body']);
        if ($emailOk) {
            $results['email_sent']++;
            fer_log_info('seg_email_sent', ['id' => $id, 'step' => $step, 'to' => $email1]);
        }
    }

    // Update contact: Step++, Next follow up date, Last contact date
    $nextDays = seg_days_for_step($step);
    $nextDate = date('Y-m-d', strtotime("+{$nextDays} days"));
    seg_at('PATCH', seg_url('/' . $id), ['fields' => [
        'Seguimiento Step'   => $step + 1,
        'Last contact date'  => $today,
        'Next follow up date'=> $nextDate,
    ]]);
}

echo json_encode(array_merge(['ok' => true, 'date' => $today], $results));
