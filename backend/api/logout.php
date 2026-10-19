<?php
require __DIR__ . '/cors.php';
guard(['POST' => 'public']);
session_unset();
session_destroy();
json_out(['ok' => true]);
