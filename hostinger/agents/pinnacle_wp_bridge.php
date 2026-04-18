<?php
/**
 * pinnacle_wp_bridge.php — debug build
 */

declare(strict_types=1);

$config = __DIR__ . '/alex_config.php';
if (file_exists($config)) {
    require_once $config;
}

$secret = defined('ALEX_SECRET') ? ALEX_SECRET : (string) getenv('ALEX_SECRET');
if ($secret === '') {
    http_response_code(500);
    echo json_encode(['error' => 'bridge not configured (missing ALEX_SECRET)']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed; use POST']);
    exit;
}

$received_secret = (string) ($_SERVER['HTTP_X_ALEX_SECRET'] ?? '');
$auth_ok         = hash_equals($secret, $received_secret);
$auth_method     = $auth_ok ? 'x-alex-secret' : '';

if (!$auth_ok) {
    $authz = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($authz, 'Basic ') === 0) {
        $decoded = base64_decode(substr($authz, 6));
        if ($decoded !== false && strpos($decoded, ':') !== false) {
            [$u, $p] = explode(':', $decoded, 2);
            $pending_basic_user = $u;
            $pending_basic_pass = $p;
        }
    }
    if (!isset($pending_basic_user)) {
        http_response_code(403);
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }
}

$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip = preg_replace('/[^a-zA-Z0-9:.]/', '', explode(',', $ip)[0]);

$wp_load_candidates = [
    dirname(__DIR__) . '/wp-load.php',
    '/home/u433637438/domains/pinnaclegroupwi.com/public_html/wp-load.php',
];
$wp_loaded = false;
foreach ($wp_load_candidates as $cand) {
    if (file_exists($cand)) {
        require_once $cand;
        $wp_loaded = true;
        break;
    }
}
if (!$wp_loaded) {
    http_response_code(500);
    echo json_encode(['error' => 'WordPress bootstrap failed', 'tried' => $wp_load_candidates]);
    exit;
}

$auth_debug = [];
if (!$auth_ok && isset($pending_basic_user)) {
    $auth_debug['user_input']     = $pending_basic_user;
    $auth_debug['pass_len']       = strlen($pending_basic_pass);
    $auth_debug['got_email']      = (bool) get_user_by('email', $pending_basic_user);
    $auth_debug['got_login']      = (bool) get_user_by('login', $pending_basic_user);
    $auth_debug['class_exists']   = class_exists('WP_Application_Passwords');
    $auth_debug['wp_check_fn']    = function_exists('wp_check_password');

    $user = get_user_by('email', $pending_basic_user);
    if (!$user) {
        $user = get_user_by('login', $pending_basic_user);
    }
    if ($user instanceof WP_User) {
        $auth_debug['user_id']        = $user->ID;
        $auth_debug['user_login']     = $user->user_login;
        $auth_debug['manage_options'] = user_can($user, 'manage_options');
        if (class_exists('WP_Application_Passwords')) {
            $cleaned_pass = str_replace(' ', '', $pending_basic_pass);
            $hashed_passwords = WP_Application_Passwords::get_user_application_passwords($user->ID);
            $auth_debug['app_pass_count'] = is_array($hashed_passwords) ? count($hashed_passwords) : 0;
            $auth_debug['checks'] = [];
            if (is_array($hashed_passwords)) {
                foreach ($hashed_passwords as $idx => $item) {
                    $matched = wp_check_password($cleaned_pass, $item['password'], $user->ID);
                    $auth_debug['checks'][] = ['idx' => $idx, 'name' => $item['name'] ?? '?', 'matched' => $matched];
                    if ($matched && user_can($user, 'manage_options')) {
                        wp_set_current_user($user->ID);
                        $auth_ok     = true;
                        $auth_method = 'app-password';
                        break;
                    }
                }
            }
        }
    }
}
if (!$auth_ok) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized', 'hint' => 'check debug', 'debug' => $auth_debug]);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode((string) $raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid JSON body']);
    exit;
}
$action = (string) ($body['action'] ?? '');
$request_id = bin2hex(random_bytes(4));

$reply = function (array $data, int $code = 200) use ($request_id): void {
    http_response_code($code);
    $data['request_id'] = $request_id;
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

switch ($action) {
    case 'ping':
        $reply(['ok' => true, 'site_url' => get_site_url(), 'wp_version' => get_bloginfo('version'), 'auth' => $auth_method, 'time' => date('c')]);
    case 'get_option':
        $key = (string) ($body['key'] ?? '');
        if ($key === '') { $reply(['error' => 'key required'], 400); }
        $reply(['key' => $key, 'value' => get_option($key)]);
    case 'update_option':
        $key = (string) ($body['key'] ?? '');
        $value = $body['value'] ?? '';
        if ($key === '') { $reply(['error' => 'key required'], 400); }
        $ok = update_option($key, $value);
        $reply(['ok' => (bool) $ok, 'key' => $key]);
    case 'update_post':
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) { $reply(['error' => 'id required'], 400); }
        $args = ['ID' => $id];
        if (array_key_exists('content', $body)) { $args['post_content'] = (string) $body['content']; }
        if (array_key_exists('title', $body))   { $args['post_title']   = (string) $body['title']; }
        if (array_key_exists('status', $body))  { $args['post_status']  = (string) $body['status']; }
        $result = wp_update_post($args, true);
        if (is_wp_error($result)) { $reply(['error' => $result->get_error_message()], 500); }
        $reply(['ok' => true, 'id' => $id]);
    case 'get_post':
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) { $reply(['error' => 'id required'], 400); }
        $post = get_post($id);
        if (!$post) { $reply(['error' => 'post not found'], 404); }
        $reply(['id' => $post->ID, 'content' => $post->post_content]);
    case 'purge_cache':
        do_action('litespeed_purge_all');
        if (function_exists('wp_cache_flush')) { wp_cache_flush(); }
        $reply(['ok' => true]);
    default:
        $reply(['error' => 'unknown action', 'action' => $action], 400);
}
