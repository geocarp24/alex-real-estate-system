<?php
// ============================================================
//  PINNACLE — calendar.php
//  Creates Google Calendar events via API in admin@geocarpentry.com
//  Requires: Google OAuth2 access token stored in config.php
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

require_once __DIR__ . '/config.php';

$body     = json_decode(file_get_contents('php://input'), true);
$title    = $body['title']         ?? 'Property Visit';
$location = $body['location']      ?? '';
$desc     = $body['description']   ?? '';
$start    = $body['start']         ?? '';
$end      = $body['end']           ?? '';
$tz       = $body['timezone']      ?? 'America/Chicago';
$attendee = $body['attendeeEmail'] ?? null;
$reminders = $body['reminders']    ?? [1440, 120];

if (!$start || !$end) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing start or end time']);
    exit;
}

// ── Build event payload ───────────────────────────────────────
$event = [
    'summary'     => $title,
    'location'    => $location,
    'description' => $desc,
    'start'       => ['dateTime' => $start, 'timeZone' => $tz],
    'end'         => ['dateTime' => $end,   'timeZone' => $tz],
    'reminders'   => [
        'useDefault' => false,
        'overrides'  => array_map(fn($m) => ['method' => 'email', 'minutes' => $m], $reminders)
            + array_map(fn($m) => ['method' => 'popup', 'minutes' => $m], $reminders),
    ],
];

// Add attendees — seller + organizer
$attendees = [['email' => GOOGLE_CALENDAR_EMAIL, 'organizer' => true]];
if ($attendee) $attendees[] = ['email' => $attendee];
$event['attendees'] = $attendees;

// ── Get valid access token (refresh if needed) ────────────────
$access_token = getAccessToken();
if (!$access_token) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not get Google Calendar access token. Please re-authorize.']);
    exit;
}

// ── Create event via Google Calendar API ─────────────────────
$calendarId = urlencode(GOOGLE_CALENDAR_EMAIL);
$url = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events?sendUpdates=all";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($event),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
    ],
]);
$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($status === 200 || $status === 201) {
    echo json_encode([
        'success'  => true,
        'eventId'  => $result['id'] ?? null,
        'htmlLink' => $result['htmlLink'] ?? null,
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Calendar API error: ' . ($result['error']['message'] ?? $response)]);
}

// ── Token management ──────────────────────────────────────────
function getAccessToken() {
    $tokenFile = __DIR__ . '/google_token.json';

    if (!file_exists($tokenFile)) return null;

    $token = json_decode(file_get_contents($tokenFile), true);
    if (!$token) return null;

    // Check if token is still valid (with 60 second buffer)
    if (isset($token['expires_at']) && time() < ($token['expires_at'] - 60)) {
        return $token['access_token'];
    }

    // Refresh the token
    if (!isset($token['refresh_token'])) return null;

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'refresh_token' => $token['refresh_token'],
            'grant_type'    => 'refresh_token',
        ]),
    ]);
    $res    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) return null;

    $newToken = json_decode($res, true);
    $newToken['refresh_token'] = $token['refresh_token']; // Keep refresh token
    $newToken['expires_at']    = time() + ($newToken['expires_in'] ?? 3600);

    file_put_contents($tokenFile, json_encode($newToken), LOCK_EX);

    return $newToken['access_token'];
}
