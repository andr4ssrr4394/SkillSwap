<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
csrf_check();

$conn = getConnection();
$userId = $_SESSION['user_id'];
$exchangeId = intval($_POST['exchange_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($exchangeId === 0 || !in_array($action, ['request', 'confirm', 'reject', 'cancel'])) {
    redirect('/skillswap/modules/exchanges/index.php');
}

$stmt = $conn->prepare("SELECT * FROM exchanges WHERE id = ?");
$stmt->bind_param("i", $exchangeId);
$stmt->execute();
$exchange = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exchange || !isParticipant($exchange, $userId)) {
    flash('error', 'Intercambio no válido o no participas en él.');
    redirect('/skillswap/modules/exchanges/index.php');
}

$activeStatus = ['accepted', 'in_progress'];
$otherId = $exchange['proposer_id'] === $userId ? $exchange['acceptor_id'] : $exchange['proposer_id'];

if ($action === 'request') {
    $hours = round((float)($_POST['extend_hours'] ?? 0) * 2) / 2;
    $direction = $_POST['extend_direction'] ?? '';

    if (!in_array($exchange['status'], $activeStatus)) {
        flash('error', 'Este intercambio ya no está activo.');
    } elseif (!empty($exchange['extend_requested_by'])) {
        flash('error', 'Ya hay una solicitud de extensión pendiente.');
    } elseif ($hours <= 0) {
        flash('error', 'Las horas deben ser mayores a 0.');
    } elseif (!in_array($direction, ['proposer', 'acceptor'])) {
        flash('error', 'Dirección de extensión no válida.');
    } else {
        $newHours = (float)$exchange[$direction === 'proposer' ? 'proposer_hours' : 'acceptor_hours'] + $hours;
        if ($newHours > 24) {
            flash('error', 'La extensión superaría el máximo de 24 horas en esa dirección.');
        } else {
            $stmt = $conn->prepare("UPDATE exchanges SET extend_requested_by = ?, extend_hours = ?, extend_direction = ? WHERE id = ? AND status IN ('accepted','in_progress')");
            $stmt->bind_param("idsi", $userId, $hours, $direction, $exchangeId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($affected > 0) {
                flash('success', 'Solicitud de extensión enviada. Espera la confirmación de la otra persona.');
            } else {
                flash('error', 'No se pudo registrar la extensión (el intercambio ya no está activo).');
            }
        }
    }
    redirect('/skillswap/modules/exchanges/index.php');
}

if ($action === 'cancel') {
    if ($exchange['extend_requested_by'] !== $userId) {
        flash('error', 'Solo quien solicitó la extensión puede cancelarla.');
    } else {
        $stmt = $conn->prepare("UPDATE exchanges SET extend_requested_by = NULL, extend_hours = NULL, extend_direction = NULL WHERE id = ?");
        $stmt->bind_param("i", $exchangeId);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Solicitud de extensión cancelada.');
    }
    redirect('/skillswap/modules/exchanges/index.php');
}

if ($action === 'reject') {
    if ($exchange['extend_requested_by'] !== $otherId) {
        flash('error', 'Solo la otra persona puede rechazar la extensión.');
    } else {
        $stmt = $conn->prepare("UPDATE exchanges SET extend_requested_by = NULL, extend_hours = NULL, extend_direction = NULL WHERE id = ?");
        $stmt->bind_param("i", $exchangeId);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Extensión rechazada. Las horas quedan como estaban.');
    }
    redirect('/skillswap/modules/exchanges/index.php');
}

// confirm
if ($exchange['extend_requested_by'] !== $otherId) {
    flash('error', 'Solo la otra persona puede confirmar la extensión.');
    redirect('/skillswap/modules/exchanges/index.php');
}

$addHours = (float)$exchange['extend_hours'];
$direction = $exchange['extend_direction'];
$now = (float)$exchange[$direction === 'proposer' ? 'proposer_hours' : 'acceptor_hours'];
$newHours = $now + $addHours;

if ($newHours > 24) {
    flash('error', 'La extensión superaría el máximo de 24 horas en esa dirección.');
    redirect('/skillswap/modules/exchanges/index.php');
}

$conn->begin_transaction();
try {
    // El que paga es quien aprende en la dirección extendida:
    // - extender 'proposer' (el proposer enseña más) -> paga el acceptor
    // - extender 'acceptor' (el acceptor enseña más) -> paga el proposer
    $payerId = $direction === 'proposer' ? $exchange['acceptor_id'] : $exchange['proposer_id'];
    $stmt = $conn->prepare("SELECT hours_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $payerId);
    $stmt->execute();
    $payerBal = (float)$stmt->get_result()->fetch_assoc()['hours_balance'];
    $stmt->close();

    $proposerNow = (float)$exchange['proposer_hours'];
    $acceptorNow = (float)$exchange['acceptor_hours'];
    if ($direction === 'proposer') $proposerNow = $newHours;
    else $acceptorNow = $newHours;

    $payerProj = $payerId == $exchange['acceptor_id']
        ? $payerBal + $acceptorNow - $proposerNow
        : $payerBal + $proposerNow - $acceptorNow;

    if ($payerProj < 0) {
        throw new Exception("La extensión dejaría el saldo del que paga en {$payerProj}h (nunca se permite saldo negativo).");
    }

    if ($direction === 'proposer') {
        $stmt = $conn->prepare("UPDATE exchanges SET proposer_hours = ?, extend_requested_by = NULL, extend_hours = NULL, extend_direction = NULL WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE exchanges SET acceptor_hours = ?, extend_requested_by = NULL, extend_hours = NULL, extend_direction = NULL WHERE id = ?");
    }
    $stmt->bind_param("di", $newHours, $exchangeId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    flash('success', 'Extensión confirmada: la dirección ahora es de ' . formatHours($newHours) . '.');
} catch (Exception $e) {
    $conn->rollback();
    flash('error', 'No se pudo confirmar la extensión: ' . $e->getMessage());
}

$conn->close();
redirect('/skillswap/modules/exchanges/index.php');