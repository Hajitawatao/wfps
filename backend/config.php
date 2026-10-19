<?php
// Settings. Each value can be set by an environment variable (start.local.bat sets them for start.bat;
// for Apache use SetEnv in the VirtualHost). The defaults fit a stock XAMPP.
$env = static function (string $key, string $default): string {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
};

return [
    'db_host'       => $env('WFP_DB_HOST', '127.0.0.1'),
    'db_port'       => (int)$env('WFP_DB_PORT', '3306'),
    'db_name'       => $env('WFP_DB_NAME', 'r1mc_wfp'),
    'db_user'       => $env('WFP_DB_USER', 'root'),
    'db_pass'       => $env('WFP_DB_PASS', ''),
    'timezone'      => $env('APP_TZ', 'Asia/Manila'),
    'session_name'  => 'WFPSESSID',
    'session_hours' => 8,
];
