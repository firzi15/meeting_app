<?php
// database.php
date_default_timezone_set('Asia/Jakarta');

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        // Parse key-value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove optional surrounding quotes
            if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                $value = $matches[1];
            }
            
            // Only set if not already defined in system environment
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Database configuration from environment variables (Docker or .env)
$host = getenv('DB_HOST') ?: '';
$dbname = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';

try {
    // Connect to PostgreSQL
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    
    // Set errormode to exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Auto migrations
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_owner BOOLEAN DEFAULT FALSE");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS nik VARCHAR(50)");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS jabatan VARCHAR(150)");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS group_name VARCHAR(100)");
        $pdo->exec("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS has_snack BOOLEAN DEFAULT FALSE");
        $pdo->exec("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS has_coffee BOOLEAN DEFAULT FALSE");
        $pdo->exec("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS coffee_temp TEXT");
        $pdo->exec("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS coffee_type TEXT");
        $pdo->exec("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS is_hybrid_zoom BOOLEAN DEFAULT FALSE");
        $pdo->exec("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS pdf_link TEXT");

        $pdo->exec("CREATE TABLE IF NOT EXISTS external_guests (
            id SERIAL PRIMARY KEY,
            meeting_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            institution VARCHAR(255)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS employee_groups (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Seed default employee groups if not exist
        $stmt_check_g = $pdo->query("SELECT COUNT(*) FROM employee_groups");
        if ($stmt_check_g->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO employee_groups (name, description) VALUES 
                ('Manager', 'Kelompok Managerial & Kepala Divisi/Cabang'),
                ('Kepala Bagian (Kabag)', 'Kelompok Kepala Bagian / Section Head'),
                ('Staff', 'Kelompok Staff Karyawan')
            ON CONFLICT (name) DO NOTHING");
        }

        // Insert 'Online' room for all existing branches
        $stmt_br = $pdo->query("SELECT id FROM branches");
        $branches = $stmt_br->fetchAll();
        foreach ($branches as $br) {
            $stmt_check_room = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE LOWER(name) = 'online' AND branch_id = ?");
            $stmt_check_room->execute([$br['id']]);
            if ($stmt_check_room->fetchColumn() == 0) {
                $stmt_ins_room = $pdo->prepare("INSERT INTO rooms (name, branch_id) VALUES ('Online', ?)");
                $stmt_ins_room->execute([$br['id']]);
            }
        }

        // Auto-populate employees from sync script if not yet populated
        if (file_exists(__DIR__ . '/auto_sync_employees.php')) {
            $stmt_check_nik = $pdo->query("SELECT COUNT(*) FROM users WHERE nik IS NOT NULL AND nik != ''");
            if ($stmt_check_nik && $stmt_check_nik->fetchColumn() == 0) {
                include_once __DIR__ . '/auto_sync_employees.php';
            }
        }
    } catch (Exception $e) {}
    
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

function getCurrentBranchId() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
        return $_SESSION['admin_branch_id'] ?? ($_SESSION['branch_id'] ?? 1);
    }
    return $_SESSION['branch_id'] ?? 1;
}

function renderPagination($page, $total_pages, $params = []) {
    if ($total_pages <= 1) return;
    
    $buildUrl = function($p) use ($params) {
        $pArr = array_merge($_GET, $params, ['page' => $p]);
        return '?' . http_build_query($pArr);
    };

    echo '<div class="pagination-wrapper" style="padding: 20px 15px; display: flex; justify-content: center; align-items: center; gap: 6px; flex-wrap: wrap;">';

    // Previous Button
    if ($page > 1) {
        echo '<a href="' . htmlspecialchars($buildUrl($page - 1)) . '" class="pagination-nav-btn" style="padding: 6px 14px; border: 1px solid #cbd5e1; border-radius: 8px; color: #334155; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"><i class="fa-solid fa-chevron-left" style="font-size:0.75rem;"></i> Prev</a>';
    } else {
        echo '<span class="pagination-nav-btn disabled" style="padding: 6px 14px; border: 1px solid #e2e8f0; border-radius: 8px; color: #94a3b8; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; cursor: not-allowed;"><i class="fa-solid fa-chevron-left" style="font-size:0.75rem;"></i> Prev</span>';
    }

    // Numbered pages with window
    $range = 2;
    $start = max(1, $page - $range);
    $end = min($total_pages, $page + $range);

    if ($start > 1) {
        echo '<a href="' . htmlspecialchars($buildUrl(1)) . '" class="pagination-num-btn" style="min-width: 36px; height: 36px; display: inline-flex; justify-content: center; align-items: center; border: 1px solid #cbd5e1; border-radius: 8px; color: #334155; text-decoration: none; font-size: 0.85rem; font-weight: 600; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">1</a>';
        if ($start > 2) {
            echo '<span style="color: #94a3b8; padding: 0 4px; font-weight: 700; font-size: 0.85rem;">...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $page) {
            echo '<span class="pagination-num-btn active" style="min-width: 36px; height: 36px; display: inline-flex; justify-content: center; align-items: center; border: 1px solid var(--primary-color, #4f46e5); border-radius: 8px; color: #ffffff; font-size: 0.85rem; font-weight: 700; background: var(--primary-color, #4f46e5); box-shadow: 0 2px 4px rgba(79, 70, 229, 0.25);">' . $i . '</span>';
        } else {
            echo '<a href="' . htmlspecialchars($buildUrl($i)) . '" class="pagination-num-btn" style="min-width: 36px; height: 36px; display: inline-flex; justify-content: center; align-items: center; border: 1px solid #cbd5e1; border-radius: 8px; color: #334155; text-decoration: none; font-size: 0.85rem; font-weight: 600; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">' . $i . '</a>';
        }
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            echo '<span style="color: #94a3b8; padding: 0 4px; font-weight: 700; font-size: 0.85rem;">...</span>';
        }
        echo '<a href="' . htmlspecialchars($buildUrl($total_pages)) . '" class="pagination-num-btn" style="min-width: 36px; height: 36px; display: inline-flex; justify-content: center; align-items: center; border: 1px solid #cbd5e1; border-radius: 8px; color: #334155; text-decoration: none; font-size: 0.85rem; font-weight: 600; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">' . $total_pages . '</a>';
    }

    // Next Button
    if ($page < $total_pages) {
        echo '<a href="' . htmlspecialchars($buildUrl($page + 1)) . '" class="pagination-nav-btn" style="padding: 6px 14px; border: 1px solid #cbd5e1; border-radius: 8px; color: #334155; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">Next <i class="fa-solid fa-chevron-right" style="font-size:0.75rem;"></i></a>';
    } else {
        echo '<span class="pagination-nav-btn disabled" style="padding: 6px 14px; border: 1px solid #e2e8f0; border-radius: 8px; color: #94a3b8; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; cursor: not-allowed;">Next <i class="fa-solid fa-chevron-right" style="font-size:0.75rem;"></i></span>';
    }

    echo '</div>';
}

if (!function_exists('renderGroupedUserOptions')) {
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
}

?>
