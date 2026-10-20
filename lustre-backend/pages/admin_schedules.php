<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
require_once __DIR__ . '/../src/staff_shell.php';
require_once __DIR__ . '/../src/staff_data.php';

$data  = admin_view_data($conn, 'schedules');
$staff = staff_identity('admin');
render_staff_shell($staff, 'schedules', $data, 'admin_schedules', 'Schedules');
