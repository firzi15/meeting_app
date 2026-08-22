<?php
session_start();
require_once 'database.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$meeting_id = $_GET['id'] ?? null;
$token = $_GET['token'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$meeting_id) {
    header("Location: index.php");
    exit;
}

// Fetch meeting details
$stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
$stmt->execute([$meeting_id]);
$meeting = $stmt->fetch();

if (!$meeting) {
    die("Meeting tidak ditemukan.");
}

// Check if user was a participant in this meeting
$stmt_check = $pdo->prepare("SELECT user_id FROM meeting_participants WHERE meeting_id = ? AND user_id = ?");
$stmt_check->execute([$meeting_id, $user_id]);
if (!$stmt_check->fetch() && $_SESSION['role'] !== 'admin') {
    header("Location: index.php?msg=Anda tidak terdaftar dalam meeting ini");
    exit;
}

// Check if already submitted
$stmt_fb = $pdo->prepare("SELECT id FROM meeting_feedbacks WHERE meeting_id = ? AND user_id = ?");
$stmt_fb->execute([$meeting_id, $user_id]);
if ($stmt_fb->fetch()) {
    header("Location: my_schedule.php?msg=Feedback sudah pernah dikirim");
    exit;
}

// Handle Submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $q1 = (int)($_POST['q1_rating'] ?? 0);
    $q2 = (int)($_POST['q2_rating'] ?? 0);
    $q3 = (int)($_POST['q3_rating'] ?? 0);
    $q4 = (int)($_POST['q4_rating'] ?? 0);
    $feedback_text = $_POST['feedback_text'] ?? '';

    if ($q1 && $q2 && $q3 && $q4 && $feedback_text) {
        try {
            $stmt = $pdo->prepare("INSERT INTO meeting_feedbacks (meeting_id, user_id, q1_rating, q2_rating, q3_rating, q4_rating, feedback_text) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$meeting_id, $user_id, $q1, $q2, $q3, $q4, $feedback_text]);
            header("Location: thanks.php?type=feedback");
            exit;
        } catch (PDOException $e) {
            $error = "Gagal menyimpan feedback: " . $e->getMessage();
        }
    } else {
        $error = "Semua pertanyaan wajib diisi.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Meeting - Indoarsip</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: #f1f5f9;
            min-height: 100vh;
            padding: 40px 20px;
        }
        .feedback-container {
            max-width: 720px;
            margin: 0 auto;
        }
        .header-banner {
            height: 12px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            border-radius: 10px 10px 0 0;
        }
        .feedback-card {
            background: white;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .card-content {
            padding: 40px;
        }
        .title-card {
            padding: 40px;
            border-top: 12px solid #6366f1;
            border-radius: 12px;
        }
        .meeting-title {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 12px;
            letter-spacing: -0.025em;
        }
        .meeting-subtitle {
            color: #64748b;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        .question-card {
            padding: 30px 40px;
            border-radius: 12px;
        }
        .question-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 10px;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 2.5rem;
            color: #cbd5e1;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #f59e0b;
            transform: scale(1.1);
        }
        .rating-desc {
            margin-top: 10px;
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        textarea.feedback-input {
            width: 100%;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            font-size: 1rem;
            min-height: 150px;
            transition: all 0.2s;
            background: #f8fafc;
        }
        textarea.feedback-input:focus {
            outline: none;
            border-color: #6366f1;
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
        }
        .btn-submit {
            padding: 16px 40px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }
        .btn-submit:hover {
            background: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.4);
        }
        
        @media (max-width: 640px) {
            body { padding: 20px 10px; }
            .card-content, .question-card { padding: 25px 20px; }
            .meeting-title { font-size: 1.5rem; }
            .star-rating label { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="feedback-container">
        <!-- Title Card -->
        <div class="feedback-card" style="border-top: 10px solid #6366f1;">
            <div class="card-content">
                <h1 class="meeting-title"><?= htmlspecialchars($meeting['title']) ?></h1>
                <p class="meeting-subtitle">Terima kasih telah berpartisipasi dalam meeting ini. Mohon luangkan waktu sejenak untuk memberikan feedback agar kami dapat terus berkembang.</p>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f1f5f9; display: flex; gap: 20px; color: #64748b; font-size: 0.9rem;">
                    <span><i class="fa-solid fa-calendar" style="margin-right: 5px;"></i> <?= date('d M Y', strtotime($meeting['scheduled_time'])) ?></span>
                    <span><i class="fa-solid fa-user" style="margin-right: 5px;"></i> PIC: <?= htmlspecialchars($_SESSION['name']) ?></span>
                </div>
            </div>
        </div>

        <form method="POST" id="feedbackForm">
            <!-- Question 1 -->
            <div class="feedback-card">
                <div class="question-card">
                    <p class="question-text">1. Apakah pelaksanaan meeting sudah sesuai dengan jadwal yang ditentukan? <span style="color: #ef4444;">*</span></p>
                    <div class="star-rating">
                        <input type="radio" id="q1-5" name="q1_rating" value="5" required /><label for="q1-5"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q1-4" name="q1_rating" value="4" /><label for="q1-4"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q1-3" name="q1_rating" value="3" /><label for="q1-3"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q1-2" name="q1_rating" value="2" /><label for="q1-2"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q1-1" name="q1_rating" value="1" /><label for="q1-1"><i class="fa-solid fa-star"></i></label>
                    </div>
                    <div class="rating-desc">Sangat Kurang &nbsp; &mdash; &nbsp; Sangat Sesuai</div>
                </div>
            </div>

            <!-- Question 2 -->
            <div class="feedback-card">
                <div class="question-card">
                    <p class="question-text">2. Apakah isi notulen sudah sesuai dengan jalannya meeting yang sebenarnya? <span style="color: #ef4444;">*</span></p>
                    <div class="star-rating">
                        <input type="radio" id="q2-5" name="q2_rating" value="5" required /><label for="q2-5"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q2-4" name="q2_rating" value="4" /><label for="q2-4"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q2-3" name="q2_rating" value="3" /><label for="q2-3"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q2-2" name="q2_rating" value="2" /><label for="q2-2"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q2-1" name="q2_rating" value="1" /><label for="q2-1"><i class="fa-solid fa-star"></i></label>
                    </div>
                    <div class="rating-desc">Sangat Kurang &nbsp; &mdash; &nbsp; Sangat Sesuai</div>
                </div>
            </div>

            <!-- Question 3 -->
            <div class="feedback-card">
                <div class="question-card">
                    <p class="question-text">3. Apakah penggunaan tools meeting sudah berjalan efektif? <span style="color: #ef4444;">*</span></p>
                    <div class="star-rating">
                        <input type="radio" id="q3-5" name="q3_rating" value="5" required /><label for="q3-5"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q3-4" name="q3_rating" value="4" /><label for="q3-4"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q3-3" name="q3_rating" value="3" /><label for="q3-3"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q3-2" name="q3_rating" value="2" /><label for="q3-2"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q3-1" name="q3_rating" value="1" /><label for="q3-1"><i class="fa-solid fa-star"></i></label>
                    </div>
                    <div class="rating-desc">Sangat Kurang &nbsp; &mdash; &nbsp; Sangat Efektif</div>
                </div>
            </div>

            <!-- Question 4 -->
            <div class="feedback-card">
                <div class="question-card">
                    <p class="question-text">4. Apakah notulen telah didistribusikan tepat waktu? <span style="color: #ef4444;">*</span></p>
                    <div class="star-rating">
                        <input type="radio" id="q4-5" name="q4_rating" value="5" required /><label for="q4-5"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q4-4" name="q4_rating" value="4" /><label for="q4-4"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q4-3" name="q4_rating" value="3" /><label for="q4-3"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q4-2" name="q4_rating" value="2" /><label for="q4-2"><i class="fa-solid fa-star"></i></label>
                        <input type="radio" id="q4-1" name="q4_rating" value="1" /><label for="q4-1"><i class="fa-solid fa-star"></i></label>
                    </div>
                    <div class="rating-desc">Sangat Kurang &nbsp; &mdash; &nbsp; Sangat Tepat Waktu</div>
                </div>
            </div>

            <!-- Question 5 -->
            <div class="feedback-card">
                <div class="question-card">
                    <p class="question-text">5. Apakah terdapat saran atau masukan untuk perbaikan ke depannya? <span style="color: #ef4444;">*</span></p>
                    <textarea name="feedback_text" class="feedback-input" placeholder="Jawaban Anda" required></textarea>
                </div>
            </div>

            <div class="footer-actions">
                <?php if ($token): ?>
                    <a href="attendance.php?token=<?= urlencode($token) ?>" style="color: #64748b; text-decoration: none; font-weight: 600;">Kembali</a>
                <?php else: ?>
                    <a href="my_schedule.php" style="color: #64748b; text-decoration: none; font-weight: 600;">Kembali</a>
                <?php endif; ?>
                <button type="submit" class="btn-submit">Kirim Feedback</button>
            </div>
        </form>
        
        <p style="text-align: center; margin-top: 60px; color: #94a3b8; font-size: 0.85rem; font-weight: 500;">
            Konten ini tidak dibuat atau didukung oleh Indoarsip. 
            <br>&copy; <?= date('Y') ?> Indoarsip Meeting System
        </p>
    </div>

    <script>
        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal Mengirim',
            text: '<?= $error ?>',
            confirmButtonColor: '#6366f1'
        });
        <?php endif; ?>
    </script>
</body>
</html>

