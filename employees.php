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

$success = '';
$error = '';
$current_branch = getCurrentBranchId();
$insert_branch = $current_branch > 0 ? $current_branch : 1;
$branch_condition = $current_branch > 0 ? "AND branch_id = $current_branch" : "";

// Add Employee
if (isset($_POST['add_employee'])) {
    $name = strip_tags($_POST['name'] ?? '');
    $username = strip_tags($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $division = strip_tags($_POST['division'] ?? '');
    
    if (!preg_match('/^[a-zA-Z0-9 \.]+$/', $name) || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = "Nama atau Username mengandung simbol yang dilarang.";
    } elseif ($name && $username && $password) {
        try {
            // Check if username exists
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $error = "Gagal: Username '{$username}' sudah digunakan oleh orang lain.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role, division, branch_id) VALUES (?, ?, ?, 'user', ?, ?)");
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                if ($stmt->execute([$name, $username, $hashed, $division, $insert_branch])) {
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
    $id = $_POST['employee_id'] ?? '';
    $name = strip_tags($_POST['name'] ?? '');
    $username = strip_tags($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $division = strip_tags($_POST['division'] ?? '');
    
    if (!preg_match('/^[a-zA-Z0-9 \.]+$/', $name) || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = "Nama atau Username mengandung simbol yang dilarang.";
    } elseif ($id && $name && $username) {
        try {
            // Check if username exists for other users
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$username, $id]);
            if ($check->fetch()) {
                $error = "Gagal: Username '{$username}' sudah digunakan oleh orang lain.";
            } else {
                if ($password) {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, password = ?, division = ? WHERE id = ? AND role != 'admin' $branch_condition");
                    $stmt->execute([$name, $username, $hashed, $division, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, division = ? WHERE id = ? AND role != 'admin' $branch_condition");
                    $stmt->execute([$name, $username, $division, $id]);
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
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            
            // Periksa relasi di tabel meetings dan meeting_participants
            $check = $pdo->prepare("SELECT COUNT(*) FROM meetings WHERE created_by IN ($inQuery) OR pic_id IN ($inQuery)");
            $check->execute(array_merge($ids, $ids));
            $meetingsCount = $check->fetchColumn();

            $checkPart = $pdo->prepare("SELECT COUNT(*) FROM meeting_participants WHERE user_id IN ($inQuery)");
            $checkPart->execute($ids);
            $partCount = $checkPart->fetchColumn();

            if ($meetingsCount > 0 || $partCount > 0) {
                $error = "Aman DB: Karyawan masih memiliki keterlibatan dalam meeting terdaftar (sebagai pembuat, PIC, atau peserta). Silakan hapus meeting terkait terlebih dahulu.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($inQuery) AND role != 'admin' $branch_condition");
                $stmt->execute($ids);
                $success = count($ids) . " karyawan berhasil dihapus!";
            }
        } catch (Exception $e) {
            $error = "Gagal menghapus karyawan.";
        }
    }
}


// Pagination logic
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin' $branch_condition")->fetchColumn();
$total_pages = ceil($total_users / $limit);

$stmt = $pdo->prepare("SELECT * FROM users WHERE role != 'admin' $branch_condition ORDER BY id ASC LIMIT ? OFFSET ?");
$stmt->execute([$limit, $offset]);
$employees = $stmt->fetchAll();

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
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h1 class="page-title">Master Karyawan</h1>
                        <p class="page-subtitle">Kelola data login karyawan perusahaan</p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="submit" form="bulkDeleteForm" name="bulk_delete_employee" class="btn-submit" id="btnBulkDelete" style="background:#ef4444; display: none;">
                            <i class="fa-solid fa-trash" style="margin-right: 8px;"></i> Hapus Terpilih
                        </button>
                        <button class="btn-submit" onclick="document.getElementById('addModal').classList.add('active')">
                            <i class="fa-solid fa-user-plus" style="margin-right: 8px;"></i> Tambah Karyawan
                        </button>
                    </div>
                </div>

                <form id="bulkDeleteForm" method="POST" onsubmit="return confirmWithSweetAlert(event, 'bulkDeleteForm', 'bulk_delete_employee', 'Hapus semua karyawan yang dipilih?');">
                <div class="card">
                    <div class="table-responsive">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px; text-align: center;">No.</th>
                                        <th>Nama</th>
                                        <th>Username</th>
                                        <th>Password</th>
                                        <th>Divisi</th>
                                        <th style="width: 120px; text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($employees)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                                <i class="fa-solid fa-users-slash" style="display: block; font-size: 2rem; margin-bottom: 10px;"></i>
                                                Belum ada data tersedia
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $no = $offset + 1;
                                        foreach ($employees as $emp): 
                                        ?>
                                        <tr class="selectable-row">
                                            <td style="color: #94a3b8; font-weight: 500; text-align: center;">
                                                <input type="checkbox" name="bulk_ids[]" value="<?= $emp['id'] ?>" class="row-checkbox" style="display:none;">
                                                <?= $no++ ?>
                                            </td>
                                            <td><strong><?= htmlspecialchars($emp['name']) ?></strong></td>
                                            <td><code><?= htmlspecialchars($emp['username']) ?></code></td>
                                            <td><span style="font-family: monospace; background: #f1f5f9; padding: 2px 8px; border-radius: 4px; color: #94a3b8; letter-spacing: 2px;">••••••••</span></td>
                                            <td><span class="badge" style="background:#e0e7ff; color:#4338ca;"><?= htmlspecialchars($emp['division'] ?: '-') ?></span></td>
                                            <td style="text-align: center;">
                                                <div style="display: flex; justify-content: center; gap: 8px;">
                                                    <button type="button" class="btn-action-text" style="background:#f59e0b; color:white; border-radius:6px; padding:6px 12px;" onclick="editEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['name']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($emp['username']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($emp['division'] ?: ''), ENT_QUOTES) ?>')" title="Edit Karyawan">
                                                        <i class="fa-solid fa-pen-to-square" style="margin-right: 5px;"></i> Edit
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div style="padding: 15px; display: flex; justify-content: center; gap: 10px;">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>" class="btn-action-text <?= $i === $page ? 'btn-view-blue' : '' ?>" style="text-decoration: none; border: 1px solid #e2e8f0; padding: 5px 10px;"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
                </form>
            </main>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Modal Tambah -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Tambah Karyawan</h3>
                <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-signature" style="color: var(--primary-color); margin-right: 8px;"></i> Nama Lengkap</label>
                        <input type="text" name="name" class="form-control" required placeholder="Nama lengkap karyawan">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-user-tag" style="color: var(--primary-color); margin-right: 8px;"></i> Username</label>
                        <input type="text" name="username" class="form-control" required placeholder="Username untuk login">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Masukkan password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Divisi</label>
                        <select name="division" class="form-control" required>
                            <option value="">-- Pilih Divisi --</option>
                            <?php foreach($divisions as $div): ?>
                                <option value="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_employee" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px;">
                        Simpan Karyawan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Edit Karyawan</h3>
                <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <input type="hidden" name="employee_id" id="edit_emp_id">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-signature" style="color: var(--primary-color); margin-right: 8px;"></i> Nama Lengkap</label>
                        <input type="text" name="name" id="edit_emp_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-user-tag" style="color: var(--primary-color); margin-right: 8px;"></i> Username</label>
                        <input type="text" name="username" id="edit_emp_username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password (Opsional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ganti">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Divisi</label>
                        <select name="division" id="edit_emp_division" class="form-control" required>
                            <option value="">-- Pilih Divisi --</option>
                            <?php foreach($divisions as $div): ?>
                                <option value="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="edit_employee" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px;">
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>


    <script>
        function editEmployee(id, name, username, division) {
            document.getElementById('edit_emp_id').value = id;
            document.getElementById('edit_emp_name').value = name;
            document.getElementById('edit_emp_username').value = username;
            document.getElementById('edit_emp_division').value = division;
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
        Toast.fire({ icon: 'success', title: <?= json_encode($success) ?> });
    </script>
    <?php endif; ?>
    <?php if ($error): ?>
    <script>
        Toast.fire({ icon: 'error', title: <?= json_encode($error) ?> });
    </script>
    <?php endif; ?>
</body>
</html>
