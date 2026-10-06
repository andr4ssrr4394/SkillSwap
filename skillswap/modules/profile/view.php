<?php
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$conn = getConnection();
$viewerId = $_SESSION['user_id'];
$profileId = intval($_GET['id'] ?? 0);

if ($profileId === 0 || $profileId === $viewerId) {
    redirect('/skillswap/modules/profile/index.php');
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $profileId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    flash('error', 'Usuario no encontrado.');
    redirect('/skillswap/modules/dashboard/index.php');
}

$offeredStmt = $conn->prepare("
    SELECT uso.*, s.name as skill_name, c.name as category_name, c.icon
    FROM user_skills_offered uso
    JOIN skills s ON uso.skill_id = s.id
    LEFT JOIN categories c ON s.category_id = c.id
    WHERE uso.user_id = ?
");
$offeredStmt->bind_param("i", $profileId);
$offeredStmt->execute();
$offered = $offeredStmt->get_result();
$offeredStmt->close();

$wantedStmt = $conn->prepare("
    SELECT usw.*, s.name as skill_name, c.name as category_name, c.icon
    FROM user_skills_wanted usw
    JOIN skills s ON usw.skill_id = s.id
    LEFT JOIN categories c ON s.category_id = c.id
    WHERE usw.user_id = ?
");
$wantedStmt->bind_param("i", $profileId);
$wantedStmt->execute();
$wanted = $wantedStmt->get_result();
$wantedStmt->close();

$rating = getUserRating($conn, $profileId);

$certStmt = $conn->prepare("
    SELECT c.*, s.name as skill_name
    FROM certificates c
    LEFT JOIN skills s ON c.skill_id = s.id
    WHERE c.user_id = ?
    ORDER BY c.issue_date DESC, c.created_at DESC
");
$certStmt->bind_param("i", $profileId);
$certStmt->execute();
$profileCerts = $certStmt->get_result();
$certStmt->close();

$ratingsStmt = $conn->prepare("
    SELECT r.*, u.name as rater_name
    FROM ratings r
    JOIN users u ON r.rater_id = u.id
    WHERE r.rated_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$ratingsStmt->bind_param("i", $profileId);
$ratingsStmt->execute();
$ratings = $ratingsStmt->get_result();
$ratingsStmt->close();

$badges = getUserBadges($conn, $profileId);
$profileAvatar = getAvatarUrl($user);
?>

<div class="profile-header">
    <div class="row align-items-center">
        <div class="col-md-8 d-flex align-items-center gap-3">
            <?php if ($profileAvatar): ?>
                <img src="<?= $profileAvatar ?>" alt="Avatar" class="rounded-circle border" style="width:72px; height:72px; object-fit:cover;">
            <?php else: ?>
                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:72px; height:72px; font-size:1.4rem; font-weight:bold;">
                    <?= htmlspecialchars(getUserInitials($user['name'])) ?>
                </div>
            <?php endif; ?>
            <div>
                <h2 class="mb-1"><?= htmlspecialchars($user['name']) ?></h2>
                <p class="mb-1"><i class="bi bi-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                <?php if ($user['career']): ?>
                    <p class="mb-1">
                        <i class="bi bi-mortarboard"></i> <?= htmlspecialchars($user['career']) ?>
                        <?php if ($user['semester']): ?> - <?= $user['semester'] ?>° Semestre<?php endif; ?>
                    </p>
                <?php endif; ?>
                <p class="mb-1"><i class="bi bi-calendar"></i> Miembro desde <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
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
        </div>
    </div>

    <?php if (!empty($badges)): ?>
        <div class="mt-3">
            <?php foreach ($badges as $badge): ?>
                <span class="badge <?= $badge['class'] ?> me-1" title="<?= htmlspecialchars($badge['desc']) ?>">
                    <i class="bi <?= $badge['icon'] ?>"></i> <?= htmlspecialchars($badge['name']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="text-end mb-3">
    <a href="/skillswap/modules/exchanges/propose.php?user_id=<?= $profileId ?>" class="btn btn-success">
        <i class="bi bi-arrow-left-right"></i> Proponer Intercambio
    </a>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-mortarboard"></i> Ofrece (<?= $offered->num_rows ?>)</h5>
            </div>
            <div class="card-body">
                <?php while ($s = $offered->fetch_assoc()): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <strong><?= htmlspecialchars($s['skill_name']) ?></strong>
                            <br><small class="text-muted"><i class="bi <?= $s['icon'] ?>"></i> <?= htmlspecialchars($s['category_name']) ?></small>
                            <?php if ($s['description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($s['description']) ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="badge <?= getLevelBadgeClass($s['level']) ?>"><?= getLevelLabel($s['level']) ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-search"></i> Busca (<?= $wanted->num_rows ?>)</h5>
            </div>
            <div class="card-body">
                <?php while ($s = $wanted->fetch_assoc()): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <strong><?= htmlspecialchars($s['skill_name']) ?></strong>
                            <br><small class="text-muted"><i class="bi <?= $s['icon'] ?>"></i> <?= htmlspecialchars($s['category_name']) ?></small>
                        </div>
                        <span class="badge <?= getLevelBadgeClass($s['level']) ?>"><?= getLevelLabel($s['level']) ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($profileCerts->num_rows > 0): ?>
<div class="card mb-4">
    <div class="card-header" style="background:var(--reddit-gold)">
        <h5 class="mb-0"><i class="bi bi-patch-check"></i> Certificados</h5>
    </div>
    <div class="card-body">
        <?php while ($c = $profileCerts->fetch_assoc()): ?>
            <div class="border-bottom py-2">
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
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($ratings->num_rows > 0): ?>
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-star"></i> Reseñas Recientes</h5></div>
    <div class="card-body">
        <?php while ($r = $ratings->fetch_assoc()): ?>
            <div class="border-bottom py-2">
                <div class="d-flex justify-content-between">
                    <strong><?= htmlspecialchars($r['rater_name']) ?></strong>
                    <span class="rating-stars"><?= renderStars($r['score']) ?></span>
                </div>
                <?php if ($r['review']): ?>
                    <p class="mb-0 text-muted"><?= htmlspecialchars($r['review']) ?></p>
                <?php endif; ?>
                <small class="text-muted"><?= timeAgo($r['created_at']) ?></small>
            </div>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
?>
