<?php
// ============================================================
//  PINNACLE — google_auth.php
//  Run this ONCE to authorize Google Calendar access
//  URL: pinnaclegroupwi.com/Tools/google_auth.php
//  DELETE this file after authorization is complete!
// ============================================================

require_once __DIR__ . '/config.php';

$redirect_uri = 'https://pinnaclegroupwi.com/Tools/google_auth.php';
$scope        = 'https://www.googleapis.com/auth/calendar.events';

// Step 2: Handle callback with code
if (isset($_GET['code'])) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $_GET['code'],
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ]),
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($response['access_token'])) {
        $response['expires_at'] = time() + ($response['expires_in'] ?? 3600);
        file_put_contents(__DIR__ . '/google_token.json', json_encode($response), LOCK_EX);
        echo '<h2 style="color:green;font-family:Arial">✅ Google Calendar authorized successfully!</h2>';
        echo '<p style="font-family:Arial">Token saved. <strong>Delete this file (google_auth.php) from your server now for security.</strong></p>';
    } else {
        echo '<h2 style="color:red;font-family:Arial">❌ Error</h2>';
        echo '<pre>'.json_encode($response, JSON_PRETTY_PRINT).'</pre>';
    }
    exit;
}

// Step 1: Redirect to Google OAuth
$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => $scope,
    'access_type'   => 'offline',
    'prompt'        => 'consent',
    'login_hint'    => GOOGLE_CALENDAR_EMAIL,
]);

echo '<!DOCTYPE html><html><body style="font-family:Arial;padding:40px">';
echo '<h2>Pinnacle — Google Calendar Authorization</h2>';
echo '<p>This will authorize <strong>admin@geocarpentry.com</strong> to create calendar events.</p>';
echo '<a href="'.htmlspecialchars($auth_url).'" style="background:#085041;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-size:16px;font-weight:bold">Authorize Google Calendar →</a>';
echo '</body></html>';
