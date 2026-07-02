<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION['role'] !== 'admin' && !(isset($_SESSION['can_dashboard']) && $_SESSION['can_dashboard'])) {
    header("Location: index.php");
    exit;
}

// Handle Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $meeting_id = $_POST['meeting_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $status = ($action === 'approve') ? 'approved' : 'rejected';

    $stmt = $pdo->prepare("UPDATE meetings SET status = ? WHERE id = ?");
    $stmt->execute([$status, $meeting_id]);
    
    header("Location: approval.php?msg=" . $status);
    exit;
}

$current_branch = getCurrentBranchId();
$and_branch = $current_branch > 0 ? " AND m.branch_id = $current_branch " : "";

$stmt = $pdo->query("
    SELECT m.*, u.name as creator_name, u.division as creator_division 
    FROM meetings m 
    JOIN users u ON m.created_by = u.id 
    WHERE m.status = 'pending' $and_branch
    ORDER BY m.scheduled_time ASC
");
$pending_meetings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Approval Meeting - Indoarsip</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 15px; border-bottom: 1px solid var(--border-color); text-align: left; }
        .table th { background: #f8f9fa; font-weight: 700; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.025em; }
        .btn-approve { background: #10b981; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
        .btn-reject { background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
        .creator-info { font-size: 0.8rem; color: #64748b; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <div class="main-wrapper">
            <?php include 'topbar.php'; ?>
            <main class="content">
                <h1 class="page-title">Approval Meeting</h1>
                <p class="page-subtitle">Setujui atau tolak permintaan pembuatan jadwal meeting dari divisi</p>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Meeting</th>
                                    <th>Waktu & Tempat</th>
                                    <th>Diajukan Oleh</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pending_meetings)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                                            <div style="background: #f8fafc; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                                <i class="fa-solid fa-clipboard-check" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                                            </div>
                                            <h3 style="color: #475569; margin-bottom: 8px;">Semua Beres!</h3>
                                            <p style="font-size: 0.9rem;">Tidak ada permintaan meeting yang perlu disetujui saat ini.</p>
                                        </td>
                                    </tr>
                                <?php else: foreach ($pending_meetings as $m): ?>
                                    <tr>
                                        <td style="padding: 20px 15px;">
                                            <strong style="display: block; color: #1e293b; font-size: 1rem; margin-bottom: 4px;"><?= htmlspecialchars($m['title']) ?></strong>
                                            <span style="font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em; font-weight: 600;">Pengajuan Baru</span>
                                        </td>
                                        <td style="padding: 20px 15px;">
                                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                                <div style="font-size: 0.9rem; font-weight: 600; color: #334155;">
                                                    <i class="fa-solid fa-calendar-day" style="width: 18px; color: var(--primary-color);"></i> <?= date('d M Y', strtotime($m['scheduled_time'])) ?>
                                                </div>
                                                <div style="font-size: 0.85rem; color: #64748b;">
                                                    <i class="fa-solid fa-clock" style="width: 18px; color: #94a3b8;"></i> <?= date('H:i', strtotime($m['scheduled_time'])) ?> - <?= date('H:i', strtotime($m['end_time'])) ?>
                                                </div>
                                                <div style="font-size: 0.85rem; color: #64748b;">
                                                    <i class="fa-solid fa-location-dot" style="width: 18px; color: #94a3b8;"></i> <?= htmlspecialchars($m['room']) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 20px 15px;">
                                            <div style="font-weight: 600; color: #1e293b; font-size: 0.95rem;"><?= htmlspecialchars($m['creator_name']) ?></div>
                                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;"><?= htmlspecialchars($m['creator_division']) ?></div>
                                        </td>
                                        <td style="padding: 20px 15px;">
                                            <div style="display: flex; gap: 10px;">
                                                <form method="POST" onsubmit="confirmApproval(event, 'approve')" style="margin:0;">
                                                    <input type="hidden" name="meeting_id" value="<?= $m['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn-approve" style="display: flex; align-items: center; gap: 6px; padding: 8px 16px;">
                                                        <i class="fa-solid fa-check-circle"></i> Setujui
                                                    </button>
                                                </form>
                                                <form method="POST" onsubmit="confirmApproval(event, 'reject')" style="margin:0;">
                                                    <input type="hidden" name="meeting_id" value="<?= $m['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn-reject" style="display: flex; align-items: center; gap: 6px; padding: 8px 16px; background: #fee2e2; color: #ef4444; border: 1px solid #fecaca;">
                                                        <i class="fa-solid fa-times-circle"></i> Tolak
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script>
        function confirmApproval(event, action) {
            event.preventDefault();
            const form = event.target;
            const title = action === 'approve' ? 'Setujui Meeting?' : 'Tolak Meeting?';
            const text = action === 'approve' ? 'Jadwal akan resmi terdaftar dan dapat diakses peserta.' : 'Jadwal akan dibatalkan.';
            const confirmBtn = action === 'approve' ? '#10b981' : '#ef4444';

            Swal.fire({
                title: title,
                text: text,
                icon: action === 'approve' ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonColor: confirmBtn,
                cancelButtonColor: '#94a3b8',
                confirmButtonText: action === 'approve' ? 'Ya, Setujui!' : 'Ya, Tolak!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        <?php if (isset($_GET['msg'])): ?>
            Toast.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Meeting telah <?= $_GET['msg'] === 'approved' ? 'disetujui' : 'ditolak' ?>.'
            });
        <?php endif; ?>
    </script>
</body>
</html>
