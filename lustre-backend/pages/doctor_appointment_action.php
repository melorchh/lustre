<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_id"])) { echo "Unauthorized"; exit; }
require_once __DIR__ . '/../src/db.php';

$doctor_id = (int)$_SESSION['doctor_id'];
$appt_id   = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$status    = isset($_POST['status']) ? trim($_POST['status']) : '';

$allowed = ['pending','confirmed','completed','cancelled'];
if ($appt_id <= 0 || !in_array($status, $allowed, true)) {
    echo "Invalid request";
    $conn->close();
    exit;
}

// Verify ownership
$chk = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND doctor_id = ?");
$chk->bind_param("ii", $appt_id, $doctor_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    $chk->close();
    echo "Not allowed";
    $conn->close();
    exit;
}
$chk->close();

$stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
$stmt->bind_param("sii", $status, $appt_id, $doctor_id);
if ($stmt->execute()) {
    echo "success";
} else {
    echo "Failed";
}
$stmt->close();
$conn->close();
