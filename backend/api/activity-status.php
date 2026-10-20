<?php
require __DIR__ . '/cors.php';
// POST ?id=  {action: submit | approve | return | reopen, note, add_to_list}
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
    db()->beginTransaction();
    q('UPDATE activities SET workflow = ?, review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
        [$to, $action === 'return' ? $note : null, $u['id'], $id]);
    // Approve with "Add to Dropdown lists": the objective / success indicator typed under "Others"
    // becomes a choice in the Dropdown lists, and this activity is moved onto it.
    if ($action === 'approve' && !empty(input('add_to_list', false)) && ($a['objective_other'] || $a['indicator_other'])) {
        $objId = (int)$a['objective_id'];
        if ($a['objective_other']) {
            $objId = (int)scalar('SELECT id FROM objectives WHERE function_type = ? AND LOWER(title) = LOWER(?) LIMIT 1',
                [$a['function_type'], $a['objective_other']]);
            if (!$objId) {
                // just above "Others" of the same type of function, so "Others" stays last
                $at = (int)scalar('SELECT sort_order FROM objectives WHERE id = ?', [$a['objective_id']]);
                q('UPDATE objectives SET sort_order = sort_order + 1 WHERE sort_order >= ?', [$at]);
                $objId = insert_row('objectives', ['function_type' => $a['function_type'], 'title' => $a['objective_other'], 'sort_order' => $at, 'active' => 1]);
            }
        }
        $indTitle = $a['indicator_other'] ?: (string)scalar('SELECT title FROM indicators WHERE id = ?', [$a['indicator_id']]);
        $indId = (int)scalar('SELECT id FROM indicators WHERE objective_id = ? AND LOWER(title) = LOWER(?) LIMIT 1', [$objId, $indTitle]);
        if (!$indId) {
            $sort = (int)scalar('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM indicators WHERE objective_id = ?', [$objId]);
            $indId = insert_row('indicators', ['objective_id' => $objId, 'title' => $indTitle, 'sort_order' => $sort, 'active' => 1]);
        }
        q('UPDATE activities SET objective_id = ?, indicator_id = ?, objective_other = NULL, indicator_other = NULL WHERE id = ?', [$objId, $indId, $id]);
    }
    db()->commit();
}
json_out(['data' => get_activity($u, $id)]);
