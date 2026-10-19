<?php
/**
 * Emails a completed appointment's result to the patient.
 *
 * Used from the client profile (admin and doctor portals). Doctors may only
 * send results for appointments assigned to them; admins may send any. The
 * appointment must be completed and have a non-empty result.
 */
require __DIR__ . '/../src/session.php';

header('Content-Type: text/plain; charset=utf-8');

$is_admin  = isset($_SESSION['admin_id']);
$is_doctor = isset($_SESSION['doctor_id']);

if (!$is_admin && !$is_doctor) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/appointment_mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'Invalid request';
    exit;
}

$appt_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
if ($appt_id <= 0) {
    echo 'Invalid appointment';
    $conn->close();
    exit;
}

$stmt = $conn->prepare("SELECT doctor_id, status, result FROM appointments WHERE id = ?");
$stmt->bind_param("i", $appt_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appt) {
    echo 'Appointment not found';
    $conn->close();
    exit;
}

if ($is_doctor && !$is_admin && (int)$appt['doctor_id'] !== (int)$_SESSION['doctor_id']) {
    echo 'You are not allowed to send this result';
    $conn->close();
    exit;
}

if ($appt['status'] !== 'completed') {
    echo 'The appointment must be completed before the result can be sent';
    $conn->close();
    exit;
}

if (trim((string)$appt['result']) === '') {
    echo 'No result has been recorded for this appointment yet';
    $conn->close();
    exit;
}

$ok = send_appointment_email($conn, $appt_id, 'result');
echo $ok ? 'success' : 'Could not send the email. Please try again.';
$conn->close();
