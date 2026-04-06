<?php
// ============================================================
//  PINNACLE — auth.php
//  Endpoint de autenticación
//  URL: pinnaclegroupwi.com/Tools/auth.php
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/config.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Leer body JSON
$body     = json_decode(file_get_contents('php://input'), true);
$action   = $body['action'] ?? '';
$email    = strtolower(trim($body['email'] ?? ''));
$password = $body['password'] ?? '';
$token    = $body['token'] ?? '';

// ── ARCHIVO DE SESIONES ──────────────────────────────────────
$sessionFile = sys_get_temp_dir() . '/pinnacle_sessions_' . md5(__FILE__) . '.json';

function loadSessions($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveSessions($file, $sessions) {
    file_put_contents($file, json_encode($sessions), LOCK_EX);
}

function cleanExpired($sessions) {
    $now = time();
    return array_filter($sessions, fn($s) => ($s['expires'] ?? 0) > $now);
}

// ── ACCIÓN: LOGIN ────────────────────────────────────────────
if ($action === 'login') {

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Email y password son requeridos']);
        exit;
    }

    $users = USERS;

    if (!isset($users[$email])) {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales incorrectas']);
        exit;
    }

    $user         = $users[$email];
    $storedPass   = $user['password'];

    // Verificar password (soporta texto plano y hashed)
    $valid = false;
    if (password_verify($password, $storedPass)) {
        $valid = true;  // Ya está encriptado
    } elseif ($password === $storedPass) {
        $valid = true;  // Texto plano (primera vez)
    }

    if (!$valid) {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales incorrectas']);
        exit;
    }

    // Generar token de sesión
    $sessionToken = bin2hex(random_bytes(32));
    $expires      = time() + (SESSION_HOURS * 3600);

    // Guardar sesión
    $sessions = cleanExpired(loadSessions($sessionFile));
    $sessions[$sessionToken] = [
        'email'   => $email,
        'name'    => $user['name'],
        'role'    => $user['role'] ?? 'user',
        'expires' => $expires,
    ];
    saveSessions($sessionFile, $sessions);

    echo json_encode([
        'success'      => true,
        'sessionToken' => $sessionToken,
        'name'         => $user['name'],
        'role'         => $user['role'] ?? 'user',
        'at'           => AIRTABLE_TOKEN,
        'expires'      => $expires,
    ]);
    exit;
}

// ── ACCIÓN: VERIFY (validar sesión activa) ────────────────────
if ($action === 'verify') {

    if (!$token) {
        http_response_code(400);
        echo json_encode(['valid' => false, 'error' => 'Token requerido']);
        exit;
    }

    $sessions = cleanExpired(loadSessions($sessionFile));

    if (!isset($sessions[$token])) {
        http_response_code(401);
        echo json_encode(['valid' => false, 'error' => 'Sesión expirada o inválida']);
        exit;
    }

    $session = $sessions[$token];

    echo json_encode([
        'valid' => true,
        'name'  => $session['name'],
        'role'  => $session['role'],
        'at'    => AIRTABLE_TOKEN,
    ]);
    exit;
}

// ── ACCIÓN: LOGOUT ────────────────────────────────────────────
if ($action === 'logout') {

    if ($token) {
        $sessions = loadSessions($sessionFile);
        unset($sessions[$token]);
        saveSessions($sessionFile, $sessions);
    }

    echo json_encode(['success' => true]);
    exit;
}

// Acción no reconocida
http_response_code(400);
echo json_encode(['error' => 'Acción no reconocida']);
