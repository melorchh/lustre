<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["patient_id"])) {
    echo "error: Please login first";
    exit;
}

include __DIR__ . '/../src/db.php';

$patient_id = $_SESSION["patient_id"];
$appointment_id = isset($_POST["appointment_id"]) ? intval($_POST["appointment_id"]) : 0;

if (empty($appointment_id)) {
    echo "error: Invalid appointment";
    $conn->close();
    exit;
}

// Verify appointment belongs to this patient and is cancellable (FIXED: check for pending or confirmed, not 'active')
$stmt = $conn->prepare("SELECT id FROM appointments WHERE id=? AND patient_id=? AND status IN ('pending', 'confirmed')");
$stmt->bind_param("ii", $appointment_id, $patient_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    echo "error: Appointment not found or already cancelled";
    $conn->close();
    exit;
}
$stmt->close();

// Cancel the appointment
$stmt = $conn->prepare("UPDATE appointments SET status='cancelled' WHERE id=?");
$stmt->bind_param("i", $appointment_id);

if ($stmt->execute()) {
    // Email the patient a cancellation confirmation (non-fatal on failure)
    require_once __DIR__ . '/appointment_mailer.php';
    send_appointment_email($conn, $appointment_id, 'cancelled');
    echo "success";
} else {
    echo "error: Failed to cancel appointment";
}

$stmt->close();
$conn->close();
?>