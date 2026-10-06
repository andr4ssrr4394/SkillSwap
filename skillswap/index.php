<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    redirect('/skillswap/modules/dashboard/index.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillSwap - Intercambio de Habilidades</title>
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
                <a class="nav-link d-sm-inline d-none" href="/skillswap/modules/auth/login.php">Iniciar Sesión</a>
                <a class="btn btn-orange" href="/skillswap/modules/auth/register.php">Registrarse</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center my-5">
            <div class="col-lg-8 text-center">
                <div class="reddit-card px-4 py-5">
                    <h1 class="display-5 fw-bold mb-2">skill<span class="brand-accent">swap</span></h1>
                    <p class="lead text-muted mb-4">Enseña lo que sabes, aprende lo que necesitas. Intercambia habilidades con otros estudiantes.</p>
                    <a href="/skillswap/modules/auth/register.php" class="btn btn-orange btn-lg px-4">
                        <i class="bi bi-rocket-takeoff"></i> Comenzar Ahora
                    </a>
                </div>
            </div>
        </div>

        <h2 class="text-center fw-bold mb-4">¿Cómo funciona?</h2>
        <div class="row g-3 mb-5">
            <div class="col-md-4">
                <div class="reddit-card h-100">
                    <div class="text-center mb-2"><i class="bi bi-person-plus" style="font-size:2.2rem; color:var(--reddit-orange)"></i></div>
                    <h5 class="text-center">1. Regístrate</h5>
                    <p class="text-muted text-center mb-0">Crea tu perfil con las habilidades que ofreces y las que buscas.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="reddit-card h-100">
                    <div class="text-center mb-2"><i class="bi bi-lightning" style="font-size:2.2rem; color:var(--reddit-orange)"></i></div>
                    <h5 class="text-center">2. Encuentra tu match</h5>
                    <p class="text-muted text-center mb-0">El sistema empareja a quienes tienen habilidades complementarias.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="reddit-card h-100">
                    <div class="text-center mb-2"><i class="bi bi-arrow-repeat" style="font-size:2.2rem; color:var(--reddit-orange)"></i></div>
                    <h5 class="text-center">3. Intercambia</h5>
                    <p class="text-muted text-center mb-0">Ambos enseñan y aprenden. Ganas horas y valoraciones al completar.</p>
                </div>
            </div>
        </div>

        <h2 class="text-center fw-bold mb-4">Categorías populares</h2>
        <div class="row g-3 mb-4 justify-content-center">
            <?php
            $cats = [
                ['Programación', 'bi-code-slash'],
                ['Diseño', 'bi-palette'],
                ['Idiomas', 'bi-translate'],
                ['Matemáticas', 'bi-calculator'],
                ['Bases de Datos', 'bi-database'],
                ['Redes', 'bi-diagram-3'],
            ];
            foreach ($cats as [$name, $icon]): ?>
                <div class="col-auto">
                    <span class="badge" style="background:var(--reddit-chip); color:var(--reddit-text); border:1px solid var(--reddit-border); padding:0.6rem 1rem;">
                        <i class="bi <?= $icon ?> me-1"></i> <?= $name ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer class="footer-strip">
        <div class="container text-center">
            <strong>skill<span style="color:var(--reddit-orange)">swap</span></strong>
            <span class="ms-2">© <?= date('Y') ?> · Tercer Año de Desarrollo de Software</span>
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