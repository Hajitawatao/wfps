<?php
require __DIR__ . '/cors.php';
guard(['POST' => 'public']);
$u = check_password(trim((string)input('username', '')), (string)input('password', ''));
session_regenerate_id(true);
$_SESSION['uid'] = (int)$u['id'];
json_out(['user' => current_user(true)]);
