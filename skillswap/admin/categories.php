<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'bi-book');
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $icon);
            $stmt->execute();
            $stmt->close();
            flash('success', 'Categoría creada.');
        }
    } elseif ($action === 'add_skill') {
        $categoryId = intval($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!empty($name) && $categoryId > 0) {
            $stmt = $conn->prepare("INSERT INTO skills (category_id, name) VALUES (?, ?)");
            $stmt->bind_param("is", $categoryId, $name);
            $stmt->execute();
            $stmt->close();
            flash('success', 'Habilidad creada.');
        }
    } else    if ($action === 'delete_category') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Categoría eliminada.');
    } elseif ($action === 'delete_skill') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM skills WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Habilidad eliminada.');
    }
    redirect('/skillswap/admin/categories.php');
}

require_once __DIR__ . '/../includes/header.php';

$categories = $conn->query("SELECT c.*, COUNT(s.id) as skill_count FROM categories c LEFT JOIN skills s ON s.category_id = c.id GROUP BY c.id ORDER BY c.name");
$allSkills = $conn->query("SELECT s.*, c.name as category_name FROM skills s LEFT JOIN categories c ON s.category_id = c.id ORDER BY c.name, s.name");
?>

<h2><i class="bi bi-tags"></i> Categorías y Habilidades</h2>
<hr>

<div class="row">
    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Categorías</h5></div>
            <div class="card-body">
                <form method="POST" class="row g-2 mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_category">
                    <div class="col-7">
                        <input type="text" class="form-control" name="name" placeholder="Nombre" required>
                    </div>
                    <div class="col-3">
                        <input type="text" class="form-control" name="icon" placeholder="bi-book" value="bi-book">
                    </div>
                    <div class="col-2">
                        <button class="btn btn-success w-100"><i class="bi bi-plus"></i></button>
                    </div>
                </form>
                <table class="table table-sm">
                    <?php while ($c = $categories->fetch_assoc()): ?>
                        <tr>
                            <td><i class="bi <?= $c['icon'] ?>"></i> <?= htmlspecialchars($c['name']) ?></td>
                            <td class="text-muted"><?= $c['skill_count'] ?> skills</td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar categoría?">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Habilidades</h5></div>
            <div class="card-body">
                <form method="POST" class="row g-2 mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_skill">
                    <div class="col-5">
                        <select name="category_id" class="form-select" required>
                            <option value="">Categoría...</option>
                            <?php
                            $categories->data_seek(0);
                            while ($c = $categories->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-5">
                        <input type="text" class="form-control" name="name" placeholder="Nombre habilidad" required>
                    </div>
                    <div class="col-2">
                        <button class="btn btn-success w-100"><i class="bi bi-plus"></i></button>
                    </div>
                </form>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm">
                        <?php while ($s = $allSkills->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($s['category_name']) ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_skill">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar habilidad?">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>
