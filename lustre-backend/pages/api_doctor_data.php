<?php
// GET bundle endpoint for the React DOCTOR portal.
//   ?view=dashboard|appointments|clients|laboratory|schedule|profile
require_once __DIR__ . '/../src/api.php';
require_once __DIR__ . '/../src/staff_data.php';
api_require_doctor();

$view = api_str('view', 'dashboard');
$data = doctor_view_data($conn, $view);
if (empty($data) && !in_array($view, array('dashboard','appointments','clients','laboratory','schedule','profile'), true)) {
    api_fail('Unknown view: ' . $view, 404);
}

api_ok(array('view' => $view, 'data' => $data));
