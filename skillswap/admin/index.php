<?php
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$conn = getConnection();

$stats = [
    'users' => $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'student'")->fetch_assoc()['c'],
    'exchanges_total' => $conn->query("SELECT COUNT(*) as c FROM exchanges")->fetch_assoc()['c'],
    'exchanges_completed' => $conn->query("SELECT COUNT(*) as c FROM exchanges WHERE status = 'completed'")->fetch_assoc()['c'],
    'skills' => $conn->query("SELECT COUNT(*) as c FROM skills")->fetch_assoc()['c'],
    'hours_total' => $conn->query("SELECT SUM(amount) as t FROM hours_log")->fetch_assoc()['t'] ?? 0,
];

$recentUsers = $conn->query("SELECT * FROM users WHERE role = 'student' ORDER BY created_at DESC LIMIT 5");
$recentExchanges = $conn->query("
    SELECT e.*, so.name as offered_name, sr.name as requested_name,
           p.name as proposer_name, a.name as acceptor_name
    FROM exchanges e
    LEFT JOIN skills so ON e.skill_offered_id = so.id
    LEFT JOIN skills sr ON e.skill_requested_id = sr.id
    JOIN users p ON e.proposer_id = p.id
    JOIN users a ON e.acceptor_id = a.id
    ORDER BY e.proposed_at DESC LIMIT 10
");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-shield-lock"></i> Panel de Administración</h2>
    <div>
        <a href="/skillswap/admin/users.php" class="btn btn-outline-primary">
            <i class="bi bi-people"></i> Usuarios
        </a>
        <a href="/skillswap/admin/categories.php" class="btn btn-outline-secondary">
            <i class="bi bi-tags"></i> Categorías
        </a>
        <a href="/skillswap/admin/reports.php" class="btn btn-outline-info">
            <i class="bi bi-graph-up"></i> Reportes
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-number"><?= $stats['users'] ?></div>
            <div class="text-muted">Estudiantes</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-number"><?= $stats['exchanges_completed'] ?></div>
            <div class="text-muted">Intercambios Completados</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-number"><?= $stats['skills'] ?></div>
            <div class="text-muted">Habilidades</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-number"><?= number_format($stats['hours_total']) ?></div>
            <div class="text-muted">Horas Intercambiadas</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Usuarios Recientes</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php while ($u = $recentUsers->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($u['email']) ?></small></td>
                                <td><small><?= timeAgo($u['created_at']) ?></small></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Últimos Intercambios</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Intercambio</th><th>Propuso</th><th>Aceptó</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($e = $recentExchanges->fetch_assoc()): ?>
                            <tr>
                                <td><?= $e['skill_offered_id'] ? htmlspecialchars($e['offered_name']) : 'solo aprendizaje' ?> ↔ <?= $e['skill_requested_id'] ? htmlspecialchars($e['requested_name']) : 'solo enseñar' ?></td>
                                <td><?= htmlspecialchars($e['proposer_name']) ?></td>
                                <td><?= htmlspecialchars($e['acceptor_name']) ?></td>
                                <td><span class="badge <?= getStatusBadgeClass($e['status']) ?>"><?= getStatusLabel($e['status']) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>
