<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$certId = intval($_GET['id'] ?? 0);
if ($certId <= 0) {
    http_response_code(404);
    exit('Not found');
}
$viewerId = $_SESSION['user_id'];

$conn = getConnection();
$stmt = $conn->prepare("SELECT file_path FROM certificates WHERE id = ? AND file_path IS NOT NULL");
$stmt->bind_param("i", $certId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) {
    http_response_code(404);
    exit('Not found');
}

$abs = realpath(__DIR__ . '/../..' . '/' . $row['file_path']);
$baseDir = realpath(__DIR__ . '/../..' . '/uploads');
if ($abs === false || $baseDir === false || strpos($abs, $baseDir . DIRECTORY_SEPARATOR) !== 0 || !is_file($abs)) {
    http_response_code(404);
    exit('Not found');
}

$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'pdf' => 'application/pdf',
    default => 'application/octet-stream',
};

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($abs) . '"');
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . filesize($abs));
readfile($abs);
exit;