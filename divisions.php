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
$where_branch = $current_branch > 0 ? "WHERE branch_id = $current_branch" : "WHERE 1=1";

// Add Division
if (isset($_POST['add_division'])) {
    $name = strip_tags($_POST['name'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9 \.]+$/', $name)) {
        $error = "Input tidak boleh mengandung simbol khusus.";
    } elseif ($name) {
        try {
            $stmt = $pdo->prepare("INSERT INTO divisions (name, branch_id) VALUES (?, ?)");
            $stmt->execute([$name, $insert_branch]);
            $success = "Divisi berhasil ditambahkan!";
        } catch (Exception $e) {
            $error = "Gagal menambahkan divisi: " . $e->getMessage();
        }
    }
}

// Edit Division
if (isset($_POST['edit_division'])) {
    $id = $_POST['division_id'] ?? '';
    $name = strip_tags($_POST['name'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9 \.]+$/', $name)) {
        $error = "Input tidak boleh mengandung simbol khusus.";
    } elseif ($id && $name) {
        try {
            $stmt = $pdo->prepare("UPDATE divisions SET name = ? WHERE id = ? $branch_condition");
            $stmt->execute([$name, $id]);
            $success = "Divisi berhasil diperbarui!";
        } catch (Exception $e) {
            $error = "Gagal memperbarui divisi.";
        }
    }
}

// Delete Division
if (isset($_POST['delete_division'])) {
    $id = $_POST['division_id'] ?? '';
    if ($id) {
        try {
            $stmt = $pdo->prepare("SELECT name FROM divisions WHERE id = ?");
            $stmt->execute([$id]);
            $div = $stmt->fetch();
            if ($div) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE division = ?");
                $check->execute([$div['name']]);
                if ($check->fetchColumn() > 0) {
                    $error = "Aman DB: Divisi '{$div['name']}' masih terkait dengan karyawan. Hapus/ubah karyawan tersebut di Master Karyawan terlebih dahulu.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM divisions WHERE id = ? $branch_condition");
                    $stmt->execute([$id]);
                    $success = "Divisi berhasil dihapus!";
                }
            }
        } catch (Exception $e) {
            $error = "Gagal menghapus divisi.";
        }
    }
}

// Bulk Delete Division
if (isset($_POST['bulk_delete_division'])) {
    $ids = $_POST['bulk_ids'] ?? [];
    if (!empty($ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT name FROM divisions WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if ($names) {
                $placeholdersNames = implode(',', array_fill(0, count($names), '?'));
                $check = $pdo->prepare("SELECT DISTINCT division FROM users WHERE division IN ($placeholdersNames)");
                $check->execute($names);
                $used = $check->fetchAll(PDO::FETCH_COLUMN);
                
                if (count($used) > 0) {
                    $usedStr = implode(", ", $used);
                    $error = "Aman DB: Divisi ($usedStr) masih terkait dengan karyawan. Silakan ubah/hapus karyawan tersebut di Master Karyawan.";
                } else {
                    $branch_clause = "";
                    $params = $ids;
                    if ($current_branch > 0) {
                        $branch_clause = " AND branch_id = ?";
                        $params[] = $current_branch;
                    }
                    $stmt = $pdo->prepare("DELETE FROM divisions WHERE id IN ($placeholders)$branch_clause");
                    $stmt->execute($params);
                    $success = count($ids) . " divisi berhasil dihapus!";
                }
            }
        } catch (Exception $e) {
            $error = "Gagal menghapus divisi.";
        }
    }
}

// Pagination logic
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_divisions = $pdo->query("SELECT COUNT(*) FROM divisions $where_branch")->fetchColumn();
$total_pages = ceil($total_divisions / $limit);

$stmt = $pdo->prepare("SELECT * FROM divisions $where_branch ORDER BY id ASC LIMIT ? OFFSET ?");
$stmt->execute([$limit, $offset]);
$divisions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Divisi - Indoarsip</title>
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
                        <h1 class="page-title">Master Divisi</h1>
                        <p class="page-subtitle">Kelola daftar divisi meeting perusahaan</p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="submit" form="bulkDeleteForm" name="bulk_delete_division" class="btn-submit" id="btnBulkDelete" style="background:#ef4444; display: none;">
                            <i class="fa-solid fa-trash" style="margin-right: 8px;"></i> Hapus Terpilih
                        </button>
                        <button class="btn-submit" onclick="document.getElementById('addModal').classList.add('active')">
                            <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Tambah Divisi
                        </button>
                    </div>
                </div>

                <form id="bulkDeleteForm" method="POST" onsubmit="return confirmWithSweetAlert(event, 'bulkDeleteForm', 'bulk_delete_division', 'Hapus semua divisi yang dipilih?');">
                <div class="card">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 60px; text-align: center;">No.</th>
                                    <th>Nama Divisi</th>
                                    <th style="width: 200px; text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($divisions)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <i class="fa-solid fa-folder-open" style="display: block; font-size: 2rem; margin-bottom: 10px;"></i>
                                            Belum ada data tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = $offset + 1; foreach ($divisions as $division): ?>
                                    <tr class="selectable-row">
                                        <td style="text-align: center; color: #94a3b8; font-size: 0.8rem;">
                                            <input type="checkbox" name="bulk_ids[]" value="<?= $division['id'] ?>" class="row-checkbox" style="display:none;">
                                            <?= htmlspecialchars($no++) ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($division['name']) ?></strong></td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; justify-content: center; gap: 8px;">
                                                <button type="button" class="btn-action-text" style="background:#f59e0b; color:white; border-radius:6px; padding:6px 12px;" onclick="editDivision(<?= $division['id'] ?>, '<?= htmlspecialchars(addslashes($division['name']), ENT_QUOTES) ?>')" title="Edit Divisi">
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
        <div class="modal-card" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <h3>Tambah Divisi Baru</h3>
                <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Nama Divisi</label>
                        <input type="text" name="name" class="form-control" required placeholder="Contoh: Ruang Mezanine">
                    </div>
                    <button type="submit" name="add_division" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px;">
                        Simpan Divisi
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <h3>Edit Data Divisi</h3>
                <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <input type="hidden" name="division_id" id="edit_division_id">
                    <div class="form-group">
                        <label class="form-label">Nama Divisi</label>
                        <input type="text" name="name" id="edit_division_name" class="form-control" required>
                    </div>
                    <button type="submit" name="edit_division" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px;">
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>

    
    

    <script>
        function editDivision(id, name) {
            document.getElementById('edit_division_id').value = id;
            document.getElementById('edit_division_name').value = name;
            document.getElementById('editModal').classList.add('active');
        }

        let qrcodeObj = null;
        function showBarcode(id, name) {
            document.getElementById('barcodeDivisionName').textContent = name;
            const pathArray = window.location.pathname.split('/');
            pathArray.pop(); // remove divisions.php
            const basePath = pathArray.join('/');
            const baseUrl = window.location.protocol + "//" + window.location.host + basePath;
            const link = baseUrl + "/attendance.php?division_id=" + id;
            document.getElementById('barcodeLink').textContent = link;

            document.getElementById('qrcode').innerHTML = "";
            qrcodeObj = new QRCode(document.getElementById("qrcode"), {
                text: link,
                width: 200,
                height: 200
            });

            document.getElementById('barcodeModal').classList.add('active');
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
        Toast.fire({ icon: 'success', title: '<?= htmlspecialchars($success) ?>' });
    </script>
    <?php endif; ?>
    <?php if ($error): ?>
    <script>
        Toast.fire({ icon: 'error', title: '<?= htmlspecialchars($error) ?>' });
    </script>
    <?php endif; ?>
</body>
</html>
