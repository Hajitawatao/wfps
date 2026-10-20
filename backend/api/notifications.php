<?php
require __DIR__ . '/cors.php';
// Notification bell and the counts beside the menu items.
//   Planning Office / admin: activities that areas submitted and that still wait for review,
//   marked when the area typed its own objective / success indicator under "Others" (to be verified).
//   Encoders: their area's activities that the Planning Office approved or returned.
//   GET ?fy=   newest first; "unread" = since the bell was last opened
//   PUT        the bell was opened: everything up to now counts as seen
// Viewers have nothing to act on, so they get no notifications.
$u = guard(['GET' => ['admin', 'planning', 'encoder'], 'PUT' => ['admin', 'planning', 'encoder']]);

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    q('UPDATE users SET notes_seen_at = NOW() WHERE id = ?', [$u['id']]);
    json_out(['ok' => true]);
}

$fy = fy();
$seen = scalar('SELECT notes_seen_at FROM users WHERE id = ?', [$u['id']]);
$since = fn(string $col) => $seen ? "$col > " . db()->quote($seen) : '1 = 1';

if (is_reviewer($u)) {
    $isNew = $since('a.submitted_at');
    $where = "a.fiscal_year = ? AND a.workflow = 'submitted'";
    json_out([
        'kind' => 'review',
        'unread' => (int)scalar("SELECT COUNT(*) FROM activities a WHERE $where AND $isNew", [$fy]),
        'counts' => ['review' => (int)scalar("SELECT COUNT(*) FROM activities a WHERE $where", [$fy]),
                     'others' => (int)scalar("SELECT COUNT(*) FROM activities a WHERE $where AND (a.objective_other IS NOT NULL OR a.indicator_other IS NOT NULL)", [$fy])],
        'items' => all("SELECT a.id, a.title, a.workflow, a.submitted_at at, TIMESTAMPDIFF(MINUTE, a.submitted_at, NOW()) mins,
                               o.name office, s.full_name who, NULL note, ($isNew) is_new, a.objective_other, a.indicator_other
                        FROM activities a JOIN offices o ON o.id = a.office_id LEFT JOIN users s ON s.id = a.submitted_by
                        WHERE $where ORDER BY a.submitted_at DESC, a.id DESC LIMIT 15", [$fy]),
    ]);
}

// encoder: what happened to the area's submitted activities
$oid = (int)$u['office_id'];
$isNew = $since('a.reviewed_at');
$where = "a.fiscal_year = ? AND a.office_id = ? AND a.workflow IN ('approved','returned') AND a.reviewed_at IS NOT NULL";
json_out([
    'kind' => 'area',
    'unread' => (int)scalar("SELECT COUNT(*) FROM activities a WHERE $where AND $isNew", [$fy, $oid]),
    'counts' => ['returned' => (int)scalar("SELECT COUNT(*) FROM activities a WHERE a.fiscal_year = ? AND a.office_id = ? AND a.workflow = 'returned'", [$fy, $oid])],
    'items' => all("SELECT a.id, a.title, a.workflow, a.reviewed_at at, TIMESTAMPDIFF(MINUTE, a.reviewed_at, NOW()) mins,
                           o.name office, r.full_name who, a.review_note note, ($isNew) is_new
                    FROM activities a JOIN offices o ON o.id = a.office_id LEFT JOIN users r ON r.id = a.reviewed_by
                    WHERE $where ORDER BY a.reviewed_at DESC, a.id DESC LIMIT 15", [$fy, $oid]),
]);
