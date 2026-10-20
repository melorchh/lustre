<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_id"])) { header("Location: doctor_login.php"); exit; }
require_once __DIR__ . '/../src/staff_shell.php';
require_once __DIR__ . '/../src/staff_data.php';

$data  = doctor_view_data($conn, 'laboratory');
$staff = staff_identity('doctor');
render_staff_shell($staff, 'laboratory', $data, 'doctor_laboratory', 'Laboratory');
