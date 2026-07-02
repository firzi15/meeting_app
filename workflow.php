<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Workflow - Vertikal Mode Cerah</title>
    
    <!-- Drawflow -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow/dist/drawflow.min.css">
    <script src="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow/dist/drawflow.min.js"></script>
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --dfBackgroundColor: #f8fafc;
            --dfBackgroundSize: 20px;
            --dfBackgroundImage: linear-gradient(to right, #e2e8f0 1px, transparent 1px), linear-gradient(to bottom, #e2e8f0 1px, transparent 1px);
        }

        html, body {
            margin: 0; padding: 0; width: 100vw; height: 100vh;
            overflow: hidden; font-family: 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
        }

        #drawflow {
            position: relative; width: 100%; height: 100%;
            background-color: var(--dfBackgroundColor);
            background-size: var(--dfBackgroundSize) var(--dfBackgroundSize);
            background-image: var(--dfBackgroundImage);
        }
        
        .editor-header {
            position: absolute; top: 0; left: 0; width: 100%; height: 55px;
            background: #ffffff; border-bottom: 1px solid #cbd5e1;
            display: flex; align-items: center; padding: 0 20px;
            box-sizing: border-box; z-index: 10;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        
        .editor-header h3 { margin: 0; font-size: 16px; color: #1e293b; font-weight: 600; }
        .back-btn {
            background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569;
            padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 14px; margin-right: 15px;
            display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;
        }
        .back-btn:hover { background: #e2e8f0; color: #0f172a; }

        /* Custom Node Design (Mode Cerah) */
        .drawflow .drawflow-node {
            display: flex; flex-direction: column;
            border: 1px solid #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 0; border-radius: 8px; background: #ffffff;
            width: 280px; color: #334155;
            transition: all 0.2s ease;
        }
        
        .drawflow .drawflow-node:hover { border-color: #94a3b8; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .drawflow .drawflow-node.selected { border: 2px solid #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2); }

        .node-header {
            display: flex; align-items: center; padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9; border-radius: 8px 8px 0 0; background: #ffffff;
        }
        
        .node-icon {
            width: 32px; height: 32px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            margin-right: 12px; color: white; font-size: 15px;
        }

        .node-title { font-weight: 600; font-size: 14px; color: #1e293b; flex-grow: 1; }

        .node-content {
            padding: 15px; font-size: 12px; color: #475569; line-height: 1.5;
            border-radius: 0 0 8px 8px; position: relative;
        }
        
        /* HOVER PAGES TOOLTIP LOGIC */
        .page-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: #f8fafc;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s;
            z-index: 100;
            pointer-events: none;
            margin-bottom: 5px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .page-tooltip::after {
            content: ''; position: absolute; top: 100%; left: 50%;
            margin-left: -5px; border-width: 5px; border-style: solid;
            border-color: #1e293b transparent transparent transparent;
        }
        
        .drawflow-node:hover .page-tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        .condition-badge {
            display: inline-block; background: #f1f5f9; border: 1px solid #cbd5e1;
            color: #475569; font-family: monospace; font-size: 10px;
            padding: 2px 6px; border-radius: 4px; margin-bottom: 4px; margin-top: 4px; font-weight: 600;
        }

        /* Input/Output Ports */
        .drawflow .drawflow-node .input, .drawflow .drawflow-node .output {
            width: 14px; height: 14px; background: #ffffff;
            border: 2px solid #94a3b8; border-radius: 50%;
        }
        .drawflow .drawflow-node .input:hover, .drawflow .drawflow-node .output:hover {
            background: #3b82f6; border-color: #3b82f6;
        }
        
        /* Connections */
        .drawflow .connection .main-path { stroke: #94a3b8; stroke-width: 3px; }
        .drawflow .connection .main-path:hover { stroke: #3b82f6; }

        .zoom-controls {
            position: absolute; bottom: 20px; right: 20px; display: flex;
            background: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #cbd5e1; z-index: 10;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .zoom-btn {
            background: none; border: none; padding: 10px 15px; cursor: pointer; color: #475569;
            border-right: 1px solid #cbd5e1;
        }
        .zoom-btn:last-child { border-right: none; }
        .zoom-btn:hover { background: #f1f5f9; color: #0f172a; }
        
        .output-label {
            position: absolute; right: -80px; background: #ffffff; color: #475569;
            font-size: 10px; padding: 2px 6px; border-radius: 4px; white-space: nowrap;
            border: 1px solid #cbd5e1; z-index: 100; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <div class="editor-header">
        <a href="index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
        <h3><i class="fa-solid fa-diagram-project" style="color: #3b82f6; margin-right: 8px;"></i> Alur Sistem (Vertical & Light)</h3>
    </div>

    <div id="drawflow">
        <div class="zoom-controls">
            <button class="zoom-btn" onclick="editor.zoom_in()"><i class="fa-solid fa-plus"></i></button>
            <button class="zoom-btn" onclick="editor.zoom_reset()"><i class="fa-solid fa-expand"></i></button>
            <button class="zoom-btn" onclick="editor.zoom_out()"><i class="fa-solid fa-minus"></i></button>
        </div>
    </div>

    <script>
        var id = document.getElementById("drawflow");
        const editor = new Drawflow(id);
        editor.reroute = true;
        editor.reroute_fix_curvature = true;
        editor.start();

        function createNodeHTML(data) {
            let outputLabelsHTML = '';
            if (data.outputLabels && data.outputLabels.length > 0) {
                data.outputLabels.forEach((lbl, idx) => {
                    let top = 40 + (idx * 20); 
                    outputLabelsHTML += `<div class="output-label" style="top: ${top}px;">${lbl}</div>`;
                });
            }
            
            // Render tooltip containing pages
            let pagesHTML = data.pages ? `<div class="page-tooltip"><i class="fa-solid fa-file-code"></i> ${data.pages}</div>` : '';

            return `
            <div style="position:relative;">
                ${outputLabelsHTML}
                <div class="node-header">
                    ${pagesHTML}
                    <div class="node-icon" style="background-color: ${data.color};">
                        <i class="fa-solid ${data.icon}"></i>
                    </div>
                    <div class="node-title">${data.title}</div>
                </div>
                <div class="node-content">
                    ${data.content}
                </div>
            </div>
            `;
        }

        const nodesData = {
            login: {
                title: "Authentication", icon: "fa-right-to-bracket", color: "#8b5cf6",
                content: "<span class='condition-badge'>IF Valid</span> Session dibuat.<br/><span class='condition-badge'>IF Invalid</span> Alert Gagal.",
                pages: "login.php, auth.php", inputs: 0, outputs: 2, outputLabels: ["Valid", "Invalid"]
            },
            role_check: {
                title: "Role Checker", icon: "fa-user-shield", color: "#f97316",
                content: "<span class='condition-badge'>IF Role == Admin/HR</span> Full Access<br/><span class='condition-badge'>IF Role == User</span> Limited",
                pages: "topbar.php, sidebar.php", inputs: 1, outputs: 2, outputLabels: ["Admin/HR", "User Biasa"]
            },
            dashboard_admin: {
                title: "Dashboard Admin/HR", icon: "fa-gauge-high", color: "#3b82f6",
                content: "Melihat SEMUA meeting & Master Data.",
                pages: "index.php", inputs: 1, outputs: 1
            },
            dashboard_user: {
                title: "Dashboard User Biasa", icon: "fa-gauge", color: "#0ea5e9",
                content: "Melihat meeting sendiri.",
                pages: "index.php", inputs: 1, outputs: 1
            },
            create_form: {
                title: "Form Booking Meeting", icon: "fa-pen-to-square", color: "#10b981",
                content: "Mengisi Data: Judul, Waktu, Ruangan.",
                pages: "index.php, save_schedule.php", inputs: 2, outputs: 2, outputLabels: ["Offline", "Online"]
            },
            if_offline: {
                title: "Ruangan Fisik (Offline)", icon: "fa-building", color: "#f59e0b",
                content: "<span class='condition-badge'>IF != Online</span><br/>Tampilkan panel konsumsi (Kopi/Snack).",
                pages: "index.php", inputs: 1, outputs: 1
            },
            if_online: {
                title: "Ruangan Online", icon: "fa-globe", color: "#f59e0b",
                content: "<span class='condition-badge'>IF == Online</span><br/>Sembunyikan panel konsumsi.",
                pages: "index.php", inputs: 1, outputs: 1
            },
            conflict_check: {
                title: "Cek Bentrok Jadwal", icon: "fa-calendar-xmark", color: "#ef4444",
                content: "<span class='condition-badge'>IF Conflict</span> Alert & Batal<br/><span class='condition-badge'>IF Aman</span> Insert DB (Pending)",
                pages: "save_schedule.php", inputs: 2, outputs: 2, outputLabels: ["Ada Bentrok", "Jadwal Aman"]
            },
            approval_process: {
                title: "Approval HR", icon: "fa-clipboard-check", color: "#f97316",
                content: "<span class='condition-badge'>IF Approved</span> Status -> approved<br/><span class='condition-badge'>IF Rejected</span> Status -> rejected",
                pages: "approval.php", inputs: 1, outputs: 2, outputLabels: ["Approved", "Rejected"]
            },
            tv_display: {
                title: "TV Display Ruangan", icon: "fa-tv", color: "#8b5cf6",
                content: "Tampil di TV depan ruangan saat meeting aktif.",
                pages: "tv.php", inputs: 1, outputs: 0
            },
            qr_generate: {
                title: "QR Code Akses", icon: "fa-qrcode", color: "#ec4899",
                content: "Generate URL barcode absensi.",
                pages: "index.php, report.php", inputs: 1, outputs: 1
            },
            scan_process: {
                title: "Scan & Validasi Waktu", icon: "fa-mobile-screen", color: "#64748b",
                content: "<span class='condition-badge'>IF Jam < Mulai</span> Belum Mulai<br/><span class='condition-badge'>IF Sesuai Jam</span> Lanjut",
                pages: "attendance.php", inputs: 1, outputs: 2, outputLabels: ["Token/Jam Salah", "Sesuai"]
            },
            cek_hadir: {
                title: "Cek Data Absen DB", icon: "fa-database", color: "#f97316",
                content: "<span class='condition-badge'>IF User sdh ada</span> -> 'Sudah Absen'<br/><span class='condition-badge'>IF Belum</span> -> Cek Keterlambatan",
                pages: "process_attendance.php", inputs: 1, outputs: 2, outputLabels: ["Sdh Absen", "Belum"]
            },
            cek_telat: {
                title: "Validasi Keterlambatan", icon: "fa-stopwatch", color: "#ef4444",
                content: "<span class='condition-badge'>IF Telat > 15m</span> -> Isi Feedback<br/><span class='condition-badge'>IF Tepat</span> -> Sukses",
                pages: "process_attendance.php", inputs: 1, outputs: 2, outputLabels: ["Terlambat", "Tepat Waktu"]
            },
            form_feedback: {
                title: "Form Feedback (Late)", icon: "fa-comment-dots", color: "#f43f5e",
                content: "Peserta wajib mengisi Alasan Terlambat.",
                pages: "feedback.php, submit_feedback.php", inputs: 1, outputs: 1
            },
            insert_absen: {
                title: "Insert Kehadiran", icon: "fa-check", color: "#10b981",
                content: "Menyimpan kehadiran ke DB.",
                pages: "process_attendance.php", inputs: 2, outputs: 1
            },
            laporan: {
                title: "Laporan & Export", icon: "fa-file-excel", color: "#14b8a6",
                content: "<span class='condition-badge'>IF Filter Ruangan</span> Filter<br/><span class='condition-badge'>IF For HR</span> Tampil Alasan Telat.",
                pages: "report.php, export_excel.php", inputs: 1, outputs: 0
            }
        };

        // VERTICAL LAYOUT COORDINATES
        let startX = window.innerWidth / 2 - 150; // Center initially
        let currentY = 100;
        let spacingY = 220;
        let spacingX = 350;

        const n_login = editor.addNode('login', nodesData.login.inputs, nodesData.login.outputs, startX, currentY, 'login', {}, createNodeHTML(nodesData.login));
        
        currentY += spacingY;
        const n_role = editor.addNode('role_check', nodesData.role_check.inputs, nodesData.role_check.outputs, startX, currentY, 'role_check', {}, createNodeHTML(nodesData.role_check));
        
        currentY += spacingY;
        // Branching for dashboards
        const n_dash_a = editor.addNode('dashboard_admin', nodesData.dashboard_admin.inputs, nodesData.dashboard_admin.outputs, startX - spacingX/2, currentY, 'dashboard_admin', {}, createNodeHTML(nodesData.dashboard_admin));
        const n_dash_u = editor.addNode('dashboard_user', nodesData.dashboard_user.inputs, nodesData.dashboard_user.outputs, startX + spacingX/2, currentY, 'dashboard_user', {}, createNodeHTML(nodesData.dashboard_user));
        
        currentY += spacingY;
        const n_create = editor.addNode('create_form', nodesData.create_form.inputs, nodesData.create_form.outputs, startX, currentY, 'create_form', {}, createNodeHTML(nodesData.create_form));
        
        currentY += spacingY;
        // Branching for online/offline
        const n_offline = editor.addNode('if_offline', nodesData.if_offline.inputs, nodesData.if_offline.outputs, startX - spacingX/2, currentY, 'if_offline', {}, createNodeHTML(nodesData.if_offline));
        const n_online = editor.addNode('if_online', nodesData.if_online.inputs, nodesData.if_online.outputs, startX + spacingX/2, currentY, 'if_online', {}, createNodeHTML(nodesData.if_online));
        
        currentY += spacingY;
        const n_conflict = editor.addNode('conflict_check', nodesData.conflict_check.inputs, nodesData.conflict_check.outputs, startX, currentY, 'conflict_check', {}, createNodeHTML(nodesData.conflict_check));
        
        currentY += spacingY;
        const n_approval = editor.addNode('approval_process', nodesData.approval_process.inputs, nodesData.approval_process.outputs, startX, currentY, 'approval_process', {}, createNodeHTML(nodesData.approval_process));
        
        currentY += spacingY;
        // TV & QR Generate
        const n_tv = editor.addNode('tv_display', nodesData.tv_display.inputs, nodesData.tv_display.outputs, startX - spacingX/2, currentY, 'tv_display', {}, createNodeHTML(nodesData.tv_display));
        const n_qr = editor.addNode('qr_generate', nodesData.qr_generate.inputs, nodesData.qr_generate.outputs, startX + spacingX/2, currentY, 'qr_generate', {}, createNodeHTML(nodesData.qr_generate));

        currentY += spacingY;
        const n_scan = editor.addNode('scan_process', nodesData.scan_process.inputs, nodesData.scan_process.outputs, startX + spacingX/2, currentY, 'scan_process', {}, createNodeHTML(nodesData.scan_process));
        
        currentY += spacingY;
        const n_cek_hadir = editor.addNode('cek_hadir', nodesData.cek_hadir.inputs, nodesData.cek_hadir.outputs, startX + spacingX/2, currentY, 'cek_hadir', {}, createNodeHTML(nodesData.cek_hadir));
        
        currentY += spacingY;
        const n_cek_telat = editor.addNode('cek_telat', nodesData.cek_telat.inputs, nodesData.cek_telat.outputs, startX + spacingX/2, currentY, 'cek_telat', {}, createNodeHTML(nodesData.cek_telat));
        
        currentY += spacingY;
        // Feedback vs Direct Insert
        const n_feedback = editor.addNode('form_feedback', nodesData.form_feedback.inputs, nodesData.form_feedback.outputs, startX, currentY, 'form_feedback', {}, createNodeHTML(nodesData.form_feedback));
        const n_insert = editor.addNode('insert_absen', nodesData.insert_absen.inputs, nodesData.insert_absen.outputs, startX + spacingX, currentY, 'insert_absen', {}, createNodeHTML(nodesData.insert_absen));
        
        currentY += spacingY;
        const n_report = editor.addNode('laporan', nodesData.laporan.inputs, nodesData.laporan.outputs, startX + spacingX/2, currentY, 'laporan', {}, createNodeHTML(nodesData.laporan));

        // Buat Koneksi
        editor.addConnection(n_login, n_role, 'output_1', 'input_1');
        
        editor.addConnection(n_role, n_dash_a, 'output_1', 'input_1');
        editor.addConnection(n_role, n_dash_u, 'output_2', 'input_1');
        
        editor.addConnection(n_dash_a, n_create, 'output_1', 'input_1');
        editor.addConnection(n_dash_u, n_create, 'output_1', 'input_2');
        
        editor.addConnection(n_create, n_offline, 'output_1', 'input_1');
        editor.addConnection(n_create, n_online, 'output_2', 'input_1');
        
        editor.addConnection(n_offline, n_conflict, 'output_1', 'input_1');
        editor.addConnection(n_online, n_conflict, 'output_1', 'input_2');
        
        // Output 2 = Aman
        editor.addConnection(n_conflict, n_approval, 'output_2', 'input_1');
        
        // Approval -> TV & QR
        editor.addConnection(n_approval, n_tv, 'output_1', 'input_1');
        editor.addConnection(n_approval, n_qr, 'output_1', 'input_1');
        
        // Scan QR
        editor.addConnection(n_qr, n_scan, 'output_1', 'input_1');
        // Sesuai Jam (Output 2)
        editor.addConnection(n_scan, n_cek_hadir, 'output_2', 'input_1');
        
        // Cek Hadir: Output 2 (Belum)
        editor.addConnection(n_cek_hadir, n_cek_telat, 'output_2', 'input_1');
        
        // Cek Telat: Output 1 (Terlambat) -> Feedback -> Insert
        editor.addConnection(n_cek_telat, n_feedback, 'output_1', 'input_1');
        editor.addConnection(n_feedback, n_insert, 'output_1', 'input_1');
        
        // Cek Telat: Output 2 (Tepat) -> Insert Langsung
        editor.addConnection(n_cek_telat, n_insert, 'output_2', 'input_2');
        
        // Insert -> Report
        editor.addConnection(n_insert, n_report, 'output_1', 'input_1');

        // Setting initial position
        editor.zoom = 0.7;
        editor.canvas_x = 0;
        editor.canvas_y = 50;
        editor.editor_mode = 'edit';
    </script>
</body>
</html>
