<?php
// Router for PHP's built-in web server (start.bat / start.sh / npm run dev):
//   php -S 0.0.0.0:86 -t frontend/dist server.php
// /api/<name>.php  -> backend/api/<name>.php
// existing file in frontend/dist -> served as is
// anything else    -> frontend/dist/index.html (Vue Router history mode)
// Apache does the same with deploy/apache-wfp.conf.

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');

if (str_starts_with($path, '/api/')) {
    if (preg_match('#^/api/([a-z0-9-]+)\.php$#', $path, $m) && $m[1] !== 'cors' && is_file($f = __DIR__ . "/backend/api/{$m[1]}.php")) {
        require $f;
        return true;
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo '{"error":"Not found."}';
    return true;
}

$dist = __DIR__ . '/frontend/dist';
if ($path !== '/' && is_file($dist . $path) && !str_contains($path, '..')) return false; // static file

if (!is_file($dist . '/index.html')) {
    http_response_code(503);
    header('Content-Type: text/plain');
    echo "frontend/dist is missing. Run: npm run build";
    return true;
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
readfile($dist . '/index.html');
return true;
