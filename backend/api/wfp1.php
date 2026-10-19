<?php
require __DIR__ . '/cors.php';
// Print (GAA) / Print (Income): Budget Line Item > A. Strategic / B. Core / C. Support > rows, with sub-totals and totals.
$u = guard(['GET' => 'user']);
$income = strtoupper((string)input('form', 'GAA')) === 'INCOME';
[$where, $p] = activity_filters($u);
$where .= ' AND f.is_income = ' . ($income ? 1 : 0);
$rows = all(ACT_SELECT . " WHERE $where ORDER BY l.sort_order, l.name, FIELD(a.function_type,'Strategic','Core','Support'), a.id", $p);

$groups = [];
foreach ($rows as $r) {
    $li = $r['line_item'];
    if (!isset($groups[$li])) {
        $groups[$li] = ['line_item' => $li, 'total' => 0.0, 'functions' => []];
        foreach (FUNCTION_TYPES as $ft) $groups[$li]['functions'][$ft] = ['function_type' => $ft, 'subtotal' => 0.0, 'rows' => []];
    }
    $groups[$li]['functions'][$r['function_type']]['rows'][] = [
        'id' => (int)$r['id'], 'objective' => $r['objective'], 'title' => $r['title'],
        'start_date' => $r['start_date'], 'end_date' => $r['end_date'],
        'q1' => $r['target_q1'], 'q2' => $r['target_q2'], 'q3' => $r['target_q3'], 'q4' => $r['target_q4'],
        'unit' => $r['target_unit'], 'cost' => (float)$r['cost'], 'fund_source' => $r['fund_source'],
        'responsible' => $r['responsible'], 'workflow' => $r['workflow'],
    ];
    $groups[$li]['functions'][$r['function_type']]['subtotal'] += (float)$r['cost'];
    $groups[$li]['total'] += (float)$r['cost'];
}
foreach ($groups as &$g) $g['functions'] = array_values($g['functions']);
unset($g);

$oid = $u['role'] === 'encoder' ? (int)$u['office_id'] : (int)input('office_id', 0);
$office = $oid ? scalar('SELECT name FROM offices WHERE id = ?', [$oid]) : null;

json_out([
    'form' => $income ? 'INCOME' : 'GAA',
    'fiscal_year' => fy(),
    'settings' => one('SELECT * FROM settings WHERE fiscal_year = ?', [fy()]),
    'office' => $office,
    'groups' => array_values($groups),
    'grand_total' => array_sum(array_column($groups, 'total')),
    'count' => count($rows),
]);
