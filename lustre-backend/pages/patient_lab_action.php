<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["patient_id"])) {
    echo "error: Please login first";
    exit;
}

require_once __DIR__ . '/../src/db.php';

$patient_id     = $_SESSION["patient_id"];
$doctor_id      = isset($_POST['doctor_id'])      ? intval($_POST['doctor_id'])            : 0;
$test_type      = isset($_POST['test_type'])      ? $_POST['test_type']                     : '';
$priority       = isset($_POST['priority'])       ? trim($_POST['priority'])                : 'normal';
$scheduled_date = isset($_POST['scheduled_date']) ? trim($_POST['scheduled_date'])          : null;
$notes          = isset($_POST['notes'])          ? trim($_POST['notes'])                   : '';

$test_types = is_array($test_type) ? $test_type : array($test_type);
$test_types = array_values(array_unique(array_filter(array_map(function ($test) {
    return is_string($test) ? trim($test) : '';
}, $test_types), function ($test) {
    return $test !== '';
})));

if (empty($doctor_id) || empty($test_types)) {
    echo "error: Doctor and at least one test type are required";
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

$pdo = $conn->getPdo();
if (!$pdo->beginTransaction()) {
    http_response_code(500);
    echo "error: Could not submit the lab request. Please try again.";
    $conn->close();
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO lab_tests 
        (patient_id, doctor_id, test_type, priority, scheduled_date, notes, status, result)
    VALUES (?, ?, ?, ?, ?, ?, 'pending', NULL)
");
if (!$stmt) {
    $pdo->rollBack();
    http_response_code(500);
    echo "error: Could not prepare the lab request. Please try again.";
    $conn->close();
    exit;
}
$inserted = true;
foreach ($test_types as $test) {
    $stmt->bind_param("iissss",
        $patient_id, $doctor_id, $test, $priority, $scheduled_date_val, $notes
    );
    if (!$stmt->execute()) {
        $inserted = false;
        break;
    }
}
$stmt->close();

if ($inserted && $pdo->commit()) {
    echo "success";
} else {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo "error: Failed to submit the complete lab request. Please try again.";
}

$conn->close();
?>
