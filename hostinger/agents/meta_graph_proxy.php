<?php
/**
 * meta_graph_proxy.php — Authenticated proxy for Meta Graph API (Facebook + Instagram).
 *
 * Auth (two factors):
 *   X-Alex-Secret:  Pinnacle ALEX_SECRET (alex_config.php)
 *   X-Meta-Token:   caller-provided Page Access Token (lives per-call, never on disk)
 *
 * Allowed hosts: graph.facebook.com, graph-video.facebook.com (uploads).
 *
 * Methods: GET (read), POST (publish), DELETE (rare).
 *
 * Path: ?path=/<api-version>/<endpoint>?fields=...
 *
 * Examples:
 *   GET  ?path=/v21.0/me?fields=id,name,instagram_business_account
 *   GET  ?path=/v21.0/{page-id}?fields=id,name,fan_count,instagram_business_account
 *   POST ?path=/v21.0/{ig-user-id}/media   body={"media_type":"REELS","video_url":"...","caption":"..."}
 *   POST ?path=/v21.0/{page-id}/video_reels?upload_phase=start
 */

$config = __DIR__ . '/alex_config.php';
if (file_exists($config)) require_once $config;

$secret = defined('ALEX_SECRET') ? ALEX_SECRET : getenv('ALEX_SECRET');
if (!$secret) { http_response_code(500); die(json_encode(['error' => 'ALEX_SECRET not configured'])); }

$req_secret = $_SERVER['HTTP_X_ALEX_SECRET'] ?? '';
if ($req_secret !== $secret) { http_response_code(403); die(json_encode(['error' => 'Unauthorized'])); }

$meta_token = $_SERVER['HTTP_X_META_TOKEN'] ?? '';
if (!$meta_token) { http_response_code(400); die(json_encode(['error' => 'Missing X-Meta-Token header'])); }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST','DELETE'])) {
    http_response_code(405); die(json_encode(['error' => "Method {$method} not allowed"]));
}

$path = $_GET['path'] ?? '';
if (!$path || !preg_match('#^/v[0-9]+\.[0-9]+/#', $path)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid or missing path. Expected /v21.0/<endpoint>']));
}

// Optional override of host for upload endpoints (rupload.facebook.com for video upload phase).
$allowed_hosts = ['graph.facebook.com', 'graph-video.facebook.com', 'rupload.facebook.com'];
$host = $_GET['host'] ?? 'graph.facebook.com';
if (!in_array($host, $allowed_hosts, true)) {
    http_response_code(400);
    die(json_encode(['error' => "Host {$host} not allowed. Use one of: " . implode(',', $allowed_hosts)]));
}

$url = "https://{$host}{$path}";

// Append token to URL via query string (Meta's standard auth method, also supports Authorization header for upload).
$url .= (strpos($path, '?') !== false ? '&' : '?') . 'access_token=' . urlencode($meta_token);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CUSTOMREQUEST  => $method,
]);

$headers = ['Accept: application/json'];

if ($method === 'POST') {
    $body = file_get_contents('php://input') ?: '';
    if ($body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $headers[] = 'Content-Type: application/json';
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, ''); // empty body for upload-phase calls
    }
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err      = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    die(json_encode(['error' => 'curl failed', 'detail' => $err]));
}

http_response_code($status ?: 200);
header('Content-Type: application/json');
echo $response;
