<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'register') {
    $data = json_body();
    require_fields($data, ['phone','password']);
    $phone = preg_replace('/\s|\-/', '', (string)$data['phone']);
    $password = (string)$data['password'];
    if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
        respond([ 'ok' => false, 'error' => 'Invalid phone' ], 400);
    }
    if (strlen($password) < 4) {
        respond([ 'ok' => false, 'error' => 'Password too short' ], 400);
    }

    $db = db_connect();
    // If phone is stored in users.username or email? We'll use username=phone
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        respond([ 'ok' => false, 'error' => 'User already exists' ], 409);
    }
    $stmt->close();

    $hash = hash_password($password);
    $email = $phone . '@example.local';
    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $phone, $email, $hash);
    if (!$stmt->execute()) {
        respond([ 'ok' => false, 'error' => 'Registration failed' ], 500);
    }
    $_SESSION['phone'] = $phone;
    respond([ 'ok' => true, 'phone' => $phone ]);
}

if ($method === 'POST' && $action === 'login') {
    $data = json_body();
    require_fields($data, ['phone','password']);
    $phone = preg_replace('/\s|\-/', '', (string)$data['phone']);
    $password = (string)$data['password'];
    $db = db_connect();
    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $stmt->bind_result($uid, $hash);
    if ($stmt->fetch()) {
        if (verify_password($password, $hash)) {
            $_SESSION['phone'] = $phone;
            respond([ 'ok' => true, 'phone' => $phone ]);
        }
    }
    respond([ 'ok' => false, 'error' => 'Invalid credentials' ], 401);
}

if ($method === 'POST' && $action === 'logout') {
    session_destroy();
    respond([ 'ok' => true ]);
}

if ($method === 'GET' && $action === 'me') {
    $phone = current_user_phone();
    if ($phone) respond([ 'ok' => true, 'phone' => $phone ]);
    respond([ 'ok' => false ], 200);
}

respond([ 'ok' => false, 'error' => 'Not found' ], 404);


