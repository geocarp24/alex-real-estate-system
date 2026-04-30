<?php
// Deploy attempt 2: 2026-04-29T04:31Z
/**
 * github_dispatch.php — Trigger GitHub Actions workflow_dispatch from Hostinger.
 *
 * Auth: X-Alex-Secret header == ALEX_SECRET (defined in alex_config.php).
 * Body (JSON):
 *   workflow:  filename of workflow yml under .github/workflows (e.g. "supervisor-cron.yml")
 *   ref:       branch name (default: "master")
 *   inputs:    object — workflow_dispatch inputs (e.g. {"mode":"deep"})
 *   repo:      repo name (default: "alex-real-estate-system")
 */
$config = __DIR__ . '/alex_config.php';
if (file_exists($config)) require_once $config;

$token  = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : getenv('GITHUB_TOKEN');
$secret = defined('ALEX_SECRET')  ? ALEX_SECRET  : getenv('ALEX_SECRET');

if (!$token) {
    http_response_code(500);
    die(json_encode(['error' => 'GITHUB_TOKEN not configured']));
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

$body     = json_decode(file_get_contents('php://input'), true);
$workflow = $body['workflow'] ?? '';
$ref      = $body['ref']      ?? 'master';
$inputs   = $body['inputs']   ?? new stdClass();
$repo     = $body['repo']     ?? 'alex-real-estate-system';

if (!$workflow) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing workflow filename']));
}

$allowed_repos = ['alex-real-estate-system','pinnacle-agent-memory','geo-budget-pro','pinnacle-tools','geo-carpentry'];
if (!in_array($repo, $allowed_repos)) {
    http_response_code(403);
    die(json_encode(['error' => 'Repo not authorized']));
}

$allowed_workflows = ['supervisor-cron.yml','agents-cron.yml','deploy-hostinger.yml','deploy-vps-bot.yml'];
if (!in_array($workflow, $allowed_workflows)) {
    http_response_code(403);
    die(json_encode(['error' => 'Workflow not authorized']));
}

$url = "https://api.github.com/repos/geocarp24/{$repo}/actions/workflows/{$workflow}/dispatches";
$payload = json_encode(['ref' => $ref, 'inputs' => $inputs]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$token}",
    "User-Agent: ALEX-System-Pinnacle",
    "Accept: application/vnd.github+json",
    "X-GitHub-Api-Version: 2022-11-28",
    "Content-Type: application/json",
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
http_response_code($httpCode);
echo ($httpCode === 204 || $httpCode === 200)
    ? json_encode(['success' => true, 'workflow' => $workflow, 'ref' => $ref, 'inputs' => $inputs])
    : json_encode(['error' => 'GitHub dispatch failed', 'code' => $httpCode, 'response' => $response]);
