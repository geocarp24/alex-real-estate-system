<?php
/**
 * analyze.php — Geo Carpentry Budget Builder
 * Proxies Claude API for PDF plan analysis.
 * Keeps ANTHROPIC_KEY server-side (never exposed to browser).
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Budget-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

// ── Auth ──────────────────────────────────────────────────────
$token = $_SERVER['HTTP_X_BUDGET_TOKEN'] ?? '';
if ($token !== APP_TOKEN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Get PDF ───────────────────────────────────────────────────
$body    = json_decode(file_get_contents('php://input'), true);
$pdfB64  = $body['pdf_base64'] ?? '';
$mediaType = $body['media_type'] ?? 'application/pdf';

if (!$pdfB64) {
    http_response_code(400);
    echo json_encode(['error' => 'No PDF data received']);
    exit;
}

// ── Build Claude prompt ───────────────────────────────────────
$prompt = <<<PROMPT
You are a professional construction estimator in Wisconsin, USA (2026 pricing).
Analyze this architectural plan carefully and generate a detailed construction budget.

Return ONLY a valid JSON object — no markdown, no explanation, just raw JSON:

{
  "projectName": "Project name derived from the plan",
  "client": "Client name if visible on plan, otherwise empty string",
  "location": "City, WI",
  "divisions": [
    {
      "num": "01",
      "name": "Site Work & Excavation",
      "items": "Line item 1\nLine item 2\nLine item 3",
      "mat": 12000,
      "lab": 10000
    }
  ]
}

Rules:
- Use these CSI division numbers: 01 through 17 (or as many as apply)
- Common divisions: Site Work, Concrete & Foundation, Framing, Roofing, Windows & Doors, Exterior Finishes, Insulation, Drywall, Interior Carpentry, Cabinets, Flooring, Plumbing, HVAC, Electrical, Painting, Flatwork, Permits & General Conditions
- Use realistic Wisconsin 2026 material and labor costs
- "items" field: 3-5 specific scope items separated by \n
- "mat" and "lab" must be integers (no decimals, no dollar signs)
- Be specific and accurate based on what you see in the plan
PROMPT;

// ── Call Claude API ───────────────────────────────────────────
$payload = [
    'model'      => 'claude-opus-4-6',
    'max_tokens' => 4096,
    'messages'   => [[
        'role'    => 'user',
        'content' => [
            [
                'type'   => 'document',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $mediaType,
                    'data'       => $pdfB64,
                ],
            ],
            [
                'type' => 'text',
                'text' => $prompt,
            ],
        ],
    ]],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'x-api-key: '          . ANTHROPIC_KEY,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
    ],
    CURLOPT_TIMEOUT => 120,
]);

$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    http_response_code(500);
    echo json_encode(['error' => 'Claude API error', 'detail' => $raw]);
    exit;
}

$data = json_decode($raw, true);
$text = $data['content'][0]['text'] ?? '';

// Extract JSON from Claude response
preg_match('/\{[\s\S]*\}/U', $text, $m);
$jsonStr = $m[0] ?? '{}';
// Validate it's real JSON
$parsed = json_decode($jsonStr, true);
if (!$parsed) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not parse Claude response', 'raw' => $text]);
    exit;
}

echo json_encode($parsed);
