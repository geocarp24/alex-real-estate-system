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
$body      = json_decode(file_get_contents('php://input'), true);
$pdfB64    = $body['pdf_base64'] ?? '';
$mediaType = $body['media_type'] ?? 'application/pdf';

if (!$pdfB64) {
    http_response_code(400);
    echo json_encode(['error' => 'No PDF data received']);
    exit;
}

// ── Build Claude prompt ───────────────────────────────────────
$prompt = <<<'PROMPT'
You are a professional construction estimator in Wisconsin, USA (2026 pricing).
Analyze this architectural plan and generate a detailed quantity takeoff.

Return ONLY a valid JSON object — no markdown, no explanation, no code fences, just raw JSON:

{
  "projectName": "Project name from plan",
  "client": "Client name if visible, else empty string",
  "location": "City, WI",
  "projectInfo": {
    "totalSF": 2400,
    "garageSF": 576,
    "basementSF": 0,
    "stories": 2,
    "bedrooms": 4,
    "fullBaths": 2,
    "halfBaths": 1,
    "garageSpaces": 2,
    "roofPitch": "6:12",
    "foundationType": "Poured concrete full basement",
    "electricalOutlets": 42
  },
  "divisions": [
    {
      "num": "03",
      "name": "Concrete & Foundation",
      "items": [
        {
          "description": "Perimeter footings 16x8",
          "qty": 185,
          "unit": "LF",
          "matUnit": 14.50,
          "labUnit": 9.00
        },
        {
          "description": "8 inch poured concrete foundation walls",
          "qty": 1480,
          "unit": "SF",
          "matUnit": 18.00,
          "labUnit": 13.50
        }
      ]
    }
  ]
}

Rules:
- projectInfo: extract all specs from the plan. Use 0 or empty string if not visible.
- divisions: use nums 01-17, only include applicable divisions. Nums: 01=General Conditions and Permits, 02=Site Work and Excavation, 03=Concrete and Foundation, 04=Framing and Lumber, 05=Roofing, 06=Exterior Windows Doors and Siding, 07=Insulation, 08=Drywall, 09=Interior Millwork and Trim, 10=Cabinets and Countertops, 11=Flooring, 12=Plumbing, 13=HVAC, 14=Electrical, 15=Painting and Finishes, 16=Flatwork, 17=Cleanup
- Each item: description (specific), qty (measured from plan), unit (SF/LF/CY/SY/EA/BDL/BAG/TON/LS/SQ/ROLL/GAL), matUnit (material dollar per unit number), labUnit (labor dollar per unit number)
- Measure real quantities: LF for linear items, SF for areas, EA for counts, CY for volume
- Use real Wisconsin 2026 unit pricing
- qty, matUnit, labUnit must be numbers only — no dollar signs, no strings
- 3-6 line items per division
PROMPT;

// ── Call Claude API ───────────────────────────────────────────
$payload = [
    'model'      => 'claude-opus-4-6',
    'max_tokens' => 8192,
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
    CURLOPT_TIMEOUT => 180,
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

// Extract JSON — use greedy match to get the full object
preg_match('/\{[\s\S]*\}/', $text, $m);
$jsonStr = $m[0] ?? '{}';
$parsed  = json_decode($jsonStr, true);

if (!$parsed) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not parse Claude response', 'raw' => $text]);
    exit;
}

echo json_encode($parsed);
