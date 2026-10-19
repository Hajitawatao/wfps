<?php
// Shared start of every endpoint: headers, JSON helpers, error handling, session and libraries.
// Endpoints: <?php require __DIR__ . '/cors.php'; $u = guard([...]);
declare(strict_types=1);

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/wfp.php';

date_default_timezone_set(cfg('timezone'));
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function json_out($data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function body(): array
{
    static $b = null;
    if ($b === null) {
        $raw = file_get_contents('php://input');
        $b = $raw ? (json_decode($raw, true) ?? []) : [];
    }
    return $b;
}

/** A value from the JSON body, else from the query string. */
function input(string $key, $default = null)
{
    $b = body();
    return array_key_exists($key, $b) ? $b[$key] : ($_GET[$key] ?? $default);
}

/** ?id= of the record the endpoint works on. */
function id_param(): int
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id < 1) throw new HttpError(400, 'Missing id.');
    return $id;
}

set_exception_handler(function (Throwable $e) {
    if ($e instanceof HttpError) json_out(['error' => $e->getMessage()] + $e->extra, $e->status);
    error_log('[WFP] ' . $e);
    if ($e instanceof PDOException) {
        $m = $e->getMessage();
        $msg = str_contains($m, 'foreign key') ? 'This item is still used by encoded activities, so it cannot be deleted. Set it inactive instead.'
             : (str_contains($m, 'Duplicate entry') ? 'That code or username is already used.'
             : 'Database error. Check that MySQL is running.');
        json_out(['error' => $msg], 500);
    }
    json_out(['error' => 'Server error.'], 500);
});

// Changes must come from our own pages (fetch adds this header); a plain form on another site cannot.
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    throw new HttpError(400, 'Bad request.');
}

start_session();
