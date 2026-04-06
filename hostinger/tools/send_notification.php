<?php
// ============================================================
//  PINNACLE — send_notification.php
//  Handles SMS (Quo API) and Email (SMTP Hostinger)
//  Called from Call Assistant when appointment is scheduled
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

require_once __DIR__ . '/config.php';

$body = json_decode(file_get_contents('php://input'), true);
$type = $body['type'] ?? '';

// ── SMS via Quo API ───────────────────────────────────────────
if ($type === 'sms') {
    $to      = preg_replace('/[^0-9]/', '', $body['to'] ?? '');
    $message = $body['message'] ?? '';

    if (!$to || !$message) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing to or message']);
        exit;
    }

    // Format as E164
    if (strlen($to) === 10) $to = '1' . $to;
    $to = '+' . $to;

    $payload = json_encode([
        'from'    => '+19207779886',
        'to'      => [$to],
        'content' => $message,
    ]);

    $ch = curl_init('https://api.openphone.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: ' . QUO_API_KEY,
            'X-OpenPhone-Number: +19207779886',
        ],
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status >= 200 && $status < 300) {
        echo json_encode(['success' => true, 'type' => 'sms']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'SMS failed', 'detail' => $response]);
    }
    exit;
}

// ── EMAIL via SMTP Hostinger ──────────────────────────────────
if ($type === 'email') {
    $to      = $body['to']      ?? '';
    $subject = $body['subject'] ?? 'Message from Pinnacle Holdings Group';
    $text    = $body['body']    ?? '';

    if (!$to || !$text) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing to or body']);
        exit;
    }

    // Use PHPMailer if available, fallback to mail()
    $from_email = 'deals@pinnaclegroupwi.com';
    $from_name  = 'Jorge — Pinnacle Holdings Group';

    // Try PHPMailer (if installed via composer)
    $phpmailer_path = __DIR__ . '/vendor/autoload.php';
    if (file_exists($phpmailer_path)) {
        require_once $phpmailer_path;
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $from_email;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = 'ssl';
            $mail->Port       = 465;
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $text;
            $mail->send();
            echo json_encode(['success' => true, 'type' => 'email']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Email failed: ' . $mail->ErrorInfo]);
        }
    } else {
        // Fallback: PHP mail()
        $headers  = "From: {$from_name} <{$from_email}>\r\n";
        $headers .= "Reply-To: {$from_email}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $sent = mail($to, $subject, $text, $headers);
        if ($sent) {
            echo json_encode(['success' => true, 'type' => 'email']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Email failed via mail()']);
        }
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown type']);
