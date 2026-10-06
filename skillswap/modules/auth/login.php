<?php
require_once __DIR__ . '/../../includes/auth.php';

if (isLoggedIn()) {
    redirect('/skillswap/modules/dashboard/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'];

    if (empty($email) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        $conn = getConnection();

        // Brute-force: check blocked (>=5 failures in 15 min)
        $stmt = $conn->prepare("SELECT COUNT(*) c FROM login_attempts WHERE email = ? AND ip = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stmt->bind_param("ss", $email, $ip);
        $stmt->execute();
        $attempts = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        if ($attempts >= 5) {
            $error = 'Demasiados intentos. Intenta de nuevo en 15 minutos.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                // Record success
                $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, 1)");
                $stmt->bind_param("ss", $email, $ip);
                $stmt->execute();
                $stmt->close();

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $conn->close();
                redirect('/skillswap/modules/dashboard/index.php');
            } else {
                // Record failure
                $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, 0)");
                $stmt->bind_param("ss", $email, $ip);
                $stmt->execute();
                $stmt->close();
                $error = 'Correo o contraseña incorrectos.';
            }
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SkillSwap</title>
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
            <div class="d-flex align-items-center gap-2">
                <button class="theme-toggle" id="themeToggle" type="button" title="Cambiar tema" aria-label="Cambiar tema">
                    <i class="bi bi-moon-stars" id="themeIcon"></i>
                </button>
                <a class="btn btn-orange" href="/skillswap/modules/auth/register.php">Registrarse</a>
            </div>
        </div>
    </nav>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-5">
                <div class="text-center mb-4">
                    <a href="/skillswap/" class="text-decoration-none">
                        <h2 class="fw-bold"><i class="bi bi-arrow-repeat brand-accent me-1"></i> skill<span class="brand-accent">swap</span></h2>
                    </a>
                </div>
                <div class="reddit-card">
                    <h4 class="text-center mb-4">Iniciar Sesión</h4>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-orange w-100 py-2">
                            <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="/skillswap/modules/auth/register.php" class="text-decoration-none">
                            ¿No tienes cuenta? Regístrate aquí
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
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