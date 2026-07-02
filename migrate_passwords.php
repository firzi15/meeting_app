<?php
/**
 * migrate_passwords.php
 * 
 * Script ONE-TIME untuk meng-hash semua password plain text yang ada di database.
 * Jalankan SEKALI setelah pertama deploy, lalu HAPUS file ini dari server!
 * 
 * Cara jalankan:
 *   php migrate_passwords.php
 * atau akses via browser (hanya bisa jika admin sudah login):
 *   http://server/migrate_passwords.php
 */

// Keamanan: pastikan hanya bisa diakses via CLI atau admin yang sudah login
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die('Akses ditolak. Login sebagai admin terlebih dahulu.');
    }
}

require_once __DIR__ . '/database.php';

echo "=== Migrasi Password ke BCrypt ===\n\n";

// Ambil semua user
$stmt = $pdo->query("SELECT id, username, password FROM users");
$users = $stmt->fetchAll();

$migrated = 0;
$already_hashed = 0;
$skipped = 0;

foreach ($users as $user) {
    $pw = $user['password'];
    
    // Cek apakah sudah bcrypt (format $2y$...)
    if (str_starts_with($pw, '$2y$') || str_starts_with($pw, '$2a$')) {
        $already_hashed++;
        echo "  [SKIP] {$user['username']} — sudah bcrypt\n";
        continue;
    }
    
    if (empty($pw)) {
        $skipped++;
        echo "  [SKIP] {$user['username']} — password kosong\n";
        continue;
    }
    
    // Hash password plain text
    $hashed = password_hash($pw, PASSWORD_BCRYPT);
    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->execute([$hashed, $user['id']]);
    $migrated++;
    echo "  [OK]   {$user['username']} — berhasil di-hash\n";
}

echo "\n=== Selesai ===\n";
echo "  Di-hash    : {$migrated} user\n";
echo "  Sudah hash : {$already_hashed} user\n";
echo "  Dilewati   : {$skipped} user\n";
echo "\n⚠️  HAPUS file migrate_passwords.php dari server sekarang!\n";
