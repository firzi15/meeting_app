<?php
session_start();
require_once 'database.php';
$type = $_GET['type'] ?? 'absen';
$meeting_id = $_GET['id'] ?? null;
$token = $_GET['token'] ?? '';

$end_time_js = 0;
if ($meeting_id) {
    $stmt = $pdo->prepare("SELECT end_time FROM meetings WHERE id = ?");
    $stmt->execute([$meeting_id]);
    $m = $stmt->fetch();
    if ($m) {
        $end_time_js = strtotime($m['end_time']) * 1000;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terima Kasih - Indoarsip</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --google-purple: #673ab7;
            --bg-gray: #f0ebf8;
        }
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg-gray);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 640px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            border-top: 10px solid var(--google-purple);
        }
        .content {
            padding: 24px;
        }
        h1 {
            font-size: 32px;
            margin: 0 0 12px 0;
            font-weight: 400;
        }
        p {
            font-size: 14px;
            color: #202124;
            margin-bottom: 24px;
        }
        .links {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .link {
            color: #1a73e8;
            text-decoration: none;
            font-size: 14px;
            width: fit-content;
        }
        .link:hover {
            text-decoration: underline;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #70757a;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div style="width:100%; max-width:640px;">
        <div class="container">
            <div class="content">
                <h1><?= $type === 'feedback' ? 'Feedback Meeting' : 'Presensi Meeting' ?></h1>
                <p>Jawaban Anda telah direkam.</p>
                <div style="margin-top:20px; color:#5f6368; font-size:14px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-circle-notch fa-spin"></i> Mohon tunggu di halaman ini sampai meeting berakhir untuk mengisi feedback.
                </div>
            </div>
        </div>
        <div class="footer">
            Sistem Presensi Meeting &copy; <?= date('Y') ?> Indoarsip
        </div>
    </div>

    <?php if ($end_time_js > 0 && $type === 'absen'): ?>
    <script>
        // Check meeting status every 5 seconds
        const endTime = <?= $end_time_js ?>;
        const checkEnd = setInterval(() => {
            const now = new Date().getTime();
            if (now >= endTime) {
                clearInterval(checkEnd);
                // Simple redirect to feedback
                window.location.href = 'feedback.php?id=<?= $meeting_id ?>&token=<?= $token ?>';
            }
        }, 5000);
    </script>
    <?php endif; ?>
</body>
</html>
