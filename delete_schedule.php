<?php
session_start();
require_once 'database.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meeting_id = $_POST['meeting_id'] ?? null;
    $user_id = $_SESSION['user_id'];
    $is_admin = ($_SESSION['role'] === 'admin' || (isset($_SESSION['can_dashboard']) && $_SESSION['can_dashboard']));

    if ($meeting_id) {
        // PIC or Admin can delete
        $stmt_check = $pdo->prepare("SELECT created_by FROM meetings WHERE id = ?");
        $stmt_check->execute([$meeting_id]);
        $meeting = $stmt_check->fetch();

        if ($meeting && ($meeting['created_by'] == $user_id || $is_admin)) {
            try {
                $pdo->beginTransaction();
                
                // Delete child records first
                $pdo->prepare("DELETE FROM meeting_feedbacks WHERE meeting_id = ?")->execute([$meeting_id]);
                $pdo->prepare("DELETE FROM attendances WHERE meeting_id = ?")->execute([$meeting_id]);
                $pdo->prepare("DELETE FROM meeting_participants WHERE meeting_id = ?")->execute([$meeting_id]);
                
                // Delete parent record
                $pdo->prepare("DELETE FROM meetings WHERE id = ?")->execute([$meeting_id]);
                
                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                die("Gagal menghapus jadwal: " . $e->getMessage());
            }
        }
    }
}

// Redirect back to report page
header("Location: report.php");
exit;
?>
