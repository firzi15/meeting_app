<?php
// auto_sync_employees.php
require_once __DIR__ . '/database.php';

try {
    $dataFile = __DIR__ . '/auto_sync_employees.json';
    if (!file_exists($dataFile)) {
        die("auto_sync_employees.json not found.");
    }
    $payload = json_decode(file_get_contents($dataFile), true);
    if (!$payload) {
        die("Invalid JSON data.");
    }

    // 1. Ensure branches exist
    foreach ($payload['branches'] as $br) {
        $stmt = $pdo->prepare("INSERT INTO branches (id, name) VALUES (?, ?) ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name");
        $stmt->execute([$br[0], $br[1]]);
    }
    // Update sequence
    $pdo->exec("SELECT setval('branches_id_seq', (SELECT COALESCE(MAX(id), 1) FROM branches))");

    // 2. Ensure default groups exist
    $pdo->exec("INSERT INTO employee_groups (name, description) VALUES 
        ('Manager', 'Kelompok Managerial & Kepala Divisi/Cabang'),
        ('Kepala Bagian (Kabag)', 'Kelompok Kepala Bagian / Section Head'),
        ('Staff', 'Kelompok Staff Karyawan')
    ON CONFLICT (name) DO NOTHING");

    // 3. Upsert Employees
    $hashed_pass = password_hash('password123', PASSWORD_BCRYPT);

    $stmt_check = $pdo->prepare("SELECT id, role FROM users WHERE username = ? OR (nik = ? AND nik != '')");
    $stmt_insert = $pdo->prepare("INSERT INTO users (nik, name, username, password, role, jabatan, group_name, division, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_update = $pdo->prepare("UPDATE users SET nik = ?, name = ?, jabatan = ?, group_name = ?, division = ?, branch_id = ? WHERE id = ?");

    foreach ($payload['employees'] as $emp) {
        $stmt_check->execute([$emp['username'], $emp['nik']]);
        $existing = $stmt_check->fetch();
        if ($existing) {
            $stmt_update->execute([$emp['nik'], $emp['name'], $emp['jabatan'], $emp['group_name'], $emp['division'], $emp['branch_id'], $existing['id']]);
        } else {
            $role = 'user';
            $stmt_insert->execute([$emp['nik'], $emp['name'], $emp['username'], $hashed_pass, $role, $emp['jabatan'], $emp['group_name'], $emp['division'], $emp['branch_id']]);
        }
    }
    
    // Ensure admin user is superadmin
    $pdo->exec("UPDATE users SET role = 'superadmin' WHERE username = 'admin'");
    
    echo "SYNC_SUCCESS: " . count($payload['employees']) . " employees processed successfully.";
} catch (Exception $e) {
    echo "SYNC_ERROR: " . $e->getMessage();
}
