<?php
require __DIR__ . '/../toddcare-backend/src/session.php';

if (!isset($_SESSION["patient_id"])) {
    echo "error: Please login first";
    exit;
}

include __DIR__ . '/../toddcare-backend/src/db.php';

$patient_id     = $_SESSION["patient_id"];
$doctor_id      = isset($_POST['doctor_id'])      ? intval($_POST['doctor_id'])            : 0;
$test_type      = isset($_POST['test_type'])      ? trim($_POST['test_type'])               : '';
$priority       = isset($_POST['priority'])       ? trim($_POST['priority'])                : 'normal';
$scheduled_date = isset($_POST['scheduled_date']) ? trim($_POST['scheduled_date'])          : null;
$notes          = isset($_POST['notes'])          ? trim($_POST['notes'])                   : '';

if (empty($doctor_id) || empty($test_type)) {
    echo "error: Doctor and test type are required";
    $conn->close();
    exit;
}

$valid_priorities = ['normal', 'urgent', 'stat'];
if (!in_array($priority, $valid_priorities)) {
    $priority = 'normal';
}

// Validate patient exists
$chk = $conn->prepare("SELECT id FROM patients WHERE id = ?");
$chk->bind_param("i", $patient_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    echo "error: Patient not found. Please login again.";
    $chk->close();
    $conn->close();
    exit;
}
$chk->close();

// Validate doctor exists
$chk2 = $conn->prepare("SELECT id FROM doctors WHERE id = ?");
$chk2->bind_param("i", $doctor_id);
$chk2->execute();
$chk2->store_result();
if ($chk2->num_rows === 0) {
    echo "error: Doctor not found";
    $chk2->close();
    $conn->close();
    exit;
}
$chk2->close();

$scheduled_date_val = (!empty($scheduled_date)) ? $scheduled_date : null;

$stmt = $conn->prepare("
    INSERT INTO lab_tests 
        (patient_id, doctor_id, test_type, priority, scheduled_date, notes, status, result)
    VALUES (?, ?, ?, ?, ?, ?, 'pending', NULL)
");
$stmt->bind_param("iissss",
    $patient_id, $doctor_id, $test_type, $priority, $scheduled_date_val, $notes
);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error: Failed to submit lab request â€” " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
