<?php
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];

$tab = $_GET['tab'] ?? 'learn';
if (!in_array($tab, ['learn', 'teach'])) $tab = 'learn';

$search = trim($_GET['q'] ?? '');
$category = intval($_GET['category'] ?? 0);
$level = $_GET['level'] ?? '';

$page = max(1, intval($_GET['p'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$whereExtra = '';
if (!empty($search)) $whereExtra .= " AND (s.name LIKE ? OR c.name LIKE ?)";
if ($category > 0) $whereExtra .= " AND c.id = ?";
if (!empty($level)) $whereExtra .= " AND " . ($tab === 'learn' ? 'uso.level' : 'mio.level') . " = ?";

if ($tab === 'learn') {
    $aliases = 'uso.*, s.name as skill_name, c.name as category_name, c.id as category_id, c.icon, u.name as user_name, u.id as uid, (SELECT AVG(r.score) FROM ratings r WHERE r.rated_id = u.id) as avg_rating';
    $from = "
        FROM user_skills_offered uso
        JOIN skills s ON uso.skill_id = s.id
        LEFT JOIN categories c ON s.category_id = c.id
        JOIN users u ON uso.user_id = u.id
        WHERE uso.user_id != ? AND uso.skill_id IN (SELECT skill_id FROM user_skills_wanted WHERE user_id=?)";
    $countQuery = "SELECT COUNT(*) c FROM user_skills_offered uso
        JOIN skills s ON uso.skill_id = s.id
        LEFT JOIN categories c ON s.category_id = c.id
        JOIN users u ON uso.user_id = u.id
        WHERE uso.user_id != ? AND uso.skill_id IN (SELECT skill_id FROM user_skills_wanted WHERE user_id=?)" . $whereExtra;
    $types = 'ii';
    $values = [$userId, $userId];
} else {
    $aliases = 'uw.skill_id, s.name as skill_name, c.name as category_name, c.id as category_id, c.icon, u.name as user_name, u.id as uid, mio.level, (SELECT AVG(r.score) FROM ratings r WHERE r.rated_id = u.id) as avg_rating';
    $from = "
        FROM user_skills_wanted uw
        JOIN skills s ON uw.skill_id = s.id
        LEFT JOIN categories c ON s.category_id = c.id
        JOIN users u ON uw.user_id = u.id
        JOIN user_skills_offered mio ON mio.user_id = ? AND mio.skill_id = uw.skill_id
        WHERE uw.user_id != ?" . $whereExtra;
    $countQuery = "SELECT COUNT(*) c FROM user_skills_wanted uw
        JOIN skills s ON uw.skill_id = s.id
        LEFT JOIN categories c ON s.category_id = c.id
        JOIN users u ON uw.user_id = u.id
        JOIN user_skills_offered mio ON mio.user_id = ? AND mio.skill_id = uw.skill_id
        WHERE uw.user_id != ?" . $whereExtra;
    $types = 'ii';
    $values = [$userId, $userId];
}

$countParams = array_merge($values,
    !empty($search) ? ["%$search%", "%$search%"] : [],
    $category > 0 ? [$category] : [],
    !empty($level) ? [$level] : []);
$cTypeStr = $types . (!empty($search) ? 'ss' : '') . ($category > 0 ? 'i' : '') . (!empty($level) ? 's' : '');

$stmt = $conn->prepare($countQuery);
if ($cTypeStr !== '') $stmt->bind_param($cTypeStr, ...$countParams);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;

$dataQuery = "SELECT $aliases $from" . ($tab === 'learn' ? $whereExtra : '') . " ORDER BY avg_rating DESC, skill_name ASC LIMIT $perPage OFFSET $offset";

$dataParams = array_merge($values,
    !empty($search) ? ["%$search%", "%$search%"] : [],
    $category > 0 ? [$category] : [],
    !empty($level) ? [$level] : []);
$dataTypeStr = $types . (!empty($search) ? 'ss' : '') . ($category > 0 ? 'i' : '') . (!empty($level) ? 's' : '');

$stmt = $conn->prepare($dataQuery);
if ($dataTypeStr !== '') $stmt->bind_param($dataTypeStr, ...$dataParams);
$stmt->execute();
$results = $stmt->get_result();
$stmt->close();

$categories = $conn->query("SELECT * FROM categories ORDER BY name");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-search"></i> Explorar Habilidades</h2>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'learn' ? 'active' : '' ?>"
           href="?tab=learn<?= $search ? '&q=' . urlencode($search) : '' ?><?= $category ? '&category=' . $category : '' ?><?= $level ? '&level=' . urlencode($level) : '' ?>">
            <i class="bi bi-mortarboard"></i> Quiero aprender
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'teach' ? 'active' : '' ?>"
           href="?tab=teach<?= $search ? '&q=' . urlencode($search) : '' ?><?= $category ? '&category=' . $category : '' ?><?= $level ? '&level=' . urlencode($level) : '' ?>">
            <i class="bi bi-easel"></i> Puedo enseñar
        </a>
    </li>
</ul>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="<?= $tab ?>">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="q" placeholder="Nombre de habilidad..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Categoría</label>
                <select class="form-select" name="category">
                    <option value="0">Todas</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Nivel</label>
                <select class="form-select" name="level">
                    <option value="">Todos</option>
                    <option value="beginner" <?= $level === 'beginner' ? 'selected' : '' ?>>Principiante</option>
                    <option value="intermediate" <?= $level === 'intermediate' ? 'selected' : '' ?>>Intermedio</option>
                    <option value="advanced" <?= $level === 'advanced' ? 'selected' : '' ?>>Avanzado</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($tab === 'learn'): ?>
    <p class="text-muted small mb-3">
        <i class="bi bi-mortarboard"></i> Estudiantes que ofrecen las habilidades que
        <a href="/skillswap/modules/profile/edit.php">buscas</a>.
    </p>
<?php else: ?>
    <p class="text-muted small mb-3">
        <i class="bi bi-easel"></i> Estudiantes que quieren aprender lo que
        <a href="/skillswap/modules/profile/edit.php">tú ofreces</a>.
    </p>
<?php endif; ?>

<div class="row g-3">
    <?php if ($results->num_rows > 0): ?>
        <?php while ($r = $results->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card skill-card <?= $tab === 'learn' ? 'offered' : 'requested' ?> h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <h6 class="card-title mb-1">
                                <i class="bi <?= $r['icon'] ?>"></i> <?= htmlspecialchars($r['skill_name']) ?>
                            </h6>
                            <span class="badge <?= getLevelBadgeClass($r['level']) ?>"><?= getLevelLabel($r['level']) ?></span>
                        </div>
                        <span class="cat-chip c-<?= (int)$r['category_id'] ?>"><?= htmlspecialchars($r['category_name']) ?></span>
                        <?php if (!empty($r['description'])): ?>
                            <p class="text-muted mt-2 mb-2 small"><?= htmlspecialchars($r['description']) ?></p>
                        <?php endif; ?>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="/skillswap/modules/profile/view.php?id=<?= $r['uid'] ?>" class="text-decoration-none">
                                <i class="bi bi-person"></i> <?= htmlspecialchars($r['user_name']) ?>
                                <?php if ($r['avg_rating']): ?>
                                    <small class="text-warning">
                                        <i class="bi bi-star-fill"></i> <?= number_format($r['avg_rating'], 1) ?>
                                    </small>
                                <?php endif; ?>
                            </a>
                            <?php if ($tab === 'learn'): ?>
                                <a href="/skillswap/modules/exchanges/propose.php?user_id=<?= $r['uid'] ?>&skill_id=<?= $r['skill_id'] ?>"
                                   class="btn btn-sm btn-success">
                                    <i class="bi bi-arrow-left-right"></i> Intercambiar
                                </a>
                            <?php else: ?>
                                <a href="/skillswap/modules/exchanges/propose.php?user_id=<?= $r['uid'] ?>&offered_skill=<?= $r['skill_id'] ?>"
                                   class="btn btn-sm btn-info">
                                    <i class="bi bi-arrow-left-right"></i> Ofrecer
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12 text-center py-5">
            <i class="bi bi-search display-1 text-muted"></i>
            <p class="text-muted mt-3">
                <?= $tab === 'learn' ? 'No hay estudiantes que ofrezcan lo que buscas con esos criterios.' : 'No hay estudiantes que quieran aprender lo que ofreces con esos criterios.' ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php
            $qs = function($p) use ($tab, $search, $category, $level) {
                $params = ['tab' => $tab, 'p' => $p];
                if ($search) $params['q'] = $search;
                if ($category) $params['category'] = $category;
                if ($level) $params['level'] = $level;
                return '?' . http_build_query($params);
            };
            ?>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $qs($page - 1) ?>">&laquo;</a>
            </li>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $qs($i) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $qs($page + 1) ?>">&raquo;</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
?>