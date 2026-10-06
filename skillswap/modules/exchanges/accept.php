<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
csrf_check();

$conn = getConnection();
$userId = $_SESSION['user_id'];
$exchangeId = intval($_POST['exchange_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($exchangeId === 0 || empty($action)) {
    redirect('/skillswap/modules/exchanges/index.php');
}

$stmt = $conn->prepare("SELECT * FROM exchanges WHERE id = ?");
$stmt->bind_param("i", $exchangeId);
$stmt->execute();
$exchange = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Only the acceptor can accept/reject a pending exchange; the proposer can cancel while pending
$isCancel = $action === 'cancel' && $exchange && $exchange['status'] === 'pending' && $exchange['proposer_id'] === $userId;
if (!$exchange || (!$isCancel && !canAcceptExchange($exchange, $userId))) {
    flash('error', 'No tienes permiso para esta acción. Solo la persona a quien se propuso puede responder.');
    redirect('/skillswap/modules/exchanges/index.php');
}

if ($action === 'accept' && $exchange['status'] === 'pending') {
    $stmt = $conn->prepare("SELECT hours_balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $myBalance = (float)$stmt->get_result()->fetch_assoc()['hours_balance'];
    $stmt->close();

    $teach = (float)$exchange['acceptor_hours'];
    $learn = (float)$exchange['proposer_hours'];
    $projected = $myBalance + $teach - $learn;
    if ($projected < 0) {
        flash('error', "No puedes aceptar: al enseñar " . formatHours($teach) . " y aprender " . formatHours($learn) . " tu saldo quedaría en " . formatHours($projected) . " (saldo actual: " . formatHours($myBalance) . ").");
    } else {
        $stmt = $conn->prepare("UPDATE exchanges SET status = 'accepted' WHERE id = ?");
        $stmt->bind_param("i", $exchangeId);
        $stmt->execute();
        $stmt->close();

        $me = getCurrentUser();
        createNotification(
            $conn,
            $exchange['proposer_id'],
            'exchange_accepted',
            'Intercambio aceptado',
            ($me['name'] ?? 'Tu contraparte') . ' aceptó tu propuesta #' . $exchangeId . '. Ya pueden coordinar la sesión.',
            '/skillswap/modules/exchanges/index.php'
        );
        flash('success', 'Intercambio aceptado. Ganas ' . formatHours($teach) . ' por enseñar y pagas ' . formatHours($learn) . ' por aprender. Coordina los detalles en mensajes.');
    }
} elseif ($action === 'cancel' && $exchange['status'] === 'pending' && $exchange['proposer_id'] === $userId) {
    $stmt = $conn->prepare("UPDATE exchanges SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $exchangeId);
    $stmt->execute();
    $stmt->close();

    $me = getCurrentUser();
    createNotification(
        $conn,
        $exchange['acceptor_id'],
        'exchange_cancelled',
        'Propuesta cancelada',
        ($me['name'] ?? 'Alguien') . ' canceló la propuesta de intercambio #' . $exchangeId . '.',
        '/skillswap/modules/exchanges/index.php'
    );
    flash('success', 'Propuesta cancelada.');
} elseif ($action === 'reject' && $exchange['status'] === 'pending') {
    $stmt = $conn->prepare("UPDATE exchanges SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $exchangeId);
    $stmt->execute();
    $stmt->close();

    $me = getCurrentUser();
    createNotification(
        $conn,
        $exchange['proposer_id'],
        'exchange_rejected',
        'Propuesta no aceptada',
        ($me['name'] ?? 'Tu contraparte') . ' no aceptó la propuesta #' . $exchangeId . '.',
        '/skillswap/modules/exchanges/index.php'
    );
    flash('success', 'Intercambio rechazado.');
}

$conn->close();
redirect('/skillswap/modules/exchanges/index.php');
?>
