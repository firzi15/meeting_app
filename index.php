<?php
session_start();
require_once 'database.php';

// Cleanup unnecessary temporary files
$junk_files = ['debug.php', 'debug2.php', 'debug_participants.php', 'exec.php', 'dump.php', 'rename.php', 'migrate_branches.php', 'migrate_templates.php', 'dump_meeting_local.sql'];
foreach ($junk_files as $jf) {
    if (file_exists($jf)) {
        @unlink($jf);
    }
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'] ?? 'user';
$is_superadmin = ($role === 'superadmin');
$is_admin = ($role === 'admin');
$has_dashboard = $is_superadmin || $is_admin || !empty($_SESSION['can_dashboard']);

if (!$has_dashboard) {
    header("Location: my_schedule.php");
    exit;
}

function renderGroupedUserOptions($pdo, $current_branch, $include_empty = false, $empty_label = '-- Pilih Karyawan --') {
    if ($include_empty) {
        echo '<option value="">' . htmlspecialchars($empty_label) . '</option>';
    }
    $stmt = $pdo->query("SELECT * FROM users WHERE role != 'superadmin' ORDER BY CASE WHEN branch_id = $current_branch THEN 0 ELSE 1 END ASC, name ASC");
    $users = $stmt->fetchAll();
    
    foreach ($users as $u) {
        $grp = $u['group_name'] ?? 'Staff';
        echo '<option value="' . $u['id'] . '" data-group="' . htmlspecialchars($grp) . '">' . htmlspecialchars($u['name']) . '</option>';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Indoarsip</title>
    <link rel="icon" type="image/png" href="logo_login.png">
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* Select2 Modern Multi-select & Scrollable Box Styling */
        .select2-container--default .select2-selection--multiple {
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            min-height: 44px !important;
            max-height: 150px !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            padding: 6px !important;
            display: block !important;
            box-sizing: border-box !important;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--primary-color, #4f46e5) !important;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12) !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: flex-start !important;
            gap: 6px !important;
            padding: 0 !important;
            margin: 0 !important;
            list-style: none !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            position: relative !important;
            display: inline-flex !important;
            align-items: center !important;
            background-color: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
            color: #1e293b !important;
            border-radius: 6px !important;
            padding: 3px 8px 3px 6px !important;
            margin: 0 !important;
            font-size: 0.8125rem !important;
            font-weight: 500 !important;
            line-height: 1.4 !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important;
            box-sizing: border-box !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__display {
            padding-left: 4px !important;
            padding-right: 2px !important;
            white-space: normal !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            position: static !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: transparent !important;
            border: none !important;
            border-right: none !important;
            color: #94a3b8 !important;
            border-radius: 50% !important;
            width: 16px !important;
            height: 16px !important;
            margin: 0 !important;
            padding: 0 !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            line-height: 1 !important;
            cursor: pointer !important;
            transition: all 0.15s ease !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            background: #fee2e2 !important;
            color: #ef4444 !important;
        }
        .select2-container--default .select2-selection--multiple .select2-search--inline {
            display: inline-flex !important;
            align-items: center !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
            margin: 0 !important;
            padding: 4px 6px !important;
            font-family: inherit !important;
            font-size: 0.85rem !important;
            min-width: 120px !important;
            border: none !important;
            outline: none !important;
            background: transparent !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__clear {
            display: none !important;
        }
        .select2-container--default .select2-selection--single {
            height: 44px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            background-color: #ffffff !important;
            display: flex !important;
            align-items: center !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #1e293b !important;
            font-family: inherit !important;
            font-size: 0.875rem !important;
            padding-left: 14px !important;
            padding-right: 28px !important;
            line-height: 42px !important;
            font-weight: 500 !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #94a3b8 !important;
            font-family: inherit !important;
            font-size: 0.875rem !important;
            font-weight: 400 !important;
        }
        .select2-container--default.select2-container--disabled .select2-selection--single {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed !important;
        }
        .select2-container--default.select2-container--disabled .select2-selection--single .select2-selection__rendered {
            color: #94a3b8 !important;
            font-family: inherit !important;
            font-size: 0.875rem !important;
            font-weight: 400 !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px !important;
            right: 10px !important;
        }
        .select2-dropdown {
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
            z-index: 99999 !important;
        }
        .select2-container--default .select2-results__group {
            font-weight: 700 !important;
            color: #1e293b !important;
            background: #f8fafc !important;
            padding: 6px 10px !important;
            border-bottom: 1px solid #e2e8f0 !important;
            border-top: 1px solid #e2e8f0 !important;
            font-size: 0.85rem !important;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: var(--primary-color, #4f46e5) !important;
            color: white !important;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-wrapper">
            <!-- Topbar -->
            <?php include 'topbar.php'; ?>

            <!-- Content Area -->
            <main class="content">
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h1 class="page-title">Meetings</h1>
                        <p class="page-subtitle">Plan meetings, check schedules, and stay connected with your team.</p>
                    </div>
                    <a href="tv.php" class="btn-filter" style="text-decoration: none; padding: 10px 16px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px;" title="Mode TV / Monitor">
                        <i class="fa-solid fa-tv"></i> Mode TV
                    </a>
                </div>

                <div class="dashboard-grid">
                    <!-- Left Side: Action Cards -->
                    <div>
                        <div class="action-cards-grid">
                            <!-- Card 1: Buat Meeting (modal trigger) -->
                            <div class="action-card primary-border" onclick="openScheduleModal()">
                                <div class="icon-circle-btn">
                                    <i class="fa-solid fa-plus"></i>
                                </div>
                                <h3>Buat Meeting</h3>
                                <p>Jadwalkan rapat baru untuk tim Anda.</p>
                            </div>

                            <!-- Card 2: Kalender -->
                            <a href="calendar.php" class="action-card">
                                <div class="icon-circle-btn outline">
                                    <i class="fa-solid fa-calendar-days"></i>
                                </div>
                                <h3>Kalender</h3>
                                <p>Lihat jadwal dan ketersediaan ruangan.</p>
                            </a>

                            <!-- Card 3: Laporan -->
                            <a href="<?= $has_dashboard ? 'report.php' : 'my_schedule.php' ?>" class="action-card">
                                <div class="icon-circle-btn outline">
                                    <i class="fa-solid fa-chart-bar"></i>
                                </div>
                                <h3>Laporan</h3>
                                <p>Rekap absensi dan riwayat meeting.</p>
                            </a>

                            <!-- Card 4: Master Template -->
                            <a href="templates.php" class="action-card">
                                <div class="icon-circle-btn outline">
                                    <i class="fa-solid fa-layer-group"></i>
                                </div>
                                <h3>Master Template</h3>
                                <p>Kelola template rapat yang sering digunakan.</p>
                            </a>
                        </div>
                    </div>

                    <!-- Right Side: Calendar Widget -->
                    <div>
                        <?php
                        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
                        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
                        if ($month < 1) { $month = 12; $year--; }
                        if ($month > 12) { $month = 1; $year++; }
                        if ($year < 1970 || $year > 2100) { $year = (int)date('Y'); }

                        $first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
                        $total_days = date('t', $first_day_of_month);
                        $start_day_of_week = date('N', $first_day_of_month); // 1 (Mon) to 7 (Sun)
                        
                        $month_names_id = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                        ];
                        $month_name = $month_names_id[$month] . ' ' . $year;

                        // Prev and Next Calculation
                        $prev_month = $month - 1;
                        $prev_year = $year;
                        if ($prev_month < 1) {
                            $prev_month = 12;
                            $prev_year--;
                        }
                        $next_month = $month + 1;
                        $next_year = $year;
                        if ($next_month > 12) {
                            $next_month = 1;
                            $next_year++;
                        }
                        
                        $current_branch = getCurrentBranchId();
                        $and_branch = $current_branch > 0 ? "AND branch_id = $current_branch" : "";
                        
                        $stmt_month_meetings = $pdo->prepare("
                            SELECT DISTINCT DATE(scheduled_time) as meeting_date 
                            FROM meetings 
                            WHERE EXTRACT(YEAR FROM scheduled_time) = ? 
                              AND EXTRACT(MONTH FROM scheduled_time) = ?
                              AND status = 'approved'
                              $and_branch
                        ");
                        $stmt_month_meetings->execute([$year, $month]);
                        $meeting_dates = $stmt_month_meetings->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <div class="calendar-card">
                            <div class="calendar-header">
                                <h3>Meeting Calendar</h3>
                                <button class="btn-filter" style="border:none; padding:4px; background:transparent;"><i class="fa-solid fa-ellipsis"></i></button>
                            </div>
                            <div class="calendar-nav">
                                <a href="index.php?month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="calendar-nav-btn" style="text-decoration: none; color: #ffffff; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-solid fa-chevron-left"></i></a>
                                <span><?= $month_name ?></span>
                                <a href="index.php?month=<?= $next_month ?>&year=<?= $next_year ?>" class="calendar-nav-btn" style="text-decoration: none; color: #ffffff; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-solid fa-chevron-right"></i></a>
                            </div>
                            <div class="calendar-grid">
                                <div class="calendar-day-name">Mon</div>
                                <div class="calendar-day-name">Tue</div>
                                <div class="calendar-day-name">Wed</div>
                                <div class="calendar-day-name">Thu</div>
                                <div class="calendar-day-name">Fri</div>
                                <div class="calendar-day-name">Sat</div>
                                <div class="calendar-day-name">Sun</div>
                                
                                <?php
                                // Empty cells before first day
                                for ($i = 1; $i < $start_day_of_week; $i++) {
                                    echo '<div class="calendar-date-cell other-month"></div>';
                                }
                                
                                // Days of month
                                $today_day = date('j');
                                for ($day = 1; $day <= $total_days; $day++) {
                                    $cell_date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                    $has_meeting = in_array($cell_date, $meeting_dates);
                                    
                                    $class = 'calendar-date-cell';
                                    
                                    echo '<div class="' . $class . '" data-date="' . $cell_date . '" style="cursor: pointer;" onclick="filterTableByDate(\'' . $cell_date . '\', this)">';
                                    echo $day;
                                    if ($has_meeting) {
                                        echo '<div class="calendar-dot dot-green"></div>';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Unified Meeting Schedule Table -->
                <div class="schedule-table-card">
                    <div class="schedule-table-header">
                        <h3 class="schedule-table-title">Meeting Schedule</h3>
                        <div class="schedule-table-filters">
                            <div class="schedule-search-wrapper">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="tableSearch" class="schedule-search-input" placeholder="Search meeting...">
                            </div>
                            <?php
                            $stmt_rooms_filter = $pdo->query("SELECT name FROM rooms " . ($current_branch > 0 ? "WHERE branch_id = $current_branch" : "") . " ORDER BY name ASC");
                            $rooms_filter = $stmt_rooms_filter->fetchAll();
                            ?>
                            <!-- Custom styled dropdown -->
                            <div class="custom-dropdown" id="roomDropdown">
                                <button type="button" class="btn-filter custom-dropdown-trigger" id="roomDropdownTrigger">
                                    <span id="roomDropdownLabel">Semua Ruangan</span>
                                    <i class="fa-solid fa-chevron-down" style="font-size:0.7rem; margin-left:4px; transition:transform 0.2s;" id="roomDropdownChevron"></i>
                                </button>
                                <div class="custom-dropdown-menu" id="roomDropdownMenu">
                                    <div class="custom-dropdown-item active" data-value="">Semua Ruangan</div>
                                    <?php foreach ($rooms_filter as $rf): ?>
                                        <div class="custom-dropdown-item" data-value="<?= htmlspecialchars($rf['name']) ?>"><?= htmlspecialchars($rf['name']) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="schedule-table-container">
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th>Meeting Details</th>
                                    <th>Team / Room</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Participants</th>
                                    <th>Fasilitas</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="scheduleTableBody">
                                <?php
                                 $and_branch_m = $current_branch > 0 ? "AND m.branch_id = $current_branch" : "";
                                 
                                 // Pagination setup
                                 $limit = 5;
                                 $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
                                 if ($page < 1) $page = 1;
                                 $offset = ($page - 1) * $limit;

                                 if ($has_dashboard) {
                                     $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM meetings m WHERE 1=1 $and_branch_m");
                                     $stmt_count->execute();
                                 } else {
                                     $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM meetings m WHERE 1=1 $and_branch_m AND (m.status = 'approved' OR m.created_by = ?)");
                                     $stmt_count->execute([$_SESSION['user_id']]);
                                 }
                                 $total_meetings = $stmt_count->fetchColumn();
                                 $total_pages = ceil($total_meetings / $limit);

                                 if ($has_dashboard) {
                                     $stmt_schedule = $pdo->prepare("
                                         SELECT m.*, b.name as branch_name 
                                         FROM meetings m 
                                         LEFT JOIN branches b ON m.branch_id = b.id
                                         WHERE 1=1 $and_branch_m 
                                         ORDER BY m.scheduled_time DESC
                                         LIMIT $limit OFFSET $offset
                                     ");
                                     $stmt_schedule->execute();
                                 } else {
                                     $stmt_schedule = $pdo->prepare("
                                         SELECT m.*, b.name as branch_name 
                                         FROM meetings m 
                                         LEFT JOIN branches b ON m.branch_id = b.id
                                         WHERE 1=1 $and_branch_m 
                                         AND (m.status = 'approved' OR m.created_by = ?)
                                         ORDER BY m.scheduled_time DESC
                                         LIMIT $limit OFFSET $offset
                                     ");
                                     $stmt_schedule->execute([$_SESSION['user_id']]);
                                 }
                                  $schedule_meetings = $stmt_schedule->fetchAll();
                                  
                                  $has_active = false;
                                  foreach ($schedule_meetings as $rm) {
                                      if (date('Y-m-d', strtotime($rm['scheduled_time'])) >= date('Y-m-d')) {
                                          $has_active = true;
                                          break;
                                      }
                                  }
                                  
                                  if (empty($schedule_meetings)):
                                  ?>
                                      <tr id="emptyScheduleRow">
                                          <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                              <i class="fa-solid fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                              Belum ada jadwal meeting.
                                          </td>
                                      </tr>
                                  <?php
                                  else:
                                  ?>
                                      <tr id="emptyScheduleRow" <?= $has_active ? 'style="display: none;"' : '' ?>>
                                          <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                              <i class="fa-solid fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                              Tidak ada jadwal meeting aktif.
                                          </td>
                                      </tr>
                                  <?php
                                     foreach ($schedule_meetings as $rm):
                                         $meeting_id = $rm['id'];
                                         $pic_id = $rm['pic_id'];
                                         
                                         // Get PIC and Participant Avatars (fallback to pic_id if meeting_participants is incomplete)
                                         $stmt_part = $pdo->prepare("
                                             SELECT u.name, u.photo 
                                             FROM (
                                                 SELECT user_id FROM meeting_participants WHERE meeting_id = ?
                                                 UNION
                                                 SELECT pic_id AS user_id FROM meetings WHERE id = ?
                                             ) mp 
                                             JOIN users u ON mp.user_id = u.id 
                                             LIMIT 3
                                         ");
                                         $stmt_part->execute([$meeting_id, $meeting_id]);
                                         $participants = $stmt_part->fetchAll();
                                         
                                         $stmt_count_part = $pdo->prepare("
                                             SELECT COUNT(*) FROM (
                                                 SELECT user_id FROM meeting_participants WHERE meeting_id = ?
                                                 UNION
                                                 SELECT pic_id AS user_id FROM meetings WHERE id = ?
                                             ) sub
                                         ");
                                         $stmt_count_part->execute([$meeting_id, $meeting_id]);
                                         $total_part = $stmt_count_part->fetchColumn();
                                         
                                         $is_inactive = ($rm['status'] === 'pending' || $rm['status'] === 'rejected') && !$has_dashboard;
                                         if ($is_inactive) {
                                             $item_url = 'javascript:void(0)';
                                         } elseif ($has_dashboard) {
                                             $item_url = ($rm['status'] === 'pending') ? 'approval.php' : 'report.php?id=' . $rm['id'];
                                         } else {
                                             $item_url = 'attendance.php?token=' . $rm['token'];
                                         }
                                         
                                         $meeting_date = date('Y-m-d', strtotime($rm['scheduled_time']));
                                         $today_date = date('Y-m-d');
                                 ?>
                                     <tr class="meeting-row" data-date="<?= $meeting_date ?>" data-room="<?= htmlspecialchars($rm['room']) ?>" <?= ($meeting_date !== $today_date) ? 'style="display: none;"' : '' ?>>
                                        <td>
                                            <div class="meeting-title-col"><?= htmlspecialchars($rm['title']) ?></div>
                                        </td>
                                        <td>
                                            <div class="tag-value badge-team">
                                                <span class="badge-dot" style="background-color: <?= $rm['status'] === 'approved' ? '#10b981' : '#f59e0b' ?>;"></span>
                                                <?= htmlspecialchars($rm['room']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="tag-value"><?= date('M d, Y', strtotime($rm['scheduled_time'])) ?></div>
                                        </td>
                                        <td>
                                            <div class="tag-value"><?= date('H:i', strtotime($rm['scheduled_time'])) ?> - <?= date('H:i', strtotime($rm['end_time'])) ?></div>
                                        </td>
                                        <td>
                                            <div class="avatar-stack">
                                                <?php foreach ($participants as $p): ?>
                                                    <?php if ($p['photo']): ?>
                                                        <img src="uploads/profiles/<?= htmlspecialchars($p['photo']) ?>" class="avatar-stack-item" title="<?= htmlspecialchars($p['name']) ?>">
                                                    <?php else: ?>
                                                        <div class="avatar-stack-item" title="<?= htmlspecialchars($p['name']) ?>"><?= strtoupper(substr($p['name'], 0, 1)) ?></div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <?php if ($total_part > 3): ?>
                                                    <div class="avatar-stack-more">+<?= ($total_part - 3) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="meeting-feature-badges" style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                <?php if (!empty($rm['has_coffee'])): ?>
                                                    <span class="feature-badge coffee" title="Kopi: <?= ucfirst($rm['coffee_temp'] ?? '') ?> - <?= ($rm['coffee_type'] ?? '') === 'bikin' ? 'Bikin' : 'Beli' ?>">
                                                        <i class="fa-solid fa-mug-hot"></i>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($rm['has_snack'])): ?>
                                                    <span class="feature-badge snack" title="Snack tersedia">
                                                        <i class="fa-solid fa-cookie-bite"></i>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($rm['is_hybrid_zoom'])): ?>
                                                    <span class="feature-badge zoom" title="Hybrid Zoom">
                                                        <i class="fa-solid fa-video"></i>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (empty($rm['has_coffee']) && empty($rm['has_snack']) && empty($rm['is_hybrid_zoom'])): ?>
                                                    <span style="color:#cbd5e1; font-size:0.75rem;">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $now_ts = time();
                                            $start_ts = strtotime($rm['scheduled_time']);
                                            $end_ts = strtotime($rm['end_time']);
                                            if ($rm['status'] === 'finished' || $now_ts > $end_ts) {
                                                $status_text = 'Berakhir';
                                                $status_color = '#64748b';
                                            } elseif ($now_ts >= $start_ts && $now_ts <= $end_ts) {
                                                $status_text = 'Berjalan';
                                                $status_color = '#10b981';
                                            } else {
                                                $status_text = 'Belum Mulai';
                                                $status_color = '#3b82f6';
                                            }
                                            ?>
                                            <span style="background-color: <?= $status_color ?>; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; white-space: nowrap; display: inline-block;">
                                                <?= $status_text ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="schedule-actions">
                                                <?php if (!$is_inactive): ?>
                                                    <?php if (!$has_dashboard || $status_text === 'Berakhir'): ?>
                                                        <a href="<?= $item_url ?>" class="btn-icon-dark" title="Laporan / Presensi">
                                                            <i class="fa-solid fa-video"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <button type="button"
                                                    class="btn-icon-outline"
                                                    title="Lihat QR Absensi"
                                                    onclick="showQRModal('<?= htmlspecialchars(addslashes($rm['title'])) ?>', '<?= htmlspecialchars(rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']), '/') . '/attendance.php?token=' . $rm['token']) ?>')">
                                                    <i class="fa-solid fa-qrcode"></i>
                                                </button>
                                                <?php if ($has_dashboard): ?>
                                                    <?php if ($status_text !== 'Berakhir'): ?>
                                                        <button type="button"
                                                            class="btn-icon-outline"
                                                            title="Edit Meeting"
                                                            onclick="openEditModal(<?= $rm['id'] ?>)">
                                                            <i class="fa-solid fa-pen-to-square"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button"
                                                        class="btn-icon-outline btn-icon-danger"
                                                        title="Hapus Meeting"
                                                        onclick="confirmDeleteMeeting(<?= $rm['id'] ?>, '<?= htmlspecialchars(addslashes($rm['title'])) ?>')">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                    endforeach;
                                endif;
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="display:flex; justify-content:center; gap:8px; margin-top:20px; padding-bottom:20px;">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="index.php?page=<?= $i ?>" class="page-link <?= ($page == $i) ? 'active' : '' ?>" style="text-decoration:none; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 0.9rem; <?= ($page == $i) ? 'background:#8b5cf6; color:white; border-color:#8b5cf6;' : 'background:white;' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </main>

            <?php include 'footer.php'; ?>
        </div>
    </div>
    <!-- Modal Buat Jadwal (Always available, but results in 'pending' if not admin) -->
    <div id="scheduleModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 650px; border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <div>
                    <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700;">Buat Jadwal Meeting Baru</h3>
                    <p style="margin: 4px 0 0; font-size: 0.875rem; opacity: 0.8;">Isi detail meeting untuk mendapatkan link absensi otomatis</p>
                </div>
                <button class="modal-close" onclick="document.getElementById('scheduleModal').classList.remove('active')" style="color: white; opacity: 0.7;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px 25px; background: #fff;">
                <form id="scheduleForm">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label"><i class="fa-solid fa-copy" style="margin-right: 8px; color: var(--primary-color);"></i> Pilih Template (Opsional)</label>
                        <select name="template_id" id="templateSelect" class="form-control" style="width: 100%;">
                            <option value="">-- Tidak Menggunakan Template --</option>
                            <?php
                            $current_branch = getCurrentBranchId();
                            $branch_condition = $current_branch > 0 ? "WHERE branch_id = $current_branch" : "";
                            $stmt_templates = $pdo->query("SELECT id, name FROM meeting_templates $branch_condition ORDER BY name ASC");
                            while($t = $stmt_templates->fetch()) {
                                echo "<option value=\"{$t['id']}\">".htmlspecialchars($t['name'])."</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label">Judul Meeting</label>
                        <input type="text" name="title" id="meetingTitle" class="form-control" required placeholder="Contoh: Rapat Koordinasi Mingguan">
                    </div>

                    <div class="schedule-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Ruang Meeting</label>
                            <select name="room" id="meetingRoomSelect" class="form-control" required>
                                <option value="">-- Pilih Ruangan --</option>
                                <?php
                                $stmt_rooms_m = $pdo->query("SELECT name FROM rooms $branch_condition ORDER BY name ASC");
                                while($r = $stmt_rooms_m->fetch()) {
                                    echo "<option value=\"".htmlspecialchars($r['name'])."\">".htmlspecialchars($r['name'])."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Toleransi (Menit)</label>
                            <input type="number" name="late_tolerance" class="form-control" value="15" min="0" required>
                        </div>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 0.8rem;">Tanggal Pelaksanaan</label>
                            <input type="date" name="date" class="form-control" required style="padding: 8px 12px;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.8rem;">Jam Mulai</label>
                                <input type="time" name="time" class="form-control" required style="padding: 8px 12px;">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.8rem;">Jam Selesai</label>
                                <input type="time" name="end_time" class="form-control" required style="padding: 8px 12px;">
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label"><i class="fa-solid fa-users" style="margin-right: 8px; color: var(--primary-color);"></i> Peserta Diundang</label>
                        
                        <!-- Quick Group Selection Bar -->
                        <div style="display: flex; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; align-items: center; background: #f8fafc; padding: 8px 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <span style="font-size: 0.8rem; font-weight: 600; color: #475569;">Tambah Cepat:</span>
                            <button type="button" onclick="addParticipantsByGroup('participantSelect', 'Manager')" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                + Manager
                            </button>
                            <button type="button" onclick="addParticipantsByGroup('participantSelect', 'Kepala Bagian (Kabag)')" style="background: #d1fae5; color: #047857; border: 1px solid #a7f3d0; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                + Kabag
                            </button>
                            <button type="button" onclick="addParticipantsByGroup('participantSelect', 'Staff')" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                + Staff
                            </button>
                            <button type="button" onclick="clearParticipants('participantSelect')" style="background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer; margin-left: auto;">
                                Kosongkan
                            </button>
                        </div>

                        <select name="participants[]" id="participantSelect" class="form-control" multiple="multiple" style="width: 100%;">
                            <?php renderGroupedUserOptions($pdo, $current_branch, false); ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label"><i class="fa-solid fa-user-tie" style="margin-right: 8px; color: var(--primary-color);"></i> PIC Meeting</label>
                        <select name="pic_id" id="picSelect" class="form-control" required style="width: 100%;" disabled>
                            <option value="">Pilih Peserta Diundang Terlebih Dahulu</option>
                        </select>
                    </div>

                    <!-- Food, Beverages & Facilities Options -->
                    <div id="consumptionPanel" style="background: #f8fafc; border: 1px solid #e5e7eb; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
                        <label class="form-label" style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-cookie-bite" style="color: var(--primary-color);"></i> Konsumsi & Fasilitas</label>
                        <div style="display: flex; gap: 24px; align-items: center; margin-bottom: 4px; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                                <input type="checkbox" name="has_snack" value="1" style="width: 16px; height: 16px; border-radius: 4px; border: 1px solid #d1d5db; accent-color: var(--primary-color);"> Snack
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                                <input type="checkbox" id="hasCoffeeCheckbox" name="has_coffee" value="1" style="width: 16px; height: 16px; border-radius: 4px; border: 1px solid #d1d5db; accent-color: var(--primary-color);" onchange="toggleCoffeeOptions(this.checked)"> Coffee
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                                <input type="checkbox" name="is_hybrid_zoom" value="1" style="width: 16px; height: 16px; border-radius: 4px; border: 1px solid #d1d5db; accent-color: var(--primary-color);"> Hybrid Zoom
                            </label>
                        </div>
                        
                        <!-- Coffee details sub-options -->
                        <div id="coffeeOptionsContainer" style="display: none; border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 12px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 0.75rem; color: #6b7280;">Suhu Kopi</label>
                                    <select name="coffee_temp" id="coffeeTempSelect" style="width: 100%;">
                                        <option value="panas">Panas</option>
                                        <option value="dingin">Dingin</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 0.75rem; color: #6b7280;">Metode Penyediaan</label>
                                    <select name="coffee_type" id="coffeeTypeSelect" style="width: 100%;">
                                        <option value="bikin">Bikin Sendiri</option>
                                        <option value="beli">Beli Luar</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn" style="width: 100%; padding: 12px; font-size: 0.95rem;">
                        <i class="fa-solid fa-calendar-check" style="margin-right: 8px;"></i> Simpan Jadwal Meeting
                    </button>
                </form>
            </div>
            </div>
        </div>
    </div>

    <!-- Edit Meeting Modal -->
    <div id="editMeetingModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 650px; border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <div>
                    <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700;">Edit Jadwal Meeting</h3>
                    <p style="margin: 4px 0 0; font-size: 0.875rem; opacity: 0.8;">Perbarui detail meeting yang sudah ada</p>
                </div>
                <button class="modal-close" onclick="document.getElementById('editMeetingModal').classList.remove('active')" style="color: white; opacity: 0.7;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px 25px; background: #fff;">
                <form id="editScheduleForm">
                    <input type="hidden" name="id" id="editMeetingId">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label">Judul Meeting</label>
                        <input type="text" name="title" id="editMeetingTitle" class="form-control" required>
                    </div>

                    <div class="schedule-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Ruang Meeting</label>
                            <select name="room" id="editMeetingRoom" class="form-control" required>
                                <option value="">-- Pilih Ruangan --</option>
                                <?php
                                $current_branch = getCurrentBranchId();
                                $branch_condition = $current_branch > 0 ? "WHERE branch_id = $current_branch" : "";
                                $stmt_rooms_edit = $pdo->query("SELECT name FROM rooms $branch_condition ORDER BY name ASC");
                                while($r = $stmt_rooms_edit->fetch()) {
                                    echo "<option value=\"".htmlspecialchars($r['name'])."\">".htmlspecialchars($r['name'])."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Toleransi (Menit)</label>
                            <input type="number" name="late_tolerance" id="editLateTolerance" class="form-control" required>
                        </div>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 0.8rem;">Tanggal Pelaksanaan</label>
                            <input type="date" name="date" id="editMeetingDate" class="form-control" required style="padding: 8px 12px;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.8rem;">Jam Mulai</label>
                                <input type="time" name="time" id="editMeetingTime" class="form-control" required style="padding: 8px 12px;">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.8rem;">Jam Selesai</label>
                                <input type="time" name="end_time" id="editMeetingEndTime" class="form-control" required style="padding: 8px 12px;">
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label"><i class="fa-solid fa-users" style="margin-right: 8px; color: var(--primary-color);"></i> Peserta Diundang</label>
                        
                        <!-- Quick Group Selection Bar -->
                        <div style="display: flex; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; align-items: center; background: #f8fafc; padding: 8px 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <span style="font-size: 0.8rem; font-weight: 600; color: #475569;">Tambah Cepat:</span>
                            <button type="button" onclick="addParticipantsByGroup('editParticipantSelect', 'Manager')" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                + Manager
                            </button>
                            <button type="button" onclick="addParticipantsByGroup('editParticipantSelect', 'Kepala Bagian (Kabag)')" style="background: #d1fae5; color: #047857; border: 1px solid #a7f3d0; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                + Kabag
                            </button>
                            <button type="button" onclick="addParticipantsByGroup('editParticipantSelect', 'Staff')" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                + Staff
                            </button>
                            <button type="button" onclick="clearParticipants('editParticipantSelect')" style="background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer; margin-left: auto;">
                                Kosongkan
                            </button>
                        </div>

                        <select name="participants[]" id="editParticipantSelect" class="form-control" multiple="multiple" style="width: 100%;">
                            <?php renderGroupedUserOptions($pdo, $current_branch, false); ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label"><i class="fa-solid fa-user-tie" style="margin-right: 8px; color: var(--primary-color);"></i> PIC Meeting</label>
                        <select name="pic_id" id="editPicSelect" class="form-control" required style="width: 100%;" disabled>
                            <option value="">Pilih Peserta Diundang Terlebih Dahulu</option>
                        </select>
                    </div>

                    <!-- Food, Beverages & Facilities Options -->
                    <div id="editConsumptionPanel" style="background: #f8fafc; border: 1px solid #e5e7eb; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
                        <label class="form-label" style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-cookie-bite" style="color: var(--primary-color);"></i> Konsumsi & Fasilitas</label>
                        <div style="display: flex; gap: 24px; align-items: center; margin-bottom: 4px; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                                <input type="checkbox" name="has_snack" id="editHasSnack" value="1" style="width: 16px; height: 16px; border-radius: 4px; border: 1px solid #d1d5db; accent-color: var(--primary-color);"> Snack
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                                <input type="checkbox" id="editHasCoffeeCheckbox" name="has_coffee" value="1" style="width: 16px; height: 16px; border-radius: 4px; border: 1px solid #d1d5db; accent-color: var(--primary-color);" onchange="toggleEditCoffeeOptions(this.checked)"> Coffee
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                                <input type="checkbox" name="is_hybrid_zoom" id="editHybridZoom" value="1" style="width: 16px; height: 16px; border-radius: 4px; border: 1px solid #d1d5db; accent-color: var(--primary-color);"> Hybrid Zoom
                            </label>
                        </div>
                        
                        <!-- Coffee details sub-options -->
                        <div id="editCoffeeOptionsContainer" style="display: none; border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 12px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 0.75rem; color: #6b7280;">Suhu Kopi</label>
                                    <select name="coffee_temp" id="editCoffeeTemp" style="width: 100%;">
                                        <option value="panas">Panas</option>
                                        <option value="dingin">Dingin</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 0.75rem; color: #6b7280;">Metode Penyediaan</label>
                                    <select name="coffee_type" id="editCoffeeType" style="width: 100%;">
                                        <option value="bikin">Bikin Sendiri</option>
                                        <option value="beli">Beli Luar</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="editSubmitBtn" style="width: 100%; padding: 12px; font-size: 0.95rem;">
                        <i class="fa-solid fa-save" style="margin-right: 8px;"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Feature badges (coffee / snack / zoom) */
        .meeting-feature-badges {
            display: flex;
            gap: 4px;
            margin-top: 5px;
            flex-wrap: wrap;
        }
        .feature-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 0.7rem;
            padding: 2px 7px;
            border-radius: 20px;
            font-weight: 600;
        }
        .feature-badge.coffee  { background: #fef3c7; color: #b45309; }
        .feature-badge.snack   { background: #fce7f3; color: #be185d; }
        .feature-badge.zoom    { background: #ede9fe; color: #6d28d9; }

        /* Danger action button */
        button.btn-icon-danger {
            color: #ef4444;
        }
        button.btn-icon-danger:hover {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #dc2626;
        }

        /* Custom Room Filter Dropdown */
        .custom-dropdown {
            position: relative;
            display: inline-block;
        }
        .custom-dropdown-trigger {
            min-width: 160px;
            justify-content: space-between;
        }
        .custom-dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            min-width: 180px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            z-index: 999;
            overflow: hidden;
            animation: dropdownFadeIn 0.15s ease;
        }
        .custom-dropdown-menu.open {
            display: block;
        }
        @keyframes dropdownFadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .custom-dropdown-item {
            padding: 9px 14px;
            font-size: 0.875rem;
            color: #374151;
            cursor: pointer;
            transition: background 0.15s;
        }
        .custom-dropdown-item:hover {
            background: #f3f4f6;
        }
        .custom-dropdown-item.active {
            color: var(--primary-color, #3f51b5);
            font-weight: 600;
            background: #f0f4ff;
        }
    </style>

    <script>
        // --- Live Search + Room Filter + Date Filter ---
        let _activeRoom = '';
        let _activeDate = '<?= date("Y-m-d") ?>';

        function applyTableFilters() {
            const searchVal = (document.getElementById('tableSearch')?.value || '').toLowerCase();
            const roomVal   = _activeRoom;
            const dateVal   = _activeDate;
            const rows      = document.querySelectorAll('#scheduleTableBody tr.meeting-row');
            const emptyRow  = document.getElementById('emptyScheduleRow');
            let visible = 0;

            rows.forEach(row => {
                const title   = (row.querySelector('.meeting-title-col')?.textContent || '').toLowerCase();
                const room    = row.getAttribute('data-room') || '';
                const rowDate = row.getAttribute('data-date');

                const matchSearch = !searchVal || title.includes(searchVal);
                const matchRoom   = !roomVal   || room === roomVal;
                const matchDate   = !dateVal   || rowDate === dateVal;

                if (matchSearch && matchRoom && matchDate) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (emptyRow) emptyRow.style.display = (visible === 0 ? '' : 'none');
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Live search
            document.getElementById('tableSearch')?.addEventListener('input', applyTableFilters);

            // Custom dropdown logic
            const trigger  = document.getElementById('roomDropdownTrigger');
            const menu     = document.getElementById('roomDropdownMenu');
            const label    = document.getElementById('roomDropdownLabel');
            const chevron  = document.getElementById('roomDropdownChevron');
            const items    = menu?.querySelectorAll('.custom-dropdown-item');

            trigger?.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = menu.classList.toggle('open');
                chevron.style.transform = isOpen ? 'rotate(180deg)' : 'rotate(0deg)';
            });

            items?.forEach(item => {
                item.addEventListener('click', () => {
                    _activeRoom = item.dataset.value;
                    label.textContent = item.textContent;
                    items.forEach(i => i.classList.remove('active'));
                    item.classList.add('active');
                    menu.classList.remove('open');
                    chevron.style.transform = 'rotate(0deg)';
                    applyTableFilters();
                });
            });

            // Close when clicking outside
            document.addEventListener('click', () => {
                menu?.classList.remove('open');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            });
        });


        function toggleCoffeeOptions(checked) {
            const container = document.getElementById('coffeeOptionsContainer');
            if (container) {
                container.style.display = checked ? 'block' : 'none';
            }
        }

        function filterTableByDate(dateStr, element) {
            const isAlreadyActive = element ? element.classList.contains('active-filter-date') : false;
            
            // Reset all cells
            document.querySelectorAll('.calendar-date-cell').forEach(el => {
                el.classList.remove('active-filter-date');
            });
            
            if (isAlreadyActive) {
                // Toggled off -> default to today
                _activeDate = '<?= date("Y-m-d") ?>';
            } else {
                _activeDate = dateStr;
                if (element) element.classList.add('active-filter-date');
            }
            
            applyTableFilters();
        }

        $(document).ready(function() {
            <?php if (isset($_SESSION['success'])): ?>
                Toast.fire({ icon: 'success', title: <?= json_encode($_SESSION['success']) ?> });
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                Toast.fire({ icon: 'error', title: <?= json_encode($_SESSION['error']) ?> });
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            $('#picSelect').select2({
                placeholder: "Pilih PIC Meeting",
                allowClear: true,
                dropdownParent: $('#scheduleModal'),
                width: '100%'
            });

            $('#participantSelect').select2({
                placeholder: "Pilih Peserta Diundang",
                allowClear: true,
                dropdownParent: $('#scheduleModal'),
                width: '100%',
                closeOnSelect: false
            });

            $('#editPicSelect').select2({
                placeholder: "Pilih PIC Meeting",
                allowClear: true,
                dropdownParent: $('#editMeetingModal'),
                width: '100%'
            });

            $('#editParticipantSelect').select2({
                placeholder: "Pilih Peserta Diundang",
                allowClear: true,
                dropdownParent: $('#editMeetingModal'),
                width: '100%',
                closeOnSelect: false
            });

            // Prevent dropdown opening & preserve scroll position when removing tags (prevents jumping to bottom search field)
            $('#participantSelect, #editParticipantSelect').on('select2:unselect', function (e) {
                var selectEl = this;
                var $container = $(selectEl).next('.select2-container').find('.select2-selection--multiple');
                var currentScroll = $container.scrollTop();
                
                setTimeout(function() {
                    $(selectEl).select2('close');
                    $container.scrollTop(currentScroll);
                    $container.find('.select2-search__field').blur();
                }, 0);
            });

            $(document).on('mousedown click', '.select2-selection__choice__remove', function(e) {
                var $container = $(this).closest('.select2-selection--multiple');
                var currentScroll = $container.scrollTop();
                setTimeout(function() {
                    $container.scrollTop(currentScroll);
                    $container.find('.select2-search__field').blur();
                }, 10);
            });

            $('#coffeeTempSelect, #coffeeTypeSelect').select2({
                dropdownParent: $('#scheduleModal'),
                minimumResultsForSearch: Infinity,
                width: '100%'
            });

            $('#editCoffeeTemp, #editCoffeeType').select2({
                dropdownParent: $('#editMeetingModal'),
                minimumResultsForSearch: Infinity,
                width: '100%'
            });

            // Table search logic
            $('#tableSearch').on('keyup', function() {
                const query = $(this).val().toLowerCase();
                $('#scheduleTableBody tr.meeting-row').each(function() {
                    const titleText = $(this).find('.meeting-title-col').text().toLowerCase();
                    const descText = $(this).find('.meeting-desc-col').text().toLowerCase();
                    if (titleText.includes(query) || descText.includes(query)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Toggle consumption panel based on meeting room
            $('#meetingRoomSelect').on('change', function() {
                const roomVal = $(this).val();
                if (roomVal && roomVal.toLowerCase() === 'online') {
                    $('#consumptionPanel').hide();
                } else {
                    $('#consumptionPanel').show();
                }
            });

            $('#editMeetingRoom').on('change', function() {
                const roomVal = $(this).val();
                if (roomVal && roomVal.toLowerCase() === 'online') {
                    $('#editConsumptionPanel').hide();
                } else {
                    $('#editConsumptionPanel').show();
                }
            });

            // Function to dynamically populate PIC options based on selected participants
            window.updatePicOptions = function(participantSelectId, picSelectId, forcedPicId) {
                var $part = $('#' + participantSelectId);
                var $pic = $('#' + picSelectId);
                var selectedValues = $part.val() || [];
                if (!Array.isArray(selectedValues)) selectedValues = [selectedValues];
                
                var currentPicVal = (forcedPicId !== undefined) ? forcedPicId : $pic.val();
                
                $pic.empty();
                
                if (selectedValues.length === 0) {
                    $pic.append(new Option('Pilih Peserta Diundang Terlebih Dahulu', '', true, true));
                    $pic.prop('disabled', true);
                    $pic.val('').trigger('change.select2');
                } else {
                    $pic.prop('disabled', false);
                    $pic.append(new Option('Pilih PIC Meeting', '', true, !currentPicVal));
                    
                    var picStillValid = false;
                    selectedValues.forEach(function(val) {
                        var opt = $part.find('option[value="' + val + '"]');
                        var text = opt.text();
                        var isSelected = (String(val) === String(currentPicVal));
                        if (isSelected) picStillValid = true;
                        $pic.append(new Option(text, val, false, isSelected));
                    });
                    
                    if (picStillValid && currentPicVal) {
                        $pic.val(currentPicVal);
                    } else {
                        $pic.val('');
                    }
                    $pic.trigger('change.select2');
                }
            };

            $('#participantSelect').on('change', function() {
                updatePicOptions('participantSelect', 'picSelect');
            });

            $('#editParticipantSelect').on('change', function() {
                updatePicOptions('editParticipantSelect', 'editPicSelect');
            });

            // Template selection logic
            $('#templateSelect').on('change', function() {
                const templateId = $(this).val();
                if (templateId) {
                    $.ajax({
                        url: 'get_template.php',
                        type: 'GET',
                        data: { id: templateId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                $('#meetingTitle').val(response.title);
                                
                                // Set Participants first, then PIC
                                $('#participantSelect').val(response.participants).trigger('change');
                                setTimeout(() => {
                                    updatePicOptions('participantSelect', 'picSelect', response.pic_id);
                                }, 50);
                                
                                Toast.fire({ icon: 'success', title: 'Template berhasil dimuat.' });
                            } else {
                                Toast.fire({ icon: 'error', title: response.message });
                            }
                        },
                        error: function() {
                            Toast.fire({ icon: 'error', title: 'Terjadi kesalahan saat memuat template.' });
                        }
                    });
                } else {
                    $('#meetingTitle').val('');
                    $('#participantSelect').val([]).trigger('change');
                    updatePicOptions('participantSelect', 'picSelect');
                }
            });
        });

        function openFeedback(meetingId, title) {
            Swal.fire({
                title: 'Feedback Meeting',
                html: `<p style="margin-bottom:15px; font-size: 0.9rem;"><strong>${title}</strong></p>
                       <form id="feedbackForm">
                           <input type="hidden" name="meeting_id" value="${meetingId}">
                           <div style="margin-bottom: 15px; text-align: left;">
                               <label style="display:block; margin-bottom: 8px; font-weight:600; font-size:0.9rem;">Rating Kepuasan</label>
                               <select name="rating" class="form-control" required style="width:100%; border:1px solid #ccc; border-radius:5px; padding:8px;">
                                   <option value="5">⭐⭐⭐⭐⭐ (Sangat Baik)</option>
                                   <option value="4">⭐⭐⭐⭐ (Baik)</option>
                                   <option value="3">⭐⭐⭐ (Cukup)</option>
                                   <option value="2">⭐⭐ (Kurang)</option>
                                   <option value="1">⭐ (Sangat Kurang)</option>
                               </select>
                           </div>
                           <div style="margin-bottom: 15px; text-align: left;">
                               <label style="display:block; margin-bottom: 8px; font-weight:600; font-size:0.9rem;">Komentar / Masukan</label>
                               <textarea name="feedback_text" rows="4" class="form-control" required style="width:100%; border:1px solid #ccc; border-radius:5px; padding:8px; box-sizing:border-box;" placeholder="Tulis masukan Anda di sini..."></textarea>
                           </div>
                       </form>`,
                showCancelButton: true,
                confirmButtonText: 'Kirim Feedback',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#3f51b5',
                preConfirm: () => {
                    const form = document.getElementById('feedbackForm');
                    const fd = new FormData(form);
                    if(!fd.get('feedback_text').trim()) {
                        Swal.showValidationMessage('Komentar tidak boleh kosong');
                        return false;
                    }
                    return fetch('submit_feedback.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Terjadi kesalahan sistem');
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Request failed: ${error.message}`);
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Toast.fire({
                        icon: 'success',
                        title: 'Terkirim!',
                        text: 'Terima kasih atas feedback Anda.'
                    }).then(() => location.reload());
                }
            });
        }

        document.getElementById('scheduleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

            fetch('save_schedule.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-save" style="margin-right: 8px;"></i> Simpan Jadwal & Generate Link';
                
                if (data.success) {
                    document.getElementById('scheduleModal').classList.remove('active');
                    this.reset();
                    $('#participantSelect').val(null).trigger('change');
                    
                    Swal.fire({
                        title: 'Jadwal Berhasil Dibuat!',
                        html: `
                            <p style="margin-bottom:15px; color: #64748b;">Meeting <strong>${data.title}</strong> berhasil disimpan.</p>
                            <div style="margin-bottom: 20px; display: flex; flex-direction: column; align-items: center;">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(data.link)}" style="border: 4px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-radius: 12px; margin-bottom: 15px; width: 200px; height: 200px;">
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:8px; word-break:break-all; font-family:monospace; font-size: 0.75rem; color: #475569; width: 100%;">
                                    ${data.link}
                                </div>
                            </div>
                            <button class="btn-submit" onclick="copyLinkModal('${data.link}')" style="width:100%;">
                                <i class="fa-solid fa-copy" style="margin-right: 8px;"></i> Salin Link Absensi
                            </button>
                        `,
                        icon: 'success',
                        confirmButtonText: 'Tutup & Segarkan',
                        confirmButtonColor: '#3f51b5'
                    }).then(() => location.reload());
                } else {
                    Toast.fire({ icon: 'error', title: data.message });
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-save" style="margin-right: 8px;"></i> Simpan Jadwal & Generate Link';
                Toast.fire({ icon: 'error', title: 'Terjadi kesalahan sistem.' });
            });
        });

        function copyLinkModal(text) {
            navigator.clipboard.writeText(text).then(() => {
                Toast.fire({
                    icon: 'success',
                    title: 'Link Berhasil Disalin!',
                    text: 'Link absensi siap dibagikan.'
                });
            });
        }

        function showQRModal(title, link) {
            Swal.fire({
                title: 'QR Code Absensi',
                html: `
                    <p style="margin-bottom:15px;">Meeting: <strong>${title}</strong></p>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(link)}" style="margin-bottom:15px; border:4px solid white; box-shadow: var(--shadow-md); border-radius:8px;">
                    <p style="font-size:0.8rem; color:#64748b; word-break:break-all;">${link}</p>
                `,
                confirmButtonText: 'Tutup',
                confirmButtonColor: '#3f51b5'
            });
        }

        function confirmDeleteMeeting(id, title) {
            Swal.fire({
                title: 'Hapus Meeting?',
                html: `Meeting <strong>${title}</strong> akan dihapus permanen.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: '<i class="fa-solid fa-trash"></i> Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('delete_meeting.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=' + id
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Toast.fire({ icon: 'success', title: 'Meeting berhasil dihapus' });
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            Toast.fire({ icon: 'error', title: data.message || 'Gagal menghapus meeting' });
                        }
                    })
                    .catch(() => Toast.fire({ icon: 'error', title: 'Terjadi kesalahan' }));
                }
            });
        }

        function toggleEditCoffeeOptions(show) {
            const container = document.getElementById('editCoffeeOptionsContainer');
            if (show) {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }

        function openScheduleModal() {
            document.getElementById('scheduleModal').classList.add('active');
        }

        function openEditModal(id) {
            fetch('get_meeting.php?id=' + id)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const data = res.data;
                        document.getElementById('editMeetingId').value = data.id;
                        document.getElementById('editMeetingTitle').value = data.title;
                        document.getElementById('editMeetingRoom').value = data.room;
                        
                        if (data.room.toLowerCase() === 'online') {
                            document.getElementById('editConsumptionPanel').style.display = 'none';
                        } else {
                            document.getElementById('editConsumptionPanel').style.display = 'block';
                        }

                        document.getElementById('editLateTolerance').value = data.late_tolerance;
                        document.getElementById('editMeetingDate').value = data.date;
                        document.getElementById('editMeetingTime').value = data.time;
                        document.getElementById('editMeetingEndTime').value = data.end_time_formatted;

                        $('#editParticipantSelect').val(data.participants).trigger('change');
                        updatePicOptions('editParticipantSelect', 'editPicSelect', data.pic_id);

                        document.getElementById('editHasSnack').checked = (data.has_snack == 1);
                        document.getElementById('editHasCoffeeCheckbox').checked = (data.has_coffee == 1);
                        document.getElementById('editHybridZoom').checked = (data.is_hybrid_zoom == 1);

                        document.getElementById('editCoffeeTemp').value = data.coffee_temp || 'panas';
                        document.getElementById('editCoffeeType').value = data.coffee_type || 'bikin';

                        toggleEditCoffeeOptions(data.has_coffee == 1);
                        document.getElementById('editMeetingModal').classList.add('active');
                    } else {
                        Toast.fire({ icon: 'error', title: res.message || 'Gagal mengambil data' });
                    }
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: 'Terjadi kesalahan' });
                });
        }

        document.getElementById('editScheduleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('editSubmitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

            fetch('update_meeting.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({ icon: 'success', title: 'Jadwal berhasil diperbarui!' });
                    document.getElementById('editMeetingModal').classList.remove('active');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Toast.fire({ icon: 'error', title: data.message || 'Gagal memperbarui jadwal' });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-save" style="margin-right: 8px;"></i> Simpan Perubahan';
                }
            })
            .catch(() => {
                Toast.fire({ icon: 'error', title: 'Terjadi kesalahan sistem' });
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-save" style="margin-right: 8px;"></i> Simpan Perubahan';
            });
        });

        function addParticipantsByGroup(selectId, groupName) {
            var $select = $('#' + selectId);
            var currentValues = $select.val() || [];
            if (!Array.isArray(currentValues)) currentValues = [currentValues];
            
            var addedCount = 0;
            $select.find('option').each(function() {
                if ($(this).data('group') === groupName) {
                    var val = $(this).val();
                    if (val && currentValues.indexOf(val) === -1) {
                        currentValues.push(val);
                        addedCount++;
                    }
                }
            });
            
            $select.val(currentValues).trigger('change');
            if (addedCount > 0) {
                Toast.fire({
                    icon: 'info',
                    title: addedCount + ' peserta grup ' + groupName + ' ditambahkan'
                });
            } else {
                Toast.fire({
                    icon: 'info',
                    title: 'Semua peserta grup ' + groupName + ' sudah ada dalam daftar'
                });
            }
        }

        function clearParticipants(selectId) {
            $('#' + selectId).val([]).trigger('change');
            Toast.fire({
                icon: 'info',
                title: 'Daftar peserta telah dikosongkan'
            });
        }
    </script>
</body>
</html>
