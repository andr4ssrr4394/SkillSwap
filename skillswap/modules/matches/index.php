<?php
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Bidirectional matches: user offers what I want AND wants what I offer
$matches = $conn->query("
    SELECT DISTINCT
        u.id, u.name, u.career, u.semester,
        (SELECT AVG(score) FROM ratings WHERE rated_id = u.id) as avg_rating,
        (SELECT COUNT(*) FROM ratings WHERE rated_id = u.id) as rating_count,
        (SELECT GROUP_CONCAT(DISTINCT s2.name SEPARATOR ', ')
         FROM user_skills_offered uof2 JOIN skills s2 ON uof2.skill_id = s2.id
         WHERE uof2.user_id = u.id AND uof2.skill_id IN (SELECT skill_id FROM user_skills_wanted WHERE user_id = $userId)) as they_teach_me,
        (SELECT GROUP_CONCAT(DISTINCT s3.name SEPARATOR ', ')
         FROM user_skills_wanted uwa3 JOIN skills s3 ON uwa3.skill_id = s3.id
         WHERE uwa3.user_id = u.id AND uwa3.skill_id IN (SELECT skill_id FROM user_skills_offered WHERE user_id = $userId)) as they_want_from_me
    FROM users u
    WHERE u.id != $userId
      AND EXISTS (SELECT 1 FROM user_skills_offered ofr WHERE ofr.user_id = u.id AND ofr.skill_id IN (SELECT skill_id FROM user_skills_wanted WHERE user_id = $userId))
      AND EXISTS (SELECT 1 FROM user_skills_wanted w WHERE w.user_id = u.id AND w.skill_id IN (SELECT skill_id FROM user_skills_offered WHERE user_id = $userId))
    ORDER BY avg_rating DESC, u.name
");

// One-way: they can teach me (offer what I want) but I have nothing to trade
$oneWay = $conn->query("
    SELECT DISTINCT
        u.id, u.name, u.career,
        (SELECT AVG(score) FROM ratings WHERE rated_id = u.id) as avg_rating,
        (SELECT GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ')
         FROM user_skills_offered uof JOIN skills s ON uof.skill_id = s.id
         WHERE uof.user_id = u.id AND uof.skill_id IN (SELECT skill_id FROM user_skills_wanted WHERE user_id = $userId)) as they_teach_me
    FROM users u
    WHERE u.id != $userId
      AND EXISTS (SELECT 1 FROM user_skills_offered ofr WHERE ofr.user_id = u.id AND ofr.skill_id IN (SELECT skill_id FROM user_skills_wanted WHERE user_id = $userId))
      AND NOT EXISTS (SELECT 1 FROM user_skills_wanted w WHERE w.user_id = u.id AND w.skill_id IN (SELECT skill_id FROM user_skills_offered WHERE user_id = $userId))
    ORDER BY avg_rating DESC, u.name
");
?>

<div class="thread-header mb-4">
    <h2><i class="bi bi-lightning"></i> Matches</h2>
    <p class="text-muted">Estudiantes con habilidades complementarias a las tuyas.</p>
</div>

<?php if ($matches->num_rows > 0): ?>
    <h5 class="mb-3"><span class="text-muted" style="font-size:0.85rem">MATCHES BIDIRECCIONALES</span></h5>
    <div class="row g-3 mb-4">
        <?php while ($m = $matches->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4">
                <div class="reddit-card match-card h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="mb-0">
                                <a href="/skillswap/modules/profile/view.php?id=<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></a>
                            </h5>
                            <?php if ($m['avg_rating']): ?>
                                <small class="text-warning"><i class="bi bi-star-fill"></i> <?= number_format($m['avg_rating'], 1) ?></small>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small mb-2">
                        <?= htmlspecialchars($m['career'] ?? '') ?>
                        <?php if ($m['semester']): ?> · <?= $m['semester'] ?>°<?php endif; ?>
                    </p>
                    <div class="mb-2">
                        <small class="d-block fw-bold text-success">Te puede enseñar:</small>
                        <?php foreach (array_values(array_filter(explode(',', $m['they_teach_me']))) as $s): ?>
                            <span class="badge bg-success-subtle text-success-emphasis me-1"><?= htmlspecialchars(trim($s)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="mb-3">
                        <small class="d-block fw-bold text-info">Quiere aprender de ti:</small>
                        <?php foreach (array_values(array_filter(explode(',', $m['they_want_from_me']))) as $s): ?>
                            <span class="badge bg-info-subtle text-info-emphasis me-1"><?= htmlspecialchars(trim($s)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <a href="/skillswap/modules/exchanges/propose.php?user_id=<?= $m['id'] ?>" class="btn btn-orange w-100">
                        <i class="bi bi-arrow-repeat"></i> Proponer Intercambio
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No hay matches bidireccionales todavía.
        <a href="/skillswap/modules/profile/edit.php">Agrega habilidades</a> para encontrar personas compatibles.
    </div>
<?php endif; ?>

<?php if ($oneWay->num_rows > 0): ?>
    <h5 class="mb-3"><span class="text-muted" style="font-size:0.85rem">PUEDEN ENSEÑARTE</span></h5>
    <div class="row g-3">
        <?php while ($m = $oneWay->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4">
                <div class="reddit-card match-card oneway h-100">
                    <h6 class="mb-0">
                        <a href="/skillswap/modules/profile/view.php?id=<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></a>
                    </h6>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($m['career'] ?? '') ?></p>
                    <small class="d-block text-success mb-2">Te puede enseñar:</small>
                    <?php foreach (array_values(array_filter(explode(',', $m['they_teach_me']))) as $s): ?>
                        <span class="badge bg-success-subtle text-success-emphasis me-1"><?= htmlspecialchars(trim($s)) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
?>