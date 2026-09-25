<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { echo "error: Unauthorized"; exit; }

include __DIR__ . '/../src/db.php';

$patient_id = isset($_POST["patient_id"]) ? intval($_POST["patient_id"]) : 0;
$doctor_id  = isset($_POST["doctor_id"])  ? intval($_POST["doctor_id"])  : 0;
$date       = isset($_POST["date"])  ? trim($_POST["date"]) : '';
$time       = isset($_POST["time"])  ? trim($_POST["time"]) : '';
$appointment_duration = 120; // 2 hours - FIXED DURATION (matches booking)

// Validate inputs
if (empty($patient_id) || empty($doctor_id) || empty($date) || empty($time)) {
    echo "error: Please fill all fields";
    $conn->close(); exit;
}

// Patient must exist
$stmt = $conn->prepare("SELECT id, name FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) { $stmt->close(); echo "error: Invalid patient"; $conn->close(); exit; }
$stmt->close();

// Doctor must exist
$stmt = $conn->prepare("SELECT id FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) { $stmt->close(); echo "error: Invalid doctor"; $conn->close(); exit; }
$stmt->close();

// Date not in the past
if (strtotime($date) < strtotime(date('Y-m-d'))) {
    echo "error: Cannot book appointments in the past";
    $conn->close(); exit;
}

// Time format
if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
    echo "error: Invalid time format";
    $conn->close(); exit;
}

$time_with_seconds = $time . ":00";
$start_timestamp = strtotime($date . ' ' . $time);
$end_timestamp   = $start_timestamp + ($appointment_duration * 60);
$end_time        = date('H:i:s', $end_timestamp);

// Overlap check (same doctor, same day, pending/confirmed)
$stmt = $conn->prepare("
    SELECT id FROM appointments
    WHERE doctor_id=? AND appointment_date=?
      AND status IN ('pending','confirmed')
      AND appointment_time < ?
      AND appointment_time + INTERVAL '2 hours' > ?
");
$duration_sql = NULL;
$stmt->bind_param("isss", $doctor_id, $date, $end_time, $time_with_seconds);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo "error: This time slot / overlapping period is already booked for this doctor";
    $conn->close(); exit;
}
$stmt->close();

// Within doctor's schedule
$day_of_week = date('l', strtotime($date));
$stmt = $conn->prepare("
    SELECT id FROM doctor_schedules
    WHERE doctor_id=? AND day_of_week=? AND ? BETWEEN start_time AND end_time AND is_active=1
");
$stmt->bind_param("iss", $doctor_id, $day_of_week, $time_with_seconds);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    echo "error: This doctor is not available at the selected time";
    $conn->close(); exit;
}
$stmt->close();

// Not inside an unavailable block
$stmt = $conn->prepare("
    SELECT id FROM doctor_time_blocks
    WHERE doctor_id=? AND ((block_type='date' AND block_date=?) OR (block_type='weekly' AND day_of_week=?))
      AND ? >= start_time AND ? < end_time
");
$stmt->bind_param("issss", $doctor_id, $date, $day_of_week, $time_with_seconds, $time_with_seconds);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo "error: This time is not available (the doctor has time off)";
    $conn->close(); exit;
}
$stmt->close();

// Insert
$stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, appointment_duration, status, payment_status) VALUES (?, ?, ?, ?, ?, 'pending','pending')");
$stmt->bind_param("iissi", $patient_id, $doctor_id, $date, $time_with_seconds, $appointment_duration);
if ($stmt->execute()) {
    $new_id = $conn->insert_id;
    require_once __DIR__ . '/appointment_mailer.php';
    send_appointment_email($conn, $new_id, 'booked');
    echo "success:" . $new_id;
} else {
    echo "error: Failed to book appointment";
}
$stmt->close();
$conn->close();
