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

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';
$exclude_appointment_id = isset($_GET['exclude_appointment_id']) ? (int)$_GET['exclude_appointment_id'] : 0;
$email_token = isset($_GET['email_token']) ? trim($_GET['email_token']) : '';

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
        error_log('Unable to load appointment for reschedule-time lookup: ' . $stmt->error);
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

if (empty($doctor_id) || empty($date)) {
    echo json_encode([]);
    $conn->close();
    exit;
}

// Only allow appointments to be scheduled at least one day ahead.
$selected_date = strtotime($date);
$today = strtotime(date('Y-m-d'));

if ($selected_date === false || date('Y-m-d', $selected_date) !== $date || $selected_date <= $today) {
    echo json_encode([]);
    $conn->close();
    exit;
}

$daily_limit = doctor_daily_limit_status($conn, $doctor_id, $date, false, $exclude_appointment_id);
if ($daily_limit === null) {
    http_response_code(500);
    echo json_encode(['error' => "Could not verify the doctor's daily appointment limit."]);
    $conn->close();
    exit;
}
if (!$daily_limit['available']) {
    echo json_encode([]);
    $conn->close();
    exit;
}

// Only slots inside the doctor's first 4 available hours with free capacity.
$available = lustre_appt_available_slots($conn, $doctor_id, $date, $exclude_appointment_id);

$conn->close();

echo json_encode($available);
?>