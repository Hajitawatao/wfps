<?php
require __DIR__ . '/cors.php';
// Offices / areas. GET: everyone signed in. PUT ?id=, DELETE ?id=: admin.
guard(['GET' => 'user', 'PUT' => ['admin'], 'DELETE' => ['admin']]);
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    json_out(['data' => all("SELECT o.id, o.code, o.name, o.active,
                                    (SELECT COUNT(*) FROM users u WHERE u.office_id = o.id AND u.role = 'encoder') encoders
                             FROM offices o ORDER BY o.active DESC, o.name")]);
}
$id = id_param();
if ($method === 'DELETE') {
    if (scalar('SELECT 1 FROM activities WHERE office_id = ?', [$id])) throw new HttpError(422, 'This office already has activities. Set it inactive instead.');
    q("DELETE FROM users WHERE office_id = ? AND role = 'encoder'", [$id]);
    q('DELETE FROM offices WHERE id = ?', [$id]);
    json_out(['ok' => true]);
}
$b = body();
$data = [];
foreach (['code', 'name'] as $f) if (array_key_exists($f, $b)) $data[$f] = trim((string)$b[$f]) ?: null;
if (array_key_exists('active', $b)) $data['active'] = $b['active'] ? 1 : 0;
if (isset($data['code'])) $data['code'] = strtoupper($data['code']);
if (array_key_exists('code', $data) && !$data['code'] || array_key_exists('name', $data) && !$data['name']) throw new HttpError(422, 'Code and name are required.');
update_row('offices', 'id', $id, $data);
json_out(['ok' => true]);
