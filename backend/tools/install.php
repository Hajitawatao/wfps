<?php
// Prepares the MySQL database. Run by start.bat / start.sh before the web server starts.
//   php backend/tools/install.php            create the database the first time, check it afterwards
//   php backend/tools/install.php --replace  replace a database from an older, incompatible version
// Exit codes: 0 ready, 2 MySQL not reachable, 3 older database found (needs --replace), 4 wrong MySQL password, 1 other error.
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';
date_default_timezone_set(cfg('timezone'));

function out(string $s): void { fwrite(STDOUT, "  $s\n"); }

/** Splits an SQL file into statements (ignores ; inside quotes and -- comments). */
function sql_statements(string $sql): array
{
    $out = [];
    $cur = '';
    $quote = null;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($quote === null && $c === '-' && ($sql[$i + 1] ?? '') === '-') {
            while ($i < $len && $sql[$i] !== "\n") $i++;
            $cur .= "\n";
            continue;
        }
        if ($quote !== null) {
            if ($c === '\\') { $cur .= $c . ($sql[++$i] ?? ''); continue; }
            if ($c === $quote) {
                if (($sql[$i + 1] ?? '') === $quote) { $cur .= $c . $c; $i++; continue; }
                $quote = null;
            }
        } elseif ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
        } elseif ($c === ';') {
            if (trim($cur) !== '') $out[] = trim($cur);
            $cur = '';
            continue;
        }
        $cur .= $c;
    }
    if (trim($cur) !== '') $out[] = trim($cur);
    return $out;
}

function import_file(PDO $pdo, string $file): void
{
    foreach (sql_statements(file_get_contents($file)) as $st) $pdo->exec($st);
}

$replace = in_array('--replace', $argv, true);
$name = cfg('db_name');
if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) { out("Invalid database name: $name"); exit(1); }

try {
    $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', cfg('db_host'), cfg('db_port')), cfg('db_user'), cfg('db_pass'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Access denied')) {
        out('MySQL refused the user "' . cfg('db_user') . '". Set WFP_DB_USER / WFP_DB_PASS in start.local.bat.');
        exit(4);
    }
    exit(2);
}

try {
    $hasTables = (bool)$server->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $server->quote($name) . " AND table_name = 'activities'")->fetchColumn();
    $version = null;
    if ($hasTables) {
        $hasMeta = (bool)$server->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $server->quote($name) . " AND table_name = 'app_meta'")->fetchColumn();
        if ($hasMeta) $version = $server->query("SELECT value FROM `$name`.app_meta WHERE name = 'schema_version'")->fetchColumn() ?: null;
    }

    // Older databases are upgraded in place, data kept. Version 1 (the earlier php -S build) lacks the lockout columns and app_meta.
    $col = fn(string $t, string $c) => (bool)$server->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = "
        . $server->quote($name) . " AND table_name = '$t' AND column_name = '$c'")->fetchColumn();
    if ($hasTables && $version === null && $col('users', 'reset_requested_at') && $col('settings', 'encoding_deadline')) {
        out("Upgrading database \"$name\" (your offices, users and activities are kept)...");
        $server->exec("USE `$name`");
        if (!$col('users', 'failed_logins')) $server->exec('ALTER TABLE users ADD COLUMN failed_logins TINYINT NOT NULL DEFAULT 0 AFTER reset_requested_at');
        if (!$col('users', 'locked_until')) $server->exec('ALTER TABLE users ADD COLUMN locked_until DATETIME NULL AFTER failed_logins');
        $server->exec('CREATE TABLE IF NOT EXISTS app_meta (name VARCHAR(50) PRIMARY KEY, value VARCHAR(200) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $server->exec("REPLACE INTO app_meta (name, value) VALUES ('schema_version', '2')");
        $version = '2';
    }
    // Version 2 -> 3: access requests (data kept)
    if ($hasTables && $version === '2') {
        out("Upgrading database \"$name\" (adding access requests; data is kept)...");
        $server->exec("USE `$name`");
        $server->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS access_requests (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(150) NOT NULL,
  position      VARCHAR(150) NULL,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,             -- chosen by the requester; becomes the account password when granted
  office_id     INT NULL,                          -- an existing area ...
  new_office    VARCHAR(150) NULL,                 -- ... or a new one, created when access is granted
  role          ENUM('encoder','viewer') NOT NULL DEFAULT 'encoder',  -- level asked for; the admin may grant another
  note          VARCHAR(300) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);
        $server->exec("REPLACE INTO app_meta (name, value) VALUES ('schema_version', '3')");
        $version = '3';
    }
    // Version 3 -> 4: signatories per area (data kept)
    if ($hasTables && $version === '3') {
        out("Upgrading database \"$name\" (adding signatories per area; data is kept)...");
        $server->exec("USE `$name`");
        foreach (['prepared_by', 'prepared_pos', 'recommending_by', 'recommending_pos', 'approved_by', 'approved_pos'] as $c)
            if (!$col('offices', $c)) $server->exec("ALTER TABLE offices ADD COLUMN $c VARCHAR(150) NULL");
        $server->exec("REPLACE INTO app_meta (name, value) VALUES ('schema_version', '4')");
        $version = '4';
    }
    // Version 4 -> 5: head of each area (a user account) (data kept)
    if ($hasTables && $version === '4') {
        out("Upgrading database \"$name\" (adding area heads; data is kept)...");
        $server->exec("USE `$name`");
        if (!$col('offices', 'head_user_id')) $server->exec('ALTER TABLE offices ADD COLUMN head_user_id INT NULL AFTER active');
        if ($col('offices', 'head_name')) {
            // a typed head name becomes the area's "Prepared by" if that was still empty
            $server->exec('UPDATE offices SET prepared_by = head_name WHERE prepared_by IS NULL AND head_name IS NOT NULL');
            $server->exec('ALTER TABLE offices DROP COLUMN head_name');
        }
        $server->exec("REPLACE INTO app_meta (name, value) VALUES ('schema_version', '5')");
        $version = '5';
    }
    // Version 5 -> 6: budget per area removed (the WFP allocation is for the whole hospital)
    if ($hasTables && $version === '5') {
        $server->exec("USE `$name`");
        $server->exec('DROP TABLE IF EXISTS office_budgets');
        $server->exec("REPLACE INTO app_meta (name, value) VALUES ('schema_version', '6')");
        $version = '6';
    }
    // Version 6 -> 7: notification of new submissions for the Planning Office (data kept)
    if ($hasTables && $version === '6') {
        $server->exec("USE `$name`");
        if (!$col('activities', 'submitted_by')) $server->exec('ALTER TABLE activities ADD COLUMN submitted_by INT NULL AFTER reviewed_by');
        if (!$col('users', 'notes_seen_at')) $server->exec('ALTER TABLE users ADD COLUMN notes_seen_at DATETIME NULL AFTER last_login');
        $server->exec("REPLACE INTO app_meta (name, value) VALUES ('schema_version', '7')");
        $version = '7';
    }
    // Version 7 -> 8: signatories per area removed; WFP Form 1 is signed for the whole hospital (General Info)
    if ($hasTables && $version === '7') {
        $server->exec("USE `$name`");
        foreach (['head_user_id', 'prepared_by', 'prepared_pos', 'recommending_by', 'recommending_pos', 'approved_by', 'approved_pos'] as $c)
            if ($col('offices', $c)) $server->exec("ALTER TABLE offices DROP COLUMN $c");
        $server->exec("REPLACE INTO app_meta (name, value) VALUES ('schema_version', '8')");
        $version = '8';
    }
    // Version 8 -> 9: the text typed when "Others, please insert note/remarks" is the objective or indicator (data kept)
    if ($hasTables && $version === '8') {
        $server->exec("USE `$name`");
        if (!$col('activities', 'objective_other')) $server->exec('ALTER TABLE activities ADD COLUMN objective_other VARCHAR(600) NULL AFTER indicator_id');
        if (!$col('activities', 'indicator_other')) $server->exec('ALTER TABLE activities ADD COLUMN indicator_other VARCHAR(600) NULL AFTER objective_other');
        $server->exec("REPLACE INTO app_meta (name, value) VALUES ('schema_version', '9')");
        $version = '9';
    }
    // Version 9 -> 10: Procurement role and the budget of each area (data kept)
    if ($hasTables && $version === '9') {
        $server->exec("USE `$name`");
        $server->exec("ALTER TABLE users MODIFY role ENUM('admin','planning','procurement','encoder','viewer') NOT NULL DEFAULT 'encoder'");
        $server->exec("CREATE TABLE IF NOT EXISTS office_budgets (
            fiscal_year SMALLINT NOT NULL, office_id INT NOT NULL,
            mooe DECIMAL(14,2) NOT NULL DEFAULT 0, co DECIMAL(14,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (fiscal_year, office_id),
            CONSTRAINT fk_budget_office FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $server->exec("REPLACE INTO app_meta (name, value) VALUES ('schema_version', '10')");
        $version = SCHEMA_VERSION;
    }

    if ($hasTables && $version !== SCHEMA_VERSION) {
        if (!$replace) {
            out("The database \"$name\" is from an earlier test version of this system and has to be replaced.");
            exit(3);
        }
        $server->exec("DROP DATABASE `$name`");
        $hasTables = false;
    }

    if (!$hasTables) {
        out("Creating database \"$name\"...");
        $server->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $server->exec("USE `$name`");
        import_file($server, __DIR__ . '/../../database/schema.sql');
        import_file($server, __DIR__ . '/../../database/seed.sql');
        out('Database created. First sign-in: admin / admin123 and planning / wfp2026.');
    }

    // FY 2026 and FY 2027 are always open (an older install may have only one of them)
    $server->exec("USE `$name`");
    $server->exec("INSERT INTO settings (fiscal_year, office_name, prepared_by, prepared_pos, recommending_by, recommending_pos, approved_by, approved_pos)
        SELECT y.fy, s.office_name, s.prepared_by, s.prepared_pos, s.recommending_by, s.recommending_pos, s.approved_by, s.approved_pos
        FROM (SELECT 2026 fy UNION SELECT 2027) y
        CROSS JOIN (SELECT * FROM settings ORDER BY fiscal_year DESC LIMIT 1) s
        WHERE NOT EXISTS (SELECT 1 FROM settings x WHERE x.fiscal_year = y.fy)");
    out('Database ready.');
    exit(0);
} catch (Throwable $e) {
    out('Database setup failed: ' . $e->getMessage());
    exit(1);
}
