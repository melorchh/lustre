<?php
// Doctors who can still take a patient on a given day (default: today).
//
// Used by the admin walk-in form so the doctor dropdown only lists doctors
// with room left. See lustre-backend/src/doctor_capacity.php for the rules:
// the doctor must have an active schedule and at least one free walk-in slot.
// Online appointments and time-off blocks reserve their overlapping slots;
// doctors.daily_limit also caps the total daily patient count.
//
//   GET get_available_doctors.php[?date=YYYY-MM-DD]
//   -> [ { id, name, specialty, daily_limit, mode, remaining }, ... ]
//
// Doctors without an active shift that day are omitted.

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/doctor_capacity.php';

$date = isset($_GET['date']) ? trim((string)$_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
    echo json_encode(array());
    $conn->close();
    exit;
}

$snapshot = doctor_capacity_map($conn, $date);

$doctors = array();
$res = $conn->query("SELECT id, name, specialty, daily_limit FROM doctors ORDER BY name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $id = (int)$row['id'];
        $info = isset($snapshot[$id]) ? $snapshot[$id] : null;
        if (!$info || empty($info['available'])) {
            continue;
        }
        $doctors[] = array(
            'id'          => $id,
            'name'        => $row['name'],
            'specialty'   => $row['specialty'],
            'daily_limit' => (int)$row['daily_limit'],
            'mode'        => $info['mode'],
            'remaining'   => $info['remaining'],
        );
    }
    $res->close();
}

$conn->close();
echo json_encode($doctors);
