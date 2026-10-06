<?php
require_once __DIR__ . '/../../includes/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidad - SkillSwap</title>
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
                <a class="btn btn-orange" href="/skillswap/modules/auth/register.php">Volver al registro</a>
            </div>
        </div>
    </nav>
    <div class="container">
        <div class="row justify-content-center my-5">
            <div class="col-lg-8">
                <div class="reddit-card">
                    <h4 class="mb-3"><i class="bi bi-shield-lock"></i> Política de Privacidad</h4>
                    <p class="text-muted">SkillSwap – Plataforma de intercambio de habilidades entre estudiantes · Actualizada: <?= date('d/m/Y') ?></p>

                    <h6 class="mt-4">1. Responsable del tratamiento</h6>
                    <p>SkillSwap es un proyecto académico de Tercer Año de Desarrollo de Software. El responsable del
                    tratamiento de los datos personales recopilados a través de esta plataforma son sus integrantes.</p>

                    <h6 class="mt-4">2. Datos personales que recopilamos</h6>
                    <ul>
                        <li><strong>Identificación:</strong> nombre y correo electrónico.</li>
                        <li><strong>Académicos:</strong> carrera y semestre.</li>
                        <li><strong>Proceso:</strong> habilidades ofrecidas y buscadas, saldo de horas, intercambios y valoraciones.</li>
                    </ul>

                    <h6 class="mt-4">3. Finalidad del tratamiento</h6>
                    <p>Los datos se utilizan exclusivamente para el funcionamiento de la plataforma:
                    crear y mantener tu perfil, encontrar intercambios de habilidades compatibles, gestionar los
                    intercambios y el banco de horas, y mostrar valoraciones a otros estudiantes.</p>

                    <h6 class="mt-4">4. Base legal</h6>
                    <p>El tratamiento se realiza con tu <strong>consentimiento</strong>, otorgado al marcar la casilla de aceptación al crear tu cuenta.</p>

                    <h6 class="mt-4">5. Comunicación de datos</h6>
                    <p>Tus datos son visibles para los demás estudiantes de la plataforma (nombre, carrera, semestre,
                    habilidades y valoraciones) con el fin de facilitar los intercambios. No se comparten con terceros
                    ajenos a la institución ni se transfieren fuera del país.</p>

                    <h6 class="mt-4">6. Conservación</h6>
                    <p>Los datos se conservan mientras la cuenta esté activa. Al solicitar la eliminación de la cuenta,
                    se suprimen de forma definitiva.</p>

                    <h6 class="mt-4">7. Seguridad</h6>
                    <p>Las contraseñas se almacenan cifradas (hash), las sesiones se protegen con cookies seguras,
                    y se aplican controles contra accesos no autorizados, falsificación de peticiones y fugas de información.</p>

                    <h6 class="mt-4">8. Derechos de los titulares</h6>
                    <p>De acuerdo con la Ley de Protección de Datos Personales, puedes ejercer los derechos de
                    <strong>acceso, rectificación, cancelación y oposición</strong> escribiendo al correo
                    del responsable del proyecto o gestionando tu perfil dentro de la plataforma.</p>

                    <h6 class="mt-4">9. Contacto</h6>
                    <p>Para cualquier consulta sobre el tratamiento de tus datos, contacta a los responsables del
                    proyecto a través de la institución educativa.</p>
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