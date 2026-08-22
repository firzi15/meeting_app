<?php
session_start();
require_once 'database.php';
require_once 'SimpleXLSXGen.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit;
}

$data = [
    ['ID', 'NIK', 'Nama', 'Username', 'Role', 'Jabatan', 'Group', 'Divisi']
];

$current_branch = getCurrentBranchId();
$branch_condition = $current_branch > 0 ? "AND branch_id = $current_branch" : "";

$employees = $pdo->query("SELECT * FROM users WHERE role != 'superadmin' $branch_condition ORDER BY id ASC")->fetchAll();
foreach ($employees as $emp) {
    $data[] = [$emp['id'], $emp['nik'] ?? '', $emp['name'], $emp['username'], $emp['role'], $emp['jabatan'] ?? '', $emp['group_name'] ?? '', $emp['division']];
}

Shuchkin\SimpleXLSXGen::fromArray($data)->downloadAs('data_karyawan.xlsx');
?>
