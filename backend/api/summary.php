<?php
require __DIR__ . '/cors.php';
// Summary dashboard: rows 1-2 of the Encoding Sheet (Total / Remaining MOOE and CO of the whole hospital),
// the budget Procurement gave the chosen area, and what each area asked for.
//   ?fy=         fiscal year
//   ?office_id=  one area (Planning, Procurement, admin, viewer); encoders always get their own area
//   ?count=      all (default, like the Excel file) | final (submitted + approved) | approved
$u = guard(['GET' => 'user']);
$fy = fy();

$count = (string)input('count', 'all');
$counted = match ($count) {
    'final' => "a.workflow IN ('submitted','approved')",
    'approved' => "a.workflow = 'approved'",
    default => '1 = 1',
};
$oid = $u['role'] === 'encoder' ? (int)$u['office_id'] : (int)input('office_id', 0);
$inArea = $oid ? 'a.office_id = ' . $oid : '1 = 1';
$sums = "COALESCE(SUM(CASE WHEN a.expense_class = 'MOOE' THEN a.cost END), 0) mooe,
         COALESCE(SUM(CASE WHEN a.expense_class = 'CO' THEN a.cost END), 0) co, COALESCE(SUM(a.cost), 0) cost, COUNT(a.id) n";

// Encoding Sheet B1 / D1 = General Info budget + CONAP; B2 / D2 = total minus what is counted (whole hospital)
$total = hospital_budget($fy);
$hospital = one("SELECT $sums FROM activities a WHERE a.fiscal_year = ? AND $counted", [$fy]);
$scope = one("SELECT $sums FROM activities a WHERE a.fiscal_year = ? AND $inArea AND $counted", [$fy]);

// the area's own budget (set by Procurement): remaining = budget minus what the area encoded
$area = null;
if ($oid && ($b = area_budget($oid, $fy))) {
    $area = ['mooe_total' => $b['mooe'], 'mooe_remaining' => $b['mooe'] - (float)$scope['mooe'],
             'co_total' => $b['co'], 'co_remaining' => $b['co'] - (float)$scope['co']];
}

json_out([
    'fiscal_year' => $fy,
    'count' => $count,
    'budget' => ['mooe_total' => $total['mooe'], 'mooe_remaining' => $total['mooe'] - (float)$hospital['mooe'],
                 'co_total' => $total['co'], 'co_remaining' => $total['co'] - (float)$hospital['co']],
    'area_budget' => $area,
    // what the chosen area (or the whole hospital) asked for
    'scope' => ['office_id' => $oid ?: null, 'name' => $oid ? scalar('SELECT name FROM offices WHERE id = ?', [$oid]) : null] + $scope,
    'by_function' => all("SELECT a.function_type label, COUNT(*) n, SUM(a.cost) cost FROM activities a
                          WHERE a.fiscal_year = ? AND $inArea AND $counted
                          GROUP BY a.function_type ORDER BY FIELD(a.function_type,'Strategic','Core','Support')", [$fy]),
    // entries per status are always all of them, so drafts and returned rows stay visible
    'by_workflow' => all("SELECT a.workflow label, COUNT(*) n FROM activities a WHERE a.fiscal_year = ? AND $inArea GROUP BY a.workflow", [$fy]),
    'by_office' => $u['role'] === 'encoder' ? [] : all(
        "SELECT o.id, o.name label, $sums,
                COALESCE(SUM(a.workflow = 'submitted'), 0) submitted, COALESCE(SUM(a.workflow = 'approved'), 0) approved,
                MAX(b.mooe) mooe_budget, MAX(b.co) co_budget
         FROM offices o
         LEFT JOIN activities a ON a.office_id = o.id AND a.fiscal_year = ? AND $counted
         LEFT JOIN office_budgets b ON b.office_id = o.id AND b.fiscal_year = ?
         WHERE o.active = 1 GROUP BY o.id, o.name ORDER BY cost DESC, o.name", [$fy, $fy]),
]);
