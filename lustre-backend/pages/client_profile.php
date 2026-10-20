<?php
require __DIR__ . '/../src/session.php';
$is_admin  = isset($_SESSION['admin_id']);
$is_doctor = isset($_SESSION['doctor_id']);
if (!$is_admin && !$is_doctor) { header('Location: login.php'); exit; }
require_once __DIR__ . '/../src/staff_shell.php';
require_once __DIR__ . '/../src/staff_data.php';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$doctor_id  = $is_doctor ? (int)$_SESSION['doctor_id'] : 0;
$data  = client_profile_data($conn, $patient_id, $doctor_id);
$staff = staff_identity($is_doctor ? 'doctor' : 'admin');
render_staff_shell($staff, 'client_profile', $data, 'client_profile', 'Patient Profile');
