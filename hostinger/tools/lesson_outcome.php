<?php
/**
 * lesson_outcome.php — Record the outcome of a manually-applied fix to a Lessons_Learned row.
 *
 * Phase 3 of the auto-curative Supervisor needs attempted_fixes history to
 * compute confidence_score. After 3+ resolved outcomes, the lesson's
 * confidence crosses 0.9 and Phase 3 can apply the same fix automatically.
 *
 * POST /Tools/lesson_outcome.php
 *   Header: X-Alex-Secret: <ALEX_SECRET>
 *   Body (JSON):
 *     lesson_id        (string, required) — lesson_id from Lessons_Learned
 *     outcome          (string, required) — resolved | no_effect | worsened | pending
 *     action_category  (string, optional)
 *     notes            (string, optional)
 */
require_once __DIR__ . '/config.php';
// ALEX_SECRET lives in /agents/alex_config.php, not /Tools/config.php.
$alex_cfg = '/home/u433637438/domains/pinnaclegroupwi.com/public_html/agents/alex_config.php';
if (file_exists($alex_cfg)) require_once $alex_cfg;

if (!defined('AIRTABLE_TOKEN')) {
    http_response_code(500);
    die(json_encode(['error' => 'config: AIRTABLE_TOKEN missing']));
}
if (!defined('ALEX_SECRET')) {
    http_response_code(500);
    die(json_encode(['error' => 'config: ALEX_SECRET missing (alex_config.php not loaded)']));
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

$body = json_decode(file_get_contents('php://input'), true);
$lesson_id = $body['lesson_id'] ?? '';
$outcome   = $body['outcome']   ?? '';
$action    = $body['action_category'] ?? 'manual';
$notes     = $body['notes']     ?? '';

if (!$lesson_id || !$outcome) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing lesson_id or outcome']));
}
if (!in_array($outcome, ['resolved', 'no_effect', 'worsened', 'pending'])) {
    http_response_code(400);
    die(json_encode(['error' => 'outcome must be: resolved | no_effect | worsened | pending']));
}

$base = 'appfQbDA750Oihy9J';
$table = 'tbloCtdxSukBI3R3j';

$filter = urlencode("{lesson_id}='" . str_replace("'", "''", $lesson_id) . "'");
$url = "https://api.airtable.com/v0/{$base}/{$table}?filterByFormula={$filter}&maxRecords=1";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . AIRTABLE_TOKEN]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

$rec = $resp['records'][0] ?? null;
if (!$rec) {
    http_response_code(404);
    die(json_encode(['error' => "lesson_id not found: {$lesson_id}"]));
}

$current = $rec['fields'] ?? [];
$attempted = json_decode($current['attempted_fixes'] ?? '[]', true);
if (!is_array($attempted)) $attempted = [];

$attempted[] = [
    'run_id' => 'manual_' . date('Ymd_His'),
    'action_category' => $action,
    'action' => 'manual_operator_fix',
    'executed' => true,
    'outcome' => $outcome,
    'details' => $notes,
    'timestamp' => date('c'),
];
if (count($attempted) > 20) $attempted = array_slice($attempted, -20);

$record_id = $rec['id'];
$patch_url = "https://api.airtable.com/v0/{$base}/{$table}/{$record_id}";
$patch_body = json_encode([
    'fields' => [
        'attempted_fixes' => json_encode($attempted),
        'last_outcome'    => $outcome,
    ],
    'typecast' => true,
]);
$ch = curl_init($patch_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch, CURLOPT_POSTFIELDS, $patch_body);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . AIRTABLE_TOKEN,
    "Content-Type: application/json",
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$patch_resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
http_response_code($http === 200 ? 200 : 500);
echo json_encode([
    'success' => $http === 200,
    'lesson_id' => $lesson_id,
    'outcome' => $outcome,
    'attempted_fixes_count' => count($attempted),
    'message' => $http === 200
        ? "Outcome recorded. After 3+ resolved consecutive, Phase 3 will auto-apply this fix."
        : "Update failed: HTTP {$http}",
]);
