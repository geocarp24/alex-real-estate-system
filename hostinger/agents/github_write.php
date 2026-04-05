<?php
$config = __DIR__ . '/alex_config.php';
if (file_exists($config)) require_once $config;

$token  = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : getenv('GITHUB_TOKEN');
$secret = defined('ALEX_SECRET')  ? ALEX_SECRET  : getenv('ALEX_SECRET');

if (!$token) {
    http_response_code(500);
    die(json_encode(['error' => 'Token not configured']));
}

$request_secret = $_SERVER['HTTP_X_ALEX_SECRET'] ?? '';
if ($request_secret !== $secret) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$body    = json_decode(file_get_contents('php://input'), true);
$repo    = $body['repo']    ?? '';
$file    = $body['file']    ?? '';
$content = $body['content'] ?? '';
$message = $body['message'] ?? 'ALEX memory update';

if (!$repo || !$file || $content === '') {
    http_response_code(400);
    die(json_encode(['error' => 'Missing params']));
}

$allowed = ['alex-real-estate-system','pinnacle-agent-memory','geo-budget-pro','pinnacle-tools','geo-carpentry'];
if (!in_array($repo, $allowed)) {
    http_response_code(403);
    die(json_encode(['error' => 'Repo not authorized']));
}

$url     = "https://api.github.com/repos/geocarp24/{$repo}/contents/{$file}";
$headers = [
    "Authorization: Bearer {$token}",
    "User-Agent: ALEX-System-Pinnacle",
    "Accept: application/vnd.github.v3+json",
    "Content-Type: application/json"
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$existing = json_decode(curl_exec($ch), true);
curl_close($ch);
$sha = $existing['sha'] ?? null;

$put = json_encode(['message' => $message, 'content' => base64_encode($content), 'sha' => $sha]);
$ch  = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, $put);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$result   = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
http_response_code($httpCode);
echo ($httpCode === 200 || $httpCode === 201)
    ? json_encode(['success' => true])
    : json_encode(['error' => 'GitHub write error', 'code' => $httpCode]);
