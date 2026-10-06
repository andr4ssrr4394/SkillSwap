<?php
// API de notificaciones: alimenta la campana del encabezado.
// GET  -> { unread_count, notifications[] }
// POST -> action=mark_all_read (exige csrf_token)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$conn = getConnection();
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido']);
        $conn->close();
        exit;
    }

    if ($action === 'mark_all_read') {
        $updated = markAllNotificationsRead($conn, $userId);
        echo json_encode(['success' => true, 'updated' => $updated]);
        $conn->close();
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    $conn->close();
    exit;
}

$count = getUnreadNotificationCount($conn, $userId);
$rows = getRecentNotifications($conn, $userId, 10);
$notifs = [];
foreach ($rows as $row) {
    $notifs[] = [
        'id' => (int)$row['id'],
        'type' => $row['type'],
        'title' => $row['title'],
        'message' => $row['message'],
        'link' => $row['link'],
        'is_read' => (int)$row['is_read'],
        'time' => timeAgo($row['created_at'])
    ];
}
$conn->close();

echo json_encode([
    'success' => true,
    'unread_count' => $count,
    'notifications' => $notifs
]);