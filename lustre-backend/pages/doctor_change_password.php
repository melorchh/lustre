<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_account_id"])) { header("Location: doctor_login.php"); exit; }
require_once __DIR__ . '/../src/staff_shell.php';
require_once __DIR__ . '/../src/staff_data.php';

$data  = doctor_view_data($conn, 'profile');
$staff = staff_identity('doctor');
render_staff_shell($staff, 'change_password', $data, 'doctor_change_password', 'Change Password');
