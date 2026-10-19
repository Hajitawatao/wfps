<?php
require __DIR__ . '/cors.php';
// Dropdown lists of the Encoding Sheet (hidden "Validation" sheet): ?list=objectives|indicators|classifications|line_items|fund_sources
// GET: everyone signed in. POST (add), PUT ?id=, DELETE ?id=: Planning Office / admin.
guard(['GET' => 'user', 'POST' => ['admin', 'planning'], 'PUT' => ['admin', 'planning'], 'DELETE' => ['admin', 'planning']]);
$lists = [
    'objectives'      => [['function_type', 'title', 'sort_order', 'active'], "FIELD(function_type,'Strategic','Core','Support'), sort_order, id"],
    'indicators'      => [['objective_id', 'title', 'sort_order', 'active'], 'objective_id, sort_order, id'],
    'classifications' => [['name', 'sort_order', 'active'], 'sort_order, name'],
    'line_items'      => [['name', 'sort_order', 'active'], 'sort_order, name'],
    'fund_sources'    => [['name', 'is_income', 'sort_order', 'active'], 'sort_order'],
];
$table = (string)($_GET['list'] ?? '');
if (!isset($lists[$table])) throw new HttpError(404, 'Unknown list.');
[$fields, $order] = $lists[$table];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') json_out(['data' => all("SELECT * FROM $table ORDER BY $order")]);
if ($method === 'DELETE') { q("DELETE FROM $table WHERE id = ?", [id_param()]); json_out(['ok' => true]); }

$b = body();
$data = [];
foreach ($fields as $f) if (array_key_exists($f, $b)) $data[$f] = is_string($b[$f]) ? trim($b[$f]) : (is_bool($b[$f]) ? (int)$b[$f] : $b[$f]);
if (isset($data['function_type']) && !in_array($data['function_type'], FUNCTION_TYPES, true)) throw new HttpError(422, 'Unknown type of function.');
if (($data['title'] ?? $data['name'] ?? 'x') === '') throw new HttpError(422, 'The text cannot be empty.');

if ($method === 'POST') $id = insert_row($table, $data);
else update_row($table, 'id', $id = id_param(), $data);
json_out(['data' => one("SELECT * FROM $table WHERE id = ?", [$id])], $method === 'POST' ? 201 : 200);
