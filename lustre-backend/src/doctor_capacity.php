<?php
require_once __DIR__ . '/appointment_time_rules.php';

// ============================================================================
// Doctor daily capacity / availability.
//
// A doctor is available for a date only when they have an active shift that
// day. Without an active doctor_schedules row they are not offered for
// appointments. When they are scheduled, the rule that decides whether they
// are full depends on the data the clinic keeps:
//
//   * doctors.daily_limit > 0  ->  explicit daily patient cap. The doctor is
//     full at that cap or when no conflict-free one-hour walk-in slot remains.
//   * no cap  ->  slot based. The doctor is full when every 1-hour visit
//     window in today's shifts is already booked or blocked (the same 1-hour
//     block get_available_times.php books, so the counter and the patient
//     booking flow agree).
//
// Registering a walk-in also creates a confirmed appointment for the same
// patient, so patients are counted once (UNION of both sources) instead of
// adding the two tables together.
// ============================================================================

if (!function_exists('ensure_doctor_capacity_schema')) {
    /**
     * Adds doctors.daily_limit to databases created before the column existed.
     * Safe to call from every endpoint that reads or writes capacity.
     */
    function ensure_doctor_capacity_schema($conn)
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $conn->query("ALTER TABLE doctors ADD COLUMN IF NOT EXISTS daily_limit INTEGER NOT NULL DEFAULT 0");
        $ensured = true;
    }
}

if (!function_exists('doctor_daily_limit_status')) {
    /**
     * Checks the doctor's daily patient cap. Set $lock to serialize concurrent
     * bookings on the doctor row; callers must have an active transaction.
     */
    function doctor_daily_limit_status($conn, $doctor_id, $date, $lock = false, $exclude_appointment_id = 0)
    {
        ensure_doctor_capacity_schema($conn);

        $sql = "SELECT daily_limit FROM doctors WHERE id = ?" . ($lock ? " FOR UPDATE" : "");
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("i", $doctor_id);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $result = $stmt->get_result();
        $doctor = $result ? $result->fetch_assoc() : null;
        if ($result) {
            $result->close();
        }
        $stmt->close();
        if (!$doctor) {
            return array(
                'limit' => 0,
                'assigned' => 0,
                'remaining' => 0,
                'available' => false,
            );
        }

        $limit = (int)$doctor['daily_limit'];
        if ($limit <= 0) {
            return array(
                'limit' => $limit,
                'assigned' => 0,
                'remaining' => null,
                'available' => true,
            );
        }

        $appointment_filter = ((int)$exclude_appointment_id > 0) ? " AND id <> ?" : "";
        $count_sql = "
            SELECT COUNT(*) AS patient_count
            FROM (
                SELECT patient_id FROM appointments
                WHERE doctor_id = ? AND appointment_date = ?
                  AND status IN ('pending', 'confirmed', 'completed')" . $appointment_filter . "
                UNION
                SELECT patient_id FROM walk_ins
                WHERE doctor_id = ? AND arrival_date = ? AND status <> 'cancelled'
            ) AS assigned_patients
        ";
        $count = $conn->prepare($count_sql);
        if (!$count) {
            return null;
        }
        if ((int)$exclude_appointment_id > 0) {
            $count->bind_param("isiis", $doctor_id, $date, $exclude_appointment_id, $doctor_id, $date);
        } else {
            $count->bind_param("isis", $doctor_id, $date, $doctor_id, $date);
        }
        if (!$count->execute()) {
            $count->close();
            return null;
        }
        $count_result = $count->get_result();
        $row = $count_result ? $count_result->fetch_assoc() : null;
        if ($count_result) {
            $count_result->close();
        }
        $count->close();
        if (!$row) {
            return null;
        }

        $assigned = (int)$row['patient_count'];
        return array(
            'limit' => $limit,
            'assigned' => $assigned,
            'remaining' => ($limit > 0) ? max(0, $limit - $assigned) : null,
            'available' => ($limit <= 0 || $assigned < $limit),
        );
    }
}

if (!function_exists('doctor_clock_seconds')) {
    /** "HH:MM" or "HH:MM:SS" -> seconds since midnight (0 if unparseable). */
    function doctor_clock_seconds($value)
    {
        $parts = explode(':', (string)$value);
        if (count($parts) < 2) {
            return 0;
        }
        $seconds = ((int)$parts[0] * 3600) + ((int)$parts[1] * 60);
        if (isset($parts[2])) {
            $seconds += (int)$parts[2];
        }
        return $seconds;
    }
}

if (!function_exists('doctor_shift_on')) {
    /** True when a doctor_schedules row applies to $date. */
    function doctor_shift_on($row, $date, $day_of_week)
    {
        $schedule_date = isset($row['schedule_date']) ? substr((string)$row['schedule_date'], 0, 10) : '';
        if ($schedule_date !== '' && $schedule_date === $date) {
            return true;
        }
        return (string)$row['day_of_week'] === $day_of_week;
    }
}

if (!function_exists('doctor_walkin_time_slots_from_blocks')) {
    function doctor_walkin_time_slots_from_blocks($shifts, $busy, $now)
    {
        $window = 60 * 60;
        $available = array();
        foreach ($shifts as $shift) {
            $start = $shift['start'];
            if ($start < $now) {
                $elapsed = $now - $start;
                $start += (int)ceil($elapsed / $window) * $window;
            }
            while ($start + $window <= $shift['end']) {
                $end = $start + $window;
                $is_available = !appointment_interval_overlaps_lunch_break($start, $end);
                foreach ($busy as $block) {
                    if ($start < $block['end'] && $end > $block['start']) {
                        $is_available = false;
                        break;
                    }
                }
                if ($is_available) {
                    $available[sprintf('%02d:%02d', (int)floor($start / 3600), (int)floor(($start % 3600) / 60))] = true;
                }
                $start += $window;
            }
        }
        $times = array_keys($available);
        sort($times, SORT_STRING);
        return $times;
    }
}

if (!function_exists('doctor_available_walkin_times')) {
    /**
     * Available one-hour-spaced walk-in start times for today, or null on
     * query failure. Each time reserves a full hour inside an active shift.
     */
    function doctor_available_walkin_times($conn, $doctor_id, $date)
    {
        $day_of_week = date('l', strtotime($date));
        $stmt = $conn->prepare(
            "SELECT schedule_date, day_of_week, start_time, end_time, is_active
             FROM doctor_schedules WHERE doctor_id = ?"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("i", $doctor_id);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $rows = $stmt->get_result();
        if (!$rows) {
            $stmt->close();
            return null;
        }
        $shifts = array();
        while ($row = $rows->fetch_assoc()) {
            if ((int)$row['is_active'] !== 1 || !doctor_shift_on($row, $date, $day_of_week)) {
                continue;
            }
            $start = doctor_clock_seconds($row['start_time']);
            $end = doctor_clock_seconds($row['end_time']);
            if ($end > $start) {
                $shifts[] = array('start' => $start, 'end' => $end);
            }
        }
        $rows->close();
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT appointment_time FROM appointments
             WHERE doctor_id = ? AND appointment_date = ?
               AND status IN ('pending', 'confirmed', 'completed')"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("is", $doctor_id, $date);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $rows = $stmt->get_result();
        if (!$rows) {
            $stmt->close();
            return null;
        }
        $booked = array();
        while ($row = $rows->fetch_assoc()) {
            $start = doctor_clock_seconds($row['appointment_time']);
            $booked[] = array('start' => $start, 'end' => $start + (60 * 60));
        }
        $rows->close();
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT block_type, block_date, end_date, day_of_week, start_time, end_time
             FROM doctor_time_blocks WHERE doctor_id = ?"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("i", $doctor_id);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $rows = $stmt->get_result();
        if (!$rows) {
            $stmt->close();
            return null;
        }
        $blocked = array();
        while ($row = $rows->fetch_assoc()) {
            $type = (string)$row['block_type'];
            $block_date = isset($row['block_date']) ? substr((string)$row['block_date'], 0, 10) : '';
            $end_date = isset($row['end_date']) && $row['end_date']
                ? substr((string)$row['end_date'], 0, 10)
                : $block_date;
            $applies = (
                ($type === 'date' && $block_date === $date)
                || ($type === 'week' && $block_date !== '' && $block_date <= $date && $date <= $end_date)
                || ($type === 'weekly' && (string)$row['day_of_week'] === $day_of_week)
            );
            if ($applies) {
                $blocked[] = array(
                    'start' => doctor_clock_seconds($row['start_time']),
                    'end' => doctor_clock_seconds($row['end_time']),
                );
            }
        }
        $rows->close();
        $stmt->close();

        $now = doctor_clock_seconds(date('H:i'));
        return doctor_walkin_time_slots_from_blocks($shifts, array_merge($booked, $blocked), $now);
    }
}

if (!function_exists('doctor_slot_is_available')) {
    function doctor_slot_is_available($conn, $doctor_id, $date, $time)
    {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$time)) {
            return false;
        }
        $available = doctor_available_walkin_times($conn, $doctor_id, $date);
        if ($available === null) {
            return null;
        }
        return in_array($time, $available, true);
    }
}

if (!function_exists('doctor_free_windows')) {
    /**
     * How many more 1-hour visits fit in a set of shifts.
     *
     * Mirrors get_available_times.php: a visit occupies a 1-hour block, must
     * end before the shift does, and may not overlap an existing booking or a
     * blocked range. Candidates start on the 30-minute grid; once a window is
     * counted the following hour is skipped.
     */
    function doctor_free_windows($shifts, $booked, $blocked)
    {
        $blocks = array_merge($booked, $blocked);
        $window = 60 * 60;
        $step = 30 * 60;
        $free = 0;

        foreach ($shifts as $shift) {
            $start = $shift['start'];
            while ($start + $window <= $shift['end']) {
                $hit = appointment_interval_overlaps_lunch_break($start, $start + $window);
                foreach ($blocks as $block) {
                    if ($hit) {
                        break;
                    }
                    if ($start < $block['end'] && $start + $window > $block['start']) {
                        $hit = true;
                        break;
                    }
                }
                if ($hit) {
                    $start += $step;
                } else {
                    $free++;
                    $start += $window;
                }
            }
        }
        return $free;
    }
}

if (!function_exists('doctor_capacity_map')) {
    /**
     * Availability snapshot for every doctor on $date (default: today).
     *
     * @return array<int, array{
     *   daily_limit: int, mode: string, capacity: int|null, assigned: int,
     *   remaining: int|null, available: bool, reason: string
     * }>
     */
    function doctor_capacity_map($conn, $date = null)
    {
        ensure_doctor_capacity_schema($conn);

        $date = ($date === null || $date === '') ? date('Y-m-d') : $date;
        $day_of_week = date('l', strtotime($date));

        $limits       = array();
        $has_schedule = array();
        $shifts       = array();
        $assigned     = array();
        $booked       = array();
        $blocked      = array();

        $res = $conn->query("SELECT id, daily_limit FROM doctors");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $limits[(int)$row['id']] = (int)$row['daily_limit'];
            }
        }

        $res = $conn->query("SELECT doctor_id, schedule_date, day_of_week, start_time, end_time, is_active FROM doctor_schedules");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)$row['doctor_id'];
                $has_schedule[$id] = true;
                if ((int)$row['is_active'] !== 1) {
                    continue;
                }
                if (!doctor_shift_on($row, $date, $day_of_week)) {
                    continue;
                }
                $start = doctor_clock_seconds($row['start_time']);
                $end   = doctor_clock_seconds($row['end_time']);
                if ($end <= $start) {
                    continue;
                }
                $shifts[$id][] = array('start' => $start, 'end' => $end);
            }
        }

        // Patients already assigned that day: appointments that were not
        // cancelled (including completed visits) plus walk-ins, counted once
        // per patient. Completed visits still used a slot and count toward
        // the doctor's daily limit.
        $stmt = $conn->prepare(
            "SELECT doctor_id, patient_id FROM appointments
              WHERE appointment_date = ? AND status IN ('pending','confirmed','completed')
             UNION
             SELECT doctor_id, patient_id FROM walk_ins
              WHERE arrival_date = ? AND status <> 'cancelled'"
        );
        if ($stmt) {
            $stmt->bind_param("ss", $date, $date);
            if ($stmt->execute()) {
                $rows = $stmt->get_result();
                if ($rows) {
                    while ($row = $rows->fetch_assoc()) {
                        $id = (int)$row['doctor_id'];
                        $assigned[$id] = (isset($assigned[$id]) ? $assigned[$id] : 0) + 1;
                    }
                    $rows->close();
                }
            }
            $stmt->close();
        }

        $stmt = $conn->prepare(
            "SELECT doctor_id, appointment_time FROM appointments
              WHERE appointment_date = ? AND status IN ('pending','confirmed','completed')"
        );
        if ($stmt) {
            $stmt->bind_param("s", $date);
            if ($stmt->execute()) {
                $rows = $stmt->get_result();
                if ($rows) {
                    while ($row = $rows->fetch_assoc()) {
                        $start = doctor_clock_seconds($row['appointment_time']);
                        $booked[(int)$row['doctor_id']][] = array('start' => $start, 'end' => $start + (60 * 60));
                    }
                    $rows->close();
                }
            }
            $stmt->close();
        }

        $res = $conn->query(
            "SELECT doctor_id, block_type, block_date, end_date, day_of_week, start_time, end_time
             FROM doctor_time_blocks"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $type = (string)$row['block_type'];
                $from = isset($row['block_date']) ? substr((string)$row['block_date'], 0, 10) : '';
                $to   = isset($row['end_date']) && $row['end_date'] ? substr((string)$row['end_date'], 0, 10) : '';

                if ($type === 'date') {
                    $applies = ($from !== '' && $from === $date);
                } elseif ($type === 'week') {
                    $end = ($to !== '') ? $to : $from;
                    $applies = ($from !== '' && $from <= $date && $date <= $end);
                } elseif ($type === 'weekly') {
                    $applies = ((string)$row['day_of_week'] === $day_of_week);
                } else {
                    $applies = false;
                }
                if (!$applies) {
                    continue;
                }

                $blocked[(int)$row['doctor_id']][] = array(
                    'start' => doctor_clock_seconds($row['start_time']),
                    'end'   => doctor_clock_seconds($row['end_time']),
                );
            }
        }

        $ids = array_unique(array_merge(
            array_keys($limits),
            array_keys($has_schedule),
            array_keys($assigned)
        ));

        $out = array();
        foreach ($ids as $id) {
            $id      = (int)$id;
            $limit   = isset($limits[$id]) ? $limits[$id] : 0;
            $count   = isset($assigned[$id]) ? $assigned[$id] : 0;
            $walkin_times = doctor_walkin_time_slots_from_blocks(
                isset($shifts[$id]) ? $shifts[$id] : array(),
                array_merge(
                    isset($booked[$id]) ? $booked[$id] : array(),
                    isset($blocked[$id]) ? $blocked[$id] : array()
                ),
                doctor_clock_seconds(date('H:i'))
            );
            $has_walkin_time = count($walkin_times) > 0;
            $mode    = 'unknown';
            $capacity = null;
            $remaining = null;
            $available = true;
            $reason    = '';

            if (empty($shifts[$id])) {
                // No active shift applies to this date. This also covers
                // doctors with no schedule rows at all.
                $mode      = 'off_duty';
                $remaining = 0;
                $available = false;
                $reason    = 'not_scheduled';
            } elseif ($limit > 0) {
                $mode      = 'daily_limit';
                $capacity  = $limit;
                $remaining = max(0, $limit - $count);
                $available = $count < $limit && $has_walkin_time;
                if (!$available) {
                    $reason = 'full';
                }
            } else {
                $mode      = 'schedule';
                $remaining = doctor_free_windows(
                    $shifts[$id],
                    isset($booked[$id]) ? $booked[$id] : array(),
                    isset($blocked[$id]) ? $blocked[$id] : array()
                );
                $available = $remaining > 0 && $has_walkin_time;
                if (!$available) {
                    $reason = 'full';
                }
            }

            $out[$id] = array(
                'daily_limit' => $limit,
                'mode'        => $mode,
                'capacity'    => $capacity,
                'assigned'    => $count,
                'remaining'   => $remaining,
                'available'   => $available,
                'reason'      => $reason,
            );
        }

        return $out;
    }
}
