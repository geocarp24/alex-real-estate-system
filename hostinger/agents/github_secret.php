<?php
/**
 * github_secret.php — Set/update GitHub Actions repository secrets.
 * Uses the GH_PAT stored in alex_config.php (admin scope to alex-real-estate-system).
 *
 * Auth: X-Alex-Secret header.
 * Body (JSON):
 *   name:  string — secret name (e.g. "DOPPLER_TOKEN")
 *   value: string — plaintext secret value (will be sodium-encrypted before sending)
 *   repo:  string — default "alex-real-estate-system"
 */
$config = __DIR__ . '/alex_config.php';
if (file_exists($config)) require_once $config;

$token  = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : getenv('GITHUB_TOKEN');
$secret = defined('ALEX_SECRET')  ? ALEX_SECRET  : getenv('ALEX_SECRET');
if (!$token) { http_response_code(500); die(json_encode(['error' => 'GITHUB_TOKEN not configured'])); }

$request_secret = $_SERVER['HTTP_X_ALEX_SECRET'] ?? '';
if ($request_secret !== $secret) { http_response_code(403); die(json_encode(['error' => 'Unauthorized'])); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); die(json_encode(['error' => 'Method not allowed']));
}

if (!function_exists('sodium_crypto_box_seal')) {
    http_response_code(500); die(json_encode(['error' => 'libsodium not available — PHP 7.2+ with sodium ext required']));
}

$body  = json_decode(file_get_contents('php://input'), true);
$name  = $body['name']  ?? '';
$value = $body['value'] ?? '';
$repo  = $body['repo']  ?? 'alex-real-estate-system';

if (!$name || $value === '') { http_response_code(400); die(json_encode(['error' => 'Missing name or value'])); }

$allowed_repos = ['alex-real-estate-system','pinnacle-agent-memory','geo-budget-pro','pinnacle-tools','geo-carpentry'];
if (!in_array($repo, $allowed_repos)) { http_response_code(403); die(json_encode(['error' => 'Repo not authorized'])); }

// Validate name format (uppercase letters, digits, underscores).
if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
    http_response_code(400); die(json_encode(['error' => 'Secret name must match ^[A-Z][A-Z0-9_]*$']));
}

$ghHeaders = [
    "Authorization: Bearer {$token}",
    "User-Agent: ALEX-System-Pinnacle",
    "Accept: application/vnd.github+json",
    "X-GitHub-Api-Version: 2022-11-28",
];

// 1. Fetch repo's public key for sealing.
$ch = curl_init("https://api.github.com/repos/geocarp24/{$repo}/actions/secrets/public-key");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $ghHeaders);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$pkResp  = curl_exec($ch);
$pkCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($pkCode !== 200) {
    http_response_code(500);
    die(json_encode(['error' => "public-key fetch failed HTTP {$pkCode}", 'response' => substr($pkResp, 0, 200)]));
}
$pk = json_decode($pkResp, true);
$publicKey = $pk['key'] ?? '';
$keyId     = $pk['key_id'] ?? '';
if (!$publicKey || !$keyId) {
    http_response_code(500); die(json_encode(['error' => 'public_key missing in response']));
}

// 2. Encrypt the value with libsodium sealed box.
$decodedKey = base64_decode($publicKey);
$encrypted  = sodium_crypto_box_seal($value, $decodedKey);
$encryptedBase64 = base64_encode($encrypted);

// 3. PUT the secret.
$payload = json_encode(['encrypted_value' => $encryptedBase64, 'key_id' => $keyId]);
$ch = curl_init("https://api.github.com/repos/geocarp24/{$repo}/actions/secrets/{$name}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($ghHeaders, ["Content-Type: application/json"]));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$putResp = curl_exec($ch);
$putCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
http_response_code($putCode === 201 || $putCode === 204 ? 200 : 500);
echo json_encode([
    'success' => $putCode === 201 || $putCode === 204,
    'name'    => $name,
    'repo'    => $repo,
    'http'    => $putCode,
    'message' => $putCode === 201 ? 'created' : ($putCode === 204 ? 'updated' : 'failed: ' . substr($putResp, 0, 150)),
]);
