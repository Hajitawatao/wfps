<?php
require __DIR__ . '/cors.php';
// POST {common: {...columns shared by every row}, rows: [{title, start_date, end_date, target_q1..q4, target_unit, cost}], submit}
// Encodes several rows of the Encoding Sheet at once (for example the items of a PPMP).
// Every row is checked with the same rules as one activity. If any row is wrong nothing is saved and the
// errors come back per row: {rows: {"0": {field: message}}}.
$u = guard(['POST' => ['admin', 'planning', 'encoder']]);
$common = (array)input('common', []);
$rows = array_values(array_filter((array)input('rows', []), fn($r) => is_array($r) && trim(implode('', array_map('strval', $r))) !== ''));
if (!$rows) throw new HttpError(422, 'Add at least one row.');
if (count($rows) > 200) throw new HttpError(422, 'Save at most 200 rows at a time.');
$fy = (int)($common['fiscal_year'] ?? 0) ?: fy();
if ($u['role'] === 'encoder' && !encoding_open($fy)) throw new HttpError(403, "Encoding for FY $fy is closed.");

$ROW = ['title', 'start_date', 'end_date', 'target_q1', 'target_q2', 'target_q3', 'target_q4', 'target_unit', 'cost'];
$valid = [];
$errors = [];
foreach ($rows as $i => $r) {
    $in = $common;
    foreach ($ROW as $k) $in[$k] = $r[$k] ?? '';
    $in['fiscal_year'] = $fy;
    try { $valid[$i] = validate_activity($u, $in, null); }
    catch (HttpError $e) { $errors[(string)$i] = $e->extra['fields'] ?? ['title' => $e->getMessage()]; }
}
if ($errors) throw new HttpError(422, count($errors) . ' of ' . count($rows) . ' rows have empty or wrong columns. Nothing was saved.', ['rows' => $errors]);

$submit = !empty(input('submit', false));
$ids = [];
db()->beginTransaction();
foreach ($valid as $d) {
    $extra = ['workflow' => 'draft', 'created_by' => $u['id'], 'updated_by' => $u['id']];
    if ($submit) $extra = ['workflow' => 'submitted', 'submitted_by' => $u['id']] + $extra;
    $ids[] = insert_row('activities', $d + $extra);
}
// the database clock, the same one the notifications compare with
if ($submit) q('UPDATE activities SET submitted_at = NOW() WHERE id IN (' . implode(',', $ids) . ')');
db()->commit();
json_out(['saved' => count($ids), 'submitted' => $submit, 'ids' => $ids], 201);
