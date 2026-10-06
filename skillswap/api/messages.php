<?php
// API de mensajes del intercambio.
// GET  ?exchange_id=&last_id= -> mensajes nuevos (id > last_id)
// POST {exchange_id, content, csrf_token} -> envía mensaje y notifica al otro participante
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$conn = getConnection();
$userId = (int)$_SESSION['user_id'];

function jsonFail($conn, $code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $exchangeId = intval($_GET['exchange_id'] ?? 0);
    $lastId = intval($_GET['last_id'] ?? 0);

    if ($exchangeId <= 0) {
        jsonFail($conn, 400, 'ID de intercambio inválido');
    }

    $chk = $conn->prepare("SELECT proposer_id, acceptor_id FROM exchanges WHERE id = ?");
    $chk->bind_param("i", $exchangeId);
    $chk->execute();
    $ex = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$ex || !isParticipant($ex, $userId)) {
        jsonFail($conn, 403, 'Acceso denegado');
    }

    $stmt = $conn->prepare("
        SELECT m.id, m.exchange_id, m.sender_id, m.content, m.created_at, u.name AS sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.exchange_id = ? AND m.id > ?
        ORDER BY m.id ASC
    ");
    $stmt->bind_param("ii", $exchangeId, $lastId);
    $stmt->execute();
    $res = $stmt->get_result();

    $messages = [];
    while ($row = $res->fetch_assoc()) {
        $messages[] = [
            'id' => (int)$row['id'],
            'exchange_id' => (int)$row['exchange_id'],
            'sender_id' => (int)$row['sender_id'],
            'sender_name' => $row['sender_name'],
            'content' => $row['content'],
            'created_at' => date('d/m H:i', strtotime($row['created_at'])),
            'mine' => ((int)$row['sender_id'] === $userId)
        ];
    }
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)$token)) {
        jsonFail($conn, 403, 'Token de seguridad inválido');
    }

    $exchangeId = intval($_POST['exchange_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if ($exchangeId <= 0 || $content === '') {
        jsonFail($conn, 400, 'Mensaje vacío o intercambio no especificado');
    }
    if (mb_strlen($content) > 2000) {
        jsonFail($conn, 400, 'El mensaje supera los 2000 caracteres');
    }

    $chk = $conn->prepare("SELECT proposer_id, acceptor_id, status FROM exchanges WHERE id = ?");
    $chk->bind_param("i", $exchangeId);
    $chk->execute();
    $ex = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$ex || !isParticipant($ex, $userId)) {
        jsonFail($conn, 403, 'Acceso denegado');
    }

    $stmt = $conn->prepare("INSERT INTO messages (exchange_id, sender_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $exchangeId, $userId, $content);
    $stmt->execute();
    $newId = $conn->insert_id;
    $stmt->close();

    $me = getCurrentUser();
    $senderName = $me['name'] ?? 'Un usuario';
    $otherUserId = ((int)$ex['proposer_id'] === $userId) ? (int)$ex['acceptor_id'] : (int)$ex['proposer_id'];
    createNotification(
        $conn,
        $otherUserId,
        'message',
        'Nuevo mensaje de ' . $senderName,
        mb_substr($content, 0, 80) . (mb_strlen($content) > 80 ? '...' : ''),
        '/skillswap/modules/messages/index.php?exchange_id=' . $exchangeId
    );
    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => [
            'id' => (int)$newId,
            'exchange_id' => $exchangeId,
            'sender_id' => $userId,
            'sender_name' => $senderName,
            'content' => $content,
            'created_at' => date('d/m H:i'),
            'mine' => true
        ]
    ]);
    exit;
}

jsonFail($conn, 405, 'Método no permitido');