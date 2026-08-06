<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$has_dashboard = (isset($_SESSION['can_dashboard']) && $_SESSION['can_dashboard']) || ($_SESSION['role'] === 'admin');
$is_admin = $has_dashboard;
$is_hr = (isset($_SESSION['division']) && $_SESSION['division'] === 'HR');

// Handle Save PDF Link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pdf_link'])) {
    try {
        $meeting_id = $_POST['meeting_id'];
        $pdf_link = $_POST['pdf_link'] ?? '';
        $stmt = $pdo->prepare("UPDATE meetings SET pdf_link = ? WHERE id = ?");
        $stmt->execute([$pdf_link, $meeting_id]);
        header("Location: report.php?saved=success");
        exit;
    } catch (Exception $e) {
        die("Gagal menyimpan link PDF: " . $e->getMessage());
    }
}

// Handle Ad-hoc Invite from Report Detail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_adhoc'])) {
    try {
        $meeting_id = $_POST['meeting_id'];
        $invited_user_id = $_POST['user_to_invite'];
        $pdo->beginTransaction();
        
        $stmt_invite = $pdo->prepare("INSERT INTO meeting_participants (meeting_id, user_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
        $stmt_invite->execute([$meeting_id, $invited_user_id]);
        
        // Auto-absent with status 'Dadakan' if not already checked in
        $stmt_check_att = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE meeting_id = ? AND user_id = ?");
        $stmt_check_att->execute([$meeting_id, $invited_user_id]);
        if ($stmt_check_att->fetchColumn() == 0) {
            $waktu_absen_db = date('Y-m-d H:i:s');
            $stmt_auto_absen = $pdo->prepare("INSERT INTO attendances (meeting_id, user_id, check_in_time, status, late_reason) VALUES (?, ?, ?, ?, ?)");
            $stmt_auto_absen->execute([$meeting_id, $invited_user_id, $waktu_absen_db, 'Dadakan', 'Peserta Dadakan']);
        }
        
        $pdo->commit();
        header("Location: report.php?id=" . $meeting_id . "&invited=success");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Gagal mengundang peserta: " . $e->getMessage());
    }
}

// Handle Add External Guest
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_external_guest'])) {
    try {
        $meeting_id = $_POST['meeting_id'];
        $guest_name = trim(strip_tags($_POST['guest_name']));
        $guest_institution = trim(strip_tags($_POST['guest_institution']));
        
        if ($guest_name) {
            $stmt = $pdo->prepare("INSERT INTO external_guests (meeting_id, name, institution) VALUES (?, ?, ?)");
            $stmt->execute([$meeting_id, $guest_name, $guest_institution]);
            header("Location: report.php?id=" . $meeting_id . "&guest=added");
            exit;
        }
    } catch (Exception $e) {
        die("Gagal menambahkan tamu eksternal: " . $e->getMessage());
    }
}

// Handle Delete External Guest
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_external_guest'])) {
    try {
        $meeting_id = $_POST['meeting_id'];
        $guest_id = $_POST['guest_id'];
        
        $stmt = $pdo->prepare("DELETE FROM external_guests WHERE id = ? AND meeting_id = ?");
        $stmt->execute([$guest_id, $meeting_id]);
        header("Location: report.php?id=" . $meeting_id . "&guest=deleted");
        exit;
    } catch (Exception $e) {
        die("Gagal menghapus tamu eksternal: " . $e->getMessage());
    }
}

// Bulk Delete Meetings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete_meeting'])) {
    $ids = $_POST['bulk_ids'] ?? [];
    if (!empty($ids)) {
        try {
            // Need to verify permissions before deleting
            if ($is_admin || $is_hr) {
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM meetings WHERE id IN ($inQuery)");
                $stmt->execute($ids);
            } else {
                // If not admin/hr, only delete own meetings
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $params = array_merge([$_SESSION['user_id']], $ids);
                $stmt = $pdo->prepare("DELETE FROM meetings WHERE created_by = ? AND id IN ($inQuery)");
                $stmt->execute($params);
            }
            header("Location: report.php");
            exit;
        } catch (Exception $e) {
            die("Gagal menghapus meeting: " . $e->getMessage());
        }
    }
}

$user_id = $_SESSION['user_id'];
$room_filter = $_GET['room'] ?? '';
$detail_id = $_GET['id'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Absensi - Indoarsip</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); text-align: left; }
        .table th { background: #f8f9fa; font-weight: 600; color: #555; }
        .table tr:hover { background: #fdfdfd; }
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .btn-view { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .btn-view:hover { text-decoration: underline; }
        .btn-excel { 
            background-color: #10b981; 
            color: white; 
            padding: 8px 16px; 
            border-radius: 8px; 
            text-decoration: none !important; 
            font-weight: 600; 
            font-size: 0.9rem; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            transition: all 0.2s; 
            border: none; 
            cursor: pointer; 
        }
        .btn-excel:hover { background-color: #059669; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <div class="main-wrapper">
            <?php include 'topbar.php'; ?>

            <main class="content">
                <?php if (!$detail_id): ?>
                    <!-- LIST MEETINGS -->
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <h1 class="page-title">Daftar Meeting</h1>
                            <p class="page-subtitle">Riwayat pertemuan dan rekap absensi</p>
                        </div>
                        <div style="display:flex; gap:12px; align-items:center;">
                            <button type="submit" form="bulkDeleteForm" name="bulk_delete_meeting" class="btn-submit" id="btnBulkDelete" style="background:#ef4444; display: none;">
                                <i class="fa-solid fa-trash" style="margin-right: 8px;"></i> Hapus Terpilih
                            </button>
                            
                            <?php if ($is_admin || (isset($_SESSION['can_export']) && $_SESSION['can_export'])): ?>
                            <button type="button" class="btn-submit" style="background:#10b981; border:none; padding: 12px 20px; border-radius: 12px;" onclick="exportWithHR(event, 'summary')">
                                <i class="fa-solid fa-file-excel" style="margin-right: 8px;"></i> Ekspor Excel
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card">
                    <div style="display:flex; justify-content:flex-end; align-items:center; margin-bottom:20px;">
                        <form method="GET" style="display:flex; gap:10px; align-items:center;">
                            <input type="date" name="date" class="form-control" style="width:180px; border-radius:12px; height: 48px;" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>" onchange="this.form.submit()">
                                <select name="room" id="roomFilter" class="form-control" style="width:250px; border-radius: 12px;" onchange="this.form.submit()">
                                    <option value="">-- Semua Ruangan --</option>
                                    <?php
                                    $current_branch = getCurrentBranchId();
                                    $branch_condition = $current_branch > 0 ? "WHERE branch_id = $current_branch" : "";
                                    $stmt_rooms = $pdo->query("SELECT name FROM rooms $branch_condition ORDER BY name ASC");
                                    while($r = $stmt_rooms->fetch()) {
                                        $sel = ($room_filter === $r['name']) ? 'selected' : '';
                                        echo "<option value=\"".htmlspecialchars($r['name'])."\" $sel>".htmlspecialchars($r['name'])."</option>";
                                    }
                                    ?>
                                </select>
                            </form>
                        </div>

                    <form id="bulkDeleteForm" method="POST" onsubmit="return confirmWithSweetAlert(event, 'bulkDeleteForm', 'bulk_delete_meeting', 'Hapus semua meeting yang dipilih?');">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No.</th>
                                    <th>Tanggal</th>
                                    <th>Judul Meeting</th>
                                    <th>Ruang</th>
                                    <th>Jam</th>
                                    <th style="text-align: center;">Akses Absen</th>
                                    <th style="width: 130px; text-align: center;">Rekap</th>
                                    <th style="width: 130px; text-align: center;">Summary</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $limit = 10;
                                $room_filter = $_GET['room'] ?? '';
                                $date_filter = $_GET['date'] ?? '';
                                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                $offset = ($page - 1) * $limit;

                                // 1. Build filters with table alias
                                $and_branch = isset($current_branch) && $current_branch > 0 ? " AND meetings.branch_id = $current_branch " : "";
                                $where = " WHERE meetings.id IS NOT NULL $and_branch ";
                                $params = [];
                                
                                if (!$is_admin && !$is_hr) {
                                    $where .= " AND meetings.created_by = ? ";
                                    $params[] = $user_id;
                                }

                                if ($room_filter) {
                                    $where .= " AND meetings.room = ? ";
                                    $params[] = $room_filter;
                                }
                                if ($date_filter) {
                                    $where .= " AND DATE(meetings.scheduled_time) = ? ";
                                    $params[] = $date_filter;
                                }

                                // Count total
                                $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM meetings" . $where);
                                $stmt_count->execute($params);
                                $total_rows = $stmt_count->fetchColumn();
                                $total_pages = ceil($total_rows / $limit);

                                // Fetch meetings with their room IDs - Ordered by ID DESC to show newly created first
                                $stmt = $pdo->prepare("SELECT meetings.*, rooms.id as room_id FROM meetings JOIN rooms ON rooms.name = meetings.room AND rooms.branch_id = meetings.branch_id " . $where . " ORDER BY meetings.id DESC LIMIT $limit OFFSET $offset");
                                $stmt->execute($params);
                                $meetings = $stmt->fetchAll();

                                if (count($meetings) === 0): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <i class="fa-solid fa-calendar-xmark" style="display: block; font-size: 2rem; margin-bottom: 10px;"></i>
                                            Belum ada data tersedia
                                        </td>
                                    </tr>
                                <?php else: 
                                    $no = $offset + 1;
                                    foreach ($meetings as $m):
                                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                                        $link = $protocol . "://" . $_SERVER['HTTPS_HOST'] . $basePath . "/attendance.php?room_id=" . $m['room_id'];
                                ?>
                                <?php 
                                    $now_ts = time();
                                    $end_ts = strtotime($m['end_time']);
                                    $is_finished = ($m['status'] === 'finished' || $now_ts >= $end_ts);
                                ?>
                                <tr class="selectable-row">
                                    <td style="color: #94a3b8; font-weight: 500; text-align: center;">
                                        <input type="checkbox" name="bulk_ids[]" value="<?= $m['id'] ?>" class="row-checkbox" style="display:none;">
                                        <?= $no++ ?>
                                    </td>
                                    <td><span style="white-space: nowrap; font-weight: 500; color: #475569;"><?= date('d M Y', strtotime($m['scheduled_time'])) ?></span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($m['title']) ?></strong>
                                        <div style="margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap;">
                                            <?php if (isset($m['has_snack']) && $m['has_snack']): ?>
                                                <span style="font-size: 0.7rem; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-weight: 600;"><i class="fa-solid fa-cookie"></i> Snack</span>
                                            <?php endif; ?>
                                            <?php if (isset($m['has_coffee']) && $m['has_coffee']): ?>
                                                <span style="font-size: 0.7rem; background: #fef3c7; color: #b45309; padding: 2px 6px; border-radius: 4px; font-weight: 600;"><i class="fa-solid fa-coffee"></i> Kopi (<?= ucfirst($m['coffee_temp'] ?? '') ?> - <?= ($m['coffee_type'] ?? '') === 'bikin' ? 'Bikin' : 'Beli' ?>)</span>
                                            <?php endif; ?>
                                            <?php if (isset($m['is_hybrid_zoom']) && $m['is_hybrid_zoom']): ?>
                                                <span style="font-size: 0.7rem; background: #dcfce7; color: #15803d; padding: 2px 6px; border-radius: 4px; font-weight: 600;"><i class="fa-solid fa-video"></i> Hybrid Zoom</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($m['room']) ?></td>
                                    <td>
                                        <span style="white-space: nowrap;"><?= date('H:i', strtotime($m['scheduled_time'])) ?> - <?= date('H:i', strtotime($m['end_time'])) ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($m['status'] === 'pending'): ?>
                                            <button class="btn-action-text" style="background:#f1f5f9; color:#94a3b8; cursor:not-allowed; border-radius:6px; padding:6px 12px;" disabled title="Menunggu Approval">Pending</button>
                                        <?php else: ?>
                                            <button type="button" class="btn-action-text" style="background:#3b82f6; color:white; border-radius:6px; padding:6px 12px;" onclick="showAccess('<?= $link ?>', '<?= htmlspecialchars(addslashes($m['title'])) ?>')" title="Lihat Akses Absen">
                                                <i class="fa-solid fa-eye" style="margin-right: 5px;"></i> Akses
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                     <td style="text-align: center; vertical-align: middle;">
                                         <?php if (!$is_finished): ?>
                                             <?php if ($m['status'] === 'pending'): ?>
                                                 <button type="button" class="btn-action-text" style="background:#f1f5f9; color:#94a3b8; cursor:not-allowed; border-radius:6px; padding:6px 12px;" disabled title="Belum disetujui">Akhiri</button>
                                             <?php else: ?>
                                                 <button type="button" class="btn-action-text" style="background:#f59e0b; color:white; border-radius:6px; padding:6px 12px;" onclick="confirmEnd(<?= $m['id'] ?>)">
                                                     <i class="fa-solid fa-stop" style="margin-right: 5px;"></i> Akhiri
                                                 </button>
                                             <?php endif; ?>
                                         <?php else: ?>
                                             <a href="report.php?id=<?= $m['id'] ?>" class="btn-action-text" style="background:#8b5cf6; color:white; border-radius:6px; padding:6px 12px; text-decoration:none; display: inline-flex; align-items: center; justify-content: center; width: 105px; box-sizing: border-box;">
                                                 <i class="fa-solid fa-chart-bar" style="margin-right: 5px;"></i> Rekap
                                             </a>
                                         <?php endif; ?>
                                     </td>
                                     <td style="text-align: center; vertical-align: middle;">
                                         <?php if ($is_finished): ?>
                                             <button type="button" class="btn-action-text btn-pdf-link" style="background:#0ea5e9; color:white; border-radius:6px; padding:6px 12px; border:none; cursor:pointer; font-weight:500; font-family:inherit; display: inline-flex; align-items: center; justify-content: center; width: 105px; box-sizing: border-box;" onclick="openPdfModal(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['pdf_link'] ?? '')) ?>')" title="Link Summary">
                                                 <i class="fa-solid fa-file-lines" style="margin-right: 5px;"></i> Summary
                                             </button>
                                         <?php else: ?>
                                             <span style="color:#94a3b8;">-</span>
                                         <?php endif; ?>
                                     </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="report.php?page=<?= $i ?><?= $room_filter ? '&room='.urlencode($room_filter) : '' ?>" class="page-link <?= ($page == $i) ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    </form>
                    
                    <!-- Hidden Form for End Meeting (To avoid nested form issue) -->
                    <form id="globalEndForm" action="end_meeting.php" method="POST" style="display:none;">
                        <input type="hidden" name="meeting_id" id="end_meeting_id">
                    </form>
                </div>
                <?php else: ?>
                    <!-- DETAIL MEETING -->
                    <?php
                    $stmt = $pdo->prepare("SELECT meetings.*, users.name as pic_name FROM meetings LEFT JOIN users ON users.id = meetings.pic_id WHERE meetings.id = ?");
                    $stmt->execute([$detail_id]);
                    $meeting = $stmt->fetch();
                    if (!$meeting) die("Meeting tidak ditemukan.");
                    
                    // Ownership check for non-admin/HR
                    if (!$is_admin && !$is_hr && $meeting['created_by'] != $user_id) {
                        die("Akses ditolak. Anda hanya bisa melihat laporan meeting yang Anda buat.");
                    }
                    ?>
                    <h1 class="page-title">Laporan Absen: <?= htmlspecialchars($meeting['title']) ?></h1>
                    <p class="page-subtitle">Jadwal: <?= date('d M Y, H:i', strtotime($meeting['scheduled_time'])) ?> - <?= date('H:i', strtotime($meeting['end_time'])) ?> | PIC: <?= htmlspecialchars($meeting['pic_name'] ?? '-') ?></p>
                    
                    <div style="margin-bottom: 20px; display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php if (isset($meeting['has_snack']) && $meeting['has_snack']): ?>
                            <span style="font-size: 0.8rem; background: #e0f2fe; color: #0369a1; padding: 6px 12px; border-radius: 6px; font-weight: 600;"><i class="fa-solid fa-cookie"></i> Snack Terjadwal</span>
                        <?php endif; ?>
                        <?php if (isset($meeting['has_coffee']) && $meeting['has_coffee']): ?>
                            <span style="font-size: 0.8rem; background: #fef3c7; color: #b45309; padding: 6px 12px; border-radius: 6px; font-weight: 600;"><i class="fa-solid fa-coffee"></i> Kopi: <?= ucfirst($meeting['coffee_temp'] ?? '') ?> (<?= ($meeting['coffee_type'] ?? '') === 'bikin' ? 'Bikin Sendiri' : 'Beli Luar' ?>)</span>
                        <?php endif; ?>
                        <?php if (isset($meeting['is_hybrid_zoom']) && $meeting['is_hybrid_zoom']): ?>
                            <span style="font-size: 0.8rem; background: #dcfce7; color: #15803d; padding: 6px 12px; border-radius: 6px; font-weight: 600;"><i class="fa-solid fa-video"></i> Hybrid Zoom</span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                        <a href="report.php" style="display:inline-flex; align-items:center; padding: 8px 16px; background-color: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: background 0.2s; border: 1px solid #e2e8f0;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                            Kembali ke Daftar
                        </a>
                        <div style="display:flex; gap:15px; align-items:center;">
                            <?php if ($is_admin || (isset($_SESSION['can_export']) && $_SESSION['can_export'])): ?>
                            <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; font-size:0.85rem; font-weight:600; color:#475569;">
                                <input type="checkbox" id="isForHRDetail" style="width:16px; height:16px; cursor:pointer;">
                                <label for="isForHRDetail" style="cursor:pointer;">is for HR?</label>
                            </div>
                            <a href="export_excel.php?type=detail&id=<?= $detail_id ?>" id="exportDetailBtn" class="btn-excel">
                                <i class="fa-solid fa-file-excel"></i> Export Detail Excel
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px; flex-wrap:wrap; gap:15px;">
                            <h4 style="margin:0;">Tabel Presensi</h4>
                            
                            <div style="display:flex; gap:15px; flex-wrap:wrap;">
                                <!-- Ad-hoc Invite Section -->
                                <div style="background:#f8fafc; padding:10px 15px; border-radius:10px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:10px;">
                                    <span style="font-size:0.85rem; font-weight:600; color:#475569;"><i class="fa-solid fa-user-plus"></i> Tambah Karyawan:</span>
                                    <form method="POST" style="display:flex; gap:8px; align-items:center;">
                                        <input type="hidden" name="meeting_id" value="<?= $detail_id ?>">
                                        <select name="user_to_invite" id="user_invite_select" style="width: 180px;">
                                            <option value=""></option>
                                            <?php
                                            $stmt_users = $pdo->prepare("SELECT id, name, division FROM users WHERE id NOT IN (SELECT user_id FROM meeting_participants WHERE meeting_id = ?) ORDER BY name ASC");
                                            $stmt_users->execute([$detail_id]);
                                            while ($u = $stmt_users->fetch()) {
                                                echo "<option value='{$u['id']}'>{$u['name']} ({$u['division']})</option>";
                                            }
                                            ?>
                                        </select>
                                        <button type="submit" name="invite_adhoc" class="btn-excel" style="padding: 0 15px; height: 36px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; background:#475569;"><i class="fa-solid fa-plus" style="margin-right: 5px;"></i> Tambah</button>
                                    </form>
                                </div>
                                
                                <!-- Add External Guest Section -->
                                <div style="background:#f8fafc; padding:10px 15px; border-radius:10px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:10px;">
                                    <span style="font-size:0.85rem; font-weight:600; color:#475569;"><i class="fa-solid fa-user-plus"></i> Tambah Tamu:</span>
                                    <form method="POST" style="display:flex; gap:8px; align-items:center;">
                                        <input type="hidden" name="meeting_id" value="<?= $detail_id ?>">
                                        <input type="text" name="guest_name" placeholder="Nama Tamu" required style="height: 36px; padding: 0 10px; box-sizing: border-box; border-radius:6px; border:1px solid #cbd5e1; font-size:0.8rem; width: 130px;">
                                        <input type="text" name="guest_institution" placeholder="Instansi" style="height: 36px; padding: 0 10px; box-sizing: border-box; border-radius:6px; border:1px solid #cbd5e1; font-size:0.8rem; width: 130px;">
                                        <button type="submit" name="add_external_guest" class="btn-excel" style="padding: 0 15px; height: 36px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; background:#10b981; border:none; color:white; border-radius:6px; cursor:pointer;"><i class="fa-solid fa-plus" style="margin-right: 5px;"></i> Tambah</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nama Peserta</th>
                                        <th>Status Absen</th>
                                        <th>Waktu Absen</th>
                                        <?php if ($is_hr || $is_admin): ?>
                                            <th>Alasan Telat</th>
                                            <th>Feedback</th>
                                        <?php endif; ?>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get all participants for this meeting
                                    $stmt_part = $pdo->prepare("SELECT users.id, users.name, users.is_owner FROM meeting_participants JOIN users ON users.id = meeting_participants.user_id WHERE meeting_id = ?");
                                    $stmt_part->execute([$detail_id]);
                                    $participants = $stmt_part->fetchAll();

                                    foreach ($participants as $p):
                                        // Cek apakah dia absen
                                        $stmt_absen = $pdo->prepare("SELECT * FROM attendances WHERE meeting_id = ? AND user_id = ?");
                                        $stmt_absen->execute([$detail_id, $p['id']]);
                                        $absen = $stmt_absen->fetch();
                                        
                                        if (isset($p['is_owner']) && $p['is_owner']) {
                                            $badge = '<span class="badge badge-success">Hadir (Tepat Waktu)</span>';
                                            $waktu = $absen ? date('d M Y, H:i:s', strtotime($absen['check_in_time'])) : 'Owner (Auto)';
                                        } else if ($absen) {
                                            $status = $absen['status'];
                                            $waktu = date('d M Y, H:i:s', strtotime($absen['check_in_time']));
                                            if ($status === 'Tepat Waktu' || $status === 'Dadakan') {
                                                $badge = '<span class="badge badge-success">Hadir (' . htmlspecialchars($status) . ')</span>';
                                            } else {
                                                $badge = '<span class="badge badge-warning">Hadir (Telat)</span>';
                                            }
                                        } else {
                                            $badge = '<span class="badge badge-danger">Tidak Absen</span>';
                                            $waktu = '-';
                                        }

                                        // Cek feedback
                                        $stmt_fb = $pdo->prepare("SELECT * FROM meeting_feedbacks WHERE meeting_id = ? AND user_id = ?");
                                        $stmt_fb->execute([$detail_id, $p['id']]);
                                        $fb = $stmt_fb->fetch();
                                        
                                        if ($fb) {
                                            $safe_text = htmlspecialchars(addslashes($fb['feedback_text']));
                                            $safe_name = htmlspecialchars(addslashes($p['name']));
                                            $q1 = (int)$fb['q1_rating'];
                                            $q2 = (int)$fb['q2_rating'];
                                            $q3 = (int)$fb['q3_rating'];
                                            $q4 = (int)$fb['q4_rating'];
                                            
                                            $fb_html = "<button class='btn-action-text btn-view-blue' onclick=\"viewFeedback('{$safe_name}', '{$safe_text}', {$q1}, {$q2}, {$q3}, {$q4})\">Lihat Feedback</button>";
                                        } else {
                                            $fb_html = "<span style='color:#ccc;'>-</span>";
                                        }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td><?= $badge ?></td>
                                        <td><?= $waktu ?></td>
                                        <?php if ($is_hr || $is_admin): ?>
                                            <td><span style="font-size:0.85rem; color:#64748b;"><?= htmlspecialchars($absen['late_reason'] ?? '-') ?></span></td>
                                            <td><?= $fb_html ?></td>
                                        <?php endif; ?>
                                        <td>-</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php
                                    $stmt_guests = $pdo->prepare("SELECT * FROM external_guests WHERE meeting_id = ? ORDER BY id ASC");
                                    $stmt_guests->execute([$detail_id]);
                                    $guests = $stmt_guests->fetchAll();
                                    
                                    if (count($guests) > 0): ?>
                                    <tr>
                                        <td colspan="<?= ($is_hr || $is_admin) ? 6 : 4 ?>" style="background:#f8fafc; font-weight:600; color:#475569; font-size:0.85rem; padding: 8px 15px;">Tamu Eksternal</td>
                                    </tr>
                                    <?php foreach ($guests as $g): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($g['name']) ?>
                                            <?php if (!empty($g['institution'])): ?>
                                                <br><span style="font-size:0.8rem; color:#64748b;"><?= htmlspecialchars($g['institution']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-success">Hadir (Eksternal)</span></td>
                                        <td>-</td>
                                        <?php if ($is_hr || $is_admin): ?>
                                            <td>-</td>
                                            <td>-</td>
                                        <?php endif; ?>
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus tamu eksternal ini?');">
                                                <input type="hidden" name="meeting_id" value="<?= $detail_id ?>">
                                                <input type="hidden" name="guest_id" value="<?= $g['id'] ?>">
                                                <button type="submit" name="delete_external_guest" style="background:none; border:none; color:#ef4444; cursor:pointer;" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script>
        function exportWithHR(e, type, id = null) {
            e.preventDefault();
            const isForHR = (type === 'detail') ? document.getElementById('isForHRDetail').checked : false;
            let url = `export_excel.php?type=${type}`;
            if (id) url += `&id=${id}`;
            if (isForHR) url += `&is_hr=1`;
            
            // For summary, add room filter
            if (type === 'summary') {
                const room = document.getElementById('roomFilter').value;
                if (room) url += `&room=${encodeURIComponent(room)}`;
            }
            
            window.location.href = url;
        }
        function viewFeedback(name, text, q1, q2, q3, q4) {
            const getStars = (count) => {
                let s = '';
                for(let i=0; i<5; i++) s += i < count ? '⭐' : '☆';
                return s;
            };

            Swal.fire({
                title: 'Feedback dari ' + name,
                width: '600px',
                html: `<div style="text-align:left; font-size:0.9rem; color:#475569;">
                         <div style="margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
                            <p style="margin:0 0 5px 0; font-weight:600;">1. Kesesuaian Jadwal:</p>
                            <span style="font-size:1.1rem; letter-spacing:2px;">${getStars(q1)}</span>
                         </div>
                         <div style="margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
                            <p style="margin:0 0 5px 0; font-weight:600;">2. Kesesuaian Isi Notulen:</p>
                            <span style="font-size:1.1rem; letter-spacing:2px;">${getStars(q2)}</span>
                         </div>
                         <div style="margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
                            <p style="margin:0 0 5px 0; font-weight:600;">3. Efektivitas Tools:</p>
                            <span style="font-size:1.1rem; letter-spacing:2px;">${getStars(q3)}</span>
                         </div>
                         <div style="margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
                            <p style="margin:0 0 5px 0; font-weight:600;">4. Ketepatan Distribusi Notulen:</p>
                            <span style="font-size:1.1rem; letter-spacing:2px;">${getStars(q4)}</span>
                         </div>
                         <div>
                            <p style="margin:0 0 8px 0; font-weight:600;">5. Saran & Masukan:</p>
                            <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0; line-height:1.5;">
                                ${text.replace(/\n/g, '<br>')}
                            </div>
                         </div>
                       </div>`,
                showConfirmButton: true,
                confirmButtonText: 'Tutup',
                confirmButtonColor: '#3f51b5'
            });
        }

        $(document).ready(function() {
            $('#roomFilter').select2({
                placeholder: "-- Semua Ruangan --",
                allowClear: true
            });
            $('#user_invite_select').select2({
                placeholder: "Pilih karyawan...",
                allowClear: true
            });
        });

        function copyLink(text) {
            navigator.clipboard.writeText(text).then(() => {
                Toast.fire({
                    icon: 'success',
                    title: 'Link Disalin',
                    text: 'Link absensi sudah tersalin ke clipboard Anda.'
                });
            });
        }

        function showAccess(url, title) {
            Swal.fire({
                title: 'Akses Absensi',
                html: `<p style="margin-bottom:20px; font-size:1.1rem;"><strong id="barcodeMeetingTitle">${title}</strong></p>
                       <div style="display: flex; justify-content: center; margin-bottom: 20px;">
                           <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(url)}" style="border:4px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-radius:12px; width: 200px; height: 200px;">
                       </div>
                       <div id="qrcode-hidden" style="display: none;"></div>
                       <div style="background: #f8fafc; padding: 10px; border-radius: 8px; word-break: break-all; font-family: monospace; font-size: 0.75rem; color: #64748b; border: 1px solid #e2e8f0; margin-bottom: 15px;" id="barcodeLink">${url}</div>
                       <button type="button" class="btn-submit" style="width: 100%; background: #10b981; border:none; padding: 12px; border-radius: 8px; color:white; font-weight:600; cursor:pointer;" onclick="downloadQR('${url}')">
                           <i class="fa-solid fa-download" style="margin-right: 8px;"></i> Unduh Barcode
                       </button>`,
                showConfirmButton: false,
                showCloseButton: true
            });
        }

        function downloadQR(url) {
            // Generate QR in hidden div first
            const hiddenDiv = document.getElementById('qrcode-hidden');
            hiddenDiv.innerHTML = "";
            new QRCode(hiddenDiv, {
                text: url,
                width: 500,
                height: 500
            });

            // Wait a bit for QRCode to render
            setTimeout(() => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = 800;
                canvas.height = 1000;
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                const title = document.getElementById('barcodeMeetingTitle').textContent;
                
                const drawContent = (logoHeight = 0) => {
                    ctx.fillStyle = '#1e293b';
                    ctx.font = 'bold 40px "Inter", sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(title.toUpperCase(), canvas.width / 2, 80 + logoHeight);
                    
                    const qrCanvas = hiddenDiv.querySelector('canvas');
                    const qrImg = hiddenDiv.querySelector('img');
                    
                    const drawQR = (source) => {
                        const qrSize = 500;
                        ctx.drawImage(source, (canvas.width - qrSize) / 2, 130 + logoHeight, qrSize, qrSize);
                        
                        const rulesX = (canvas.width - qrSize) / 2;
                        ctx.textAlign = 'left';
                        ctx.fillStyle = '#475569';
                        ctx.font = 'bold 20px "Inter", sans-serif';
                        ctx.fillText('Tata Tertib Kehadiran Meeting:', rulesX, 130 + logoHeight + qrSize + 60);
                        
                        ctx.font = '18px "Inter", sans-serif';
                        ctx.fillStyle = '#334155';
                        const rules = [
                            '1. Scan Barcode ini untuk melakukan absensi.',
                            '2. Harap hadir tepat waktu sesuai jadwal.',
                            '3. Isi feedback setelah meeting berakhir.',
                            '4. Notulen akan dibagikan setelah meeting selesai.'
                        ];
                        rules.forEach((rule, index) => {
                            ctx.fillText(rule, rulesX, 130 + logoHeight + qrSize + 100 + (index * 30));
                        });
                        
                        canvas.toBlob((blob) => {
                            const urlBlob = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = urlBlob;
                            a.download = `Barcode_${title.replace(/\s+/g, '_')}.png`;
                            a.click();
                        }, 'image/png');
                    };

                    if (qrImg && qrImg.src && qrImg.src.startsWith('data:')) {
                        drawQR(qrImg);
                    } else if (qrCanvas) {
                        drawQR(qrCanvas);
                    }
                };

                const drawTintedLogo = (img) => {
                    const logoWidth = 250;
                    const logoHeight = (logoWidth / img.width) * img.height;
                    const tCanvas = document.createElement('canvas');
                    tCanvas.width = logoWidth;
                    tCanvas.height = logoHeight;
                    const tCtx = tCanvas.getContext('2d');
                    tCtx.drawImage(img, 0, 0, logoWidth, logoHeight);
                    const imgData = tCtx.getImageData(0, 0, logoWidth, logoHeight);
                    const data = imgData.data;
                    for (let i = 0; i < data.length; i += 4) {
                        if (data[i] > 200 && data[i+1] > 200 && data[i+2] > 200 && data[i+3] > 50) {
                            data[i] = 30; data[i+1] = 41; data[i+2] = 59;
                        }
                    }
                    tCtx.putImageData(imgData, 0, 0);
                    ctx.drawImage(tCanvas, (canvas.width - logoWidth) / 2, 40);
                    drawContent(logoHeight + 40);
                };

                const logo = new Image();
                logo.src = 'logo.png';
                logo.onload = () => drawTintedLogo(logo);
                logo.onerror = () => {
                    const rootLogo = new Image();
                    rootLogo.src = 'assets/logo.png';
                    rootLogo.onload = () => drawTintedLogo(rootLogo);
                    rootLogo.onerror = () => drawContent(50);
                };
            }, 100);
        }

        function confirmEnd(id) {
            Swal.fire({
                title: 'Akhiri Meeting?',
                text: "Peserta tidak akan bisa absen lagi setelah ini.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f39c12',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Akhiri!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('end_meeting_id').value = id;
                    document.getElementById('globalEndForm').submit();
                }
            });
        }

        function confirmDelete(id) {
            Swal.fire({
                title: 'Hapus Jadwal?',
                text: "Data absen yang sudah ada juga akan terhapus permanen!",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-form-' + id).submit();
                }
            });
        }

        let lastSelectedIndex = -1;

        document.addEventListener('DOMContentLoaded', () => {
            const isForHRCheckbox = document.getElementById('isForHRDetail');
            const exportDetailBtn = document.getElementById('exportDetailBtn');
            if (isForHRCheckbox && exportDetailBtn) {
                isForHRCheckbox.addEventListener('change', () => {
                    const baseUrl = 'export_excel.php?type=detail&id=<?= $detail_id ?>';
                    exportDetailBtn.setAttribute('href', isForHRCheckbox.checked ? baseUrl + '&is_hr=1' : baseUrl);
                });
            }

            const rows = Array.from(document.querySelectorAll('.selectable-row'));
            
            rows.forEach((row, index) => {
                row.addEventListener('click', (e) => {
                    if (e.target.closest('button') || e.target.closest('a') || e.target.tagName.toLowerCase() === 'input') return;

                    const checkbox = row.querySelector('.row-checkbox');
                    
                    if (e.shiftKey && lastSelectedIndex !== -1) {
                        let min = Math.min(lastSelectedIndex, index);
                        let max = Math.max(lastSelectedIndex, index);
                        
                        if (!e.ctrlKey && !e.metaKey) {
                            rows.forEach(r => {
                                r.classList.remove('selected');
                                r.querySelector('.row-checkbox').checked = false;
                            });
                        }
                        
                        for (let i = min; i <= max; i++) {
                            rows[i].classList.add('selected');
                            rows[i].querySelector('.row-checkbox').checked = true;
                        }
                    } else if (e.ctrlKey || e.metaKey) {
                        checkbox.checked = !checkbox.checked;
                        if (checkbox.checked) {
                            row.classList.add('selected');
                        } else {
                            row.classList.remove('selected');
                        }
                        lastSelectedIndex = index;
                    } else {
                        rows.forEach(r => {
                            r.classList.remove('selected');
                            r.querySelector('.row-checkbox').checked = false;
                        });
                        checkbox.checked = true;
                        row.classList.add('selected');
                        lastSelectedIndex = index;
                    }
                    
                    toggleBulkDeleteBtn();
                    if(window.getSelection) { window.getSelection().removeAllRanges(); }
                });
            });

            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                    if (e.target.tagName.toLowerCase() === 'input' || e.target.tagName.toLowerCase() === 'textarea') return;
                    e.preventDefault();
                    
                    let allSelected = true;
                    rows.forEach(r => {
                        if (!r.querySelector('.row-checkbox').checked) allSelected = false;
                    });
                    
                    rows.forEach(r => {
                        if (allSelected) {
                            r.classList.remove('selected');
                            r.querySelector('.row-checkbox').checked = false;
                        } else {
                            r.classList.add('selected');
                            r.querySelector('.row-checkbox').checked = true;
                        }
                    });
                    toggleBulkDeleteBtn();
                }
            });
        });

        function toggleBulkDeleteBtn() {
            var checkboxes = document.querySelectorAll('.row-checkbox:checked');
            var btn = document.getElementById('btnBulkDelete');
            if (btn) {
                if (checkboxes.length > 0) {
                    btn.style.display = 'inline-flex';
                } else {
                    btn.style.display = 'none';
                }
            }
        }

        function openPdfModal(meetingId, currentLink) {
            document.getElementById('pdf_meeting_id').value = meetingId;
            document.getElementById('pdf_input_link').value = currentLink;
            
            var visitBtn = document.getElementById('visit_pdf_btn');
            if (currentLink && currentLink.trim() !== '') {
                visitBtn.href = currentLink;
                visitBtn.style.display = 'inline-flex';
            } else {
                visitBtn.style.display = 'none';
            }
            
            document.getElementById('pdfModal').classList.add('active');
        }
        
        function closePdfModal() {
            document.getElementById('pdfModal').classList.remove('active');
        }
    </script>
    <style>
        .selectable-row {
            cursor: pointer;
            user-select: none;
            transition: background 0.15s ease;
        }
        .selectable-row.selected {
            background-color: #e2e8f0 !important;
        }
        .selectable-row:hover:not(.selected) {
            background-color: #f8fafc;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <div id="qrcode" style="display:none;"></div>
    
    <?php if (isset($_GET['invited']) && $_GET['invited'] === 'success'): ?>
        <script>
            Toast.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Peserta dadakan telah ditambahkan dan diabsen otomatis.'
            });
        </script>
    <?php endif; ?>

    <?php if (isset($_GET['end_success'])): ?>
        <script>
            Toast.fire({
                icon: 'success',
                title: 'Meeting Diakhiri',
                text: 'Meeting telah berhasil diselesaikan.'
            });
        </script>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: '<?= htmlspecialchars($_GET['error']) ?>'
            });
        </script>
    <?php endif; ?>

    <?php if (isset($_GET['saved']) && $_GET['saved'] === 'success'): ?>
        <script>
            Toast.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Link PDF summary berhasil disimpan.'
            });
        </script>
    <?php endif; ?>

    <!-- Modal PDF Link -->
    <div id="pdfModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Link Summary</h3>
                <button class="modal-close" onclick="closePdfModal()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <input type="hidden" name="meeting_id" id="pdf_meeting_id">
                    <div class="form-group">
                        <label class="form-label" style="display:block; margin-bottom:8px; font-weight:500;">Diambil dari Sena(DMS)</label>
                        <input type="url" name="pdf_link" id="pdf_input_link" class="form-control" placeholder="https://skycloud.indoarsip.co.id/dokmeeecm/#/viewer/" style="width:100%; box-sizing:border-box; margin-bottom:15px; padding:10px; border:1px solid #e2e8f0; border-radius:6px;">
                    </div>
                    <div style="display:flex; gap:10px; margin-top: 15px;">
                        <button type="submit" name="save_pdf_link" class="btn-submit" style="flex:1; padding: 12px; border-radius: 8px; border:none; color:white; font-weight:600; cursor:pointer;">
                            Simpan Link
                        </button>
                        <a href="#" id="visit_pdf_btn" target="_blank" class="btn-submit" style="background:#10b981; text-decoration:none; text-align:center; padding: 12px; border-radius: 8px; display:none; justify-content:center; align-items:center; color:white; font-weight:600;">
                            <i class="fa-solid fa-external-link" style="margin-right:8px;"></i> Buka Link
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
