<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["patient_id"])) {
    echo "error: Please login first";
    exit;
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/appointment_time_rules.php';
require_once __DIR__ . '/../src/appointment_slots.php';
require_once __DIR__ . '/../src/unique_ids.php';
ensure_unique_ids($conn);
require_once __DIR__ . '/../src/time_blocks_schema.php';
require_once __DIR__ . '/../src/doctor_capacity.php';
ensure_time_blocks_schema($conn);
ensure_doctor_capacity_schema($conn);

$patient_id = $_SESSION["patient_id"];
$doctor_id = isset($_POST["doctor_id"]) ? intval($_POST["doctor_id"]) : 0;
$date = isset($_POST["date"]) ? trim($_POST["date"]) : '';
$time = isset($_POST["time"]) ? trim($_POST["time"]) : '';
$service = isset($_POST["service"]) ? trim($_POST["service"]) : '';
$appointment_duration = APPOINTMENT_SLOT_MINUTES; // one slot per appointment

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

// Require appointments to be booked at least one day in advance.
$selected_date = strtotime($date);
$today = strtotime(date('Y-m-d'));
if ($selected_date === false || date('Y-m-d', $selected_date) !== $date) {
    echo "error: Invalid appointment date";
    $conn->close();
    exit;
}
if ($selected_date <= $today) {
    echo "error: Appointments must be booked at least one day in advance";
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

// The selected slot must be inside the doctor's first 4 available hours and
// still have room for another patient.
$slot_error = lustre_appt_validate_slot($conn, $doctor_id, $date, $time);
if ($slot_error !== null) {
    echo "error: " . $slot_error;
    $conn->close();
    exit;
}

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

// Save appointment inside its 1-hour slot.
$pdo = $conn->getPdo();
if (!$pdo->beginTransaction()) {
    http_response_code(500);
    echo "error: Could not start appointment booking. Please try again.";
    $conn->close();
    exit;
}
$patient_lock = $conn->prepare("SELECT id FROM patients WHERE id = ? FOR UPDATE");
$patient_lock->bind_param("i", $patient_id);
if (!$patient_lock->execute()) {
    $patient_lock->close();
    $pdo->rollBack();
    http_response_code(500);
    echo "error: Could not verify the patient account. Please try again.";
    $conn->close();
    exit;
}
$patient_lock->store_result();
if ($patient_lock->num_rows === 0) {
    $patient_lock->close();
    $pdo->rollBack();
    echo "error: Invalid patient. Please login again.";
    $conn->close();
    exit;
}
$patient_lock->close();

// Recheck after locking the patient row so parallel bookings for different
// doctors cannot both pass the one-active-appointment rule.
$active = $conn->prepare("SELECT id FROM appointments WHERE patient_id = ? AND status IN ('pending', 'confirmed') AND appointment_date >= CURRENT_DATE LIMIT 1");
$active->bind_param("i", $patient_id);
if (!$active->execute()) {
    $active->close();
    $pdo->rollBack();
    http_response_code(500);
    echo "error: Could not verify your active appointments. Please try again.";
    $conn->close();
    exit;
}
$active->store_result();
if ($active->num_rows > 0) {
    $active->close();
    $pdo->rollBack();
    echo "error: You already have an active appointment. Please cancel or complete it first.";
    $conn->close();
    exit;
}
$active->close();

$daily_limit = doctor_daily_limit_status($conn, $doctor_id, $date, true);
if ($daily_limit === null) {
    $pdo->rollBack();
    http_response_code(500);
    echo "error: Could not verify the doctor's daily patient limit. Please try again.";
    $conn->close();
    exit;
}
if (!$daily_limit['available']) {
    $pdo->rollBack();
    echo "error: This doctor has reached the daily patient limit for the selected date. Please choose another date.";
    $conn->close();
    exit;
}
$slot_error = lustre_appt_validate_slot($conn, $doctor_id, $date, $time);
if ($slot_error !== null) {
    $pdo->rollBack();
    echo "error: " . $slot_error;
    $conn->close();
    exit;
}

$stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, appointment_duration, service_requested, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending')");
$stmt->bind_param("iissis", $patient_id, $doctor_id, $date, $time_with_seconds, $appointment_duration, $service);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    if (!$pdo->commit()) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo "error: Failed to save the appointment. Please try again.";
        $stmt->close();
        $conn->close();
        exit;
    }

    // Send the patient a booking request confirmation email (non-fatal on failure)
    require_once __DIR__ . '/appointment_mailer.php';
    send_appointment_email($conn, $new_id, 'booked');

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'appointment_id' => $new_id]);
} else {
    $pdo->rollBack();
    echo "error: Failed to book appointment. Please try again.";
}

$stmt->close();
$conn->close();
?>