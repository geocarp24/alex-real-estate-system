<?php
/**
 * Property Inspector Proxy
 * Pinnacle Holdings Group LLC
 * Handles: Airtable CRUD, photo uploads, OpenAI Whisper, AI estimate generation
 */

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_contact':        getContact();          break;
    case 'get_lead':           getLead();             break;
    case 'get_existing_visit': getExistingVisit();    break;
    case 'upload_photos':      uploadPhotos();        break;
    case 'transcribe':         transcribeAudio();     break;
    case 'generate_estimate':  generateEstimate();    break;
    case 'save_records':       saveRecords();         break;
    case 'send_client_invite': sendClientInvite();    break;
    case 'debug_invite':       debugInvite();         break;
    default:
        echo json_encode(['error' => 'Invalid action: ' . htmlspecialchars($action)]);
}

// ─────────────────────────────────────────────
// SEND CLIENT INVITE — SMS via Quo + Email via SMTP
// ─────────────────────────────────────────────
function sendClientInvite() {
    $input       = json_decode(file_get_contents('php://input'), true);
    $contactId   = $input['contact_id']   ?? '';
    $contactName = $input['contact_name'] ?? 'there';
    $phone       = $input['phone']        ?? '';
    $email       = $input['email']        ?? '';
    $address     = $input['address']      ?? 'your property';

    if (!$contactId) { echo json_encode(['error' => 'No contact ID']); return; }

    $clientUrl = "https://pinnaclegroupwi.com/Tools/Property_Inspector.html?cid={$contactId}&mode=client";

    $smsMsg = "Hi {$contactName}! Jorge from Pinnacle Holdings Group here. Please use this link to upload photos of your property at {$address}: {$clientUrl} — Thank you!";

    $emailSubject = "Property Photos — Pinnacle Holdings Group";
    $emailBody    = "Hi {$contactName},\n\nThank you for working with Pinnacle Holdings Group.\n\nPlease use the link below to upload photos of your property at {$address}:\n\n{$clientUrl}\n\nYou can save your progress and return at any time.\n\nIf you have any questions, please call Jorge at (920) 777-9886.\n\nBest regards,\nJorge\nPinnacle Holdings Group LLC\n(920) 777-9886";

    $results = [];

    // ── SMS via Quo API ──
    if ($phone) {
        // Handle Phone1 as Number type — may arrive as float (19207843792.0) or scientific notation
        $phone = preg_replace('/[^0-9]/', '', strval(intval(floatval($phone))));
        if (strlen($phone) === 10) $phone = '1' . $phone;
        if (strlen($phone) < 10) {
            $results['sms'] = ['success' => false, 'error' => 'Phone number too short: ' . $phone];
        } else {
            $phone = '+' . $phone;

        $smsPayload = json_encode([
            'content' => $smsMsg,
            'from'    => '+19207779886',
            'to'      => [$phone]
        ]);

        $ch = curl_init('https://api.openphone.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $smsPayload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . QUO_API_KEY,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT        => 15
        ]);
        $smsResp = curl_exec($ch);
        $smsCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $smsErr  = curl_error($ch);
        curl_close($ch);
        $results['sms'] = [
            'code'     => $smsCode,
            'sent_to'  => $phone,
            'success'  => $smsCode >= 200 && $smsCode < 300,
            'response' => json_decode($smsResp, true),
            'curl_err' => $smsErr
        ];
        } // close else (phone length valid)
    } // close if ($phone)

    // ── Email via SMTP (Hostinger) ──
    if ($email) {
        $sent = sendSmtpEmail($email, $emailSubject, $emailBody);
        $results['email'] = ['sent_to' => $email, 'success' => $sent];
    }

    $anySuccess = !empty($results['sms']['success']) || !empty($results['email']['success']);
    echo json_encode([
        'success' => $anySuccess,
        'results' => $results,
        'link'    => $clientUrl,
        'input_received' => ['phone' => $input['phone'] ?? '(empty)', 'email' => $input['email'] ?? '(empty)', 'contact_name' => $contactName]
    ]);
}

function sendSmtpEmail($to, $subject, $body) {
    $from_email = 'deals@pinnaclegroupwi.com';
    $from_name  = 'Jorge — Pinnacle Holdings Group';

    // Try PHPMailer first (most reliable)
    $phpmailer_path = __DIR__ . '/vendor/autoload.php';
    if (file_exists($phpmailer_path)) {
        require_once $phpmailer_path;
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
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
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback: PHP mail()
    $headers  = "From: {$from_name} <{$from_email}>\r\n";
    $headers .= "Reply-To: {$from_email}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}


// ─────────────────────────────────────────────
// DEBUG — test SMS + Email config
// ─────────────────────────────────────────────
function debugInvite() {
    $debug = [];

    // Check constants defined
    $debug['quo_api_key_defined']  = defined('QUO_API_KEY');
    $debug['smtp_password_defined']= defined('SMTP_PASSWORD');
    $debug['quo_key_length']       = defined('QUO_API_KEY') ? strlen(QUO_API_KEY) : 0;

    // Test SMS
    $testPhone = '+19207843792'; // Kathryn test number
    $smsPayload = json_encode([
        'content' => 'TEST — Pinnacle Inspector debug message',
        'from'    => '+19207779886',
        'to'      => [$testPhone]
    ]);

    $ch = curl_init('https://api.openphone.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $smsPayload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . QUO_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    $smsResp = curl_exec($ch);
    $smsCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $smsErr  = curl_error($ch);
    curl_close($ch);

    $debug['sms_http_code'] = $smsCode;
    $debug['sms_curl_error'] = $smsErr;
    $debug['sms_response'] = json_decode($smsResp, true) ?? $smsResp;

    // Test Email
    $emailSent = sendSmtpEmail('deals@pinnaclegroupwi.com', 'TEST Email Debug', 'This is a test from Property Inspector debug.');
    $debug['email_sent'] = $emailSent;

    echo json_encode(['debug' => $debug], JSON_PRETTY_PRINT);
}


function getExistingVisit() {
    $contactId = $_GET['contact_id'] ?? '';
    if (!$contactId) { echo json_encode(['exists' => false]); return; }

    // NOTE: ARRAYJOIN({Contact}) returns display names, NOT record IDs.
    // So we fetch recent records and filter in PHP by checking the Contact linked field,
    // which the API returns as an array of record IDs.
    $url = 'https://api.airtable.com/v0/[REDACTED_AIRTABLE_BASE_ID]/Property%20Docs'
         . '?sort[0][field]=Visit%20Date&sort[0][direction]=desc&maxRecords=50';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data    = json_decode($response, true);
    $records = $data['records'] ?? [];

    // Find the most recent record linked to this contact
    $matchedFields = null;
    foreach ($records as $rec) {
        $linkedContacts = $rec['fields']['Contact'] ?? [];
        if (is_array($linkedContacts) && in_array($contactId, $linkedContacts)) {
            $matchedFields = $rec['fields'];
            break; // Already sorted by date desc, so first match = most recent
        }
    }

    if (!$matchedFields) { echo json_encode(['exists' => false]); return; }

    $fields = $matchedFields;

    // ── Override Airtable attachment URLs with permanent server URLs ──
    // Airtable re-hosts attachments with temporary tokens that expire in ~2 hours.
    // Our photos are saved permanently on the server, so scan that directory instead.
    $safeContactId = preg_replace('/[^a-zA-Z0-9_-]/', '', $contactId);
    $photosDir = __DIR__ . '/property_photos/' . $safeContactId;
    $photosUrl = 'https://pinnaclegroupwi.com/Tools/property_photos/' . $safeContactId;

    if (is_dir($photosDir)) {
        // Map section directory prefixes to Airtable field names
        $sectionMap = [
            'Exterior_Front' => 'Exterior Front', 'Exterior_Back' => 'Exterior Back',
            'Garage' => 'Garage', 'Living_Room' => 'Living Room', 'Kitchen' => 'Kitchen',
            'Basement' => 'Basement', 'Roof' => 'Roof', 'HVAC' => 'HVAC', 'Additional' => 'Additional'
        ];
        for ($i = 1; $i <= 10; $i++) $sectionMap["Bedroom_{$i}"]  = "Bedroom {$i}";
        for ($i = 1; $i <=  5; $i++) $sectionMap["Bathroom_{$i}"] = "Bathroom {$i}";

        $subdirs = scandir($photosDir);
        foreach ($subdirs as $subdir) {
            if ($subdir === '.' || $subdir === '..') continue;
            $fullPath = $photosDir . '/' . $subdir;
            if (!is_dir($fullPath)) continue;

            // Extract section prefix from directory name (e.g., "Exterior_Front_20260325_120000")
            foreach ($sectionMap as $prefix => $fieldName) {
                if (strpos($subdir, $prefix) === 0) {
                    $photos = [];
                    $files = scandir($fullPath);
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') continue;
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg','jpeg','png','webp','gif','heic','heif'])) {
                            $photos[] = ['url' => $photosUrl . '/' . $subdir . '/' . $file];
                        }
                    }
                    if (!empty($photos)) {
                        // Server photos are permanent — REPLACE Airtable temp URLs for this section
                        // (Airtable URLs are duplicates that expire in ~2 hours)
                        $fields[$fieldName] = $photos;
                    }
                    break;
                }
            }
        }
    }

    // Also load transcript from Visit Repair Estimate (same approach — filter in PHP)
    $tUrl = 'https://api.airtable.com/v0/[REDACTED_AIRTABLE_BASE_ID]/Visit%20Repair%20Estimate'
          . '?sort[0][field]=Visit%20Date&sort[0][direction]=desc&maxRecords=50';

    $ch2 = curl_init($tUrl);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN]
    ]);
    $tResponse = curl_exec($ch2);
    curl_close($ch2);
    $tData    = json_decode($tResponse, true);
    $tRecords = $tData['records'] ?? [];

    $transcript    = '';
    $repairList    = '';
    $totalEstimate = 0;
    foreach ($tRecords as $tRec) {
        $tLinked = $tRec['fields']['Contact'] ?? [];
        if (is_array($tLinked) && in_array($contactId, $tLinked)) {
            $tf = $tRec['fields'];
            $transcript    = $tf['Voice Transcript'] ?? '';
            $repairList    = $tf['Repair List']      ?? '';
            $totalEstimate = $tf['Total Estimate']   ?? 0;
            break;
        }
    }

    echo json_encode([
        'exists'         => true,
        'fields'         => $fields,
        'transcript'     => $transcript,
        'repair_list'    => $repairList,
        'total_estimate' => $totalEstimate
    ]);
}

function getLead() {
    $recordId = $_GET['id'] ?? '';
    if (!$recordId) { echo json_encode(['error' => 'No record ID']); return; }

    // Property Address links to Leads table
    foreach (['Leads', 'Deals'] as $table) {
        $url = 'https://api.airtable.com/v0/[REDACTED_AIRTABLE_BASE_ID]/' . rawurlencode($table) . '/' . urlencode($recordId);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) { echo $response; return; }
    }
    echo json_encode(['error' => 'Record not found', 'id' => $recordId]);
}


function getContact() {
    $recordId = $_GET['id'] ?? '';
    if (!$recordId) { echo json_encode(['error' => 'No record ID provided']); return; }

    $url = 'https://api.airtable.com/v0/[REDACTED_AIRTABLE_BASE_ID]/Contacts/' . urlencode($recordId);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode(['error' => 'Airtable error', 'code' => $httpCode, 'raw' => $response]);
        return;
    }
    echo $response;
}

// ─────────────────────────────────────────────
// UPLOAD PHOTOS to server, return public URLs
// ─────────────────────────────────────────────
function uploadPhotos() {
    $contactId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['contact_id'] ?? 'unknown');
    $section   = preg_replace('/[^a-zA-Z0-9 _-]/', '', $_POST['section'] ?? 'misc');
    $safeSection = str_replace(' ', '_', $section);
    $timestamp = date('Ymd_His');

    $uploadDir = __DIR__ . '/property_photos/' . $contactId . '/' . $safeSection . '_' . $timestamp . '/';
    $urlBase   = 'https://pinnaclegroupwi.com/Tools/property_photos/' . $contactId . '/' . $safeSection . '_' . $timestamp . '/';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        echo json_encode(['error' => 'Cannot create upload directory']);
        return;
    }

    $urls    = [];
    $allowed = ['jpg','jpeg','png','heic','heif','webp','gif'];

    foreach ($_FILES as $fileKey => $file) {
        // Normalize single or multiple file input
        $names   = is_array($file['name'])     ? $file['name']     : [$file['name']];
        $tmps    = is_array($file['tmp_name']) ? $file['tmp_name'] : [$file['tmp_name']];
        $errors  = is_array($file['error'])    ? $file['error']    : [$file['error']];

        foreach ($names as $i => $name) {
            if ($errors[$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;
            $filename = uniqid('ph_') . '.' . $ext;
            if (move_uploaded_file($tmps[$i], $uploadDir . $filename)) {
                $urls[] = $urlBase . $filename;
            }
        }
    }

    echo json_encode(['success' => true, 'urls' => $urls, 'section' => $section, 'count' => count($urls)]);
}

// ─────────────────────────────────────────────
// TRANSCRIBE audio via OpenAI Whisper
// ─────────────────────────────────────────────
function transcribeAudio() {
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'No audio file received', 'files_debug' => array_keys($_FILES)]);
        return;
    }

    $audio   = $_FILES['audio'];
    $tmpPath = $audio['tmp_name'];
    $origName = $audio['name'] ?: 'audio.webm';
    $ext     = strtolower(pathinfo($origName, PATHINFO_EXTENSION)) ?: 'webm';
    $mime    = $audio['type'] ?: 'audio/webm';

    // Whisper needs a valid extension — remap common browser types
    $extMap = ['ogg'=>'ogg','webm'=>'webm','mp4'=>'mp4','m4a'=>'m4a','wav'=>'wav','mp3'=>'mp3'];
    if (!array_key_exists($ext, $extMap)) $ext = 'webm';

    $cfile = new CURLFile($tmpPath, $mime, 'audio.' . $ext);

    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['file' => $cfile, 'model' => 'whisper-1'],
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . OPENAI_API_KEY],
        CURLOPT_TIMEOUT        => 60
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) { echo json_encode(['error' => 'CURL error: ' . $curlErr]); return; }

    $result = json_decode($response, true);
    if ($httpCode !== 200) {
        echo json_encode(['error' => 'Whisper API error', 'code' => $httpCode, 'detail' => $result]);
        return;
    }

    echo json_encode(['success' => true, 'transcript' => $result['text'] ?? '']);
}

// ─────────────────────────────────────────────
// GENERATE AI REPAIR ESTIMATE via GPT-4o-mini
// ─────────────────────────────────────────────
function generateEstimate() {
    $input       = json_decode(file_get_contents('php://input'), true);
    $transcripts = trim($input['transcripts'] ?? '');
    $zipCode     = preg_replace('/[^0-9]/', '', $input['zip_code'] ?? '');
    $city        = trim($input['city']   ?? 'Wisconsin');
    $estate      = trim($input['estate'] ?? 'WI');

    if (!$transcripts) { echo json_encode(['error' => 'No transcripts provided']); return; }

    $location = $city ? "{$city}, {$estate} {$zipCode}" : "Wisconsin, USA";

    $prompt = <<<EOT
You are a licensed general contractor specializing in residential property repair estimates in {$location}.

Use average contractor labor and material costs specific to the {$location} area when generating estimates.
Consider local market rates, cost of living, and typical contractor pricing in that zip code.

Based on the following property inspection voice notes, generate a detailed, itemized repair estimate.

INSPECTION NOTES:
{$transcripts}

Respond ONLY with valid JSON. No markdown fences, no explanation. Use this exact structure:
{
  "repair_list": [
    {"item": "Roof Replacement", "description": "Full tear-off and replacement based on local {$location} contractor rates", "cost": 12000}
  ],
  "total_estimate": 35000,
  "repair_list_text": "1. Roof Replacement - \$12,000\n2. HVAC System - \$8,000",
  "line_items": "- Roof Replacement: \$12,000\n- HVAC System: \$8,000",
  "location_note": "Prices based on average contractor rates in {$location}"
}
EOT;

    $payload = json_encode([
        'model'       => 'gpt-4o-mini',
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'max_tokens'  => 2000,
        'temperature' => 0.2
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT        => 60
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result  = json_decode($response, true);
    $content = $result['choices'][0]['message']['content'] ?? '{}';

    // Strip possible markdown fences
    $content = preg_replace('/```json\s*/i', '', $content);
    $content = preg_replace('/```\s*/', '', $content);
    $content = trim($content);

    $estimate = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['error' => 'Could not parse AI response', 'raw' => $content]);
        return;
    }

    echo json_encode(['success' => true, 'estimate' => $estimate]);
}

// ─────────────────────────────────────────────
// SAVE RECORDS to Airtable
// Property Docs + Visit Repair Estimate
// ─────────────────────────────────────────────
function saveRecords() {
    $input       = json_decode(file_get_contents('php://input'), true);
    $contactId   = $input['contact_id']    ?? '';
    $visitName   = $input['visit_name']    ?? ('Visit ' . date('Y-m-d'));
    $visitDate   = date('Y-m-d');
    $notes       = $input['notes']         ?? '';
    $photoUrls   = $input['photo_urls']    ?? [];
    $allTranscripts = $input['all_transcripts'] ?? '';
    $estimate    = $input['estimate']      ?? [];
    $results     = [];

    // ── 1. Property Docs ─────────────────────
    $docsFields = [
        'Name'       => $visitName,
        'Visit Date' => $visitDate,
        'Notes'      => $notes
    ];
    if ($contactId) $docsFields['Contact'] = [$contactId];

    foreach ($photoUrls as $section => $urls) {
        if (!empty($urls)) {
            $docsFields[$section] = array_map(fn($u) => ['url' => $u], (array)$urls);
        }
    }

    // Check if record already exists — UPDATE instead of CREATE to avoid duplicates
    $existingDocsId = $contactId ? findExistingRecord('Property Docs', $contactId) : null;
    if ($existingDocsId) {
        $results['property_docs'] = airtableUpdate('Property Docs', $existingDocsId, $docsFields);
    } else {
        $results['property_docs'] = airtableCreate('Property Docs', $docsFields);
    }

    // ── 2. Visit Repair Estimate ──────────────
    $estimateFields = [
        'Name'             => $visitName,
        'Visit Date'       => $visitDate,
        'Voice Transcript' => $allTranscripts,
        'Repair List'      => $estimate['repair_list_text'] ?? '',
        'Line Items'       => $estimate['line_items']       ?? ''
    ];
    if ($contactId) $estimateFields['Contact'] = [$contactId];
    if (!empty($estimate['total_estimate']) && is_numeric($estimate['total_estimate'])) {
        $estimateFields['Total Estimate'] = floatval($estimate['total_estimate']);
    }

    // Check if record already exists — UPDATE instead of CREATE to avoid duplicates
    $existingEstId = $contactId ? findExistingRecord('Visit Repair Estimate', $contactId) : null;
    if ($existingEstId) {
        $results['visit_repair_estimate'] = airtableUpdate('Visit Repair Estimate', $existingEstId, $estimateFields);
    } else {
        $results['visit_repair_estimate'] = airtableCreate('Visit Repair Estimate', $estimateFields);
    }

    $hasError = isset($results['property_docs']['error']) || isset($results['visit_repair_estimate']['error']);
    echo json_encode(['success' => !$hasError, 'results' => $results]);
}

// ─────────────────────────────────────────────
// HELPER: Find existing record by Contact linked field
// Returns record ID if found, null if not
// ─────────────────────────────────────────────
function findExistingRecord($table, $contactId) {
    $url = 'https://api.airtable.com/v0/[REDACTED_AIRTABLE_BASE_ID]/' . rawurlencode($table)
         . '?sort[0][field]=Visit%20Date&sort[0][direction]=desc&maxRecords=50';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . AIRTABLE_TOKEN]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data    = json_decode($response, true);
    $records = $data['records'] ?? [];

    foreach ($records as $rec) {
        $linked = $rec['fields']['Contact'] ?? [];
        if (is_array($linked) && in_array($contactId, $linked)) {
            return $rec['id']; // Return the Airtable record ID
        }
    }
    return null;
}

// ─────────────────────────────────────────────
// HELPER: POST to Airtable (CREATE new record)
// ─────────────────────────────────────────────
function airtableCreate($table, $fields) {
    $url     = 'https://api.airtable.com/v0/[REDACTED_AIRTABLE_BASE_ID]/' . rawurlencode($table);
    $payload = json_encode(['fields' => $fields]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// ─────────────────────────────────────────────
// HELPER: PATCH to Airtable (UPDATE existing record)
// ─────────────────────────────────────────────
function airtableUpdate($table, $recordId, $fields) {
    $url     = 'https://api.airtable.com/v0/[REDACTED_AIRTABLE_BASE_ID]/' . rawurlencode($table) . '/' . $recordId;
    $payload = json_encode(['fields' => $fields]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . AIRTABLE_TOKEN,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
