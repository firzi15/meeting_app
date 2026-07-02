<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && !(isset($_SESSION['can_dashboard']) && $_SESSION['can_dashboard']))) {
    header("Location: index.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $room = $_POST['room'] ?? '';
    $late_tolerance = $_POST['late_tolerance'] ?? 15;
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $pic_id_raw = $_POST['pic_id'] ?? '';
    $pic_id = is_array($pic_id_raw) ? ($pic_id_raw[0] ?? '') : $pic_id_raw;
    $participants = $_POST['participants'] ?? [];

    $has_snack = isset($_POST['has_snack']) ? 1 : 0;
    $has_coffee = isset($_POST['has_coffee']) ? 1 : 0;
    $coffee_temp = $_POST['coffee_temp'] ?? null;
    $coffee_type = $_POST['coffee_type'] ?? null;
    $is_hybrid_zoom = isset($_POST['is_hybrid_zoom']) ? 1 : 0;

    if ($title && $room && $date && $time && $end_time && $pic_id) {
        $scheduled_time = $date . ' ' . $time . ':00';
        $scheduled_end_time = $date . ' ' . $end_time . ':00';

        try {
            $pdo->beginTransaction();

            // Check double booking (exclude current meeting)
            $stmt_check = $pdo->prepare("
                SELECT COUNT(*) FROM meetings 
                WHERE room = ? AND id != ? AND status != 'rejected'
                AND (
                    (scheduled_time < ? AND end_time > ?)
                )
            ");
            $stmt_check->execute([$room, $id, $scheduled_end_time, $scheduled_time]);
            if ($stmt_check->fetchColumn() > 0) {
                $_SESSION['error'] = 'Ruangan sudah dipesan untuk waktu tersebut.';
                $pdo->rollBack();
            } else {
                $stmt = $pdo->prepare("UPDATE meetings SET title = ?, room = ?, scheduled_time = ?, end_time = ?, late_tolerance = ?, pic_id = ?, has_snack = ?, has_coffee = ?, coffee_temp = ?, coffee_type = ?, is_hybrid_zoom = ? WHERE id = ?");
                $stmt->execute([$title, $room, $scheduled_time, $scheduled_end_time, $late_tolerance, $pic_id, $has_snack, $has_coffee, $coffee_temp, $coffee_type, $is_hybrid_zoom, $id]);

                // Update participants
                $pdo->prepare("DELETE FROM meeting_participants WHERE meeting_id = ?")->execute([$id]);
                
                $stmt_part = $pdo->prepare("INSERT INTO meeting_participants (meeting_id, user_id) VALUES (?, ?)");
                $stmt_part->execute([$id, $pic_id]);

                if (!empty($participants)) {
                    foreach ($participants as $uid) {
                        if ($uid != $pic_id) {
                            $stmt_part->execute([$id, $uid]);
                        }
                    }
                }

                $pdo->commit();
                $_SESSION['success'] = 'Meeting berhasil diperbarui.';
                header("Location: index.php");
                exit;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Gagal menyimpan jadwal: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Data tidak lengkap.';
    }
}

// Fetch meeting data
$stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
$stmt->execute([$id]);
$meeting = $stmt->fetch();

if (!$meeting) {
    header("Location: index.php");
    exit;
}

// Fetch participants
$stmt_part = $pdo->prepare("SELECT user_id FROM meeting_participants WHERE meeting_id = ? AND user_id != ?");
$stmt_part->execute([$id, $meeting['pic_id']]);
$current_participants = $stmt_part->fetchAll(PDO::FETCH_COLUMN);

$date_val = date('Y-m-d', strtotime($meeting['scheduled_time']));
$time_val = date('H:i', strtotime($meeting['scheduled_time']));
$end_time_val = date('H:i', strtotime($meeting['end_time']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Meeting - Indoarsip</title>
    <link rel="icon" type="image/png" href="logo_login.png">
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .edit-container { background: #fff; border-radius: 16px; padding: 30px; box-shadow: var(--shadow-sm); max-width: 700px; margin: 0 auto; }
        .select2-container--default .select2-selection--multiple { border: 1px solid #d1d5db; border-radius: 8px; min-height: 42px; padding: 4px; }
        .select2-container--default.select2-container--focus .select2-selection--multiple { border-color: var(--primary-color); outline: 0; box-shadow: 0 0 0 3px rgba(63, 81, 181, 0.1); }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <div class="main-wrapper">
            <?php include 'topbar.php'; ?>
            <main class="content">
                <div style="margin-bottom: 20px;">
                    <a href="index.php" style="color: #64748b; text-decoration: none; font-size: 0.875rem; font-weight: 500;">
                        <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                </div>
                
                <div class="edit-container">
                    <h2 style="margin: 0 0 20px; font-size: 1.5rem; font-weight: 700; color: #1e293b;">Edit Meeting</h2>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-error">
                            <i class="fa-solid fa-circle-exclamation"></i> <?= $_SESSION['error'] ?>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label class="form-label">Judul Meeting</label>
                            <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($meeting['title']) ?>">
                        </div>

                        <div class="schedule-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Ruang Meeting</label>
                                <select name="room" class="form-control" required>
                                    <option value="">-- Pilih Ruangan --</option>
                                    <?php
                                    $stmt_rooms_m = $pdo->query("SELECT name FROM rooms ORDER BY name ASC");
                                    while($r = $stmt_rooms_m->fetch()) {
                                        $sel = ($meeting['room'] === $r['name']) ? 'selected' : '';
                                        echo "<option value=\"".htmlspecialchars($r['name'])."\" $sel>".htmlspecialchars($r['name'])."</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Toleransi (Menit)</label>
                                <input type="number" name="late_tolerance" class="form-control" min="0" required value="<?= htmlspecialchars($meeting['late_tolerance']) ?>">
                            </div>
                        </div>

                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label class="form-label" style="font-size: 0.8rem;">Tanggal Pelaksanaan</label>
                                <input type="date" name="date" class="form-control" required style="padding: 8px 12px;" value="<?= $date_val ?>">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 0.8rem;">Jam Mulai</label>
                                    <input type="time" name="time" class="form-control" required style="padding: 8px 12px;" value="<?= $time_val ?>">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 0.8rem;">Jam Selesai</label>
                                    <input type="time" name="end_time" class="form-control" required style="padding: 8px 12px;" value="<?= $end_time_val ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="form-label"><i class="fa-solid fa-user-tie" style="margin-right: 8px; color: var(--primary-color);"></i> PIC Meeting</label>
                            <select name="pic_id[]" id="picSelect" required style="width: 100%;" multiple="multiple">
                                <option></option>
                                <?php
                                $current_branch = getCurrentBranchId();
                                $stmt_pic = $pdo->query("SELECT * FROM users WHERE role != 'admin' ORDER BY CASE WHEN branch_id = $current_branch THEN 0 ELSE 1 END ASC, name ASC");
                                while($u = $stmt_pic->fetch()) {
                                    $sel = ($u['id'] == $meeting['pic_id']) ? 'selected' : '';
                                    echo "<option value=\"{$u['id']}\" $sel>".htmlspecialchars($u['name'])."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="form-label"><i class="fa-solid fa-users" style="margin-right: 8px; color: var(--primary-color);"></i> Peserta Diundang</label>
                            <select name="participants[]" id="participantSelect" multiple="multiple" style="width: 100%;">
                                <?php
                                $current_branch = getCurrentBranchId();
                                $stmt_u = $pdo->query("SELECT * FROM users WHERE role != 'admin' ORDER BY CASE WHEN branch_id = $current_branch THEN 0 ELSE 1 END ASC, name ASC");
                                while($u = $stmt_u->fetch()) {
                                    $sel = in_array($u['id'], $current_participants) ? 'selected' : '';
                                    echo "<option value=\"{$u['id']}\" $sel>".htmlspecialchars($u['name'])."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Food, Beverages & Facilities Options -->
                        <div style="background: #f8fafc; border: 1px solid #e5e7eb; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
                            <label class="form-label" style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-cookie-bite" style="color: var(--primary-color);"></i> Konsumsi & Fasilitas</label>
                            <div style="display: flex; gap: 24px; align-items: center; margin-bottom: 4px; flex-wrap: wrap;">
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                                    <input type="checkbox" name="has_snack" value="1" <?= !empty($meeting['has_snack']) ? 'checked' : '' ?> style="width: 16px; height: 16px; border-radius: 4px; accent-color: var(--primary-color);"> Snack
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                                    <input type="checkbox" id="hasCoffeeCheckbox" name="has_coffee" value="1" <?= !empty($meeting['has_coffee']) ? 'checked' : '' ?> style="width: 16px; height: 16px; border-radius: 4px; accent-color: var(--primary-color);" onchange="toggleCoffeeOptions(this.checked)"> Coffee
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                                    <input type="checkbox" name="is_hybrid_zoom" value="1" <?= !empty($meeting['is_hybrid_zoom']) ? 'checked' : '' ?> style="width: 16px; height: 16px; border-radius: 4px; accent-color: var(--primary-color);"> Hybrid Zoom
                                </label>
                            </div>
                            
                            <!-- Coffee details sub-options -->
                            <div id="coffeeOptionsContainer" style="<?= empty($meeting['has_coffee']) ? 'display: none;' : '' ?> border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 12px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label" style="font-size: 0.75rem; color: #6b7280;">Suhu Kopi</label>
                                        <select name="coffee_temp" class="form-control" style="padding: 8px 12px; font-size: 0.875rem; height: 40px; min-height: 40px;">
                                            <option value="panas" <?= ($meeting['coffee_temp'] ?? '') === 'panas' ? 'selected' : '' ?>>Panas</option>
                                            <option value="dingin" <?= ($meeting['coffee_temp'] ?? '') === 'dingin' ? 'selected' : '' ?>>Dingin</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label" style="font-size: 0.75rem; color: #6b7280;">Metode Penyediaan</label>
                                        <select name="coffee_type" class="form-control" style="padding: 8px 12px; font-size: 0.875rem; height: 40px; min-height: 40px;">
                                            <option value="bikin" <?= ($meeting['coffee_type'] ?? '') === 'bikin' ? 'selected' : '' ?>>Bikin Sendiri</option>
                                            <option value="beli" <?= ($meeting['coffee_type'] ?? '') === 'beli' ? 'selected' : '' ?>>Beli Luar</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit" style="width: 100%; padding: 12px; font-size: 0.95rem;">
                            <i class="fa-solid fa-save" style="margin-right: 8px;"></i> Simpan Perubahan
                        </button>
                    </form>
                </div>
            </main>
            <?php include 'footer.php'; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#picSelect').select2({
                placeholder: 'Pilih Penanggung Jawab',
                maximumSelectionLength: 1
            });
            $('#participantSelect').select2({
                placeholder: 'Cari & Pilih Peserta'
            });
        });

        function toggleCoffeeOptions(show) {
            const container = document.getElementById('coffeeOptionsContainer');
            if (show) {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }
    </script>
</body>
</html>
