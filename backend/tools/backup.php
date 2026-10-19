<?php
// Saves the whole WFP database to backups/wfp_YYYYMMDD_HHMM.sql (works without mysqldump).
// Restore: phpMyAdmin > database r1mc_wfp > Import this file (each table is dropped and recreated).
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';
date_default_timezone_set(cfg('timezone'));

$dir = __DIR__ . '/../../backups';
if (!is_dir($dir)) mkdir($dir, 0775, true);
$file = $dir . '/wfp_' . date('Ymd_His') . '.sql';
$pdo = db();
$fh = fopen($file, 'w');
fwrite($fh, "-- R1MC WFP backup " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
    fwrite($fh, "DROP TABLE IF EXISTS `$t`;\n$create;\n");
    foreach ($pdo->query("SELECT * FROM `$t`", PDO::FETCH_ASSOC) as $row) {
        $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
        fwrite($fh, "INSERT INTO `$t` (`" . implode('`,`', array_keys($row)) . "`) VALUES (" . implode(',', $vals) . ");\n");
    }
    fwrite($fh, "\n");
}
fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($fh);
echo "  Backup saved: " . realpath($file) . "\n";
