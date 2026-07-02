<?php
session_start();
require_once 'database.php';
require_once 'SimpleXLSXGen.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && !(isset($_SESSION['can_dashboard']) && $_SESSION['can_dashboard']))) {
    header("Location: login.php");
    exit;
}

$data = [
    ['ID', 'Nama', 'Username', 'Password', 'Divisi']
];

$current_branch = getCurrentBranchId();
$branch_condition = $current_branch > 0 ? "AND branch_id = $current_branch" : "";

$employees = $pdo->query("SELECT * FROM users WHERE role = 'user' $branch_condition ORDER BY id ASC")->fetchAll();
foreach ($employees as $emp) {
    $data[] = [$emp['id'], $emp['name'], $emp['username'], $emp['password'], $emp['division']];
}

Shuchkin\SimpleXLSXGen::fromArray($data)->downloadAs('data_karyawan.xlsx');
?>
