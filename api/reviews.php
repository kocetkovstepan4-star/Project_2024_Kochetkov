<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $db = db_connect();
    $sql = 'SELECT r.id, r.rating, r.comment, r.created_at, u.username AS phone
            FROM reviews r LEFT JOIN users u ON u.id = r.user_id
            ORDER BY r.created_at DESC LIMIT 100';
    $res = $db->query($sql);
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'rating' => (int)$row['rating'],
            'text' => $row['comment'] ?? '',
            'phone' => $row['phone'] ?? 'Гость',
            'ts' => strtotime($row['created_at']) * 1000,
        ];
    }
    respond([ 'ok' => true, 'items' => $items ]);
}

if ($method === 'POST') {
    require_auth_session();
    $data = json_body();
    require_fields($data, ['rating','text']);
    $rating = (int)$data['rating'];
    $text = trim((string)$data['text']);
    if ($rating < 1 || $rating > 5) respond([ 'ok' => false, 'error' => 'Bad rating' ], 400);
    if (mb_strlen($text) < 3) respond([ 'ok' => false, 'error' => 'Text too short' ], 400);

    $db = db_connect();
    // find user id by phone
    $phone = current_user_phone();
    $uid = null;
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $stmt->bind_result($uidVal);
    if ($stmt->fetch()) { $uid = (int)$uidVal; }
    $stmt->close();

    $stmt = $db->prepare('INSERT INTO reviews (user_id, quest_id, name, rating, comment) VALUES (?, NULL, ?, ?, ?)');
    $name = $phone;
    $stmt->bind_param('isis', $uid, $name, $rating, $text);
    if (!$stmt->execute()) respond([ 'ok' => false, 'error' => 'Insert failed' ], 500);
    respond([ 'ok' => true ]);
}

respond([ 'ok' => false, 'error' => 'Not found' ], 404);


