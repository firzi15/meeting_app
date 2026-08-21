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
$where_branch = $current_branch > 0 ? "WHERE branch_id = $current_branch" : "WHERE 1=1";

// Add Room
if (isset($_POST['add_room'])) {
    $name = strip_tags($_POST['name'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9 \.]+$/', $name)) {
        $error = "Input tidak boleh mengandung simbol khusus.";
    } elseif ($name) {
        try {
            $stmt = $pdo->prepare("INSERT INTO rooms (name, branch_id) VALUES (?, ?)");
            $stmt->execute([$name, $insert_branch]);
            $success = "Ruangan berhasil ditambahkan!";
        } catch (Exception $e) {
            $error = "Gagal menambahkan ruangan: " . $e->getMessage();
        }
    }
}

// Edit Room
if (isset($_POST['edit_room'])) {
    $id = $_POST['room_id'] ?? '';
    $name = strip_tags($_POST['name'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9 \.]+$/', $name)) {
        $error = "Input tidak boleh mengandung simbol khusus.";
    } elseif ($id && $name) {
        try {
            $stmt = $pdo->prepare("UPDATE rooms SET name = ? WHERE id = ? $branch_condition");
            $stmt->execute([$name, $id]);
            $success = "Ruangan berhasil diperbarui!";
        } catch (Exception $e) {
            $error = "Gagal memperbarui ruangan.";
        }
    }
}

// Delete Room
if (isset($_POST['delete_room'])) {
    $id = $_POST['room_id'] ?? '';
    if ($id) {
        try {
            $stmt = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
            $stmt->execute([$id]);
            $room = $stmt->fetch();
            if ($room) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM meetings WHERE room = ?");
                $check->execute([$room['name']]);
                if ($check->fetchColumn() > 0) {
                    $error = "Aman DB: Ruangan '{$room['name']}' masih memiliki jadwal meeting terdaftar. Silakan hapus jadwal meeting tersebut terlebih dahulu.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ? $branch_condition");
                    $stmt->execute([$id]);
                    $success = "Ruangan berhasil dihapus!";
                }
            }
        } catch (Exception $e) {
            $error = "Gagal menghapus ruangan.";
        }
    }
}

// Bulk Delete Room
if (isset($_POST['bulk_delete_room'])) {
    $ids = $_POST['room_ids'] ?? [];
    if (!empty($ids)) {
        try {
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT name FROM rooms WHERE id IN ($inQuery)");
            $stmt->execute($ids);
            $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if ($names) {
                $inQueryNames = implode(',', array_fill(0, count($names), '?'));
                $check = $pdo->prepare("SELECT DISTINCT room FROM meetings WHERE room IN ($inQueryNames)");
                $check->execute($names);
                $used = $check->fetchAll(PDO::FETCH_COLUMN);
                
                if (count($used) > 0) {
                    $usedStr = implode(", ", $used);
                    $error = "Aman DB: Ruangan ($usedStr) masih memiliki jadwal meeting. Silakan hapus jadwal tersebut terlebih dahulu.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM rooms WHERE id IN ($inQuery) $branch_condition");
                    $stmt->execute($ids);
                    $success = count($ids) . " ruangan berhasil dihapus!";
                }
            }
        } catch (Exception $e) {
            $error = "Gagal menghapus ruangan.";
        }
    }
}

// Pagination logic
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_rooms = $pdo->query("SELECT COUNT(*) FROM rooms $where_branch")->fetchColumn();
$total_pages = ceil($total_rooms / $limit);

$stmt = $pdo->prepare("SELECT * FROM rooms $where_branch ORDER BY id ASC LIMIT ? OFFSET ?");
$stmt->execute([$limit, $offset]);
$rooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Ruangan - Indoarsip</title>
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
                        <h1 class="page-title">Master Ruangan</h1>
                        <p class="page-subtitle">Kelola daftar ruangan meeting perusahaan</p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="submit" form="bulkDeleteForm" name="bulk_delete_room" class="btn-submit" id="btnBulkDelete" style="background:#ef4444; display: none;">
                            <i class="fa-solid fa-trash" style="margin-right: 8px;"></i> Hapus Terpilih
                        </button>
                        <button class="btn-submit" onclick="document.getElementById('addModal').classList.add('active')">
                            <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Tambah Ruangan
                        </button>
                    </div>
                </div>

                <form id="bulkDeleteForm" method="POST" onsubmit="return confirmWithSweetAlert(event, 'bulkDeleteForm', 'bulk_delete_room', 'Hapus semua ruangan yang dipilih?');">
                <div class="card">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 60px; text-align: center;">No.</th>
                                    <th>Nama Ruangan</th>
                                    <th style="width: 200px; text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rooms)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <i class="fa-solid fa-folder-open" style="display: block; font-size: 2rem; margin-bottom: 10px;"></i>
                                            Belum ada data tersedia
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = $offset + 1; foreach ($rooms as $room): ?>
                                    <tr class="selectable-row">
                                        <td style="text-align: center; color: #94a3b8; font-size: 0.8rem;">
                                            <input type="checkbox" name="bulk_ids[]" value="<?= $room['id'] ?>" class="row-checkbox" style="display:none;">
                                            <?= $no++ ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($room['name']) ?></strong></td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; justify-content: center; gap: 8px;">
                                                <button type="button" class="btn-action-text" style="background:#f59e0b; color:white; border-radius:6px; padding:6px 12px;" onclick="editRoom(<?= $room['id'] ?>, '<?= htmlspecialchars(addslashes($room['name']), ENT_QUOTES) ?>')" title="Edit Ruangan">
                                                    <i class="fa-solid fa-pen-to-square" style="margin-right: 5px;"></i> Edit
                                                </button>
                                                <button type="button" class="btn-action-text" style="background:#3b82f6; color:white; border-radius:6px; padding:6px 12px;" onclick="showBarcode(<?= $room['id'] ?>, '<?= htmlspecialchars(addslashes($room['name']), ENT_QUOTES) ?>')" title="Lihat Barcode Ruangan">
                                                    <i class="fa-solid fa-qrcode" style="margin-right: 5px;"></i> Barcode
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
                <h3>Tambah Ruangan Baru</h3>
                <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Nama Ruangan</label>
                        <input type="text" name="name" class="form-control" required placeholder="Contoh: Ruang Mezanine">
                    </div>
                    <button type="submit" name="add_room" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px;">
                        Simpan Ruangan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <h3>Edit Data Ruangan</h3>
                <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form method="POST">
                    <input type="hidden" name="room_id" id="edit_room_id">
                    <div class="form-group">
                        <label class="form-label">Nama Ruangan</label>
                        <input type="text" name="name" id="edit_room_name" class="form-control" required>
                    </div>
                    <button type="submit" name="edit_room" class="btn-submit" style="width: 100%; padding: 12px; border-radius: 8px;">
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Barcode -->
    <div id="barcodeModal" class="modal-overlay">
        <div class="modal-card" style="border-radius: 16px; overflow: hidden; width: 400px; text-align: center;">
            <div class="modal-header">
                <h3>Barcode Ruangan</h3>
                <button class="modal-close" onclick="document.getElementById('barcodeModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <h4 id="barcodeRoomName" style="margin-bottom: 15px; color: #2a2e42;"></h4>
                <div id="qrcode" style="display: flex; justify-content: center; margin-bottom: 20px;"></div>
                <div style="background: #f8fafc; padding: 10px; border-radius: 8px; word-break: break-all; font-size: 0.85rem; color: #64748b; border: 1px solid #e2e8f0; margin-bottom: 15px;" id="barcodeLink"></div>
                <button type="button" class="btn-submit" style="width: 100%; background: #10b981;" onclick="downloadQR()">
                    <i class="fa-solid fa-download" style="margin-right: 8px;"></i> Unduh
                </button>
            </div>
        </div>
    </div>

    <!-- Include qrcode.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <script>
        function editRoom(id, name) {
            document.getElementById('edit_room_id').value = id;
            document.getElementById('edit_room_name').value = name;
            document.getElementById('editModal').classList.add('active');
        }

        let qrcodeObj = null;
        function showBarcode(id, name) {
            document.getElementById('barcodeRoomName').textContent = name;
            const pathArray = window.location.pathname.split('/');
            pathArray.pop(); // remove rooms.php
            const basePath = pathArray.join('/');
            const baseUrl = window.location.protocol + "//" + window.location.host + basePath;
            const link = baseUrl + "/attendance.php?room_id=" + id;
            document.getElementById('barcodeLink').textContent = link;

            document.getElementById('qrcode').innerHTML = "";
            qrcodeObj = new QRCode(document.getElementById("qrcode"), {
                text: link,
                width: 500, // High-res
                height: 500
            });
            // Force display size to be small in the modal
            setTimeout(() => {
                const qrCanvas = document.querySelector('#qrcode canvas');
                const qrImg = document.querySelector('#qrcode img');
                if (qrCanvas) { qrCanvas.style.width = '200px'; qrCanvas.style.height = '200px'; }
                if (qrImg) { qrImg.style.width = '200px'; qrImg.style.height = '200px'; }
            }, 50);

            document.getElementById('barcodeModal').classList.add('active');
        }

        function downloadQR() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            // Set high resolution canvas
            canvas.width = 800;
            canvas.height = 1000;
            
            // White background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            const roomName = document.getElementById('barcodeRoomName').textContent;
            
            const drawContent = (logoHeight = 0) => {
                // Draw Title
                ctx.fillStyle = '#1e293b';
                ctx.font = 'bold 50px "Plus Jakarta Sans", Arial, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(roomName.toUpperCase(), canvas.width / 2, 80 + logoHeight);
                
                // Draw QR Code
                const qrImg = document.querySelector('#qrcode img');
                const qrCanvas = document.querySelector('#qrcode canvas');
                
                const drawQR = (source) => {
                    const qrSize = 500;
                    ctx.drawImage(source, (canvas.width - qrSize) / 2, 130 + logoHeight, qrSize, qrSize);
                    
                    // Draw Rules (Left Aligned)
                    const rulesX = (canvas.width - qrSize) / 2; // Align with left edge of QR code
                    
                    ctx.textAlign = 'left';
                    ctx.fillStyle = '#475569';
                    ctx.font = 'bold 20px "Plus Jakarta Sans", Arial, sans-serif';
                    ctx.fillText('Tata Tertib Penggunaan Ruang Meeting:', rulesX, 130 + logoHeight + qrSize + 60);
                    
                    ctx.font = '18px "Plus Jakarta Sans", Arial, sans-serif';
                    ctx.fillStyle = '#334155';
                    const rules = [
                        '1. Scan Barcode ini untuk absensi kehadiran meeting.',
                        '2. Harap masuk ruangan tepat pada waktunya.',
                        '3. Jaga kebersihan dan rapikan kursi setelah selesai.',
                        '4. Matikan AC, lampu & proyektor sebelum keluar.'
                    ];
                    
                    rules.forEach((rule, index) => {
                        ctx.fillText(rule, rulesX, 130 + logoHeight + qrSize + 100 + (index * 30));
                    });
                    
                    // Trigger blob output in new tab
                    canvas.toBlob((blob) => {
                        const url = URL.createObjectURL(blob);
                        window.open(url, '_blank');
                    }, 'image/png');
                };

                if (qrImg && qrImg.src && qrImg.src.startsWith('data:')) {
                    if(qrImg.complete) drawQR(qrImg);
                    else qrImg.onload = () => drawQR(qrImg);
                } else if (qrCanvas) {
                    drawQR(qrCanvas);
                }
            };

            const drawTintedLogo = (img) => {
                const logoWidth = 250;
                const logoHeight = (logoWidth / img.width) * img.height;
                
                // Create offscreen canvas to process pixels
                const tCanvas = document.createElement('canvas');
                tCanvas.width = logoWidth;
                tCanvas.height = logoHeight;
                const tCtx = tCanvas.getContext('2d');
                
                // Draw original image
                tCtx.drawImage(img, 0, 0, logoWidth, logoHeight);
                
                // Process pixels to replace white with dark color (#1e293b)
                const imgData = tCtx.getImageData(0, 0, logoWidth, logoHeight);
                const data = imgData.data;
                for (let i = 0; i < data.length; i += 4) {
                    const r = data[i];
                    const g = data[i+1];
                    const b = data[i+2];
                    const a = data[i+3];
                    // If pixel is mostly white
                    if (r > 200 && g > 200 && b > 200 && a > 50) {
                        data[i] = 30;     // R
                        data[i+1] = 41;   // G
                        data[i+2] = 59;   // B
                    }
                }
                tCtx.putImageData(imgData, 0, 0);
                
                // Draw processed logo to main canvas
                ctx.drawImage(tCanvas, (canvas.width - logoWidth) / 2, 40);
                
                // Draw rest of content with extra gap (+40)
                drawContent(logoHeight + 40);
            };

            // Load Logo
            const logo = new Image();
            logo.src = 'assets/logo.png'; 
            logo.onload = () => drawTintedLogo(logo);
            logo.onerror = () => {
                const rootLogo = new Image();
                rootLogo.src = 'logo.png';
                rootLogo.onload = () => drawTintedLogo(rootLogo);
                rootLogo.onerror = () => drawContent(50); // No logo
            };
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
