<?php
// Constancia oficial de horas del usuario.
//
//   certificate.php         -> vista previa A4 FIJA (se escala en pantallas
//                              angostas, nunca cambia de forma)
//   certificate.php?pdf=1   -> descarga el PDF generado por Dompdf
//
// Es un documento GENERADO por el sistema a partir de los intercambios
// completados; es distinto a los "certificados" de evidencia que el usuario
// registra a mano.
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../../includes/certificate.php';

$user = getCurrentUser();
$conn = getConnection();
$data = certificateData($conn, $user);
$conn->close();

if (isset($_GET['pdf'])) {
    $pdf = certificateRenderPdf($data);
    $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$user['name']) ?: 'usuario';
    $file = 'constancia-horas-' . preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($name)) . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . strlen($pdf));
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Constancia de Horas - <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="Constancia oficial de horas de intercambio de <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?> en SkillSwap: descárgala en PDF o imprímela.">
    <style>
        html, body { margin: 0; padding: 0; }
        body { background: #eef1f5; color: #1f2933; font-family: "DejaVu Sans", sans-serif; }

        /* Barra de acciones (fuera de la hoja: no se imprime ni se exporta) */
        .c-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
                 gap: 8px; padding: 12px 16px; background: #fff; border-bottom: 1px solid #dde3ea; }
        .c-bar a { display: inline-block; padding: 6px 12px; border-radius: 4px; font-size: 14px;
                   text-decoration: none; border: 1px solid transparent; }
        .c-bar .c-back { color: #4b5563; border-color: #c9d2dc; }
        .c-bar .c-pdf { background: #e8662d; color: #fff; font-weight: 700; }
        .c-bar .c-print { color: #1f2933; border-color: #c9d2dc; }
        .c-bar a:hover { filter: brightness(.95); }

        /* Hoja A4 fija: en pantallas angostas se escala la hoja entera (sin reflow) */
        .c-wrap { width: 100%; padding: 20px 0 40px; overflow-x: hidden; }
        .c-sheet { margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,.12); }

        @media print {
            .c-bar { display: none; }
            .c-wrap { padding: 0; }
            .c-sheet { transform: none !important; box-shadow: none; }
        }
        @page { size: A4; margin: 0; }
    </style>
    <!-- Hoja A4 compartida con el PDF (mismo CSS, mismo maquetado por tablas) -->
    <style><?= certificateSheetCss() ?></style>
</head>
<body>

<div class="c-bar">
    <a href="/skillswap/modules/profile/index.php" class="c-back">&#8592; Volver al Perfil</a>
    <span>
        <a href="?pdf=1" class="c-pdf">&#8681; Descargar PDF</a>
        <a href="#" class="c-print" onclick="window.print();return false;">&#128424; Imprimir</a>
    </span>
</div>

<div class="c-wrap">
    <?= certificateSheetBody($data, false) ?>
</div>

<script>
    // La hoja es A4 fijo (210mm). Si el navegador es mas angosto que la hoja,
    // se escala la hoja COMPLETA: el texto nunca se sale ni la pagina cambia.
    (function () {
        var wrap = document.querySelector('.c-wrap');
        var sheet = document.querySelector('.c-sheet');
        function fit() {
            var avail = wrap.clientWidth;
            var w = sheet.offsetWidth;
            var s = w > avail ? avail / w : 1;
            // Origen arriba-izquierda: el ancho VISUAL queda igual al disponible,
            // asi no hay scroll horizontal ni se recorta la hoja.
            sheet.style.transformOrigin = 'top left';
            sheet.style.transform = s < 1 ? 'scale(' + s + ')' : 'none';
            wrap.style.height = Math.round(sheet.offsetHeight * s) + 'px';
        }
        fit();
        window.addEventListener('resize', fit);
    })();
</script>

</body>
</html>
