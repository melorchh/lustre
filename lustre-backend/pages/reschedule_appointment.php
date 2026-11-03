<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["patient_id"])) {
    echo "error: Please login first";
    exit;
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/appointment_time_rules.php';
require_once __DIR__ . '/../src/appointment_slots.php';
require_once __DIR__ . '/../src/time_blocks_schema.php';
require_once __DIR__ . '/../src/doctor_capacity.php';
ensure_time_blocks_schema($conn);
ensure_doctor_capacity_schema($conn);

$patient_id = $_SESSION["patient_id"];
$appointment_id = isset($_POST["appointment_id"]) ? intval($_POST["appointment_id"]) : 0;
$doctor_id = isset($_POST["doctor_id"]) ? intval($_POST["doctor_id"]) : 0;
$date = isset($_POST["date"]) ? trim($_POST["date"]) : '';
$time = isset($_POST["time"]) ? trim($_POST["time"]) : '';
$appointment_duration = APPOINTMENT_SLOT_MINUTES; // one slot per appointment

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

// The new slot must be inside the doctor's first 4 available hours and still
// have room for another patient. The moved appointment is excluded.
$slot_error = lustre_appt_validate_slot($conn, $doctor_id, $date, $time, $appointment_id);
if ($slot_error !== null) {
    echo "error: " . $slot_error;
    $conn->close();
    exit;
}

// Preserve the daily patient cap when moving an appointment. The current
// appointment is excluded from the target-date count.
$pdo = $conn->getPdo();
if (!$pdo->beginTransaction()) {
    http_response_code(500);
    echo "error: Could not start rescheduling. Please try again.";
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

// Re-lock and revalidate the appointment so a concurrent cancel or reschedule
// cannot make this request overwrite newer appointment state.
$locked_appointment = $conn->prepare("SELECT doctor_id, appointment_date, appointment_time FROM appointments WHERE id = ? AND patient_id = ? AND status IN ('pending', 'confirmed') FOR UPDATE");
$locked_appointment->bind_param("ii", $appointment_id, $patient_id);
if (!$locked_appointment->execute()) {
    $locked_appointment->close();
    $pdo->rollBack();
    http_response_code(500);
    echo "error: Could not verify the appointment. Please try again.";
    $conn->close();
    exit;
}
$locked_appointment->store_result();
if ($locked_appointment->num_rows === 0) {
    $locked_appointment->close();
    $pdo->rollBack();
    echo "error: Appointment not found or cannot be rescheduled";
    $conn->close();
    exit;
}
$locked_appointment->bind_result($existing_doctor_id, $old_date, $old_time);
$locked_appointment->fetch();
$locked_appointment->close();

$daily_limit = doctor_daily_limit_status($conn, $doctor_id, $date, true, $appointment_id);
if ($daily_limit === null) {
    $pdo->rollBack();
    http_response_code(500);
    echo "error: Could not verify the doctor's daily patient limit. Please try again.";
    $conn->close();
    exit;
}
if (!$daily_limit['available']) {
    $pdo->rollBack();
    echo "error: This doctor has reached the daily patient limit for the selected date";
    $conn->close();
    exit;
}
$slot_error = lustre_appt_validate_slot($conn, $doctor_id, $date, $time, $appointment_id);
if ($slot_error !== null) {
    $pdo->rollBack();
    echo "error: " . $slot_error;
    $conn->close();
    exit;
}

// Update the appointment with the new date/time, reset status to pending, and mark it as rescheduled
$stmt = $conn->prepare("UPDATE appointments SET doctor_id=?, appointment_date=?, appointment_time=?, appointment_duration=?, status='pending', reschedule_count = reschedule_count + 1, last_rescheduled_at = NOW() WHERE id=? AND patient_id=? AND status IN ('pending', 'confirmed')");
$stmt->bind_param("issiii", $doctor_id, $date, $time_with_seconds, $appointment_duration, $appointment_id, $patient_id);

if ($stmt->execute() && $stmt->affected_rows >= 0) {
    // Log the reschedule into history so admins can see old -> new details
    $hist = $conn->prepare("INSERT INTO reschedule_history (appointment_id, old_date, old_time, new_date, new_time, rescheduled_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $hist->bind_param("issss", $appointment_id, $old_date, $old_time, $date, $time_with_seconds);
    $hist->execute();
    $hist->close();

    if (!$pdo->commit()) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo "error: Failed to save the rescheduled appointment. Please try again.";
        $stmt->close();
        $conn->close();
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'appointment_id' => $appointment_id]);

    // Push the response to the client before the slow SMTP send runs, so a
    // sluggish mail server can never stall (or appear to stall) the request.
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }

    // Email the patient a reschedule confirmation (non-fatal on failure).
    // Buffered so any warnings/output the mailer produces are discarded and
    // can never be appended to the JSON response above.
    ob_start();
    try {
        require_once __DIR__ . '/appointment_mailer.php';
        send_appointment_email($conn, $appointment_id, 'rescheduled');
    } catch (Throwable $e) {
        error_log('Reschedule email failed (appt=' . $appointment_id . '): ' . $e->getMessage());
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
} else {
    $pdo->rollBack();
    echo "error: Failed to reschedule appointment. Please try again.";
}

$stmt->close();
$conn->close();
?>
