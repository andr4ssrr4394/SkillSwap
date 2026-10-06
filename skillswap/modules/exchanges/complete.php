<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
csrf_check();

$conn = getConnection();
$userId = $_SESSION['user_id'];
$exchangeId = intval($_POST['exchange_id'] ?? 0);

if ($exchangeId === 0) {
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

// Only active exchanges can be finalized
if (!in_array($exchange['status'], ['pending', 'accepted', 'in_progress'])) {
    flash('error', 'Este intercambio ya no puede finalizarse.');
    redirect('/skillswap/modules/exchanges/index.php');
}

$conn->begin_transaction();

try {
    if ($exchange['status'] !== 'completed') {
        if ($exchange['completion_confirmed_by'] === null) {
            // First confirmation -> mark in progress
            $stmt = $conn->prepare("UPDATE exchanges SET status = 'in_progress', completion_confirmed_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $userId, $exchangeId);
            $stmt->execute();
            $stmt->close();

            $me = getCurrentUser();
            $otherId = ((int)$exchange['proposer_id'] === (int)$userId)
                ? (int)$exchange['acceptor_id']
                : (int)$exchange['proposer_id'];
            createNotification(
                $conn,
                $otherId,
                'exchange_confirmed',
                'Confirmación de finalización',
                ($me['name'] ?? 'Tu contraparte') . ' confirmó que el intercambio #' . $exchangeId . ' se realizó. Falta tu confirmación para cerrarlo.',
                '/skillswap/modules/exchanges/index.php'
            );

            $conn->commit();
            flash('success', 'Has confirmado la finalización. Falta la confirmación de la otra persona para completar el intercambio.');
            redirect('/skillswap/modules/exchanges/index.php');
        }

        if ($exchange['completion_confirmed_by'] === $userId) {
            $conn->rollback();
            flash('error', 'Ya confirmaste la finalización de este intercambio.');
            redirect('/skillswap/modules/exchanges/index.php');
        }

        // Second confirmation -> complete exchange + apply debit/credit (banco de horas/tiempo)
        $proposerId   = (int)$exchange['proposer_id'];
        $acceptorId   = (int)$exchange['acceptor_id'];
        $teachMe      = (float)$exchange['proposer_hours'];   // proposer enseña al acceptor
        $teachHim     = (float)$exchange['acceptor_hours'];   // acceptor enseña al proposer

        // Lock both users to avoid concurrent negative balances
        $mysql = "SELECT hours_balance FROM users WHERE id = ? FOR UPDATE";
        $stmt = $conn->prepare($mysql);
        $stmt->bind_param("i", $proposerId);
        $stmt->execute();
        $proposerBal = (float)$stmt->get_result()->fetch_assoc()['hours_balance'];
        $stmt->close();

        $stmt = $conn->prepare($mysql);
        $stmt->bind_param("i", $acceptorId);
        $stmt->execute();
        $acceptorBal = (float)$stmt->get_result()->fetch_assoc()['hours_balance'];
        $stmt->close();

        // Proposer: gana por enseñar (+teachMe), paga por aprender (-teachHim)
        $pMid   = $proposerBal + $teachMe;
        $pFinal = $pMid - $teachHim;
        // Acceptor: gana por enseñar (+teachHim), paga por aprender (-teachMe)
        $aMid   = $acceptorBal + $teachHim;
        $aFinal = $aMid - $teachMe;

        if ($pFinal < 0 || $aFinal < 0) {
            throw new Exception("Saldo insuficiente para completar el intercambio (nunca se permite saldo negativo).");
        }

        // hours_log del proposer (solo direcciones con horas)
        if ($teachMe > 0) {
            $stmt = $conn->prepare("INSERT INTO hours_log (user_id, exchange_id, amount, balance_after) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iidd", $proposerId, $exchangeId, $teachMe, $pMid);
            $stmt->execute();
            $stmt->close();
        }
        if ($teachHim > 0) {
            $stmt = $conn->prepare("INSERT INTO hours_log (user_id, exchange_id, amount, balance_after) VALUES (?, ?, ?, ?)");
            $minus = -$teachHim;
            $stmt->bind_param("iidd", $proposerId, $exchangeId, $minus, $pFinal);
            $stmt->execute();
            $stmt->close();
        }

        // hours_log del acceptor
        if ($teachHim > 0) {
            $stmt = $conn->prepare("INSERT INTO hours_log (user_id, exchange_id, amount, balance_after) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iidd", $acceptorId, $exchangeId, $teachHim, $aMid);
            $stmt->execute();
            $stmt->close();
        }
        if ($teachMe > 0) {
            $stmt = $conn->prepare("INSERT INTO hours_log (user_id, exchange_id, amount, balance_after) VALUES (?, ?, ?, ?)");
            $minus = -$teachMe;
            $stmt->bind_param("iidd", $acceptorId, $exchangeId, $minus, $aFinal);
            $stmt->execute();
            $stmt->close();
        }

        // Update balances
        $stmt = $conn->prepare("UPDATE users SET hours_balance = ? WHERE id = ?");
        $stmt->bind_param("di", $pFinal, $proposerId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare("UPDATE users SET hours_balance = ? WHERE id = ?");
        $stmt->bind_param("di", $aFinal, $acceptorId);
        $stmt->execute();
        $stmt->close();

        // Mark exchange completed and clear any pending extension
        $stmt = $conn->prepare("UPDATE exchanges SET status = 'completed', completed_at = NOW(), completion_confirmed_by = NULL, extend_requested_by = NULL, extend_hours = NULL, extend_direction = NULL WHERE id = ?");
        $stmt->bind_param("i", $exchangeId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        $meIsProposer = ($exchange['proposer_id'] === $userId);
        $iTeach = $meIsProposer ? $teachMe : $teachHim;
        $iLearn = $meIsProposer ? $teachHim : $teachMe;
        flash('success', "Intercambio completado. Ganaste " . formatHours($iTeach) . " por enseñar y pagaste " . formatHours($iLearn) . " por aprender.");

        // Aviso post-completado: el otro participante ya puede valorar
        createNotification(
            $conn,
            $meIsProposer ? $acceptorId : $proposerId,
            'exchange_completed',
            'Intercambio completado',
            ($me['name'] ?? 'Tu contraparte') . ' completó el intercambio #' . $exchangeId . '. Ya puedes dejarlo una valoración.',
            '/skillswap/modules/exchanges/index.php'
        );
    }
} catch (Exception $e) {
    $conn->rollback();
    flash('error', 'Error al procesar la finalización: ' . $e->getMessage());
}

$conn->close();
redirect('/skillswap/modules/exchanges/index.php');