<?php
require __DIR__ . '/cors.php';
// Everything the Encoding Sheet dropdowns need (the hidden "Validation" sheet) + the year's General Info.
guard(['GET' => 'user']);
$objectives = all('SELECT id, function_type, title FROM objectives WHERE active = 1 ORDER BY sort_order, id');
$byObj = [];
foreach (all('SELECT id, objective_id, title FROM indicators WHERE active = 1 ORDER BY sort_order, id') as $i) $byObj[$i['objective_id']][] = $i;
foreach ($objectives as &$o) $o['indicators'] = $byObj[$o['id']] ?? [];
unset($o);
json_out([
    'offices' => all('SELECT id, code, name FROM offices WHERE active = 1 ORDER BY name'),
    'objectives' => $objectives,
    'classifications' => all('SELECT id, name FROM classifications WHERE active = 1 ORDER BY sort_order, name'),
    'line_items' => all('SELECT id, name FROM line_items WHERE active = 1 ORDER BY sort_order, name'),
    'fund_sources' => all('SELECT id, name, is_income FROM fund_sources WHERE active = 1 ORDER BY sort_order'),
    'years' => array_map('intval', array_column(all('SELECT fiscal_year FROM settings ORDER BY fiscal_year DESC'), 'fiscal_year')),
    'settings' => one('SELECT * FROM settings WHERE fiscal_year = ?', [fy()]),
]);
