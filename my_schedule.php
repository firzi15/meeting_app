<?php
session_start();
require_once 'database.php';

// Users and Admin can access
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$room_filter = $_GET['room'] ?? '';

$current_branch = getCurrentBranchId();
$branch_condition = $current_branch > 0 ? "WHERE branch_id = $current_branch" : "WHERE 1=1";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Absen - Indoarsip</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); text-align: left; }
        .table th { background: #f8f9fa; font-weight: 600; color: #555; }
        .table tr:hover { background: #fdfdfd; }
        .btn-view { display:inline-block; background: var(--primary-color); color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 0.9rem; font-weight: 500; }
        .btn-view:hover { background: #303f9f; }
        .btn-disabled { display:inline-block; background: #e0e0e0; color: #888; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 0.9rem; font-weight: 500; pointer-events: none; }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <div class="main-wrapper">
            <?php include 'topbar.php'; ?>

            <main class="content">
                <h1 class="page-title">Jadwal Absensi Anda</h1>
                <p class="page-subtitle">Daftar meeting di mana Anda diundang</p>
                
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h3>Daftar Jadwal</h3>
                        <form method="GET" style="display:flex; gap:10px; align-items:center;">
                            <select name="room" id="roomFilter" class="form-control" style="width:250px;" onchange="this.form.submit()">
                                <option value="">-- Semua Ruangan --</option>
                                <?php
                                $stmt_rooms = $pdo->query("SELECT name FROM rooms $branch_condition ORDER BY name ASC");
                                while($r = $stmt_rooms->fetch()) {
                                    $sel = ($room_filter === $r['name']) ? 'selected' : '';
                                    echo "<option value=\"".htmlspecialchars($r['name'])."\" $sel>".htmlspecialchars($r['name'])."</option>";
                                }
                                ?>
                            </select>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No.</th>
                                    <th>Tanggal</th>
                                    <th>Judul Meeting</th>
                                    <th>Ruang</th>
                                    <th>Waktu</th>
                                    <th>Aksi Absen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $limit = 5;
                                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                $offset = ($page - 1) * $limit;

                                $where_clause = " WHERE mp.user_id = ? AND m.status = 'approved' ";
                                $params = [$user_id];
                                if ($room_filter) {
                                    $where_clause .= " AND m.room = ? ";
                                    $params[] = $room_filter;
                                }

                                // Count total
                                $stmt_count = $pdo->prepare("
                                    SELECT COUNT(*) FROM meetings m 
                                    JOIN meeting_participants mp ON m.id = mp.meeting_id 
                                    $where_clause
                                ");
                                $stmt_count->execute($params);
                                $total_rows = $stmt_count->fetchColumn();
                                $total_pages = ceil($total_rows / $limit);

                                // Fetch meetings
                                $stmt = $pdo->prepare("
                                    SELECT m.* 
                                    FROM meetings m 
                                    JOIN meeting_participants mp ON m.id = mp.meeting_id 
                                    $where_clause
                                    ORDER BY m.scheduled_time DESC
                                    LIMIT $limit OFFSET $offset
                                ");
                                $stmt->execute($params);
                                $meetings = $stmt->fetchAll();

                                if (count($meetings) === 0): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <i class="fa-solid fa-calendar-xmark" style="display: block; font-size: 2rem; margin-bottom: 10px;"></i>
                                            Belum ada data tersedia
                                        </td>
                                    </tr>
                                <?php else: 
                                    $no = $offset + 1;
                                    $current_time = time();
                                    foreach ($meetings as $m):
                                        $start_time = strtotime($m['scheduled_time']);
                                        $end_time = strtotime($m['end_time']);
                                        $link = "attendance.php?token=" . $m['token'];
                                        $meeting_id = $m['id'];

                                        // Check if already submitted
                                        $stmt_cek = $pdo->prepare("SELECT status FROM attendances WHERE meeting_id = ? AND user_id = ?");
                                        $stmt_cek->execute([$m['id'], $user_id]);
                                        $absen = $stmt_cek->fetch();

                                        if ($absen) {
                                            $btn_class = 'btn-disabled';
                                            $btn_text = ($absen['status'] === 'Tepat Waktu') ? 'Sudah Absen' : 'Absen Telat';
                                        } else {
                                            if ($current_time < $start_time) {
                                                $btn_class = 'btn-disabled';
                                                $btn_text = 'Belum Waktunya';
                                            } elseif ($current_time > $end_time) {
                                                $btn_class = 'btn-disabled';
                                                $btn_text = 'Kadaluarsa';
                                            } else {
                                                $btn_class = 'btn-view';
                                                $btn_text = 'Buka Form Absen';
                                            }
                                        }

                                        // Feedback logic
                                        $stmt_fb = $pdo->prepare("SELECT id FROM meeting_feedbacks WHERE meeting_id = ? AND user_id = ?");
                                        $stmt_fb->execute([$m['id'], $user_id]);
                                        $has_feedback = $stmt_fb->fetch();
                                ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td style="white-space: nowrap;">
                                            <div style="font-size: 0.85rem; font-weight: 700; color: #1e293b;"><?= date('d M Y', $start_time) ?></div>
                                        </td>
                                        <td><strong><?= htmlspecialchars($m['title']) ?></strong></td>
                                        <td><?= htmlspecialchars($m['room']) ?></td>
                                        <td><span style="font-weight: 600; color: #1e293b;"><?= date('H:i', $start_time) ?> - <?= date('H:i', $end_time) ?></span></td>
                                        <td>
                                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                                <?php if ($absen): ?>
                                                    <span class="btn-status <?= $absen['status'] === 'Tepat Waktu' ? 'btn-status-neutral' : 'btn-status-warning' ?>">
                                                        <?= $absen['status'] === 'Tepat Waktu' ? 'Sudah Absen' : 'Absen Telat' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <a href="<?= $link ?>" 
                                                       class="<?= $btn_class ?>" 
                                                       id="btn-<?= $meeting_id ?>"
                                                       data-start="<?= $start_time ?>"
                                                       data-end="<?= $end_time ?>">
                                                       <?= $btn_text ?>
                                                    </a>
                                                <?php endif; ?>

                                                <?php 
                                                // Correct PIC check
                                                $is_pic_for_this_meeting = ($m['pic_id'] == $user_id);
                                                
                                                if ($is_pic_for_this_meeting): ?>
                                                    <?php if ($has_feedback): ?>
                                                        <span class="btn-status btn-status-success">Feedback Terkirim</span>
                                                    <?php else: ?>
                                                        <?php if ($absen): // Only show if already absented ?>
                                                            <?php if ($current_time < $end_time): ?>
                                                                <span class="btn-status" id="fb-status-<?= $meeting_id ?>" data-end="<?= $end_time ?>">Feedback: Belum Waktunya</span>
                                                            <?php else: ?>
                                                                <a href="feedback.php?id=<?= $meeting_id ?>" class="btn-view" style="text-decoration:none;">Beri Feedback</a>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="my_schedule.php?page=<?= $i ?><?= $room_filter ? '&room='.urlencode($room_filter) : '' ?>" class="page-link <?= ($page == $i) ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#roomFilter').select2({
                placeholder: "-- Pilih Ruangan --",
                allowClear: true
            });
        });

        function checkMeetings() {
            const now = Math.floor(Date.now() / 1000);
            
            // Update attendance buttons
            const attendanceButtons = document.querySelectorAll('[id^="btn-"]');
            attendanceButtons.forEach(btn => {
                const startTime = parseInt(btn.getAttribute('data-start'));
                const endTime = parseInt(btn.getAttribute('data-end'));
                const currentText = btn.textContent.trim();
                
                if (currentText === 'Belum Waktunya' && now >= startTime) {
                    btn.className = 'btn-view';
                    btn.textContent = 'Buka Form Absen';
                } else if (currentText === 'Buka Form Absen' && now > endTime) {
                    btn.className = 'btn-disabled';
                    btn.textContent = 'Kadaluarsa';
                }
            });

            // Update feedback buttons (auto-reveal when meeting ends)
            const feedbackStatuses = document.querySelectorAll('[id^="fb-status-"]');
            feedbackStatuses.forEach(status => {
                const meetingId = status.id.split('-')[2];
                // Find corresponding attendance button to get end time, or use a data attribute
                // Better: I'll add data-end to the feedback status itself in PHP
                const endTime = parseInt(status.getAttribute('data-end'));
                
                if (now >= endTime) {
                    const parent = status.parentElement;
                    const link = document.createElement('a');
                    link.href = 'feedback.php?id=' + meetingId;
                    link.className = 'btn-view';
                    link.style.textDecoration = 'none';
                    link.textContent = 'Beri Feedback';
                    parent.replaceChild(link, status);
                }
            });
        }
        setInterval(checkMeetings, 1000);

    </script>
</body>
</html>
