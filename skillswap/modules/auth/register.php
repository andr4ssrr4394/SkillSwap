<?php
require_once __DIR__ . '/../../includes/auth.php';

if (isLoggedIn()) {
    redirect('/skillswap/modules/dashboard/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $career = trim($_POST['career'] ?? '');
    $semester = intval($_POST['semester'] ?? 0);
    $consent = isset($_POST['consent']);

    // Antispam: campo trampa invisible para personas; los bots suelen rellenarlo.
    $honeypot = trim($_POST['website'] ?? '');

    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Por favor completa todos los campos obligatorios.';
    } elseif ($honeypot !== '') {
        $error = 'No se pudo completar el registro.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'La contraseña debe tener al menos 8 caracteres e incluir letras y números.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo electrónico válido.';
    } elseif (mb_strlen($name) > 150 || mb_strlen($email) > 150 || mb_strlen($career) > 150) {
        $error = 'Alguno de los campos supera la longitud permitida.';
    } elseif ($semester < 0 || $semester > 12) {
        $error = 'El semestre debe estar entre 1 y 12.';
    } elseif (!$consent) {
        $error = 'Debes aceptar la política de privacidad para crear tu cuenta.';
    } else {
        $conn = getConnection();
        purgeOldAttempts($conn);

        if (rateLimitExceeded($conn, '__register__', 10, 60)) {
            $error = 'Demasiados registros desde esta red. Intenta de nuevo más tarde.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($exists) {
                $error = 'Este correo ya está registrado.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt2 = $conn->prepare("INSERT INTO users (name, email, password, career, semester) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("ssssi", $name, $email, $hashed, $career, $semester);
                if ($stmt2->execute()) {
                    $userId = $conn->insert_id;
                    $stmt2->close();
                    rateLimitRecord($conn, '__register__');
                    session_regenerate_id(true);
                    csrf_rotate();
                    $_SESSION['user_id'] = (int)$userId;
                    $_SESSION['user_role'] = 'student';
                    securityLog('registro_ok', ['user' => $userId]);
                    flash('success', '¡Bienvenido a SkillSwap! Comienza agregando tus habilidades.');
                    $conn->close();
                    redirect('/skillswap/modules/profile/edit.php');
                } else {
                    $stmt2->close();
                    $error = 'Error al crear la cuenta. Inténtalo de nuevo.';
                }
            }
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - SkillSwap</title>
    <meta name="description" content="Crea tu cuenta gratis en SkillSwap: publica las habilidades que enseñas, encuentra lo que quieres aprender e intercambia conocimientos con otros estudiantes.">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="/skillswap/assets/img/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset_url('css/style.css') ?>" rel="stylesheet">
    <script>
        (function() {
            var theme = localStorage.getItem('skillswap-theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top navbar-role-guest">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="/skillswap/">
                <i class="bi bi-arrow-repeat brand-accent me-1"></i>
                <span>skill<span class="brand-accent">swap</span></span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a class="nav-link d-none d-sm-inline" href="/skillswap/"><i class="bi bi-house"></i> Inicio</a>
                <button class="theme-toggle" id="themeToggle" type="button" title="Cambiar tema" aria-label="Cambiar tema">
                    <i class="bi bi-moon-stars" id="themeIcon"></i>
                </button>
                <a class="nav-link" href="/skillswap/modules/auth/login.php">Iniciar Sesión</a>
            </div>
        </div>
    </nav>
    <div class="container">
        <div class="row justify-content-center mt-4">
            <div class="col-md-6">
                <div class="text-center mb-4">
                    <a href="/skillswap/" class="text-decoration-none">
                        <h2 class="fw-bold"><i class="bi bi-arrow-repeat brand-accent me-1"></i> skill<span class="brand-accent">swap</span></h2>
                    </a>
                </div>
                <div class="reddit-card">
                    <h4 class="text-center mb-4">Crear Cuenta</h4>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <?= csrf_field() ?>
                        <div style="position:absolute; left:-9999px; top:-9999px;" aria-hidden="true">
                            <label for="website">No completar este campo</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico *</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Contraseña *</label>
                                <input type="password" class="form-control" id="password" name="password"
                                       minlength="8" required>
                                <small class="text-muted">Mínimo 8 caracteres con letras y números.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirmar *</label>
                                <input type="password" class="form-control" id="confirm_password"
                                       name="confirm_password" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="career" class="form-label">Carrera</label>
                            <input type="text" class="form-control" id="career" name="career"
                                   value="<?= htmlspecialchars($_POST['career'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="semester" class="form-label">Semestre</label>
                            <select class="form-select" id="semester" name="semester">
                                <option value="0">Seleccionar...</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($_POST['semester'] ?? '') == $i ? 'selected' : '' ?>>
                                        <?= $i ?>° Semestre
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="consent" id="consent"
                                   value="1" <?= isset($_POST['consent']) ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="consent">
                                He leído y acepto la <a href="/skillswap/modules/auth/politica.php" target="_blank" rel="noopener">política de privacidad</a> de SkillSwap. *
                            </label>
                        </div>
                        <button type="submit" class="btn btn-orange w-100 py-2">
                            <i class="bi bi-person-plus"></i> Crear Cuenta
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="/skillswap/modules/auth/login.php" class="text-decoration-none">
                            ¿Ya tienes cuenta? Inicia sesión
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer-strip">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <strong>skill<span style="color: var(--reddit-orange)">swap</span></strong>
                    <span class="ms-2">Intercambio de habilidades entre estudiantes</span>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="me-3">© <?= date('Y') ?> SkillSwap</span>
                    <span>Tercer Año de Desarrollo de Software</span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const html = document.documentElement;
        const icon = document.getElementById('themeIcon');
        function upd() {
            icon.className = html.getAttribute('data-theme') === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
        upd();
        document.getElementById('themeToggle').addEventListener('click', function() {
            const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('skillswap-theme', next);
            upd();
        });
    </script>
</body>
</html>
