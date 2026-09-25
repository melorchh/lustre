<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["admin_id"])) {
    echo "error: Unauthorized access";
    exit;
}

include __DIR__ . '/../src/db.php';
include __DIR__ . '/../src/schedule_sync.php';

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

    // Validate day-of-week range (order matters: Mon -> Sat)
    $valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
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

    foreach ($days as $day_of_week) {
        // Check if schedule already exists for this doctor, day, and time
        $stmt = $conn->prepare("SELECT id FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND start_time = ? AND is_active = 1");
        $stmt->bind_param("iss", $doctor_id, $day_of_week, $start_time);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();
            echo "error: Some selected days already have a schedule at this time";
            $conn->close();
            exit;
        }
        $stmt->close();

        // Add schedule for this day
        $stmt = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("isss", $doctor_id, $day_of_week, $start_time, $end_time);

        if (!$stmt->execute()) {
            $stmt->close();
            $conn->close();
            echo "error: Failed to add schedule";
            exit;
        }
        $stmt->close();
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
    $day_of_week = isset($_POST['day_of_week']) ? trim($_POST['day_of_week'])   : '';
    $start_time  = isset($_POST['start_time'])  ? trim($_POST['start_time'])    : '';
    $end_time    = isset($_POST['end_time'])    ? trim($_POST['end_time'])      : '';
    $reason      = isset($_POST['reason'])      ? trim($_POST['reason'])        : '';

    if (empty($doctor_id) || empty($start_time) || empty($end_time) || !in_array($block_type, ['date', 'weekly'])) {
        echo "error: Please fill in the required fields";
        $conn->close();
        exit;
    }

    if ($block_type === 'date' && empty($block_date)) {
        echo "error: Please select a date";
        $conn->close();
        exit;
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

    // Date blocks: prevent a block ending in the past
    if ($block_type === 'date' && strtotime($block_date) < strtotime(date('Y-m-d'))) {
        echo "error: Cannot block a date in the past";
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO doctor_time_blocks (doctor_id, block_type, block_date, day_of_week, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $doctor_id, $block_type, $block_date, $day_of_week, $start_time, $end_time, $reason);

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