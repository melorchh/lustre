<?php
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/api.php';

$wantJson = (isset($_SERVER['HTTP_X_LUSTRE_API']) && $_SERVER['HTTP_X_LUSTRE_API'] === '1');

function dla_out($msg, $isError = false, $status = 400) {
    global $wantJson;
    if ($wantJson) {
        if ($isError) { api_fail($msg, $status); }
        api_ok();
    }
    echo $msg;
    exit;
}

if (!isset($_SESSION["doctor_id"])) {
    if ($wantJson) { api_fail('Unauthorized', 401); }
    header("Content-Type: text/plain; charset=utf-8");
    echo "Unauthorized";
    exit;
}
require_once __DIR__ . '/../src/db.php';

if (!$wantJson) { header('Content-Type: text/plain; charset=utf-8'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { dla_out("Invalid request", true, 405); }

$doctor_id = (int)$_SESSION['doctor_id'];
$action    = isset($_GET['action']) ? $_GET['action'] : '';
$in        = api_input();
if (empty($in)) { $in = $_POST; }
$lab_id    = isset($in['lab_id']) ? (int)$in['lab_id'] : 0;

if (!$lab_id) { dla_out("Invalid lab test", true); }

$check = $conn->prepare("SELECT doctor_id FROM lab_tests WHERE id = ?");
$check->bind_param("i", $lab_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) { $check->close(); dla_out("Lab test not found", true, 404); }
$check->bind_result($owner_id);
$check->fetch();
$check->close();
if ((int)$owner_id !== $doctor_id) { dla_out("You are not allowed to modify this lab test", true, 403); }

if ($action === 'update_status') {
    $status = isset($in['status']) ? $in['status'] : '';
    $allowed = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($status, $allowed)) { dla_out("Invalid status", true); }
    $stmt = $conn->prepare("UPDATE lab_tests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $lab_id);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();
    dla_out($ok ? "success" : "Failed: " . $err, !$ok);

} elseif ($action === 'save_result') {
    $result = isset($in['result']) ? trim($in['result']) : '';
    $notes  = isset($in['notes'])  ? trim($in['notes'])  : '';
    $status = isset($in['status']) ? $in['status'] : 'completed';
    $allowed = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($status, $allowed)) { $status = 'completed'; }

    if ($result === '') { dla_out("Please enter the lab result", true); }

    $stmt = $conn->prepare("UPDATE lab_tests SET result = ?, notes = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssi", $result, $notes, $status, $lab_id);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();
    dla_out($ok ? "success" : "Failed: " . $err, !$ok);

} else {
    dla_out("Unknown action", true);
}

$conn->close();
?>
