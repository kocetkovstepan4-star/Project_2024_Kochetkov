<?php
// Basic configuration for DB and CORS

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('DB_HOST', 'sql111.infinityfree.com');
define('DB_USER', 'if0_40067151');
define('DB_PASS', 'wtZ5blAJENBqS');
define('DB_NAME', 'if0_40067151_asd');
// Simple admin token for maintenance endpoints (change to a secret value)
define('SEED_TOKEN', 'change_me_secret');

// Force timezone for consistent datetimes
date_default_timezone_set('Europe/Moscow');

function json_body() {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_fields($arr, $fields) {
    foreach ($fields as $f) {
        if (!isset($arr[$f]) || $arr[$f] === '') {
            respond([ 'ok' => false, 'error' => 'Missing field: ' . $f ], 400);
        }
    }
}


