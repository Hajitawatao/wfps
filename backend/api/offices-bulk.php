<?php
require __DIR__ . '/cors.php';
// POST {lines}: add areas from a pasted list, one per line: "CODE, Name" or just "Name".
// Admin: each new area also gets an encoder account (username = code in small letters) with a temporary password, shown once.
// Planning Office: adds the areas only; MIS (admin) makes the accounts.
$u = guard(['POST' => ['admin', 'planning']]);
$withAccounts = $u['role'] === 'admin';
$done = [];
$skipped = [];
db()->beginTransaction();
foreach (preg_split('/\r\n|\r|\n/', (string)input('lines', '')) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    [$code, $name] = preg_match('/^([A-Za-z0-9_-]{2,20})\s*[,\t|]\s*(.+)$/', $line, $m) ? [$m[1], $m[2]] : [null, $line];
    [$oid, $code, $created] = ensure_office($name, $code);
    if (!$created) { $skipped[] = trim($name); continue; }
    if (!$withAccounts) { $done[] = ['office' => trim($name), 'username' => null, 'password' => null]; continue; }
    $pass = make_password();
    insert_row('users', ['username' => strtolower($code), 'password_hash' => password_hash($pass, PASSWORD_BCRYPT),
        'full_name' => trim($name) . ' Encoder', 'role' => 'encoder', 'office_id' => $oid, 'must_change' => 1]);
    $done[] = ['office' => trim($name), 'username' => strtolower($code), 'password' => $pass];
}
db()->commit();
json_out(['created' => $done, 'skipped' => $skipped], 201);
