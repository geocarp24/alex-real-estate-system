<?php
/**
 * dispatch_workflow.php — Trigger GitHub Actions workflow_dispatch from Hostinger.
 * Lives in /Tools/ because /agents/ path may have deploy issue.
 *
 * Auth: X-Alex-Secret header == ALEX_SECRET (from /Tools/config.php).
 */
require_once __DIR__ . '/config.php';

if (!defined('ALEX_SECRET') || !defined('ANTHROPIC_API_KEY')) {
    http_response_code(500);
    die(json_encode(['error' => 'config: ALEX_SECRET or ANTHROPIC_API_KEY missing']));
}

// Tools/config.php holds AIRTABLE_TOKEN, etc. but GH_PAT lives in /agents/alex_config.php.
$gh_config = '/home/u433637438/domains/pinnaclegroupwi.com/public_html/agents/alex_config.php';
if (file_exists($gh_config)) require_once $gh_config;

$token  = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : getenv('GITHUB_TOKEN');
if (!$token) {
    http_response_code(500);
    die(json_encode(['error' => 'GITHUB_TOKEN not configured (alex_config.php missing or empty)']));
}

$request_secret = $_SERVER['HTTP_X_ALEX_SECRET'] ?? '';
if ($request_secret !== ALEX_SECRET) {
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

$allowed_repos     = ['alex-real-estate-system','pinnacle-agent-memory','geo-budget-pro','pinnacle-tools','geo-carpentry'];
$allowed_workflows = ['supervisor-cron.yml','agents-cron.yml','deploy-hostinger.yml','deploy-vps-bot.yml'];
if (!in_array($repo, $allowed_repos))         { http_response_code(403); die(json_encode(['error' => 'Repo not authorized'])); }
if (!in_array($workflow, $allowed_workflows)) { http_response_code(403); die(json_encode(['error' => 'Workflow not authorized'])); }

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
    ? json_encode(['success' => true, 'workflow' => $workflow, 'ref' => $ref])
    : json_encode(['error' => 'GitHub dispatch failed', 'code' => $httpCode, 'response' => $response]);
