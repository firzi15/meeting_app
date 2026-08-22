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

$success = '';
$error = '';
$current_branch = getCurrentBranchId();
$insert_branch = $current_branch > 0 ? $current_branch : 1;
$branch_condition = $current_branch > 0 ? "AND branch_id = $current_branch" : "";

// Fetch all available groups
$stmt_all_groups = $pdo->query("SELECT * FROM employee_groups ORDER BY name ASC");
$all_groups = $stmt_all_groups->fetchAll();

// Fetch branches
$stmt_all_branches = $pdo->query("SELECT * FROM branches ORDER BY name ASC");
$branches = $stmt_all_branches->fetchAll();

// Add Employee
if (isset($_POST['add_employee'])) {
    $nik = strip_tags(trim($_POST['nik'] ?? ''));
    $name = strip_tags(trim($_POST['name'] ?? ''));
    $username = strip_tags(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $jabatan = strip_tags(trim($_POST['jabatan'] ?? ''));
    $group_name = strip_tags(trim($_POST['group_name'] ?? 'Staff'));
    $division = strip_tags(trim($_POST['division'] ?? ''));
    $branch_id = (int)($_POST['branch_id'] ?? $insert_branch);
    if ($branch_id <= 0) $branch_id = 1;

    if (!in_array($role, ['superadmin', 'admin', 'user'])) {
        $role = 'user';
    }

    if (!preg_match('/^[a-zA-Z0-9 \.\,\-\/\(\)]+$/', $name) || !preg_match('/^[a-zA-Z0-9_\.\-]+$/', $username)) {
        $error = "Nama atau Username mengandung simbol yang dilarang.";
    } elseif ($name && $username && $password) {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $error = "Gagal: Username '{$username}' sudah digunakan oleh orang lain.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (nik, name, username, password, role, jabatan, group_name, division, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                if ($stmt->execute([$nik, $name, $username, $hashed, $role, $jabatan, $group_name, $division, $branch_id])) {
                    $success = "Karyawan berhasil ditambahkan!";
                }
            }
        } catch (Exception $e) {
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}

// Edit Employee
if (isset($_POST['edit_employee'])) {
    $id = (int)($_POST['employee_id'] ?? 0);
    $nik = strip_tags(trim($_POST['nik'] ?? ''));
    $name = strip_tags(trim($_POST['name'] ?? ''));
    $username = strip_tags(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $jabatan = strip_tags(trim($_POST['jabatan'] ?? ''));
    $group_name = strip_tags(trim($_POST['group_name'] ?? 'Staff'));
    $division = strip_tags(trim($_POST['division'] ?? ''));
    $branch_id = (int)($_POST['branch_id'] ?? $insert_branch);
    if ($branch_id <= 0) $branch_id = 1;

    if (!in_array($role, ['superadmin', 'admin', 'user'])) {
        $role = 'user';
    }

    if (!preg_match('/^[a-zA-Z0-9 \.\,\-\/\(\)]+$/', $name) || !preg_match('/^[a-zA-Z0-9_\.\-]+$/', $username)) {
        $error = "Nama atau Username mengandung simbol yang dilarang.";
    } elseif ($id && $name && $username) {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$username, $id]);
            if ($check->fetch()) {
                $error = "Gagal: Username '{$username}' sudah digunakan oleh orang lain.";
            } else {
                if ($password) {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET nik = ?, name = ?, username = ?, password = ?, role = ?, jabatan = ?, group_name = ?, division = ?, branch_id = ? WHERE id = ?");
                    $stmt->execute([$nik, $name, $username, $hashed, $role, $jabatan, $group_name, $division, $branch_id, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET nik = ?, name = ?, username = ?, role = ?, jabatan = ?, group_name = ?, division = ?, branch_id = ? WHERE id = ?");
                    $stmt->execute([$nik, $name, $username, $role, $jabatan, $group_name, $division, $branch_id, $id]);
                }
                $success = "Data karyawan berhasil diperbarui!";
            }
        } catch (Exception $e) {
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}

// Bulk Delete Employee
if (isset($_POST['bulk_delete_employee'])) {
    $ids = $_POST['bulk_ids'] ?? [];
    if (!empty($ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $check = $pdo->prepare("SELECT COUNT(*) FROM meetings WHERE created_by IN ($placeholders) OR pic_id IN ($placeholders)");
            $check->execute(array_merge($ids, $ids));
            $meetingsCount = $check->fetchColumn();

            $checkPart = $pdo->prepare("SELECT COUNT(*) FROM meeting_participants WHERE user_id IN ($placeholders)");
            $checkPart->execute($ids);
            $partCount = $checkPart->fetchColumn();

            if ($meetingsCount > 0 || $partCount > 0) {
                $error = "Aman DB: Karyawan masih memiliki keterlibatan dalam meeting terdaftar (sebagai pembuat, PIC, atau peserta). Silakan hapus meeting terkait terlebih dahulu.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role != 'superadmin'");
                $stmt->execute($ids);
                $success = count($ids) . " karyawan berhasil dihapus!";
            }
        } catch (Exception $e) {
            $error = "Gagal menghapus karyawan: " . $e->getMessage();
        }
    }
}

// Filter & Search Logic
$search = trim($_GET['search'] ?? '');
$filter_group = trim($_GET['filter_group'] ?? '');

$where_clauses = ["u.role != 'superadmin'"];
$params = [];

if ($current_branch > 0) {
    $where_clauses[] = "u.branch_id = ?";
    $params[] = $current_branch;
}

if ($search !== '') {
    $where_clauses[] = "(u.name ILIKE ? OR u.username ILIKE ? OR u.nik ILIKE ? OR u.jabatan ILIKE ? OR u.division ILIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if ($filter_group !== '') {
    $where_clauses[] = "u.group_name = ?";
    $params[] = $filter_group;
}

$where_sql = implode(' AND ', $where_clauses);

// Pagination logic
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where_sql");
$stmt_count->execute($params);
$total_users = $stmt_count->fetchColumn();
$total_pages = ceil($total_users / $limit);

$stmt_emp = $pdo->prepare("
    SELECT u.*, b.name as branch_name 
    FROM users u 
    LEFT JOIN branches b ON b.id = u.branch_id 
    WHERE $where_sql 
    ORDER BY u.id ASC 
    LIMIT ? OFFSET ?
");
$exec_params = array_merge($params, [$limit, $offset]);
$stmt_emp->execute($exec_params);
$employees = $stmt_emp->fetchAll();

$divisions = $pdo->query("SELECT name FROM divisions WHERE 1=1 $branch_condition ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Karyawan - Indoarsip</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <div class="main-wrapper">
            <?php include 'topbar.php'; ?>
            <main class="content">
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h1 class="page-title">Master Karyawan & Jabatan</h1>
                        <p class="page-subtitle">Kelola data karyawan, jabatan, tag grouping (Manager / Kabag / Staff), serta role akses</p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <a href="export_employees.php" class="btn-submit" style="background:#10b981; text-decoration:none;">
                            <i class="fa-solid fa-file-excel" style="margin-right: 6px;"></i> Ekspor Excel
                        </a>
                        <button type="submit" form="bulkDeleteForm" name="bulk_delete_employee" class="btn-submit" id="btnBulkDelete" style="background:#ef4444; display: none;">
                            <i class="fa-solid fa-trash" style="margin-right: 6px;"></i> Hapus Terpilih
                        </button>
                        <button class="btn-submit" onclick="document.getElementById('addModal').classList.add('active')">
                            <i class="fa-solid fa-user-plus" style="margin-right: 6px;"></i> Tambah Karyawan
                        </button>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="card" style="margin-bottom: 20px; padding: 15px 20px;">
                    <form method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 220px; position: relative;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Cari NIK, Nama, Username, Jabatan..." style="padding-left: 36px; width: 100%;">
                        </div>
                        <div style="min-width: 180px;">
                            <select name="filter_group" class="form-control" onchange="this.form.submit()">
                                <option value="">-- Semua Group --</option>
                                <option value="Manager" <?= $filter_group === 'Manager' ? 'selected' : '' ?>>👔 Manager</option>
                                <option value="Kepala Bagian (Kabag)" <?= $filter_group === 'Kepala Bagian (Kabag)' ? 'selected' : '' ?>>📋 Kepala Bagian (Kabag)</option>
                                <option value="Staff" <?= $filter_group === 'Staff' ? 'selected' : '' ?>>👥 Staff</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-submit" style="padding: 10px 18px;">Filter</button>
                        <?php if ($search !== '' || $filter_group !== ''): ?>
                            <a href="employees.php" class="btn-action-text" style="background:#e2e8f0; color:#475569; text-decoration:none; padding: 10px 15px; border-radius: 6px;">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <form id="bulkDeleteForm" method="POST" onsubmit="return confirmWithSweetAlert(event, 'bulkDeleteForm', 'bulk_delete_employee', 'Hapus semua karyawan yang dipilih?');">
                <div class="card">
                    <div class="table-responsive">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px; text-align: center;">No.</th>
                                        <th style="width: 100px;">NIK</th>
                                        <th>Nama Lengkap</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Jabatan</th>
                                        <th>Group</th>
                                        <th>Divisi</th>
                                        <th style="width: 100px; text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($employees)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; padding: 40px; color: #94a3b8;">
                                                <i class="fa-solid fa-users-slash" style="display: block; font-size: 2rem; margin-bottom: 10px;"></i>
                                                Belum ada data karyawan
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $no = $offset + 1;
                                        foreach ($employees as $emp): 
                                            $grp = $emp['group_name'] ?? 'Staff';
                                            $grp_color = '#64748b';
                                            $grp_bg = '#f1f5f9';
                                            $grp_icon = 'fa-user';
                                            if ($grp === 'Manager') {
                                                $grp_color = '#b45309';
                                                $grp_bg = '#fef3c7';
                                                $grp_icon = 'fa-user-tie';
                                            } elseif ($grp === 'Kepala Bagian (Kabag)') {
                                                $grp_color = '#047857';
                                                $grp_bg = '#d1fae5';
                                                $grp_icon = 'fa-id-badge';
                                            }
                                            $grp_display = ($grp === 'Kepala Bagian (Kabag)') ? 'Kabag' : $grp;

                                            $role_badge = '<span class="badge" style="background:#f1f5f9; color:#475569;">User</span>';
                                            if ($emp['role'] === 'superadmin') {
                                                $role_badge = '<span class="badge" style="background:#ede9fe; color:#6d28d9; font-weight:700;">Superadmin</span>';
                                            } elseif ($emp['role'] === 'admin') {
                                                $role_badge = '<span class="badge" style="background:#dbeafe; color:#1d4ed8; font-weight:600;">Admin</span>';
                                            }
                                        ?>
                                        <tr class="selectable-row">
                                            <td style="color: #94a3b8; font-weight: 500; text-align: center;">
                                                <input type="checkbox" name="bulk_ids[]" value="<?= $emp['id'] ?>" class="row-checkbox" style="display:none;">
                                                <?= htmlspecialchars($no++) ?>
                                            </td>
                                            <td><code style="font-size:0.85rem; color:#475569; font-weight:600;"><?= htmlspecialchars($emp['nik'] ?: '-') ?></code></td>
                                            <td><strong><?= htmlspecialchars($emp['name']) ?></strong></td>
                                            <td><code><?= htmlspecialchars($emp['username']) ?></code></td>
                                            <td><?= $role_badge ?></td>
                                            <td><span class="truncate-cell" style="font-size:0.85rem; color:#334155; font-weight:500;" title="<?= htmlspecialchars($emp['jabatan'] ?: '-') ?>"><?= htmlspecialchars($emp['jabatan'] ?: '-') ?></span></td>
                                            <td>
                                                <span class="badge" title="<?= htmlspecialchars($grp) ?>" style="background:<?= $grp_bg ?>; color:<?= $grp_color ?>; font-weight:600; cursor:default;">
                                                    <i class="fa-solid <?= $grp_icon ?>"></i> <?= htmlspecialchars($grp_display) ?>
                                                </span>
                                            </td>
                                            <td><span class="badge badge-truncate" style="background:#e0e7ff; color:#4338ca; cursor:default;" title="<?= htmlspecialchars($emp['division'] ?: '-') ?>"><?= htmlspecialchars($emp['division'] ?: '-') ?></span></td>
                                            <td style="text-align: center;">
                                                <button type="button" class="btn-action-text" style="background:#f59e0b; color:white; border-radius:6px; padding:6px 12px;" 
                                                    onclick="editEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['nik'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($emp['name']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($emp['username']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($emp['role'] ?? 'user'), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($emp['jabatan'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($emp['group_name'] ?? 'Staff'), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($emp['division'] ?: ''), ENT_QUOTES) ?>', <?= (int)($emp['branch_id'] ?? 1) ?>)" title="Edit Karyawan">
                                                    <i class="fa-solid fa-pen-to-square" style="margin-right: 4px;"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php renderPagination($page, $total_pages, ['search' => $search, 'filter_group' => $filter_group]); ?>
                </div>
                </form>
            </main>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Modal Tambah -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 550px;">
            <div class="modal-header">
                <h3>Tambah Karyawan Baru</h3>
                <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-id-card" style="color: var(--primary-color); margin-right: 6px;"></i> NIK</label>
                            <input type="text" name="nik" class="form-control" placeholder="Misal: 120004">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-signature" style="color: var(--primary-color); margin-right: 6px;"></i> Nama Lengkap</label>
                            <input type="text" name="name" class="form-control" required placeholder="Nama lengkap karyawan">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-user-tag" style="color: var(--primary-color); margin-right: 6px;"></i> Username</label>
                            <input type="text" name="username" class="form-control" required placeholder="Username login">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-lock" style="color: var(--primary-color); margin-right: 6px;"></i> Password</label>
                            <input type="password" name="password" class="form-control" required placeholder="Password">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-shield-halved" style="color: var(--primary-color); margin-right: 6px;"></i> Role Access</label>
                            <select name="role" class="form-control" required>
                                <option value="user">User (Presensi / Kalender)</option>
                                <option value="admin">Admin (Request / Kelola Meeting)</option>
                                <option value="superadmin">Superadmin (Akses Penuh)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-layer-group" style="color: var(--primary-color); margin-right: 6px;"></i> Group Jabatan</label>
                            <select name="group_name" class="form-control" required>
                                <option value="Manager">👔 Manager</option>
                                <option value="Kepala Bagian (Kabag)">📋 Kepala Bagian (Kabag)</option>
                                <option value="Staff" selected>👥 Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-briefcase" style="color: var(--primary-color); margin-right: 6px;"></i> Jabatan Lengkap</label>
                        <input type="text" name="jabatan" class="form-control" placeholder="Contoh: IT MANAGER / KEPALA BAGIAN ACCOUNTING">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">Divisi</label>
                            <select name="division" class="form-control" required>
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach($divisions as $div): ?>
                                    <option value="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cabang</label>
                            <select name="branch_id" class="form-control" required>
                                <?php foreach($branches as $br): ?>
                                    <option value="<?= $br['id'] ?>" <?= $br['id'] == $insert_branch ? 'selected' : '' ?>><?= htmlspecialchars($br['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="add_employee" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px; margin-top: 10px;">
                        Simpan Karyawan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 550px;">
            <div class="modal-header">
                <h3>Edit Data Karyawan</h3>
                <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <input type="hidden" name="employee_id" id="edit_emp_id">
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-id-card" style="color: var(--primary-color); margin-right: 6px;"></i> NIK</label>
                            <input type="text" name="nik" id="edit_emp_nik" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-signature" style="color: var(--primary-color); margin-right: 6px;"></i> Nama Lengkap</label>
                            <input type="text" name="name" id="edit_emp_name" class="form-control" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-user-tag" style="color: var(--primary-color); margin-right: 6px;"></i> Username</label>
                            <input type="text" name="username" id="edit_emp_username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-lock" style="color: var(--primary-color); margin-right: 6px;"></i> Password (Opsional)</label>
                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ganti">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-shield-halved" style="color: var(--primary-color); margin-right: 6px;"></i> Role Access</label>
                            <select name="role" id="edit_emp_role" class="form-control" required>
                                <option value="user">User (Presensi / Kalender)</option>
                                <option value="admin">Admin (Request / Kelola Meeting)</option>
                                <option value="superadmin">Superadmin (Akses Penuh)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-layer-group" style="color: var(--primary-color); margin-right: 6px;"></i> Group Jabatan</label>
                            <select name="group_name" id="edit_emp_group" class="form-control" required>
                                <option value="Manager">👔 Manager</option>
                                <option value="Kepala Bagian (Kabag)">📋 Kepala Bagian (Kabag)</option>
                                <option value="Staff">👥 Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-briefcase" style="color: var(--primary-color); margin-right: 6px;"></i> Jabatan Lengkap</label>
                        <input type="text" name="jabatan" id="edit_emp_jabatan" class="form-control">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">Divisi</label>
                            <select name="division" id="edit_emp_division" class="form-control" required>
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach($divisions as $div): ?>
                                    <option value="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cabang</label>
                            <select name="branch_id" id="edit_emp_branch" class="form-control" required>
                                <?php foreach($branches as $br): ?>
                                    <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="edit_employee" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px; margin-top: 10px;">
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editEmployee(id, nik, name, username, role, jabatan, groupName, division, branchId) {
            document.getElementById('edit_emp_id').value = id;
            document.getElementById('edit_emp_nik').value = nik;
            document.getElementById('edit_emp_name').value = name;
            document.getElementById('edit_emp_username').value = username;
            document.getElementById('edit_emp_role').value = role || 'user';
            document.getElementById('edit_emp_jabatan').value = jabatan || '';
            document.getElementById('edit_emp_group').value = groupName || 'Staff';
            document.getElementById('edit_emp_division').value = division || '';
            document.getElementById('edit_emp_branch').value = branchId || 1;
            document.getElementById('editModal').classList.add('active');
        }

        let lastSelectedIndex = -1;

        document.addEventListener('DOMContentLoaded', () => {
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

    <?php if ($success): ?>
    <script>
        Toast.fire({ icon: 'success', title: <?= json_encode(htmlspecialchars($success)) ?> });
    </script>
    <?php endif; ?>
    <?php if ($error): ?>
    <script>
        Toast.fire({ icon: 'error', title: <?= json_encode(htmlspecialchars($error)) ?> });
    </script>
    <?php endif; ?>
</body>
</html>
