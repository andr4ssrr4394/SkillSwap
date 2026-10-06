<?php
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];

$user = getCurrentUser();
$offered = $conn->query("
    SELECT uso.*, s.name as skill_name, c.name as category_name, c.icon
    FROM user_skills_offered uso
    JOIN skills s ON uso.skill_id = s.id
    LEFT JOIN categories c ON s.category_id = c.id
    WHERE uso.user_id = $userId
");
$wanted = $conn->query("
    SELECT usw.*, s.name as skill_name, c.name as category_name, c.icon
    FROM user_skills_wanted usw
    JOIN skills s ON usw.skill_id = s.id
    LEFT JOIN categories c ON s.category_id = c.id
    WHERE usw.user_id = $userId
");
$rating = getUserRating($conn, $userId);
$myCerts = $conn->query("
    SELECT c.*, s.name as skill_name
    FROM certificates c
    LEFT JOIN skills s ON c.skill_id = s.id
    WHERE c.user_id = $userId
    ORDER BY c.issue_date DESC, c.created_at DESC
");
?>

<div class="profile-header">
    <div class="row align-items-center">
        <div class="col-md-8 d-flex align-items-center gap-3">
            <?php $ownAvatar = getAvatarUrl($user); ?>
            <?php if ($ownAvatar): ?>
                <img src="<?= $ownAvatar ?>" alt="Avatar" class="rounded-circle border" style="width:72px; height:72px; object-fit:cover;">
            <?php else: ?>
                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:72px; height:72px; font-size:1.4rem; font-weight:bold;">
                    <?= htmlspecialchars(getUserInitials($user['name'])) ?>
                </div>
            <?php endif; ?>
            <div>
                <h2 class="mb-1"><?= htmlspecialchars($user['name']) ?></h2>
                <p class="mb-1">
                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($user['email']) ?>
                </p>
                <?php if ($user['career']): ?>
                    <p class="mb-1">
                        <i class="bi bi-mortarboard"></i> <?= htmlspecialchars($user['career']) ?>
                        <?php if ($user['semester']): ?>
                            - <?= $user['semester'] ?>° Semestre
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <p class="mb-1">
                    <i class="bi bi-calendar"></i> Miembro desde <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                </p>
                <?php if (!empty($user['bio'])): ?>
                    <p class="mb-1"><i class="bi bi-quote"></i> <?= nl2br(htmlspecialchars($user['bio'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($user['github_url']) || !empty($user['linkedin_url'])): ?>
                    <p class="mb-0">
                        <?php if (!empty($user['github_url'])): ?>
                            <a href="<?= htmlspecialchars($user['github_url']) ?>" target="_blank" rel="noopener noreferrer" class="me-2"><i class="bi bi-github"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($user['linkedin_url'])): ?>
                            <a href="<?= htmlspecialchars($user['linkedin_url']) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-linkedin"></i></a>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="hours-badge fs-4 mb-2"><?= formatHours($user['hours_balance']) ?></div>
            <?php if ($rating['total'] > 0): ?>
                <div><?= renderStars($rating['avg_rating']) ?> <?= number_format($rating['avg_rating'], 1) ?></div>
                <small class="text-light">(<?= $rating['total'] ?> reseñas)</small>
            <?php endif; ?>
            <div class="mt-2">
                <a href="/skillswap/modules/profile/certificate.php" target="_blank" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-file-earmark-text"></i> Constancia de horas
                </a>
            </div>
        </div>
    </div>

    <?php $ownBadges = getUserBadges($conn, $userId); ?>
    <?php if (!empty($ownBadges)): ?>
        <div class="mt-3">
            <?php foreach ($ownBadges as $badge): ?>
                <span class="badge <?= $badge['class'] ?> me-1" title="<?= htmlspecialchars($badge['desc']) ?>">
                    <i class="bi <?= $badge['icon'] ?>"></i> <?= htmlspecialchars($badge['name']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-mortarboard"></i> Habilidades que Ofrezco (<?= $offered->num_rows ?>)</h5>
            </div>
            <div class="card-body">
                <?php if ($offered->num_rows > 0): ?>
                    <?php while ($s = $offered->fetch_assoc()): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <strong><?= htmlspecialchars($s['skill_name']) ?></strong>
                                <br><small class="text-muted">
                                    <i class="bi <?= $s['icon'] ?>"></i> <?= htmlspecialchars($s['category_name']) ?>
                                </small>
                                <?php if ($s['description']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($s['description']) ?></small>
                                <?php endif; ?>
                            </div>
                            <span class="badge <?= getLevelBadgeClass($s['level']) ?>">
                                <?= getLevelLabel($s['level']) ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted text-center py-3">Aún no has agregado habilidades ofrecidas</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-search"></i> Habilidades que Busco (<?= $wanted->num_rows ?>)</h5>
            </div>
            <div class="card-body">
                <?php if ($wanted->num_rows > 0): ?>
                    <?php while ($s = $wanted->fetch_assoc()): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <strong><?= htmlspecialchars($s['skill_name']) ?></strong>
                                <br><small class="text-muted">
                                    <i class="bi <?= $s['icon'] ?>"></i> <?= htmlspecialchars($s['category_name']) ?>
                                </small>
                            </div>
                            <span class="badge <?= getLevelBadgeClass($s['level']) ?>">
                                <?= getLevelLabel($s['level']) ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted text-center py-3">Aún no has agregado habilidades buscadas</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="text-center">
    <a href="/skillswap/modules/profile/edit.php" class="btn btn-primary">
        <i class="bi bi-pencil"></i> Editar Perfil y Habilidades
    </a>
</div>

<?php if ($myCerts->num_rows > 0): ?>
<div class="card mt-4">
    <div class="card-header" style="background:var(--reddit-gold)">
        <h5 class="mb-0"><i class="bi bi-patch-check"></i> Certificados</h5>
    </div>
    <div class="card-body">
        <?php while ($c = $myCerts->fetch_assoc()): ?>
            <div class="border-bottom py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($c['title']) ?></strong>
                        <?php if ($c['issuer']): ?>
                            <span class="text-muted"> — <?= htmlspecialchars($c['issuer']) ?></span>
                        <?php endif; ?>
                        <?php if ($c['skill_name']): ?>
                            <span class="badge bg-info text-white ms-1"><?= htmlspecialchars($c['skill_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($c['issue_date']): ?>
                            <br><small class="text-muted">Emitido: <?= date('d/m/Y', strtotime($c['issue_date'])) ?></small>
                        <?php endif; ?>
                        <?php if ($c['credential_url'] || $c['file_path']): ?>
                            <br>
                            <?php if ($c['credential_url']): ?>
                                <a href="<?= htmlspecialchars($c['credential_url']) ?>" target="_blank" rel="noopener" class="small"><i class="bi bi-box-arrow-up-right"></i> Ver credencial</a>
                            <?php endif; ?>
                            <?php if ($c['file_path']): ?>
                                <a href="/skillswap/modules/profile/file.php?id=<?= $c['id'] ?>" target="_blank" class="small ms-2"><i class="bi bi-file-earmark<?= str_ends_with($c['file_path'], '.pdf') ? '-pdf' : '-image' ?>"></i> Ver archivo</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
?>
