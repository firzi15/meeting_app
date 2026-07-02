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

if ($_SESSION['role'] === 'admin' || (isset($_SESSION['can_dashboard']) && $_SESSION['can_dashboard'])) {
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
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Calendar Theme Tweaks to match Inter font */
        #calendar {
            font-family: 'Inter', sans-serif;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        .fc .fc-toolbar-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }
        .fc .fc-button-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .fc .fc-button-primary:not(:disabled):active, .fc .fc-button-primary:not(:disabled).fc-button-active {
            background-color: #303f9f;
            border-color: #303f9f;
        }
        .fc .fc-button-primary:hover {
            background-color: #4f63d6;
            border-color: #4f63d6;
        }
        .fc-event {
            cursor: pointer;
            border: none;
            border-radius: 4px;
        }
        /* Fixed height for day cells and scrollable events */
        .fc .fc-daygrid-day-frame {
            height: 120px !important;
            overflow: hidden;
        }
        .fc .fc-daygrid-day-events {
            max-height: 90px;
            overflow-y: auto;
            scrollbar-width: thin;
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
                <div style="margin-bottom: 20px;">
                    <h1 class="page-title">Kalender Jadwal Meeting</h1>
                    <p class="page-subtitle">Pantau seluruh agenda meeting Anda secara visual</p>
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
            }
        });
        calendar.render();
    });
    </script>
</body>
</html>
