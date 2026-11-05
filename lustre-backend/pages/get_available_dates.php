<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/time_blocks_schema.php';
require_once __DIR__ . '/../src/appointment_time_rules.php';
require_once __DIR__ . '/../src/appointment_slots.php';
require_once __DIR__ . '/../src/doctor_capacity.php';
require_once __DIR__ . '/../src/appointment_reschedule_tokens.php';
ensure_time_blocks_schema($conn);
ensure_doctor_capacity_schema($conn);

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$exclude_appointment_id = isset($_GET['exclude_appointment_id']) ? (int)$_GET['exclude_appointment_id'] : 0;
$email_token = isset($_GET['email_token']) ? trim($_GET['email_token']) : '';
$days = isset($_GET['days']) ? intval($_GET['days']) : 14;
if ($days < 1) $days = 14;

if ($email_token !== '') {
    $token_row = find_appointment_reschedule_token($conn, $email_token);
    if (!$token_row) {
        http_response_code(410);
        echo json_encode([]);
        $conn->close();
        exit;
    }
    $stmt = $conn->prepare("SELECT doctor_id FROM appointments WHERE id = ? AND status IN ('pending', 'confirmed', 'cancelled')");
    $stmt->bind_param('i', $token_row['appointment_id']);
    if (!$stmt->execute()) {
        error_log('Unable to load appointment for reschedule-date lookup: ' . $stmt->error);
        http_response_code(500);
        echo json_encode([]);
        $stmt->close();
        $conn->close();
        exit;
    }
    $res = $stmt->get_result();
    $appointment = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$appointment) {
        http_response_code(410);
        echo json_encode([]);
        $conn->close();
        exit;
    }
    $doctor_id = (int)$appointment['doctor_id'];
    $exclude_appointment_id = $token_row['appointment_id'];
}

if (empty($doctor_id)) {
    echo json_encode([]);
    $conn->close();
    exit;
}

$available_dates = [];
$today = strtotime(date('Y-m-d'));
$interval = 24 * 60 * 60;

for ($i = 1; $i <= $days; $i++) {
    $date = date('Y-m-d', $today + $i * $interval);
    $daily_limit = doctor_daily_limit_status($conn, $doctor_id, $date, false, $exclude_appointment_id);
    if ($daily_limit === null) {
        http_response_code(500);
        echo json_encode(['error' => "Could not verify the doctor's daily appointment limit."]);
        $conn->close();
        exit;
    }
    if (!$daily_limit['available']) {
        continue;
    }

    // A date is bookable only when at least one slot in the doctor's first
    // 4 available hours still has room for another patient.
    if (empty(lustre_appt_available_slots($conn, $doctor_id, $date, $exclude_appointment_id))) {
        continue;
    }

    $available_dates[] = [
        'date' => $date,
        'display' => date('M d (D)', strtotime($date))
    ];
}

$conn->close();
echo json_encode($available_dates);
?>
