<?php
require __DIR__ . '/cors.php';
// GET: names of the active areas, for the "Request access" form on the sign-in page (no sign-in needed).
guard(['GET' => 'public']);
json_out(['data' => all('SELECT id, name FROM offices WHERE active = 1 ORDER BY name')]);
