<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

include __DIR__ . '/../src/db.php';

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$days = isset($_GET['days']) ? intval($_GET['days']) : 14;
if ($days < 1) $days = 14;

if (empty($doctor_id)) {
    echo json_encode([]);
    $conn->close();
    exit;
}

$available_dates = [];
$today = strtotime(date('Y-m-d'));
$interval = 24 * 60 * 60;

for ($i = 0; $i < $days; $i++) {
    $date = date('Y-m-d', $today + $i * $interval);
    $day_of_week = date('l', strtotime($date));

    // Get ALL schedules for that day
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
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $stmt->close();
        continue;
    }

    // Get all schedules for this day
    $schedules = [];
    while ($row = $res->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt->close();

    // Fetch booked times for that date with 2-hour blocks
    $stmt2 = $conn->prepare("SELECT appointment_time FROM appointments WHERE doctor_id=? AND appointment_date=? AND status IN ('pending','confirmed')");
    $stmt2->bind_param("is", $doctor_id, $date);
    $stmt2->execute();
    $r2 = $stmt2->get_result();
    $booked_blocks = []; // Store as [start_seconds, end_seconds] blocks
    while ($row = $r2->fetch_assoc()) {
        $appointment_time = $row['appointment_time'];
        // Convert to seconds since midnight for easier comparison
        $parts = explode(':', $appointment_time);
        $start_seconds = ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        $end_seconds = $start_seconds + (2 * 60 * 60); // Add 2 hours
        $booked_blocks[] = ['start' => $start_seconds, 'end' => $end_seconds];
    }
    $stmt2->close();

    // Fetch unavailable time blocks for this doctor on this date
    $stmt3 = $conn->prepare("SELECT start_time, end_time FROM doctor_time_blocks WHERE doctor_id=? AND ((block_type='date' AND block_date=?) OR (block_type='weekly' AND day_of_week=?))");
    $stmt3->bind_param("iss", $doctor_id, $date, $day_of_week);
    $stmt3->execute();
    $r3 = $stmt3->get_result();
    $blocked_ranges = [];
    while ($row = $r3->fetch_assoc()) {
        $bp  = explode(':', $row['start_time']);
        $bpe = explode(':', $row['end_time']);
        $blocked_ranges[] = ['start' => ($bp[0] * 3600) + ($bp[1] * 60), 'end' => ($bpe[0] * 3600) + ($bpe[1] * 60)];
    }
    $stmt3->close();

    $is_today = ($date === date('Y-m-d'));
    $now = time();
    $has_slot = false;

    // Check if any slot is available across all schedules for this day
    foreach ($schedules as $sched) {
        $start = strtotime($sched['start_time']);
        $end = strtotime($sched['end_time']);
        $slot_interval = 30 * 60;

        for ($t = $start; $t < $end; $t += $slot_interval) {
            $time_str = date('H:i', $t);
            if ($is_today) {
                $slot_ts = strtotime($date . ' ' . $time_str);
                if ($slot_ts <= $now) continue;
            }
            
            // Convert slot time to seconds since midnight
            $slot_parts = explode(':', $time_str);
            $slot_start_seconds = ($slot_parts[0] * 3600) + ($slot_parts[1] * 60);
            $slot_end_seconds = $slot_start_seconds + (30 * 60); // 30-minute slot
            
            // Check if slot overlaps with any booked 2-hour block
            $is_booked = false;
            foreach ($booked_blocks as $block) {
                if ($slot_start_seconds < $block['end'] && $slot_end_seconds > $block['start']) {
                    $is_booked = true;
                    break;
                }
            }

            // Check if slot overlaps with any unavailable time block
            if (!$is_booked) {
                foreach ($blocked_ranges as $br) {
                    if ($slot_start_seconds < $br['end'] && $slot_end_seconds > $br['start']) {
                        $is_booked = true;
                        break;
                    }
                }
            }
            
            if (!$is_booked) {
                $has_slot = true;
                break 2; // Break out of both loops
            }
        }
    }

    if ($has_slot) {
        $available_dates[] = [
            'date' => $date,
            'display' => date('M d (D)', strtotime($date))
        ];
    }
}

$conn->close();
echo json_encode($available_dates);
?>
