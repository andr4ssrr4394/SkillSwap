<?php
// Exportación de datos a CSV (solo administradores).
// Usa el separador ';' y BOM UTF-8 para que Excel en español lo abra correctamente.
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$type = $_GET['type'] ?? 'users';
$conn = getConnection();

function csvHeaders($filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

if ($type === 'users') {
    csvHeaders('usuarios_skillswap_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['ID', 'Nombre', 'Correo', 'Carrera', 'Semestre', 'Saldo de horas', 'Rol',
                   'Intercambios completados', 'Valoración promedio', 'Fecha de registro'], ';');

    $res = $conn->query("
        SELECT u.id, u.name, u.email, u.career, u.semester, u.hours_balance, u.role, u.created_at,
               (SELECT COUNT(*) FROM exchanges
                 WHERE (proposer_id = u.id OR acceptor_id = u.id) AND status = 'completed') AS completed,
               (SELECT AVG(score) FROM ratings WHERE rated_id = u.id) AS avg_rating
        FROM users u
        ORDER BY u.id ASC
    ");
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['name'],
            $row['email'],
            $row['career'] !== '' && $row['career'] !== null ? $row['career'] : '-',
            $row['semester'] ? $row['semester'] . '°' : '-',
            $row['hours_balance'],
            $row['role'],
            $row['completed'],
            $row['avg_rating'] !== null ? number_format((float)$row['avg_rating'], 2) : 'Sin valoraciones',
            date('d/m/Y', strtotime($row['created_at']))
        ], ';');
    }
    fclose($out);
    $conn->close();
    exit;
}

if ($type === 'exchanges') {
    csvHeaders('intercambios_skillswap_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['ID', 'Proponente', 'Aceptante', 'Habilidad ofrecida', 'Habilidad solicitada',
                   'Horas del proponente', 'Horas del aceptante', 'Estado', 'Modalidad',
                   'Fecha programada', 'Propuesta', 'Completado'], ';');

    $res = $conn->query("
        SELECT e.id,
               p.name AS proposer, a.name AS acceptor,
               so.name AS offered, sr.name AS requested,
               e.proposer_hours, e.acceptor_hours, e.status, e.meeting_type,
               e.scheduled_at, e.proposed_at, e.completed_at
        FROM exchanges e
        JOIN users p ON p.id = e.proposer_id
        JOIN users a ON a.id = e.acceptor_id
        LEFT JOIN skills so ON so.id = e.skill_offered_id
        LEFT JOIN skills sr ON sr.id = e.skill_requested_id
        ORDER BY e.id DESC
    ");
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['proposer'],
            $row['acceptor'],
            $row['offered'] ?: '-',
            $row['requested'] ?: '-',
            $row['proposer_hours'],
            $row['acceptor_hours'],
            getStatusLabel($row['status']),
            getMeetingTypeLabel($row['meeting_type'] ?? 'virtual'),
            $row['scheduled_at'] ? date('d/m/Y H:i', strtotime($row['scheduled_at'])) : '-',
            date('d/m/Y H:i', strtotime($row['proposed_at'])),
            $row['completed_at'] ? date('d/m/Y H:i', strtotime($row['completed_at'])) : '-'
        ], ';');
    }
    fclose($out);
    $conn->close();
    exit;
}

$conn->close();
flash('error', 'Tipo de exportación no válido.');
redirect('/skillswap/admin/reports.php');