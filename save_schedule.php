<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// All meetings are auto-approved
$status = 'approved';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if (!$title || !$room || !$date || !$time || !$end_time || !$pic_id || !is_numeric($late_tolerance)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
        exit;
    }

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
    
    $token = bin2hex(random_bytes(16));

    try {
        $pdo->beginTransaction();

        // Check for double booking (bypassed if room is 'Online')
        if (strtolower($room) !== 'online') {
            $stmt_check = $pdo->prepare("
                SELECT COUNT(*) FROM meetings 
                WHERE room = ? 
                AND status != 'rejected'
                AND (
                    (scheduled_time < ? AND end_time > ?)
                )
            ");
            $stmt_check->execute([$room, $scheduled_end_time, $scheduled_time]);
            if ($stmt_check->fetchColumn() > 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Ruangan sudah dipesan untuk waktu tersebut.']);
                exit;
            }
        }

        // Check participant conflict (single session per user at any time)
        $all_target_users = array_unique(array_filter(array_merge([$pic_id], (array)$participants)));
        if (!empty($all_target_users)) {
            $placeholders = implode(',', array_fill(0, count($all_target_users), '?'));
            $conflict_sql = "
                SELECT m.title, m.scheduled_time, m.end_time, u.name as user_name
                FROM meetings m
                JOIN meeting_participants mp ON m.id = mp.meeting_id
                JOIN users u ON u.id = mp.user_id
                WHERE mp.user_id IN ($placeholders)
                  AND m.status != 'rejected'
                  AND m.status != 'finished'
                  AND (m.scheduled_time < ? AND m.end_time > ?)
            ";
            $conflict_params = array_merge($all_target_users, [$scheduled_end_time, $scheduled_time]);
            $stmt_conflict = $pdo->prepare($conflict_sql);
            $stmt_conflict->execute($conflict_params);
            $conflicts = $stmt_conflict->fetchAll();

            if (!empty($conflicts)) {
                $pdo->rollBack();
                $conflict_names = array_unique(array_column($conflicts, 'user_name'));
                $first_c = $conflicts[0];
                $c_time = date('H:i', strtotime($first_c['scheduled_time'])) . ' - ' . date('H:i', strtotime($first_c['end_time']));
                
                echo json_encode([
                    'success' => false,
                    'message' => "Konflik Jadwal: Peserta '" . implode(', ', $conflict_names) . "' sudah terdaftar pada meeting lain di waktu yang sama ('" . $first_c['title'] . "' [" . $c_time . "]). Setiap karyawan hanya dapat berada di 1 sesi meeting dalam waktu yang sama."
                ]);
                exit;
            }
        }

        $current_branch = getCurrentBranchId();
        $insert_branch = $current_branch > 0 ? $current_branch : 1;

        $stmt = $pdo->prepare("INSERT INTO meetings (title, room, scheduled_time, end_time, late_tolerance, token, created_by, pic_id, status, branch_id, has_snack, has_coffee, coffee_temp, coffee_type, is_hybrid_zoom) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
        $stmt->execute([$title, $room, $scheduled_time, $scheduled_end_time, $late_tolerance, $token, $_SESSION['user_id'], $pic_id, $status, $insert_branch, $has_snack, $has_coffee, $coffee_temp, $coffee_type, $is_hybrid_zoom]);
        
        $meeting_id = $stmt->fetchColumn();

        // Add PIC as participant automatically
        $stmt_part = $pdo->prepare("INSERT INTO meeting_participants (meeting_id, user_id) VALUES (?, ?)");
        $stmt_part->execute([$meeting_id, $pic_id]);

        if (!empty($participants)) {
            foreach ($participants as $uid) {
                // Ensure we don't duplicate PIC if they were somehow selected
                if ($uid != $pic_id) {
                    $stmt_part->execute([$meeting_id, $uid]);
                }
            }
        }

        $pdo->commit();
        
        $stmt_rid = $pdo->prepare("SELECT id FROM rooms WHERE name = ?");
        $stmt_rid->execute([$room]);
        $room_id_db = $stmt_rid->fetchColumn();
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $attendance_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . $basePath . "/attendance.php?room_id=" . $room_id_db;
        
        echo json_encode([
            'success' => true, 
            'link' => $attendance_link,
            'title' => $title,
            'room' => $room
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan jadwal: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
