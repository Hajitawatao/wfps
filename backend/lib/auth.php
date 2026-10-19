<?php
declare(strict_types=1);

const ROLES = ['admin', 'planning', 'encoder', 'viewer'];
const MAX_TRIES = 5;          // wrong passwords in a row before the account is locked
const LOCK_MINUTES = 15;

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name(cfg('session_name'));
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    if (isset($_SESSION['last']) && time() - $_SESSION['last'] > cfg('session_hours') * 3600) session_unset();
    $_SESSION['last'] = time();
}

function current_user(bool $refresh = false): ?array
{
    static $u = false;
    if ($u === false || $refresh) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = one('SELECT u.id, u.username, u.full_name, u.position, u.role, u.office_id, u.must_change,
                             o.name office_name, o.code office_code
                      FROM users u LEFT JOIN offices o ON o.id = u.office_id
                      WHERE u.id = ? AND u.active = 1', [$_SESSION['uid']]);
        }
    }
    return $u;
}

function is_reviewer(?array $u): bool { return in_array($u['role'] ?? '', ['admin', 'planning'], true); }

/**
 * Every endpoint calls this first. $rules maps HTTP method => who may call it:
 *   'public'  anyone, signed in or not
 *   'user'    any signed-in user
 *   [roles]   signed-in users with one of these roles
 * $passwordStep: still allowed while the user must replace a temporary password.
 * Returns the signed-in user (or null on a public call without a session).
 */
function guard(array $rules, bool $passwordStep = false): ?array
{
    $method = $_SERVER['REQUEST_METHOD'];
    if (!array_key_exists($method, $rules)) throw new HttpError(405, 'Method not allowed.');
    $rule = $rules[$method];
    $u = current_user();
    if ($rule === 'public') return $u;
    if (!$u) throw new HttpError(401, 'Please sign in.');
    if ((int)$u['must_change'] && !$passwordStep) throw new HttpError(403, 'Set a new password first.');
    if ($rule !== 'user' && !in_array($u['role'], (array)$rule, true)) throw new HttpError(403, 'Your account is not allowed to do this.');
    return $u;
}

function check_password(string $username, string $password): array
{
    $u = one('SELECT id, password_hash, active, failed_logins, locked_until FROM users WHERE username = ?', [$username]);
    if ($u && $u['locked_until'] && strtotime($u['locked_until']) > time()) {
        $mins = (int)ceil((strtotime($u['locked_until']) - time()) / 60);
        throw new HttpError(429, "Too many wrong passwords. Try again in $mins minute" . ($mins === 1 ? '' : 's') . ', or ask MIS to reset your password.');
    }
    if (!$u || !$u['active'] || !password_verify($password, $u['password_hash'])) {
        if ($u) {
            $fails = (int)$u['failed_logins'] + 1;
            $lock = $fails >= MAX_TRIES ? date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60) : null;
            q('UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?', [$lock ? 0 : $fails, $lock, $u['id']]);
        }
        if (!$u && scalar('SELECT 1 FROM access_requests WHERE username = ?', [$username]))
            throw new HttpError(403, 'Your access request is still waiting for the administrator (MIS).');
        throw new HttpError(401, 'Wrong username or password.');
    }
    q('UPDATE users SET failed_logins = 0, locked_until = NULL, last_login = NOW() WHERE id = ?', [$u['id']]);
    return $u;
}

function make_password(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789'; // no 0/O or 1/l, easy to read aloud
    $p = '';
    for ($i = 0; $i < 8; $i++) $p .= $chars[random_int(0, strlen($chars) - 1)];
    return $p;
}
