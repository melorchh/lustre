<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
require_once __DIR__ . '/../src/staff_shell.php';
require_once __DIR__ . '/../src/staff_data.php';

$data  = admin_view_data($conn, 'reports', array(
    'period'    => isset($_GET['period']) ? trim($_GET['period']) : '',
    'from'      => isset($_GET['from']) ? trim($_GET['from']) : '',
    'to'        => isset($_GET['to']) ? trim($_GET['to']) : '',
    'status'    => isset($_GET['status']) ? trim($_GET['status']) : '',
    'payment'   => isset($_GET['payment']) ? trim($_GET['payment']) : '',
    'doctor_id' => isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0,
    'group'     => isset($_GET['group']) ? trim($_GET['group']) : 'date',
));
$staff = staff_identity('admin');
render_staff_shell($staff, 'reports', $data, 'admin_reports', 'Reports');
