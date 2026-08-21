<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}

// Handle toggle
if (isset($_POST['user_id']) && isset($_POST['feature'])) {
    $user_id = (int)$_POST['user_id'];
    $feature = $_POST['feature'];
    $is_checked = isset($_POST['toggle_access']);
    $new_status = $is_checked ? 'TRUE' : 'FALSE';

    if ($feature === 'is_owner') {
        $stmt = $pdo->prepare("UPDATE users SET is_owner = $new_status WHERE id = ?");
        $stmt->execute([$user_id]);
    } elseif ($feature === 'full_access') {
        // Toggle all 3 dashboard flags at once
        $stmt = $pdo->prepare("UPDATE users SET can_dashboard = $new_status, can_schedule = $new_status, can_export = $new_status WHERE id = ?");
        $stmt->execute([$user_id]);
    }

    header("Location: grant_access.php?success=1");
    exit;
}

$stmt_users = $pdo->query("
    SELECT u.id, u.name, u.username, u.division, u.role,
           u.can_schedule, u.can_export, u.can_dashboard, u.is_owner,
           b.name AS branch_name
    FROM users u
    LEFT JOIN branches b ON b.id = u.branch_id
    WHERE u.role != 'superadmin'
    ORDER BY u.name ASC
");
$users = $stmt_users->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Grant Access - Indoarsip</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px; width: 18px;
            left: 4px; bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        input:checked + .slider { background-color: var(--primary-color); }
        input:checked + .slider:before { transform: translateX(24px); }
        .switch:hover .slider { box-shadow: 0 0 1px var(--primary-color); }


    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <div class="main-wrapper">
            <?php include 'topbar.php'; ?>
            <main class="content">
                <h1 class="page-title">Manajemen Hak Akses</h1>
                <p class="page-subtitle">Berikan hak Owner Privilege atau Full Dashboard Access kepada karyawan</p>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama Karyawan</th>
                                    <th>Divisi</th>
                                    <th>Cabang</th>
                                    <th style="text-align:center;">Owner Privilege</th>
                                    <th style="text-align:center;">Akses Dashboard</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u):
                                    $has_full_access = $u['can_dashboard'] && $u['can_schedule'] && $u['can_export'];
                                    $is_owner = !empty($u['is_owner']);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($u['name']) ?></strong>
                                    </td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($u['division']) ?></span></td>
                                    <td><span style="font-size: 0.82rem; color: #475569;"><?= htmlspecialchars($u['branch_name'] ?? '-') ?></span></td>

                                    <!-- Owner Privilege Toggle -->
                                    <td style="text-align:center;">
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="feature" value="is_owner">
                                            <label class="switch">
                                                <input type="checkbox" name="toggle_access"
                                                       <?= $is_owner ? 'checked' : '' ?>
                                                       onchange="this.form.submit()">
                                                <span class="slider"></span>
                                            </label>
                                        </form>
                                    </td>

                                    <!-- Full Dashboard Access Toggle -->
                                    <td style="text-align:center;">
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="feature" value="full_access">
                                            <label class="switch">
                                                <input type="checkbox" name="toggle_access"
                                                       <?= $has_full_access ? 'checked' : '' ?>
                                                       onchange="this.form.submit()">
                                                <span class="slider"></span>
                                            </label>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: 'Akses diperbarui' });
    </script>
    <?php endif; ?>
</body>
</html>
