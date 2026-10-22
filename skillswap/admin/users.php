<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();
$me = getCurrentUser();
$iAmSuper = isSuperAdminUser($me);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $uid = intval($_POST['user_id'] ?? 0);

    if ($uid > 0) {
        $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$target) {
            flash('error', 'Usuario no encontrado.');
        } elseif ($action === 'delete') {
            // Solo el admin principal elimina usuarios (nunca cuentas admin).
            if (!$iAmSuper) {
                flash('error', 'Solo el admin principal puede eliminar usuarios.');
            } elseif ($target['role'] === 'admin') {
                flash('error', 'Quita primero sus permisos de admin para eliminarlo.');
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->bind_param("i", $uid);
                $stmt->execute();
                $stmt->close();
                flash('success', 'Usuario eliminado.');
            }
        } elseif ($action === 'promote') {
            // Nombrar administrador: solo el admin principal (con confirmación).
            if (!$iAmSuper) {
                flash('error', 'Solo el admin principal puede nombrar administradores.');
            } elseif (isSuperAdminUser($target)) {
                flash('error', 'Esa cuenta ya es el admin principal.');
            } elseif ($target['role'] === 'admin') {
                flash('error', 'Ya es administrador.');
            } else {
                $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ? AND role != 'admin'");
                $stmt->bind_param("i", $uid);
                $stmt->execute();
                $stmt->close();
                flash('success', $target['name'] . ' ahora es administrador.');
            }
        } elseif ($action === 'demote') {
            // Quitar admin: puede hacerlo el admin principal, o el propio admin
            // para renunciar (siempre que no se pierda el ultimo admin).
            $renounce = ((int)$target['id'] === (int)$me['id']);
            if (isSuperAdminUser($target)) {
                flash('error', 'La cuenta principal de SkillSwap no puede perder los permisos de admin.');
            } elseif (!$renounce && !$iAmSuper) {
                flash('error', 'Solo el admin principal puede quitar permisos de admin.');
            } elseif ($target['role'] !== 'admin') {
                flash('error', 'Ese usuario no es administrador.');
            } else {
                $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND id != ?");
                $stmt->bind_param("i", $uid);
                $stmt->execute();
                $others = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
                $stmt->close();
                if ($others === 0) {
                    flash('error', 'No puede quedar el sistema sin administradores.');
                } else {
                    $stmt = $conn->prepare("UPDATE users SET role = 'student' WHERE id = ? AND role = 'admin'");
                    $stmt->bind_param("i", $uid);
                    $stmt->execute();
                    $stmt->close();
                    flash('success', $renounce
                        ? 'Ya no eres administrador.'
                        : $target['name'] . ' dejó de ser administrador.');
                }
            }
        } elseif ($action === 'reset_password') {
            if (!$iAmSuper) {
                flash('error', 'Solo el admin principal puede restablecer contraseñas.');
            } elseif ($target['role'] === 'admin') {
                flash('error', 'No puedes reiniciar la contraseña de un administrador.');
            } else {
                $temp = bin2hex(random_bytes(4));
                $hash = password_hash($temp, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hash, $uid);
                $stmt->execute();
                $stmt->close();
                flash('success', 'Contraseña reiniciada. Contraseña temporal del usuario #' . $uid . ': ' . $temp);
            }
        }
    }
    redirect('/skillswap/admin/users.php');
}

$pageTitle = 'Gestionar usuarios';
$pageDescription = 'Gestiona los usuarios de SkillSwap: consulta cuentas, promueve o quita administradores, y administra el estado de cada estudiante.';
require_once __DIR__ . '/../includes/header.php';

$page = max(1, intval($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$totalUsers = (int)$conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$pages = max(1, (int)ceil($totalUsers / $perPage));
if ($page > $pages) $page = $pages;

$users = $conn->query("SELECT u.*, 
    (SELECT COUNT(*) FROM exchanges WHERE proposer_id = u.id OR acceptor_id = u.id) as exchange_count,
    (SELECT AVG(score) FROM ratings WHERE rated_id = u.id) as avg_rating
    FROM users u ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset");
?>

<h2><i class="bi bi-people"></i> Gestionar Usuarios</h2>
<hr>

<div class="alert alert-info small">
    <i class="bi bi-diagram-3"></i>
    <strong>Jerarquía:</strong> el <strong>admin principal</strong> (cuenta de SkillSwap) es quien nombra y quita
    administradores, elimina usuarios y restablece contraseñas. Un <strong>admin</strong> normal gestiona el panel,
    los reportes y las categorías, pero no toca roles ni borra usuarios.
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <?php if ($iAmSuper): ?>
        <span class="small text-muted"><i class="bi bi-star-fill text-warning"></i> Eres el admin principal</span>
    <?php else: ?>
        <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="demote">
            <input type="hidden" name="user_id" value="<?= (int)$me['id'] ?>">
            <button class="btn btn-sm btn-outline-warning"
                    data-confirm="¿Dejar de ser administrador? Perderás el acceso al panel de administración.">
                <i class="bi bi-person-dash"></i> Dejar de ser admin
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$iAmSuper): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-eye"></i> Estás viendo los usuarios en <strong>modo lectura</strong>. Solo el admin
        principal puede modificar roles, restablecer contraseñas o eliminar usuarios.
    </div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Carrera</th>
                <th>Horas</th>
                <th>Intercambios</th>
                <th>Rating</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($u = $users->fetch_assoc()): ?>
                <?php $isPrincipal = isSuperAdminUser($u); ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['career'] ?? '-') ?></td>
                    <td><?= formatHours($u['hours_balance']) ?></td>
                    <td><?= $u['exchange_count'] ?></td>
                    <td><?= $u['avg_rating'] ? number_format($u['avg_rating'], 1) : '-' ?></td>
                    <td>
                        <?php if ($isPrincipal): ?>
                            <span class="badge bg-danger"><i class="bi bi-star-fill"></i> Admin principal</span>
                        <?php elseif ($u['role'] === 'admin'): ?>
                            <span class="badge bg-warning text-dark">Admin</span>
                        <?php else: ?>
                            <span class="badge bg-primary">Estudiante</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isPrincipal): ?>
                            <span class="text-muted small"><i class="bi bi-lock"></i> Cuenta principal</span>
                        <?php elseif ($iAmSuper && $u['role'] !== 'admin'): ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="promote">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-warning"
                                        title="Hacer admin"
                                        data-confirm="¿Hacer administrador a <?= htmlspecialchars($u['name']) ?>? Podrá gestionar el panel, pero no roles de otros usuarios.">
                                    <i class="bi bi-shield-check"></i> Hacer admin
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" title="Generar contraseña temporal"
                                        data-confirm="¿Generar una nueva contraseña temporal para este usuario?">
                                    <i class="bi bi-key"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar este usuario?">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        <?php elseif ($iAmSuper && $u['role'] === 'admin'): ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="demote">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-warning"
                                        title="Quitar permisos de admin"
                                        data-confirm="¿Quitar los permisos de admin a <?= htmlspecialchars($u['name']) ?>? Volverá a ser estudiante.">
                                    <i class="bi bi-shield-x"></i> Quitar admin
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small"><i class="bi bi-eye"></i> Solo lectura</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <nav>
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?p=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>
