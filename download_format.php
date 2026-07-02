<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && !(isset($_SESSION['can_dashboard']) && $_SESSION['can_dashboard']))) {
    header("Location: login.php");
    exit;
}

require_once 'SimpleXLSXGen.php';

$data = [
    ['ID (Kosongkan jika baru)', 'Nama', 'Username', 'Password', 'Divisi'],
    ['', 'John Doe', 'johndoe', 'password123', 'IT'],
    ['5', 'Jane Doe', 'janedoe', '', 'Finance']
];

Shuchkin\SimpleXLSXGen::fromArray($data)->downloadAs('template_import_karyawan.xlsx');
?>
