<?php
require __DIR__ . '/cors.php';
// User accounts: admin only. GET list, POST add, PUT ?id= edit / reset password (reset_password: true).
// A new or reset password is temporary: the user must replace it at the next sign-in.
$me = guard(['GET' => ['admin'], 'POST' => ['admin'], 'PUT' => ['admin']]);
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    json_out(['data' => all('SELECT u.id, u.username, u.full_name, u.position, u.role, u.office_id, u.active, u.last_login,
                                    u.reset_requested_at, u.locked_until, o.name office_name
                             FROM users u LEFT JOIN offices o ON o.id = u.office_id ORDER BY o.name, u.username')]);
}
$b = body();
$data = [];
foreach (['username', 'full_name', 'position', 'role'] as $f) if (array_key_exists($f, $b)) $data[$f] = trim((string)$b[$f]);
if (array_key_exists('office_id', $b)) $data['office_id'] = (int)$b['office_id'] ?: null;
if (array_key_exists('active', $b)) $data['active'] = $b['active'] ? 1 : 0;
// a new area typed in the form instead of picked from the list: create it first
if (trim((string)($b['new_office'] ?? '')) !== '') {
    db()->beginTransaction();
    [$data['office_id']] = ensure_office((string)$b['new_office']);
}
if (isset($data['role']) && !in_array($data['role'], ROLES, true)) throw new HttpError(422, 'Unknown role.');
if (($data['role'] ?? null) === 'encoder' && empty($data['office_id'])) throw new HttpError(422, 'An encoder must belong to an office.');

$newPass = null;
if ($method === 'POST' || !empty($b['reset_password'])) {
    $newPass = make_password();
    $data += ['password_hash' => password_hash($newPass, PASSWORD_BCRYPT), 'must_change' => 1, 'reset_requested_at' => null,
              'failed_logins' => 0, 'locked_until' => null];
}

if ($method === 'POST') {
    if (empty($data['username']) || empty($data['full_name'])) throw new HttpError(422, 'Username and name are required.');
    if (scalar('SELECT 1 FROM users WHERE username = ?', [$data['username']])) throw new HttpError(422, 'That username is already taken.');
    insert_row('users', $data);
    if (db()->inTransaction()) db()->commit();
    json_out(['ok' => true, 'password' => $newPass], 201);
}
$id = id_param();
if ($id === (int)$me['id'] && array_key_exists('active', $data) && !$data['active']) throw new HttpError(422, 'You cannot turn off your own account.');
if ($id === (int)$me['id'] && isset($data['role']) && $data['role'] !== 'admin') throw new HttpError(422, 'You cannot remove your own administrator role.');
unset($data['username']);
update_row('users', 'id', $id, $data);
if (db()->inTransaction()) db()->commit();
json_out(['ok' => true, 'password' => $newPass]);
