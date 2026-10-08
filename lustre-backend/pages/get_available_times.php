<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

require_once __DIR__ . '/../src/db.php';

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

if (empty($doctor_id) || empty($date)) {
    echo json_encode([]);
    $conn->close();
    exit;
}

// Validate date format and check if it's not in the past
$selected_date = strtotime($date);
$today = strtotime(date('Y-m-d'));

if ($selected_date < $today) {
    echo json_encode([]);
    $conn->close();
    exit;
}

// Get day of week
$day_of_week = date('l', strtotime($date)); // Monday, Tuesday, etc.

// Get ALL doctor's schedules for this day from database
$stmt = $conn->prepare("
    SELECT start_time, end_time 
    FROM doctor_schedules 
    WHERE doctor_id = ? 
    AND day_of_week = ? 
    AND is_active = 1
    ORDER BY start_time ASC
");
$stmt->bind_param("is", $doctor_id, $day_of_week);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Doctor doesn't work on this day
    echo json_encode([]);
    $stmt->close();
    $conn->close();
    exit;
}

// Process all schedules for this day
$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}
$stmt->close();

// Get booked appointments for this doctor on this date with 2-hour duration blocks
$stmt = $conn->prepare("SELECT appointment_time FROM appointments WHERE doctor_id=? AND appointment_date=? AND status IN ('pending', 'confirmed')");
$stmt->bind_param("is", $doctor_id, $date);
$stmt->execute();
$result = $stmt->get_result();
$booked_blocks = []; // Store as [start_time, end_time] blocks
while ($row = $result->fetch_assoc()) {
    $appointment_time = $row['appointment_time'];
    // Convert to seconds since midnight for easier comparison
    $parts = explode(':', $appointment_time);
    $start_seconds = ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
    $end_seconds = $start_seconds + (2 * 60 * 60); // Add 2 hours
    $booked_blocks[] = ['start' => $start_seconds, 'end' => $end_seconds];
}
$stmt->close();

// Get unavailable time blocks for this doctor (specific date OR weekly day-of-week)
$stmt = $conn->prepare("SELECT start_time, end_time FROM doctor_time_blocks WHERE doctor_id=? AND ((block_type='date' AND block_date=?) OR (block_type='weekly' AND day_of_week=?))");
$stmt->bind_param("iss", $doctor_id, $date, $day_of_week);
$stmt->execute();
$result = $stmt->get_result();
$blocked_ranges = [];
while ($row = $result->fetch_assoc()) {
    $bp = explode(':', $row['start_time']);
    $bse = explode(':', $row['end_time']);
    $blocked_ranges[] = [
        'start' => ($bp[0] * 3600) + ($bp[1] * 60),
        'end'   => ($bse[0] * 3600) + ($bse[1] * 60)
    ];
}
$stmt->close();

// Generate available slots from all schedules
$available = [];
$current_time = time();
$is_today = (date('Y-m-d') == $date);

foreach ($schedules as $schedule) {
    $start = strtotime($schedule['start_time']);
    $end = strtotime($schedule['end_time']);
    $interval = 30 * 60; // 30 minutes

    for ($t = $start; $t < $end; $t += $interval) {
        $time = date("H:i", $t);
        
        // If it's today, skip past time slots
        if ($is_today) {
            $slot_timestamp = strtotime(date('Y-m-d') . ' ' . $time);
            if ($slot_timestamp <= $current_time) {
                continue;
            }
        }

        // The full 2-hour visit must fit before the shift ends
        if ($t + (2 * 60 * 60) > $end) {
            continue;
        }

        // Check if this slot is not booked (considering 2-hour duration)
        $is_booked = false;
        
        // Convert slot time to seconds since midnight
        $slot_parts = explode(':', $time);
        $slot_start_seconds = ($slot_parts[0] * 3600) + ($slot_parts[1] * 60);
        $slot_end_seconds = $slot_start_seconds + (2 * 60 * 60); // full 2-hour visit window
        
        // Check against all booked 2-hour blocks
        foreach ($booked_blocks as $block) {
            // Check if there's any overlap with booked block
            if ($slot_start_seconds < $block['end'] && $slot_end_seconds > $block['start']) {
                $is_booked = true;
                break;
            }
        }

        // Check against unavailable time blocks
        if (!$is_booked) {
            foreach ($blocked_ranges as $br) {
                if ($slot_start_seconds < $br['end'] && $slot_end_seconds > $br['start']) {
                    $is_booked = true;
                    break;
                }
            }
        }
        
        // Add to available slots if not booked
        if (!$is_booked) {
            $available[] = $time;
        }
    }
}

// Remove duplicates and sort
$available = array_unique($available);
sort($available);

$conn->close();

echo json_encode($available);
?>