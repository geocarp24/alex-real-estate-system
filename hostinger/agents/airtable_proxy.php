<?php
/**
 * airtable_proxy.php — Authenticated proxy for Airtable REST API.
 *
 * Auth (two factors):
 *   X-Alex-Secret: Pinnacle ALEX_SECRET (defined in alex_config.php)
 *   X-Airtable-Token: caller-provided Airtable PAT (so the token never
 *                     lives in the repo or on disk; it travels per-call)
 *
 * Allowed Airtable bases (so caller can't reach unrelated bases):
 *   appfQbDA750Oihy9J  (Pinnacle main — Contacts/Leads/Deals/Notes)
 *   appU9s3kGkVpdrJkw  (Social Media Ideas)
 *
 * Methods: GET (read), POST (create), PATCH (update). DELETE not exposed.
 *
 * Path: ?path=/v0/<baseId>/<tableId>[/<recordId>][?...]
 *
 * Examples:
 *   GET  ?path=/v0/appU9s3kGkVpdrJkw/tblAj0Pkj1jW4p5Ld?maxRecords=5
 *   POST ?path=/v0/appU9s3kGkVpdrJkw/tblAj0Pkj1jW4p5Ld   body={"fields":{...}}
 *   PATCH ?path=/v0/appU9s3kGkVpdrJkw/tblAj0Pkj1jW4p5Ld/rec123   body={"fields":{...}}
 */

$config = __DIR__ . '/alex_config.php';
if (file_exists($config)) require_once $config;

$secret = defined('ALEX_SECRET') ? ALEX_SECRET : getenv('ALEX_SECRET');
if (!$secret) { http_response_code(500); die(json_encode(['error' => 'ALEX_SECRET not configured'])); }

$req_secret = $_SERVER['HTTP_X_ALEX_SECRET'] ?? '';
if ($req_secret !== $secret) { http_response_code(403); die(json_encode(['error' => 'Unauthorized'])); }

$air_token = $_SERVER['HTTP_X_AIRTABLE_TOKEN'] ?? '';
if (!$air_token) { http_response_code(400); die(json_encode(['error' => 'Missing X-Airtable-Token header'])); }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST','PATCH'])) {
    http_response_code(405); die(json_encode(['error' => "Method {$method} not allowed"]));
}

$path = $_GET['path'] ?? '';
if (!$path)            { http_response_code(400); die(json_encode(['error' => 'Missing ?path parameter'])); }
if ($path[0] !== '/')  $path = '/' . $path;

// Allowlist: must start with /v0/<approved_base>/...
$allowed_bases = ['appfQbDA750Oihy9J','appU9s3kGkVpdrJkw'];
$base_ok = false;
foreach ($allowed_bases as $b) {
    if (strpos($path, "/v0/{$b}/") === 0 || strpos($path, "/v0/{$b}?") === 0) { $base_ok = true; break; }
}
if (!$base_ok) {
    http_response_code(403);
    die(json_encode(['error' => 'Base not on allowlist', 'path' => $path, 'allowed' => $allowed_bases]));
}

$url = "https://api.airtable.com{$path}";
$ch  = curl_init($url);
$opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer {$air_token}",
        "User-Agent: ALEX-System-Pinnacle",
        "Content-Type: application/json",
    ],
];

if ($method !== 'GET') {
    $body = file_get_contents('php://input');
    if ($body === '' || $body === false) $body = '{}';
    $opts[CURLOPT_CUSTOMREQUEST] = $method;
    $opts[CURLOPT_POSTFIELDS]    = $body;
}

curl_setopt_array($ch, $opts);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
http_response_code($code);
echo $resp;
