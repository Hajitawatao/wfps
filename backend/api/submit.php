<?php
require __DIR__ . '/cors.php';
// POST {ids: [...]}: submit several Draft/Returned activities at once.
$u = guard(['POST' => ['admin', 'planning', 'encoder']]);
$done = 0;
foreach ((array)input('ids', []) as $aid) {
    $a = one('SELECT * FROM activities WHERE id = ?', [(int)$aid]);
    if (!$a || !in_array($a['workflow'], ['draft', 'returned'], true) || !can_edit($u, $a)) continue;
    q("UPDATE activities SET workflow = 'submitted', submitted_at = NOW(), submitted_by = ?, updated_by = ? WHERE id = ?", [$u['id'], $u['id'], $a['id']]);
    $done++;
}
json_out(['submitted' => $done]);
