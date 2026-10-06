<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$pendingCount = 0;
$unreadCount = 0;
$recentNotifs = [];
$userAvatar = null;
if ($currentUser) {
    $hc = getConnection();
    $hs = $hc->prepare("SELECT COUNT(*) c FROM exchanges WHERE (proposer_id = ? OR acceptor_id = ?) AND status = 'pending'");
    $hs->bind_param("ii", $currentUser['id'], $currentUser['id']);
    $hs->execute();
    $pendingCount = (int)$hs->get_result()->fetch_assoc()['c'];
    $hs->close();

    // La campana es opcional: si la tabla `notifications` no existe (migración no aplicada),
    // el resto de la página debe seguir funcionando con normalidad.
    try {
        $unreadCount = getUnreadNotificationCount($hc, $currentUser['id']);
        $recentNotifs = getRecentNotifications($hc, $currentUser['id'], 5);
    } catch (Throwable $e) {
        error_log('SkillSwap notificaciones no disponibles: ' . $e->getMessage());
        $unreadCount = 0;
        $recentNotifs = [];
    }
    $hc->close();
    $userAvatar = getAvatarUrl($currentUser);
}
$pageTitle = $pageTitle ?? 'SkillSwap';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - SkillSwap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/skillswap/assets/css/style.css?v=2" rel="stylesheet">
    <script>
        (function() {
            var theme = localStorage.getItem('skillswap-theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="/skillswap/">
                <i class="bi bi-arrow-repeat brand-accent me-1"></i>
                <span>skill<span class="brand-accent">swap</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if ($currentUser): ?>
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="/skillswap/modules/dashboard/index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/skillswap/modules/skills/index.php">Explorar</a></li>
                    <li class="nav-item"><a class="nav-link" href="/skillswap/modules/matches/index.php">Matches</a></li>
                    <li class="nav-item"><a class="nav-link" href="/skillswap/modules/exchanges/index.php">
                        <i class="bi bi-arrow-repeat"></i> Intercambios
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge bg-warning text-dark ms-1"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a></li>
                    <li class="nav-item"><a class="nav-link" href="/skillswap/modules/messages/index.php"><i class="bi bi-chat-left-text"></i> Mensajes</a></li>
                </ul>
                <ul class="navbar-nav align-items-center gap-2">
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative p-2" href="#" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones" id="notifDropdownToggle">
                            <i class="bi bi-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifBadge" style="font-size:0.65rem;"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 300px; max-width: 360px;">
                            <li class="dropdown-header d-flex justify-content-between align-items-center py-2">
                                <span class="fw-bold">Notificaciones</span>
                                <?php if ($unreadCount > 0): ?>
                                    <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none primary small" id="markAllRead">Marcar leídas</button>
                                <?php endif; ?>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <?php if (!empty($recentNotifs)): ?>
                                <?php foreach ($recentNotifs as $notif): ?>
                                    <li>
                                        <a class="dropdown-item py-2 notif-item <?= empty($notif['is_read']) ? 'bg-light fw-semibold' : 'text-muted' ?>"
                                           href="<?= !empty($notif['link']) ? htmlspecialchars($notif['link']) : '#' ?>">
                                            <div class="small fw-bold text-dark"><?= htmlspecialchars($notif['title']) ?></div>
                                            <div class="small text-truncate" style="max-width: 280px;"><?= htmlspecialchars($notif['message']) ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?= timeAgo($notif['created_at']) ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="p-3 text-center text-muted small">No tienes notificaciones.</li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <button class="theme-toggle" type="button" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                            <i class="bi bi-moon-stars" id="themeIcon"></i>
                        </button>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link fw-bold" style="color: var(--reddit-orange) !important;">
                            <i class="bi bi-clock-history"></i> <?= formatHours($currentUser['hours_balance']) ?>
                        </span>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                            <?php if ($userAvatar): ?>
                                <img src="<?= $userAvatar ?>" alt="Avatar" class="rounded-circle" style="width:28px; height:28px; object-fit:cover;">
                            <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($currentUser['name']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/skillswap/modules/profile/index.php"><i class="bi bi-person"></i> Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="/skillswap/modules/profile/edit.php"><i class="bi bi-pencil"></i> Editar Perfil</a></li>
                            <li><a class="dropdown-item" href="/skillswap/modules/profile/certificate.php" target="_blank"><i class="bi bi-file-earmark-text"></i> Constancia de Horas</a></li>
                            <?php if ($currentUser['role'] === 'admin'): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/skillswap/admin/index.php"><i class="bi bi-shield-lock"></i> Admin</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/skillswap/modules/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
                <?php else: ?>
                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    <li class="nav-item">
                        <button class="theme-toggle" type="button" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                            <i class="bi bi-moon-stars" id="themeIcon"></i>
                        </button>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="/skillswap/modules/auth/login.php">Iniciar Sesión</a></li>
                    <li class="nav-item">
                        <a class="btn btn-orange" href="/skillswap/modules/auth/register.php">Registrarse</a>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('markAllRead');
        if (!btn) return;
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var body = new URLSearchParams();
            body.append('action', 'mark_all_read');
            body.append('csrf_token', '<?= csrf_token() ?>');
            fetch('/skillswap/api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString()
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data.success) return;
                var badge = document.getElementById('notifBadge');
                if (badge) badge.remove();
                document.querySelectorAll('#notifDropdownToggle + .dropdown-menu .notif-item').forEach(function (el) {
                    el.classList.remove('bg-light', 'fw-semibold');
                    el.classList.add('text-muted');
                });
            }).catch(function (err) { console.error(err); });
        });
    });
    </script>

    <main class="container py-4">
        <?php
        $error = flash('error');
        $success = flash('success');
        if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif;
        if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
