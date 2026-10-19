<?php
require __DIR__ . '/cors.php';
// "Request access" from the sign-in page, and the administrator's decision.
//   POST            anyone: ask for an account (name, username, password, area, level)
//   GET             admin: waiting requests
//   PUT ?id=        admin: grant access {role, office_id | new_office} -> the account is created with the requester's password
//   DELETE ?id=     admin: decline
$me = guard(['POST' => 'public', 'GET' => ['admin'], 'PUT' => ['admin'], 'DELETE' => ['admin']]);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    json_out(['data' => all('SELECT r.id, r.full_name, r.position, r.username, r.office_id, r.new_office, r.role, r.note, r.created_at,
                                    o.name office_name
                             FROM access_requests r LEFT JOIN offices o ON o.id = r.office_id ORDER BY r.created_at')]);
}

if ($method === 'POST') {
    $s = fn($k) => trim((string)input($k, ''));
    $e = [];
    $username = strtolower($s('username'));
    if ($s('full_name') === '') $e['full_name'] = 'Enter your full name.';
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) $e['username'] = '3 to 30 small letters, numbers, dot, dash or underscore.';
    elseif (scalar('SELECT 1 FROM users WHERE username = ?', [$username]) || scalar('SELECT 1 FROM access_requests WHERE username = ?', [$username]))
        $e['username'] = 'That username is already taken.';
    if (strlen((string)input('password', '')) < 8) $e['password'] = 'At least 8 characters.';
    $officeId = (int)input('office_id', 0) ?: null;
    $newOffice = $s('new_office');
    if (!$officeId && $newOffice === '') $e['office_id'] = 'Choose your area, or type it if it is not in the list.';
    if ($officeId && !scalar('SELECT 1 FROM offices WHERE id = ? AND active = 1', [$officeId])) $e['office_id'] = 'Choose your area.';
    $role = $s('role') === 'viewer' ? 'viewer' : 'encoder';
    if ($e) throw new HttpError(422, 'Please check the highlighted fields.', ['fields' => $e]);
    if ((int)scalar('SELECT COUNT(*) FROM access_requests') >= 100) throw new HttpError(429, 'Too many requests are waiting. Contact MIS.');
    insert_row('access_requests', [
        'full_name' => $s('full_name'), 'position' => $s('position') ?: null, 'username' => $username,
        'password_hash' => password_hash((string)input('password'), PASSWORD_BCRYPT),
        'office_id' => $officeId, 'new_office' => $officeId ? null : $newOffice, 'role' => $role,
        'note' => mb_substr($s('note'), 0, 300) ?: null,
    ]);
    json_out(['ok' => true], 201);
}

$id = id_param();
$r = one('SELECT * FROM access_requests WHERE id = ?', [$id]);
if (!$r) throw new HttpError(404, 'This request was already handled.');

if ($method === 'DELETE') {
    q('DELETE FROM access_requests WHERE id = ?', [$id]);
    json_out(['ok' => true]);
}

// grant
$role = (string)input('role', $r['role']);
if (!in_array($role, ROLES, true)) throw new HttpError(422, 'Unknown access level.');
if (scalar('SELECT 1 FROM users WHERE username = ?', [$r['username']])) throw new HttpError(422, "The username {$r['username']} was taken in the meantime. Decline this request and ask them to send a new one.");
db()->beginTransaction();
$officeId = (int)input('office_id', 0) ?: null;
$newOffice = trim((string)input('new_office', ''));
if (!$officeId && $newOffice !== '') [$officeId] = ensure_office($newOffice);
if ($role === 'encoder' && !$officeId) throw new HttpError(422, 'An encoder must belong to an area.');
insert_row('users', ['username' => $r['username'], 'password_hash' => $r['password_hash'], 'full_name' => $r['full_name'],
    'position' => $r['position'], 'role' => $role, 'office_id' => $officeId, 'must_change' => 0]);
q('DELETE FROM access_requests WHERE id = ?', [$id]);
db()->commit();
json_out(['ok' => true]);
