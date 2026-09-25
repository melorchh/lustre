<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
if (!isset($_SESSION["patient_id"])) {
    echo "error: Please login first";
    exit;
}

include __DIR__ . '/../toddcare-backend/src/db.php';

$patient_id = $_SESSION["patient_id"];
$appointment_id = isset($_POST["appointment_id"]) ? intval($_POST["appointment_id"]) : 0;
$doctor_id = isset($_POST["doctor_id"]) ? intval($_POST["doctor_id"]) : 0;
$date = isset($_POST["date"]) ? trim($_POST["date"]) : '';
$time = isset($_POST["time"]) ? trim($_POST["time"]) : '';
$appointment_duration = 120; // 2 hours in minutes - FIXED DURATION

if (empty($appointment_id) || empty($date) || empty($time)) {
    echo "error: Please fill all fields";
    $conn->close();
    exit;
}

// Load the existing appointment â€” must belong to this patient and be reschedulable
$stmt = $conn->prepare("SELECT doctor_id, appointment_date, appointment_time FROM appointments WHERE id=? AND patient_id=? AND status IN ('pending','confirmed')");
$stmt->bind_param("ii", $appointment_id, $patient_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    echo "error: Appointment not found or cannot be rescheduled";
    $conn->close();
    exit;
}
$stmt->bind_result($existing_doctor_id, $old_date, $old_time);
$stmt->fetch();
$stmt->close();

if (empty($doctor_id)) {
    $doctor_id = $existing_doctor_id;
}

// Validate that doctor exists
$stmt = $conn->prepare("SELECT id FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    echo "error: Invalid doctor selected";
    $conn->close();
    exit;
}
$stmt->close();

// Validate date is not in the past
$selected_date = strtotime($date);
$today = strtotime(date('Y-m-d'));
if ($selected_date < $today) {
    echo "error: Cannot schedule appointments in the past";
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
$end_timestamp = $start_timestamp + (120 * 60);
$end_time = date('H:i:s', $end_timestamp);

// Check if the new slot is already booked (excluding this same appointment id)
$stmt = $conn->prepare("
    SELECT id FROM appointments 
    WHERE doctor_id=? 
    AND appointment_date=? 
    AND status IN ('pending', 'confirmed')
    AND id <> ?
    AND appointment_time < ?
    AND appointment_time + INTERVAL '2 hours' > ?
");
$stmt->bind_param("iisss", $doctor_id, $date, $appointment_id, $end_time, $time_with_seconds);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo "error: This time slot or overlapping 2-hour period is already booked";
    $conn->close();
    exit;
}
$stmt->close();

// Verify the selected time is within doctor's schedule
$day_of_week = date('l', strtotime($date));

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

// Verify the selected time is not inside an unavailable time block
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

// Update the appointment with the new date/time, reset status to pending, and mark it as rescheduled
$stmt = $conn->prepare("UPDATE appointments SET doctor_id=?, appointment_date=?, appointment_time=?, status='pending', reschedule_count = reschedule_count + 1, last_rescheduled_at = NOW() WHERE id=? AND patient_id=?");
$stmt->bind_param("issii", $doctor_id, $date, $time_with_seconds, $appointment_id, $patient_id);

if ($stmt->execute() && $stmt->affected_rows >= 0) {
    // Log the reschedule into history so admins can see old -> new details
    $hist = $conn->prepare("INSERT INTO reschedule_history (appointment_id, old_date, old_time, new_date, new_time, rescheduled_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $hist->bind_param("issss", $appointment_id, $old_date, $old_time, $date, $time_with_seconds);
    $hist->execute();
    $hist->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'appointment_id' => $appointment_id]);

    // Email the patient a reschedule confirmation (non-fatal on failure)
    require_once __DIR__ . '/appointment_mailer.php';
    send_appointment_email($conn, $appointment_id, 'rescheduled');
} else {
    echo "error: Failed to reschedule appointment. Please try again.";
}

$stmt->close();
$conn->close();
?>
