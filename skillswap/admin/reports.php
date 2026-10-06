<?php
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$conn = getConnection();

// Top rated users
$topUsersRes = $conn->query("
    SELECT u.name, u.email, u.hours_balance,
           AVG(r.score) as avg_rating, COUNT(r.id) as rating_count,
           (SELECT COUNT(*) FROM exchanges WHERE (proposer_id = u.id OR acceptor_id = u.id) AND status = 'completed') as completed
    FROM users u
    LEFT JOIN ratings r ON r.rated_id = u.id
    WHERE u.role = 'student'
    GROUP BY u.id
    HAVING rating_count > 0
    ORDER BY avg_rating DESC
    LIMIT 10
");
$topUsers = [];
while ($row = $topUsersRes->fetch_assoc()) {
    $topUsers[] = $row;
}
$topUsersRes->free();

// Most popular skills (se cargan en memoria para tabla y gráfica)
$popularRes = $conn->query("
    SELECT s.name, c.name as category, COUNT(DISTINCT uso.user_id) as offer_count
    FROM skills s
    JOIN user_skills_offered uso ON uso.skill_id = s.id
    LEFT JOIN categories c ON s.category_id = c.id
    GROUP BY s.id
    ORDER BY offer_count DESC
    LIMIT 10
");
$popularSkills = [];
$skillLabels = [];
$skillData = [];
while ($row = $popularRes->fetch_assoc()) {
    $popularSkills[] = $row;
    $skillLabels[] = $row['name'];
    $skillData[] = (int)$row['offer_count'];
}
$popularRes->free();

// Monthly exchanges (en orden cronológico para la gráfica)
$monthlyRes = $conn->query("
    SELECT DATE_FORMAT(completed_at, '%Y-%m') as month, COUNT(*) as count
    FROM exchanges
    WHERE status = 'completed' AND completed_at IS NOT NULL
    GROUP BY month
    ORDER BY month ASC
    LIMIT 12
");
$monthlyExchanges = [];
$monthLabels = [];
$monthData = [];
while ($row = $monthlyRes->fetch_assoc()) {
    $monthlyExchanges[] = $row;
    $parts = explode('-', $row['month']);
    $monthLabels[] = (isset($parts[1]) ? $parts[1] : $row['month']) . '/' . substr($row['month'], 2, 2);
    $monthData[] = (int)$row['count'];
}
$monthlyRes->free();

// Hours transferred
$hoursStats = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total_hours, COUNT(*) as transactions
    FROM hours_log
    WHERE amount > 0
")->fetch_assoc();

// Uso de la modalidad de sesión
$modalityRes = $conn->query("
    SELECT meeting_type, COUNT(*) as total
    FROM exchanges
    GROUP BY meeting_type
");
$modalityData = ['virtual' => 0, 'presencial' => 0];
while ($row = $modalityRes->fetch_assoc()) {
    $key = $row['meeting_type'] === 'presencial' ? 'presencial' : 'virtual';
    $modalityData[$key] = (int)$row['total'];
}
$modalityRes->free();
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h2 class="mb-0"><i class="bi bi-graph-up"></i> Reportes</h2>
    <div class="d-flex gap-2">
        <a href="/skillswap/admin/export.php?type=users" class="btn btn-success btn-sm">
            <i class="bi bi-filetype-csv"></i> Exportar usuarios (CSV)
        </a>
        <a href="/skillswap/admin/export.php?type=exchanges" class="btn btn-primary btn-sm">
            <i class="bi bi-filetype-csv"></i> Exportar intercambios (CSV)
        </a>
    </div>
</div>
<hr>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-number"><?= number_format($hoursStats['total_hours'] ?? 0, 1) ?></div>
            <div class="text-muted">Horas impartidas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-number"><?= (int)($hoursStats['transactions'] ?? 0) ?></div>
            <div class="text-muted">Transacciones de horas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-number"><?= count($popularSkills) ?></div>
            <div class="text-muted">Habilidades más activas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-number"><?= count($topUsers) ?></div>
            <div class="text-muted">Usuarios valorados</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Usuarios Mejor Valorados</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Usuario</th><th>Rating</th><th>Reseñas</th><th>Completados</th></tr></thead>
                    <tbody>
                        <?php foreach ($topUsers as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= renderStars($u['avg_rating']) ?> <?= number_format($u['avg_rating'], 1) ?></td>
                                <td><?= (int)$u['rating_count'] ?></td>
                                <td><?= (int)$u['completed'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$topUsers): ?>
                            <tr><td colspan="4" class="text-muted">Todavía no hay valoraciones.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Habilidades Más Populares</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Habilidad</th><th>Categoría</th><th>Ofrecida por</th></tr></thead>
                    <tbody>
                        <?php foreach ($popularSkills as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($s['category'] ?? '—') ?></td>
                                <td><?= (int)$s['offer_count'] ?> usuarios</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$popularSkills): ?>
                            <tr><td colspan="3" class="text-muted">Aún no hay habilidades ofrecidas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($monthlyExchanges)): ?>
<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0">Intercambios por Mes</h5></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>Mes</th><th>Completados</th></tr></thead>
            <tbody>
                <?php foreach ($monthlyExchanges as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['month']) ?></td>
                        <td><?= (int)$m['count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mt-1">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> Habilidades más ofrecidas</h5></div>
            <div class="card-body">
                <canvas id="skillsChart" height="240"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history"></i> Intercambios completados por mes</h5></div>
            <div class="card-body">
                <canvas id="monthsChart" height="240"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-camera-video"></i> Modalidad de las sesiones</h5></div>
            <div class="card-body">
                <canvas id="modalityChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;

    Chart.defaults.font.family = "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.color = getComputedStyle(document.body).getPropertyValue('--bs-body-color') || '#212529';

    var skillCanvas = document.getElementById('skillsChart');
    if (skillCanvas) {
        new Chart(skillCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($skillLabels) ?>,
                datasets: [{
                    label: 'Usuarios que la ofrecen',
                    data: <?= json_encode($skillData) ?>,
                    backgroundColor: '#ff4500',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {legend: {display: false}},
                scales: {
                    y: {beginAtZero: true, ticks: {precision: 0}},
                    x: {ticks: {maxRotation: 45, minRotation: 30}}
                }
            }
        });
    }

    var monthsCanvas = document.getElementById('monthsChart');
    if (monthsCanvas) {
        new Chart(monthsCanvas, {
            type: 'line',
            data: {
                labels: <?= json_encode($monthLabels) ?>,
                datasets: [{
                    label: 'Completados',
                    data: <?= json_encode($monthData) ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,.15)',
                    fill: true,
                    tension: .3,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {legend: {display: false}},
                scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
            }
        });
    }

    var modalityCanvas = document.getElementById('modalityChart');
    if (modalityCanvas) {
        new Chart(modalityCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Virtual', 'Presencial'],
                datasets: [{
                    data: [<?= (int)$modalityData['virtual'] ?>, <?= (int)$modalityData['presencial'] ?>],
                    backgroundColor: ['#0dcaf0', '#6c757d']
                }]
            },
            options: {responsive: true, plugins: {legend: {position: 'bottom'}}}
        });
    }
})();
</script>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>