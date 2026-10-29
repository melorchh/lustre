<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

require __DIR__ . '/../src/session.php';
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(array('error' => 'Unauthorized'));
    exit;
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/doctor_capacity.php';
require_once __DIR__ . '/../src/time_blocks_schema.php';
ensure_time_blocks_schema($conn);

$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
if ($doctor_id <= 0) {
    http_response_code(400);
    echo json_encode(array('error' => 'Please select a valid doctor.'));
    $conn->close();
    exit;
}

$date = date('Y-m-d');
$capacity = doctor_capacity_map($conn, $date);
if (!isset($capacity[$doctor_id]) || empty($capacity[$doctor_id]['available'])) {
    echo json_encode(array());
    $conn->close();
    exit;
}

$times = doctor_available_walkin_times($conn, $doctor_id, $date);
if ($times === null) {
    http_response_code(500);
    echo json_encode(array('error' => 'Could not load available walk-in times.'));
    $conn->close();
    exit;
}

echo json_encode($times);
$conn->close();
