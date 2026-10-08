<?php

function fmt_hm12($t) {
    $ts = strtotime($t);
    return date('i', $ts) === '00' ? date('gA', $ts) : date('g:iA', $ts);
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