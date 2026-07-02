<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
    $stmt->execute([$id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$meeting) {
        echo json_encode(['success' => false, 'message' => 'Meeting tidak ditemukan']);
        exit;
    }

    $stmt_part = $pdo->prepare("SELECT user_id FROM meeting_participants WHERE meeting_id = ? AND user_id != ?");
    $stmt_part->execute([$id, $meeting['pic_id']]);
    $participants = $stmt_part->fetchAll(PDO::FETCH_COLUMN);

    $meeting['participants'] = $participants;
    
    // Formatting date and time
    $meeting['date'] = date('Y-m-d', strtotime($meeting['scheduled_time']));
    $meeting['time'] = date('H:i', strtotime($meeting['scheduled_time']));
    $meeting['end_time_formatted'] = date('H:i', strtotime($meeting['end_time']));

    echo json_encode(['success' => true, 'data' => $meeting]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
