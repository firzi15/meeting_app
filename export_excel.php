<?php
session_start();
require_once 'database.php';

// Access Control: Admin or Granted User
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && !$_SESSION['can_export'])) {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengekspor laporan.");
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');
$is_hr_role = (isset($_SESSION['division']) && $_SESSION['division'] === 'HR');

// User-driven export preference (Admin/HR can include HR data)
$include_hr_data = (isset($_GET['is_hr']) && $_GET['is_hr'] === '1' && ($is_admin || $is_hr_role));

$type = $_GET['type'] ?? 'summary';
$filename = "laporan_meeting_" . ($include_hr_data ? "HR_" : "") . date('Ymd_His') . ".xls";

// Output headers for Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Generate Excel HTML Structure
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        .table { border-collapse: collapse; width: 100%; }
        .table th, .table td { border: 1px solid #000; padding: 5px; text-align: left; }
        .header-bg { background-color: #f2f2f2; font-weight: bold; }
        .title { font-size: 16pt; font-weight: bold; margin-bottom: 10px; }
        .info { font-size: 11pt; margin-bottom: 20px; }
    </style>
</head>
<body>

<?php
if ($type === 'summary') {
    // Mode: Rekap Daftar Meeting
    $room_filter = $_GET['room'] ?? '';
    $where = " WHERE 1=1 ";
    $params = [];
    
    if (!$is_admin && !$is_hr_role) {
        $where .= " AND created_by = ? ";
        $params[] = $user_id;
    }
    if ($room_filter) {
        $where .= " AND room = ? ";
        $params[] = $room_filter;
    }

    $stmt = $pdo->prepare("SELECT * FROM meetings" . $where . " ORDER BY scheduled_time DESC");
    $stmt->execute($params);
    
    echo "<div class='title'>Rekap Laporan Meeting</div>";
    echo "<div class='info'>Dicetak pada: " . date('d M Y H:i:s') . "</div>";
    
    echo "<table class='table'>";
    echo "<thead><tr class='header-bg'>
            <th>Tanggal</th>
            <th>Judul Meeting</th>
            <th>Ruangan</th>
            <th>Jam Mulai</th>
            <th>Jam Selesai</th>
            <th>Status Approval</th>
            <th>Snack</th>
            <th>Coffee</th>
            <th>Hybrid Zoom</th>
            <th>Daftar Peserta (Status)</th>
          </tr></thead><tbody>";

    while ($row = $stmt->fetch()) {
        $stmt_p = $pdo->prepare("
            SELECT users.name, users.is_owner, attendances.status 
            FROM meeting_participants 
            JOIN users ON users.id = meeting_participants.user_id 
            LEFT JOIN attendances ON attendances.meeting_id = meeting_participants.meeting_id 
                AND attendances.user_id = meeting_participants.user_id
            WHERE meeting_participants.meeting_id = ?
        ");
        $stmt_p->execute([$row['id']]);
        $participants_data = $stmt_p->fetchAll();
        
        $p_list = [];
        foreach ($participants_data as $p) {
            $status = $p['status'] ?? 'Tidak Absen';
            if (isset($p['is_owner']) && $p['is_owner']) {
                $status = 'Tepat Waktu';
            }
            $p_list[] = ucwords(strtolower($p['name'])) . " (" . ucwords(strtolower($status)) . ")";
        }
        $participants_list = implode(', ', $p_list);

        $snack_text = (isset($row['has_snack']) && $row['has_snack']) ? 'Ya' : 'Tidak';
        $coffee_text = (isset($row['has_coffee']) && $row['has_coffee']) ? 'Ya (' . ucfirst($row['coffee_temp'] ?? '') . ' - ' . (($row['coffee_type'] ?? '') === 'bikin' ? 'Bikin' : 'Beli') . ')' : 'Tidak';
        $zoom_text = (isset($row['is_hybrid_zoom']) && $row['is_hybrid_zoom']) ? 'Ya' : 'Tidak';

        echo "<tr>
                <td>" . date('Y-m-d', strtotime($row['scheduled_time'])) . "</td>
                <td>" . htmlspecialchars(ucwords(strtolower($row['title']))) . "</td>
                <td>" . htmlspecialchars(ucwords(strtolower($row['room']))) . "</td>
                <td>" . date('H:i', strtotime($row['scheduled_time'])) . "</td>
                <td>" . date('H:i', strtotime($row['end_time'])) . "</td>
                <td>" . ucfirst(strtolower($row['status'])) . "</td>
                <td>" . $snack_text . "</td>
                <td>" . $coffee_text . "</td>
                <td>" . $zoom_text . "</td>
                <td>" . htmlspecialchars($participants_list) . "</td>
              </tr>";
    }
    echo "</tbody></table>";

} elseif ($type === 'detail') {
    // Mode: Detail Absensi per Meeting
    $meeting_id = $_GET['id'] ?? null;
    if (!$meeting_id) die("Meeting ID tidak valid.");

    $stmt_m = $pdo->prepare("SELECT meetings.*, users.name as pic_name FROM meetings LEFT JOIN users ON users.id = meetings.pic_id WHERE meetings.id = ?");
    $stmt_m->execute([$meeting_id]);
    $meeting = $stmt_m->fetch();
    if (!$meeting) die("Meeting tidak ditemukan.");

    if (!$is_admin && !$is_hr_role && $meeting['created_by'] != $user_id) {
        die("Akses ditolak.");
    }

    $snack_text = (isset($meeting['has_snack']) && $meeting['has_snack']) ? 'Ya' : 'Tidak';
    $coffee_text = (isset($meeting['has_coffee']) && $meeting['has_coffee']) ? 'Ya (' . ucfirst($meeting['coffee_temp'] ?? '') . ' - ' . (($meeting['coffee_type'] ?? '') === 'bikin' ? 'Bikin' : 'Beli') . ')' : 'Tidak';
    $zoom_text = (isset($meeting['is_hybrid_zoom']) && $meeting['is_hybrid_zoom']) ? 'Ya' : 'Tidak';

    echo "<div class='title'>Laporan Absensi Meeting: " . htmlspecialchars(ucwords(strtolower($meeting['title']))) . "</div>";
    echo "<div class='info'>
            Jadwal: " . date('d M Y, H:i', strtotime($meeting['scheduled_time'])) . "<br>
            Ruangan: " . htmlspecialchars(ucwords(strtolower($meeting['room']))) . "<br>
            PIC: " . htmlspecialchars(ucwords(strtolower($meeting['pic_name'] ?? '-'))) . "<br>
            Snack: " . $snack_text . "<br>
            Coffee: " . $coffee_text . "<br>
            Hybrid Zoom: " . $zoom_text . "
          </div>";

    echo "<table class='table'>";
    echo "<thead><tr class='header-bg'>
            <th>Nama Karyawan</th>
            <th>Status Absen</th>
            <th>Waktu Absen</th>";
    if ($include_hr_data) {
        echo "<th>Alasan Telat</th>
              <th>Q1 (Jadwal)</th>
              <th>Q2 (Notulen)</th>
              <th>Q3 (Tools)</th>
              <th>Q4 (Waktu Distribusi)</th>
              <th>Saran/Masukan</th>";
    }
    echo "</tr></thead><tbody>";

    $stmt_part = $pdo->prepare("SELECT users.id, users.name, users.is_owner FROM meeting_participants JOIN users ON users.id = meeting_participants.user_id WHERE meeting_id = ?");
    $stmt_part->execute([$meeting_id]);
    $participants = $stmt_part->fetchAll();

    foreach ($participants as $p) {
        $stmt_absen = $pdo->prepare("SELECT status, check_in_time, late_reason FROM attendances WHERE meeting_id = ? AND user_id = ?");
        $stmt_absen->execute([$meeting_id, $p['id']]);
        $absen = $stmt_absen->fetch();

        $status = $absen ? $absen['status'] : 'Tidak Absen';
        $waktu = $absen ? date('Y-m-d H:i:s', strtotime($absen['check_in_time'])) : '-';
        $late_reason = $absen ? ($absen['late_reason'] ?? '-') : '-';
        
        if (isset($p['is_owner']) && $p['is_owner']) {
            $status = 'Tepat Waktu';
            if (!$absen) $waktu = 'Owner (Auto)';
            $late_reason = '-';
        }

        echo "<tr>
                <td>" . htmlspecialchars(ucwords(strtolower($p['name']))) . "</td>
                <td>" . ucwords(strtolower($status)) . "</td>
                <td>" . $waktu . "</td>";
        
        if ($include_hr_data) {
            $stmt_fb = $pdo->prepare("SELECT * FROM meeting_feedbacks WHERE meeting_id = ? AND user_id = ?");
            $stmt_fb->execute([$meeting_id, $p['id']]);
            $fb = $stmt_fb->fetch();

            $q1 = $fb ? $fb['q1_rating'] : '-';
            $q2 = $fb ? $fb['q2_rating'] : '-';
            $q3 = $fb ? $fb['q3_rating'] : '-';
            $q4 = $fb ? $fb['q4_rating'] : '-';
            $feedback = $fb ? $fb['feedback_text'] : '-';
            
            echo "<td>" . htmlspecialchars(ucfirst(strtolower($late_reason))) . "</td>
                  <td>" . $q1 . "</td>
                  <td>" . $q2 . "</td>
                  <td>" . $q3 . "</td>
                  <td>" . $q4 . "</td>
                  <td>" . htmlspecialchars(ucfirst(strtolower($feedback))) . "</td>";
        }
        echo "</tr>";
    }
    echo "</tbody></table>";
}
?>
</body>
</html>
<?php exit; ?>
