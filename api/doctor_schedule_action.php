<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
if (!isset($_SESSION["doctor_id"])) { echo "Unauthorized"; exit; }
include __DIR__ . '/../toddcare-backend/src/db.php';
include __DIR__ . '/../toddcare-backend/src/schedule_sync.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo "Invalid request"; exit; }

$doctor_id = (int)$_SESSION['doctor_id'];
$action    = isset($_GET['action']) ? $_GET['action'] : '';

// Ownership helper: returns schedule row if it belongs to this doctor
function own_schedule($conn, $doctor_id, $schedule_id) {
    $stmt = $conn->prepare("SELECT id FROM doctor_schedules WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $schedule_id, $doctor_id);
    $stmt->execute();
    $stmt->store_result();
    $found = $stmt->num_rows > 0;
    $stmt->close();
    return $found;
}
// Ownership helper: returns time block row if it belongs to this doctor
function own_block($conn, $doctor_id, $block_id) {
    $stmt = $conn->prepare("SELECT id FROM doctor_time_blocks WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $block_id, $doctor_id);
    $stmt->execute();
    $stmt->store_result();
    $found = $stmt->num_rows > 0;
    $stmt->close();
    return $found;
}

$valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

if ($action === 'add') {
    $day_of_week = isset($_POST['day_of_week']) ? trim($_POST['day_of_week']) : '';
    $start_time  = isset($_POST['start_time'])  ? trim($_POST['start_time'])  : '';
    $end_time    = isset($_POST['end_time'])    ? trim($_POST['end_time'])    : '';

    if (!in_array($day_of_week, $valid_days) || empty($start_time) || empty($end_time)) {
        echo "Please fill in all fields"; $conn->close(); exit;
    }
    if (strtotime($end_time) <= strtotime($start_time)) {
        echo "End time must be after start time"; $conn->close(); exit;
    }

    $check = $conn->prepare("SELECT id FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
    $check->bind_param("is", $doctor_id, $day_of_week);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) { $check->close(); echo "You already have a schedule for " . $day_of_week; $conn->close(); exit; }
    $check->close();

    $stmt = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param("isss", $doctor_id, $day_of_week, $start_time, $end_time);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();
    sync_doctor_schedule_text($conn, $doctor_id);

} elseif ($action === 'edit') {
    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    $start_time  = isset($_POST['start_time'])  ? trim($_POST['start_time'])  : '';
    $end_time    = isset($_POST['end_time'])    ? trim($_POST['end_time'])    : '';

    if (!$schedule_id || empty($start_time) || empty($end_time)) {
        echo "Please fill in all fields"; $conn->close(); exit;
    }
    if (strtotime($end_time) <= strtotime($start_time)) {
        echo "End time must be after start time"; $conn->close(); exit;
    }
    if (!own_schedule($conn, $doctor_id, $schedule_id)) {
        echo "You are not allowed to modify this schedule"; $conn->close(); exit;
    }

    $stmt = $conn->prepare("UPDATE doctor_schedules SET start_time = ?, end_time = ? WHERE id = ?");
    $stmt->bind_param("ssi", $start_time, $end_time, $schedule_id);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();
    sync_doctor_schedule_text($conn, $doctor_id);

} elseif ($action === 'toggle_active') {
    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    $is_active   = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

    if (!$schedule_id) { echo "Invalid schedule"; $conn->close(); exit; }
    if (!own_schedule($conn, $doctor_id, $schedule_id)) {
        echo "You are not allowed to modify this schedule"; $conn->close(); exit;
    }

    $stmt = $conn->prepare("UPDATE doctor_schedules SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_active, $schedule_id);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();

} elseif ($action === 'delete') {
    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;

    if (!$schedule_id) { echo "Invalid schedule"; $conn->close(); exit; }
    if (!own_schedule($conn, $doctor_id, $schedule_id)) {
        echo "You are not allowed to modify this schedule"; $conn->close(); exit;
    }

    $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE id = ?");
    $stmt->bind_param("i", $schedule_id);
    echo $stmt->execute() && $stmt->affected_rows > 0 ? "success" : "Failed: " . $stmt->error;
    $stmt->close();
    sync_doctor_schedule_text($conn, $doctor_id);

} elseif ($action === 'add_block') {
    $block_type  = isset($_POST['block_type'])  ? trim($_POST['block_type'])  : 'date';
    $block_date  = isset($_POST['block_date'])  ? trim($_POST['block_date'])  : '';
    $day_of_week = isset($_POST['day_of_week']) ? trim($_POST['day_of_week']) : '';
    $start_time  = isset($_POST['start_time'])  ? trim($_POST['start_time'])  : '';
    $end_time    = isset($_POST['end_time'])    ? trim($_POST['end_time'])    : '';
    $reason      = isset($_POST['reason'])      ? trim($_POST['reason'])      : '';

    if (!in_array($block_type, ['date', 'weekly']) || empty($start_time) || empty($end_time)) {
        echo "Please fill in the required fields"; $conn->close(); exit;
    }
    if ($block_type === 'date' && empty($block_date)) {
        echo "Please select a date"; $conn->close(); exit;
    }
    if ($block_type === 'weekly' && !in_array($day_of_week, $valid_days)) {
        echo "Invalid day of week"; $conn->close(); exit;
    }
    if (strtotime($end_time) <= strtotime($start_time)) {
        echo "End time must be after start time"; $conn->close(); exit;
    }
    if ($block_type === 'date' && strtotime($block_date) < strtotime(date('Y-m-d'))) {
        echo "Cannot block a date in the past"; $conn->close(); exit;
    }

    $stmt = $conn->prepare("INSERT INTO doctor_time_blocks (doctor_id, block_type, block_date, day_of_week, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $doctor_id, $block_type, $block_date, $day_of_week, $start_time, $end_time, $reason);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();
    if ($block_type === 'weekly') {
        sync_doctor_schedule_text($conn, $doctor_id);
    }

} elseif ($action === 'delete_block') {
    $block_id = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;

    if (!$block_id) { echo "Invalid time block"; $conn->close(); exit; }
    if (!own_block($conn, $doctor_id, $block_id)) {
        echo "You are not allowed to modify this time block"; $conn->close(); exit;
    }

    $stmt = $conn->prepare("DELETE FROM doctor_time_blocks WHERE id = ?");
    $stmt->bind_param("i", $block_id);
    echo $stmt->execute() && $stmt->affected_rows > 0 ? "success" : "Failed: " . $stmt->error;
    $stmt->close();
    sync_doctor_schedule_text($conn, $doctor_id);

} else {
    echo "Unknown action";
}

$conn->close();
?>