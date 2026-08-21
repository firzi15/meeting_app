<?php
// cypress_reset_db.php
session_start();
session_destroy();

header('Content-Type: application/json');
require_once 'database.php';

try {
    // Drop existing tables and sequences to ensure a clean slate
    $pdo->exec("
        DROP TABLE IF EXISTS employee_groups CASCADE;
        DROP TABLE IF EXISTS attendances CASCADE;
        DROP TABLE IF EXISTS meeting_feedbacks CASCADE;
        DROP TABLE IF EXISTS meeting_participants CASCADE;
        DROP TABLE IF EXISTS meetings CASCADE;
        DROP TABLE IF EXISTS meeting_templates CASCADE;
        DROP TABLE IF EXISTS rooms CASCADE;
        DROP TABLE IF EXISTS divisions CASCADE;
        DROP TABLE IF EXISTS users CASCADE;
        DROP TABLE IF EXISTS branches CASCADE;
        DROP TABLE IF EXISTS login_attempts CASCADE;

        DROP SEQUENCE IF EXISTS employee_groups_id_seq CASCADE;
        DROP SEQUENCE IF EXISTS attendances_id_seq CASCADE;
        DROP SEQUENCE IF EXISTS meeting_feedbacks_id_seq CASCADE;
        DROP SEQUENCE IF EXISTS meetings_id_seq CASCADE;
        DROP SEQUENCE IF EXISTS meeting_templates_id_seq CASCADE;
        DROP SEQUENCE IF EXISTS rooms_id_seq CASCADE;
        DROP SEQUENCE IF EXISTS divisions_id_seq CASCADE;
        DROP SEQUENCE IF EXISTS users_id_seq CASCADE;
        DROP SEQUENCE IF EXISTS branches_id_seq CASCADE;
        DROP SEQUENCE IF EXISTS login_attempts_id_seq CASCADE;

        CREATE SEQUENCE employee_groups_id_seq;
        CREATE SEQUENCE attendances_id_seq;
        CREATE SEQUENCE meeting_feedbacks_id_seq;
        CREATE SEQUENCE meetings_id_seq;
        CREATE SEQUENCE meeting_templates_id_seq;
        CREATE SEQUENCE rooms_id_seq;
        CREATE SEQUENCE divisions_id_seq;
        CREATE SEQUENCE users_id_seq;
        CREATE SEQUENCE branches_id_seq;
        CREATE SEQUENCE login_attempts_id_seq;
    ");

    // Read the dump SQL schema and seed data
    $sqlDumpPath = __DIR__ . '/db_meeting_dump.sql';
    if (!file_exists($sqlDumpPath)) {
        throw new Exception("SQL dump file not found at " . $sqlDumpPath);
    }
    
    $sql = file_get_contents($sqlDumpPath);
    $pdo->exec($sql);

    // Apply auto-migrations as in database.php to ensure all newer columns are active
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS employee_groups (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $pdo->exec("INSERT INTO employee_groups (name, description) VALUES 
            ('Manager', 'Manajer Cabang / Departemen'),
            ('Kepala Bagian (Kabag)', 'Kepala Bagian / Unit Kerja'),
            ('Staff', 'Karyawan / Staff Operasional')
            ON CONFLICT (name) DO NOTHING;
        ");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_owner BOOLEAN DEFAULT FALSE");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS group_name VARCHAR(100) DEFAULT 'Staff'");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS jabatan VARCHAR(150)");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS nik VARCHAR(50)");
        $pdo->exec("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS has_snack BOOLEAN DEFAULT FALSE");
        $pdo->exec("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS has_coffee BOOLEAN DEFAULT FALSE");
        $pdo->exec("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS coffee_temp TEXT");
        $pdo->exec("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS coffee_type TEXT");
        $pdo->exec("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS is_hybrid_zoom BOOLEAN DEFAULT FALSE");
        
        // Auto sync all 9 branches and 262 employees
        $dataFile = __DIR__ . '/auto_sync_employees.json';
        if (file_exists($dataFile)) {
            $payload = json_decode(file_get_contents($dataFile), true);
            if ($payload) {
                foreach ($payload['branches'] as $br) {
                    $stmt_b = $pdo->prepare("INSERT INTO branches (id, name) VALUES (?, ?) ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name");
                    $stmt_b->execute([$br[0], $br[1]]);
                }
                $pdo->exec("SELECT setval('branches_id_seq', (SELECT COALESCE(MAX(id), 1) FROM branches))");

                $hashed_pass = password_hash('password123', PASSWORD_BCRYPT);
                $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR (nik = ? AND nik != '')");
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
                $pdo->exec("UPDATE users SET role = 'superadmin' WHERE username = 'admin'");
            }
        }
    } catch (Exception $e) {
        // Ignore column already exists errors
    }

    // Compute timestamps in PHP's timezone (Asia/Jakarta) to prevent Docker timezone mismatch
    $now_ts = time();
    $tepat_waktu_start = date('Y-m-d H:i:s', $now_ts - 5 * 60);
    $tepat_waktu_end = date('Y-m-d H:i:s', $now_ts + 60 * 60);

    $terlambat_start = date('Y-m-d H:i:s', $now_ts - 25 * 60);
    $terlambat_end = date('Y-m-d H:i:s', $now_ts + 60 * 60);

    // Seed an approved meeting starting 5 minutes ago (for Tepat Waktu test)
    $stmt1 = $pdo->prepare("
        INSERT INTO meetings (title, room, scheduled_time, end_time, late_tolerance, token, created_by, pic_id, status, branch_id)
        VALUES ('Meeting Tepat Waktu', 'Ruang Meeting Besar', ?, ?, 15, 'token-tepat-waktu', 1, 2, 'approved', 1)
    ");
    $stmt1->execute([$tepat_waktu_start, $tepat_waktu_end]);

    $pdo->exec("
        INSERT INTO meeting_participants (meeting_id, user_id)
        SELECT id, 2 FROM meetings WHERE token = 'token-tepat-waktu' UNION ALL
        SELECT id, 3 FROM meetings WHERE token = 'token-tepat-waktu'
    ");

    // Seed an approved meeting starting 25 minutes ago (for Terlambat test)
    $stmt2 = $pdo->prepare("
        INSERT INTO meetings (title, room, scheduled_time, end_time, late_tolerance, token, created_by, pic_id, status, branch_id)
        VALUES ('Meeting Terlambat', 'Ruang Meeting Besar', ?, ?, 15, 'token-terlambat', 1, 2, 'approved', 1)
    ");
    $stmt2->execute([$terlambat_start, $terlambat_end]);

    $pdo->exec("
        INSERT INTO meeting_participants (meeting_id, user_id)
        SELECT id, 2 FROM meetings WHERE token = 'token-terlambat' UNION ALL
        SELECT id, 3 FROM meetings WHERE token = 'token-terlambat'
    ");

    echo json_encode(["status" => "success", "message" => "Database successfully reset to seed state"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
