<?php
// login.php
session_start();
require_once 'database.php';

// =============================================
// Auto-create login_attempts table if missing
// =============================================
$pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
    id SERIAL PRIMARY KEY,
    ip_address TEXT NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// =============================================
// RATE LIMITING — Maks 5 percobaan per 15 menit per IP
// =============================================
define('MAX_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$stmt_check = $pdo->prepare("
    SELECT COUNT(*) FROM login_attempts
    WHERE ip_address = ?
      AND attempted_at > NOW() - INTERVAL '" . LOCKOUT_MINUTES . " minutes'
");
$stmt_check->execute([$ip]);
$attempt_count = (int)$stmt_check->fetchColumn();

$is_locked = ($attempt_count >= MAX_ATTEMPTS);
$remaining_lockout = 0;

if ($is_locked) {
    $stmt_first = $pdo->prepare("
        SELECT attempted_at FROM login_attempts
        WHERE ip_address = ?
          AND attempted_at > NOW() - INTERVAL '" . LOCKOUT_MINUTES . " minutes'
        ORDER BY attempted_at ASC LIMIT 1
    ");
    $stmt_first->execute([$ip]);
    $first_attempt = $stmt_first->fetchColumn();
    $unlock_at = strtotime($first_attempt) + (LOCKOUT_MINUTES * 60);
    $remaining_lockout = max(0, ceil(($unlock_at - time()) / 60));
}

$error = '';
$lockout_msg = '';

if ($is_locked) {
    $lockout_msg = "Terlalu banyak percobaan login. Coba lagi dalam {$remaining_lockout} menit.";
}

// =============================================
// PROSES LOGIN
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    $valid = false;
    if ($user) {
        // Coba password_verify dulu (bcrypt), fallback ke plain text (legacy)
        if (password_verify($password, $user['password'])) {
            $valid = true;
        } elseif ($password === $user['password']) {
            // Login berhasil dengan plain text — auto-upgrade ke bcrypt
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user['id']]);
            $valid = true;
        }
    }

    if ($valid) {
        // Bersihkan riwayat gagal login IP ini setelah berhasil
        $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['name']          = $user['name'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['division']      = $user['division'];
        $_SESSION['can_schedule']  = (bool)$user['can_schedule'];
        $_SESSION['can_export']    = (bool)$user['can_export'];
        $_SESSION['can_dashboard'] = (bool)$user['can_dashboard'];
        $_SESSION['photo']         = $user['photo'];
        $_SESSION['branch_id']     = $user['branch_id'] ?? 1;

        if ($user['role'] === 'superadmin' || $user['role'] === 'admin') {
            $_SESSION['admin_branch_id'] = $_SESSION['branch_id'];
        }

        if (isset($_SESSION['redirect_after_login'])) {
            $redirect = $_SESSION['redirect_after_login'];
            unset($_SESSION['redirect_after_login']);
            // Sanitize: only allow relative paths
            $parsedRedirect = parse_url($redirect);
            if (isset($parsedRedirect['host'])) {
                header("Location: index.php");
            } else {
                header("Location: " . $redirect);
            }
        } else {
            if ($_SESSION['role'] === 'superadmin' || $_SESSION['role'] === 'admin' || !empty($_SESSION['can_dashboard'])) {
                header("Location: index.php");
            } else {
                header("Location: my_schedule.php");
            }
        }
        exit;

    } else {
        // Catat percobaan gagal
        $pdo->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)")->execute([$ip]);
        $attempt_count++;
        $remaining = MAX_ATTEMPTS - $attempt_count;

        if ($attempt_count >= MAX_ATTEMPTS) {
            $lockout_msg = "Terlalu banyak percobaan gagal. Akun dikunci selama " . LOCKOUT_MINUTES . " menit.";
            $is_locked = true;
        } else {
            $error = "Username atau password salah! Sisa percobaan: {$remaining}";
        }
    }
}

// Bersihkan riwayat lama (lebih dari 1 jam) — house keeping ringan
$pdo->exec("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL '1 hour'");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Indoarsip</title>
    <link rel="icon" type="image/png" href="logo_login.png">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg-color);
            padding: 20px;
            margin: 0;
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 420px;
        }
        @media (max-width: 480px) {
            .login-card { padding: 30px 20px; }
            .login-card img { height: 100px !important; }
        }
        .login-card .form-group {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 20px;
        }
        .lockout-alert {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="text-align: center; margin-bottom: 50px;">
            <img src="logo_login.png" alt="Logo" style="height: 120px; width: auto; object-fit: contain;" onerror="this.outerHTML='<h1 style=\'font-weight:900; font-size: 2.25rem; letter-spacing: -1.5px; color: #1e293b; margin: 0;\'>INDO<span style=\'color: #e11d48;\'>A</span>RSIP</h1>'">
            <p style="margin-top: 10px; color: #64748b; font-size: 0.875rem;">Sistem Presensi Meeting Karyawan</p>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        </script>

        <?php if ($lockout_msg): ?>
            <div class="lockout-alert">
                <i class="fa-solid fa-lock"></i>
                <span><?= htmlspecialchars($lockout_msg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <script>
                Toast.fire({ icon: 'error', title: '<?= htmlspecialchars($error) ?>' });
            </script>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required placeholder="Masukkan username" <?= $is_locked ? 'disabled' : '' ?>>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Masukkan password" <?= $is_locked ? 'disabled' : '' ?>>
            </div>
            <button type="submit" class="btn-submit" style="width: 100%; padding: 12px; margin-top: 10px;" <?= $is_locked ? 'disabled' : '' ?>>
                <i class="fa-solid <?= $is_locked ? 'fa-lock' : 'fa-right-to-bracket' ?>" style="margin-right: 8px;"></i>
                <?= $is_locked ? 'Akses Dikunci' : 'Masuk Sekarang' ?>
            </button>
        </form>
    </div>
</body>
</html>
