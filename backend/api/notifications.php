<?php
require __DIR__ . '/cors.php';
// Planning Office / admin: activities that offices have submitted and that still wait for review.
//   GET ?fy=   the waiting ones of the year, newest first; "unread" = submitted since the bell was last opened
//   PUT        the bell was opened: everything up to now counts as seen
$u = guard(['GET' => ['admin', 'planning'], 'PUT' => ['admin', 'planning']]);

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    q('UPDATE users SET notes_seen_at = NOW() WHERE id = ?', [$u['id']]);
    json_out(['ok' => true]);
}

$fy = fy();
$seen = scalar('SELECT notes_seen_at FROM users WHERE id = ?', [$u['id']]);
$isNew = $seen ? 'a.submitted_at > ' . db()->quote($seen) : '1 = 1';
json_out([
    'unread' => (int)scalar("SELECT COUNT(*) FROM activities a WHERE a.fiscal_year = ? AND a.workflow = 'submitted' AND $isNew", [$fy]),
    'waiting' => (int)scalar("SELECT COUNT(*) FROM activities a WHERE a.fiscal_year = ? AND a.workflow = 'submitted'", [$fy]),
    'items' => all("SELECT a.id, a.title, a.submitted_at, TIMESTAMPDIFF(MINUTE, a.submitted_at, NOW()) mins, o.name office, s.full_name submitted_by, ($isNew) is_new
                    FROM activities a JOIN offices o ON o.id = a.office_id LEFT JOIN users s ON s.id = a.submitted_by
                    WHERE a.fiscal_year = ? AND a.workflow = 'submitted'
                    ORDER BY a.submitted_at DESC, a.id DESC LIMIT 15", [$fy]),
]);
