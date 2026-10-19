<?php
require __DIR__ . '/cors.php';
// GET: rows of the Encoding Sheet (filters: fy, office_id, function_type, workflow, q).  POST: encode a new row.
$u = guard(['GET' => 'user', 'POST' => ['admin', 'planning', 'encoder']]);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    [$where, $p] = activity_filters($u);
    $rows = all(ACT_SELECT . " WHERE $where ORDER BY o.name, FIELD(a.function_type,'Strategic','Core','Support'), a.id", $p);
    foreach ($rows as $k => $r) $rows[$k]['can_edit'] = can_edit($u, $r);
    json_out(['data' => $rows]);
}

$fyIn = (int)input('fiscal_year', 0) ?: fy();
if ($u['role'] === 'encoder' && !encoding_open($fyIn)) throw new HttpError(403, "Encoding for FY $fyIn is closed.");
$d = validate_activity($u, body(), null);
$id = insert_row('activities', $d + ['workflow' => 'draft', 'created_by' => $u['id'], 'updated_by' => $u['id']]);
json_out(['data' => get_activity($u, $id)], 201);
