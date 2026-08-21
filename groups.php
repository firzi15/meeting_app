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

// Add Group
if (isset($_POST['add_group'])) {
    $name = trim(strip_tags($_POST['name'] ?? ''));
    $description = trim(strip_tags($_POST['description'] ?? ''));
    
    if (!$name) {
        $error = "Nama grup tidak boleh kosong.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO employee_groups (name, description) VALUES (?, ?) ON CONFLICT (name) DO NOTHING");
            $stmt->execute([$name, $description]);
            if ($stmt->rowCount() > 0) {
                $success = "Grup karyawan berhasil ditambahkan!";
            } else {
                $error = "Grup dengan nama tersebut sudah ada.";
            }
        } catch (Exception $e) {
            $error = "Gagal menambahkan grup: " . $e->getMessage();
        }
    }
}

// Edit Group
if (isset($_POST['edit_group'])) {
    $id = (int)($_POST['group_id'] ?? 0);
    $name = trim(strip_tags($_POST['name'] ?? ''));
    $description = trim(strip_tags($_POST['description'] ?? ''));
    
    if ($id && $name) {
        try {
            // Get old name first to update users if renamed
            $stmt_old = $pdo->prepare("SELECT name FROM employee_groups WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_name = $stmt_old->fetchColumn();

            $stmt = $pdo->prepare("UPDATE employee_groups SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);

            if ($old_name && $old_name !== $name) {
                $stmt_u = $pdo->prepare("UPDATE users SET group_name = ? WHERE group_name = ?");
                $stmt_u->execute([$name, $old_name]);
            }
            $success = "Grup karyawan berhasil diperbarui!";
        } catch (Exception $e) {
            $error = "Gagal memperbarui grup: " . $e->getMessage();
        }
    } else {
        $error = "Data tidak lengkap.";
    }
}

// Delete Group
if (isset($_POST['delete_group'])) {
    $id = (int)($_POST['group_id'] ?? 0);
    if ($id) {
        try {
            $stmt = $pdo->prepare("SELECT name FROM employee_groups WHERE id = ?");
            $stmt->execute([$id]);
            $grp = $stmt->fetch();
            if ($grp) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE group_name = ?");
                $check->execute([$grp['name']]);
                $count = $check->fetchColumn();
                if ($count > 0) {
                    $error = "Grup '{$grp['name']}' masih memiliki {$count} anggota karyawan. Ubah/pindahkan grup karyawan terlebih dahulu di Master Karyawan.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM employee_groups WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Grup karyawan berhasil dihapus!";
                }
            }
        } catch (Exception $e) {
            $error = "Gagal menghapus grup.";
        }
    }
}

// Pagination logic
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_groups = (int)$pdo->query("SELECT COUNT(*) FROM employee_groups")->fetchColumn();
$total_pages = ceil($total_groups / $limit);

// Fetch groups with member count
$stmt = $pdo->prepare("
    SELECT g.*, COUNT(u.id) as member_count 
    FROM employee_groups g
    LEFT JOIN users u ON u.group_name = g.name
    GROUP BY g.id, g.name, g.description, g.created_at
    ORDER BY g.id ASC
    LIMIT ? OFFSET ?
");
$stmt->execute([$limit, $offset]);
$groups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Group - Indoarsip</title>
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
                        <h1 class="page-title">Master Group Karyawan</h1>
                        <p class="page-subtitle">Kelola kelompok jabatan / grup karyawan untuk mempermudah undangan meeting bersama</p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button class="btn-submit" onclick="document.getElementById('addModal').classList.add('active')">
                            <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Tambah Group
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 60px; text-align: center;">No.</th>
                                    <th>Nama Group</th>
                                    <th>Deskripsi</th>
                                    <th style="width: 150px; text-align: center;">Total Anggota</th>
                                    <th style="width: 200px; text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($groups)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <i class="fa-solid fa-folder-open" style="display: block; font-size: 2rem; margin-bottom: 10px;"></i>
                                            Belum ada grup karyawan terdaftar
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($groups as $grp): ?>
                                    <tr>
                                        <td style="text-align: center; color: #94a3b8; font-size: 0.85rem;">
                                            <?= $no++ ?>
                                        </td>
                                        <td>
                                            <strong>
                                                <?php if ($grp['name'] === 'Manager'): ?>
                                                    <span style="display:inline-flex; align-items:center; gap:6px; background:#fef3c7; color:#b45309; border:1px solid #fde68a; padding:4px 10px; border-radius:20px; font-weight:700; font-size:0.85rem;">
                                                        <i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars($grp['name']) ?>
                                                    </span>
                                                <?php elseif ($grp['name'] === 'Kepala Bagian (Kabag)'): ?>
                                                    <span style="display:inline-flex; align-items:center; gap:6px; background:#d1fae5; color:#047857; border:1px solid #a7f3d0; padding:4px 10px; border-radius:20px; font-weight:700; font-size:0.85rem;">
                                                        <i class="fa-solid fa-id-badge"></i> <?= htmlspecialchars($grp['name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="display:inline-flex; align-items:center; gap:6px; background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:4px 10px; border-radius:20px; font-weight:700; font-size:0.85rem;">
                                                        <i class="fa-solid fa-users"></i> <?= htmlspecialchars($grp['name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </strong>
                                        </td>
                                        <td style="color: #64748b; font-size: 0.9rem;">
                                            <?= htmlspecialchars($grp['description'] ?: '-') ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <span style="background: #e0f2fe; color: #0369a1; padding: 4px 12px; border-radius: 12px; font-weight: 700; font-size: 0.85rem;">
                                                <?= (int)$grp['member_count'] ?> Orang
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; justify-content: center; gap: 8px;">
                                                <button type="button" class="btn-action-text" style="background:#f59e0b; color:white; border-radius:6px; padding:6px 12px;" onclick="editGroup(<?= $grp['id'] ?>, '<?= htmlspecialchars(addslashes($grp['name']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($grp['description'] ?? ''), ENT_QUOTES) ?>')" title="Edit Group">
                                                    <i class="fa-solid fa-pen-to-square" style="margin-right: 5px;"></i> Edit
                                                </button>
                                                <button type="button" class="btn-action-text btn-icon-danger" style="background:#ef4444; color:white; border-radius:6px; padding:6px 12px;" onclick="confirmDeleteGroup(<?= $grp['id'] ?>, '<?= htmlspecialchars(addslashes($grp['name']), ENT_QUOTES) ?>')" title="Hapus Group">
                                                    <i class="fa-solid fa-trash" style="margin-right: 5px;"></i> Hapus
                                                </button>
                                            </div>
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

    <!-- Modal Tambah -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-card" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <h3>Tambah Group Baru</h3>
                <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Nama Group</label>
                        <input type="text" name="name" class="form-control" required placeholder="Contoh: Supervisor">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Keterangan grup karyawan..."></textarea>
                    </div>
                    <button type="submit" name="add_group" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px;">
                        Simpan Group
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <h3>Edit Data Group</h3>
                <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST" id="editGroupForm">
                    <input type="hidden" name="group_id" id="edit_group_id">
                    <div class="form-group">
                        <label class="form-label">Nama Group</label>
                        <input type="text" name="name" id="edit_group_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" id="edit_group_desc" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" name="edit_group" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px;">
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Form Tersembunyi untuk Delete -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="group_id" id="delete_group_id">
        <input type="hidden" name="delete_group" value="1">
    </form>

    <script>
        function editGroup(id, name, desc) {
            document.getElementById('edit_group_id').value = id;
            document.getElementById('edit_group_name').value = name;
            document.getElementById('edit_group_desc').value = desc;
            document.getElementById('editModal').classList.add('active');
        }

        function confirmDeleteGroup(id, name) {
            Swal.fire({
                title: 'Hapus Group?',
                text: "Apakah Anda yakin ingin menghapus group '" + name + "'?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_group_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }
    </script>

    <?php if ($success): ?>
        <script>
            Toast.fire({ icon: 'success', title: '<?= htmlspecialchars(addslashes($success)) ?>' });
        </script>
    <?php endif; ?>
    <?php if ($error): ?>
        <script>
            Toast.fire({ icon: 'error', title: '<?= htmlspecialchars(addslashes($error)) ?>' });
        </script>
    <?php endif; ?>
</body>
</html>
