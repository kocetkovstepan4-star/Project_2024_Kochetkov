<?php
require_once __DIR__ . '/db.php';

// Usage: GET /api/seed_slots.php?token=...&days=14&times=12:00-13:00,14:00-15:00&from=2025-10-01

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if ($token !== SEED_TOKEN) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }

$days = max(1, min(60, intval($_GET['days'] ?? '7')));
$from = $_GET['from'] ?? date('Y-m-d');
$timesParam = $_GET['times'] ?? '12:00-13:00,14:00-15:00';
$times = array_filter(array_map('trim', explode(',', $timesParam)));

$db = db_connect();

// Ensure quest
$questId = null;
$resQ = $db->query("SELECT id FROM quests ORDER BY id ASC LIMIT 1");
if ($resQ && ($rowQ = $resQ->fetch_assoc())) {
    $questId = (int)$rowQ['id'];
} else {
    $stmt = $db->prepare('INSERT INTO quests (title, description, price, duration_minutes, difficulty) VALUES (?,?,?,?,?)');
    $title = 'Тайна Джокера';
    $desc = 'Демо квест';
    $price = 49.99; $dur = 60; $diff = 'medium';
    $stmt->bind_param('ssdis', $title, $desc, $price, $dur, $diff);
    $stmt->execute();
    $questId = $stmt->insert_id;
    $stmt->close();
}

$stmt = $db->prepare('INSERT IGNORE INTO time_slots (quest_id, start_at, end_at, is_booked) VALUES (?, ?, ?, 0)');

for ($i = 0; $i < $days; $i++) {
    $date = date('Y-m-d', strtotime($from . ' +' . $i . ' day'));
    foreach ($times as $t) {
        if (!preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $t, $m)) continue;
        $start = $date . ' ' . $m[1] . ':00';
        $end   = $date . ' ' . $m[2] . ':00';
        $stmt->bind_param('iss', $questId, $start, $end);
        $stmt->execute();
    }
}

echo json_encode([ 'ok' => true, 'questId' => $questId ]);


