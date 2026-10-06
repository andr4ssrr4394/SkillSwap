<?php
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Stats
$stats = [
    'hours' => $currentUser['hours_balance'],
    'offered' => 0,
    'wanted' => 0,
    'exchanges' => 0,
    'completed' => 0,
    'rating' => ['avg_rating' => 0, 'total' => 0]
];
$stats['offered'] = 0;
$stmt = $conn->prepare("SELECT COUNT(*) c FROM user_skills_offered WHERE user_id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['offered'] = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stats['wanted'] = 0;
$stmt = $conn->prepare("SELECT COUNT(*) c FROM user_skills_wanted WHERE user_id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['wanted'] = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stats['exchanges'] = 0;
$stmt = $conn->prepare("SELECT COUNT(*) c FROM exchanges WHERE proposer_id=? OR acceptor_id=?");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$stats['exchanges'] = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stats['completed'] = 0;
$stmt = $conn->prepare("SELECT COUNT(*) c FROM exchanges WHERE (proposer_id=? OR acceptor_id=?) AND status='completed'");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$stats['completed'] = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stats['rating'] = getUserRating($conn, $userId);

// Pending actions: proposals I must accept (as acceptor)
$stmt = $conn->prepare("
    SELECT e.*, so.name AS offered_name, sr.name AS requested_name, p.name AS proposer_name
    FROM exchanges e
    LEFT JOIN skills so ON e.skill_offered_id = so.id
    LEFT JOIN skills sr ON e.skill_requested_id = sr.id
    JOIN users p ON e.proposer_id = p.id
    WHERE e.acceptor_id = ? AND e.status = 'pending'
    ORDER BY e.proposed_at DESC LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$needResponse = $stmt->get_result();
$stmt->close();

// Active exchanges requiring my completion confirmation
$stmt = $conn->prepare("
    SELECT e.*, so.name AS offered_name, sr.name AS requested_name, p.name AS proposer_name, a.name AS acceptor_name
    FROM exchanges e
    LEFT JOIN skills so ON e.skill_offered_id = so.id
    LEFT JOIN skills sr ON e.skill_requested_id = sr.id
    JOIN users p ON e.proposer_id = p.id
    JOIN users a ON e.acceptor_id = a.id
    WHERE (e.proposer_id = ? OR e.acceptor_id = ?)
      AND e.status IN ('accepted','in_progress')
    ORDER BY e.proposed_at DESC LIMIT 5
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$needCompletion = $stmt->get_result();
$stmt->close();

// Recent completed
$stmt = $conn->prepare("
    SELECT e.*, so.name AS offered_name, sr.name AS requested_name, p.name AS proposer_name, a.name AS acceptor_name
    FROM exchanges e
    LEFT JOIN skills so ON e.skill_offered_id = so.id
    LEFT JOIN skills sr ON e.skill_requested_id = sr.id
    JOIN users p ON e.proposer_id = p.id
    JOIN users a ON e.acceptor_id = a.id
    WHERE (e.proposer_id = ? OR e.acceptor_id = ?) AND e.status = 'completed'
    ORDER BY e.completed_at DESC LIMIT 5
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$recentCompleted = $stmt->get_result();
$stmt->close();

// Suggested matches (bidirectional)
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.career,
        (SELECT GROUP_CONCAT(DISTINCT s2.name SEPARATOR ', ') FROM user_skills_offered uo2 JOIN skills s2 ON uo2.skill_id=s2.id WHERE uo2.user_id=u.id AND uo2.skill_id IN (SELECT skill_id FROM user_skills_wanted WHERE user_id=?)) AS they_teach_me
    FROM users u
    WHERE u.id != ?
      AND EXISTS (SELECT 1 FROM user_skills_offered ofr WHERE ofr.user_id=u.id AND ofr.skill_id IN (SELECT skill_id FROM user_skills_wanted WHERE user_id=?))
      AND EXISTS (SELECT 1 FROM user_skills_wanted w WHERE w.user_id=u.id AND w.skill_id IN (SELECT skill_id FROM user_skills_offered WHERE user_id=?))
    ORDER BY u.name LIMIT 5
");
$stmt->bind_param("iiii", $userId, $userId, $userId, $userId);
$stmt->execute();
$matches = $stmt->get_result();
$stmt->close();

// Hours history (banco de horas)
$stmt = $conn->prepare("SELECT * FROM hours_log WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 15");
$stmt->bind_param("i", $userId);
$stmt->execute();
$hoursHistory = $stmt->get_result();
$stmt->close();
?>

<div class="thread-header mb-4">
    <h2>Bienvenido, <?= htmlspecialchars($currentUser['name']) ?></h2>
    <p class="text-muted">Resumen de tu actividad en SkillSwap.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="hours-badge mb-2"><?= formatHours($stats['hours']) ?></div>
            <span class="text-muted">Saldo de horas</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['offered'] ?></div>
            <span class="text-muted">Ofreces</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['wanted'] ?></div>
            <span class="text-muted">Buscas</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['completed'] ?></div>
            <span class="text-muted">Completados</span>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <?php if ($needResponse->num_rows > 0): ?>
            <div class="reddit-card mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"><i class="bi bi-bell"></i> Necesitan tu respuesta</h6>
                </div>
                <?php while ($e = $needResponse->fetch_assoc()): ?>
                    <div class="border-top pt-2 mt-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?= htmlspecialchars($e['proposer_name']) ?></strong> te propone:
                                <div class="small text-success">Tú enseñas <?= $e['skill_requested_id'] ? htmlspecialchars($e['requested_name']) : 'nada (solo aprendes)' ?> · <?= formatHours($e['acceptor_hours']) ?></div>
                                <div class="small text-info">Ellos te enseñan <?= $e['skill_offered_id'] ? htmlspecialchars($e['offered_name']) : 'nada (solo aprenden de ti)' ?> · <?= formatHours($e['proposer_hours']) ?></div>
                            </div>
                            <div class="text-end">
                                <form method="POST" action="/skillswap/modules/exchanges/accept.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="exchange_id" value="<?= $e['id'] ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <form method="POST" action="/skillswap/modules/exchanges/accept.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="exchange_id" value="<?= $e['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <div class="reddit-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><i class="bi bi-arrow-repeat"></i> Intercambios activos</h6>
                <a href="/skillswap/modules/exchanges/index.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <?php if ($needCompletion->num_rows > 0): ?>
                <?php while ($e = $needCompletion->fetch_assoc()): ?>
                    <?php $myRole = getUserRoleInExchange($e, $userId); ?>
                    <div class="border-top pt-2 mt-2 d-flex justify-content-between align-items-center">
                        <div>
                            <strong>#<?= $e['id'] ?></strong> · <?= htmlspecialchars($e['proposer_name']) ?> ↔ <?= htmlspecialchars($e['acceptor_name']) ?>
                            <div class="small text-muted">
                                <?= $e['skill_offered_id'] ? htmlspecialchars($e['offered_name']) : 'solo aprendizaje' ?> ↔ <?= $e['skill_requested_id'] ? htmlspecialchars($e['requested_name']) : 'solo enseñar' ?>
                                <?php if (!empty($e['scheduled_at'])): ?>
                                    <span class="text-primary">· <i class="bi bi-calendar-event"></i> Sesión: <?= date('d/m H:i', strtotime($e['scheduled_at'])) ?></span>
                                <?php endif; ?>
                                <?php if ($e['status'] === 'in_progress'): ?>
                                    <?php if ($e['completion_confirmed_by'] === $userId): ?>
                                        · <span class="text-success">Confirmaste finalización</span>
                                    <?php else: ?>
                                        · <span class="text-primary">La otra persona confirmó</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="/skillswap/modules/messages/index.php?exchange_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chat-left-text"></i></a>
                            <form method="POST" action="/skillswap/modules/exchanges/complete.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="exchange_id" value="<?= $e['id'] ?>">
                                <button class="btn btn-sm btn-primary"><i class="bi bi-check2-circle"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted py-2 mb-0">No tienes intercambios activos.</p>
            <?php endif; ?>
        </div>

        <div class="reddit-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><i class="bi bi-check2-all"></i> Recientes completados</h6>
                <a href="/skillswap/modules/exchanges/index.php?filter=completed" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <?php if ($recentCompleted->num_rows > 0): ?>
                <?php while ($e = $recentCompleted->fetch_assoc()): ?>
                    <div class="border-top pt-2 mt-2 d-flex justify-content-between align-items-center">
                        <div>
                            <strong>#<?= $e['id'] ?></strong> · <?= $e['skill_offered_id'] ? htmlspecialchars($e['offered_name']) : 'solo aprendizaje' ?> ↔ <?= $e['skill_requested_id'] ? htmlspecialchars($e['requested_name']) : 'solo enseñar' ?>
                            <div class="small text-muted">Con <?= htmlspecialchars($userId == $e['proposer_id'] ? $e['acceptor_name'] : $e['proposer_name']) ?></div>
                        </div>
                        <a href="/skillswap/modules/ratings/submit.php?exchange_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-star"></i> Valorar</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted py-2 mb-0">Aún no completas intercambios.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="reddit-card mb-3">
            <h6 class="mb-2"><i class="bi bi-lightning"></i> Matches sugeridos</h6>
            <?php if ($matches->num_rows > 0): ?>
                <?php while ($m = $matches->fetch_assoc()): ?>
                    <div class="border-top pt-2 mt-2 d-flex justify-content-between align-items-center">
                        <div>
                            <a href="/skillswap/modules/profile/view.php?id=<?= $m['id'] ?>"><strong><?= htmlspecialchars($m['name']) ?></strong></a>
                            <div class="small text-muted"><?= htmlspecialchars($m['career'] ?? '') ?></div>
                            <small class="text-success">Te enseña: <?= htmlspecialchars($m['they_teach_me']) ?></small>
                        </div>
                        <a href="/skillswap/modules/exchanges/propose.php?user_id=<?= $m['id'] ?>" class="btn btn-sm btn-orange"><i class="bi bi-arrow-repeat"></i></a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted py-2 mb-0">
                    No hay matches aún.
                    <a href="/skillswap/modules/profile/edit.php">Agrega habilidades</a>.
                </p>
            <?php endif; ?>
        </div>

        <div class="reddit-card mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-clock-history"></i> Historial de horas</h6>
            </div>
            <small class="text-muted d-block mb-2">Ganas por enseñar y pagas por aprender. El saldo nunca baja de 0.</small>
            <?php if ($hoursHistory->num_rows > 0): ?>
                <ul class="list-unstyled mb-0">
                    <?php while ($h = $hoursHistory->fetch_assoc()): ?>
                        <li class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                            <span>
                                <?php if ($h['amount'] > 0): ?>
                                    <span class="text-success fw-bold"><i class="bi bi-plus-circle"></i> +<?= formatHours($h['amount']) ?></span>
                                    <small class="text-muted d-block">Ganadas por enseñar · <?= timeAgo($h['created_at']) ?></small>
                                <?php else: ?>
                                    <span class="text-danger fw-bold"><i class="bi bi-dash-circle"></i> <?= formatHours($h['amount']) ?></span>
                                    <small class="text-muted d-block">Pagadas por aprender · <?= timeAgo($h['created_at']) ?></small>
                                <?php endif; ?>
                            </span>
                            <span class="small text-muted">Saldo: <?= $h['balance_after'] ?>h</span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted py-2 mb-0">Aún no tienes movimientos de horas.</p>
            <?php endif; ?>
        </div>

        <?php if ($stats['rating']['total'] > 0): ?>
            <div class="reddit-card text-center">
                <h6 class="mb-2">Mi calificación</h6>
                <?= renderStars($stats['rating']['avg_rating']) ?>
                <span class="ms-1"><?= number_format($stats['rating']['avg_rating'], 1) ?></span>
                <span class="text-muted">(<?= $stats['rating']['total'] ?> reseñas)</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
?>