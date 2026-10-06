<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'skillswap');

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log('SkillSwap DB connection error: ' . $conn->connect_error);
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (headers_sent()) {
            exit('Servicio temporalmente no disponible.');
        }
        $_SESSION['flash'] = ['error' => 'No se pudo conectar a la base de datos. Inténtalo de nuevo más tarde.'];
        header('Location: /skillswap/index.php');
        exit;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}
