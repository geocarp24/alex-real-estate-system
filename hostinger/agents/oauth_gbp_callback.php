<?php
/**
 * Google OAuth callback for El Cartógrafo (GBP write-side agent).
 *
 * Google Cloud Console → OAuth Client → Authorized redirect URIs must include:
 *    https://pinnaclegroupwi.com/oauth/gbp_callback.php
 *
 * Google redirects here after the user approves scopes. We display the code
 * in a minimal HTML page so Jorge can copy-paste it back to ALEX via chat.
 *
 * Security notes:
 * - The `code` is a one-time value valid for ~10 min; useless without client_secret.
 * - `state` parameter is validated only for format (not strict CSRF) — low risk
 *   since this is a 1-person admin flow.
 * - Nothing is written to disk; we do NOT store the code server-side.
 */

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$code  = isset($_GET['code'])  ? (string) $_GET['code']  : '';
$state = isset($_GET['state']) ? (string) $_GET['state'] : '';
$err   = isset($_GET['error']) ? (string) $_GET['error'] : '';

// Basic sanity filters
$code_safe  = preg_replace('/[^A-Za-z0-9._\-\/]+/', '', $code);
$state_safe = preg_replace('/[^A-Za-z0-9._\-]+/',   '', $state);
$err_safe   = htmlspecialchars($err, ENT_QUOTES, 'UTF-8');

$okStyle = "background:#0D3B2E;color:#fff;";
$errStyle= "background:#D32F2F;color:#fff;";
$gold    = "#C9A84C";

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pinnacle OAuth — GBP</title>';
echo '<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0;padding:24px;background:#F5F0E8;color:#0D3B2E;}
.card{max-width:640px;margin:0 auto;background:#fff;border-radius:14px;padding:28px;box-shadow:0 8px 24px rgba(0,0,0,.08);border:2px solid #E5DFD4;}
h1{font-size:22px;margin:0 0 18px;letter-spacing:-0.01em;}
.badge{display:inline-block;padding:8px 14px;border-radius:999px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;font-size:12px;}
.code-box{background:#0D3B2E;color:#C9A84C;font-family:Menlo,Consolas,monospace;font-size:15px;padding:16px;border-radius:10px;word-break:break-all;margin:16px 0;user-select:all;-webkit-user-select:all;}
.btn{background:#C9A84C;color:#0D3B2E;padding:12px 18px;border-radius:10px;border:0;font-weight:800;font-size:14px;display:inline-block;cursor:pointer;}
.muted{color:#6B6455;font-size:13px;line-height:1.5;}
</style></head><body><div class="card">';

if ($err_safe) {
    echo '<span class="badge" style="' . $errStyle . '">Error</span>';
    echo '<h1>OAuth failed</h1>';
    echo '<p class="muted">Google returned error: <code>' . $err_safe . '</code></p>';
    echo '<p class="muted">Check the <strong>Authorized redirect URIs</strong> in Google Cloud Console. Must include exactly:</p>';
    echo '<div class="code-box">https://pinnaclegroupwi.com/oauth/gbp_callback.php</div>';
} elseif ($code_safe) {
    echo '<span class="badge" style="' . $okStyle . '">Success</span>';
    echo '<h1>Paste this code back to ALEX</h1>';
    echo '<p class="muted">Copy the block below and paste it in your chat with ALEX. The code expires in ~10 minutes and can only be used once.</p>';
    echo '<div class="code-box" id="code">' . htmlspecialchars($code_safe, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<button class="btn" onclick="navigator.clipboard.writeText(document.getElementById(\'code\').innerText);this.textContent=\'Copied ✓\';">Copy to clipboard</button>';
    if ($state_safe) {
        echo '<p class="muted" style="margin-top:24px;">State: <code>' . htmlspecialchars($state_safe, ENT_QUOTES, 'UTF-8') . '</code></p>';
    }
} else {
    echo '<span class="badge" style="' . $errStyle . '">Invalid</span>';
    echo '<h1>Missing code parameter</h1>';
    echo '<p class="muted">This endpoint only works when Google redirects here after OAuth consent. Accessing it directly has no effect.</p>';
}

echo '</div></body></html>';
