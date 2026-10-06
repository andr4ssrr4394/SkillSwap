<?php

function sanitize($conn, $data) {
    return $conn->real_escape_string(htmlspecialchars(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
    } else {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}

function formatHours($hours) {
    $h = (float)$hours;
    $value = ($h == (int)$h) ? (int)$h : rtrim(rtrim(number_format($h, 1), '0'), '.');
    return $value . ' h';
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' año' . ($diff->y > 1 ? 's' : '');
    if ($diff->m > 0) return $diff->m . ' mes' . ($diff->m > 1 ? 'es' : '');
    if ($diff->d > 0) return $diff->d . ' día' . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) return $diff->h . ' hora' . ($diff->h > 1 ? 's' : '');
    if ($diff->i > 0) return $diff->i . ' min';
    return 'ahora';
}

function getLevelLabel($level) {
    $labels = [
        'beginner' => 'Principiante',
        'intermediate' => 'Intermedio',
        'advanced' => 'Avanzado'
    ];
    return $labels[$level] ?? $level;
}

function getLevelBadgeClass($level) {
    $classes = [
        'beginner' => 'bg-success',
        'intermediate' => 'bg-warning text-dark',
        'advanced' => 'bg-danger'
    ];
    return $classes[$level] ?? 'bg-secondary';
}

function getStatusLabel($status) {
    $labels = [
        'pending' => 'Pendiente',
        'accepted' => 'Aceptado',
        'in_progress' => 'En progreso',
        'completed' => 'Completado',
        'rejected' => 'Rechazado',
        'cancelled' => 'Cancelado'
    ];
    return $labels[$status] ?? $status;
}

function getStatusBadgeClass($status) {
    $classes = [
        'pending' => 'bg-warning text-dark',
        'accepted' => 'bg-info',
        'in_progress' => 'bg-primary',
        'completed' => 'bg-success',
        'rejected' => 'bg-danger',
        'cancelled' => 'bg-secondary'
    ];
    return $classes[$status] ?? 'bg-secondary';
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_check() {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Petición no válida.');
    }
}

function getUserRoleInExchange($exchange, $userId) {
    if ($exchange['proposer_id'] === $userId) return 'proposer';
    if ($exchange['acceptor_id'] === $userId) return 'acceptor';
    return null;
}

function canAcceptExchange($exchange, $userId) {
    return $exchange['status'] === 'pending' && $exchange['acceptor_id'] === $userId;
}

function canCompleteExchange($exchange, $userId) {
    $roles = ['pending', 'accepted', 'in_progress'];
    return in_array($exchange['status'], $roles)
        && ($exchange['proposer_id'] === $userId || $exchange['acceptor_id'] === $userId);
}

function isParticipant($exchange, $userId) {
    return $exchange['proposer_id'] === $userId || $exchange['acceptor_id'] === $userId;
}

function getUserRating($conn, $userId) {
    $stmt = $conn->prepare("SELECT AVG(score) as avg_rating, COUNT(*) as total FROM ratings WHERE rated_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

function renderStars($rating) {
    $full = floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    $html = str_repeat('<i class="bi bi-star-fill text-warning"></i> ', $full);
    $html .= str_repeat('<i class="bi bi-star-half text-warning"></i> ', $half);
    $html .= str_repeat('<i class="bi bi-star text-warning"></i> ', $empty);
    return $html;
}

function uploadCertificateFile(array $file): array {
    if (!isset($file['error'], $file['tmp_name'], $file['size'])) {
        return ['error' => 'Subida de archivo inválida.', 'path' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Error al subir el archivo.', 'path' => null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'Archivo inválido.', 'path' => null];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['error' => 'El archivo supera el tamaño máximo de 2 MB.', 'path' => null];
    }
    $head = @file_get_contents($file['tmp_name'], false, null, 0, 8);
    if ($head !== false && substr($head, 0, 4) === '%PDF') {
        $realType = 'pdf';
    } else {
        $img = @getimagesize($file['tmp_name']);
        if (is_array($img) && in_array($img[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            $realType = $img[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        } else {
            return ['error' => 'Solo se permiten imágenes (PNG/JPG) y archivos PDF.', 'path' => null];
        }
    }
    $dir = __DIR__ . '/../uploads/certificates';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return ['error' => 'No se pudo crear la carpeta de certificados.', 'path' => null];
        }
        @chmod($dir, 0777);
    }
    $name = bin2hex(random_bytes(16)) . '.' . $realType;
    $target = $dir . '/' . $name;
    if (!@move_uploaded_file($file['tmp_name'], $target)) {
        return ['error' => 'No se pudo guardar el archivo.', 'path' => null];
    }
    return ['error' => '', 'path' => 'uploads/certificates/' . $name];
}

// ---------------------------------------------------------------------------
// Notificaciones (campana del encabezado)
// ---------------------------------------------------------------------------

function createNotification($conn, $userId, $type, $title, $message, $link = null) {
    $userId = (int)$userId;
    if ($userId <= 0 || empty($title) || empty($message)) {
        return false;
    }
    $type = substr((string)$type, 0, 50);
    $title = mb_substr((string)$title, 0, 150);
    $message = mb_substr((string)$message, 0, 255);
    $link = $link ? mb_substr((string)$link, 0, 255) : null;
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $type, $title, $message, $link);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    } catch (Throwable $e) {
        error_log('SkillSwap createNotification: ' . $e->getMessage());
        return false;
    }
}

function getUnreadNotificationCount($conn, $userId) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

function getRecentNotifications($conn, $userId, $limit = 5) {
    $limit = max(1, min(20, (int)$limit));
    $stmt = $conn->prepare("SELECT id, type, title, message, link, is_read, created_at
                            FROM notifications WHERE user_id = ?
                            ORDER BY created_at DESC, id DESC LIMIT ?");
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function markAllNotificationsRead($conn, $userId) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

// ---------------------------------------------------------------------------
// Avatar de perfil
// ---------------------------------------------------------------------------

function getUserInitials($name) {
    $parts = preg_split('/\s+/', trim((string)$name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return '?';
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/**
 * URL del avatar o null si el usuario no tiene foto.
 * La extensión SIEMPRE se deriva del contenido validado (nunca del nombre enviado
 * por el cliente), de modo que un archivo PHP no pueda disfrazarse de imagen.
 */
function getAvatarUrl($user) {
    if (empty($user['profile_image'])) {
        return null;
    }
    $base = basename((string)$user['profile_image']);
    $path = __DIR__ . '/../assets/img/avatars/' . $base;
    if (!is_file($path)) {
        return null;
    }
    return '/skillswap/assets/img/avatars/' . rawurlencode($base);
}

function uploadAvatarImage(array $file) {
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!isset($file['error'], $file['tmp_name'], $file['size'])) {
        return ['error' => 'Subida de archivo inválida.', 'path' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Error al subir la imagen.', 'path' => null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'Archivo inválido.', 'path' => null];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['error' => 'La imagen no debe superar los 2 MB.', 'path' => null];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!is_array($info) || !isset($allowed[$info[2]])) {
        return ['error' => 'Formato inválido. Solo se admiten JPG, PNG y WebP.', 'path' => null];
    }
    $dir = __DIR__ . '/../assets/img/avatars';
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        return ['error' => 'No se pudo crear la carpeta de avatares.', 'path' => null];
    }
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$info[2]];
    if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['error' => 'No se pudo guardar la imagen.', 'path' => null];
    }
    return ['error' => '', 'path' => $name];
}

function deleteAvatarFile($storedName) {
    if (empty($storedName)) return;
    $base = basename((string)$storedName);
    $path = __DIR__ . '/../assets/img/avatars/' . $base;
    if (is_file($path)) {
        @unlink($path);
    }
}

// ---------------------------------------------------------------------------
// Insignias de reconocimiento
// ---------------------------------------------------------------------------

function getUserBadges($conn, $userId) {
    $userId = (int)$userId;
    $badges = [];

    $rating = getUserRating($conn, $userId);
    if ((int)$rating['total'] >= 3 && (float)$rating['avg_rating'] >= 4.5) {
        $badges[] = [
            'name' => 'Tutor 5 Estrellas',
            'icon' => 'bi-award-fill',
            'class' => 'bg-warning text-dark',
            'desc' => 'Calificación promedio de 4.5 o más con 3 o más valoraciones'
        ];
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM exchanges
                            WHERE (proposer_id = ? OR acceptor_id = ?) AND status = 'completed'");
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $completed = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    if ($completed >= 1) {
        $badges[] = [
            'name' => 'Pionero',
            'icon' => 'bi-rocket-takeoff-fill',
            'class' => 'bg-primary',
            'desc' => 'Completó su primer intercambio con éxito'
        ];
    }
    if ($completed >= 5) {
        $badges[] = [
            'name' => 'Mentor Experimentado',
            'icon' => 'bi-patch-check-fill',
            'class' => 'bg-success',
            'desc' => 'Finalizó 5 o más sesiones de intercambio'
        ];
    }

    $stmt = $conn->prepare("SELECT hours_balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $hours = (float)($stmt->get_result()->fetch_assoc()['hours_balance'] ?? 0);
    $stmt->close();

    if ($hours >= 15) {
        $badges[] = [
            'name' => 'Máster del Tiempo',
            'icon' => 'bi-clock-history',
            'class' => 'bg-info text-dark',
            'desc' => 'Acumula 15 o más horas en el banco de tiempo'
        ];
    }

    return $badges;
}

// ---------------------------------------------------------------------------
// Modalidad de la sesión
// ---------------------------------------------------------------------------

function getMeetingTypeLabel($type) {
    return ($type === 'presencial') ? 'Presencial' : 'Virtual';
}

function getMeetingTypeBadgeClass($type) {
    return ($type === 'presencial') ? 'bg-secondary' : 'bg-info text-dark';
}
