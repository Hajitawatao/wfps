<?php
require __DIR__ . '/cors.php';
// No e-mail on the LAN: the request shows up on the administrator's Users and offices page.
guard(['POST' => 'public']);
$username = trim((string)input('username', ''));
if ($username !== '') q('UPDATE users SET reset_requested_at = NOW() WHERE username = ? AND active = 1', [$username]);
json_out(['ok' => true]); // same answer whether or not the username exists
