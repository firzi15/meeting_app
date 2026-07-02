<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $current_branch = getCurrentBranchId();
    $branch_condition = $current_branch > 0 ? "AND branch_id = $current_branch" : "";
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM meeting_templates WHERE id = ? $branch_condition");
        $stmt->execute([$id]);
        $template = $stmt->fetch();
        
        if ($template) {
            echo json_encode([
                'success' => true,
                'title' => $template['title'],
                'pic_id' => $template['pic_id'],
                'participants' => json_decode($template['participants'], true)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Template tidak ditemukan.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
