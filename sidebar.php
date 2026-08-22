<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="logo.png" alt="Logo" onerror="this.outerHTML='<span class=\'logo-text\'>INDO<span style=\'color:var(--red-brand)\'>A</span>RSIP</span>'">
        </div>
        <button class="toggle-btn" onclick="if(typeof toggleSidebar==='function'){toggleSidebar();}else{document.querySelector('.sidebar').classList.toggle('collapsed');document.querySelector('.app-container')?.classList.toggle('sidebar-collapsed');}">
            <i class="fa-solid fa-bars-staggered"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <?php 
        $role = $_SESSION['role'] ?? 'user';
        $is_superadmin = ($role === 'superadmin');
        $is_admin = ($role === 'admin');
        $has_dashboard = $is_superadmin || $is_admin || !empty($_SESSION['can_dashboard']);
        $can_master = $is_superadmin;
        $can_reports = $is_superadmin;
        ?>

        <div class="nav-section">Utama</div>
        <?php if ($has_dashboard): ?>
        <a href="index.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>
        <?php endif; ?>
        <a href="calendar.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-days"></i>
            <span>Kalender</span>
        </a>

        <?php if (!$is_superadmin): ?>
        <a href="my_schedule.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'my_schedule.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span><?= $has_dashboard ? 'Riwayat Absen' : 'Presensi' ?></span>
        </a>
        <?php endif; ?>

        <?php if ($can_reports): ?>
        <div class="nav-section" onclick="this.classList.toggle('closed'); this.nextElementSibling.classList.toggle('closed');">
            <span>Manajemen Meeting</span>
            <i class="fa-solid fa-chevron-down section-chevron"></i>
        </div>
        <div class="nav-group">
            <a href="report.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-file-invoice"></i>
                <span>Laporan</span>
            </a>

            <?php if ($is_superadmin): ?>
            <a href="grant_access.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'grant_access.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-lock"></i>
                <span>Grant Access</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($can_master): ?>
        <div class="nav-section" onclick="this.classList.toggle('closed'); this.nextElementSibling.classList.toggle('closed');">
            <span>Data Master</span>
            <i class="fa-solid fa-chevron-down section-chevron"></i>
        </div>
        <div class="nav-group">
            <a href="branches.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'branches.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-building"></i>
                <span>Master Cabang</span>
            </a>
            <a href="rooms.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-door-open"></i>
                <span>Master Ruangan</span>
            </a>
            <a href="employees.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-gear"></i>
                <span>Master Karyawan</span>
            </a>
            <a href="groups.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'groups.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-layer-group"></i>
                <span>Master Group</span>
            </a>
            <a href="divisions.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'divisions.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-sitemap"></i>
                <span>Master Divisi</span>
            </a>
            <a href="templates.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'templates.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-copy"></i>
                <span>Master Template</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const activeItem = document.querySelector('.sidebar-nav .nav-item.active');
        if (activeItem) {
            const parentGroup = activeItem.closest('.nav-group');
            if (parentGroup) {
                parentGroup.classList.remove('closed');
                const previousSibling = parentGroup.previousElementSibling;
                if (previousSibling && previousSibling.classList.contains('nav-section')) {
                    previousSibling.classList.remove('closed');
                }
            }
        }
    });
</script>
