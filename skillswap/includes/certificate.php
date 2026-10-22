<?php
// ---------------------------------------------------------------------------
// Constancia de horas (documento oficial generado por el sistema).
//
// El MISMO HTML sirve para la vista previa en pantalla y para el PDF que se
// descarga (Dompdf). El formato es A4 FIJO (210mm x 297mm) y el maquetado usa
// TABLAS (nada de flexbox/grid de Bootstrap), asi el texto nunca se sale de su
// recuadro ni la hoja cambia de forma al hacerse responsiva: en pantallas
// angostas la hoja entera se escala, sin reflow.
//
// La libreria Dompdf vive en includes/dompdf/ (carpeta ya bloqueada por HTTP
// en el .htaccess), licencia LGPL-2.1, version 3.1.6.
// ---------------------------------------------------------------------------

/**
 * Datos de la constancia a partir del usuario logueado.
 */
function certificateData($conn, array $user): array {
    $userId = (int)$user['id'];

    // Horas impartidas y aprendidas en intercambios completados
    $stmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN proposer_id = ? THEN proposer_hours ELSE acceptor_hours END) AS taught,
            SUM(CASE WHEN proposer_id = ? THEN acceptor_hours ELSE proposer_hours END) AS learned,
            COUNT(*) AS completed
        FROM exchanges
        WHERE (proposer_id = ? OR acceptor_id = ?) AND status = 'completed'
    ");
    $stmt->bind_param("iiii", $userId, $userId, $userId, $userId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc() ?? [];
    $stmt->close();

    // Habilidades impartidas en esos intercambios
    $stmt = $conn->prepare("
        SELECT DISTINCT s.name AS skill_name, c.name AS category_name
        FROM exchanges e
        JOIN skills s ON s.id = CASE WHEN e.proposer_id = ? THEN e.skill_offered_id ELSE e.skill_requested_id END
        LEFT JOIN categories c ON c.id = s.category_id
        WHERE (e.proposer_id = ? OR e.acceptor_id = ?) AND e.status = 'completed'
          AND (CASE WHEN e.proposer_id = ? THEN e.skill_offered_id ELSE e.skill_requested_id END) IS NOT NULL
        ORDER BY s.name
    ");
    $stmt->bind_param("iiii", $userId, $userId, $userId, $userId);
    $stmt->execute();
    // get_result() UNA sola vez: llamarlo dentro del while provoca
    // "Commands out of sync; you can't run this command now" en cuanto hay filas.
    $res = $stmt->get_result();
    $skills = [];
    while ($row = $res->fetch_assoc()) {
        $skills[] = ['name' => $row['skill_name'], 'category' => $row['category_name']];
    }
    $stmt->close();

    // Avatar: en el PDF se incrusta como data URI (nada de recursos remotos)
    $avatarFile = null;
    if (!empty($user['profile_image'])) {
        $base = basename((string)$user['profile_image']);
        $path = __DIR__ . '/../assets/img/avatars/' . $base;
        if (is_file($path)) {
            $avatarFile = $path;
        }
    }

    $certCode = 'CERT-SWAP-' . strtoupper(substr(hash('sha256', $userId . '|' . $user['created_at']), 0, 8))
              . '-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);

    return [
        'user'        => $user,
        'code'        => $certCode,
        'taught'      => (float)($stats['taught'] ?? 0),
        'learned'     => (float)($stats['learned'] ?? 0),
        'completed'   => (int)($stats['completed'] ?? 0),
        'skills'      => $skills,
        'badges'      => getUserBadges($conn, $userId),
        'rating'      => getUserRating($conn, $userId),
        'avatar_file' => $avatarFile,
        'issued_at'   => date('d/m/Y'),
    ];
}

/**
 * CSS de la hoja A4. Se usa tal cual en pantalla y en el PDF.
 */
function certificateSheetCss(): string {
    return '
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body { color: #1f2933; font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; }

/* Dompdf NO implementa box-sizing: border-box, por eso el margen interior de la
   hoja se hace con el padding de una CELDA de tabla (ancho exacto garantizado). */
.c-sheet { width: 210mm; height: 297mm; background: #fff; overflow: hidden; }
.c-pad { width: 100%; border-collapse: collapse; table-layout: fixed; }
.c-pad-cell { padding: 11mm 15mm; vertical-align: top; }

.c-head { width: 100%; border-collapse: collapse; border-bottom: 2.5pt solid #e8662d; margin: 0 0 5mm; }
.c-head td { vertical-align: top; padding: 0 0 4mm; }
.c-logo { font-size: 16pt; font-weight: bold; }
.c-logo .o { color: #e8662d; }
.c-sub { font-size: 7.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: .4pt; }
.c-meta { text-align: right; font-size: 8.5pt; color: #6b7280; }
.c-code { font-size: 8pt; color: #1f2933; border: .6pt solid #b8c1cc; padding: 1pt 4pt; }

.c-title { text-align: center; font-size: 12.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1.6pt; margin: 2.5mm 0 1.5mm; }
.c-lead { text-align: center; color: #6b7280; margin: 0 0 2mm; }
.c-name { text-align: center; font-family: "DejaVu Serif", serif; font-size: 22pt; font-weight: bold; }
.c-avatar { width: 19mm; height: 19mm; border: .8pt solid #b8c1cc; margin: 0 5mm 0 0; }
.c-name-table { border-collapse: collapse; margin: 1mm auto 2mm; }
.c-name-table td { vertical-align: middle; padding: 0; }
.c-data { text-align: center; font-size: 9pt; margin: 1mm 0 0; }

.c-stats { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 3.5mm 0; }
.c-stats td { width: 33.33%; text-align: center; vertical-align: top; padding: 1mm 2mm; }
.c-stat-sep { border-left: .6pt solid #dde3ea; }
.c-stat-val { font-size: 17pt; font-weight: bold; }
.c-stat-lbl { font-size: 7.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: .5pt; }
.v-blue { color: #2f5f9e; } .v-green { color: #2f7d4f; } .v-dark { color: #1f2933; }

.c-h3 { font-size: 9.5pt; font-weight: bold; border-bottom: .6pt solid #dde3ea; padding-bottom: 1mm; margin: 3mm 0 1.5mm; }
.c-skills { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8.5pt; }
.c-skills td { width: 50%; padding: .5mm 0; vertical-align: top; }
.c-ok { color: #2f7d4f; }
.c-more { font-size: 7.5pt; color: #6b7280; margin: 1mm 0 0; }
.c-cat { color: #6b7280; font-size: 8pt; }

.c-chip { display: inline-block; font-size: 8pt; padding: 1pt 5pt; margin: 0 3pt 2pt 0; border-radius: 2pt; color: #fff; }
.c-chip.bg-warning { background-color: #ffc107; color: #212529; }
.c-chip.bg-primary { background-color: #0d6efd; }
.c-chip.bg-success { background-color: #198754; }
.c-chip.bg-info { background-color: #0dcaf0; color: #212529; }
.c-chip.bg-secondary { background-color: #6c757d; }

.c-note { font-size: 7.5pt; color: #6b7280; text-align: center; margin: 3mm 0 0; }

.c-foot { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 3mm; page-break-inside: avoid; }
.c-foot td { width: 50%; text-align: center; vertical-align: top; padding: 1.5mm 0 0; }
.c-seal { width: 24mm; height: 24mm; border: 1.2pt dashed #e8662d; border-radius: 50%; color: #e8662d; font-weight: bold; font-size: 7.5pt; text-transform: uppercase; margin: 0 auto; padding-top: 6mm; }
.c-seal small { font-size: 6.5pt; font-weight: normal; }
.c-sign { width: 70mm; margin: 0 auto; border-top: 1.2pt solid #1f2933; padding-top: 1.5mm; font-size: 8.5pt; font-weight: bold; }
.c-sign small { font-size: 7.5pt; font-weight: normal; color: #6b7280; }
';
}

/**
 * Cuerpo de la hoja (mismo HTML para pantalla y PDF).
 */
function certificateSheetBody(array $data, bool $forPdf = false): string {
    $user = $data['user'];
    $h = static function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

    // Avatar: data URI en el PDF, ruta local en pantalla
    $avatarHtml = '';
    if (!empty($data['avatar_file'])) {
        if ($forPdf) {
            $mime = mime_content_type($data['avatar_file']) ?: 'image/png';
            $src = 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($data['avatar_file']));
        } else {
            $src = '/skillswap/assets/img/avatars/' . rawurlencode(basename($data['avatar_file']));
        }
        $avatarHtml = '<img class="c-avatar" src="' . $src . '" alt="">';
    }

    // Habilidades en dos columnas (tope de 20 para garantizar UNA sola hoja A4;
    // el resto se resume: en el perfil del usuario estan todas).
    $skillsHtml = '';
    if (!empty($data['skills'])) {
        $shown = array_slice($data['skills'], 0, 20);
        $extra = count($data['skills']) - count($shown);
        $rows = [];
        $half = (int)ceil(count($shown) / 2);
        for ($i = 0; $i < $half; $i++) {
            $left = $shown[$i];
            $right = $shown[$i + $half] ?? null;
            $rows[] = '<tr>'
                . '<td><span class="c-ok">&#10003;</span> <strong>' . $h($left['name']) . '</strong>'
                . ($left['category'] ? ' <span class="c-cat">(' . $h($left['category']) . ')</span>' : '')
                . '</td>'
                . '<td>'
                . ($right
                    ? '<span class="c-ok">&#10003;</span> <strong>' . $h($right['name']) . '</strong>'
                      . ($right['category'] ? ' <span class="c-cat">(' . $h($right['category']) . ')</span>' : '')
                    : '')
                . '</td></tr>';
        }
        $skillsHtml = '<div class="c-h3">Habilidades impartidas y certificadas</div>'
                    . '<table class="c-skills">' . implode('', $rows) . '</table>';
        if ($extra > 0) {
            $skillsHtml .= '<p class="c-more">... y ' . $extra . ' habilidad' . ($extra === 1 ? '' : 'es') . ' m&aacute;s (en el perfil del usuario).</p>';
        }
    }

    // Reconocimientos
    $badgesHtml = '';
    if (!empty($data['badges'])) {
        $chips = '';
        foreach ($data['badges'] as $badge) {
            $cls = trim(preg_replace('/\btext-dark\b/', '', (string)($badge['class'] ?? '')));
            $chips .= '<span class="c-chip ' . $h($cls) . '">' . $h($badge['name']) . '</span> ';
        }
        $badgesHtml = '<div class="c-h3">Reconocimientos</div><div>' . $chips . '</div>';
    }

    $rating = $data['rating'];
    $ratingText = ((int)($rating['total'] ?? 0) > 0)
        ? number_format((float)$rating['avg_rating'], 1) . ' / 5'
        : 'Sin valoraciones';

    $careerLine = '';
    if (!empty($user['career'])) {
        $careerLine = 'Estudiante de <strong>' . $h($user['career']) . '</strong>'
            . (!empty($user['semester']) ? ' (semestre ' . (int)$user['semester'] . ')' : '') . ', ';
    }

    return '
<div class="c-sheet">
  <table class="c-pad"><tr><td class="c-pad-cell">
    <table class="c-head">
        <tr>
            <td>
                <div class="c-logo">skill<span class="o">swap</span></div>
                <div class="c-sub">Plataforma universitaria de aprendizaje colaborativo</div>
            </td>
            <td class="c-meta">
                <span class="c-code">' . $h($data['code']) . '</span><br>
                Emisi&oacute;n: ' . $h($data['issued_at']) . '
            </td>
        </tr>
    </table>

    <div class="c-title">Constancia de horas de intercambio</div>
    <p class="c-lead">Se hace constar que el estudiante:</p>

    <table class="c-name-table"><tr>
        ' . $avatarHtml . '
        <td><div class="c-name">' . $h($user['name']) . '</div></td>
    </tr></table>

    <p class="c-data">' . $careerLine . 'identificado con <strong>' . $h($user['email']) . '</strong>,
       miembro de SkillSwap desde el ' . $h(date('d/m/Y', strtotime($user['created_at']))) . '.</p>

    <table class="c-stats">
        <tr>
            <td>
                <div class="c-stat-val v-blue">' . $h(formatHours($data['taught'])) . '</div>
                <div class="c-stat-lbl">Horas impartidas</div>
            </td>
            <td class="c-stat-sep">
                <div class="c-stat-val v-green">' . $h(formatHours($data['learned'])) . '</div>
                <div class="c-stat-lbl">Horas aprendidas</div>
            </td>
            <td class="c-stat-sep">
                <div class="c-stat-val v-dark">' . (int)$data['completed'] . '</div>
                <div class="c-stat-lbl">Intercambios completados</div>
            </td>
        </tr>
    </table>

    ' . $skillsHtml . '
    ' . $badgesHtml . '

    <table class="c-stats">
        <tr>
            <td>
                <div class="c-stat-lbl">Calificaci&oacute;n promedio</div>
                <strong>' . $h($ratingText) . '</strong> (' . (int)($rating['total'] ?? 0) . ' rese&ntilde;as)
            </td>
            <td class="c-stat-sep">
                <div class="c-stat-lbl">Saldo actual</div>
                <strong>' . $h(formatHours($user['hours_balance'])) . '</strong>
            </td>
            <td class="c-stat-sep">
                <div class="c-stat-lbl">C&oacute;digo de verificaci&oacute;n</div>
                <strong>' . $h($data['code']) . '</strong>
            </td>
        </tr>
    </table>

    <p class="c-note">Documento generado autom&aacute;ticamente por el sistema de banco de tiempo SkillSwap a
       partir de los intercambios con confirmaci&oacute;n mutua y doble validaci&oacute;n de horas.
       No requiere firma manuscrita.</p>

    <table class="c-foot">
        <tr>
            <td>
                <div class="c-seal">Validado<br><small>' . date('Y') . '</small></div>
            </td>
            <td>
                <div class="c-sign">
                    Comit&eacute; de Desarrollo Acad&eacute;mico
                    <br><small>SkillSwap Campus</small>
                </div>
            </td>
        </tr>
    </table>
  </td></tr></table>
</div>';
}

/**
 * Genera el PDF A4 (una sola hoja) a partir de los datos de la constancia.
 */
function certificateRenderPdf(array $data): string {
    @ini_set('memory_limit', '256M');
    require_once __DIR__ . '/dompdf/autoload.inc.php';

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);   // nada de CDN ni imagenes remotas
    $options->set('isPhpEnabled', false);
    $options->set('chroot', dirname(__DIR__));

    $dompdf = new \Dompdf\Dompdf($options);
    $html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
          . '<style>@page { size: A4; margin: 0; } ' . certificateSheetCss()
          // En el PDF la hoja NO lleva alto fijo: asi nunca rebosa a una 2a pagina.
          . ' .c-sheet { height: auto !important; overflow: visible !important; }</style>'
          . '</head><body>' . certificateSheetBody($data, true) . '</body></html>';

    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}
