<?php
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/appointment_reschedule_tokens.php';

$email_token = isset($_POST['token']) ? trim($_POST['token']) : '';
$email_mode = $email_token !== '';
if (!isset($_SESSION["patient_id"]) && !$email_mode) {
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

$patient_id = isset($_SESSION["patient_id"]) ? (int)$_SESSION["patient_id"] : 0;
$token_row = $email_mode ? find_appointment_reschedule_token($conn, $email_token) : null;
$appointment_id = $email_mode
    ? ($token_row ? $token_row['appointment_id'] : 0)
    : (isset($_POST["appointment_id"]) ? intval($_POST["appointment_id"]) : 0);
$doctor_id = $email_mode ? 0 : (isset($_POST["doctor_id"]) ? intval($_POST["doctor_id"]) : 0);
$date = isset($_POST["date"]) ? trim($_POST["date"]) : '';
$time = isset($_POST["time"]) ? trim($_POST["time"]) : '';
$appointment_duration = APPOINTMENT_SLOT_MINUTES; // one slot per appointment

if ($email_mode && !$token_row) {
    http_response_code(410);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'This rescheduling link has expired or was already used. Please contact the clinic.']);
    $conn->close();
    exit;
}

if (empty($appointment_id) || empty($date) || empty($time)) {
    echo "error: Please fill all fields";
    $conn->close();
    exit;
}

// Load the existing appointment — must belong to this patient and be reschedulable
$appointment_sql = $email_mode
    ? "SELECT patient_id, doctor_id, appointment_date, appointment_time, status, reschedule_count FROM appointments WHERE id=? AND status IN ('pending','confirmed','cancelled')"
    : "SELECT patient_id, doctor_id, appointment_date, appointment_time, status, reschedule_count FROM appointments WHERE id=? AND patient_id=? AND status IN ('pending','confirmed','cancelled')";
$stmt = $conn->prepare($appointment_sql);
if ($email_mode) {
    $stmt->bind_param("i", $appointment_id);
} else {
    $stmt->bind_param("ii", $appointment_id, $patient_id);
}
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    echo "error: Appointment not found or cannot be rescheduled";
    $conn->close();
    exit;
}
$stmt->bind_result($appointment_patient_id, $existing_doctor_id, $old_date, $old_time, $existing_status, $reschedule_count);
$stmt->fetch();
$stmt->close();
$patient_id = (int)$appointment_patient_id;

if ($existing_status === 'cancelled' && (int)$reschedule_count > 0) {
    echo "error: This cancelled appointment has already been rescheduled";
    $conn->close();
    exit;
}

if (strtotime($old_date . ' ' . $old_time) < time()) {
    echo "error: This appointment time has passed. Please reschedule your current appointment instead.";
    $conn->close();
    exit;
}

if ($existing_status === 'cancelled' && $date === $old_date) {
    echo "error: Please choose a different date from the cancelled appointment";
    $conn->close();
    exit;
}

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
if ($selected_date === false || date('Y-m-d', $selected_date) !== $date || $selected_date <= $today) {
    echo "error: Please choose a valid date at least one day in advance";
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
$locked_appointment = $conn->prepare("SELECT doctor_id, appointment_date, appointment_time, status, reschedule_count FROM appointments WHERE id = ? AND patient_id = ? AND status IN ('pending', 'confirmed', 'cancelled') FOR UPDATE");
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
$locked_appointment->bind_result($existing_doctor_id, $old_date, $old_time, $existing_status, $reschedule_count);
$locked_appointment->fetch();
$locked_appointment->close();
if ($email_mode) {
    $doctor_id = (int)$existing_doctor_id;
    $consume = $conn->prepare("UPDATE appointment_reschedule_tokens SET used = 1 WHERE token_hash = ? AND appointment_id = ? AND used = 0 AND expires_at > NOW()");
    $consume->bind_param('si', $token_row['token_hash'], $appointment_id);
    $consume_ok = $consume->execute();
    $token_consumed = $consume->affected_rows === 1;
    $consume_error = $consume->error;
    $consume->close();
    if (!$consume_ok || !$token_consumed) {
        if (!$consume_ok) {
            error_log('Unable to consume appointment reschedule link: ' . $consume_error);
        }
        $pdo->rollBack();
        http_response_code($consume_ok ? 410 : 500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $consume_ok
                ? 'This rescheduling link has expired or was already used. Please contact the clinic.'
                : 'Unable to submit your request. Please try again.',
        ]);
        $conn->close();
        exit;
    }
}

if ($existing_status === 'cancelled' && (int)$reschedule_count > 0) {
    $pdo->rollBack();
    echo "error: This cancelled appointment has already been rescheduled";
    $conn->close();
    exit;
}

if (strtotime($old_date . ' ' . $old_time) < time()) {
    $pdo->rollBack();
    echo "error: This appointment time has passed. Please reschedule your current appointment instead.";
    $conn->close();
    exit;
}

if ($existing_status === 'cancelled' && $date === $old_date) {
    $pdo->rollBack();
    echo "error: Please choose a different date from the cancelled appointment";
    $conn->close();
    exit;
}

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
$stmt = $conn->prepare("UPDATE appointments SET doctor_id=?, appointment_date=?, appointment_time=?, appointment_duration=?, status='pending', reschedule_count = reschedule_count + 1, last_rescheduled_at = NOW() WHERE id=? AND patient_id=? AND status IN ('pending', 'confirmed', 'cancelled')");
$stmt->bind_param("issiii", $doctor_id, $date, $time_with_seconds, $appointment_duration, $appointment_id, $patient_id);

if ($stmt->execute() && $stmt->affected_rows >= 0) {
    // Log the reschedule into history so admins can see old -> new details
    $hist = $conn->prepare("INSERT INTO reschedule_history (appointment_id, old_date, old_time, new_date, new_time, rescheduled_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $hist->bind_param("issss", $appointment_id, $old_date, $old_time, $date, $time_with_seconds);
    $history_saved = $hist->execute();
    $history_error = $hist->error;
    $hist->close();

    if ($email_mode && !$history_saved) {
        error_log('Unable to save reschedule history for appointment ' . $appointment_id . ': ' . $history_error);
        $pdo->rollBack();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unable to save your rescheduling request. Please try again.']);
        $stmt->close();
        $conn->close();
        exit;
    }

    if ($email_mode) {
        $invalidate = $conn->prepare("UPDATE appointment_reschedule_tokens SET used = 1 WHERE appointment_id = ? AND used = 0");
        $invalidate->bind_param('i', $appointment_id);
        $invalidate_ok = $invalidate->execute();
        $invalidate_error = $invalidate->error;
        $invalidate->close();
        if (!$invalidate_ok) {
            error_log('Unable to invalidate appointment reschedule links: ' . $invalidate_error);
            $pdo->rollBack();
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Unable to submit your request. Please try again.']);
            $stmt->close();
            $conn->close();
            exit;
        }
    }

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

    // Notify the patient by email and SMS (non-fatal on notification failure).
    // Buffered so any warnings/output the mailer produces are discarded and
    // can never be appended to the JSON response above.
    ob_start();
    try {
        require_once __DIR__ . '/appointment_mailer.php';
        send_appointment_notification($conn, $appointment_id, 'rescheduled');
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
