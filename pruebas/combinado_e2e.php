<?php
/**
 * E2E del sistema combinado (skillswap-combinado) contra un entorno aislado:
 *   - BD: skillswap
 *   - Código servido en: http://localhost/skillswap/
 * No toca la base de datos real.
 */
$BASE = 'http://localhost/skillswap';
$DB   = 'skillswap';

$pass = 0; $fail = 0;
function ok($label, $cond, $extra = '') {
    global $pass, $fail;
    if ($cond) { $pass++; echo "OK   $label\n"; }
    else { $fail++; echo "FALLA $label" . ($extra ? " -> $extra" : '') . "\n"; }
}

$jarA = tempnam(sys_get_temp_dir(), 'ja');
$jarB = tempnam(sys_get_temp_dir(), 'jb');

function req($url, $post = null, $jar = null, $extraHeaders = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($jar) { curl_setopt($ch, CURLOPT_COOKIEJAR, $jar); curl_setopt($ch, CURLOPT_COOKIEFILE, $jar); }
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    if ($extraHeaders) curl_setopt($ch, CURLOPT_HTTPHEADER, $extraHeaders);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $hsize);
    $body = substr($raw, $hsize);
    preg_match('/location:\s*(.+)/i', $headers, $m);
    return ['code' => $code, 'body' => $body, 'loc' => isset($m[1]) ? trim($m[1]) : ''];
}

function csrfFrom($body) {
    if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $body, $m)) return $m[1];
    return '';
}

function apiPost($url, $fields, $jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 30,
    ]);
    $out = curl_exec($ch);
    curl_close($ch);
    return json_decode($out, true) ?: ['raw' => $out];
}

function apiGet($url, $jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 30,
    ]);
    $out = curl_exec($ch);
    curl_close($ch);
    return json_decode($out, true) ?: ['raw' => $out];
}

$db = new mysqli('localhost', 'root', '', $DB);
if ($db->connect_error) { echo "No se pudo conectar: {$db->connect_error}\n"; exit(1); }

$suffix = time();
$A = "com.a.$suffix@test.local";
$B = "com.b.$suffix@test.local";
$passA = 'ClaveSegura1!';

// ---------------------------------------------------------------- 1. Registro
// Cada registro usa su propia sesión (si no, el segundo choca con la sesión iniciada)
$jarA = tempnam(sys_get_temp_dir(), 'ja');
$jarB = tempnam(sys_get_temp_dir(), 'jb');
foreach ([[$A, 'Ana Tester', $jarA], [$B, 'Beto Tester', $jarB]] as [$email, $name, $jar]) {
    $r = req("$BASE/modules/auth/register.php", null, $jar);
    $t = csrfFrom($r['body']);
    $r = req("$BASE/modules/auth/register.php", [
        'name' => $name, 'email' => $email, 'password' => $passA,
        'confirm_password' => $passA, 'career' => 'Ingeniería', 'semester' => '3',
        'consent' => '1', 'csrf_token' => $t,
    ], $jar);
    ok("registro $email", $r['code'] === 302, "code={$r['code']} {$r['loc']}");
}
@unlink($jarA); @unlink($jarB);
$jarA = tempnam(sys_get_temp_dir(), 'ja');
$jarB = tempnam(sys_get_temp_dir(), 'jb');

// ---------------------------------------------------------------- 2. Login
foreach ([[$A, $jarA], [$B, $jarB]] as [$email, $jar]) {
    $r = req("$BASE/modules/auth/login.php", null, $jar);
    $t = csrfFrom($r['body']);
    $r = req("$BASE/modules/auth/login.php", ['email' => $email, 'password' => $passA, 'csrf_token' => $t], $jar);
    ok("login $email", $r['code'] === 302, "code={$r['code']} {$r['loc']}");
}

$ida = (int)$db->query("SELECT id FROM users WHERE email='$A'")->fetch_assoc()['id'];
$idb = (int)$db->query("SELECT id FROM users WHERE email='$B'")->fetch_assoc()['id'];
ok('usuarios creados', $ida > 0 && $idb > 0, "a=$ida b=$idb");

// Catálogo: id 1 = HTML y CSS, id 6 = Photoshop
$db->query("INSERT INTO user_skills_offered (user_id, skill_id, level, description) VALUES ($ida, 1, 'advanced', 'Experta en HTML y CSS'), ($ida, 2, 'advanced', 'JavaScript a tope'), ($idb, 6, 'intermediate', 'Diseño en Photoshop'), ($idb, 7, 'intermediate', 'Illustrator')");
ok('habilidades ofrecidas', $db->affected_rows >= 2, "filas={$db->affected_rows}");

// ---------------------------------------------------------------- 3. Propuesta virtual con enlace
$r = req("$BASE/modules/exchanges/propose.php?user_id=$idb", null, $jarA);
$t = csrfFrom($r['body']);
$r = req("$BASE/modules/exchanges/propose.php", [
    'user_id' => $idb, 'skill_offered_id' => 1, 'skill_requested_id' => 6,
    'proposer_hours' => '1', 'acceptor_hours' => '1',
    'meeting_type' => 'virtual', 'meeting_link' => 'https://meet.google.com/abc-defg-hij',
    'csrf_token' => $t,
], $jarA);
ok('propuesta creada (virtual + enlace)', $r['code'] === 302, "code={$r['code']}");

$ex = $db->query("SELECT * FROM exchanges WHERE proposer_id=$ida AND acceptor_id=$idb ORDER BY id DESC LIMIT 1")->fetch_assoc();
$exId = (int)($ex['id'] ?? 0);
ok('modalidad guardada', ($ex['meeting_type'] ?? '') === 'virtual', "meeting_type=" . ($ex['meeting_type'] ?? 'NULL'));
ok('enlace guardado', ($ex['meeting_link'] ?? '') === 'https://meet.google.com/abc-defg-hij');
ok('modalidad presencial no guarda enlace', ($ex['meeting_location'] === null || $ex['meeting_location'] === ''));

// ---------------------------------------------------------------- 4. Notificación de propuesta
$n = (int)$db->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$idb AND type='exchange_proposed'")->fetch_assoc()['c'];
ok('notificación de propuesta creada', $n >= 1, "count=$n");

// ---------------------------------------------------------------- 5. Solo el aceptador responde
$r = req("$BASE/modules/exchanges/index.php", null, $jarA);
$t = csrfFrom($r['body']);
$r = req("$BASE/modules/exchanges/accept.php", ['exchange_id' => $exId, 'action' => 'accept', 'csrf_token' => $t], $jarA);
$status = $db->query("SELECT status FROM exchanges WHERE id=$exId")->fetch_assoc()['status'];
ok('proponente NO puede aceptar', $status === 'pending', "status=$status");

$rB = req("$BASE/modules/exchanges/index.php", null, $jarB);
$tB = csrfFrom($rB['body']);
$r = req("$BASE/modules/exchanges/accept.php", ['exchange_id' => $exId, 'action' => 'accept', 'csrf_token' => $tB], $jarB);
$status = $db->query("SELECT status FROM exchanges WHERE id=$exId")->fetch_assoc()['status'];
ok('aceptante acepta', $status === 'accepted', "status=$status");

$n = (int)$db->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$ida AND type='exchange_accepted'")->fetch_assoc()['c'];
ok('notificación de aceptación', $n >= 1, "count=$n");

// ---------------------------------------------------------------- 6. API notificaciones
$j = apiGet("$BASE/api/notifications.php", $jarA);
ok('API notificaciones responde', ($j['success'] ?? false) === true && isset($j['unread_count']), json_encode($j));
ok('API refleja no leídas', ($j['unread_count'] ?? 0) >= 1, "unread=" . ($j['unread_count'] ?? '?'));

$r = req("$BASE/modules/exchanges/index.php", null, $jarA);
$t = csrfFrom($r['body']);
$j = apiPost("$BASE/api/notifications.php", ['action' => 'mark_all_read', 'csrf_token' => $t], $jarA);
ok('marcar leídas (con CSRF)', ($j['success'] ?? false) === true, json_encode($j));
$j = apiGet("$BASE/api/notifications.php", $jarA);
ok('tras marcar leídas quedan 0', ($j['unread_count'] ?? -1) === 0, "unread=" . ($j['unread_count'] ?? '?'));

$j = apiPost("$BASE/api/notifications.php", ['action' => 'mark_all_read', 'csrf_token' => 'invalido'], $jarA);
ok('CSRF inválido es rechazado', ($j['success'] ?? true) === false, json_encode($j));

// ---------------------------------------------------------------- 7. Mensajes con API
$r = req("$BASE/modules/messages/index.php?exchange_id=$exId", null, $jarA);
$t = csrfFrom($r['body']);
$j = apiPost("$BASE/api/messages.php", ['exchange_id' => $exId, 'content' => 'Hola, ¿te sirve a las 4pm?', 'csrf_token' => $t], $jarA);
ok('mensaje enviado por API', ($j['success'] ?? false) === true, json_encode($j));
$msgId = (int)($j['message']['id'] ?? 0);

$j = apiGet("$BASE/api/messages.php?exchange_id=$exId&last_id=0", $jarB);
ok(' contraparte lee el mensaje', ($j['messages'][0]['content'] ?? '') === 'Hola, ¿te sirve a las 4pm?', json_encode($j));

$j = apiGet("$BASE/api/messages.php?exchange_id=$exId&last_id=0", $jarA);
ok('no devuelve mensajes nuevos si last_id alto', count($j['messages'] ?? []) >= 1);

$j = apiPost("$BASE/api/messages.php", ['exchange_id' => $exId, 'content' => 'hack', 'csrf_token' => 'malo'], $jarA);
ok('mensaje con CSRF inválido rechazado', ($j['success'] ?? true) === false);

// Usuario ajeno no puede leer la conversación
$db->query("INSERT INTO users (name, email, password, career, semester, hours_balance) VALUES ('Intruso', 'com.x.$suffix@test.local', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ing', 1, 10)");
$idx = (int)$db->query("SELECT id FROM users WHERE email='com.x.$suffix@test.local'")->fetch_assoc()['id'];
$jarX = tempnam(sys_get_temp_dir(), 'jx');
$r = req("$BASE/modules/auth/login.php", null, $jarX);
$t = csrfFrom($r['body']);
req("$BASE/modules/auth/login.php", ['email' => "com.x.$suffix@test.local", 'password' => 'password', 'csrf_token' => $t], $jarX);
$j = apiGet("$BASE/api/messages.php?exchange_id=$exId&last_id=0", $jarX);
ok('ajeno no puede leer mensajes', ($j['success'] ?? true) === false, json_encode($j));

$n = (int)$db->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$idb AND type='message'")->fetch_assoc()['c'];
ok('notificación de mensaje nuevo', $n >= 1, "count=$n");

// ---------------------------------------------------------------- 8. Doble confirmación + horas
$rA = req("$BASE/modules/exchanges/index.php", null, $jarA);
$tA = csrfFrom($rA['body']);
$r = req("$BASE/modules/exchanges/complete.php", ['exchange_id' => $exId, 'csrf_token' => $tA], $jarA);
$st = $db->query("SELECT status FROM exchanges WHERE id=$exId")->fetch_assoc()['status'];
ok('1ra confirmación -> in_progress', $st === 'in_progress', "status=$st");

$rB = req("$BASE/modules/exchanges/index.php", null, $jarB);
$tB = csrfFrom($rB['body']);
$r = req("$BASE/modules/exchanges/complete.php", ['exchange_id' => $exId, 'csrf_token' => $tB], $jarB);
$row = $db->query("SELECT status, completion_confirmed_by FROM exchanges WHERE id=$exId")->fetch_assoc();
$ba = (float)$db->query("SELECT hours_balance FROM users WHERE id=$ida")->fetch_assoc()['hours_balance'];
$bb = (float)$db->query("SELECT hours_balance FROM users WHERE id=$idb")->fetch_assoc()['hours_balance'];
ok('2da confirmación -> completed', $row['status'] === 'completed', "status={$row['status']}");
ok('confirmador se limpia', $row['completion_confirmed_by'] === null);
// 1h por enseñar y 1h por aprender = intercambio neutro (10 -> 11 -> 10)
ok('saldo A vuelve a 10.0 (neutro)', abs($ba - 10.0) < 0.001, "saldo=$ba");
ok('saldo B vuelve a 10.0 (neutro)', abs($bb - 10.0) < 0.001, "saldo=$bb");
$logsRows = $db->query("SELECT user_id, amount, balance_after FROM hours_log WHERE exchange_id=$exId ORDER BY id");
$seen = [];
while ($row = $logsRows->fetch_assoc()) { $seen[] = "{$row['user_id']}:{$row['amount']}->{$row['balance_after']}"; }
ok('historial con 4 movimientos', count($seen) === 4, implode(' | ', $seen));
$sumA = 0.0; $sumB = 0.0;
$logs2 = $db->query("SELECT user_id, amount FROM hours_log WHERE exchange_id=$exId");
while ($row = $logs2->fetch_assoc()) {
    if ((int)$row['user_id'] === $ida) $sumA += (float)$row['amount'];
    if ((int)$row['user_id'] === $idb) $sumB += (float)$row['amount'];
}
ok('neto de horas 0 para ambos', abs($sumA) < 0.001 && abs($sumB) < 0.001, "A=$sumA B=$sumB");

$n = (int)$db->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$idb AND type='exchange_confirmed'")->fetch_assoc()['c'];
ok('notificación de confirmación', $n >= 1, "count=$n");

// ---------------------------------------------------------------- 9. Valoración
$rA = req("$BASE/modules/ratings/submit.php?exchange_id=$exId", null, $jarA);
$tA = csrfFrom($rA['body']);
$r = req("$BASE/modules/ratings/submit.php", ['exchange_id' => $exId, 'score' => '5', 'review' => 'Muy buena sesión', 'csrf_token' => $tA], $jarA);
$n = (int)$db->query("SELECT COUNT(*) c FROM ratings WHERE exchange_id=$exId")->fetch_assoc()['c'];
ok('valoración registrada', $n === 1, "count=$n");
$n = (int)$db->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$idb AND type='rating'")->fetch_assoc()['c'];
ok('notificación de valoración', $n >= 1, "count=$n");

// ---------------------------------------------------------------- 10. Constancia de horas
$r = req("$BASE/modules/profile/certificate.php", null, $jarA);
ok('constancia de horas (200)', $r['code'] === 200, "code={$r['code']}");
ok('constancia muestra código', strpos($r['body'], 'CERT-SWAP-') !== false);
ok('constancia muestra horas impartidas', strpos($r['body'], 'Horas impartidas') !== false);

// ---------------------------------------------------------------- 11. Perfil: bio, enlaces, avatar
$r = req("$BASE/modules/profile/edit.php", null, $jarA);
$t = csrfFrom($r['body']);

// 11a. Avatar legítimo (PNG 1x1) + bio + links
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$tmpPng = tempnam(sys_get_temp_dir(), 'av') . '.png';
file_put_contents($tmpPng, $png);

$ch = curl_init("$BASE/modules/profile/edit.php");
$fields = [
    'action' => 'update_profile',
    'name' => 'Ana Tester',
    'career' => 'Ingeniería en Sistemas',
    'semester' => '3',
    'bio' => 'Me gusta el diseño y la programación.',
    'github_url' => 'https://github.com/anatest',
    'linkedin_url' => 'https://linkedin.com/in/anatest',
    'csrf_token' => $t,
];
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_COOKIEJAR => $jarA, CURLOPT_COOKIEFILE => $jarA,
    CURLOPT_POSTFIELDS => $fields,
    CURLOPT_TIMEOUT => 30,
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, (function () use ($fields, $tmpPng) {
    $out = $fields;
    return [
        'action' => $out['action'], 'name' => $out['name'], 'career' => $out['career'],
        'semester' => $out['semester'], 'bio' => $out['bio'],
        'github_url' => $out['github_url'], 'linkedin_url' => $out['linkedin_url'],
        'csrf_token' => $out['csrf_token'],
        'avatar' => new CURLFile($tmpPng, 'image/png', 'foto.png'),
    ];
})());
curl_exec($ch);
curl_close($ch);
$prof = $db->query("SELECT bio, github_url, linkedin_url, profile_image FROM users WHERE id=$ida")->fetch_assoc();
ok('bio guardada', ($prof['bio'] ?? '') !== '');
ok('github guardado', ($prof['github_url'] ?? '') === 'https://github.com/anatest');
ok('linkedin guardado', ($prof['linkedin_url'] ?? '') === 'https://linkedin.com/in/anatest');
ok('avatar guardado con extensión derivada del contenido', ($prof['profile_image'] ?? '') !== null && preg_match('/^[a-f0-9]{24}\.png$/', $prof['profile_image']), 'valor=' . ($prof['profile_image'] ?? 'NULL'));

// 11b. Avatar malicioso: archivo PHP con contenido PNG (cabecera válida)
$tmpEvil = tempnam(sys_get_temp_dir(), 'ev') . '.php';
file_put_contents($tmpEvil, $png);
$r = req("$BASE/modules/profile/edit.php", null, $jarA);
$t = csrfFrom($r['body']);
$ch = curl_init("$BASE/modules/profile/edit.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_COOKIEJAR => $jarA, CURLOPT_COOKIEFILE => $jarA, CURLOPT_TIMEOUT => 30,
    CURLOPT_POSTFIELDS => [
        'action' => 'update_profile', 'name' => 'Ana Tester', 'career' => 'Ingeniería en Sistemas',
        'semester' => '3', 'csrf_token' => $t,
        'avatar' => new CURLFile($tmpEvil, 'image/png', 'evil.php'),
    ],
]);
curl_exec($ch);
curl_close($ch);
$prof2 = $db->query("SELECT profile_image FROM users WHERE id=$ida")->fetch_assoc();
$stored2 = $prof2['profile_image'] ?? '';
ok('nombre .php del cliente no produce archivo .php', $stored2 !== '' && substr($stored2, -4) === '.png', 'guardado=' . $stored2);

// 11c. Payload PHP real (contenido no imagen) debe ser RECHAZADO
$tmpReal = tempnam(sys_get_temp_dir(), 'php') . '.php';
file_put_contents($tmpReal, "<?php echo 'PWNED'; ?>");
$rA = req("$BASE/modules/profile/edit.php", null, $jarA);
$tA = csrfFrom($rA['body']);
$ch = curl_init("$BASE/modules/profile/edit.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_COOKIEJAR => $jarA, CURLOPT_COOKIEFILE => $jarA, CURLOPT_TIMEOUT => 30,
    CURLOPT_POSTFIELDS => [
        'action' => 'update_profile', 'name' => 'Ana Tester', 'career' => 'Ingeniería en Sistemas',
        'semester' => '3', 'csrf_token' => $tA,
        'avatar' => new CURLFile($tmpReal, 'image/png', 'shell.png'),
    ],
]);
curl_exec($ch);
curl_close($ch);
$prof3 = $db->query("SELECT profile_image FROM users WHERE id=$ida")->fetch_assoc();
ok('payload PHP real rechazado', ($prof3['profile_image'] ?? '') === $stored2, 'antes=' . $stored2 . ' ahora=' . ($prof3['profile_image'] ?? 'NULL'));
$phpFiles = glob('/var/www/html/skillswap/assets/img/avatars/*.php') ?: [];
ok('no hay .php en la carpeta de avatares', count($phpFiles) === 0);
@unlink($tmpReal);

// ---------------------------------------------------------------- 12. Insignias
$r = req("$BASE/modules/profile/index.php", null, $jarA);
ok('perfil propio 200', $r['code'] === 200, "code={$r['code']}");
ok('insignia Pionero visible', strpos($r['body'], 'Pionero') !== false);
$r = req("$BASE/modules/profile/view.php?id=$idb", null, $jarA);
ok('perfil público 200', $r['code'] === 200, "code={$r['code']}");
ok('perfil público muestra insignia', strpos($r['body'], 'Pionero') !== false);

// ---------------------------------------------------------------- 13. Admin: reportes y CSV
$db->query("UPDATE users SET role='admin' WHERE id=$idb");
$r = req("$BASE/admin/reports.php", null, $jarB);
ok('reportes 200', $r['code'] === 200, "code={$r['code']}");
ok('reportes incluye gráficas', strpos($r['body'], 'skillsChart') !== false && strpos($r['body'], 'monthsChart') !== false);
ok('reportes incluye botones de exportación', strpos($r['body'], 'export.php?type=users') !== false && strpos($r['body'], 'export.php?type=exchanges') !== false);

$ch = curl_init("$BASE/admin/export.php?type=users");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jarB, CURLOPT_COOKIEFILE => $jarB, CURLOPT_TIMEOUT => 30]);
$csvUsers = curl_exec($ch);
$hdrUsers = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
ok('CSV usuarios 200', $hdrUsers === 200, "code=$hdrUsers");
ok('CSV usuarios con BOM y cabeceras', substr($csvUsers, 0, 3) === "\xEF\xBB\xBF" && strpos($csvUsers, 'Saldo de horas') !== false);
ok('CSV usuarios incluye al usuario de prueba', strpos($csvUsers, $A) !== false);

$ch = curl_init("$BASE/admin/export.php?type=exchanges");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jarB, CURLOPT_COOKIEFILE => $jarB, CURLOPT_TIMEOUT => 30]);
$csvEx = curl_exec($ch);
curl_close($ch);
ok('CSV intercambios con modalidad', strpos($csvEx, 'Virtual') !== false && strpos($csvEx, 'Completado') !== false, substr($csvEx, 0, 200));

// Sin sesión el CSV no se entrega
$jarY = tempnam(sys_get_temp_dir(), 'jy');
$r = req("$BASE/admin/export.php?type=users", null, $jarY);
ok('CSV protegido sin sesión', $r['code'] === 302 && strpos($r['loc'], 'login.php') !== false, "code={$r['code']} loc={$r['loc']}");

// ---------------------------------------------------------------- 14. Modalidad presencial
$r = req("$BASE/modules/exchanges/propose.php?user_id=$idb", null, $jarA);
$t = csrfFrom($r['body']);
$r = req("$BASE/modules/exchanges/propose.php", [
    'user_id' => $idb, 'skill_offered_id' => 2, 'skill_requested_id' => 7,
    'proposer_hours' => '0.5', 'acceptor_hours' => '0.5',
    'meeting_type' => 'presencial', 'meeting_location' => 'Biblioteca, aula 204',
    'csrf_token' => $t,
], $jarA);
$ex2 = $db->query("SELECT * FROM exchanges WHERE proposer_id=$ida AND skill_offered_id=2 ORDER BY id DESC LIMIT 1")->fetch_assoc();
ok('propuesta presencial guardada', ($ex2['meeting_type'] ?? '') === 'presencial', "tipo=" . ($ex2['meeting_type'] ?? 'NULL'));
ok('lugar guardado', ($ex2['meeting_location'] ?? '') === 'Biblioteca, aula 204');
ok('presencial sin enlace', $ex2['meeting_link'] === null || $ex2['meeting_link'] === '');

// ---------------------------------------------------------------- 15. Otras páginas sin errores fatales
foreach ([
    '/modules/dashboard/index.php', '/modules/skills/index.php',
    '/modules/matches/index.php', '/modules/exchanges/index.php', '/modules/messages/index.php',
    '/modules/profile/index.php', '/modules/profile/edit.php',
    '/modules/auth/politica.php',
] as $page) {
    $r = req($BASE . $page, null, $jarA);
    $fatal = (stripos($r['body'], 'Fatal error') !== false) || (stripos($r['body'], 'Warning:') !== false) || (stripos($r['body'], 'Notice:') !== false) || (stripos($r['body'], 'Deprecated:') !== false);
    ok("página $page sin errores", $r['code'] === 200 && !$fatal, "code={$r['code']}");
}

// admin con la sesion de B (ya promovida)
foreach (['/index.php', '/admin/index.php', '/admin/users.php', '/admin/categories.php'] as $page) {
    $r = req($BASE . $page, null, $jarB);
    $accepted = in_array($r['code'], [200, 302], true);
    $fatal = (stripos($r['body'], 'Fatal error') !== false) || (stripos($r['body'], 'Warning:') !== false);
    ok("admin $page responde sin errores", $accepted && !$fatal, "code={$r['code']}");
}

// ---------------------------------------------------------------- Limpieza
// Limpieza: borra cualquier usuario de prueba creado (A, B, intruso y los de
// escenarios re-registrados), por patrón para no depender de variables.
$db->query("DELETE FROM users WHERE id IN ($ida, $idb, $idx)");
$db->query("DELETE FROM users WHERE email LIKE 'com.%@test.local'");
$db->query("DELETE FROM notifications WHERE user_id IN ($ida, $idb, $idx)");
foreach (glob('/var/www/html/skillswap/assets/img/avatars/*') as $f) {
    if (is_file($f) && basename($f) !== '.htaccess') unlink($f);
}
$left = [];
$res = $db->query("SELECT email FROM users WHERE email LIKE 'com.%@test.local'");
while ($row = $res->fetch_assoc()) { $left[] = $row['email']; }
ok('limpieza de usuarios de prueba', count($left) === 0, 'restantes=' . implode(',', $left));
$notifsLeft = (int)$db->query("SELECT COUNT(*) c FROM notifications")->fetch_assoc()['c'];
ok('sin notificaciones huerfanas', $notifsLeft === 0, "restantes=$notifsLeft");

echo "\n================ $pass correctas / $fail fallidas ================\n";
@unlink($jarA); @unlink($jarB); @unlink($jarX); @unlink($jarY);
@unlink($tmpPng); @unlink($tmpEvil);
exit($fail > 0 ? 1 : 0);