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

// Fetch users for dropdowns
$stmt_users = $pdo->query("SELECT * FROM users WHERE role != 'admin' ORDER BY CASE WHEN branch_id = $current_branch THEN 0 ELSE 1 END ASC, name ASC");
$users = $stmt_users->fetchAll();

// Add Template
if (isset($_POST['add_template'])) {
    $name = strip_tags($_POST['name'] ?? '');
    $title = strip_tags($_POST['title'] ?? '');
    $pic_id_raw = $_POST['pic_id'] ?? '';
    $pic_id = is_array($pic_id_raw) ? ($pic_id_raw[0] ?? null) : $pic_id_raw;
    $participants = $_POST['participants'] ?? [];

    if ($name && $title && $pic_id) {
        try {
            $parts_json = json_encode($participants);
            $stmt = $pdo->prepare("INSERT INTO meeting_templates (name, title, pic_id, participants, branch_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $title, $pic_id, $parts_json, $insert_branch]);
            $success = "Template berhasil ditambahkan!";
        } catch (Exception $e) {
            $error = "Gagal menambahkan template: " . $e->getMessage();
        }
    } else {
        $error = "Data tidak lengkap.";
    }
}

// Edit Template
if (isset($_POST['edit_template'])) {
    $id = $_POST['template_id'] ?? '';
    $name = strip_tags($_POST['name'] ?? '');
    $title = strip_tags($_POST['title'] ?? '');
    $pic_id_raw = $_POST['pic_id'] ?? '';
    $pic_id = is_array($pic_id_raw) ? ($pic_id_raw[0] ?? null) : $pic_id_raw;
    $participants = $_POST['participants'] ?? [];

    if ($id && $name && $title && $pic_id) {
        try {
            $parts_json = json_encode($participants);
            $stmt = $pdo->prepare("UPDATE meeting_templates SET name = ?, title = ?, pic_id = ?, participants = ? WHERE id = ? $branch_condition");
            $stmt->execute([$name, $title, $pic_id, $parts_json, $id]);
            $success = "Template berhasil diperbarui!";
        } catch (Exception $e) {
            $error = "Gagal memperbarui template.";
        }
    } else {
        $error = "Data tidak lengkap.";
    }
}

// Delete Template
if (isset($_POST['delete_template'])) {
    $id = $_POST['template_id'] ?? '';
    if ($id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM meeting_templates WHERE id = ? $branch_condition");
            $stmt->execute([$id]);
            $success = "Template berhasil dihapus!";
        } catch (Exception $e) {
            $error = "Gagal menghapus template.";
        }
    }
}

// Bulk Delete Template
if (isset($_POST['bulk_delete_template'])) {
    $ids = $_POST['bulk_ids'] ?? [];
    if (!empty($ids)) {
        try {
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM meeting_templates WHERE id IN ($inQuery) $branch_condition");
            $stmt->execute($ids);
            $success = count($ids) . " template berhasil dihapus!";
        } catch (Exception $e) {
            $error = "Gagal menghapus template.";
        }
    }
}

// Pagination logic
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_templates = $pdo->query("SELECT COUNT(*) FROM meeting_templates $where_branch")->fetchColumn();
$total_pages = ceil($total_templates / $limit);

$where_branch_join = $current_branch > 0 ? "WHERE t.branch_id = $current_branch" : "WHERE 1=1";

$stmt = $pdo->prepare("
    SELECT t.*, u.name as pic_name 
    FROM meeting_templates t
    LEFT JOIN users u ON t.pic_id = u.id
    $where_branch_join 
    ORDER BY t.id DESC LIMIT ? OFFSET ?
");
$stmt->execute([$limit, $offset]);
$templates = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Template Meeting - Indoarsip</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .select2-container--default .select2-selection--multiple,
        .select2-container--default .select2-selection--single {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 5px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <div class="main-wrapper">
            <?php include 'topbar.php'; ?>
            <main class="content">
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h1 class="page-title">Master Template Meeting</h1>
                        <p class="page-subtitle">Kelola template meeting untuk memudahkan pembuatan jadwal</p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="submit" form="bulkDeleteForm" name="bulk_delete_template" class="btn-submit" id="btnBulkDelete" style="background:#ef4444; display: none;">
                            <i class="fa-solid fa-trash" style="margin-right: 8px;"></i> Hapus Terpilih
                        </button>
                        <button class="btn-submit" onclick="openAddModal()">
                            <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Tambah Template
                        </button>
                    </div>
                </div>

                <form id="bulkDeleteForm" method="POST" onsubmit="return confirmWithSweetAlert(event, 'bulkDeleteForm', 'bulk_delete_template', 'Hapus semua template yang dipilih?');">
                <div class="card">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 60px; text-align: center;">No.</th>
                                    <th>Nama / Judul Template</th>
                                    <th>PIC</th>
                                    <th>Jml Peserta</th>
                                    <th style="width: 150px; text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($templates)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <i class="fa-solid fa-folder-open" style="display: block; font-size: 2rem; margin-bottom: 10px;"></i>
                                            Belum ada template
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = $offset + 1; foreach ($templates as $t): 
                                        $parts = json_decode($t['participants'], true) ?: [];
                                    ?>
                                    <tr class="selectable-row">
                                        <td style="text-align: center; color: #94a3b8; font-size: 0.8rem;">
                                            <input type="checkbox" name="template_ids[]" value="<?= $t['id'] ?>" class="row-checkbox" style="display:none;">
                                            <?= $no++ ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($t['pic_name']) ?></td>
                                        <td><?= count($parts) ?> Orang</td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; justify-content: center; gap: 8px;">
                                                <button type="button" class="btn-action-text" style="background:#f59e0b; color:white; border-radius:6px; padding:6px 12px;" 
                                                        onclick='editTemplate(<?= $t['id'] ?>, <?= json_encode($t['name']) ?>, <?= json_encode($t['title']) ?>, <?= $t['pic_id'] ?>, <?= json_encode($parts) ?>)' title="Edit Template">
                                                    <i class="fa-solid fa-pen-to-square" style="margin-right: 5px;"></i> Edit
                                                </button>
                                                <button type="button" class="btn-action-text" style="background:#ef4444; color:white; border-radius:6px; padding:6px 12px;" onclick="deleteTemplate(<?= $t['id'] ?>)" title="Hapus Template">
                                                    <i class="fa-solid fa-trash"></i>
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

                <!-- Form untuk delete -->
                <form id="deleteForm" method="POST" style="display:none;">
                    <input type="hidden" name="template_id" id="delete_template_id">
                    <input type="hidden" name="delete_template" value="1">
                </form>
            </main>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="templateModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 650px; border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Template Baru</h3>
                <button class="modal-close" onclick="document.getElementById('templateModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST" id="templateForm">
                    <input type="hidden" name="template_id" id="form_template_id">
                    <input type="hidden" name="action_type" id="form_action_type" value="add_template">
                    
                    <div class="form-group">
                        <label class="form-label">Nama Template (Internal)</label>
                        <input type="text" name="name" id="form_name" class="form-control" required placeholder="Contoh: Template Rapat Bulanan IT">
                    </div>
                    
                    <input type="hidden" name="title" id="form_title">

                    <div class="form-group">
                        <label class="form-label">PIC Meeting Bawaan</label>
                        <select name="pic_id[]" id="form_pic_id" class="select2" required style="width: 100%;" multiple="multiple">
                            <?php foreach($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Peserta Diundang Bawaan</label>
                        <select name="participants[]" id="form_participants" class="select2" multiple="multiple" style="width: 100%;">
                            <?php foreach($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" id="btnSubmit" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px;">
                        Simpan Template
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#form_pic_id').select2({
                placeholder: "Pilih PIC Bawaan",
                allowClear: true,
                dropdownParent: $('#templateModal'),
                maximumSelectionLength: 1
            });

            $('#form_participants').select2({
                placeholder: "Pilih Peserta Bawaan",
                allowClear: true,
                dropdownParent: $('#templateModal')
            });

            // Prevent PIC from being in participants
            $('#form_pic_id').on('change', function() {
                const picId = $(this).val();
                const $participantSelect = $('#form_participants');
                
                $participantSelect.find('option').prop('disabled', false);
                if (picId && picId.length > 0) {
                    $participantSelect.find(`option[value="${picId[0]}"]`).prop('disabled', true);
                    const currentParticipants = $participantSelect.val() || [];
                    const newParticipants = currentParticipants.filter(id => id !== picId[0]);
                    $participantSelect.val(newParticipants).trigger('change.select2');
                }
                
                $participantSelect.select2({
                    placeholder: "Pilih Peserta Bawaan",
                    allowClear: true,
                    dropdownParent: $('#templateModal')
                });
            });

            // Handle action_type switching
            $('#templateForm').on('submit', function(e) {
                // Prepopulate default meeting title with the template name
                $('#form_title').val($('#form_name').val());
                
                const actionType = $('#form_action_type').val();
                $('<input>').attr({
                    type: 'hidden',
                    name: actionType,
                    value: '1'
                }).appendTo('#templateForm');
            });
        });

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Tambah Template Baru';
            document.getElementById('form_template_id').value = '';
            document.getElementById('form_action_type').value = 'add_template';
            document.getElementById('form_name').value = '';
            document.getElementById('form_title').value = '';
            $('#form_pic_id').val(null).trigger('change');
            $('#form_participants').val(null).trigger('change');
            document.getElementById('templateModal').classList.add('active');
        }

        function editTemplate(id, name, title, picId, participants) {
            document.getElementById('modalTitle').textContent = 'Edit Template';
            document.getElementById('form_template_id').value = id;
            document.getElementById('form_action_type').value = 'edit_template';
            document.getElementById('form_name').value = name;
            document.getElementById('form_title').value = title;
            $('#form_pic_id').val([picId]).trigger('change');
            $('#form_participants').val(participants).trigger('change');
            document.getElementById('templateModal').classList.add('active');
        }

        function deleteTemplate(id) {
            Swal.fire({
                title: 'Hapus Template?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Ya, hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_template_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
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
