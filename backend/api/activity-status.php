<?php
require __DIR__ . '/cors.php';
// POST ?id=  {action: submit | approve | return | reopen, note}
// submit: the office (while it can still edit); approve/return/reopen: Planning Office and admin.
$u = guard(['POST' => ['admin', 'planning', 'encoder']]);
$id = id_param();
$a = get_activity($u, $id);
$flow = [
    'submit'  => [['draft', 'returned'], 'submitted'],
    'approve' => [['submitted'], 'approved'],
    'return'  => [['submitted', 'approved'], 'returned'],
    'reopen'  => [['submitted', 'approved', 'returned'], 'draft'],
];
$action = (string)input('action', '');
if (!isset($flow[$action])) throw new HttpError(422, 'Unknown action.');
[$from, $to] = $flow[$action];
if ($action !== 'submit' && !is_reviewer($u)) throw new HttpError(403, 'Only the Planning Office can do this.');
if (!in_array($a['workflow'], $from, true)) throw new HttpError(409, "This activity is already {$a['workflow']}.");

if ($action === 'submit') {
    if (!$a['can_edit']) throw new HttpError(403, 'You cannot submit this activity (another office, or encoding is closed).');
    q("UPDATE activities SET workflow = 'submitted', submitted_at = NOW(), submitted_by = ?, updated_by = ? WHERE id = ?", [$u['id'], $u['id'], $id]);
} else {
    $note = trim((string)input('note', ''));
    if ($action === 'return' && $note === '') throw new HttpError(422, 'Write what the office needs to fix.');
    q('UPDATE activities SET workflow = ?, review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
        [$to, $action === 'return' ? $note : null, $u['id'], $id]);
}
json_out(['data' => get_activity($u, $id)]);
