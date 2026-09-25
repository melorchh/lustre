<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["patient_id"])) {
    echo "error: Please login first";
    exit;
}

include __DIR__ . '/../src/db.php';

$patient_id = $_SESSION["patient_id"];
$doctor_id = isset($_POST["doctor_id"]) ? intval($_POST["doctor_id"]) : 0;
$date = isset($_POST["date"]) ? trim($_POST["date"]) : '';
$time = isset($_POST["time"]) ? trim($_POST["time"]) : '';
$service = isset($_POST["service"]) ? trim($_POST["service"]) : '';
$appointment_duration = 120; // 2 hours in minutes - FIXED DURATION

// Validate that patient exists
$stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo "error: Invalid patient. Please login again.";
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Validate inputs
if (empty($doctor_id) || empty($date) || empty($time) || empty($service)) {
    echo "error: Please fill all fields";
    $conn->close();
    exit;
}

// Validate that doctor exists
$stmt = $conn->prepare("SELECT id FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo "error: Invalid doctor selected";
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Validate date is not in the past
$selected_date = strtotime($date);
$today = strtotime(date('Y-m-d'));
if ($selected_date < $today) {
    echo "error: Cannot book appointments in the past";
    $conn->close();
    exit;
}

// Validate time format
if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
    echo "error: Invalid time format";
    $conn->close();
    exit;
}

$time_with_seconds = $time . ":00";

// Calculate end time based on 2-hour duration
$start_timestamp = strtotime($date . ' ' . $time);
$end_timestamp = $start_timestamp + (120 * 60); // 2 hours = 120 minutes
$end_time = date('H:i:s', $end_timestamp);

// Check if any slot is already booked within the 2-hour duration
// This checks if new appointment overlaps with any existing appointment's 2-hour block
$stmt = $conn->prepare("
    SELECT id FROM appointments 
    WHERE doctor_id=? 
    AND appointment_date=? 
    AND status IN ('pending', 'confirmed')
    AND appointment_time < ?
    AND appointment_time + INTERVAL '2 hours' > ?
");
$stmt->bind_param("isss", $doctor_id, $date, $end_time, $time_with_seconds);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo "error: This time slot or overlapping 2-hour period is already booked";
    $conn->close();
    exit;
}
$stmt->close();

// Check if patient already has an active appointment (FIXED: Check for pending OR confirmed)
$stmt = $conn->prepare("SELECT id FROM appointments WHERE patient_id=? AND status IN ('pending', 'confirmed') AND appointment_date >= CURRENT_DATE");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo "error: You already have an active appointment. Please cancel or complete it first.";
    $conn->close();
    exit;
}
$stmt->close();

// Verify the selected time is within doctor's schedule
$day_of_week = date('l', strtotime($date)); // Get day name (Monday, Tuesday, etc.)

$stmt = $conn->prepare("
    SELECT id FROM doctor_schedules 
    WHERE doctor_id = ? 
    AND day_of_week = ? 
    AND ? BETWEEN start_time AND end_time 
    AND is_active = 1
");
$stmt->bind_param("iss", $doctor_id, $day_of_week, $time_with_seconds);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    echo "error: This doctor is not available at the selected time";
    $conn->close();
    exit;
}
$stmt->close();

// Verify the selected time does not fall inside an unavailable time block
$stmt = $conn->prepare("
    SELECT id FROM doctor_time_blocks
    WHERE doctor_id = ?
      AND ((block_type = 'date' AND block_date = ?)
        OR (block_type = 'weekly' AND day_of_week = ?))
      AND ? >= start_time
      AND ? <  end_time
");
$stmt->bind_param("issss", $doctor_id, $date, $day_of_week, $time_with_seconds, $time_with_seconds);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo "error: This time is not available (the doctor has time off in this window). Please pick another time.";
    $conn->close();
    exit;
}
$stmt->close();

// Save appointment (2-hour duration is hardcoded)
$stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, service_requested, status, payment_status) VALUES (?, ?, ?, ?, ?, 'pending', 'pending')");
$stmt->bind_param("iisss", $patient_id, $doctor_id, $date, $time_with_seconds, $service);

if ($stmt->execute()) {
    $new_id = $conn->insert_id;

    // Send the patient a booking request confirmation email (non-fatal on failure)
    require_once __DIR__ . '/appointment_mailer.php';
    send_appointment_email($conn, $new_id, 'booked');

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'appointment_id' => $new_id]);
} else {
    echo "error: Failed to book appointment. Please try again.";
}

$stmt->close();
$conn->close();
?>