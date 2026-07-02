<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meeting_id = $_POST['meeting_id'] ?? null;
    
    if ($meeting_id) {
        try {
            $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || (isset($_SESSION['can_dashboard']) && $_SESSION['can_dashboard']);
            $is_hr = (isset($_SESSION['division']) && $_SESSION['division'] === 'HR');
            $user_id = (int)$_SESSION['user_id'];
            
            $stmt = $pdo->prepare("SELECT created_by, pic_id, status FROM meetings WHERE id = ?");
            $stmt->execute([$meeting_id]);
            $meeting = $stmt->fetch();
            
            if (!$meeting) {
                header("Location: report.php?error=" . urlencode("Meeting tidak ditemukan."));
                exit;
            }

            // ADMIN & HR ALWAYS HAVE ACCESS
            $has_access = ($is_admin || $is_hr);
            
            // Owners and PICs also have access
            if (!$has_access) {
                $is_owner = ((int)$meeting['created_by'] === $user_id);
                $is_assigned_pic = ($meeting['pic_id'] && (int)$meeting['pic_id'] === $user_id);
                if ($is_owner || $is_assigned_pic) {
                    $has_access = true;
                }
            }

            if ($has_access) {
                // FORCE END: Set time to past and status to finished
                $finished_time = date('Y-m-d H:i:s', time() - 10); // 10s ago for safety
                $update = $pdo->prepare("UPDATE meetings SET end_time = ?, status = 'finished' WHERE id = ?");
                $update->execute([$finished_time, $meeting_id]);
                
                header("Location: report.php?end_success=1");
                exit;
            } else {
                header("Location: report.php?error=" . urlencode("Akses ditolak: Anda tidak memiliki wewenang untuk mengakhiri meeting ini."));
                exit;
            }
        } catch (Exception $e) {
            header("Location: report.php?error=" . urlencode("Database Error: " . $e->getMessage()));
            exit;
        }
    }
}

header("Location: report.php");
exit;
?>
