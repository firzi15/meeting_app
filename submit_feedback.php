<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meeting_id = $_POST['meeting_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    if (!$meeting_id) {
        echo json_encode(['success' => false, 'message' => 'Meeting ID required']);
        exit;
    }

    // Check if user is PIC
    $stmt_pic = $pdo->prepare("SELECT pic_id FROM meetings WHERE id = ?");
    $stmt_pic->execute([$meeting_id]);
    $meeting = $stmt_pic->fetch();

    if (!$meeting || ($meeting['pic_id'] != $user_id && $_SESSION['role'] !== 'admin')) {
        echo json_encode(['success' => false, 'message' => 'Hanya PIC yang dapat memberikan feedback.']);
        exit;
    }
    $q1 = (int)($_POST['q1_rating'] ?? 0);
    $q2 = (int)($_POST['q2_rating'] ?? 0);
    $q3 = (int)($_POST['q3_rating'] ?? 0);
    $q4 = (int)($_POST['q4_rating'] ?? 0);
    $feedback_text = $_POST['feedback_text'] ?? '';
    $user_id = $_SESSION['user_id'];

    if (!$meeting_id || !$feedback_text) {
        echo json_encode(['success' => false, 'message' => 'Semua pertanyaan wajib diisi.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO meeting_feedbacks (meeting_id, user_id, q1_rating, q2_rating, q3_rating, q4_rating, feedback_text) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$meeting_id, $user_id, $q1, $q2, $q3, $q4, $feedback_text]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // If unique constraint violation
        if ($e->getCode() == '23505') {
            echo json_encode(['success' => false, 'message' => 'Anda sudah memberikan feedback untuk meeting ini.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan feedback: ' . $e->getMessage()]);
        }
    }
}
?>
