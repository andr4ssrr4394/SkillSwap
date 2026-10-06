<?php
// El POST se procesa antes de emitir HTML para que redirect() funcione
// (si la página ya se imprimió, la cabecera Location ya no se puede enviar).
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];
$exchangeId = intval($_GET['exchange_id'] ?? 0);

if ($exchangeId === 0) {
    // Inbox: list all exchanges the user participates in (pending+active+completed)
    $inbox = $conn->prepare("
        SELECT e.*, so.name AS offered_name, sr.name AS requested_name,
               p.name AS proposer_name, a.name AS acceptor_name,
               (SELECT COUNT(*) FROM messages m WHERE m.exchange_id = e.id) AS msg_count,
               (SELECT MAX(m.created_at) FROM messages m WHERE m.exchange_id = e.id) AS last_msg
        FROM exchanges e
        LEFT JOIN skills so ON e.skill_offered_id = so.id
        LEFT JOIN skills sr ON e.skill_requested_id = sr.id
        JOIN users p ON e.proposer_id = p.id
        JOIN users a ON e.acceptor_id = a.id
        WHERE e.proposer_id = ? OR e.acceptor_id = ?
        ORDER BY last_msg DESC, e.proposed_at DESC
    ");
    $inbox->bind_param("ii", $userId, $userId);
    $inbox->execute();
    $inboxResult = $inbox->get_result();
    $inbox->close();
    require_once __DIR__ . '/../../includes/header.php';
    ?>
    <div class="thread-header mb-4">
        <h2><i class="bi bi-chat-left-text"></i> Mensajes</h2>
        <p class="text-muted">Conversaciones de tus intercambios.</p>
    </div>

    <?php if ($inboxResult->num_rows > 0): ?>
        <?php while ($c = $inboxResult->fetch_assoc()): ?>
            <?php $otherName = $userId == $c['proposer_id'] ? $c['acceptor_name'] : $c['proposer_name']; ?>
            <a href="/skillswap/modules/messages/index.php?exchange_id=<?= $c['id'] ?>" class="text-decoration-none">
                <div class="reddit-card mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Intercambio #<?= $c['id'] ?></strong>
                            <span class="badge <?= getStatusBadgeClass($c['status']) ?> ms-1"><?= getStatusLabel($c['status']) ?></span>
                            <div class="small text-muted">
                                <i class="bi bi-person"></i> <?= htmlspecialchars($otherName) ?> ·
                                <?= $c['skill_offered_id'] ? htmlspecialchars($c['offered_name']) : 'solo aprendizaje' ?> ↔ <?= $c['skill_requested_id'] ? htmlspecialchars($c['requested_name']) : 'solo enseñar' ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary"><?= $c['msg_count'] ?> msj</span>
                        </div>
                    </div>
                </div>
            </a>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-chat-square-text display-1 text-muted"></i>
            <p class="text-muted mt-3">No tienes conversaciones aún.</p>
            <a href="/skillswap/modules/exchanges/index.php" class="btn btn-primary">Ver tus intercambios</a>
        </div>
    <?php endif; ?>
    <?php
    $conn->close();
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$stmt = $conn->prepare("
    SELECT e.*, so.name AS offered_name, sr.name AS requested_name,
           p.name AS proposer_name, a.name AS acceptor_name
    FROM exchanges e
    LEFT JOIN skills so ON e.skill_offered_id = so.id
    LEFT JOIN skills sr ON e.skill_requested_id = sr.id
    JOIN users p ON e.proposer_id = p.id
    JOIN users a ON e.acceptor_id = a.id
    WHERE e.id = ?
");
$stmt->bind_param("i", $exchangeId);
$stmt->execute();
$exchange = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exchange || !isParticipant($exchange, $userId)) {
    flash('error', 'No participas en este intercambio.');
    redirect('/skillswap/modules/exchanges/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $content = trim($_POST['content'] ?? '');
    if ($content !== '') {
        $stmt = $conn->prepare("INSERT INTO messages (exchange_id, sender_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $exchangeId, $userId, $content);
        $stmt->execute();
        $stmt->close();
    }
    redirect('/skillswap/modules/messages/index.php?exchange_id=' . $exchangeId);
}

// A partir de aquí se emite la página
require_once __DIR__ . '/../../includes/header.php';

$otherId = $userId == $exchange['proposer_id'] ? $exchange['acceptor_id'] : $exchange['proposer_id'];
$otherName = $userId == $exchange['proposer_id'] ? $exchange['acceptor_name'] : $exchange['proposer_name'];

$messages = $conn->prepare("
    SELECT m.*, u.name AS sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.exchange_id = ?
    ORDER BY m.created_at ASC
");
$messages->bind_param("i", $exchangeId);
$messages->execute();
$msgs = $messages->get_result();
$messages->close();
?>

<div class="reddit-card mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Mensajes del Intercambio #<?= $exchange['id'] ?></h5>
            <small class="text-muted">
                Conversando con <strong><?= htmlspecialchars($otherName) ?></strong> ·
                <?php if ($exchange['proposer_id'] == $userId): ?>
                    Enseñas <?= $exchange['skill_offered_id'] ? htmlspecialchars($exchange['offered_name']) : 'nada (solo aprendizaje)' ?> · Aprendes <?= $exchange['skill_requested_id'] ? htmlspecialchars($exchange['requested_name']) : 'nada' ?>
                <?php else: ?>
                    Enseñas <?= $exchange['skill_requested_id'] ? htmlspecialchars($exchange['requested_name']) : 'nada (solo enseñar)' ?> · Aprendes <?= $exchange['skill_offered_id'] ? htmlspecialchars($exchange['offered_name']) : 'nada' ?>
                <?php endif; ?>
            </small>
        </div>
        <div>
            <span class="badge <?= getStatusBadgeClass($exchange['status']) ?>"><?= getStatusLabel($exchange['status']) ?></span>
            <a href="/skillswap/modules/exchanges/index.php" class="btn btn-sm btn-outline-secondary ms-2">Volver</a>
        </div>
    </div>
</div>

<div class="reddit-card mb-3">
    <div class="message-thread" id="msgThread">
        <?php if ($msgs->num_rows > 0): ?>
            <?php $lastMsgId = 0; while ($m = $msgs->fetch_assoc()): ?>
                <?php $lastMsgId = (int)$m['id']; ?>
                <?php $mine = $m['sender_id'] == $userId; ?>
                <div class="d-flex <?= $mine ? 'justify-content-end' : 'justify-content-start' ?> mb-3" data-msg-id="<?= (int)$m['id'] ?>">
                    <div class="message-bubble <?= $mine ? 'bg-primary text-white' : 'bg-light' ?>" style="max-width:75%">
                        <div class="small <?= $mine ? 'text-white-50' : 'text-muted' ?>">
                            <strong><?= $mine ? 'Tú' : htmlspecialchars($m['sender_name']) ?></strong>
                            · <?= date('d/m H:i', strtotime($m['created_at'])) ?>
                        </div>
                        <div class="mt-1"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-muted text-center py-4">No hay mensajes aún. Coordinen los detalles del intercambio.</p>
        <?php endif; ?>
    </div>
</div>

<?php if (in_array($exchange['status'], ['accepted', 'in_progress', 'pending'])): ?>
<div class="reddit-card">
    <form method="POST" id="msgForm">
        <?= csrf_field() ?>
        <input type="hidden" name="exchange_id" value="<?= (int)$exchangeId ?>">
        <div class="mb-2">
            <textarea class="form-control" name="content" id="msgContent" rows="2" maxlength="2000"
                      placeholder="Escribe un mensaje para coordinar..." required></textarea>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Enviar</button>
            <small class="text-muted" id="msgStatus"></small>
        </div>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('msgForm');
    var thread = document.getElementById('msgThread');
    if (!form || !thread) return;

    var exchangeId = <?= (int)$exchangeId ?>;
    var lastId = <?= isset($lastMsgId) ? (int)$lastMsgId : 0 ?>;
    var pollBusy = false;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : text;
        return div.innerHTML;
    }

    function nearBottom() {
        return (thread.scrollHeight - thread.scrollTop - thread.clientHeight) < 90;
    }

    function appendMessage(msg) {
        var wrap = document.createElement('div');
        wrap.className = 'd-flex ' + (msg.mine ? 'justify-content-end' : 'justify-content-start') + ' mb-3';
        wrap.dataset.msgId = msg.id;

        var bubble = document.createElement('div');
        bubble.className = 'message-bubble ' + (msg.mine ? 'bg-primary text-white' : 'bg-light');
        bubble.style.maxWidth = '75%';

        var head = document.createElement('div');
        head.className = 'small ' + (msg.mine ? 'text-white-50' : 'text-muted');
        var who = document.createElement('strong');
        who.textContent = msg.mine ? 'Tú' : msg.sender_name;
        head.appendChild(who);
        head.appendChild(document.createTextNode(' · ' + msg.created_at));

        var body = document.createElement('div');
        body.className = 'mt-1';
        body.style.whiteSpace = 'pre-wrap';
        body.textContent = msg.content;

        bubble.appendChild(head);
        bubble.appendChild(body);
        wrap.appendChild(bubble);
        thread.appendChild(wrap);

        if (msg.id > lastId) lastId = msg.id;
    }

    function poll() {
        if (pollBusy || document.hidden) return;
        pollBusy = true;
        fetch('/skillswap/api/messages.php?exchange_id=' + encodeURIComponent(exchangeId) + '&last_id=' + lastId)
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.success || !data.messages || !data.messages.length) return;
                var stick = nearBottom();
                data.messages.forEach(appendMessage);
                if (stick) thread.scrollTop = thread.scrollHeight;
            })
            .catch(function () { /* silencio: se reintenta en el siguiente ciclo */ })
            .finally(function () { pollBusy = false; });
    }

    form.addEventListener('submit', function (event) {
        var content = document.getElementById('msgContent').value.trim();
        if (!content) return;
        event.preventDefault();

        var status = document.getElementById('msgStatus');
        status.textContent = 'Enviando...';

        var body = new URLSearchParams();
        body.append('exchange_id', exchangeId);
        body.append('content', content);
        body.append('csrf_token', form.querySelector('input[name="csrf_token"]').value);

        fetch('/skillswap/api/messages.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.success) throw new Error(data.error || 'Error al enviar');
            appendMessage(data.message);
            document.getElementById('msgContent').value = '';
            status.textContent = 'Enviado';
            thread.scrollTop = thread.scrollHeight;
            setTimeout(function () { status.textContent = ''; }, 2000);
        }).catch(function (err) {
            // Si la API falla se permite el envío tradicional del formulario
            status.textContent = 'Enviando de forma segura...';
            form.submit();
        });
    });

    thread.scrollTop = thread.scrollHeight;
    setInterval(poll, 4000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();
</script>
<?php endif; ?>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
?>
