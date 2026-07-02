<?php
session_start();
require_once 'database.php';

$token = $_GET['token'] ?? '';
$room_id = $_GET['room_id'] ?? '';

if (!$token && !$room_id) {
    die("Link absensi tidak valid.");
}

$meeting = null;

// 2. Auth Check - ENSURE LOGGED IN FIRST
if (!isset($_SESSION['user_id'])) {
    // Save where they wanted to go (e.g., room_id=4)
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit;
}

if ($room_id) {
    $stmt_room = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
    $stmt_room->execute([$room_id]);
    $room_name = $stmt_room->fetchColumn();

    if (!$room_name) {
        $error_title = "Ruangan Tidak Ditemukan";
        $error_message = "ID ruangan yang Anda masukkan tidak terdaftar dalam sistem.";
    } else {
        $now = date('Y-m-d H:i:s');
        $two_hours_ago = date('Y-m-d H:i:s', time() - (2 * 3600));

        // 1. PRIORITY: Find a meeting the user is IN and NEEDS to give feedback for (Scan 2)
        $stmt_fb_pending = $pdo->prepare("
            SELECT m.* 
            FROM meetings m
            JOIN attendances a ON m.id = a.meeting_id
            LEFT JOIN meeting_feedbacks mf ON m.id = mf.meeting_id AND mf.user_id = a.user_id
            WHERE m.room = ? 
            AND a.user_id = ?
            AND mf.id IS NULL
            AND m.end_time >= ?
            AND (m.status = 'approved' OR m.status = 'finished')
            ORDER BY m.end_time ASC LIMIT 1
        ");
        $stmt_fb_pending->execute([$room_name, $_SESSION['user_id'], $two_hours_ago]);
        $meeting = $stmt_fb_pending->fetch();

        // 2. FALLBACK: Find the next/current active meeting (Scan 1)
        if (!$meeting) {
            $stmt_next = $pdo->prepare("
                SELECT * FROM meetings 
                WHERE room = ? 
                AND end_time >= ?
                AND status = 'approved'
                ORDER BY scheduled_time ASC LIMIT 1
            ");
            $stmt_next->execute([$room_name, $now]);
            $meeting = $stmt_next->fetch();
        }

        if (!$meeting) {
            $error_title = "Tidak Ada Jadwal";
            $error_message = "Tidak ada jadwal meeting aktif atau kewajiban feedback di ruangan <strong>" . htmlspecialchars($room_name) . "</strong> saat ini.";
        } elseif ($meeting['status'] !== 'approved' && $meeting['status'] !== 'finished') {
            $error_title = "Menunggu Persetujuan";
            $error_message = "Meeting <strong>" . htmlspecialchars($meeting['title']) . "</strong> sedang menunggu persetujuan admin atau HR.";
            $meeting_found_but_inactive = true;
        } else {
            $start_limit = date('Y-m-d H:i:s', strtotime($now . ' + 1 hour'));
            if ($meeting['scheduled_time'] > $start_limit) {
                $error_title = "Belum Dimulai";
                $error_message = "Meeting <strong>" . htmlspecialchars($meeting['title']) . "</strong> baru akan dimulai pada jam " . date('H:i', strtotime($meeting['scheduled_time'])) . ". Silakan kembali lagi nanti.";
                $meeting_found_but_inactive = true;
            } else {
                $token = $meeting['token'];
            }
        }
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE token = ?");
    $stmt->execute([$token]);
    $meeting = $stmt->fetch();

    if (!$meeting) {
        $error_title = "Link Tidak Valid";
        $error_message = "Meeting tidak ditemukan atau link absensi sudah kadaluarsa.";
    }
}

// If error occurs, show beautiful error page
if (isset($error_title)) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $error_title ?> - Indoarsip Meeting</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --google-purple: #673ab7; --bg-gray: #f0ebf8; --border-gray: #dadce0; }
        body { font-family: 'Roboto', sans-serif; background-color: var(--bg-gray); margin: 0; padding: 20px 10px; display: flex; flex-direction: column; align-items: center; min-height: 100vh; justify-content: center; }
        .container { width: 100%; max-width: 640px; }
        .card { background: white; border-radius: 8px; border: 1px solid var(--border-gray); margin-bottom: 12px; padding: 24px; position: relative; border-top: 10px solid var(--google-purple); }
        h1 { font-size: 32px; font-weight: 400; margin: 0 0 15px 0; color: #202124; display: flex; align-items: center; gap: 15px; }
        .message { font-size: 16px; color: #3c4043; line-height: 1.5; margin-bottom: 25px; }
        .btn-back { display: inline-block; background-color: var(--google-purple); color: white; text-decoration: none; padding: 10px 24px; border-radius: 4px; font-weight: 500; font-size: 14px; }
        .status-icon { font-size: 28px; color: #fbbc04; display: flex; align-items: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>
                <div class="status-icon">
                    <?php if ($error_title === 'Belum Dimulai'): ?>
                        <i class="fa-solid fa-clock"></i>
                    <?php elseif ($error_title === 'Menunggu Persetujuan'): ?>
                        <i class="fa-solid fa-hourglass-half"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-circle-exclamation"></i>
                    <?php endif; ?>
                </div>
                <?= $error_title ?>
            </h1>
            <div class="message"><?= $error_message ?></div>
        </div>
    </div>
</body>
</html>
<?php
exit;
}


$user_id = $_SESSION['user_id'];
$meeting_id = $meeting['id'];

// 3. Permission Check (Invited or Admin)
$stmt_check_invite = $pdo->prepare("SELECT user_id FROM meeting_participants WHERE meeting_id = ? AND user_id = ?");
$stmt_check_invite->execute([$meeting_id, $user_id]);
$is_invited = (bool)$stmt_check_invite->fetch();

// Removed Access Denied block so uninvited users can join as "Dadakan"
// 4. State Checks
$stmt_check_absen = $pdo->prepare("SELECT * FROM attendances WHERE meeting_id = ? AND user_id = ?");
$stmt_check_absen->execute([$meeting_id, $user_id]);
$already_absent = (bool)$stmt_check_absen->fetch();

$stmt_check_fb = $pdo->prepare("SELECT id FROM meeting_feedbacks WHERE meeting_id = ? AND user_id = ?");
$stmt_check_fb->execute([$meeting_id, $user_id]);
$already_feedback = (bool)$stmt_check_fb->fetch();

$start_time = strtotime($meeting['scheduled_time']);
$end_time = strtotime($meeting['end_time']);
$current_time = time();

$tolerance_after_end = 15 * 60; // 15 minutes tolerance
$is_past_tolerance = ($current_time > ($end_time + $tolerance_after_end));

// A meeting is expired for ATTENDANCE purposes if it's past the tolerance OR explicitly finished
$attendance_expired = ($is_past_tolerance || $meeting['status'] === 'finished');

// A meeting is ready for FEEDBACK if it has passed its end time OR explicitly finished
$feedback_ready = ($current_time > $end_time || $meeting['status'] === 'finished');

$is_early = ($current_time < $start_time && $meeting['status'] !== 'finished');

// 5. POST Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handling Attendance
    if (isset($_POST['action']) && $_POST['action'] === 'absen' && !$already_absent) {
        if (!$is_invited) {
            $status = "Dadakan";
            $late_reason = "Peserta Dadakan";
            $stmt_invite = $pdo->prepare("INSERT INTO meeting_participants (meeting_id, user_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
            $stmt_invite->execute([$meeting_id, $user_id]);
        } else {
            $late_tolerance = $meeting['late_tolerance'] ?? 15;
            $batas_telat = $start_time + ($late_tolerance * 60);
            $status = ($current_time > $batas_telat) ? "Telat" : "Tepat Waktu";
            $late_reason = $_POST['late_reason'] ?? null;
        }
        
        $insert = $pdo->prepare("INSERT INTO attendances (meeting_id, user_id, check_in_time, status, late_reason) VALUES (?, ?, ?, ?, ?)");
        $insert->execute([$meeting_id, $user_id, date('Y-m-d H:i:s'), $status, $late_reason]);
        header("Location: attendance.php?token=" . $token);
        exit;
    }
    
    // Handling Feedback
    if (isset($_POST['action']) && $_POST['action'] === 'feedback' && !$already_feedback) {
        $q1 = (int)$_POST['q1_rating'];
        $q2 = (int)$_POST['q2_rating'];
        $q3 = (int)$_POST['q3_rating'];
        $q4 = (int)$_POST['q4_rating'];
        $text = $_POST['feedback_text'] ?? '';
        
        $insert = $pdo->prepare("INSERT INTO meeting_feedbacks (meeting_id, user_id, q1_rating, q2_rating, q3_rating, q4_rating, feedback_text) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([$meeting_id, $user_id, $q1, $q2, $q3, $q4, $text]);
        header("Location: attendance.php?token=" . $token);
        exit;
    }
}

// 6. Determine Mode
$mode = 'ATTENDANCE';

if ($attendance_expired && !$already_absent) {
    // If user forgot to check in and it's past 15m tolerance
    $mode = 'FINISHED';
} elseif ($already_absent) {
    if (!$feedback_ready) {
        $mode = 'THANKS_WAITING';
    } elseif (!$already_feedback) {
        $mode = 'FEEDBACK';
    } else {
        $mode = 'FINISHED';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($meeting['title']) ?> - Presensi</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root { --google-purple: #673ab7; --bg-gray: #f0ebf8; --border-gray: #dadce0; }
        body { font-family: 'Roboto', sans-serif; background-color: var(--bg-gray); margin: 0; padding: 20px 10px; display: flex; flex-direction: column; align-items: center; }
        .container { width: 100%; max-width: 640px; }
        .card { background: white; border-radius: 8px; border: 1px solid var(--border-gray); margin-bottom: 12px; padding: 24px; position: relative; }
        .title-card { border-top: 10px solid var(--google-purple); }
        h1 { font-size: 32px; font-weight: 400; margin: 0 0 10px 0; color: #202124; }
        .meeting-meta { font-size: 14px; color: #202124; margin-bottom: 15px; }
        .meta-item { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
        .hr-divider { border: 0; border-top: 1px solid var(--border-gray); margin: 15px 0; }
        .user-info { display: flex; justify-content: space-between; align-items: center; font-size: 14px; color: #5f6368; }
        .question-title { font-size: 16px; font-weight: 500; color: #202124; margin-bottom: 20px; }
        .required { color: #d93025; margin-left: 4px; }
        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 4px; font-size: 14px; font-weight: 500; background: #f8fafc; border: 1px solid #e2e8f0; width: 100%; box-sizing: border-box; }
        .status-success { color: #1e8e3e; background: #e6f4ea; border-color: #ceead6; }
        .status-info { color: #1a73e8; background: #e8f0fe; border-color: #d2e3fc; }
        
        /* Star Rating */
        .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 10px; margin-top: 10px; }
        .star-rating input { display: none; }
        .star-rating label { font-size: 24px; color: #dadce0; cursor: pointer; transition: color 0.2s; }
        .star-rating label:hover, .star-rating label:hover ~ label, .star-rating input:checked ~ label { color: #fbbc04; }

        textarea { width: 100%; border: none; border-bottom: 1px solid var(--border-gray); padding: 8px 0; font-family: inherit; font-size: 14px; resize: none; background: transparent; }
        textarea:focus { outline: none; border-bottom: 2px solid var(--google-purple); }
        .btn-submit { background-color: var(--google-purple); color: white; border: none; padding: 10px 24px; border-radius: 4px; font-weight: 500; cursor: pointer; }
        .footer-text {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: #70757a;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card title-card">
            <h1>Presensi Meeting</h1>
            <div class="meeting-meta">
                <div class="meta-item"><strong>Judul:</strong> <?= htmlspecialchars($meeting['title']) ?></div>
                <div class="meta-item"><strong>Ruangan:</strong> <?= htmlspecialchars($meeting['room']) ?></div>
                <div class="meta-item"><strong>Waktu:</strong> <?= date('d M Y, H:i', strtotime($meeting['scheduled_time'])) ?> - <?= date('H:i', strtotime($meeting['end_time'])) ?></div>
            </div>
            <hr class="hr-divider">
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['name']) ?></span>
                <span style="color: #d93025;">* Menunjukkan pertanyaan yang wajib diisi</span>
            </div>
        </div>

        <?php if ($mode === 'ATTENDANCE'): ?>
            <?php if ($is_early && $_SESSION['role'] !== 'admin'): ?>
                <div class="card">
                    <div class="question-title">Belum Dimulai</div>
                    <div class="status-badge status-info">
                        <i class="fa-solid fa-hourglass-half"></i> Meeting belum dimulai. Halaman akan otomatis memuat saat waktu dimulai.
                    </div>
                    <script>setTimeout(() => location.reload(), 30000);</script>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="absen">
                    <div class="card">
                        <div class="question-title">Konfirmasi Kehadiran <span class="required">*</span></div>
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="radio" name="absen" value="1" required>
                            <span style="font-size:14px;">Ya, saya hadir dalam meeting ini</span>
                        </label>
                    </div>
                    <?php 
                        $late_tolerance = $meeting['late_tolerance'] ?? 15;
                        if ($is_invited && time() > ($start_time + ($late_tolerance * 60))): 
                    ?>
                    <div class="card">
                        <div class="question-title">Alasan Keterlambatan <span class="required">*</span></div>
                        <textarea name="late_reason" placeholder="Jawaban Anda" required rows="1" oninput='this.style.height = "";this.style.height = this.scrollHeight + "px"'></textarea>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top: 15px; text-align: right;">
                        <button type="submit" class="btn-submit">Kirim Presensi</button>
                    </div>
                </form>
            <?php endif; ?>

        <?php elseif ($mode === 'THANKS_WAITING'): ?>
            <div class="card">
                <div class="question-title">Terima Kasih</div>
                <div class="status-badge status-success">
                    <i class="fa-solid fa-circle-check"></i> Presensi Anda telah berhasil direkam.
                </div>
                <div style="margin-top:20px; color:#5f6368; font-size:14px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-circle-notch fa-spin"></i> Mohon tunggu. Link feedback akan aktif otomatis saat meeting berakhir.
                </div>
                <script>
                    const endTime = <?= $end_time * 1000 ?>;
                    setInterval(() => { if (Date.now() >= endTime) location.reload(); }, 10000);
                </script>
            </div>

        <?php elseif ($mode === 'FEEDBACK'): ?>
            <form method="POST">
                <input type="hidden" name="action" value="feedback">
                <div class="card">
                    <div class="question-title">Rating Materi Meeting <span class="required">*</span></div>
                    <div class="star-rating">
                        <?php for($i=5; $i>=1; $i--): ?>
                            <input type="radio" name="q1_rating" id="q1-<?= $i ?>" value="<?= $i ?>" required>
                            <label for="q1-<?= $i ?>"><i class="fa-solid fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="question-title">Rating Narasumber <span class="required">*</span></div>
                    <div class="star-rating">
                        <?php for($i=5; $i>=1; $i--): ?>
                            <input type="radio" name="q2_rating" id="q2-<?= $i ?>" value="<?= $i ?>" required>
                            <label for="q2-<?= $i ?>"><i class="fa-solid fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="question-title">Ketepatan Waktu <span class="required">*</span></div>
                    <div class="star-rating">
                        <?php for($i=5; $i>=1; $i--): ?>
                            <input type="radio" name="q3_rating" id="q3-<?= $i ?>" value="<?= $i ?>" required>
                            <label for="q3-<?= $i ?>"><i class="fa-solid fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="question-title">Fasilitas Ruangan <span class="required">*</span></div>
                    <div class="star-rating">
                        <?php for($i=5; $i>=1; $i--): ?>
                            <input type="radio" name="q4_rating" id="q4-<?= $i ?>" value="<?= $i ?>" required>
                            <label for="q4-<?= $i ?>"><i class="fa-solid fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="question-title">Kritik & Saran <span class="required">*</span></div>
                    <textarea name="feedback_text" placeholder="Tuliskan masukan Anda..." required rows="2" oninput='this.style.height = "";this.style.height = this.scrollHeight + "px"'></textarea>
                </div>
                <div style="margin-top: 15px; text-align: right;">
                    <button type="submit" class="btn-submit">Kirim Feedback</button>
                </div>
            </form>

        <?php elseif ($mode === 'FINISHED'): ?>
            <div class="card">
                <div class="question-title">Selesai</div>
                <div class="status-badge status-success">
                    <i class="fa-solid fa-circle-check"></i> Seluruh rangkaian meeting telah selesai. Terima kasih atas partisipasi Anda.
                </div>
            </div>
        <?php endif; ?>

        <div class="footer-text">
            dalam rangka memenuhi standar SOP.<br>
            &copy; <?= date('Y') ?> Indoarsip Meeting System
        </div>
    </div>
</body>
</html>
