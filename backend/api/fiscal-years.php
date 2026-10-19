<?php
require __DIR__ . '/cors.php';
// POST {fiscal_year}: open another year. Names are copied from the latest year; budgets start at 0.
guard(['POST' => ['admin', 'planning']]);
$y = (int)input('fiscal_year', 0);
if ($y < 2000 || $y > 2100) throw new HttpError(422, 'Enter a valid fiscal year.');
if (scalar('SELECT 1 FROM settings WHERE fiscal_year = ?', [$y])) throw new HttpError(422, "FY $y is already open.");
$prev = one('SELECT office_name, prepared_by, prepared_pos, recommending_by, recommending_pos, approved_by, approved_pos
             FROM settings ORDER BY fiscal_year DESC LIMIT 1') ?? ['office_name' => 'Region I Medical Center'];
insert_row('settings', ['fiscal_year' => $y] + $prev);
json_out(['ok' => true], 201);
