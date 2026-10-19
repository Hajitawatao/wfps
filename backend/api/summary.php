<?php
require __DIR__ . '/cors.php';
// Summary dashboard: rows 1-2 of the Encoding Sheet (Total / Remaining MOOE and CO of the whole hospital)
// and what each area asked for. The WFP allocation is for the whole hospital, so every area's cost comes off the same total.
//   ?fy=         fiscal year
//   ?office_id=  one area (Planning, admin, viewer); encoders always get their own area
//   ?count=      all (default, like the Excel file) | final (submitted + approved) | approved
$u = guard(['GET' => 'user']);
$fy = fy();
$set = one('SELECT * FROM settings WHERE fiscal_year = ?', [$fy]) ?? [];

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

// Encoding Sheet B1 = General Info E13 + E17; D1 = E15 + E19; B2/D2 = total minus what is counted (whole hospital)
$mooe = (float)($set['mooe_budget'] ?? 0) + (float)($set['conap_mooe'] ?? 0);
$co = (float)($set['co_budget'] ?? 0) + (float)($set['conap_co'] ?? 0);
$hospital = one("SELECT $sums FROM activities a WHERE a.fiscal_year = ? AND $counted", [$fy]);

json_out([
    'fiscal_year' => $fy,
    'count' => $count,
    'budget' => ['mooe_total' => $mooe, 'mooe_remaining' => $mooe - (float)$hospital['mooe'],
                 'co_total' => $co, 'co_remaining' => $co - (float)$hospital['co']],
    // what the chosen area (or the whole hospital) asked for
    'scope' => ['office_id' => $oid ?: null, 'name' => $oid ? scalar('SELECT name FROM offices WHERE id = ?', [$oid]) : null]
        + one("SELECT $sums FROM activities a WHERE a.fiscal_year = ? AND $inArea AND $counted", [$fy]),
    'by_function' => all("SELECT a.function_type label, COUNT(*) n, SUM(a.cost) cost FROM activities a
                          WHERE a.fiscal_year = ? AND $inArea AND $counted
                          GROUP BY a.function_type ORDER BY FIELD(a.function_type,'Strategic','Core','Support')", [$fy]),
    // entries per status are always all of them, so drafts and returned rows stay visible
    'by_workflow' => all("SELECT a.workflow label, COUNT(*) n FROM activities a WHERE a.fiscal_year = ? AND $inArea GROUP BY a.workflow", [$fy]),
    'by_office' => $u['role'] === 'encoder' ? [] : all(
        "SELECT o.id, o.name label, $sums,
                COALESCE(SUM(a.workflow = 'submitted'), 0) submitted, COALESCE(SUM(a.workflow = 'approved'), 0) approved
         FROM offices o LEFT JOIN activities a ON a.office_id = o.id AND a.fiscal_year = ? AND $counted
         WHERE o.active = 1 GROUP BY o.id, o.name ORDER BY cost DESC, o.name", [$fy]),
]);
