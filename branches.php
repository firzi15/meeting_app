<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$success = '';
$error = '';

// Add branch
if (isset($_POST['add_branch'])) {
    $name = strip_tags($_POST['name'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9 \.]+$/', $name)) {
        $error = "Input tidak boleh mengandung simbol khusus.";
    } elseif ($name) {
        try {
            $stmt = $pdo->prepare("INSERT INTO branches (name) VALUES (?)");
            $stmt->execute([$name]);
            $success = "Cabang berhasil ditambahkan!";
        } catch (Exception $e) {
            $error = "Gagal menambahkan Cabang: " . $e->getMessage();
        }
    }
}

// Edit branch
if (isset($_POST['edit_branch'])) {
    $id = $_POST['branch_id'] ?? '';
    $name = strip_tags($_POST['name'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9 \.]+$/', $name)) {
        $error = "Input tidak boleh mengandung simbol khusus.";
    } elseif ($id && $name) {
        try {
            $stmt = $pdo->prepare("UPDATE branches SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            $success = "Cabang berhasil diperbarui!";
        } catch (Exception $e) {
            $error = "Gagal memperbarui Cabang.";
        }
    }
}

// Delete branch
if (isset($_POST['delete_branch'])) {
    $id = $_POST['branch_id'] ?? '';
    if ($id) {
        try {
            $stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
            $stmt->execute([$id]);
            $div = $stmt->fetch();
            if ($div) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ?");
                $check->execute([$id]);
                if ($check->fetchColumn() > 0) {
                    $error = "Aman DB: Cabang '{$div['name']}' masih terkait dengan karyawan. Hapus/ubah karyawan tersebut di Master Karyawan terlebih dahulu.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Cabang berhasil dihapus!";
                }
            }
        } catch (Exception $e) {
            $error = "Gagal menghapus Cabang.";
        }
    }
}

// Bulk Delete branch
if (isset($_POST['bulk_delete_branch'])) {
    $ids = $_POST['bulk_ids'] ?? [];
    if (!empty($ids)) {
        try {
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT name FROM branches WHERE id IN ($inQuery)");
            $stmt->execute($ids);
            $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if ($names) {
                $check = $pdo->prepare("SELECT DISTINCT branch_id FROM users WHERE branch_id IN ($inQuery)");
                $check->execute($ids);
                $used = $check->fetchAll(PDO::FETCH_COLUMN);
                
                if (count($used) > 0) {
                    $usedStr = implode(", ", $used);
                    $error = "Aman DB: Beberapa Cabang (ID: $usedStr) masih terkait dengan karyawan. Silakan ubah/hapus karyawan tersebut di Master Karyawan.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM branches WHERE id IN ($inQuery)");
                    $stmt->execute($ids);
                    $success = count($ids) . " Cabang berhasil dihapus!";
                }
            }
        } catch (Exception $e) {
            $error = "Gagal menghapus Cabang.";
        }
    }
}

// Pagination logic
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_branches = $pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn();
$total_pages = ceil($total_branches / $limit);

$stmt = $pdo->prepare("SELECT * FROM branches ORDER BY id ASC LIMIT ? OFFSET ?");
$stmt->execute([$limit, $offset]);
$branches = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Cabang - Indoarsip</title>
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
                        <h1 class="page-title">Master Cabang</h1>
                        <p class="page-subtitle">Kelola daftar Cabang meeting perusahaan</p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="submit" form="bulkDeleteForm" name="bulk_delete_branch" class="btn-submit" id="btnBulkDelete" style="background:#ef4444; display: none;">
                            <i class="fa-solid fa-trash" style="margin-right: 8px;"></i> Hapus Terpilih
                        </button>
                        <button class="btn-submit" onclick="document.getElementById('addModal').classList.add('active')">
                            <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Tambah Cabang
                        </button>
                    </div>
                </div>

                <form id="bulkDeleteForm" method="POST" onsubmit="return confirmWithSweetAlert(event, 'bulkDeleteForm', 'bulk_delete_branch', 'Hapus semua Cabang yang dipilih?');">
                <div class="card">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 60px; text-align: center;">No.</th>
                                    <th>Nama Cabang</th>
                                    <th style="width: 200px; text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($branches)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <i class="fa-solid fa-folder-open" style="display: block; font-size: 2rem; margin-bottom: 10px;"></i>
                                            Belum ada data tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = $offset + 1; foreach ($branches as $branch): ?>
                                    <tr class="selectable-row">
                                        <td style="text-align: center; color: #94a3b8; font-size: 0.8rem;">
                                            <input type="checkbox" name="bulk_ids[]" value="<?= $branch['id'] ?>" class="row-checkbox" style="display:none;">
                                            <?= $no++ ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($branch['name']) ?></strong></td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; justify-content: center; gap: 8px;">
                                                <button type="button" class="btn-action-text" style="background:#f59e0b; color:white; border-radius:6px; padding:6px 12px;" onclick="editbranch(<?= $branch['id'] ?>, '<?= htmlspecialchars(addslashes($branch['name']), ENT_QUOTES) ?>')" title="Edit Cabang">
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
                <h3>Tambah Cabang Baru</h3>
                <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Nama Cabang</label>
                        <input type="text" name="name" class="form-control" required placeholder="Contoh: Ruang Mezanine">
                    </div>
                    <button type="submit" name="add_branch" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px;">
                        Simpan Cabang
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <h3>Edit Data Cabang</h3>
                <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <input type="hidden" name="branch_id" id="edit_branch_id">
                    <div class="form-group">
                        <label class="form-label">Nama Cabang</label>
                        <input type="text" name="name" id="edit_branch_name" class="form-control" required>
                    </div>
                    <button type="submit" name="edit_branch" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px;">
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>

    
    

    <script>
        function editbranch(id, name) {
            document.getElementById('edit_branch_id').value = id;
            document.getElementById('edit_branch_name').value = name;
            document.getElementById('editModal').classList.add('active');
        }

        let qrcodeObj = null;
        function showBarcode(id, name) {
            document.getElementById('barcodebranchName').textContent = name;
            const pathArray = window.location.pathname.split('/');
            pathArray.pop(); // remove branches.php
            const basePath = pathArray.join('/');
            const baseUrl = window.location.protocol + "//" + window.location.host + basePath;
            const link = baseUrl + "/attendance.php?branch_id=" + id;
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
        Toast.fire({ icon: 'success', title: '<?= $success ?>' });
    </script>
    <?php endif; ?>
    <?php if ($error): ?>
    <script>
        Toast.fire({ icon: 'error', title: '<?= $error ?>' });
    </script>
    <?php endif; ?>
</body>
</html>

