<?php

function fmt_hm12($t) {
    $ts = strtotime($t);
    return date('i', $ts) === '00' ? date('gA', $ts) : date('g:iA', $ts);
}

function schedule_time_sql($value) {
    $ts = strtotime((string)$value);
    return $ts === false ? '' : date('H:i:s', $ts);
}

function schedule_time_seconds($value) {
    $parts = explode(':', (string)$value);
    if (count($parts) < 2) {
        return 0;
    }
    return ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (isset($parts[2]) ? (int)$parts[2] : 0);
}

/**
 * Returns an error message when a new schedule block overlaps another block
 * for the same doctor and day. A block with the same start time is treated as
 * an update, so it is not a conflict.
 */
function schedule_block_conflict($conn, $doctor_id, $day_of_week, $start_time, $end_time) {
    $start = schedule_time_sql($start_time);
    $end   = schedule_time_sql($end_time);
    $start_sec = schedule_time_seconds($start);
    $end_sec   = schedule_time_seconds($end);

    if ($start === '' || $end === '' || $end_sec <= $start_sec) {
        return 'End time must be after start time';
    }

    $stmt = $conn->prepare(
        "SELECT start_time, end_time FROM doctor_schedules
         WHERE doctor_id = ? AND day_of_week = ? AND start_time <> ?"
    );
    $stmt->bind_param("iss", $doctor_id, $day_of_week, $start);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $existing_start = schedule_time_seconds($row['start_time']);
        $existing_end   = schedule_time_seconds($row['end_time']);
        if ($start_sec < $existing_end && $end_sec > $existing_start) {
            $stmt->close();
            return 'This time overlaps an existing ' . $day_of_week . ' schedule';
        }
    }
    $stmt->close();

    return '';
}

/**
 * Creates a schedule block, or updates the end time/reactivates the row when
 * the same doctor already has a block on that day with the same start time.
 *
 * @return array{ok:bool, action:string, error:string}
 */
function save_doctor_schedule_block($conn, $doctor_id, $day_of_week, $start_time, $end_time) {
    $start = schedule_time_sql($start_time);
    $end   = schedule_time_sql($end_time);

    if ($start === '' || $end === '' || schedule_time_seconds($end) <= schedule_time_seconds($start)) {
        return array('ok' => false, 'action' => '', 'error' => 'End time must be after start time');
    }

    $find = $conn->prepare(
        "SELECT id FROM doctor_schedules
         WHERE doctor_id = ? AND day_of_week = ? AND start_time = ?
         ORDER BY is_active DESC, id ASC LIMIT 1"
    );
    $find->bind_param("iss", $doctor_id, $day_of_week, $start);
    $find->execute();
    $existing = $find->get_result()->fetch_assoc();
    $find->close();

    if ($existing) {
        $stmt = $conn->prepare("UPDATE doctor_schedules SET end_time = ?, is_active = 1 WHERE id = ?");
        $schedule_id = (int)$existing['id'];
        $stmt->bind_param("si", $end, $schedule_id);
        $ok = $stmt->execute();
        $error = $ok ? '' : ($stmt->error ?: 'Failed to update schedule');
        $stmt->close();
        return array('ok' => (bool)$ok, 'action' => 'updated', 'error' => $error);
    }

    $stmt = $conn->prepare(
        "INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, is_active)
         VALUES (?, ?, ?, ?, 1)"
    );
    $stmt->bind_param("isss", $doctor_id, $day_of_week, $start, $end);
    $ok = $stmt->execute();
    $error = $ok ? '' : ($stmt->error ?: 'Failed to add schedule');
    $stmt->close();

    return array('ok' => (bool)$ok, 'action' => 'added', 'error' => $error);
}

function sync_doctor_schedule_text($conn, $doctor_id) {
    $doctor_id = (int)$doctor_id;
    $week = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

    $stmt = $conn->prepare(
        "SELECT day_of_week, MIN(start_time) s, MAX(end_time) e
         FROM doctor_schedules WHERE doctor_id = ? AND is_active = 1
         GROUP BY day_of_week"
    );
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $by_day = [];
    while ($row = $res->fetch_assoc()) {
        $by_day[$row['day_of_week']] = [$row['s'], $row['e']];
    }
    $stmt->close();

    // Weekly time blocks that fully cover a working day remove that day from the summary
    $fully_blocked = [];
    $bq = $conn->prepare(
        "SELECT day_of_week, start_time, end_time
         FROM doctor_time_blocks
         WHERE doctor_id = ? AND block_type = 'weekly'"
    );
    $bq->bind_param("i", $doctor_id);
    $bq->execute();
    $bres = $bq->get_result();
    while ($brow = $bres->fetch_assoc()) {
        $day = $brow['day_of_week'];
        if (!isset($by_day[$day])) continue;
        $bs = date('H:i:s', strtotime($brow['start_time']));
        $be = date('H:i:s', strtotime($brow['end_time']));
        if ($bs <= $by_day[$day][0] && $be >= $by_day[$day][1]) {
            $fully_blocked[$day] = true;
        }
    }
    $bq->close();

    $text = '';
    $day_keys = array_values(array_filter($week, function ($d) use ($by_day, $fully_blocked) { return isset($by_day[$d]) && !isset($fully_blocked[$d]); }));
    if (count($day_keys) > 0) {
        $runs = [];
        $run_days = [$day_keys[0]];
        $run_time = $by_day[$day_keys[0]];
        for ($i = 1; $i < count($day_keys); $i++) {
            $prev_idx = array_search($run_days[count($run_days) - 1], $week);
            $cur_idx  = array_search($day_keys[$i], $week);
            $cur_time = $by_day[$day_keys[$i]];
            if ($cur_idx === $prev_idx + 1 && $cur_time === $run_time) {
                $run_days[] = $day_keys[$i];
            } else {
                $runs[] = $run_days;
                $run_days = [$day_keys[$i]];
                $run_time = $cur_time;
            }
        }
        $runs[] = $run_days;

        $day_parts = [];
        foreach ($runs as $r) {
            $label = count($r) > 1
                ? substr($r[0], 0, 3) . '-' . substr($r[count($r) - 1], 0, 3)
                : substr($r[0], 0, 3);
            $pair = $by_day[$r[0]];
            $day_parts[] = $label . ': ' . fmt_hm12($pair[0]) . '-' . fmt_hm12($pair[1]);
        }

        $text = implode(', ', $day_parts);
    }

    $upd = $conn->prepare("UPDATE doctors SET schedule = ? WHERE id = ?");
    $upd->bind_param("si", $text, $doctor_id);
    $upd->execute();
    $upd->close();

    return $text;
}