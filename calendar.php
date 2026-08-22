<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch events
$events = [];
$current_branch = getCurrentBranchId();
$where_branch = $current_branch > 0 ? "WHERE branch_id = $current_branch" : "WHERE 1=1";
$and_branch = $current_branch > 0 ? "AND branch_id = $current_branch" : "";

if (in_array($_SESSION['role'], ['superadmin', 'admin']) || !empty($_SESSION['can_dashboard'])) {
    $stmt = $pdo->query("SELECT * FROM meetings $where_branch ORDER BY scheduled_time ASC");
    $meetings = $stmt->fetchAll();
} else {
    // Users see ALL approved meetings (to know room availability) OR their own meetings (any status)
    $stmt = $pdo->prepare("
        SELECT * FROM meetings 
        WHERE (status = 'approved' OR created_by = ?)
        $and_branch
        ORDER BY scheduled_time ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $meetings = $stmt->fetchAll();
}

$now = time();
foreach ($meetings as $m) {
    // Handle time properly for ISO8601 (FullCalendar requires it)
    $start_ts = strtotime($m['scheduled_time']);
    $end_ts = strtotime($m['end_time']);
    
    // Convert to ISO 8601 e.g. "2023-10-01T14:30:00"
    $start_iso = date('Y-m-d\TH:i:s', $start_ts);
    $end_iso = date('Y-m-d\TH:i:s', $end_ts);

    if ($now > $end_ts) {
        $color = '#94a3b8'; // gray
        $status_text = 'Berakhir';
    } elseif ($now >= $start_ts && $now <= $end_ts) {
        $color = '#22c55e'; // green
        $status_text = 'Sedang Berlangsung';
    } else {
        $color = '#3b82f6'; // blue
        $status_text = 'Akan Datang';
    }

    $events[] = [
        'title' => $m['title'],
        'start' => $start_iso,
        'end' => $end_iso,
        'color' => $color,
        'extendedProps' => [
            'room' => $m['room'],
            'meeting_id' => $m['id'],
            'status_text' => $status_text
        ]
    ];
}
$events_json = json_encode($events);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalender Meeting - Indoarsip</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- FullCalendar CSS and JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        .select2-container--default .select2-selection--multiple {
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            min-height: 42px !important;
            padding: 4px !important;
        }
        /* Calendar Theme Tweaks to match Inter font */
        #calendar {
            font-family: 'Inter', sans-serif;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        .fc-theme-standard td, .fc-theme-standard th {
            border-color: #f1f5f9;
        }
        .fc .fc-toolbar-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }
        .fc-button-primary {
            background-color: #4f46e5 !important;
            border-color: #4f46e5 !important;
            border-radius: 8px !important;
            font-weight: 500 !important;
            text-transform: capitalize !important;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .fc-button-primary:hover {
            background-color: #4338ca !important;
            border-color: #4338ca !important;
        }
        .fc-button-primary:disabled {
            background-color: #94a3b8 !important;
            border-color: #94a3b8 !important;
        }
        .fc-day-today {
            background-color: #f8fafc !important;
        }
        .fc-event {
            border: none;
            border-radius: 6px;
            padding: 2px 4px;
            font-size: 0.85rem;
            font-weight: 500;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: transform 0.1s ease, box-shadow 0.1s ease;
        }
        .fc-event:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .fc .fc-daygrid-day-events {
            max-height: 110px;
            overflow-y: auto;
        }
        .fc .fc-daygrid-day-events::-webkit-scrollbar {
            width: 4px;
        }
        .fc .fc-daygrid-day-events::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <div class="main-wrapper">
            <!-- Topbar -->
            <?php include 'topbar.php'; ?>

            <main class="content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; flex-wrap:wrap; gap:15px;">
                    <div>
                        <h1 class="page-title">Kalender Jadwal Meeting</h1>
                        <p class="page-subtitle" style="margin-bottom:0;">Pantau seluruh agenda meeting Anda secara visual</p>
                    </div>
                    <button type="button" class="btn-submit" onclick="openScheduleModal()" style="padding: 10px 18px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-plus"></i> Ajukan Meeting
                    </button>
                </div>
                
                <!-- Keterangan Warna -->
                <div style="display:flex; gap:15px; margin-bottom: 20px; font-size: 0.85rem; flex-wrap: wrap;">
                    <div style="display:flex; align-items:center;"><span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#3b82f6; margin-right:6px;"></span> Akan Datang</div>
                    <div style="display:flex; align-items:center;"><span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#22c55e; margin-right:6px;"></span> Sedang Berlangsung</div>
                    <div style="display:flex; align-items:center;"><span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#94a3b8; margin-right:6px;"></span> Berakhir</div>
                </div>

                <div id='calendar'></div>
            </main>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var eventsData = <?= $events_json ?>;

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Hari Ini',
                month: 'Bulan',
                week: 'Minggu',
                day: 'Hari'
            },
            firstDay: 1, // Senin
            dayMaxEvents: false, // Jangan sembunyikan event, gunakan scroll internal
            events: eventsData,
            eventContent: function(arg) {
                // Limit title length to prevent "offside" overflow
                let title = arg.event.title;
                if (title.length > 20) {
                    title = title.substring(0, 18) + '...';
                }
                
                // Ensure we use the correct color from event properties
                let dotColor = arg.event.backgroundColor || arg.event.color || '#3b82f6';
                
                return {
                    html: `<div style="padding: 1px 4px; font-size: 0.8rem; display: flex; align-items: center; gap: 6px; font-weight: 600; color: #1e293b; width: 100%; overflow: hidden;">
                             <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:${dotColor}; flex-shrink:0; box-shadow: 0 0 0 1px rgba(0,0,0,0.05);"></span>
                             <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex-grow: 1;">${title}</span>
                           </div>`
                };
            },
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                
                var p = info.event.extendedProps;
                
                // Format dates safely
                var startObj = info.event.start;
                var endObj = info.event.end || startObj;
                
                var dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                var timeOptions = { hour: '2-digit', minute: '2-digit' };
                
                var dateStr = startObj.toLocaleDateString('id-ID', dateOptions);
                var timeStr = startObj.toLocaleTimeString('id-ID', timeOptions) + ' - ' + endObj.toLocaleTimeString('id-ID', timeOptions);
                
                Swal.fire({
                    title: info.event.title,
                    html: `<div style="text-align:left; background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:15px; font-size:0.95rem;">
                             <p style="margin:0 0 10px 0;"><i class="fa-solid fa-door-open" style="color:#3b82f6; width:20px;"></i> <strong>Ruangan:</strong> ${p.room}</p>
                             <p style="margin:0 0 10px 0;"><i class="fa-solid fa-calendar-day" style="color:#3b82f6; width:20px;"></i> <strong>Tanggal:</strong> ${dateStr}</p>
                             <p style="margin:0 0 10px 0;"><i class="fa-solid fa-clock" style="color:#3b82f6; width:20px;"></i> <strong>Waktu:</strong> ${timeStr}</p>
                             <p style="margin:0;"><i class="fa-solid fa-info-circle" style="color:#3b82f6; width:20px;"></i> <strong>Status:</strong> <span style="font-weight:600; color:${info.event.backgroundColor}">${p.status_text}</span></p>
                           </div>`,
                    showConfirmButton: true,
                    confirmButtonText: 'Tutup',
                    confirmButtonColor: '#3f51b5'
                });
            },
            dateClick: function(info) {
                openScheduleModal(info.dateStr);
            }
        });
        calendar.render();
    });
    </script>
    <?php include 'modal_schedule.php'; ?>
</body>
</html>
