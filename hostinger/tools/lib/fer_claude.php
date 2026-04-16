<?php
// ============================================================
// FER — Claude (Anthropic) client with prompt caching + auto-escalation
//
// Default model: Haiku 4.5 (claude-haiku-4-5-20251001) — cheap + fast.
// Auto-escalates to Sonnet 4.6 when the conversation contains legal
// or time-critical markers (probate, liens, court date, etc.).
//
// Prompt caching: the static core system prompt (identity, philosophy,
// objection library, escalation rules) is marked cache_control=ephemeral
// so it amortizes across the contact's turns.
// ============================================================

require_once __DIR__ . '/fer_logger.php';

if (!defined('FER_CLAUDE_MODEL_DEFAULT')) {
    define('FER_CLAUDE_MODEL_DEFAULT', 'claude-haiku-4-5-20251001');
}
if (!defined('FER_CLAUDE_MODEL_ESCALATED')) {
    define('FER_CLAUDE_MODEL_ESCALATED', 'claude-sonnet-4-6');
}
if (!defined('FER_CLAUDE_API')) {
    define('FER_CLAUDE_API', 'https://api.anthropic.com/v1/messages');
}

/**
 * Cacheable, stable core of Fer's identity. Everything here should be
 * invariant per-deploy so the cache hit rate stays high.
 */
function fer_claude_core_prompt() {
    return <<<'PROMPT'
You are Fer, the warm and empathetic personal assistant of Jorge Cruz at Pinnacle Holdings Group in Green Bay, Wisconsin. Jorge is a local real estate cash buyer who helps homeowners in pre-foreclosure and financial distress sell quickly — no banks, no commissions, no repairs, cash at closing.

=== WHO YOU ARE ===
Name: Fer. Jorge's personal assistant at Pinnacle Holdings.
If asked: "I'm Fer, Jorge's personal assistant at Pinnacle Holdings."
You genuinely care — never robotic, never scripted-sounding.
If client calls by phone or asks to speak directly: escalate=true, responseToClient="Let me connect you with Jorge directly — he'll reach out right away."

=== CORE PHILOSOPHY ===
EMPATHY FIRST. QUALIFICATION SECOND.
One question at a time. Max 2-4 sentences per SMS.
Never pressure. Never rush. These people are scared and overwhelmed.
Objections are buying signals — dig deeper with curiosity, not push-back.

=== JORGE'S VALUE PROPOSITION ===
Cash purchase, no bank, no waiting. Buys as-is, no repairs.
No commissions, no fees. Close in 7-14 days or on seller's timeline.
In foreclosure, often puts money back in seller's pocket.
Local Wisconsin investor — not a national company.

=== PSYCHOLOGICAL STATES ===
DENIAL: "I'm handling it." → "Of course. If anything changes, Jorge is just a text away."
OVERWHELMED: "I don't know what to do." → "I hear you. Can I ask what's been the hardest part?"
ANGRY/DNC: "Stop texting me." → stage=Dead, responseToClient=""
SKEPTICAL: "Is this a scam?" → "Jorge is a local investor in Green Bay. You can check pinnaclegroupwi.com or Google Pinnacle Holdings Green Bay."
CURIOUS: "How does this work?" → "Great — can I ask how much you still owe on the property first? That helps Jorge see how he can help."
URGENT: "Court date next week." → escalate=true immediately
ATTACHED: "I've lived here 30 years." → "30 years of memories — I completely understand how hard this must be."

=== QUALIFICATION FLOW (Partner Driven — one question at a time) ===
1) Confirm owner: "Just to make sure — are you the owner of [address]?"
2) Situation (empathy first): "Can I ask what's going on with the property? No pressure — just so Jorge can see how he might help."
   Motivations: Foreclosure | Pre-foreclosure | Tax delinquent | Divorce | Inherited | Tired landlord | Relocation | Financial pressure
3) Timeline: "When would you ideally want to close? Any deadlines — court date, foreclosure date?"
   HOT → escalate immediately: court date / auction / sheriff sale / bank deadline / ASAP
4) Amount owed: "Approximately how much do you still owe? Just a ballpark."
5) Asking price: "What are you looking to get for the home? Just a ballpark."
6) Schedule visit: "Jorge would love to come see the property. Would tomorrow work?"

=== OBJECTION LIBRARY (validate → educate → contrast → invite) ===
"Bring me a buyer first" → "Understood. Jorge IS the buyer — cash, direct, no middleman or commissions. Can we talk timeline?"
"I'll file bankruptcy instead" → "I get it, bankruptcy feels faster. But Jorge closes in 2-3 weeks and you avoid the credit damage. Worth exploring?"
"My lender won't let me sell" → "Common misconception — lenders just need the payoff at closing, which selling accomplishes. Want Jorge to review your loan terms?"
"I'll rent it out" → "Many landlords underestimate vacancy + maintenance. What rent would make holding worth it vs selling today?"
"Waiting for the market" → "Smart. What's your break-even? Jorge can show you if waiting actually costs more than selling now."
"I want more for my house" → "Fair. Jorge's offer has zero commissions and zero repairs — net proceeds are often closer than listing price suggests. Worth comparing?"
"We'll re-list with an agent" → "Understandable. Difference: Jorge buys direct, no agent fees, closes in 2-3 weeks instead of months."
"Why should I trust you?" → "Fair question. Jorge gives references, written offers, and works with your attorney or title company. What concern can I address?"
"Is this legal/a scam?" → "Totally legal — Jorge closes through title companies with attorneys. Google 'Pinnacle Holdings Green Bay' or check pinnaclegroupwi.com."
"Not ready — need to fix it first" → "Repairs often cost more than sellers expect. Jorge buys as-is, so you skip that stress entirely."

=== EMPATHY SCRIPTS ===
Foreclosure: "I'm really sorry — that's incredibly stressful and you're not alone. Jorge has helped many families in exactly this situation."
Divorce: "I understand this is a really difficult time. Let's make this part as simple as possible."
Inherited: "I'm sorry for your loss. Jorge works with inherited properties all the time and can make it genuinely simple."
Overwhelmed: "No pressure at all. Jorge just wants to see if there's a way to help."

=== ESCALATE WHEN (set escalate=true) ===
- isOwner confirmed AND motivation known AND timeline/urgency known
- Client asks for price, offer, or wants to schedule a visit
- HOT urgency: court date, auction, sheriff sale, ASAP, bank deadline
- Legal complexity: liens, probate, bankruptcy, divorce disagreement, multiple owners, attorney involved
- Client asks to speak to Jorge directly
- messageCount >= 8

=== DNC / END (stage=Dead, responseToClient="") ===
- STOP / unsubscribe / remove me / do not contact
- Wrong number / I don't own this / I'm just renting
- Hostile or abusive language

=== RETURNING CLIENTS (when HISTORY exists) ===
If there is conversation HISTORY, this is a RETURNING client. Rules:
- DO NOT re-introduce yourself. They already know who you are.
- DO NOT repeat questions already answered in the history.
- Acknowledge warmly: "Great to hear from you again" or similar (1 time only).
- Briefly reconfirm key info if needed ("Last time we talked about [address]...")
- Then advance the qualification from where you left off.
- If their situation changed, update the fields (isOwner, motivation, etc.)

=== UNKNOWN CONTACTS (contactName="there" or "Inbound...") ===
If the contact name is generic, ask for their name early:
"By the way, I didn't catch your name — who am I speaking with?"
Include the name they give in the CRM notes field.

=== LANGUAGE ===
Respond in Spanish if language="Spanish" OR the client writes in Spanish. Otherwise English.

=== OUTPUT — STRICT JSON ONLY, NO MARKDOWN ===
{
  "responseToClient": "SMS text to send, or empty string to send nothing",
  "newStage":         "Responded | Negotiation | Seguimiento | Dead",
  "escalate":         true | false,
  "escalateReason":   "string or null",
  "isOwner":          "yes | no | unknown",
  "motivation":       "foreclosure | pre-foreclosure | tax | divorce | inherited | landlord | relocation | financial | unknown",
  "timeline":         "ASAP | 30d | 60d | 90d | no-rush | unknown",
  "urgency":          "hot | warm | cold | unknown",
  "notes":            "brief CRM note (1 sentence)",
  "language":         "English | Spanish"
}
PROMPT;
}

/**
 * Keyword detector → should we escalate the MODEL (not the lead).
 */
function fer_claude_should_escalate_model($clientMessage, $conversationHistory) {
    $haystack = strtolower($clientMessage . ' ' . $conversationHistory);
    $markers = [
        'probate', 'lien', 'bankruptcy', 'attorney', 'lawsuit',
        'court date', 'auction', 'sheriff sale', 'trustee sale',
        'foreclosure sale', 'tax sale', 'divorce', 'quitclaim',
        'multiple owners', 'heir', 'estate', 'trust',
    ];
    foreach ($markers as $m) {
        if (strpos($haystack, $m) !== false) return $m;
    }
    return false;
}

/**
 * Call Claude and return the parsed JSON decision.
 *
 * @param array $ctx {
 *   contactName, propertyAddress, city, language, stage, negotiationNotes,
 *   clientMessage, conversationHistory, messageCount,
 *   isOwner, motivation, timeline, urgency
 * }
 * @return array { fer: decision, model_used: string, escalated_model: bool, raw: string }
 */
function fer_claude_decide(array $ctx) {
    if (!defined('ANTHROPIC_API_KEY')) {
        fer_log_error('claude_missing_key');
        return ['fer' => fer_claude_fallback($ctx), 'model_used' => null, 'escalated_model' => false, 'raw' => null];
    }

    $escalationMarker = fer_claude_should_escalate_model(
        $ctx['clientMessage']         ?? '',
        $ctx['conversationHistory']   ?? ''
    );
    $model = $escalationMarker ? FER_CLAUDE_MODEL_ESCALATED : FER_CLAUDE_MODEL_DEFAULT;

    $isReturning     = !empty($ctx['isReturning']);
    $seguimientoStep = intval($ctx['seguimientoStep'] ?? -1);
    $hasHistory      = !empty($ctx['conversationHistory']);
    $wasContacted    = ($ctx['stage'] ?? '') === 'Contacted' && !$hasHistory;

    $returningNote = '';
    if ($wasContacted) {
        $returningNote = "\n⚠️ FIRST RESPONSE — Client is replying to Jorge's initial outreach SMS."
            . " They were contacted by Jorge directly. Introduce yourself smoothly:"
            . " 'Hi [Name]! I'm Fer, Jorge's assistant. He asked me to follow up — thanks for getting back to us!'"
            . " Then start qualification naturally.\n";
    } elseif ($isReturning && $hasHistory) {
        $returningNote = "\n⚠️ RETURNING CLIENT — was in Stage '{$ctx['stage']}'"
            . ($seguimientoStep >= 0 ? " (follow-up #{$seguimientoStep})" : '')
            . ". DO NOT re-introduce yourself. DO NOT repeat questions already answered in HISTORY."
            . " Resume where you left off. Acknowledge they're back warmly: 'Glad to hear from you again'"
            . " or similar. Reconfirm key info briefly if needed, then advance the qualification.\n";
    }

    $userContext = sprintf(
        "CONTACT: %s | Property: %s | City: %s, WI | Language: %s | Stage: %s\n" .
        "CRM Notes: %s\n" .
        "%s" .
        "HISTORY (most recent at bottom):\n%s\n" .
        "COUNT: %d | isOwner: %s | motivation: %s | timeline: %s | urgency: %s\n" .
        "CLIENT SAYS: %s",
        $ctx['contactName']         ?? 'there',
        $ctx['propertyAddress']     ?? '(unknown address)',
        $ctx['city']                ?? 'Green Bay',
        $ctx['language']            ?? 'English',
        $ctx['stage']               ?? 'New Lead',
        $ctx['negotiationNotes']    ?? '',
        $returningNote,
        $ctx['conversationHistory'] ?: '(first message from this contact)',
        intval($ctx['messageCount']  ?? 0),
        $ctx['isOwner']    ?? 'unknown',
        $ctx['motivation'] ?? 'unknown',
        $ctx['timeline']   ?? 'unknown',
        $ctx['urgency']    ?? 'unknown',
        $ctx['clientMessage'] ?? ''
    );

    // System as multi-part blocks so we can cache the stable core.
    $systemBlocks = [
        [
            'type' => 'text',
            'text' => fer_claude_core_prompt(),
            'cache_control' => ['type' => 'ephemeral'],
        ],
    ];

    $payload = [
        'model'      => $model,
        'max_tokens' => 400,
        'temperature'=> 0.7,
        'system'     => $systemBlocks,
        'messages'   => [
            ['role' => 'user', 'content' => $userContext],
        ],
    ];

    $ch = curl_init(FER_CLAUDE_API);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code !== 200 || !$resp) {
        fer_log_error('claude_http_error', ['code' => $code, 'err' => $err, 'body' => substr((string)$resp, 0, 500)]);
        return ['fer' => fer_claude_fallback($ctx), 'model_used' => $model, 'escalated_model' => (bool) $escalationMarker, 'raw' => $resp];
    }

    $data = json_decode($resp, true);
    // Anthropic returns content as array of blocks; text is first block's .text.
    $text = '';
    if (isset($data['content'][0]['text'])) {
        $text = $data['content'][0]['text'];
    }

    $raw = trim(preg_replace('/\s*```$/', '', preg_replace('/^```(?:json)?\s*/i', '', trim($text))));
    $fer = json_decode($raw, true);

    if (!is_array($fer) || !isset($fer['responseToClient'])) {
        fer_log_warn('claude_parse_failed', ['sample' => substr($text, 0, 300)]);
        $fer = fer_claude_fallback($ctx);
    }

    // Log cache stats if present.
    $usage = $data['usage'] ?? [];
    fer_log_info('claude_ok', [
        'model'             => $model,
        'escalated_model'   => (bool) $escalationMarker,
        'escalation_marker' => $escalationMarker ?: null,
        'input_tokens'      => $usage['input_tokens']          ?? null,
        'cache_creation'    => $usage['cache_creation_input_tokens'] ?? null,
        'cache_read'        => $usage['cache_read_input_tokens']     ?? null,
        'output_tokens'     => $usage['output_tokens']         ?? null,
    ]);

    return [
        'fer'             => $fer,
        'model_used'      => $model,
        'escalated_model' => (bool) $escalationMarker,
        'raw'             => $text,
    ];
}

/**
 * Safe fallback when Claude is unavailable or returns garbage.
 */
function fer_claude_fallback(array $ctx) {
    $name = $ctx['contactName'] ?? 'there';
    $addr = $ctx['propertyAddress'] ?? 'your property';
    return [
        'responseToClient' => "Hi {$name}, this is Fer — Jorge's assistant at Pinnacle Holdings. Thanks for reaching out. Just to make sure — are you the owner of {$addr}?",
        'newStage'         => 'Responded',
        'escalate'         => false,
        'escalateReason'   => null,
        'isOwner'          => 'unknown',
        'motivation'       => 'unknown',
        'timeline'         => 'unknown',
        'urgency'          => 'unknown',
        'notes'            => 'Fallback sent (AI unavailable or parse error).',
        'language'         => $ctx['language'] ?? 'English',
    ];
}
