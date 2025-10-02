<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $db = db_connect();
    // Ensure there are slots for the next 30 days; if none exist, create defaults
    $needDays = 30;
    $check = $db->query("SELECT COUNT(*) AS cnt FROM time_slots WHERE start_at >= CURDATE() AND start_at < DATE_ADD(CURDATE(), INTERVAL $needDays DAY)");
    $row = $check ? $check->fetch_assoc() : ['cnt' => 0];
    if ((int)($row['cnt'] ?? 0) === 0) {
        // Ensure a quest exists
        $questId = null;
        $resQ = $db->query("SELECT id FROM quests ORDER BY id ASC LIMIT 1");
        if ($resQ && ($rowQ = $resQ->fetch_assoc())) {
            $questId = (int)$rowQ['id'];
        } else {
            $stmtQ = $db->prepare('INSERT INTO quests (title, description, price, duration_minutes, difficulty) VALUES (?,?,?,?,?)');
            $title = 'Тайна Джокера'; $desc = 'Автогенерация слотов'; $price = 49.99; $dur = 60; $diff = 'medium';
            $stmtQ->bind_param('ssdis', $title, $desc, $price, $dur, $diff);
            $stmtQ->execute();
            $questId = $stmtQ->insert_id; $stmtQ->close();
        }
        if ($questId) {
            $times = [ ['12:00:00','13:00:00'], ['14:00:00','15:00:00'] ];
            $stmt = $db->prepare('INSERT IGNORE INTO time_slots (quest_id, start_at, end_at, is_booked) VALUES (?, ?, ?, 0)');
            for ($i = 0; $i < $needDays; $i++) {
                $date = new DateTime('today +' . $i . ' day');
                foreach ($times as $t) {
                    $start = $date->format('Y-m-d') . ' ' . $t[0];
                    $end   = $date->format('Y-m-d') . ' ' . $t[1];
                    $stmt->bind_param('iss', $questId, $start, $end);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
    }

    // Read upcoming slots for the next 14 days for UI
    $res = $db->query("SELECT ts.id, ts.quest_id, ts.start_at, ts.end_at, ts.is_booked, q.title
                       FROM time_slots ts JOIN quests q ON q.id = ts.quest_id
                       WHERE ts.start_at >= CURDATE() AND ts.start_at < DATE_ADD(CURDATE(), INTERVAL 14 DAY)
                       ORDER BY ts.start_at ASC");
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'questId' => (int)$row['quest_id'],
            'title' => $row['title'],
            'start' => $row['start_at'],
            'end' => $row['end_at'],
            'booked' => (int)$row['is_booked'] === 1,
        ];
    }
    respond([ 'ok' => true, 'items' => $items ]);
}

if ($method === 'POST') {
    require_auth_session();
    $data = json_body();
    require_fields($data, ['timeSlotId']);
    $slotId = (int)$data['timeSlotId'];

    $db = db_connect();
    $db->begin_transaction();
    try {
        // Lock slot row
        $stmt = $db->prepare('SELECT id, quest_id, is_booked FROM time_slots WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $slotId);
        $stmt->execute();
        $stmt->bind_result($id, $questId, $booked);
        if (!$stmt->fetch()) { throw new Exception('Slot not found'); }
        $stmt->close();
        if ((int)$booked === 1) { throw new Exception('Slot already booked'); }

        // find user id
        $phone = current_user_phone();
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $stmt->bind_result($uid);
        if (!$stmt->fetch()) { throw new Exception('User not found'); }
        $stmt->close();

        // create booking
        $status = 'confirmed';
        $stmt = $db->prepare('INSERT INTO bookings (user_id, quest_id, time_slot_id, customer_name, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iiiss', $uid, $questId, $slotId, $phone, $status);
        if (!$stmt->execute()) { throw new Exception('Booking insert failed'); }
        $stmt->close();

        // mark slot booked
        $one = 1;
        $stmt = $db->prepare('UPDATE time_slots SET is_booked = ? WHERE id = ?');
        $stmt->bind_param('ii', $one, $slotId);
        if (!$stmt->execute()) { throw new Exception('Slot update failed'); }
        $stmt->close();

        $db->commit();
        respond([ 'ok' => true ]);
    } catch (Exception $e) {
        $db->rollback();
        respond([ 'ok' => false, 'error' => $e->getMessage() ], 400);
    }
}

respond([ 'ok' => false, 'error' => 'Not found' ], 404);


