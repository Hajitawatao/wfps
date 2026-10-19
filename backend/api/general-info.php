<?php
require __DIR__ . '/cors.php';
// The "General Info" sheet of one fiscal year (?fy=). GET: everyone signed in. PUT: Planning Office / admin.
guard(['GET' => 'user', 'PUT' => ['admin', 'planning']]);
if ($_SERVER['REQUEST_METHOD'] === 'GET') json_out(['data' => one('SELECT * FROM settings WHERE fiscal_year = ?', [fy()])]);

$y = (int)input('fiscal_year', 0);
if (!scalar('SELECT 1 FROM settings WHERE fiscal_year = ?', [$y])) throw new HttpError(404, "FY $y is not open.");
$money = ['mooe_budget', 'co_budget', 'conap_mooe', 'conap_co', 'other_fund'];
$text = ['office_name', 'prepared_by', 'prepared_pos', 'recommending_by', 'recommending_pos', 'approved_by', 'approved_pos', 'document_code', 'encoding_deadline'];
$b = body();
$data = [];
foreach ($money as $f) if (array_key_exists($f, $b)) $data[$f] = max(0, (float)$b[$f]);
foreach ($text as $f) if (array_key_exists($f, $b)) $data[$f] = ($b[$f] === '' || $b[$f] === null) ? null : trim((string)$b[$f]);
if (array_key_exists('encoding_open', $b)) $data['encoding_open'] = $b['encoding_open'] ? 1 : 0;
if (isset($data['encoding_deadline']) && !DateTime::createFromFormat('!Y-m-d', $data['encoding_deadline'])) throw new HttpError(422, 'The deadline is not a valid date.');
update_row('settings', 'fiscal_year', $y, $data);
json_out(['ok' => true]);
