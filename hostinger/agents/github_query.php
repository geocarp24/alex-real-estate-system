<?php
/**
 * github_query.php — Authenticated read-only proxy for GitHub REST API.
 * Uses GITHUB_TOKEN from alex_config.php. Restricted to allowlisted GET paths.
 *
 * Auth: X-Alex-Secret header == ALEX_SECRET.
 * Query string: ?path=<encoded-github-api-path>
 *
 * Allowlisted path prefixes (read-only, public/private repo metadata):
 *   /repos/geocarp24/<repo>/actions/runs
 *   /repos/geocarp24/<repo>/actions/runs/<id>
 *   /repos/geocarp24/<repo>/actions/runs/<id>/jobs
 *   /repos/geocarp24/<repo>/actions/jobs/<id>/logs
 *   /repos/geocarp24/<repo>/actions/workflows/<file>/runs
 *   /repos/geocarp24/<repo>/actions/workflows
 *   /repos/geocarp24/<repo>/commits
 *   /repos/geocarp24/<repo>/commits/<sha>
 */

$config = __DIR__ . '/alex_config.php';
if (file_exists($config)) require_once $config;

$token  = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : getenv('GITHUB_TOKEN');
$secret = defined('ALEX_SECRET')  ? ALEX_SECRET  : getenv('ALEX_SECRET');

if (!$token)  { http_response_code(500); die(json_encode(['error' => 'GITHUB_TOKEN not configured'])); }

$request_secret = $_SERVER['HTTP_X_ALEX_SECRET'] ?? '';
if ($request_secret !== $secret) { http_response_code(403); die(json_encode(['error' => 'Unauthorized'])); }

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); die(json_encode(['error' => 'Method not allowed — GET only']));
}

$path = $_GET['path'] ?? '';
if (!$path)            { http_response_code(400); die(json_encode(['error' => 'Missing ?path parameter'])); }
if ($path[0] !== '/')  $path = '/' . $path;

// Allowlist — only these path patterns are proxied. Locks down the proxy so it cannot be abused
// for repo writes, organization queries, deletes, etc.
$allowed_repos = ['alex-real-estate-system','pinnacle-agent-memory','geo-budget-pro','pinnacle-tools','geo-carpentry'];
$allowed = false;
foreach ($allowed_repos as $repo) {
    $patterns = [
        "#^/repos/geocarp24/{$repo}/actions/runs(\?|$)#",
        "#^/repos/geocarp24/{$repo}/actions/runs/[0-9]+(/jobs|/artifacts|/logs)?(\?|$)#",
        "#^/repos/geocarp24/{$repo}/actions/jobs/[0-9]+/logs(\?|$)#",
        "#^/repos/geocarp24/{$repo}/actions/artifacts/[0-9]+(/zip)?(\?|$)#",
        "#^/repos/geocarp24/{$repo}/actions/workflows(/[A-Za-z0-9._-]+(/runs)?)?(\?|$)#",
        "#^/repos/geocarp24/{$repo}/commits(/[A-Za-z0-9]+)?(\?|$)#",
    ];
    foreach ($patterns as $rx) {
        if (preg_match($rx, $path)) { $allowed = true; break 2; }
    }
}
if (!$allowed) { http_response_code(403); die(json_encode(['error' => 'Path not on allowlist', 'path' => $path])); }

$url = "https://api.github.com{$path}";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,  // GitHub redirects /logs and /zip to signed URLs
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$token}",
        "User-Agent: ALEX-System-Pinnacle",
        "Accept: application/vnd.github+json",
        "X-GitHub-Api-Version: 2022-11-28",
    ],
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
http_response_code($httpCode);
echo $response;
