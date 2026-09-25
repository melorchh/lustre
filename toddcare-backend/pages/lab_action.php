<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["admin_id"])) {
    echo "error: Unauthorized access";
    exit;
}

include __DIR__ . '/../src/db.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   ADD â€” Insert new lab test request
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
if ($action === 'add') {
    $patient_id     = isset($_POST['patient_id'])     ? intval($_POST['patient_id'])           : 0;
    $doctor_id      = isset($_POST['doctor_id'])      ? intval($_POST['doctor_id'])             : 0;
    $test_type      = isset($_POST['test_type'])      ? trim($_POST['test_type'])               : '';
    $priority       = isset($_POST['priority'])       ? trim($_POST['priority'])                : 'normal';
    $scheduled_date = isset($_POST['scheduled_date']) ? trim($_POST['scheduled_date'])          : null;
    $notes          = isset($_POST['notes'])          ? trim($_POST['notes'])                   : '';

    if (empty($patient_id) || empty($doctor_id) || empty($test_type)) {
        echo "error: Patient, doctor, and test type are required";
        $conn->close(); exit;
    }

    $valid_priorities = ['normal', 'urgent', 'stat'];
    if (!in_array($priority, $valid_priorities)) {
        echo "error: Invalid priority level";
        $conn->close(); exit;
    }

    // Validate patient & doctor exist
    $chk = $conn->prepare("SELECT id FROM patients WHERE id = ?");
    $chk->bind_param("i", $patient_id);
    $chk->execute(); $chk->store_result();
    if ($chk->num_rows === 0) { echo "error: Patient not found"; $chk->close(); $conn->close(); exit; }
    $chk->close();

    $chk2 = $conn->prepare("SELECT id FROM doctors WHERE id = ?");
    $chk2->bind_param("i", $doctor_id);
    $chk2->execute(); $chk2->store_result();
    if ($chk2->num_rows === 0) { echo "error: Doctor not found"; $chk2->close(); $conn->close(); exit; }
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
        echo "error: Failed to add lab test â€” " . $stmt->error;
    }
    $stmt->close();

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   UPDATE STATUS â€” inline status dropdown
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
} elseif ($action === 'update_status') {
    $lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : 0;
    $status = isset($_POST['status']) ? trim($_POST['status'])   : '';

    if (empty($lab_id)) { echo "error: Invalid lab test ID"; $conn->close(); exit; }

    $valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        echo "error: Invalid status";
        $conn->close(); exit;
    }

    $stmt = $conn->prepare("UPDATE lab_tests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $lab_id);
    echo $stmt->execute() ? "success" : "error: Failed to update status";
    $stmt->close();

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   SAVE RESULT â€” enter/edit result text
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
} elseif ($action === 'save_result') {
    $lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : 0;
    $result = isset($_POST['result']) ? trim($_POST['result'])   : '';
    $notes  = isset($_POST['notes'])  ? trim($_POST['notes'])    : '';
    $status = isset($_POST['status']) ? trim($_POST['status'])   : '';

    if (empty($lab_id)) { echo "error: Invalid lab test ID"; $conn->close(); exit; }

    $valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        echo "error: Invalid status";
        $conn->close(); exit;
    }

    $stmt = $conn->prepare("UPDATE lab_tests SET result = ?, notes = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssi", $result, $notes, $status, $lab_id);
    echo $stmt->execute() ? "success" : "error: Failed to save result â€” " . $stmt->error;
    $stmt->close();

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   DELETE
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
} elseif ($action === 'delete') {
    $lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : 0;

    if (empty($lab_id)) { echo "error: Invalid lab test ID"; $conn->close(); exit; }

    // Verify it exists first
    $chk = $conn->prepare("SELECT id FROM lab_tests WHERE id = ?");
    $chk->bind_param("i", $lab_id);
    $chk->execute(); $chk->store_result();
    if ($chk->num_rows === 0) { echo "error: Lab test not found"; $chk->close(); $conn->close(); exit; }
    $chk->close();

    $stmt = $conn->prepare("DELETE FROM lab_tests WHERE id = ?");
    $stmt->bind_param("i", $lab_id);

    if ($stmt->execute() && $stmt->affected_rows === 1) {
        echo "success";
    } else {
        echo "error: Failed to delete";
    }
    $stmt->close();

} else {
    echo "error: Invalid action";
}

$conn->close();
?>
