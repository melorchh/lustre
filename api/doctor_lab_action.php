<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
if (!isset($_SESSION["doctor_id"])) { header("Content-Type: text/plain; charset=utf-8"); echo "Unauthorized"; exit; }
include __DIR__ . '/../toddcare-backend/src/db.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo "Invalid request"; exit; }

$doctor_id = (int)$_SESSION['doctor_id'];
$action    = isset($_GET['action']) ? $_GET['action'] : '';
$lab_id    = isset($_POST['lab_id']) ? (int)$_POST['lab_id'] : 0;

if (!$lab_id) { echo "Invalid lab test"; exit; }

// Verify this lab test belongs to the logged-in doctor
$check = $conn->prepare("SELECT doctor_id FROM lab_tests WHERE id = ?");
$check->bind_param("i", $lab_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) { $check->close(); echo "Lab test not found"; $conn->close(); exit; }
$check->bind_result($owner_id);
$check->fetch();
$check->close();
if ((int)$owner_id !== $doctor_id) { echo "You are not allowed to modify this lab test"; $conn->close(); exit; }

if ($action === 'update_status') {
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $allowed = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($status, $allowed)) { echo "Invalid status"; $conn->close(); exit; }
    $stmt = $conn->prepare("UPDATE lab_tests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $lab_id);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();

} elseif ($action === 'save_result') {
    $result = isset($_POST['result']) ? trim($_POST['result']) : '';
    $notes  = isset($_POST['notes'])  ? trim($_POST['notes'])  : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'completed';
    $allowed = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($status, $allowed)) { $status = 'completed'; }

    if ($result === '') { echo "Please enter the lab result"; $conn->close(); exit; }

    $stmt = $conn->prepare("UPDATE lab_tests SET result = ?, notes = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssi", $result, $notes, $status, $lab_id);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();

} else {
    echo "Unknown action";
}

$conn->close();
?>