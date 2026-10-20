<?php
// GET bundle endpoint for the React ADMIN portal.
//   ?view=dashboard|appointments|clients|doctors|schedules|laboratory|vitals|walkins|reports|history|profile
// Shared SQL lives in src/staff_data.php so the thin PHP shells inject the same
// bundle server-side.
require_once __DIR__ . '/../src/api.php';
require_once __DIR__ . '/../src/staff_data.php';
api_require_admin();

$view = api_str('view', 'dashboard');
$params = array(
    'date'      => api_str('date'),
    'period'    => api_str('period'),
    'from'      => api_str('from'),
    'to'        => api_str('to'),
    'status'    => api_str('status'),
    'payment'   => api_str('payment'),
    'doctor_id' => api_int('doctor_id', 0),
    'patient'   => api_int('patient', 0),
    'group'     => api_str('group', 'date'),
);

$data = admin_view_data($conn, $view, $params);
if (empty($data) && !in_array($view, array('dashboard','appointments','clients','patients','doctors','schedules','laboratory','vitals','walkins','reports','history','profile'), true)) {
    api_fail('Unknown view: ' . $view, 404);
}

api_ok(array('view' => $view, 'data' => $data));
