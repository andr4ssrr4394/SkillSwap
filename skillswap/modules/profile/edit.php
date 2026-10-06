<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_check();
    $action = $_POST['action'];

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $career = trim($_POST['career'] ?? '');
        $semester = intval($_POST['semester'] ?? 0);
        $bio = trim($_POST['bio'] ?? '');
        $github = trim($_POST['github_url'] ?? '');
        $linkedin = trim($_POST['linkedin_url'] ?? '');

        if (mb_strlen($name) > 150) {
            flash('error', 'El nombre no puede superar los 150 caracteres.');
            redirect('/skillswap/modules/profile/edit.php');
        }
        if (mb_strlen($bio) > 1000) {
            flash('error', 'La biografía no puede superar los 1000 caracteres.');
            redirect('/skillswap/modules/profile/edit.php');
        }
        foreach ([['github_url', $github], ['linkedin_url', $linkedin]] as [$field, $value]) {
            if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                flash('error', ($field === 'github_url' ? 'El enlace de GitHub' : 'El enlace de LinkedIn') . ' no es una URL válida.');
                redirect('/skillswap/modules/profile/edit.php');
            }
        }

        // Avatar: la extensión se deriva del contenido validado (nunca del nombre del cliente)
        $avatarPath = $user['profile_image'] ?? null;
        $removeAvatar = isset($_POST['remove_avatar']);
        if ($removeAvatar) {
            deleteAvatarFile($avatarPath);
            $avatarPath = null;
        } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $up = uploadAvatarImage($_FILES['avatar']);
            if ($up['error']) {
                flash('error', $up['error']);
                redirect('/skillswap/modules/profile/edit.php');
            }
            deleteAvatarFile($avatarPath);
            $avatarPath = $up['path'];
        }

        if (!empty($name)) {
            $stmt = $conn->prepare("UPDATE users
                                   SET name = ?, career = ?, semester = ?, bio = ?, github_url = ?, linkedin_url = ?, profile_image = ?
                                   WHERE id = ?");
            $stmt->bind_param("ssissssi", $name, $career, $semester, $bio, $github, $linkedin, $avatarPath, $userId);
            $stmt->execute();
            $stmt->close();
            flash('success', 'Perfil actualizado correctamente.');
        }
        redirect('/skillswap/modules/profile/edit.php');
    }

    if ($action === 'add_offered') {
        $skillId = intval($_POST['skill_id'] ?? 0);
        $level = $_POST['level'] ?? 'intermediate';
        $description = trim($_POST['description'] ?? '');
        $allowedLevels = ['beginner', 'intermediate', 'advanced'];

        if (!in_array($level, $allowedLevels, true)) {
            $level = 'intermediate';
        }

        if ($skillId <= 0) {
            flash('error', 'Selecciona una habilidad.');
        } elseif (strlen($description) < 5) {
            flash('error', 'Describe brevemente qué sabes hacer (mínimo 5 caracteres) para ofrecer esta habilidad.');
        } else {
            $stmt = $conn->prepare("INSERT INTO user_skills_offered (user_id, skill_id, level, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE level = VALUES(level), description = VALUES(description)");
            $stmt->bind_param("iiss", $userId, $skillId, $level, $description);
            $stmt->execute();
            $stmt->close();
            flash('success', 'Habilidad ofrecida agregada.');
        }
        redirect('/skillswap/modules/profile/edit.php');
    }

    if ($action === 'add_wanted') {
        $skillId = intval($_POST['skill_id'] ?? 0);
        $level = $_POST['level'] ?? 'intermediate';

        if ($skillId > 0) {
            $stmt = $conn->prepare("INSERT INTO user_skills_wanted (user_id, skill_id, level) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE level = VALUES(level)");
            $stmt->bind_param("iis", $userId, $skillId, $level);
            $stmt->execute();
            $stmt->close();
            flash('success', 'Habilidad buscada agregada.');
        }
        redirect('/skillswap/modules/profile/edit.php');
    }

    if ($action === 'remove_offered') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM user_skills_offered WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $userId);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Habilidad eliminada.');
        redirect('/skillswap/modules/profile/edit.php');
    }

    if ($action === 'remove_wanted') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM user_skills_wanted WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $userId);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Habilidad eliminada.');
        redirect('/skillswap/modules/profile/edit.php');
    }

    if ($action === 'add_certificate') {
        $skillId = intval($_POST['skill_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $issuer = trim($_POST['issuer'] ?? '');
        $issueDate = trim($_POST['issue_date'] ?? '');
        $credentialUrl = trim($_POST['credential_url'] ?? '');

        if ($title === '') {
            flash('error', 'El título del certificado es obligatorio.');
            redirect('/skillswap/modules/profile/edit.php');
        }

        $filePath = null;
        if (!empty($_FILES['file']['name'])) {
            $result = uploadCertificateFile($_FILES['file']);
            if ($result['error'] !== '') {
                flash('error', $result['error']);
                redirect('/skillswap/modules/profile/edit.php');
            }
            $filePath = $result['path'];
        }

        $stmt = $conn->prepare("INSERT INTO certificates (user_id, skill_id, title, issuer, issue_date, credential_url, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $skillParam = $skillId > 0 ? $skillId : null;
        $issuerParam = $issuer === '' ? null : $issuer;
        $dateParam = $issueDate === '' ? null : $issueDate;
        $urlParam = $credentialUrl === '' ? null : $credentialUrl;
        $stmt->bind_param("iisssss", $userId, $skillParam, $title, $issuerParam, $dateParam, $urlParam, $filePath);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Certificado agregado.');
        redirect('/skillswap/modules/profile/edit.php');
    }

    if ($action === 'delete_certificate') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT file_path FROM certificates WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $stmt = $conn->prepare("DELETE FROM certificates WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $userId);
            $stmt->execute();
            $stmt->close();
            if ($row['file_path'] && is_file(__DIR__ . '/../..' . '/' . $row['file_path'])) {
                @unlink(__DIR__ . '/../..' . '/' . $row['file_path']);
            }
            flash('success', 'Certificado eliminado.');
        }
        redirect('/skillswap/modules/profile/edit.php');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$user = getCurrentUser();
$categories = $conn->query("SELECT * FROM categories ORDER BY name");
$allSkills = $conn->query("SELECT s.*, c.name as category_name FROM skills s LEFT JOIN categories c ON s.category_id = c.id ORDER BY c.name, s.name");
$offeredStmt = $conn->prepare("
    SELECT uso.*, s.name as skill_name
    FROM user_skills_offered uso
    JOIN skills s ON uso.skill_id = s.id
    WHERE uso.user_id = ?
");
$offeredStmt->bind_param("i", $userId);
$offeredStmt->execute();
$offered = $offeredStmt->get_result();
$offeredStmt->close();

$wantedStmt = $conn->prepare("
    SELECT usw.*, s.name as skill_name
    FROM user_skills_wanted usw
    JOIN skills s ON usw.skill_id = s.id
    WHERE usw.user_id = ?
");
$wantedStmt->bind_param("i", $userId);
$wantedStmt->execute();
$wanted = $wantedStmt->get_result();
$wantedStmt->close();

$certStmt = $conn->prepare("
    SELECT c.*, s.name as skill_name, cat.name as category_name, cat.icon
    FROM certificates c
    LEFT JOIN skills s ON c.skill_id = s.id
    LEFT JOIN categories cat ON s.category_id = cat.id
    WHERE c.user_id = ?
    ORDER BY c.issue_date DESC, c.created_at DESC
");
$certStmt->bind_param("i", $userId);
$certStmt->execute();
$myCerts = $certStmt->get_result();
$certStmt->close();
?>

<h2><i class="bi bi-pencil"></i> Editar Perfil</h2>
<hr>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Datos Personales</h5></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label">Foto de perfil</label>
                        <div class="d-flex align-items-center gap-3">
                            <?php $avatarUrl = getAvatarUrl($user); ?>
                            <?php if ($avatarUrl): ?>
                                <img src="<?= $avatarUrl ?>" alt="Avatar" class="rounded-circle border" style="width:72px; height:72px; object-fit:cover;">
                                <div>
                                    <input type="file" class="form-control form-control-sm" name="avatar" accept="image/jpeg,image/png,image/webp">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_avatar" id="removeAvatar" value="1">
                                        <label class="form-check-label small" for="removeAvatar">Quitar la foto actual</label>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
                                     style="width:72px; height:72px; font-size:1.4rem; font-weight:bold;">
                                    <?= htmlspecialchars(getUserInitials($user['name'])) ?>
                                </div>
                                <input type="file" class="form-control form-control-sm" name="avatar" accept="image/jpeg,image/png,image/webp">
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">JPG, PNG o WebP. Máximo 2 MB.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="name"
                               value="<?= htmlspecialchars($user['name']) ?>" required maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Carrera</label>
                            <input type="text" class="form-control" name="career" maxlength="150"
                                   value="<?= htmlspecialchars($user['career'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semestre</label>
                            <select class="form-select" name="semester">
                                <option value="0">Seleccionar...</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($user['semester'] ?? 0) == $i ? 'selected' : '' ?>>
                                        <?= $i ?>°
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Biografía</label>
                        <textarea class="form-control" name="bio" rows="3" maxlength="1000"
                                  placeholder="Cuenta algo sobre ti, tus intereses y tu disponibilidad..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-github"></i> GitHub</label>
                            <input type="url" class="form-control" name="github_url" maxlength="255"
                                   placeholder="https://github.com/usuario"
                                   value="<?= htmlspecialchars($user['github_url'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-linkedin"></i> LinkedIn</label>
                            <input type="url" class="form-control" name="linkedin_url" maxlength="255"
                                   placeholder="https://linkedin.com/in/usuario"
                                   value="<?= htmlspecialchars($user['linkedin_url'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Guardar Cambios
                    </button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Habilidades que Ofrezco</h5>
            </div>
            <div class="card-body">
                <?php if ($offered->num_rows > 0): ?>
                    <?php while ($s = $offered->fetch_assoc()): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <strong><?= htmlspecialchars($s['skill_name']) ?></strong>
                                <span class="badge <?= getLevelBadgeClass($s['level']) ?> ms-2">
                                    <?= getLevelLabel($s['level']) ?>
                                </span>
                                <?php if ($s['description']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($s['description']) ?></small>
                                <?php endif; ?>
                            </div>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove_offered">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar esta habilidad?">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted">No has agregado habilidades ofrecidas.</p>
                <?php endif; ?>

                <hr>
                <h6>Agregar habilidad ofrecida:</h6>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_offered">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Categoría</label>
                            <select id="off_category" class="form-select" required>
                                <option value="">Seleccionar categoría...</option>
                                <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?= $cat['id'] ?>"><i class="bi <?= htmlspecialchars($cat['icon']) ?>"></i> <?= htmlspecialchars($cat['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">¿Qué sabes hacer?</label>
                            <select name="skill_id" id="off_skill" class="form-select" required disabled>
                                <option value="">Primero elige categoría</option>
                                <?php $allSkills->data_seek(0); while ($sk = $allSkills->fetch_assoc()): ?>
                                    <option value="<?= $sk['id'] ?>" data-category="<?= $sk['category_id'] ?>"><?= htmlspecialchars($sk['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Nivel</label>
                            <select name="level" id="off_level" class="form-select">
                                <option value="beginner">Principiante</option>
                                <option value="intermediate" selected>Intermedio</option>
                                <option value="advanced">Avanzado</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small mb-1">Descripción (obligatoria) — qué sabes exactamente</label>
                        <textarea name="description" id="off_desc" class="form-control" required minlength="5" rows="2" placeholder="Ej.: puedo maquetar páginas responsivas con HTML y CSS avanzado, y manejo flexbox y grid."></textarea>
                    </div>
                    <div class="mt-2" id="off_preview" class="d-none">
                        <div class="border rounded p-2 bg-light">
                            <small class="text-muted">Vista previa:</small><br>
                            <strong id="pv_title"></strong>
                            <span id="pv_level" class="badge ms-1"></span>
                            <p id="pv_desc" class="small text-muted mb-0 mt-1"></p>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-plus"></i> Agregar habilidad
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Habilidades que Busco</h5>
            </div>
            <div class="card-body">
                <?php if ($wanted->num_rows > 0): ?>
                    <?php while ($s = $wanted->fetch_assoc()): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <strong><?= htmlspecialchars($s['skill_name']) ?></strong>
                                <span class="badge <?= getLevelBadgeClass($s['level']) ?> ms-2">
                                    <?= getLevelLabel($s['level']) ?>
                                </span>
                            </div>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove_wanted">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar esta habilidad?">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted">No has agregado habilidades buscadas.</p>
                <?php endif; ?>

                <hr>
                <h6>Agregar habilidad buscada:</h6>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_wanted">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small mb-1">Categoría</label>
                            <select id="wan_category" class="form-select" required>
                                <option value="">Seleccionar categoría...</option>
                                <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?= $cat['id'] ?>"><i class="bi <?= htmlspecialchars($cat['icon']) ?>"></i> <?= htmlspecialchars($cat['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">¿Qué quieres aprender?</label>
                            <select name="skill_id" id="wan_skill" class="form-select" required disabled>
                                <option value="">Primero elige categoría</option>
                                <?php $allSkills->data_seek(0); while ($sk = $allSkills->fetch_assoc()): ?>
                                    <option value="<?= $sk['id'] ?>" data-category="<?= $sk['category_id'] ?>"><?= htmlspecialchars($sk['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Nivel</label>
                            <select name="level" class="form-select">
                                <option value="beginner">Principiante</option>
                                <option value="intermediate" selected>Intermedio</option>
                                <option value="advanced">Avanzado</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-info w-100 text-white">
                            <i class="bi bi-plus"></i> Agregar habilidad
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header" style="background:var(--reddit-gold)">
                <h5 class="mb-0"><i class="bi bi-patch-check"></i> Certificados (evidencia de lo que sabes)</h5>
            </div>
            <div class="card-body">
                <?php if ($myCerts->num_rows > 0): ?>
                    <?php while ($c = $myCerts->fetch_assoc()): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <strong><?= htmlspecialchars($c['title']) ?></strong>
                                <?php if ($c['issuer']): ?>
                                    <span class="text-muted"> — <?= htmlspecialchars($c['issuer']) ?></span>
                                <?php endif; ?>
                                <?php if ($c['issue_date']): ?>
                                    <br><small class="text-muted">Emitido: <?= date('d/m/Y', strtotime($c['issue_date'])) ?></small>
                                <?php endif; ?>
                                <?php if ($c['skill_name']): ?>
                                    <span class="badge bg-info text-white ms-1"><i class="bi <?= $c['icon'] ?? 'bi-patch-check' ?>"></i> <?= htmlspecialchars($c['skill_name']) ?></span>
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
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_certificate">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar este certificado? También se borrará el archivo adjunto.">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted">Aún no has agregado certificados. Súbelos como evidencia de tus habilidades.</p>
                <?php endif; ?>

                <hr>
                <h6>Agregar certificado:</h6>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_certificate">
                    <div class="mb-2">
                        <label class="form-label small mb-1">Habilidad que respalda (opcional)</label>
                        <select name="skill_id" class="form-select">
                            <option value="0">Sin vincular</option>
                            <?php $allSkills->data_seek(0); while ($sk = $allSkills->fetch_assoc()): ?>
                                <option value="<?= $sk['id'] ?>"><?= htmlspecialchars($sk['category_name'] . ' - ' . $sk['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Título (obligatorio)</label>
                            <input type="text" class="form-control" name="title" required maxlength="150" placeholder="Ej.: Curso Profesional de PHP">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Institución / plataforma</label>
                            <input type="text" class="form-control" name="issuer" maxlength="120" placeholder="Ej.: Platzi, Google Actívate...">
                        </div>
                    </div>
                    <div class="row g-2 mt-0">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Fecha de emisión</label>
                            <input type="date" class="form-control" name="issue_date">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small mb-1">Enlace de verificación</label>
                            <input type="url" class="form-control" name="credential_url" maxlength="255" placeholder="http://...">
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small mb-1">Archivo (imagen o PDF, máx. 2 MB — opcional)</label>
                        <input type="file" class="form-control" name="file" accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf">
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-plus"></i> Agregar certificado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-person-circle display-1 text-muted"></i>
                <h5 class="mt-2"><?= htmlspecialchars($user['name']) ?></h5>
                <div class="hours-badge my-2"><?= formatHours($user['hours_balance']) ?> horas</div>
                <p class="text-muted mb-0"><?= htmlspecialchars($user['career'] ?? 'Sin carrera definida') ?></p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function levelBadgeCls(level) {
        return { beginner: 'bg-success', intermediate: 'bg-warning text-dark', advanced: 'bg-danger' }[level] || 'bg-secondary';
    }
    function levelLabel(level) {
        return { beginner: 'Principiante', intermediate: 'Intermedio', advanced: 'Avanzado' }[level] || level;
    }
    function bindCascade(catSel, skillSel) {
        if (!catSel || !skillSel) return;
        function refresh() {
            const cat = catSel.value;
            skillSel.querySelectorAll('option').forEach(function(o) {
                if (o.value === '') return;
                const show = o.dataset.category === cat;
                o.hidden = !show;
                if (!show && o.selected) o.selected = false;
            });
            skillSel.disabled = !cat;
        }
        catSel.addEventListener('change', refresh);
        refresh();
    }
    bindCascade(document.getElementById('off_category'), document.getElementById('off_skill'));
    bindCascade(document.getElementById('wan_category'), document.getElementById('wan_skill'));

    var offCategory = document.getElementById('off_category');
    var offSkill = document.getElementById('off_skill');
    var offLevel = document.getElementById('off_level');
    var offDesc = document.getElementById('off_desc');
    var offPreview = document.getElementById('off_preview');
    function renderPreview() {
        var skill = offSkill.selectedOptions[0];
        var cat = offCategory.selectedOptions[0];
        if (!skill || !skill.value) {
            offPreview.classList.add('d-none');
            return;
        }
        var level = offLevel.value;
        document.getElementById('pv_title').textContent = (cat && cat.value ? cat.textContent.trim() + ' · ' : '') + skill.textContent.trim();
        var badge = document.getElementById('pv_level');
        badge.className = 'badge ' + levelBadgeCls(level) + ' ms-1';
        badge.textContent = levelLabel(level);
        document.getElementById('pv_desc').textContent = offDesc.value;
        offPreview.classList.remove('d-none');
    }
    ['off_category', 'off_skill', 'off_level', 'off_desc'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', renderPreview);
        if (el && id === 'off_desc') el.addEventListener('input', renderPreview);
    });
});
</script>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
?>
