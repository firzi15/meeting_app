<!-- topbar.php -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php
$topbar_branch_id = getCurrentBranchId();
$stmt_topbar_branch = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$stmt_topbar_branch->execute([$topbar_branch_id]);
$topbar_branch_name = $stmt_topbar_branch->fetchColumn() ?: 'Kantor Pusat';
?>
<header class="topbar">
    <div class="topbar-left">
        <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <span id="current-datetime"><?= date('d M Y H:i:s') ?></span><span> - <?= htmlspecialchars($topbar_branch_name) ?></span>
    </div>
    <div class="topbar-right" style="display: flex; align-items: center; gap: 15px;">
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <?php
                // Fetch branches for admin switcher
                $stmt_branches = $pdo->query("SELECT * FROM branches ORDER BY name ASC");
                $all_branches = $stmt_branches->fetchAll();
                
                // Handle switch branch logic
                if (isset($_POST['switch_branch_id'])) {
                    $_SESSION['admin_branch_id'] = (int)$_POST['switch_branch_id'];
                    // Refresh page using JS or header to apply new branch
                    echo "<script>window.location.href = window.location.href;</script>";
                    exit;
                }
                
                $current_admin_branch = $_SESSION['admin_branch_id'] ?? 1;
            ?>
            <div class="profile-container">
                <div class="profile-info" onclick="toggleBranchDropdown(event)">
                    <i class="fa-solid fa-building" style="color: #64748b;"></i>
                    <span class="profile-name">
                        <?php 
                            $current_branch_name = '';
                            foreach($all_branches as $br) {
                                if ($current_admin_branch == $br['id']) {
                                    $current_branch_name = $br['name'];
                                    break;
                                }
                            }
                            if (empty($current_branch_name) && !empty($all_branches)) {
                                $current_branch_name = $all_branches[0]['name'];
                                $current_admin_branch = $all_branches[0]['id'];
                                $_SESSION['admin_branch_id'] = $current_admin_branch;
                            }
                            echo htmlspecialchars($current_branch_name);
                        ?>
                    </span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                </div>
                
                <div class="profile-dropdown" id="branchDropdown" style="width: 220px; left: 0; right: auto;">
                    <div class="dropdown-header">Pilih Cabang</div>
                    <form method="POST" id="switchBranchForm" style="display:none;">
                        <input type="hidden" name="switch_branch_id" id="switchBranchIdInput" value="">
                    </form>
                    <?php foreach($all_branches as $br): ?>
                        <a href="#" onclick="submitBranchSwitch(<?= $br['id'] ?>); return false;" class="dropdown-item <?= $current_admin_branch == $br['id'] ? 'active' : '' ?>">
                            <i class="fa-solid fa-map-pin"></i>
                            <span><?= htmlspecialchars($br['name']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="profile-container">
            <div class="profile-info" onclick="toggleProfileDropdown(event)">
                <div class="profile-avatar" id="topbarAvatar" style="overflow: hidden; display: flex; align-items: center; justify-content: center; background: #e0e7ff; color: #4f46e5; font-size: 1.2rem; border: none;">
                    <i class="fa-solid fa-user"></i>
                </div>
                <span class="profile-name"><?= htmlspecialchars($_SESSION['name']) ?></span>
                <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
            </div>
            
            <div class="profile-dropdown" id="profileDropdown">
                <div class="dropdown-header">Manajemen Akun</div>
                <div class="dropdown-divider"></div>
                <a href="#" onclick="confirmLogout(event)" class="dropdown-item logout">
                    <i class="fa-solid fa-power-off"></i>
                    <span>Keluar</span>
                </a>
            </div>
        </div>
    </div>
</header>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<script>


    function toggleBranchDropdown(e) {
        e.stopPropagation();
        const bDropdown = document.getElementById('branchDropdown');
        const pDropdown = document.getElementById('profileDropdown');
        if (pDropdown) pDropdown.classList.remove('active');
        bDropdown.classList.toggle('active');
    }

    function toggleProfileDropdown(e) {
        e.stopPropagation();
        const bDropdown = document.getElementById('branchDropdown');
        const pDropdown = document.getElementById('profileDropdown');
        if (bDropdown) bDropdown.classList.remove('active');
        pDropdown.classList.toggle('active');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        const pDropdown = document.getElementById('profileDropdown');
        const bDropdown = document.getElementById('branchDropdown');
        if (pDropdown) pDropdown.classList.remove('active');
        if (bDropdown) bDropdown.classList.remove('active');
    });

    function submitBranchSwitch(branchId) {
        document.getElementById('switchBranchIdInput').value = branchId;
        document.getElementById('switchBranchForm').submit();
    }

    function confirmLogout(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Yakin ingin keluar?',
            text: "Anda akan mengakhiri sesi saat ini.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f87171',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Ya, Keluar!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    }
</script>

<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        } else {
            sidebar.classList.toggle('collapsed');
        }
    }

    function confirmWithSweetAlert(event, formId, actionName, message) {
        event.preventDefault();
        const form = document.getElementById(formId) || event.target;
        Swal.fire({
            title: 'Konfirmasi',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = actionName;
                hidden.value = '1';
                form.appendChild(hidden);
                form.submit();
            }
        });
        return false;
    }
</script>

<script>
    function updateClock() {
        const now = new Date();
        const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        const day = String(now.getDate()).padStart(2, '0');
        const month = months[now.getMonth()];
        const year = now.getFullYear();
        const time = now.toLocaleTimeString('id-ID', { hour12: false });
        
        const display = document.getElementById('current-datetime');
        if (display) {
            display.textContent = `${day} ${month} ${year} ${time}`;
        }
    }
    // Run immediately and then every second
    updateClock();
    setInterval(updateClock, 1000);
</script>

<script>
    $(document).ready(function() {
        // Initialize select2 on all select elements with class form-control
        $('select.form-control:not(.no-select2)').select2({
            width: '100%'
        });

        // Special handling for the branch switcher to submit on change via Select2's event
        $('select[name="switch_branch_id"]').on('change', function() {
            document.getElementById('switchBranchForm').submit();
        });
    });
</script>
