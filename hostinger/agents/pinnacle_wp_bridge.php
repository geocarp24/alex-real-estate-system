<?php
declare(strict_types=1);

$config = __DIR__ . '/alex_config.php';
if (file_exists($config)) { require_once $config; }
$secret = defined('ALEX_SECRET') ? ALEX_SECRET : '';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$received_secret = (string) ($_SERVER['HTTP_X_ALEX_SECRET'] ?? '');
$auth_ok = $secret !== '' && hash_equals($secret, $received_secret);

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
}

require_once '/home/u433637438/domains/pinnaclegroupwi.com/public_html/wp-load.php';

$debug = [];
if (!$auth_ok && isset($pending_basic_user)) {
    $user = get_user_by('email', $pending_basic_user) ?: get_user_by('login', $pending_basic_user);
    if ($user instanceof WP_User) {
        $debug['user_id'] = $user->ID;
        $debug['manage_options'] = user_can($user, 'manage_options');
        $cleaned_pass = str_replace(' ', '', $pending_basic_pass);
        $debug['cleaned_pass_len'] = strlen($cleaned_pass);
        $debug['cleaned_pass_hash'] = substr(md5($cleaned_pass), 0, 8);
        $hashed_passwords = WP_Application_Passwords::get_user_application_passwords($user->ID);
        $debug['count'] = count($hashed_passwords);
        foreach ($hashed_passwords as $item) {
            $stored = $item['password'];
            $bcrypt_match = password_verify($cleaned_pass, $stored);
            require_once ABSPATH . WPINC . '/class-phpass.php';
            $phpass = new PasswordHash(8, true);
            $phpass_match = $phpass->CheckPassword($cleaned_pass, $stored);
            $debug['item'] = [
                'name'          => $item['name'] ?? '?',
                'stored_prefix' => substr($stored, 0, 4),
                'stored_len'    => strlen($stored),
                'bcrypt_match'  => $bcrypt_match,
                'phpass_match'  => $phpass_match,
            ];
            if ($bcrypt_match || $phpass_match) {
                wp_set_current_user($user->ID);
                $auth_ok = true;
                break;
            }
        }
    } else {
        $debug['user_found'] = false;
    }
}

if (!$auth_ok) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized', 'debug' => $debug]);
    exit;
}

echo json_encode(['ok' => true, 'site' => get_site_url(), 'wp' => get_bloginfo('version')]);
