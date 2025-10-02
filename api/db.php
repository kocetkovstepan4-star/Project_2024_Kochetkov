<?php
require_once __DIR__ . '/config.php';

function db_connect(): mysqli {
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        respond([ 'ok' => false, 'error' => 'DB connection failed' ], 500);
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

function hash_password(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}


