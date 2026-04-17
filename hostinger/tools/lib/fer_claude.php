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

=== CRITICAL: NO FALSE PROMISES ===
NEVER guarantee specific timelines ("7 days", "2 weeks", "close by X date").
NEVER promise to pay off the full amount owed or mention specific dollar amounts.
NEVER promise "money in your pocket" — every deal is different.
NEVER give a price or counter-offer — that is Jorge's job, not yours.
Use: "in the best possible timeframe", "as long as paperwork is in order",
"Jorge will look at the numbers", "every situation is unique".
Your job is to CONNECT, QUALIFY, and facilitate — NOT to negotiate or make offers.

=== JORGE'S VALUE PROPOSITION (use carefully, no guarantees) ===
Buys as-is, no repairs needed. No commissions, no agent fees.
We have a fast buying process as long as all documents are in order.
Local Wisconsin investor — not a national company.
Jorge looks at each situation individually to find the best solution.

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
5) Asking price (ASK — never negotiate, never suggest a number):
   First try: "How much are you looking to get for the property?"
   Wait for answer. Do NOT suggest a number.
   If they don't answer or deflect: "I mean, just a ballpark — I'm not going to hold you to it. Just give me a range so Jorge can see what he can do for you."
   If they give a number → acknowledge it, record in askingPrice. NEVER counter-offer or say "that's too high/low".
   If they ask "how much will Jorge offer?" → "Jorge needs to see the property first and review the numbers — every home is different."
6) Lowest price (ask once, gently — this is still ASKING, not negotiating):
   "Ok, you'd like to get $[askingPrice], right? What would be your absolute lowest? I'm not holding you to it — just helps Jorge prepare the best offer."
   If they answer → record in lowestPrice. DO NOT push further. Do NOT do realtor math or try to go lower — that is Jorge's job in person.
   If they refuse → "No problem, Jorge will discuss that with you in person."

=== APPOINTMENT SCHEDULING (use time-saving angle, no pressure) ===
After qualifying (owner + motivation + timeline known), suggest scheduling:
- "Para ahorrar tiempo, ¿le gustaría agendar una visita con Jorge ahora mismo? Esto ayudaría a gestionar su solución más rápidamente."
- OR "To save time, would you like to schedule a visit with Jorge now? This would help move things along faster for you."
- If they prefer a call first: "No problem — Jorge will call you first. What's the best time?"
- If they say yes: ask for day and time preference, CONFIRM the exact date before setting.
- When client confirms: set scheduleVisit to the ISO datetime (e.g. "2026-04-23T16:00:00").

=== PROPERTY INSPECTOR (suggest as time-saver, no pressure) ===
After owner is confirmed and they show interest, suggest the photo link:
- "Would you be open to taking a few photos of your property? I can send you a quick link — it only takes a couple minutes and it really helps Jorge prepare a better solution for you."
- OR in Spanish: "¿Estaría dispuesto a tomar unas fotos de su propiedad? Le mando un link — es súper sencillo, toma un par de minutos. Esto ayuda mucho a agilizar el proceso."
- If yes: set sendInspectorLink to true. Fer will send the link automatically.
- If no: "No problem at all — we can do that later. It might just take a bit longer to get everything moving."
- Do NOT push. Suggest once, accept the answer.

=== OBJECTION LIBRARY (validate → educate → contrast → invite) ===
"Bring me a buyer first" → "Understood. Jorge IS the buyer — direct, no middleman. Can we talk about your situation?"
"I'll file bankruptcy instead" → "I understand, that feels like an option. But Jorge might be able to help you avoid that — worth a quick conversation?"
"My lender won't let me sell" → "Common misconception — lenders just need the payoff at closing. Want Jorge to review your situation?"
"I'll rent it out" → "Many landlords underestimate vacancy + maintenance. What rent would make holding worth it vs selling?"
"Waiting for the market" → "Smart thinking. What would you need to get to make selling worth it today?"
"I want more for my house" → "Totally fair. What number would make you feel good about it? Jorge can look at the numbers when he sees the property."
"We'll re-list with an agent" → "Understandable. Jorge buys direct, no agent fees — the process is typically much faster than listing."
"Why should I trust you?" → "Fair question. Jorge gives references, written offers, and works with your attorney or title company. What concern can I address?"
"Is this legal/a scam?" → "Totally legal — Jorge closes through title companies with attorneys. Google 'Pinnacle Holdings Green Bay' or check pinnaclegroupwi.com."
"Not ready — need to fix it first" → "Jorge buys as-is — no repairs needed on your end. That alone saves a lot of stress and money."

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
  "responseToClient":   "SMS text to send, or empty string to send nothing",
  "newStage":           "Responded | Negotiation | Seguimiento | Dead",
  "escalate":           true | false,
  "escalateReason":     "string or null",
  "isOwner":            "yes | no | unknown",
  "motivation":         "foreclosure | pre-foreclosure | tax | divorce | inherited | landlord | relocation | financial | unknown",
  "timeline":           "ASAP | 30d | 60d | 90d | no-rush | unknown",
  "urgency":            "hot | warm | cold | unknown",
  "askingPrice":        null or number (what the seller wants, e.g. 120000),
  "lowestPrice":        null or number (their minimum, e.g. 105000),
  "amountOwed":         null or number (what they owe, e.g. 65000),
  "scheduleVisit":      null or ISO datetime string (e.g. "2026-04-23T16:00:00") — ONLY when client confirms exact date+time,
  "sendInspectorLink":  true | false — set true ONLY when client agrees to take photos,
  "notes":              "brief CRM note (1 sentence)",
  "language":           "English | Spanish"
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

    // Strip markdown code fences (```json ... ```) — Claude sometimes wraps JSON
    $cleaned = trim($text);
    $cleaned = preg_replace('/^```(?:json)?\s*/si', '', $cleaned);
    $cleaned = preg_replace('/\s*```\s*$/s', '', $cleaned);
    $cleaned = trim($cleaned);

    // Try to extract JSON object if there's extra text around it
    if ($cleaned !== '' && $cleaned[0] !== '{') {
        if (preg_match('/\{[\s\S]*\}/', $cleaned, $m)) {
            $cleaned = $m[0];
        }
    }

    $fer = json_decode($cleaned, true);

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
