<?php
// ============================================================
//  PINNACLE — calendar_events.php
//  Returns upcoming events from admin@geocarpentry.com
//  for display in the appointment picker
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

$days = intval($_GET['days'] ?? 7);
$now  = date('c');
$end  = date('c', strtotime("+{$days} days"));

$access_token = getAccessToken();
if (!$access_token) {
    echo json_encode(['error' => 'Calendar not authorized']);
    exit;
}

$calendarId = urlencode(GOOGLE_CALENDAR_EMAIL);
$url = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events?" . http_build_query([
    'timeMin'      => $now,
    'timeMax'      => $end,
    'singleEvents' => 'true',
    'orderBy'      => 'startTime',
    'maxResults'   => 20,
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $access_token],
]);
$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200) {
    echo json_encode(['error' => 'Failed to fetch events', 'status' => $status]);
    exit;
}

$data   = json_decode($response, true);
$events = [];

foreach (($data['items'] ?? []) as $event) {
    $start = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;
    $end   = $event['end']['dateTime']   ?? $event['end']['date']   ?? null;
    if (!$start) continue;

    $startTs = strtotime($start);
    $endTs   = strtotime($end);

    $events[] = [
        'title' => $event['summary'] ?? 'Busy',
        'start' => $start,
        'end'   => $end,
        'startFormatted' => date('D M j, g:i A', $startTs),
        'endFormatted'   => date('g:i A', $endTs),
    ];
}

echo json_encode(['events' => $events, 'calendarId' => GOOGLE_CALENDAR_EMAIL]);

function getAccessToken() {
    $tokenFile = __DIR__ . '/google_token.json';
    if (!file_exists($tokenFile)) return null;
    $token = json_decode(file_get_contents($tokenFile), true);
    if (!$token) return null;
    if (isset($token['expires_at']) && time() < ($token['expires_at'] - 60)) return $token['access_token'];
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
    $newToken['refresh_token'] = $token['refresh_token'];
    $newToken['expires_at']    = time() + ($newToken['expires_in'] ?? 3600);
    file_put_contents($tokenFile, json_encode($newToken), LOCK_EX);
    return $newToken['access_token'];
}
