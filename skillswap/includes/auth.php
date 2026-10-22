<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' data: https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; upgrade-insecure-requests");
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    // HSTS: se activa solo en dominios reales, no en localhost (evita bloquear
    // el desarrollo cuando se usa un certificado autofirmado).
    $hstsHost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    if (!empty($_SERVER['HTTPS']) && $hstsHost !== '' && $hstsHost !== 'localhost' && $hstsHost !== '127.0.0.1') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /skillswap/modules/auth/login.php");
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $user;
}

function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

// ---------------------------------------------------------------------------
// Jerarquia de administradores
//
//   admin principal  = la cuenta de SkillSwap que viene con el sistema.
//                      Es la UNICA que puede nombrar o quitar administradores,
//                      eliminar usuarios y restablecer contraseñas.
//                      (Se identifica por su id de instalacion o por su correo.)
//
//   admin            = gestiona el panel, reportes y categorias, pero NO toca
//                      roles de otros usuarios: ve la lista en modo lectura.
//
//   student          = usuario normal.
// ---------------------------------------------------------------------------
const SKILLSWAP_SUPER_ADMIN_ID = 1;
const SKILLSWAP_SUPER_ADMIN_EMAIL = 'admin@skillswap.com';

function isSuperAdminUser($user) {
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        return false;
    }
    return (int)($user['id'] ?? 0) === SKILLSWAP_SUPER_ADMIN_ID
        || strcasecmp((string)($user['email'] ?? ''), SKILLSWAP_SUPER_ADMIN_EMAIL) === 0;
}

function isSuperAdmin() {
    return isSuperAdminUser(getCurrentUser());
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: /skillswap/modules/dashboard/index.php");
        exit;
    }
}
