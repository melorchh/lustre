<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
require_once __DIR__ . '/../src/staff_shell.php';
require_once __DIR__ . '/../src/staff_data.php';

$data  = admin_view_data($conn, 'vitals', array(
    'patient' => isset($_GET['patient']) ? (int)$_GET['patient'] : 0,
));
$staff = staff_identity('admin');
render_staff_shell($staff, 'vitals', $data, 'admin_vitals', 'Vaccines');
