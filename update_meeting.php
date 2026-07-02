<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    $title = $_POST['title'] ?? '';
    $room = $_POST['room'] ?? '';
    $late_tolerance = $_POST['late_tolerance'] ?? 15;
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $pic_id_raw = $_POST['pic_id'] ?? '';
    $pic_id = is_array($pic_id_raw) ? ($pic_id_raw[0] ?? '') : $pic_id_raw;
    $participants = $_POST['participants'] ?? [];

    $has_snack = isset($_POST['has_snack']) ? 1 : 0;
    $has_coffee = isset($_POST['has_coffee']) ? 1 : 0;
    $coffee_temp = $_POST['coffee_temp'] ?? null;
    $coffee_type = $_POST['coffee_type'] ?? null;
    $is_hybrid_zoom = isset($_POST['is_hybrid_zoom']) ? 1 : 0;

    if ($title && $room && $date && $time && $end_time && $pic_id) {
        $scheduled_time = $date . ' ' . $time . ':00';
        $scheduled_end_time = $date . ' ' . $end_time . ':00';

        // Duration check: must be a multiple of 1 hour (at least 1 hour)
        $start_ts = strtotime($scheduled_time);
        $end_ts = strtotime($scheduled_end_time);
        $duration = $end_ts - $start_ts;
        
        if ($duration <= 0 || $duration % 3600 !== 0) {
            echo json_encode(['success' => false, 'message' => 'Durasi meeting harus kelipatan 1 jam (misal: 1 jam, 2 jam, dst).']);
            exit;
        }
        
        if (date('i', $start_ts) !== '00' || date('i', $end_ts) !== '00') {
            echo json_encode(['success' => false, 'message' => 'Waktu mulai dan selesai meeting harus tepat pada kelipatan jam (menit 00, misal: 14:00 - 15:00).']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Check double booking (exclude current meeting)
            $stmt_check = $pdo->prepare("
                SELECT COUNT(*) FROM meetings 
                WHERE room = ? AND id != ? AND status != 'rejected'
                AND (
                    (scheduled_time < ? AND end_time > ?)
                )
            ");
            $stmt_check->execute([$room, $id, $scheduled_end_time, $scheduled_time]);
            if ($stmt_check->fetchColumn() > 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Ruangan sudah dipesan untuk waktu tersebut.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE meetings SET title = ?, room = ?, scheduled_time = ?, end_time = ?, late_tolerance = ?, pic_id = ?, has_snack = ?, has_coffee = ?, coffee_temp = ?, coffee_type = ?, is_hybrid_zoom = ? WHERE id = ?");
            $stmt->execute([$title, $room, $scheduled_time, $scheduled_end_time, $late_tolerance, $pic_id, $has_snack, $has_coffee, $coffee_temp, $coffee_type, $is_hybrid_zoom, $id]);

            // Update participants
            $pdo->prepare("DELETE FROM meeting_participants WHERE meeting_id = ?")->execute([$id]);
            
            $stmt_part = $pdo->prepare("INSERT INTO meeting_participants (meeting_id, user_id) VALUES (?, ?)");
            $stmt_part->execute([$id, $pic_id]);

            if (!empty($participants)) {
                foreach ($participants as $uid) {
                    if ($uid != $pic_id) {
                        $stmt_part->execute([$id, $uid]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan jadwal: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    }
}
