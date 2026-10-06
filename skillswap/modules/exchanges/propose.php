<?php
// El manejo del POST va ANTES de emitir HTML: si se imprime la página primero,
// redirect() ya no puede enviar la cabecera Location.
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$currentUser = getCurrentUser();
$conn = getConnection();
$userId = $_SESSION['user_id'];
$targetUserId = intval($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
$preselectedRequested = intval($_GET['skill_id'] ?? 0);
$preselectedOffered = intval($_GET['offered_skill'] ?? 0);

if ($targetUserId === 0 || $targetUserId === $userId) {
    redirect('/skillswap/modules/dashboard/index.php');
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $targetUserId);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$target) {
    flash('error', 'Usuario no encontrado.');
    redirect('/skillswap/modules/dashboard/index.php');
}

$targetBalance = (float)$target['hours_balance'];

// Habilidades que YO puedo enseñar (todas las que ofrezco; el otro decide si acepta)
$stmt = $conn->prepare("
    SELECT uso.skill_id AS id, s.name AS skill_name
    FROM user_skills_offered uso
    JOIN skills s ON uso.skill_id = s.id
    WHERE uso.user_id = ?
    ORDER BY s.name
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myOfferedSkills = $stmt->get_result();
$stmt->close();

// Habilidades que el TARGET puede enseñar (las que él ofrece)
$stmt = $conn->prepare("
    SELECT uso.skill_id AS id, s.name AS skill_name
    FROM user_skills_offered uso
    JOIN skills s ON uso.skill_id = s.id
    WHERE uso.user_id = ?
    ORDER BY s.name
");
$stmt->bind_param("i", $targetUserId);
$stmt->execute();
$targetOfferedSkills = $stmt->get_result();
$stmt->close();

function decimal_step($v) {
    $h = round((float)$v * 2) / 2;
    return abs($h - (float)$v) < 0.0001 ? $h : null;
}

$success = null;
$error = null;
$myBalance = (float)$currentUser['hours_balance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $offeredId = intval($_POST['skill_offered_id'] ?? 0);
    $requestedId = intval($_POST['skill_requested_id'] ?? 0);
    $proposerHours = decimal_step($_POST['proposer_hours'] ?? 0);
    $acceptorHours = decimal_step($_POST['acceptor_hours'] ?? 0);
    $scheduledRaw = trim($_POST['scheduled_at'] ?? '');
    $scheduledAt = null;

    if ($scheduledRaw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $scheduledRaw);
        if ($dt) {
            $scheduledAt = $dt->format('Y-m-d H:i:s');
        } else {
            $error = 'Fecha/hora de sesión no válida.';
        }
    }

    // Modalidad de la sesión (virtual o presencial)
    $meetingType = ($_POST['meeting_type'] ?? 'virtual') === 'presencial' ? 'presencial' : 'virtual';
    $meetingLink = trim($_POST['meeting_link'] ?? '');
    $meetingLocation = trim($_POST['meeting_location'] ?? '');
    $meetingLink = mb_substr($meetingLink, 0, 255);
    $meetingLocation = mb_substr($meetingLocation, 0, 255);

    if ($meetingType === 'virtual') {
        $meetingLocation = '';
    } else {
        $meetingLink = '';
    }
    if (!$error && $meetingType === 'virtual' && $meetingLink !== '' && !filter_var($meetingLink, FILTER_VALIDATE_URL)) {
        $error = 'El enlace de la sesión virtual no es una URL válida.';
    }

    // La dirección sin habilidad queda en 0 horas
    if ($offeredId === 0) $proposerHours = 0;
    if ($requestedId === 0) $acceptorHours = 0;

    if (!$error) {
        // Validar que las habilidades sean reales y del dueño correcto
        $validOffered = $offeredId === 0;
        if ($offeredId > 0) {
            $stmt = $conn->prepare("SELECT 1 FROM user_skills_offered WHERE user_id = ? AND skill_id = ?");
            $stmt->bind_param("ii", $userId, $offeredId);
            $stmt->execute();
            $validOffered = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }

        $validRequested = $requestedId === 0;
        if ($requestedId > 0) {
            $stmt = $conn->prepare("SELECT 1 FROM user_skills_offered WHERE user_id = ? AND skill_id = ?");
            $stmt->bind_param("ii", $targetUserId, $requestedId);
            $stmt->execute();
            $validRequested = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }

        if ($proposerHours === null || $acceptorHours === null) {
            $error = 'Las horas deben ser en pasos de media hora (0.5).';
        } elseif (!$validOffered || !$validRequested) {
            $error = 'Seleccionaste una habilidad que no corresponde a tu perfil o al del otro estudiante.';
        } elseif ($offeredId !== 0 && $offeredId === $requestedId) {
            $error = 'No puedes proponer y recibir la misma habilidad en el mismo intercambio.';
        } elseif ($proposerHours < 0 || $proposerHours > 24 || $acceptorHours < 0 || $acceptorHours > 24) {
            $error = 'Las horas deben estar entre 0 y 24.';
        } elseif (($proposerHours + $acceptorHours) <= 0) {
            $error = 'Debe haber al menos media hora de intercambio (enseñando o aprendiendo).';
        } else {
            // Saldos proyectados de AMBOS participantes
            $myProj = $myBalance + $proposerHours - $acceptorHours;
            $targetProj = $targetBalance + $acceptorHours - $proposerHours;

            if ($myProj < 0 || $targetProj < 0) {
                $error = 'Saldo insuficiente para este intercambio. ';
                if ($myProj < 0) $error .= "Tu saldo proyectado quedaría en {$myProj}h.";
                if ($targetProj < 0) $error .= " El saldo proyectado de {$target['name']} quedaría en {$targetProj}h.";
                $error .= ' Se paga con horas lo que se aprende.';
            } else {
                // Dedupe: mismo estudiante + mismas habilidades + propuesta pendiente/activa
                $stmt = $conn->prepare("
                    SELECT id FROM exchanges
                    WHERE proposer_id = ? AND acceptor_id = ?
                      AND (skill_offered_id <=> ?) AND skill_requested_id = ?
                      AND status IN ('pending','accepted','in_progress')
                    LIMIT 1
                ");
                $nullOffered = $offeredId > 0 ? $offeredId : null;
                $nullRequested = $requestedId > 0 ? $requestedId : null;
                $stmt->bind_param("iiii", $userId, $targetUserId, $nullOffered, $requestedId);
                $stmt->execute();
                $dup = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if ($dup) {
                    $error = 'Ya tienes una propuesta pendiente o activa con este estudiante y estas habilidades.';
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO exchanges (proposer_id, acceptor_id, skill_offered_id, skill_requested_id, proposer_hours, acceptor_hours, status, scheduled_at, meeting_type, meeting_location, meeting_link)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iiidddssss", $userId, $targetUserId, $nullOffered, $nullRequested, $proposerHours, $acceptorHours, $scheduledAt, $meetingType, $meetingLocation, $meetingLink);
                    if ($stmt->execute()) {
                        $what = $requestedId > 0 ? 'aprender de ' . $target['name'] : 'enseñarle a ' . $target['name'];
                        createNotification(
                            $conn,
                            $targetUserId,
                            'exchange_proposed',
                            'Nueva propuesta de intercambio',
                            ($currentUser['name'] ?? 'Un estudiante') . ' te propuso un intercambio #' . $conn->insert_id . '.',
                            '/skillswap/modules/exchanges/index.php'
                        );
                        flash('success', 'Propuesta de intercambio enviada para ' . $what . '. Espera la respuesta de ' . htmlspecialchars($target['name']) . '.');
                        $conn->close();
                        redirect('/skillswap/modules/exchanges/index.php');
                    } else {
                        $error = 'Error al crear la propuesta.';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// A partir de aquí se emite la página (header incluido)
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="thread-header">
    <h2><i class="bi bi-arrow-repeat"></i> Proponer Intercambio</h2>
    <p class="text-muted">Con <strong><?= htmlspecialchars($target['name']) ?></strong></p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php elseif ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" class="reddit-card">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= $targetUserId ?>">

            <div class="mb-4">
                <label class="form-label fw-bold">Lo que YO le enseño a <?= htmlspecialchars($target['name']) ?></label>
                <small class="text-muted d-block mb-2">Opcional. Elige una habilidad de tu perfil (o "no enseñaré nada"). Tú fijas las horas que ganas.</small>
                <?php if ($myOfferedSkills->num_rows > 0): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="skill_offered_id" value="0" id="off_0" checked onclick="toggleOffered()">
                        <label class="form-check-label" for="off_0">No enseñaré nada (solo aprender)</label>
                    </div>
                    <?php while ($s = $myOfferedSkills->fetch_assoc()): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="skill_offered_id"
                                   value="<?= $s['id'] ?>" id="off_<?= $s['id'] ?>"
                                   <?= $s['id'] == $preselectedOffered ? 'checked' : '' ?>
                                   onclick="toggleOffered()">
                            <label class="form-check-label" for="off_<?= $s['id'] ?>">
                                <?= htmlspecialchars($s['skill_name']) ?>
                            </label>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-warning">
                        No tienes habilidades ofrecidas. <a href="/skillswap/modules/profile/edit.php">Agrégalas</a> para poder enseñar y ganar horas.
                    </div>
                    <input type="hidden" name="skill_offered_id" value="0">
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Lo que QUIERO APRENDER de <?= htmlspecialchars($target['name']) ?></label>
                <small class="text-muted d-block mb-2">Opcional. Debe ser algo que <?= htmlspecialchars($target['name']) ?> ofrece. Estas horas las pagas.</small>
                <?php if ($targetOfferedSkills->num_rows > 0): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="skill_requested_id" value="0" id="req_0" checked onclick="toggleRequested()">
                        <label class="form-check-label" for="req_0">Solo enseñar (no aprender)</label>
                    </div>
                    <?php while ($s = $targetOfferedSkills->fetch_assoc()): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="skill_requested_id"
                                   value="<?= $s['id'] ?>" id="req_<?= $s['id'] ?>"
                                   <?= $s['id'] == $preselectedRequested ? 'checked' : '' ?>
                                   onclick="toggleRequested()">
                            <label class="form-check-label" for="req_<?= $s['id'] ?>">
                                <?= htmlspecialchars($s['skill_name']) ?>
                            </label>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <?= htmlspecialchars($target['name']) ?> no ha agregado habilidades que ofrece.
                    </div>
                    <input type="hidden" name="skill_requested_id" value="0">
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Horas del intercambio (paso de 0.5 h)</label>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Horas que YO doy de clase (lo que gano)</label>
                        <input type="number" class="form-control" name="proposer_hours" id="proposerHours" value="0" min="0" max="24" step="0.5" oninput="upd()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Horas que APRENDO de <?= htmlspecialchars($target['name']) ?> (lo que pago)</label>
                        <input type="number" class="form-control" name="acceptor_hours" id="acceptorHours" value="0" min="0" max="24" step="0.5" oninput="upd()">
                    </div>
                </div>
                <small class="text-muted d-block mt-2">
                    Tu saldo proyectado: <span class="fw-bold text-success" id="myProj"><?= formatHours($myBalance) ?></span>
                    · Saldo proyectado de <?= htmlspecialchars($target['name']) ?>: <span class="fw-bold" id="targetProj"><?= formatHours($targetBalance) ?></span>
                    (actual: <?= formatHours($myBalance) ?> / <?= formatHours($targetBalance) ?>) ·
                    los saldos nunca quedan negativos.
                </small>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Programar sesión (opcional)</label>
                <input type="datetime-local" class="form-control" name="scheduled_at" id="scheduledAt">
                <small class="text-muted d-block mt-1">Si acuerdan una fecha y hora para la sesión, aparecerá como recordatorio.</small>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Modalidad de la sesión</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="meeting_type" value="virtual"
                           id="mtVirtual" <?= ($meetingType ?? 'virtual') === 'presencial' ? '' : 'checked' ?>
                           data-meeting="virtual" onclick="toggleMeeting()">
                    <label class="form-check-label" for="mtVirtual">
                        <i class="bi bi-camera-video"></i> Virtual (videollamada)
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="meeting_type" value="presencial"
                           id="mtPresencial" <?= ($meetingType ?? 'virtual') === 'presencial' ? 'checked' : '' ?>
                           data-meeting="presencial" onclick="toggleMeeting()">
                    <label class="form-check-label" for="mtPresencial">
                        <i class="bi bi-geo-alt"></i> Presencial
                    </label>
                </div>

                <div class="mt-3" id="meetingLinkWrap">
                    <label class="form-label small fw-semibold">Enlace de la sesión</label>
                    <input type="url" class="form-control" name="meeting_link" maxlength="255"
                           placeholder="https://meet.google.com/..." value="<?= htmlspecialchars($meetingLink ?? '') ?>">
                    <small class="text-muted">Opcional. Se muestra solo a la otra persona.</small>
                </div>

                <div class="mt-3 d-none" id="meetingLocationWrap">
                    <label class="form-label small fw-semibold">Punto de encuentro</label>
                    <input type="text" class="form-control" name="meeting_location" maxlength="255"
                           placeholder="Biblioteca central, aula 204..." value="<?= htmlspecialchars($meetingLocation ?? '') ?>">
                    <small class="text-muted">Opcional. Se muestra solo a la otra persona.</small>
                </div>
            </div>

            <script>
            function toggleMeeting() {
                var selected = document.querySelector('input[name="meeting_type"]:checked');
                var isVirtual = !selected || selected.value === 'virtual';
                document.getElementById('meetingLinkWrap').classList.toggle('d-none', !isVirtual);
                document.getElementById('meetingLocationWrap').classList.toggle('d-none', isVirtual);
            }
            document.addEventListener('DOMContentLoaded', toggleMeeting);
            </script>

            <script>
            (function() {
                var myBal = <?= json_encode($myBalance) ?>;
                var tarBal = <?= json_encode($targetBalance) ?>;
                var myProj = document.getElementById('myProj');
                var tProj = document.getElementById('targetProj');
                var gInput = document.getElementById('proposerHours');
                var pInput = document.getElementById('acceptorHours');
                function fmt(v) {
                    var x = Math.round(v * 100) / 100;
                    return x + ' h';
                }
                function upd() {
                    var g = parseFloat(gInput.value) || 0;
                    var p = parseFloat(pInput.value) || 0;
                    var mv = myBal + g - p;
                    var tv = tarBal + p - g;
                    myProj.textContent = fmt(mv);
                    tProj.textContent = fmt(tv);
                    myProj.style.color = mv < 0 ? '#dc3545' : '#198754';
                    tProj.style.color = tv < 0 ? '#dc3545' : '#198754';
                }
                window.upd = upd;
                function toggleOffered() {
                    var checked = document.querySelector('input[name=skill_offered_id]:checked');
                    gInput.disabled = !checked || checked.value === '0';
                }
                function toggleRequested() {
                    var checked = document.querySelector('input[name=skill_requested_id]:checked');
                    pInput.disabled = !checked || checked.value === '0';
                }
                window.toggleOffered = toggleOffered;
                window.toggleRequested = toggleRequested;
                if (document.querySelector('input[name=skill_offered_id][value=0]')) gInput.disabled = true;
                if (document.querySelector('input[name=skill_requested_id][value=0]')) pInput.disabled = true;
                upd();
            })();
            </script>

            <button type="submit" class="btn btn-orange">
                <i class="bi bi-send"></i> Enviar Propuesta
            </button>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="reddit-sidebar">
            <div class="text-center">
                <i class="bi bi-person-circle" style="font-size:3rem"></i>
                <h5 class="mt-2"><?= htmlspecialchars($target['name']) ?></h5>
                <p class="text-muted mb-0"><?= htmlspecialchars($target['career'] ?? '') ?></p>
                <p class="text-muted small">Saldo: <?= formatHours($targetBalance) ?></p>
                <a href="/skillswap/modules/profile/view.php?id=<?= $targetUserId ?>" class="btn btn-sm btn-outline-primary mt-2">Ver perfil</a>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
?>