<?php
require __DIR__ . '/cors.php';
// Budget per area: Procurement gives each area its MOOE and CO out of the hospital's Total (General Info).
//   GET ?fy=               every active area with its budget and what it encoded (admin, Planning, Procurement)
//   PUT ?fy=&office_id=    {mooe, co} in '000 PhP (admin, Procurement). The areas together may not exceed the hospital Total.
$u = guard(['GET' => ['admin', 'planning', 'procurement'], 'PUT' => ['admin', 'procurement']]);
$fy = fy();
if (!scalar('SELECT 1 FROM settings WHERE fiscal_year = ?', [$fy])) throw new HttpError(422, "FY $fy has not been opened yet.");
$total = hospital_budget($fy);

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $oid = (int)input('office_id', 0);
    if (!scalar('SELECT 1 FROM offices WHERE id = ?', [$oid])) throw new HttpError(404, 'Choose an area.');
    $new = [];
    foreach (['mooe' => 'MOOE', 'co' => 'Capital Outlay'] as $k => $label) {
        $v = body()[$k] ?? 0;
        if ($v === '' || $v === null) $v = 0;
        if (!is_numeric($v) || $v < 0) throw new HttpError(422, "Enter the $label budget in '000 PhP (0 if none).");
        $new[$k] = round((float)$v, 2);
        $others = (float)scalar("SELECT COALESCE(SUM($k), 0) FROM office_budgets WHERE fiscal_year = ? AND office_id <> ?", [$fy, $oid]);
        if ($others + $new[$k] > $total[$k] + 0.001) {
            $left = number_format(max(0, $total[$k] - $others), 2);
            throw new HttpError(422, "The areas' $label budgets would exceed the hospital's Total $label. At most $left can still be given.");
        }
    }
    q('INSERT INTO office_budgets (fiscal_year, office_id, mooe, co) VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE mooe = VALUES(mooe), co = VALUES(co)', [$fy, $oid, $new['mooe'], $new['co']]);
}

// what each area encoded (all entries, as the Encoding Sheet counts them)
$rows = all("SELECT o.id, o.name, COALESCE(b.mooe, 0) mooe, COALESCE(b.co, 0) co, b.office_id IS NOT NULL is_set,
                    COALESCE(SUM(CASE WHEN a.expense_class = 'MOOE' THEN a.cost END), 0) used_mooe,
                    COALESCE(SUM(CASE WHEN a.expense_class = 'CO' THEN a.cost END), 0) used_co
             FROM offices o
             LEFT JOIN office_budgets b ON b.office_id = o.id AND b.fiscal_year = ?
             LEFT JOIN activities a ON a.office_id = o.id AND a.fiscal_year = ?
             WHERE o.active = 1 GROUP BY o.id, o.name, b.mooe, b.co, b.office_id ORDER BY o.name", [$fy, $fy]);
$given = ['mooe' => array_sum(array_column($rows, 'mooe')), 'co' => array_sum(array_column($rows, 'co'))];
json_out([
    'fiscal_year' => $fy,
    'total' => $total,
    'given' => $given,
    'left' => ['mooe' => $total['mooe'] - $given['mooe'], 'co' => $total['co'] - $given['co']],
    'data' => $rows,
]);
