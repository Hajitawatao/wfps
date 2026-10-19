<?php
declare(strict_types=1);

// The database layout this code expects (database/schema.sql writes the same number into app_meta).
const SCHEMA_VERSION = '8';

class HttpError extends Exception
{
    public function __construct(public int $status, string $message, public array $extra = [])
    {
        parent::__construct($message);
    }
}

function cfg(string $key)
{
    static $c = null;
    $c ??= require __DIR__ . '/../config.php';
    return $c[$key] ?? null;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', cfg('db_host'), cfg('db_port'), cfg('db_name'));
        $pdo = new PDO($dsn, cfg('db_user'), cfg('db_pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function one(string $sql, array $params = []): ?array { return q($sql, $params)->fetch() ?: null; }
function scalar(string $sql, array $params = []) { return q($sql, $params)->fetchColumn(); }

function insert_row(string $table, array $data): int
{
    $cols = array_keys($data);
    q("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data));
    return (int)db()->lastInsertId();
}

function update_row(string $table, string $keyCol, $key, array $data): void
{
    if (!$data) throw new HttpError(422, 'Nothing to save.');
    q("UPDATE $table SET " . implode(',', array_map(fn($c) => "$c=?", array_keys($data))) . " WHERE $keyCol=?", [...array_values($data), $key]);
}
