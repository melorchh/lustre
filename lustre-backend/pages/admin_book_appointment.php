<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { echo "error: Unauthorized"; exit; }

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/appointment_time_rules.php';
require_once __DIR__ . '/../src/unique_ids.php';
ensure_unique_ids($conn);
require_once __DIR__ . '/../src/time_blocks_schema.php';
require_once __DIR__ . '/../src/doctor_capacity.php';
ensure_time_blocks_schema($conn);
ensure_doctor_capacity_schema($conn);

$patient_id = isset($_POST["patient_id"]) ? intval($_POST["patient_id"]) : 0;
$doctor_id  = isset($_POST["doctor_id"])  ? intval($_POST["doctor_id"])  : 0;
$date       = isset($_POST["date"])  ? trim($_POST["date"]) : '';
$time       = isset($_POST["time"])  ? trim($_POST["time"]) : '';
$appointment_duration = 60; // 1 hour - FIXED DURATION (matches booking)

// Optional vitals captured with the booking
$vt_weight = isset($_POST["weight_kg"])      ? trim($_POST["weight_kg"])      : '';
$vt_height = isset($_POST["height_cm"])      ? trim($_POST["height_cm"])      : '';
$vt_bp     = isset($_POST["blood_pressure"]) ? trim($_POST["blood_pressure"]) : '';
$vt_hr     = isset($_POST["heart_rate"])     ? trim($_POST["heart_rate"])     : '';
$vt_fh     = isset($_POST["fundal_height"])  ? trim($_POST["fundal_height"])  : '';
$vt_notes  = isset($_POST["vitals_notes"])   ? trim($_POST["vitals_notes"])   : '';
$has_vitals = ($vt_weight !== '' || $vt_height !== '' || $vt_bp !== '' || $vt_hr !== '' || $vt_fh !== '' || $vt_notes !== '');

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

// Vitals validation (same rules as the per-appointment vitals form)
if ($has_vitals) {
    if ($vt_weight === '' && $vt_height === '' && $vt_bp === '' && $vt_hr === '' && $vt_fh === '') {
        echo "error: Enter at least one vital sign measurement, or clear the vitals section";
        $conn->close(); exit;
    }
    if ($vt_weight !== '' && (!is_numeric($vt_weight) || (float)$vt_weight <= 0)) {
        echo "error: Please provide a valid weight";
        $conn->close(); exit;
    }
    if ($vt_height !== '' && (!is_numeric($vt_height) || (float)$vt_height <= 0 || (float)$vt_height > 300)) {
        echo "error: Please enter a valid height (30-300 cm)";
        $conn->close(); exit;
    }
    if ($vt_bp !== '' && !preg_match('/^\d{2,3}\/\d{2,3}$/', $vt_bp)) {
        echo "error: Blood pressure must look like 110/70";
        $conn->close(); exit;
    }
    if ($vt_hr !== '' && (!is_numeric($vt_hr) || (int)$vt_hr < 0 || (int)$vt_hr > 250)) {
        echo "error: Heart rate must be 0-250 bpm";
        $conn->close(); exit;
    }
    if ($vt_fh !== '' && (!is_numeric($vt_fh) || (float)$vt_fh < 0 || (float)$vt_fh > 50)) {
        echo "error: Invalid fundal height";
        $conn->close(); exit;
    }
    $vt_w = $vt_weight !== '' ? (float)$vt_weight : null;
    $vt_h = $vt_height !== '' ? (float)$vt_height : null;
    $vt_b = $vt_bp !== ''     ? $vt_bp            : null;
    $vt_r = $vt_hr !== ''     ? (int)$vt_hr       : null;
    $vt_f = $vt_fh !== ''     ? (float)$vt_fh     : null;
    $vt_n = $vt_notes !== ''  ? $vt_notes         : null;
}

$time_with_seconds = $time . ":00";
$start_timestamp = strtotime($date . ' ' . $time);
$end_timestamp   = $start_timestamp + ($appointment_duration * 60);
$end_time        = date('H:i:s', $end_timestamp);
if (appointment_time_overlaps_lunch_break($time, $appointment_duration)) {
    echo "error: Appointments are unavailable from 12:00 PM to 1:00 PM";
    $conn->close(); exit;
}

// Overlap check (same doctor, same day, pending/confirmed)
$stmt = $conn->prepare("
    SELECT id FROM appointments
    WHERE doctor_id=? AND appointment_date=?
      AND status IN ('pending','confirmed')
      AND appointment_time < ?
      AND appointment_time + INTERVAL '1 hour' > ?
");
$duration_sql = NULL;
$stmt->bind_param("isss", $doctor_id, $date, $end_time, $time_with_seconds);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo "error: This time slot / overlapping 1-hour period is already booked for this doctor";
    $conn->close(); exit;
}
$stmt->close();

// Within doctor's schedule
$day_of_week = date('l', strtotime($date));
$stmt = $conn->prepare("
    SELECT id FROM doctor_schedules
    WHERE doctor_id=? AND day_of_week=? AND ? >= start_time AND ? <= end_time AND is_active=1
");
$stmt->bind_param("isss", $doctor_id, $day_of_week, $time_with_seconds, $end_time);
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
    WHERE doctor_id=? AND ((block_type='date' AND block_date=?) OR (block_type='week' AND ? BETWEEN block_date AND end_date) OR (block_type='weekly' AND day_of_week=?))
      AND ? < end_time AND ? > start_time
");
$stmt->bind_param("isssss", $doctor_id, $date, $date, $day_of_week, $end_time, $time_with_seconds);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo "error: This time is not available (the doctor has time off)";
    $conn->close(); exit;
}
$stmt->close();

// Enforce the daily cap under a doctor-row lock so simultaneous bookings cannot
// push the doctor over the configured patient limit.
$pdo = $conn->getPdo();
if (!$pdo->beginTransaction()) {
    http_response_code(500);
    echo "error: Could not start appointment booking. Please try again.";
    $conn->close(); exit;
}
$patient_lock = $conn->prepare("SELECT id FROM patients WHERE id = ? FOR UPDATE");
$patient_lock->bind_param("i", $patient_id);
if (!$patient_lock->execute()) {
    $patient_lock->close();
    $pdo->rollBack();
    http_response_code(500);
    echo "error: Could not verify the patient account. Please try again.";
    $conn->close(); exit;
}
$patient_lock->store_result();
if ($patient_lock->num_rows === 0) {
    $patient_lock->close();
    $pdo->rollBack();
    echo "error: Invalid patient";
    $conn->close(); exit;
}
$patient_lock->close();

$daily_limit = doctor_daily_limit_status($conn, $doctor_id, $date, true);
if ($daily_limit === null) {
    $pdo->rollBack();
    http_response_code(500);
    echo "error: Could not verify the doctor's daily patient limit. Please try again.";
    $conn->close(); exit;
}
if (!$daily_limit['available']) {
    $pdo->rollBack();
    echo "error: This doctor has reached the daily patient limit for the selected date";
    $conn->close(); exit;
}
$slot_available = doctor_appointment_slot_is_available($conn, $doctor_id, $date, $time);
if ($slot_available === null) {
    $pdo->rollBack();
    http_response_code(500);
    echo "error: Could not verify the selected appointment time. Please try again.";
    $conn->close(); exit;
}
if (!$slot_available) {
    $pdo->rollBack();
    echo "error: This time slot / overlapping 1-hour period is already booked for this doctor";
    $conn->close(); exit;
}

// Insert
$stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, appointment_duration, status, payment_status) VALUES (?, ?, ?, ?, ?, 'pending','pending')");
$stmt->bind_param("iissi", $patient_id, $doctor_id, $date, $time_with_seconds, $appointment_duration);
if ($stmt->execute()) {
    $new_id = $stmt->insert_id;

    // Save vitals tied to this appointment (visit date = appointment date)
    if ($has_vitals) {
        $vt = $conn->prepare("INSERT INTO vitals (patient_id, appointment_id, visit_date, weight_kg, height_cm, blood_pressure, heart_rate, fundal_height, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $vt->bind_param("iisddsids", $patient_id, $new_id, $date, $vt_w, $vt_h, $vt_b, $vt_r, $vt_f, $vt_n);
        if (!$vt->execute()) {
            error_log('[lustre] vitals insert failed for appointment ' . $new_id . ': ' . $vt->error);
        }
        $vt->close();

        // Keep the patient profile in sync with the latest measurements.
        if ($vt_w !== null && $vt_h !== null) {
            $upd = $conn->prepare("UPDATE patients SET weight_kg = ?, height_cm = ? WHERE id = ?");
            $upd->bind_param("ddi", $vt_w, $vt_h, $patient_id);
        } elseif ($vt_w !== null) {
            $upd = $conn->prepare("UPDATE patients SET weight_kg = ? WHERE id = ?");
            $upd->bind_param("di", $vt_w, $patient_id);
        } elseif ($vt_h !== null) {
            $upd = $conn->prepare("UPDATE patients SET height_cm = ? WHERE id = ?");
            $upd->bind_param("di", $vt_h, $patient_id);
        } else {
            $upd = null;
        }
        if ($upd) { $upd->execute(); $upd->close(); }
    }

    if (!$pdo->commit()) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo "error: Failed to save the appointment";
        $stmt->close();
        $conn->close(); exit;
    }

    require_once __DIR__ . '/appointment_mailer.php';
    send_appointment_email($conn, $new_id, 'booked');
    echo "success:" . $new_id;
} else {
    $pdo->rollBack();
    echo "error: Failed to book appointment";
}
$stmt->close();
$conn->close();
