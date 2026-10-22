<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    redirect('/skillswap/modules/dashboard/index.php');
}

// URL base dinámica (funciona en localhost, túnel o dominio propio) para SEO.
$seoHost   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$seoScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$seoUrl    = $seoScheme . '://' . $seoHost . '/skillswap/';
$seoTitle  = 'SkillSwap - Intercambio de Habilidades entre Estudiantes';
$seoDesc   = 'SkillSwap es la plataforma para intercambiar habilidades entre estudiantes: enseña lo que sabes, aprende lo que necesitas y gana horas y valoraciones. Regístrate gratis.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seoTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta name="keywords" content="SkillSwap, intercambio de habilidades, aprender, enseñar, estudiantes, programación, diseño, idiomas, tutorías, trueque de conocimientos">
    <meta name="author" content="SkillSwap">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($seoUrl) ?>">
    <link rel="icon" type="image/svg+xml" href="/skillswap/assets/img/favicon.svg">
    <!-- Open Graph (para enlaces en WhatsApp, Facebook, etc.) -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SkillSwap">
    <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($seoUrl) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($seoScheme . '://' . $seoHost) ?>/skillswap/assets/img/og-image.png">
    <meta property="og:locale" content="es_AR">
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($seoTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($seoScheme . '://' . $seoHost) ?>/skillswap/assets/img/og-image.png">
    <!-- Datos estructurados (Google) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebApplication",
      "name": "SkillSwap",
      "url": "<?= htmlspecialchars($seoUrl) ?>",
      "description": "<?= htmlspecialchars($seoDesc) ?>",
      "applicationCategory": "EducationalApplication",
      "operatingSystem": "Web",
      "inLanguage": "es",
      "offers": { "@type": "Offer", "price": "0", "priceCurrency": "ARS" }
    }
    </script>
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="/skillswap/"><i class="bi bi-house"></i> Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/skillswap/#como-funciona"><i class="bi bi-info-circle"></i> Cómo funciona</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/skillswap/#categorias"><i class="bi bi-grid"></i> Categorías</a>
                    </li>
                </ul>
                <ul class="navbar-nav align-items-center gap-2">
                    <li class="nav-item">
                        <button class="theme-toggle" id="themeToggle" type="button" title="Cambiar tema" aria-label="Cambiar tema">
                            <i class="bi bi-moon-stars" id="themeIcon"></i>
                        </button>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="/skillswap/modules/auth/login.php">Iniciar Sesión</a></li>
                    <li class="nav-item">
                        <a class="btn btn-orange" href="/skillswap/modules/auth/register.php">Registrarse</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center my-5">
            <div class="col-lg-8 text-center">
                <div class="reddit-card px-4 py-5">
                    <h1 class="display-5 fw-bold mb-2">skill<span class="brand-accent">swap</span></h1>
                    <p class="lead text-muted mb-4">Enseña lo que sabes, aprende lo que necesitas. Intercambia habilidades con otros estudiantes.</p>
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <a href="/skillswap/modules/auth/register.php" class="btn btn-orange btn-lg px-4">
                            <i class="bi bi-rocket-takeoff"></i> Crear cuenta gratis
                        </a>
                        <a href="/skillswap/modules/auth/login.php" class="btn btn-outline-primary btn-lg px-4">
                            <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <h2 id="como-funciona" class="text-center fw-bold mb-4">¿Cómo funciona?</h2>
        <div class="row g-3 mb-5">
            <div class="col-md-4">
                <div class="reddit-card h-100">
                    <div class="text-center mb-2"><i class="bi bi-person-plus" style="font-size:2.2rem; color:var(--reddit-orange)"></i></div>
                    <h5 class="text-center">1. Regístrate</h5>
                    <p class="text-muted text-center">Crea tu perfil con las habilidades que ofreces y las que buscas.</p>
                    <div class="text-center">
                        <a href="/skillswap/modules/auth/register.php" class="btn btn-orange btn-sm">
                            <i class="bi bi-person-plus"></i> Registrarme
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="reddit-card h-100">
                    <div class="text-center mb-2"><i class="bi bi-lightning" style="font-size:2.2rem; color:var(--reddit-orange)"></i></div>
                    <h5 class="text-center">2. Encuentra tu match</h5>
                    <p class="text-muted text-center">El sistema empareja a quienes tienen habilidades complementarias.</p>
                    <div class="text-center">
                        <a href="/skillswap/modules/auth/register.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-search"></i> Ver matches
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="reddit-card h-100">
                    <div class="text-center mb-2"><i class="bi bi-arrow-repeat" style="font-size:2.2rem; color:var(--reddit-orange)"></i></div>
                    <h5 class="text-center">3. Intercambia</h5>
                    <p class="text-muted text-center">Ambos enseñan y aprenden. Ganas horas y valoraciones al completar.</p>
                    <div class="text-center">
                        <a href="/skillswap/modules/auth/register.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-repeat"></i> Empezar a intercambiar
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <h2 id="categorias" class="text-center fw-bold mb-4">Categorías populares</h2>
        <div class="row g-3 mb-5 justify-content-center">
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
                    <a href="/skillswap/modules/auth/register.php" class="badge text-decoration-none"
                       style="background:var(--reddit-chip); color:var(--reddit-text); border:1px solid var(--reddit-border); padding:0.6rem 1rem;">
                        <i class="bi <?= $icon ?> me-1"></i> <?= $name ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 text-center">
                <div class="reddit-card px-4 py-5">
                    <h2 class="fw-bold mb-2">¿Listo para intercambiar habilidades?</h2>
                    <p class="text-muted mb-4">Únete gratis, publica lo que sabes y empieza a aprender de otros estudiantes.</p>
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <a href="/skillswap/modules/auth/register.php" class="btn btn-orange btn-lg px-4">
                            <i class="bi bi-rocket-takeoff"></i> Crear cuenta gratis
                        </a>
                        <a href="/skillswap/modules/auth/politica.php" class="btn btn-outline-secondary btn-lg px-4">
                            <i class="bi bi-shield-check"></i> Política de privacidad
                        </a>
                    </div>
                </div>
            </div>
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