<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_branch = getCurrentBranchId();
$branch_condition = $current_branch > 0 ? "AND branch_id = $current_branch" : "";

// Fetch branch name
$stmt_branch = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$stmt_branch->execute([$current_branch]);
$branch_name = $stmt_branch->fetchColumn() ?: 'Semua Cabang';

// Fetch all rooms
$stmt_rooms = $pdo->prepare("SELECT * FROM rooms WHERE 1=1 $branch_condition ORDER BY name ASC");
$stmt_rooms->execute();
$rooms = $stmt_rooms->fetchAll();

// Fetch today's meetings for all rooms in this branch
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');

$branch_condition_meetings = $current_branch > 0 ? "AND m.branch_id = $current_branch" : "";

$stmt_meetings = $pdo->prepare("
    SELECT m.*, u.name as pic_name 
    FROM meetings m 
    LEFT JOIN users u ON m.pic_id = u.id
    WHERE m.status = 'approved'
    AND m.scheduled_time >= ? 
    AND m.scheduled_time <= ?
    $branch_condition_meetings
    ORDER BY m.scheduled_time ASC
");
$stmt_meetings->execute([$today_start, $today_end]);
$today_meetings = $stmt_meetings->fetchAll();

// Group meetings by room name
$room_meetings = [];
foreach ($rooms as $r) {
    $room_meetings[$r['name']] = [];
}
foreach ($today_meetings as $m) {
    $room_meetings[$m['room']][] = $m;
}

// Prepare rooms data for JSON
$rooms_data = [];
$now_time = time();

foreach ($rooms as $r) {
    $r_name = $r['name'];
    $r_id = $r['id'];
    // Generate stable capacity based on room ID
    $capacity = (($r_id % 3) * 4) + 6; // returns 6, 10, or 14
    
    // Check if currently booked
    $is_booked = false;
    $current_meeting = null;
    
    foreach ($room_meetings[$r_name] as $m) {
        $start_ts = strtotime($m['scheduled_time']);
        $end_ts = strtotime($m['end_time']);
        if ($now_time >= $start_ts && $now_time <= $end_ts) {
            $is_booked = true;
            $current_meeting = $m;
            break;
        }
    }
    
    // Generate today's timeline slots (combining bookings and free time)
    // Business hours: 08:00 to 18:00
    $timeline = [];
    $current_cursor = strtotime(date('Y-m-d 08:00:00'));
    $day_end_ts = strtotime(date('Y-m-d 18:00:00'));
    
    // Sort meetings for the room
    $meetings_sorted = $room_meetings[$r_name];
    
    foreach ($meetings_sorted as $m) {
        $m_start = strtotime($m['scheduled_time']);
        $m_end = strtotime($m['end_time']);
        
        // If there's free time before this meeting starts
        if ($m_start > $current_cursor) {
            $free_duration = round(($m_start - $current_cursor) / 3600, 1);
            $timeline[] = [
                'type' => 'available',
                'start' => date('H:i', $current_cursor),
                'end' => date('H:i', $m_start),
                'label' => "Available for " . $free_duration . " hour" . ($free_duration > 1 ? 's' : '')
            ];
        }
        
        // Add the meeting booking slot
        $timeline[] = [
            'type' => 'booked',
            'start' => date('H:i', $m_start),
            'end' => date('H:i', $m_end),
            'label' => "Booked by " . htmlspecialchars($m['pic_name']) . " (" . htmlspecialchars($m['title']) . ")"
        ];
        
        $current_cursor = $m_end;
    }
    
    // If there's free time left at the end of the day
    if ($current_cursor < $day_end_ts) {
        $free_duration = round(($day_end_ts - $current_cursor) / 3600, 1);
        $timeline[] = [
            'type' => 'available',
            'start' => date('H:i', $current_cursor),
            'end' => date('H:i', $day_end_ts),
            'label' => "Available for " . $free_duration . " hour" . ($free_duration > 1 ? 's' : '')
        ];
    }
    
    $rooms_data[] = [
        'id' => $r_id,
        'name' => $r_name,
        'capacity' => $capacity,
        'is_booked' => $is_booked,
        'current_pic' => $current_meeting ? $current_meeting['pic_name'] : null,
        'current_title' => $current_meeting ? $current_meeting['title'] : null,
        'timeline' => $timeline
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV Dashboard - Indoarsip Meeting</title>
    <link rel="icon" type="image/png" href="logo_login.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-dark: #1e293b;
            --accent-green: #10b981;
            --accent-red: #ef4444;
            --text-light: #f8fafc;
            --text-gray: #94a3b8;
            --border-color: #334155;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 24px;
        }

        /* Header Style */
        .tv-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .tv-logo {
            height: 36px;
        }

        .tv-title-badge {
            background-color: #3b82f6;
            color: white;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .clock-container {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-light);
            background: rgba(255, 255, 255, 0.05);
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .btn-exit {
            background-color: var(--card-dark);
            color: var(--text-light);
            border: 1px solid var(--border-color);
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-exit:hover {
            background-color: #334155;
        }

        /* Layout Grid */
        .tv-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 24px;
            flex: 1;
            min-height: 0; /* Important for inner scroll */
        }

        /* Panel Common */
        .tv-panel {
            background-color: var(--card-dark);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 24px;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        /* Left Side: Room Details */
        .room-detail-header {
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            color: white;
            transition: background 0.3s;
        }

        .room-detail-header.available {
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.15);
        }

        .room-detail-header.booked {
            background: linear-gradient(135deg, #be123c 0%, #9f1239 100%);
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.15);
        }

        .room-title {
            font-size: 2.2rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 8px;
        }

        .room-capacity {
            font-size: 0.95rem;
            opacity: 0.85;
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.15);
            padding: 4px 10px;
            border-radius: 20px;
            width: fit-content;
        }

        .room-status-text {
            font-size: 1.2rem;
            font-weight: 700;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Timeline list */
        .timeline-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .timeline-list {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding-right: 6px;
        }

        .timeline-item {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
        }

        .timeline-item.available {
            border: 2px solid #059669;
            box-shadow: inset 0 0 10px rgba(16, 185, 129, 0.05);
        }

        .timeline-item.booked {
            border: 2px solid #e11d48;
            box-shadow: inset 0 0 10px rgba(239, 68, 68, 0.05);
        }

        .timeline-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .timeline-time {
            font-size: 1.2rem;
            font-weight: 800;
        }

        .timeline-item.available .timeline-time { color: var(--accent-green); }
        .timeline-item.booked .timeline-time { color: var(--accent-red); }

        .timeline-label {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-light);
        }

        .timeline-badge {
            font-size: 0.8rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .timeline-item.available .timeline-badge {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--accent-green);
        }

        .timeline-item.booked .timeline-badge {
            background-color: rgba(239, 68, 68, 0.15);
            color: var(--accent-red);
        }

        /* Right Side: Room list */
        .right-panel-header {
            margin-bottom: 20px;
        }

        .right-panel-title {
            font-size: 1.4rem;
            font-weight: 800;
        }

        .right-panel-subtitle {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-top: 4px;
        }

        .room-list {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-right: 6px;
        }

        .room-item {
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.2s;
        }

        .room-item:hover {
            background-color: rgba(255, 255, 255, 0.07);
            border-color: #475569;
        }

        .room-item.active {
            background-color: rgba(59, 130, 246, 0.1);
            border-color: #3b82f6;
        }

        .room-item-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .room-status-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            box-shadow: 0 0 10px currentColor;
        }

        .room-status-dot.available {
            background-color: var(--accent-green);
            color: var(--accent-green);
        }

        .room-status-dot.booked {
            background-color: var(--accent-red);
            color: var(--accent-red);
        }

        .room-item-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-light);
        }

        .room-item-right {
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--text-gray);
        }

        .room-item-cap {
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Scrollbar styles */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <header class="tv-header">
        <div class="header-left">
            <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--text-light);"><?= htmlspecialchars($branch_name) ?></h2>
        </div>
        <div class="header-right">
            <div class="clock-container" id="tv-clock"></div>
            <a href="index.php" class="btn-exit">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Keluar</span>
            </a>
        </div>
    </header>

    <!-- Main Grid -->
    <div class="tv-grid">
        <!-- Left Panel: Detail Room -->
        <div class="tv-panel" id="detail-panel">
            <!-- Header (Dynamic) -->
            <div class="room-detail-header" id="detail-header">
                <div class="room-title">
                    <span id="detail-room-name">Nama Ruangan</span>
                    <span class="room-capacity" id="detail-capacity">
                        <i class="fa-solid fa-users"></i> Max <span id="detail-cap-val">0</span>
                    </span>
                </div>
                <div class="room-status-text" id="detail-status-text">
                    <i class="fa-solid fa-circle-check"></i> Available Now
                </div>
            </div>

            <!-- Timeline -->
            <div class="timeline-section-title">Schedule Today</div>
            <div class="timeline-list" id="detail-timeline-list">
                <!-- Timeline items loaded dynamically -->
            </div>
        </div>

        <!-- Right Panel: Room List -->
        <div class="tv-panel">
            <div class="right-panel-header">
                <div class="right-panel-title">Ruang Rapat</div>
                <div class="right-panel-subtitle">Pilih ruangan untuk melihat detail jadwal hari ini</div>
            </div>
            
            <div class="room-list" id="tv-room-list">
                <!-- Room list items loaded dynamically -->
            </div>
        </div>
    </div>

    <script>
        // Rooms data loaded from PHP
        const roomsData = <?= json_encode($rooms_data) ?>;
        let activeRoomId = null;

        // Initialize TV View
        function initTV() {
            if (roomsData.length > 0) {
                renderRoomList();
                // Select first room by default
                selectRoom(roomsData[0].id);
            } else {
                document.getElementById('detail-panel').innerHTML = `
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; color:var(--text-gray);">
                        <i class="fa-solid fa-door-closed" style="font-size:3rem; margin-bottom:15px;"></i>
                        <p>Tidak ada ruangan terdaftar di cabang ini.</p>
                    </div>
                `;
                document.getElementById('tv-room-list').innerHTML = `
                    <div style="text-align:center; padding:40px; color:var(--text-gray);">Kosong</div>
                `;
            }
        }

        // Render Room List on the Right Panel
        function renderRoomList() {
            const listContainer = document.getElementById('tv-room-list');
            listContainer.innerHTML = '';

            roomsData.forEach(r => {
                const item = document.createElement('div');
                item.className = `room-item ${r.id === activeRoomId ? 'active' : ''}`;
                item.setAttribute('onclick', `selectRoom(${r.id})`);
                
                const statusDotClass = r.is_booked ? 'booked' : 'available';
                
                item.innerHTML = `
                    <div class="room-item-left">
                        <div class="room-status-dot ${statusDotClass}"></div>
                        <div class="room-item-name">${r.name}</div>
                    </div>
                    <div class="room-item-right">
                        <div class="room-item-cap">
                            <i class="fa-solid fa-users"></i> ${r.capacity}
                        </div>
                        <i class="fa-solid fa-chevron-right" style="font-size:0.8rem;"></i>
                    </div>
                `;
                listContainer.appendChild(item);
            });
        }

        // Select Room and Update Left Panel
        function selectRoom(roomId) {
            activeRoomId = roomId;
            
            // Re-render list to update active class
            renderRoomList();

            // Find room data
            const room = roomsData.find(r => r.id === roomId);
            if (!room) return;

            // Update Header Name and Capacity
            document.getElementById('detail-room-name').textContent = room.name;
            document.getElementById('detail-cap-val').textContent = room.capacity;

            // Update Status Header Colors & Text
            const header = document.getElementById('detail-header');
            const statusText = document.getElementById('detail-status-text');

            if (room.is_booked) {
                header.className = 'room-detail-header booked';
                statusText.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> Booked: ${room.current_title} (by ${room.current_pic})`;
            } else {
                header.className = 'room-detail-header available';
                statusText.innerHTML = `<i class="fa-solid fa-circle-check"></i> Available Now`;
            }

            // Update Timeline List
            const timelineContainer = document.getElementById('detail-timeline-list');
            timelineContainer.innerHTML = '';

            if (room.timeline.length === 0) {
                timelineContainer.innerHTML = `
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; color:var(--text-gray); padding: 40px 0;">
                        <i class="fa-solid fa-calendar-day" style="font-size:2.5rem; margin-bottom:12px;"></i>
                        <p>Tidak ada jadwal meeting hari ini.</p>
                    </div>
                `;
            } else {
                room.timeline.forEach(slot => {
                    const item = document.createElement('div');
                    item.className = `timeline-item ${slot.type}`;
                    
                    item.innerHTML = `
                        <div class="timeline-left">
                            <div class="timeline-time">${slot.start} — ${slot.end}</div>
                            <div class="timeline-label">${slot.label}</div>
                        </div>
                        <div class="timeline-badge">${slot.type}</div>
                    `;
                    timelineContainer.appendChild(item);
                });
            }
        }

        // Live Clock
        function updateClock() {
            const now = new Date();
            const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            const day = String(now.getDate()).padStart(2, '0');
            const month = months[now.getMonth()];
            const year = now.getFullYear();
            const time = now.toLocaleTimeString('id-ID', { hour12: false });
            
            document.getElementById('tv-clock').textContent = `${day} ${month} ${year} ${time}`;
        }

        // Init Clock and Dashboard
        updateClock();
        setInterval(updateClock, 1000);
        
        initTV();

        // Auto Refresh page every 30 seconds to fetch fresh schedules
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
