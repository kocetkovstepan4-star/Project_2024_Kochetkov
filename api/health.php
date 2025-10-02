<?php
require_once __DIR__ . '/db.php';

try {
    $db = db_connect();
    $res = $db->query('SELECT 1 AS ok');
    $row = $res ? $res->fetch_assoc() : null;
    respond([ 'ok' => true, 'db' => (int)($row['ok'] ?? 0), 'db_name' => DB_NAME ]);
} catch (Throwable $e) {
    respond([ 'ok' => false, 'error' => 'DB error' ], 500);
}


