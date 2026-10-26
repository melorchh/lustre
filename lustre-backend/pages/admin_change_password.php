<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
require_once __DIR__ . '/../src/staff_shell.php';
require_once __DIR__ . '/../src/staff_data.php';

$data  = admin_view_data($conn, 'profile');
$staff = staff_identity('admin');
render_staff_shell($staff, 'change_password', $data, 'admin_change_password', 'Change Password');
