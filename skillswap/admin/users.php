<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $uid = intval($_POST['user_id'] ?? 0);

    if ($action === 'delete' && $uid > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Usuario eliminado.');
    } elseif ($action === 'toggle_admin' && $uid > 0) {
        $stmt = $conn->prepare("UPDATE users SET role = IF(role='admin','student','admin') WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Rol actualizado.');
    } elseif ($action === 'reset_password' && $uid > 0) {
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $role = $stmt->get_result()->fetch_assoc()['role'] ?? '';
        $stmt->close();
        if ($role === 'admin') {
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
    redirect('/skillswap/admin/users.php');
}

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
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['career'] ?? '-') ?></td>
                    <td><?= formatHours($u['hours_balance']) ?></td>
                    <td><?= $u['exchange_count'] ?></td>
                    <td><?= $u['avg_rating'] ? number_format($u['avg_rating'], 1) : '-' ?></td>
                    <td>
                        <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?>">
                            <?= $u['role'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['role'] !== 'admin'): ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_admin">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-warning" title="Hacer admin">
                                    <i class="bi bi-shield-check"></i>
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
