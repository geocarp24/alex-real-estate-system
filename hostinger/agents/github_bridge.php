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

$repo = $_GET['repo'] ?? '';
$file = $_GET['file'] ?? '';
if (!$repo || !$file) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing params']));
}

$allowed = ['alex-real-estate-system','pinnacle-agent-memory','geo-budget-pro','pinnacle-tools','geo-carpentry'];
if (!in_array($repo, $allowed)) {
    http_response_code(403);
    die(json_encode(['error' => 'Repo not authorized']));
}

$url = "https://api.github.com/repos/geocarp24/{$repo}/contents/{$file}";
$ch  = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$token}",
    "User-Agent: ALEX-System-Pinnacle",
    "Accept: application/vnd.github.v3.raw"
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
header('Content-Type: text/plain; charset=utf-8');
echo $response;
