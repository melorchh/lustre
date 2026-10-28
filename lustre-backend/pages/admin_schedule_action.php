<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["admin_id"])) {
    echo "error: Unauthorized access";
    exit;
}

require_once __DIR__ . '/../src/db.php';
include __DIR__ . '/../src/schedule_sync.php';
require_once __DIR__ . '/../src/time_blocks_schema.php';
ensure_time_blocks_schema($conn);
// Allow the 'week' (date-range) block type on existing databases.
$conn->query("ALTER TABLE doctor_time_blocks DROP CONSTRAINT IF EXISTS doctor_time_blocks_block_type_check");
$conn->query("ALTER TABLE doctor_time_blocks ADD CONSTRAINT doctor_time_blocks_block_type_check CHECK (block_type IN ('date','weekly','week'))");

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'add') {
    $doctor_id = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : 0;
    $start_day = isset($_POST['start_day']) ? trim($_POST['start_day']) : '';
    $end_day   = isset($_POST['end_day']) ? trim($_POST['end_day']) : '';
    $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
    $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';

    if (empty($doctor_id) || empty($start_day) || empty($end_day) || empty($start_time) || empty($end_time)) {
        echo "error: All fields are required";
        $conn->close();
        exit;
    }

    // Validate day-of-week range (order matters: Mon -> Sun)
    $valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $si = array_search($start_day, $valid_days);
    $ei = array_search($end_day, $valid_days);
    if ($si === false || $ei === false) {
        echo "error: Invalid day of week";
        $conn->close();
        exit;
    }
    if ($ei < $si) {
        echo "error: End day must be on or after start day";
        $conn->close();
        exit;
    }
    $days = array_slice($valid_days, $si, $ei - $si + 1);

    // Validate the whole range before writing anything so a conflict on one
    // day cannot leave the range half-applied.
    foreach ($days as $day_of_week) {
        $conflict = schedule_block_conflict($conn, $doctor_id, $day_of_week, $start_time, $end_time);
        if ($conflict !== '') {
            echo "error: " . $conflict;
            $conn->close();
            exit;
        }
    }

    foreach ($days as $day_of_week) {
        $saved = save_doctor_schedule_block($conn, $doctor_id, $day_of_week, $start_time, $end_time);
        if (!$saved['ok']) {
            echo "error: " . $saved['error'];
            $conn->close();
            exit;
        }
    }

    echo "success";
    sync_doctor_schedule_text($conn, $doctor_id);
    $conn->close();
    exit;
}
elseif ($action === 'toggle') {
    $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;

    if (empty($schedule_id)) {
        echo "error: Invalid schedule ID";
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("UPDATE doctor_schedules SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_active, $schedule_id);

    if ($stmt->execute()) {
        echo "success";
        $owns = $conn->query("SELECT doctor_id FROM doctor_schedules WHERE id = $schedule_id");
        if ($owns && $owns->num_rows > 0) {
            sync_doctor_schedule_text($conn, (int)$owns->fetch_assoc()['doctor_id']);
        }
    } else {
        echo "error: Failed to update schedule status";
    }

    $stmt->close();
}
elseif ($action === 'delete') {
    $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;

    if (empty($schedule_id)) {
        echo "error: Invalid schedule ID";
        $conn->close();
        exit;
    }

    // Verify the schedule exists before deleting
    $verify_stmt = $conn->prepare("SELECT doctor_id FROM doctor_schedules WHERE id = ?");
    $verify_stmt->bind_param("i", $schedule_id);
    $verify_stmt->execute();
    $verify_stmt->store_result();

    if ($verify_stmt->num_rows === 0) {
        echo "error: Schedule not found";
        $verify_stmt->close();
        $conn->close();
        exit;
    }
    $verify_stmt->bind_result($owner_doctor_id);
    $verify_stmt->fetch();
    $verify_stmt->close();

    $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE id = ?");
    $stmt->bind_param("i", $schedule_id);

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        if ($affected === 1) {
            echo "success";
            sync_doctor_schedule_text($conn, (int)$owner_doctor_id);
        } else {
            echo "error: Unexpected number of rows deleted";
        }
    } else {
        echo "error: Failed to delete schedule";
    }

    $stmt->close();
}
elseif ($action === 'add_block') {
    $doctor_id   = isset($_POST['doctor_id'])   ? intval($_POST['doctor_id'])   : 0;
    $block_type  = isset($_POST['block_type'])  ? trim($_POST['block_type'])    : 'date';
    $block_date  = isset($_POST['block_date'])  ? trim($_POST['block_date'])    : '';
    $end_date    = isset($_POST['end_date'])    ? trim($_POST['end_date'])      : '';
    $day_of_week = isset($_POST['day_of_week']) ? trim($_POST['day_of_week'])   : '';
    $start_time  = isset($_POST['start_time'])  ? trim($_POST['start_time'])    : '';
    $end_time    = isset($_POST['end_time'])    ? trim($_POST['end_time'])      : '';
    $reason      = isset($_POST['reason'])      ? trim($_POST['reason'])        : '';

    if (empty($doctor_id) || empty($start_time) || empty($end_time) || !in_array($block_type, ['date', 'weekly', 'week'])) {
        echo "error: Please fill in the required fields";
        $conn->close();
        exit;
    }

    if ($block_type === 'date' && empty($block_date)) {
        echo "error: Please select a date";
        $conn->close();
        exit;
    }
    if ($block_type === 'week') {
        if ($block_date === '' || $end_date === '') {
            echo "error: Please select both a start and end date";
            $conn->close();
            exit;
        }
        if (strtotime($end_date) < strtotime($block_date)) {
            echo "error: End date must be on or after the start date";
            $conn->close();
            exit;
        }
    }
    if ($block_type === 'weekly') {
        $valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        if (!in_array($day_of_week, $valid_days)) {
            echo "error: Invalid day of week";
            $conn->close();
            exit;
        }
    }

    // Validate block range order
    $start_sec = strtotime($start_time);
    $end_sec   = strtotime($end_time);
    if ($end_sec <= $start_sec) {
        echo "error: End time must be after start time";
        $conn->close();
        exit;
    }

    // Date / week blocks: prevent a block starting in the past
    if (in_array($block_type, ['date', 'week']) && strtotime($block_date) < strtotime(date('Y-m-d'))) {
        echo "error: Cannot block a date in the past";
        $conn->close();
        exit;
    }

    $block_date_sql = ($block_date !== '') ? $block_date : null;
    $end_date_sql   = ($block_type === 'week' && $end_date !== '') ? $end_date : null;
    $day_of_week_sql = ($day_of_week !== '') ? $day_of_week : null;

    $stmt = $conn->prepare("INSERT INTO doctor_time_blocks (doctor_id, block_type, block_date, end_date, day_of_week, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $doctor_id, $block_type, $block_date_sql, $end_date_sql, $day_of_week_sql, $start_time, $end_time, $reason);

    if ($stmt->execute()) {
        if ($block_type === 'weekly') {
            sync_doctor_schedule_text($conn, $doctor_id);
        }
        echo "success";
    } else {
        echo "error: Failed to add time block";
    }
    $stmt->close();
}
elseif ($action === 'delete_block') {
    $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;

    if (empty($block_id)) {
        echo "error: Invalid block ID";
        $conn->close();
        exit;
    }

    $find = $conn->prepare("SELECT doctor_id FROM doctor_time_blocks WHERE id = ?");
    $find->bind_param("i", $block_id);
    $find->execute();
    $fres = $find->get_result();
    $block_doctor = $fres->num_rows > 0 ? (int)$fres->fetch_assoc()['doctor_id'] : 0;
    $find->close();

    $stmt = $conn->prepare("DELETE FROM doctor_time_blocks WHERE id = ?");
    $stmt->bind_param("i", $block_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows === 1) {
            if ($block_doctor > 0) {
                sync_doctor_schedule_text($conn, $block_doctor);
            }
            echo "success";
        } else {
            echo "error: Time block not found";
        }
    } else {
        echo "error: Failed to delete time block";
    }
    $stmt->close();
}
else {
    echo "error: Invalid action";
}

$conn->close();
?>