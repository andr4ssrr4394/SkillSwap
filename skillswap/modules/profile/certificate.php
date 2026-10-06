<?php
// Constancia oficial de horas del usuario (página imprimible / PDF desde el navegador).
// Es un documento GENERADO por el sistema a partir de los intercambios completados;
// es distinto a los "certificados" de evidencia que el usuario registra a mano.
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$conn = getConnection();
$userId = (int)$_SESSION['user_id'];
$user = getCurrentUser();

$certCode = 'CERT-SWAP-' . strtoupper(substr(hash('sha256', $userId . '|' . $user['created_at']), 0, 8))
            . '-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);

// Horas teaching (enseña) y learning (aprende) en intercambios completados
$stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN proposer_id = ? THEN proposer_hours ELSE acceptor_hours END) AS taught,
        SUM(CASE WHEN proposer_id = ? THEN acceptor_hours ELSE proposer_hours END) AS learned,
        COUNT(*) AS completed
    FROM exchanges
    WHERE (proposer_id = ? OR acceptor_id = ?) AND status = 'completed'
");
$stmt->bind_param("iiii", $userId, $userId, $userId, $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

$taught = (float)($stats['taught'] ?? 0);
$learned = (float)($stats['learned'] ?? 0);
$completed = (int)($stats['completed'] ?? 0);

// Habilidades impartidas en intercambios completados
$stmt = $conn->prepare("
    SELECT DISTINCT s.name AS skill_name, c.name AS category_name
    FROM exchanges e
    JOIN skills s ON s.id = CASE WHEN e.proposer_id = ? THEN e.skill_offered_id ELSE e.skill_requested_id END
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE (e.proposer_id = ? OR e.acceptor_id = ?) AND e.status = 'completed'
      AND (CASE WHEN e.proposer_id = ? THEN e.skill_offered_id ELSE e.skill_requested_id END) IS NOT NULL
    ORDER BY s.name
");
$stmt->bind_param("iiii", $userId, $userId, $userId, $userId);
$stmt->execute();
$taughtSkills = $stmt->get_result();
$stmt->close();

$rating = getUserRating($conn, $userId);
$badges = getUserBadges($conn, $userId);
$avatarUrl = getAvatarUrl($user);
$conn->close();

$issuedAt = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Constancia de Horas - <?= htmlspecialchars($user['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #eef1f5; color: #1f2933; }
        .cert-sheet {
            max-width: 860px;
            margin: 28px auto;
            background: #fff;
            border: 10px solid #e3e8ef;
            padding: 48px 56px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }
        .cert-head { border-bottom: 3px solid var(--reddit-orange, #ff4500); padding-bottom: 18px; }
        .cert-title { letter-spacing: 2px; }
        .cert-name { font-family: Georgia, 'Times New Roman', serif; }
        .cert-seal {
            border: 3px dashed var(--reddit-orange, #ff4500);
            border-radius: 50%;
            width: 108px; height: 108px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: var(--reddit-orange, #ff4500);
            font-weight: 700; font-size: .72rem; text-transform: uppercase; letter-spacing: 1px;
            transform: rotate(-8deg); margin: 0 auto;
        }
        .cert-stat { border-left: 1px solid #dde3ea; }
        .cert-skill { border-bottom: 1px dashed #dde3ea; padding: 6px 0; }
        .cert-signature { border-top: 2px solid #1f2933; padding-top: 6px; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .cert-sheet { border: 4px solid #ccc; box-shadow: none; margin: 0; padding: 24px 32px; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container no-print py-3">
    <div class="d-flex justify-content-between align-items-center">
        <a href="/skillswap/modules/profile/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al Perfil
        </a>
        <div>
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="bi bi-printer-fill"></i> Imprimir / Guardar como PDF
            </button>
        </div>
    </div>
</div>

<div class="cert-sheet">
    <div class="cert-head d-flex justify-content-between align-items-start">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-arrow-repeat"></i> skill<span style="color:var(--reddit-orange,#ff4500)">swap</span></h3>
            <small class="text-muted text-uppercase">Plataforma universitaria de aprendizaje colaborativo</small>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border"><?= htmlspecialchars($certCode) ?></span>
            <div class="small text-muted mt-1">Emisión: <?= $issuedAt ?></div>
        </div>
    </div>

    <div class="text-center my-4">
        <h5 class="text-uppercase text-secondary cert-title">Constancia de horas de intercambio</h5>
        <p class="text-muted mb-2">Se hace constar que el estudiante:</p>
        <div class="d-flex justify-content-center">
            <?php if ($avatarUrl): ?>
                <img src="<?= $avatarUrl ?>" alt="" class="rounded-circle border me-3" style="width:76px; height:76px; object-fit:cover;">
            <?php endif; ?>
            <h1 class="cert-name fw-bold my-0 align-self-center"><?= htmlspecialchars($user['name']) ?></h1>
        </div>
        <p class="fs-6 mt-3 mb-0">
            <?php if (!empty($user['career'])): ?>
                Estudiante de <strong><?= htmlspecialchars($user['career']) ?></strong><?php if (!empty($user['semester'])): ?> (semestre <?= (int)$user['semester'] ?>)<?php endif; ?>,
            <?php endif; ?>
            identificado con <strong><?= htmlspecialchars($user['email']) ?></strong>,
            miembro de SkillSwap desde el <?= date('d/m/Y', strtotime($user['created_at'])) ?>.
        </p>
    </div>

    <div class="row g-0 my-4 text-center">
        <div class="col-4 cert-stat">
            <div class="fs-3 fw-bold text-primary"><?= formatHours($taught) ?></div>
            <div class="text-muted small text-uppercase">Horas impartidas</div>
        </div>
        <div class="col-4 cert-stat">
            <div class="fs-3 fw-bold text-success"><?= formatHours($learned) ?></div>
            <div class="text-muted small text-uppercase">Horas aprendidas</div>
        </div>
        <div class="col-4">
            <div class="fs-3 fw-bold text-dark"><?= $completed ?></div>
            <div class="text-muted small text-uppercase">Intercambios completados</div>
        </div>
    </div>

    <?php if ($taughtSkills && $taughtSkills->num_rows > 0): ?>
        <div class="my-4">
            <h6 class="fw-bold border-bottom pb-2">Habilidades impartidas y certificadas</h6>
            <div class="row g-1 mt-1">
                <?php while ($sk = $taughtSkills->fetch_assoc()): ?>
                    <div class="col-6">
                        <i class="bi bi-check2-circle text-success me-1"></i>
                        <strong><?= htmlspecialchars($sk['skill_name']) ?></strong>
                        <?php if ($sk['category_name']): ?>
                            <span class="text-muted small">(<?= htmlspecialchars($sk['category_name']) ?>)</span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($badges)): ?>
        <div class="my-4">
            <h6 class="fw-bold border-bottom pb-2">Reconocimientos</h6>
            <?php foreach ($badges as $badge): ?>
                <span class="badge <?= $badge['class'] ?> me-1">
                    <i class="bi <?= $badge['icon'] ?>"></i> <?= htmlspecialchars($badge['name']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-2 my-4 text-center small">
        <div class="col-4">
            <div class="text-muted text-uppercase">Calificación promedio</div>
            <strong>
                <?= (int)$rating['total'] > 0 ? number_format((float)$rating['avg_rating'], 1) . ' / 5' : 'Sin valoraciones' ?>
            </strong>
            (<?= (int)$rating['total'] ?> reseñas)
        </div>
        <div class="col-4">
            <div class="text-muted text-uppercase">Saldo actual</div>
            <strong><?= formatHours($user['hours_balance']) ?></strong>
        </div>
        <div class="col-4">
            <div class="text-muted text-uppercase">Código de verificación</div>
            <strong style="font-size:.75rem;"><?= htmlspecialchars($certCode) ?></strong>
        </div>
    </div>

    <p class="small text-muted text-center mt-4">
        Documento generado automáticamente por el sistema de banco de tiempo SkillSwap a partir de los
        intercambios con confirmación mutua y doble validación de horas. No requiere firma manuscrita.
    </p>

    <div class="row align-items-center pt-4 border-top mt-4">
        <div class="col-6 text-center">
            <div class="cert-seal">
                <i class="bi bi-shield-check fs-2"></i>
                <span>Validado</span>
                <span style="font-size:.55rem;"><?= date('Y') ?></span>
            </div>
        </div>
        <div class="col-6 text-center">
            <div class="cert-signature mx-auto" style="width:220px;">
                <span class="small fw-bold">Comité de Desarrollo Académico</span>
                <div class="text-muted" style="font-size:.75rem;">SkillSwap Campus</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>