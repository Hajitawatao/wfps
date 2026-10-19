<?php
require __DIR__ . '/cors.php';
$u = guard(['GET' => 'public'], true);
json_out(['user' => $u]);
