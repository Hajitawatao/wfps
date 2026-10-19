<?php
require __DIR__ . '/cors.php';
// One row of the Encoding Sheet: GET / PUT / DELETE ?id=
$u = guard(['GET' => 'user', 'PUT' => ['admin', 'planning', 'encoder'], 'DELETE' => ['admin', 'planning', 'encoder']]);
$id = id_param();
$a = get_activity($u, $id);

if ($_SERVER['REQUEST_METHOD'] === 'GET') json_out(['data' => $a]);

if (!$a['can_edit']) throw new HttpError(403, 'This activity can no longer be changed. It is already submitted or approved, or encoding is closed.');
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    q('DELETE FROM activities WHERE id = ?', [$id]);
    json_out(['ok' => true]);
}
update_row('activities', 'id', $id, validate_activity($u, body(), $a) + ['updated_by' => $u['id']]);
json_out(['data' => get_activity($u, $id)]);
