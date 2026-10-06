<?php
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all';
$page = max(1, intval($_GET['p'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$filterSql = '';
if ($filter === 'pending') $filterSql = " AND e.status = 'pending'";
elseif ($filter === 'active') $filterSql = " AND e.status IN ('accepted', 'in_progress')";
elseif ($filter === 'completed') $filterSql = " AND e.status = 'completed'";
elseif ($filter === 'cancelled') $filterSql = " AND e.status IN ('cancelled','rejected')";

$countStmt = $conn->prepare("SELECT COUNT(*) c FROM exchanges e WHERE (e.proposer_id = ? OR e.acceptor_id = ?)" . $filterSql);
$countStmt->bind_param("ii", $userId, $userId);
$countStmt->execute();
$total = (int)$countStmt->get_result()->fetch_assoc()['c'];
$countStmt->close();
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;

$query = "
    SELECT e.*,
           so.name AS offered_name, sr.name AS requested_name,
           p.name AS proposer_name, a.name AS acceptor_name
    FROM exchanges e
    LEFT JOIN skills so ON e.skill_offered_id = so.id
    LEFT JOIN skills sr ON e.skill_requested_id = sr.id
    JOIN users p ON e.proposer_id = p.id
    JOIN users a ON e.acceptor_id = a.id
    WHERE (e.proposer_id = ? OR e.acceptor_id = ?)
" . $filterSql . " ORDER BY e.proposed_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $userId, $userId, $perPage, $offset);
$stmt->execute();
$exchanges = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) c FROM exchanges WHERE (proposer_id=? OR acceptor_id=?) AND status IN ('accepted','in_progress')");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$hasActive = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-arrow-repeat"></i> Mis Intercambios</h2>
    <a href="/skillswap/modules/skills/index.php" class="btn btn-primary">
        <i class="bi bi-search"></i> Encontrar Intercambio
    </a>
</div>

<div class="mb-3">
    <div class="btn-group">
        <a href="?filter=all&p=1" class="btn btn-sm btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">Todos</a>
        <a href="?filter=pending&p=1" class="btn btn-sm btn-outline-primary <?= $filter === 'pending' ? 'active' : '' ?>">Pendientes</a>
        <a href="?filter=active&p=1" class="btn btn-sm btn-outline-primary <?= $filter === 'active' ? 'active' : '' ?>">Activos</a>
        <a href="?filter=completed&p=1" class="btn btn-sm btn-outline-primary <?= $filter === 'completed' ? 'active' : '' ?>">Completados</a>
        <a href="?filter=cancelled&p=1" class="btn btn-sm btn-outline-secondary <?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelados/Rechazados</a>
    </div>
</div>

<?php if ($exchanges->num_rows > 0): ?>
    <?php while ($e = $exchanges->fetch_assoc()): ?>
        <?php
        $myRole = getUserRoleInExchange($e, $userId);
        // Who teaches what
        // proposer teaches offered; acceptor teaches requested (to proposer)
        ?>
        <div class="reddit-card mb-3">
            <div class="d-flex align-items-start gap-3">
                <div class="vote-column">
                    <div class="text-muted small"><?= formatHours($e['proposer_hours']) ?></div>
                    <div class="text-muted small text-success"><i class="bi bi-plus-lg"></i> enseña</div>
                    <div class="vote-divider"></div>
                    <div class="text-muted small"><?= formatHours($e['acceptor_hours']) ?></div>
                    <div class="text-muted small text-success"><i class="bi bi-plus-lg"></i> enseña</div>
                </div>

                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <strong class="fs-5">Intercambio #<?= $e['id'] ?></strong>
                        <span class="badge <?= getStatusBadgeClass($e['status']) ?>"><?= getStatusLabel($e['status']) ?></span>
                    </div>

                    <div class="text-muted small mb-1">
                        <i class="bi bi-person-fill"></i> <?= htmlspecialchars($e['proposer_name']) ?> ↔ <?= htmlspecialchars($e['acceptor_name']) ?>
                    </div>

                    <?php if (!empty($e['scheduled_at'])): ?>
                        <div class="small text-primary mb-1">
                            <i class="bi bi-calendar-event"></i> Sesión programada: <?= date('d/m/Y H:i', strtotime($e['scheduled_at'])) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array($e['status'], ['accepted', 'in_progress'])): ?>
                        <div class="small mb-2">
                            <span class="badge <?= getMeetingTypeBadgeClass($e['meeting_type'] ?? 'virtual') ?>">
                                <i class="bi <?= ($e['meeting_type'] ?? 'virtual') === 'presencial' ? 'bi-geo-alt' : 'bi-camera-video' ?>"></i>
                                <?= getMeetingTypeLabel($e['meeting_type'] ?? 'virtual') ?>
                            </span>
                            <?php if (($e['meeting_type'] ?? 'virtual') === 'virtual' && !empty($e['meeting_link'])): ?>
                                <a class="ms-1" href="<?= htmlspecialchars($e['meeting_link']) ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="bi bi-box-arrow-up-right"></i> Enlace
                                </a>
                            <?php elseif (($e['meeting_type'] ?? 'virtual') === 'presencial' && !empty($e['meeting_location'])): ?>
                                <span class="ms-1 text-muted"><i class="bi bi-geo"></i> <?= htmlspecialchars($e['meeting_location']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-2">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <div class="exchange-direction offered">
                                <span class="direction-badge offered"><?= htmlspecialchars($e['proposer_name']) ?> enseña</span>
                                <div class="mt-1">
                                    <?php if ($e['skill_offered_id']): ?>
                                        <?= htmlspecialchars($e['offered_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Sin clase (solo aprendizaje)</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= formatHours($e['proposer_hours']) ?></small>
                                <?php if ($myRole === 'proposer'): ?>
                                    <small class="text-muted">— Tú</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="exchange-direction requested">
                                <span class="direction-badge requested"><?= htmlspecialchars($e['acceptor_name']) ?> enseña</span>
                                <div class="mt-1">
                                    <?php if ($e['skill_requested_id']): ?>
                                        <?= htmlspecialchars($e['requested_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Sin clase (solo enseñar)</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= formatHours($e['acceptor_hours']) ?></small>
                                <?php if ($myRole === 'acceptor'): ?>
                                    <small class="text-muted">— Tú</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($e['status'] === 'in_progress'): ?>
                        <div class="small text-muted mb-2">
                            <i class="bi bi-check2-circle"></i>
                            <?php if ($e['completion_confirmed_by'] === $userId): ?>
                                Confirmaste la finalización. Esperando a la otra persona.
                            <?php else: ?>
                                <?= htmlspecialchars($e['completion_confirmed_by'] ? ($e['completion_confirmed_by'] == $e['proposer_id'] ? $e['proposer_name'] : $e['acceptor_name']) : '') ?>
                                confirmó. Confirma para completar.
                            <?php endif; ?>
                        </div>
<?php endif; ?>

<?php
$extPending = ($e['extend_requested_by'] !== null && $e['extend_hours'] !== null);
$extByMe = $extPending && (int)$e['extend_requested_by'] === $userId;
$extByOther = $extPending && !$extByMe;
$extDirLabel = $e['extend_direction'] === 'proposer' ? $e['proposer_name'] : $e['acceptor_name'];
?>
                    <?php if (in_array($e['status'], ['accepted', 'in_progress'])): ?>
                        <?php if ($extPending): ?>
                            <div class="alert alert-info py-2 px-3 small mb-2">
                                <?php if ($extByOther): ?>
                                    <strong><?= htmlspecialchars($extDirLabel) ?></strong> propone extender +<?= formatHours($e['extend_hours']) ?> (<?= htmlspecialchars($extDirLabel) ?> enseña más). Eso sumaría a las horas de esa dirección.
                                    <div class="mt-2 d-inline-flex gap-2 align-items-center">
                                        <form method="POST" action="/skillswap/modules/exchanges/extend.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="exchange_id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="action" value="confirm">
                                            <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Confirmar</button>
                                        </form>
                                        <form method="POST" action="/skillswap/modules/exchanges/extend.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="exchange_id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Rechazar</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    Solicitaste extender +<?= formatHours($e['extend_hours']) ?> (<?= htmlspecialchars($extDirLabel) ?> enseña más). Esperando confirmación.
                                    <form method="POST" action="/skillswap/modules/exchanges/extend.php" class="d-inline ms-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="exchange_id" value="<?= $e['id'] ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button class="btn btn-sm btn-outline-secondary">Cancelar solicitud</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <details class="small mb-2" id="ext-<?= $e['id'] ?>">
                                <summary class="text-primary" style="cursor:pointer"><i class="bi bi-plus-circle"></i> Solicitar extensión (más horas)</summary>
                                <form method="POST" action="/skillswap/modules/exchanges/extend.php" class="d-flex flex-wrap gap-2 align-items-center mt-2"
                                      onsubmit="var d=this.querySelector('select');var s=this.querySelector('input[type=number]');if(!d.value||!(parseFloat(s.value)>0)){alert('Elige la dirección y un número de horas mayor a 0.');return false;}">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="exchange_id" value="<?= $e['id'] ?>">
                                    <input type="hidden" name="action" value="request">
                                    <select name="extend_direction" class="form-select form-select-sm" style="width:auto">
                                        <option value="">Dirección…</option>
                                        <option value="proposer"><?= htmlspecialchars($e['proposer_name']) ?> enseña más</option>
                                        <option value="acceptor"><?= htmlspecialchars($e['acceptor_name']) ?> enseña más</option>
                                    </select>
                                    <input type="number" name="extend_hours" min="0.5" max="24" step="0.5" class="form-control form-control-sm" style="width:100px" placeholder="Horas">
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Solicitar</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php if (canAcceptExchange($e, $userId)): ?>
                            <form method="POST" action="/skillswap/modules/exchanges/accept.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="exchange_id" value="<?= $e['id'] ?>">
                                <input type="hidden" name="action" value="accept">
                                <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Aceptar</button>
                            </form>
                            <form method="POST" action="/skillswap/modules/exchanges/accept.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="exchange_id" value="<?= $e['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Rechazar</button>
                            </form>
                        <?php endif; ?>

                        <?php if (in_array($e['status'], ['accepted', 'in_progress'])): ?>
                            <a href="/skillswap/modules/messages/index.php?exchange_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-chat-left-text"></i> Mensajes
                            </a>
                            <form method="POST" action="/skillswap/modules/exchanges/complete.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="exchange_id" value="<?= $e['id'] ?>">
                                <button class="btn btn-sm btn-primary"
                                        data-confirm="<?= $e['completion_confirmed_by'] === $userId ? 'Ya confirmaste la finalización.' : '¿Confirmar finalización del intercambio?' ?>">
                                    <i class="bi bi-check2-circle"></i>
                                    <?= $e['completion_confirmed_by'] === $userId ? 'Esperando al otro' : 'Confirmar Finalización' ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($e['status'] === 'completed'): ?>
                            <a href="/skillswap/modules/ratings/submit.php?exchange_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-star"></i> Valorar
                            </a>
                        <?php endif; ?>

                        <?php if ($e['status'] === 'pending' && $myRole === 'proposer'): ?>
                            <form method="POST" action="/skillswap/modules/exchanges/accept.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="exchange_id" value="<?= $e['id'] ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button class="btn btn-sm btn-outline-secondary" data-confirm="¿Cancelar esta propuesta?">
                                    <i class="bi bi-x-circle"></i> Cancelar propuesta
                                </button>
                            </form>
                        <?php elseif ($e['status'] === 'rejected' && $myRole === 'proposer'): ?>
                            <span class="small text-muted align-self-center">Rechazado por <?= htmlspecialchars($e['acceptor_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="text-center py-5">
        <i class="bi bi-arrow-repeat display-1 text-muted"></i>
        <p class="text-muted mt-3">No hay intercambios para mostrar.</p>
        <a href="/skillswap/modules/skills/index.php" class="btn btn-primary">Explorar Habilidades</a>
    </div>
<?php endif; ?>

<?php if ($pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php
            $pqs = fn($p) => '?filter=' . urlencode($filter) . '&p=' . $p;
            ?>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $pqs($page - 1) ?>">&laquo;</a>
            </li>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $pqs($i) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $pqs($page + 1) ?>">&raquo;</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
