<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_id"])) { echo "Unauthorized"; exit; }
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/appointment_slots.php';
require_once __DIR__ . '/../src/doctor_capacity.php';
require_once __DIR__ . '/../src/time_blocks_schema.php';

$doctor_id = (int)$_SESSION['doctor_id'];
$appt_id   = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$status    = isset($_POST['status']) ? trim($_POST['status']) : '';
$suggested_date = isset($_POST['suggested_date']) ? trim($_POST['suggested_date']) : '';
$suggested_time = isset($_POST['suggested_time']) ? trim($_POST['suggested_time']) : '';

$allowed = ['pending','confirmed','completed','cancelled'];
if ($appt_id <= 0 || !in_array($status, $allowed, true)) {
    echo "Invalid request";
    $conn->close();
    exit;
}

// Verify the appointment belongs to this doctor and is still actionable.
$chk = $conn->prepare("SELECT id, status FROM appointments WHERE id = ? AND doctor_id = ?");
$chk->bind_param("ii", $appt_id, $doctor_id);
$chk->execute();
$chk->bind_result($owned_appointment_id, $current_status);
if (!$chk->fetch()) {
    $chk->close();
    echo "Not allowed";
    $conn->close();
    exit;
}
$chk->close();

if ($status === 'cancelled') {
    if (!in_array($current_status, ['pending', 'confirmed'], true)) {
        echo "Appointment is no longer cancellable";
        $conn->close();
        exit;
    }
    if (($suggested_date === '') !== ($suggested_time === '')) {
        echo "Choose both a suggested date and time, or leave both blank";
        $conn->close();
        exit;
    }
    if ($suggested_date !== '') {
        $date_timestamp = strtotime($suggested_date);
        if ($date_timestamp === false || date('Y-m-d', $date_timestamp) !== $suggested_date || $suggested_date <= date('Y-m-d')) {
            echo "Suggested date must be a valid future date";
            $conn->close();
            exit;
        }
        if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $suggested_time)) {
            echo "Invalid suggested time";
            $conn->close();
            exit;
        }
        ensure_time_blocks_schema($conn);
        ensure_doctor_capacity_schema($conn);
        $daily_limit = doctor_daily_limit_status($conn, $doctor_id, $suggested_date, false, $appt_id);
        if ($daily_limit === null) {
            http_response_code(500);
            echo "Could not verify the suggested date";
            $conn->close();
            exit;
        }
        if (!$daily_limit['available']) {
            echo "The doctor has reached the daily patient limit for that date";
            $conn->close();
            exit;
        }
        $slot_error = lustre_appt_validate_slot($conn, $doctor_id, $suggested_date, $suggested_time, $appt_id);
        if ($slot_error !== null) {
            echo $slot_error;
            $conn->close();
            exit;
        }
    }
}

$update_sql = $status === 'cancelled'
    ? "UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ? AND status IN ('pending', 'confirmed')"
    : "UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param("sii", $status, $appt_id, $doctor_id);
if ($stmt->execute()) {
    if (in_array($status, ['confirmed', 'cancelled'], true) && $stmt->affected_rows > 0) {
        require_once __DIR__ . '/appointment_mailer.php';
        send_appointment_notification(
            $conn,
            $appt_id,
            $status,
            $status === 'cancelled' ? ($suggested_date ?: null) : null,
            $status === 'cancelled' ? ($suggested_time ?: null) : null
        );
    }
    if ($status === 'cancelled' && $stmt->affected_rows === 0) {
        echo "Appointment is no longer cancellable";
    } else {
        echo "success";
    }
} else {
    echo "Failed";
}
$stmt->close();
$conn->close();
