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

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

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

    $ret_page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $ret_search = isset($_POST['search']) ? $_POST['search'] : '';
    header("Location: grant_access.php?success=1&page=" . $ret_page . ($ret_search ? "&search=" . urlencode($ret_search) : ""));
    exit;
}

$current_branch = getCurrentBranchId();
$where_clauses = ["u.role != 'superadmin'"];
$params = [];

if ($current_branch > 0) {
    $where_clauses[] = "u.branch_id = ?";
    $params[] = $current_branch;
}

if ($search !== '') {
    $where_clauses[] = "(u.name ILIKE ? OR u.username ILIKE ? OR u.division ILIKE ? OR u.nik ILIKE ? OR b.name ILIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

$where = "WHERE " . implode(' AND ', $where_clauses);

// Total count
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM users u
    LEFT JOIN branches b ON b.id = u.branch_id
    $where
");
$count_stmt->execute($params);
$total_users = (int)$count_stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Fetch users
$query = "
    SELECT u.id, u.nik, u.name, u.username, u.division, u.role, u.jabatan, u.group_name,
           u.can_schedule, u.can_export, u.can_dashboard, u.is_owner,
           b.name AS branch_name
    FROM users u
    LEFT JOIN branches b ON b.id = u.branch_id
    $where
    ORDER BY u.id ASC
    LIMIT $limit OFFSET $offset
";
$stmt_users = $pdo->prepare($query);
$stmt_users->execute($params);
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
        input:checked + .slider { background-color: var(--primary-color, #4f46e5); }
        input:checked + .slider:before { transform: translateX(24px); }
        .switch:hover .slider { box-shadow: 0 0 1px var(--primary-color, #4f46e5); }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <div class="main-wrapper">
            <?php include 'topbar.php'; ?>
            <main class="content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h1 class="page-title">Manajemen Hak Akses</h1>
                        <p class="page-subtitle" style="margin-bottom: 0;">Berikan hak Owner Privilege atau Full Dashboard Access kepada karyawan</p>
                    </div>

                    <!-- Search Filter Form -->
                    <form method="GET" style="display: flex; gap: 8px; align-items: center;">
                        <div style="position: relative; width: 280px;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.875rem;"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, NIK, divisi..." class="form-control" style="padding-left: 36px; height: 40px; font-size: 0.875rem;">
                        </div>
                        <button type="submit" class="btn-submit" style="padding: 0 16px; height: 40px; border-radius: 8px; font-size: 0.875rem;">
                            Cari
                        </button>
                        <?php if ($search !== ''): ?>
                            <a href="grant_access.php" class="btn-action-text" style="padding: 8px 12px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 600;">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-fixed">
                            <thead>
                                <tr>
                                    <th style="width: 44px; text-align: center;">No.</th>
                                    <th style="width: 30%;">Nama Karyawan</th>
                                    <th style="width: 22%;">Divisi</th>
                                    <th style="width: 16%;">Cabang</th>
                                    <th style="text-align:center; width: 140px;">Owner Privilege</th>
                                    <th style="text-align:center; width: 150px;">Akses Dashboard</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <i class="fa-solid fa-user-slash" style="display: block; font-size: 2rem; margin-bottom: 10px;"></i>
                                            Tidak ada karyawan ditemukan
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = $offset + 1; foreach ($users as $u):
                                        $has_full_access = $u['can_dashboard'] && $u['can_schedule'] && $u['can_export'];
                                        $is_owner = !empty($u['is_owner']);
                                    ?>
                                    <tr>
                                        <td style="text-align: center; color: #94a3b8; font-size: 0.85rem;"><?= $no++ ?></td>
                                        <td>
                                            <div class="name-truncate" style="font-weight: 700; color: #1e293b; line-height: 1.3;" title="<?= htmlspecialchars($u['name']) ?>"><?= htmlspecialchars($u['name']) ?></div>
                                            <?php if (!empty($u['jabatan'])): ?>
                                                <div class="truncate-cell" style="font-size: 0.75rem; color: #64748b; margin-top: 3px; line-height: 1.3;" title="<?= htmlspecialchars($u['jabatan']) ?>"><?= htmlspecialchars($u['jabatan']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-info badge-truncate" style="cursor:default;" title="<?= htmlspecialchars($u['division'] ?: 'Umum') ?>"><?= htmlspecialchars($u['division'] ?: 'Umum') ?></span></td>
                                        <td><span style="font-size: 0.82rem; color: #475569; font-weight: 500;"><?= htmlspecialchars($u['branch_name'] ?? '-') ?></span></td>

                                        <!-- Owner Privilege Toggle -->
                                        <td style="text-align:center;">
                                            <form method="POST" style="margin:0; display:flex; justify-content:center;">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="feature" value="is_owner">
                                                <input type="hidden" name="page" value="<?= $page ?>">
                                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
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
                                            <form method="POST" style="margin:0; display:flex; justify-content:center;">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="feature" value="full_access">
                                                <input type="hidden" name="page" value="<?= $page ?>">
                                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
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
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php renderPagination($page, $total_pages); ?>
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
        Toast.fire({ icon: 'success', title: 'Akses berhasil diperbarui' });
    </script>
    <?php endif; ?>
</body>
</html>
