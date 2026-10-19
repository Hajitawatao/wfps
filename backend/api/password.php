<?php
require __DIR__ . '/cors.php';
$u = guard(['POST' => 'user'], true);
$hash = (string)scalar('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
if (!password_verify((string)input('old_password', ''), $hash)) throw new HttpError(422, 'Your current password is incorrect.');
$new = (string)input('new_password', '');
if (strlen($new) < 8) throw new HttpError(422, 'The new password needs at least 8 characters.');
if (password_verify($new, $hash)) throw new HttpError(422, 'Choose a password different from the current one.');
q('UPDATE users SET password_hash = ?, must_change = 0 WHERE id = ?', [password_hash($new, PASSWORD_BCRYPT), $u['id']]);
json_out(['ok' => true, 'user' => current_user(true)]);
