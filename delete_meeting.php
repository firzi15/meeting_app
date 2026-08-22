<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin' && empty($_SESSION['can_dashboard']))) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

try {
    $pdo->prepare("DELETE FROM meeting_participants WHERE meeting_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM attendances WHERE meeting_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM meeting_feedbacks WHERE meeting_id = ?")->execute([$id]);

    // Delete the meeting itself
    $stmt = $pdo->prepare("DELETE FROM meetings WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Meeting tidak ditemukan']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
}
