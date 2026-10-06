<?php
// El POST se procesa antes de emitir HTML para que redirect() funcione
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];
$exchangeId = intval($_GET['exchange_id'] ?? $_POST['exchange_id'] ?? 0);

if ($exchangeId === 0) {
    redirect('/skillswap/modules/exchanges/index.php');
}

$stmt = $conn->prepare("
    SELECT e.*, so.name AS offered_name, sr.name AS requested_name,
           p.name AS proposer_name, a.name AS acceptor_name
    FROM exchanges e
    LEFT JOIN skills so ON e.skill_offered_id = so.id
    LEFT JOIN skills sr ON e.skill_requested_id = sr.id
    JOIN users p ON e.proposer_id = p.id
    JOIN users a ON e.acceptor_id = a.id
    WHERE e.id = ? AND e.status = 'completed'
      AND (e.proposer_id = ? OR e.acceptor_id = ?)
");
$stmt->bind_param("iii", $exchangeId, $userId, $userId);
$stmt->execute();
$exchange = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exchange) {
    flash('error', 'Intercambio no encontrado o no completado.');
    redirect('/skillswap/modules/exchanges/index.php');
}

// The person I'm rating is the other participant
$otherId = $userId == $exchange['proposer_id'] ? $exchange['acceptor_id'] : $exchange['proposer_id'];
$otherName = $userId == $exchange['proposer_id'] ? $exchange['acceptor_name'] : $exchange['proposer_name'];

// Check if already rated
$check = $conn->prepare("SELECT id FROM ratings WHERE exchange_id = ? AND rater_id = ?");
$check->bind_param("ii", $exchangeId, $userId);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    flash('error', 'Ya has valorado este intercambio.');
    redirect('/skillswap/modules/exchanges/index.php');
}
$check->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $score = intval($_POST['score'] ?? 0);
    $review = trim($_POST['review'] ?? '');

    if ($score >= 1 && $score <= 5) {
        $stmt = $conn->prepare("INSERT INTO ratings (exchange_id, rater_id, rated_id, score, review) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiis", $exchangeId, $userId, $otherId, $score, $review);
        if ($stmt->execute()) {
            $stmt->close();
            $me = getCurrentUser();
            createNotification(
                $conn,
                $otherId,
                'rating',
                'Nueva valoración',
                ($me['name'] ?? 'Alguien') . ' te valoraró con ' . $score . '/5 en el intercambio #' . $exchangeId . '.',
                '/skillswap/modules/profile/view.php?id=' . $userId
            );
            flash('success', 'Valoración enviada. ¡Gracias!');
            redirect('/skillswap/modules/exchanges/index.php');
        }
        $stmt->close();
    }
}

// A partir de aquí se emite la página
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="thread-header mb-4">
    <h2><i class="bi bi-star"></i> Valorar Intercambio</h2>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <form method="POST" class="reddit-card">
            <?= csrf_field() ?>
            <input type="hidden" name="exchange_id" value="<?= $exchangeId ?>">

            <div class="text-center mb-3">
                <h5 class="mb-1">Intercambio #<?= $exchangeId ?></h5>
                <p class="text-muted mb-0">
                    <?= $exchange['skill_offered_id'] ? htmlspecialchars($exchange['offered_name']) : 'solo aprendizaje' ?> ↔ <?= $exchange['skill_requested_id'] ? htmlspecialchars($exchange['requested_name']) : 'solo enseñar' ?>
                </p>
                <p class="text-muted mb-1">Con <strong><?= htmlspecialchars($otherName) ?></strong></p>
                <p class="text-muted small mb-0">Enseñaste <?= $exchange[$userId == $exchange['proposer_id'] ? 'proposer_hours' : 'acceptor_hours'] ?>h y pagaste <?= $exchange[$userId == $exchange['proposer_id'] ? 'acceptor_hours' : 'proposer_hours'] ?>h</p>
            </div>

            <div class="mb-3 text-center">
                <label class="form-label fw-bold">¿Cómo fue tu experiencia?</label>
                <div class="fs-2" id="starRating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi bi-star text-warning" data-value="<?= $i ?>" style="cursor:pointer" onclick="setRating(<?= $i ?>)"></i>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="score" id="ratingInput" value="0" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Reseña (opcional)</label>
                <textarea class="form-control" name="review" rows="3" placeholder="Cuéntanos sobre tu experiencia..."></textarea>
            </div>

            <button type="submit" class="btn btn-orange" id="submitBtn" disabled>
                <i class="bi bi-send"></i> Enviar Valoración
            </button>
        </form>
    </div>
</div>

<script>
function setRating(value) {
    document.getElementById('ratingInput').value = value;
    document.getElementById('submitBtn').disabled = false;
    const stars = document.querySelectorAll('#starRating i');
    stars.forEach((star, index) => {
        star.className = index < value ? 'bi bi-star-fill text-warning' : 'bi bi-star text-warning';
    });
}
</script>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
?>