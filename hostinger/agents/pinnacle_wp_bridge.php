<?php
/**
 * pinnacle_wp_bridge.php
 *
 * HTTPS bridge for programmatic WordPress operations on pinnaclegroupwi.com.
 * Dual auth: X-Alex-Secret header OR Basic Auth with WP Application Password.
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
    echo json_encode(['error' => 'WordPress bootstrap failed']);
    exit;
}

// -------- Deferred App Password auth --------
// Handles both bcrypt ($2y$) (WP 6.8+) and phpass ($P$) (legacy) hash formats.
// Bypasses wp_check_password to avoid check_password filter interference.

if (!$auth_ok && isset($pending_basic_user)) {
    $user = get_user_by('email', $pending_basic_user) ?: get_user_by('login', $pending_basic_user);
    if ($user instanceof WP_User && user_can($user, 'manage_options') && class_exists('WP_Application_Passwords')) {
        $cleaned_pass = str_replace(' ', '', $pending_basic_pass);
        $hashed_passwords = WP_Application_Passwords::get_user_application_passwords($user->ID);
        if (is_array($hashed_passwords)) {
            foreach ($hashed_passwords as $item) {
                $stored = $item['password'];
                $matched = false;
                // Try bcrypt first (WP 6.8+)
                if (strlen($stored) >= 60 && str_starts_with($stored, '$2')) {
                    $matched = password_verify($cleaned_pass, $stored);
                }
                // Fall back to phpass portable hash
                if (!$matched && str_starts_with($stored, '$P$')) {
                    if (!class_exists('PasswordHash', false)) {
                        require_once ABSPATH . WPINC . '/class-phpass.php';
                    }
                    $hasher = new PasswordHash(8, true);
                    $matched = $hasher->CheckPassword($cleaned_pass, $stored);
                }
                if ($matched) {
                    wp_set_current_user($user->ID);
                    $auth_ok     = true;
                    $auth_method = 'app-password';
                    break;
                }
            }
        }
    }
}
if (!$auth_ok) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized', 'hint' => 'use X-Alex-Secret header or Basic Auth with WP App Password']);
    exit;
}

// -------- Request handling --------

$raw = file_get_contents('php://input');
$body = json_decode((string) $raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid JSON body']);
    exit;
}
$action = (string) ($body['action'] ?? '');
$request_id = bin2hex(random_bytes(4));

$log_file = (getenv('HOME') ?: '/tmp') . '/wp-bridge.log';
@file_put_contents($log_file, sprintf("[%s] req=%s auth=%s action=%s size=%d\n", date('c'), $request_id, $auth_method, $action, strlen((string) $raw)), FILE_APPEND);

$reply = function (array $data, int $code = 200) use ($request_id): void {
    http_response_code($code);
    $data['request_id'] = $request_id;
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

switch ($action) {

    case 'ping':
        $reply([
            'ok'         => true,
            'site_url'   => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'theme'      => get_option('stylesheet'),
            'php'        => PHP_VERSION,
            'auth'       => $auth_method,
            'time'       => date('c'),
        ]);

    case 'list_pages':
        $pages = get_pages(['number' => 100, 'sort_column' => 'menu_order,post_title']);
        $out = [];
        foreach ($pages as $p) {
            $out[] = ['id' => $p->ID, 'slug' => $p->post_name, 'title' => $p->post_title, 'status' => $p->post_status];
        }
        $reply(['count' => count($out), 'pages' => $out]);

    case 'list_posts':
        $args_in = (array) ($body['args'] ?? []);
        $allowed = ['post_type', 'post_status', 'posts_per_page', 's', 'orderby', 'order'];
        $args = array_intersect_key($args_in, array_flip($allowed));
        $args['posts_per_page'] = min((int) ($args['posts_per_page'] ?? 20), 100);
        $args['post_type']      = (string) ($args['post_type'] ?? 'post');
        $args['post_status']    = (string) ($args['post_status'] ?? 'publish');
        $q = new WP_Query($args);
        $out = [];
        foreach ($q->posts as $p) {
            $out[] = ['id' => $p->ID, 'slug' => $p->post_name, 'title' => $p->post_title, 'status' => $p->post_status, 'type' => $p->post_type];
        }
        $reply(['count' => count($out), 'found' => (int) $q->found_posts, 'posts' => $out]);

    case 'get_post':
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) { $reply(['error' => 'id required'], 400); }
        $post = get_post($id);
        if (!$post) { $reply(['error' => 'post not found', 'id' => $id], 404); }
        $reply([
            'id'           => $post->ID,
            'title'        => $post->post_title,
            'slug'         => $post->post_name,
            'status'       => $post->post_status,
            'type'         => $post->post_type,
            'content'      => $post->post_content,
            'content_size' => strlen($post->post_content),
            'modified'     => $post->post_modified_gmt,
        ]);

    case 'update_post':
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) { $reply(['error' => 'id required'], 400); }
        $existing = get_post($id);
        if (!$existing) { $reply(['error' => 'post not found', 'id' => $id], 404); }
        $args = ['ID' => $id];
        if (array_key_exists('content', $body)) { $args['post_content'] = (string) $body['content']; }
        if (array_key_exists('title', $body))   { $args['post_title']   = (string) $body['title']; }
        if (array_key_exists('status', $body))  { $args['post_status']  = (string) $body['status']; }
        if (array_key_exists('slug', $body))    { $args['post_name']    = (string) $body['slug']; }
        $result = wp_update_post($args, true);
        if (is_wp_error($result)) { $reply(['error' => $result->get_error_message()], 500); }
        $verify = get_post($id);
        $reply(['ok' => true, 'id' => $id, 'new_content_size' => strlen($verify->post_content)]);

    case 'create_post':
        $args = [
            'post_type'    => (string) ($body['post_type'] ?? 'page'),
            'post_status'  => (string) ($body['status'] ?? 'draft'),
            'post_title'   => (string) ($body['title'] ?? 'Untitled'),
            'post_content' => (string) ($body['content'] ?? ''),
            'post_name'    => (string) ($body['slug'] ?? ''),
        ];
        $id = wp_insert_post($args, true);
        if (is_wp_error($id)) { $reply(['error' => $id->get_error_message()], 500); }
        $reply(['ok' => true, 'id' => $id, 'permalink' => get_permalink($id)]);

    case 'delete_post':
        $id    = (int) ($body['id'] ?? 0);
        $force = !empty($body['force']);
        if ($id <= 0) { $reply(['error' => 'id required'], 400); }
        $result = wp_delete_post($id, $force);
        $reply(['ok' => (bool) $result, 'id' => $id, 'forced' => $force]);

    case 'get_post_meta':
        $id  = (int) ($body['id'] ?? 0);
        $key = (string) ($body['key'] ?? '');
        if ($id <= 0 || $key === '') { $reply(['error' => 'id and key required'], 400); }
        $reply(['id' => $id, 'key' => $key, 'value' => get_post_meta($id, $key, true)]);

    case 'update_post_meta':
        $id    = (int) ($body['id'] ?? 0);
        $key   = (string) ($body['key'] ?? '');
        $value = $body['value'] ?? '';
        if ($id <= 0 || $key === '') { $reply(['error' => 'id and key required'], 400); }
        $ok = update_post_meta($id, $key, $value);
        $reply(['ok' => (bool) $ok, 'id' => $id, 'key' => $key]);

    case 'get_option':
        $key = (string) ($body['key'] ?? '');
        if ($key === '') { $reply(['error' => 'key required'], 400); }
        $reply(['key' => $key, 'value' => get_option($key)]);

    case 'update_option':
        $key   = (string) ($body['key'] ?? '');
        $value = $body['value'] ?? '';
        if ($key === '') { $reply(['error' => 'key required'], 400); }
        $ok = update_option($key, $value);
        $reply(['ok' => (bool) $ok, 'key' => $key]);

    case 'purge_cache':
        $purged = [];
        if (function_exists('do_action')) { do_action('litespeed_purge_all'); $purged[] = 'litespeed'; }
        if (function_exists('wp_cache_flush')) { wp_cache_flush(); $purged[] = 'wp_cache'; }
        $reply(['ok' => true, 'purged' => $purged]);

    default:
        $reply(['error' => 'unknown action', 'action' => $action], 400);
}
