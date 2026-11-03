<?php
// Shared appointment-slot rules.
//
// Appointments are booked into 1-hour slots that sit inside the doctor's
// normal availability. Only the FIRST 4 HOURS of the doctor's day are open for
// appointment bookings; the rest of the shift is reserved for walk-ins and
// other clinic work. Each slot can hold a fixed number of patients (4 by
// default), so patients keep seeing the same time until the slot is full.

require_once __DIR__ . '/appointment_time_rules.php';

if (!defined('LUSTRE_APPOINTMENT_SLOTS_LOADED')) {
    define('LUSTRE_APPOINTMENT_SLOTS_LOADED', true);

    if (!defined('APPOINTMENT_SLOT_MINUTES')) {
        define('APPOINTMENT_SLOT_MINUTES', 60);
    }
    if (!defined('APPOINTMENT_SLOT_CAPACITY')) {
        define('APPOINTMENT_SLOT_CAPACITY', 4);
    }
    if (!defined('APPOINTMENT_BOOKING_WINDOW_MINUTES')) {
        define('APPOINTMENT_BOOKING_WINDOW_MINUTES', 4 * 60);
    }

    if (!function_exists('ensure_appointment_slot_capacity_schema')) {
        /**
         * Older databases had a UNIQUE key on (doctor_id, appointment_date,
         * appointment_time) from the one-patient-per-time model. Slot capacity
         * requires several patients at the same time, so drop that key when it
         * is still present. Safe to call from every booking endpoint.
         */
        function ensure_appointment_slot_capacity_schema($conn)
        {
            static $ensured = false;
            if ($ensured) {
                return;
            }
            $ensured = true;

            $constraints = [];
            $res = $conn->query("
                SELECT c.conname AS name
                FROM pg_constraint c
                JOIN pg_class t ON t.oid = c.conrelid
                WHERE t.relname = 'appointments'
                  AND c.contype = 'u'
                  AND (
                    SELECT array_agg(a.attname::text ORDER BY a.attname::text)
                    FROM pg_attribute a
                    WHERE a.attrelid = t.oid
                      AND a.attnum = ANY(c.conkey)
                  ) = ARRAY['appointment_date', 'appointment_time', 'doctor_id']::text[]
            ");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $constraints[] = $row['name'];
                }
            }
            foreach ($constraints as $name) {
                $quoted = str_replace('"', '""', $name);
                $conn->query('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS "' . $quoted . '"');
            }

            $indexes = [];
            $res = $conn->query("
                SELECT indexname AS name
                FROM pg_indexes
                WHERE tablename = 'appointments'
                  AND indexdef ILIKE 'CREATE UNIQUE INDEX%'
                  AND indexdef ILIKE '%doctor_id%'
                  AND indexdef ILIKE '%appointment_date%'
                  AND indexdef ILIKE '%appointment_time%'
            ");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $indexes[] = $row['name'];
                }
            }
            foreach ($indexes as $name) {
                $quoted = str_replace('"', '""', $name);
                $conn->query('DROP INDEX IF EXISTS "' . $quoted . '"');
            }
        }
    }

    function lustre_appt_time_to_minutes($time)
    {
        $parts = explode(':', (string)$time);
        $h = isset($parts[0]) ? (int)$parts[0] : 0;
        $m = isset($parts[1]) ? (int)$parts[1] : 0;
        return ($h * 60) + $m;
    }

    function lustre_appt_minutes_to_time($minutes)
    {
        $minutes = (int)$minutes;
        if ($minutes < 0) $minutes = 0;
        if ($minutes > 1439) $minutes = 1439;
        return sprintf('%02d:%02d', (int)floor($minutes / 60), $minutes % 60);
    }

    function lustre_appt_schedules_for_day($conn, $doctor_id, $day_of_week)
    {
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

        $schedules = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $schedules[] = [
                    'start' => lustre_appt_time_to_minutes($row['start_time']),
                    'end'   => lustre_appt_time_to_minutes($row['end_time']),
                ];
            }
        }
        $stmt->close();
        return $schedules;
    }

    // Clamp every schedule to the first 4 hours of the doctor's day. The day's
    // booking window starts at the earliest schedule start, not at each
    // individual schedule, so a split shift cannot extend the window.
    function lustre_appt_booking_windows(array $schedules)
    {
        if (empty($schedules)) {
            return [];
        }

        $first_start = null;
        foreach ($schedules as $s) {
            if ($first_start === null || $s['start'] < $first_start) {
                $first_start = $s['start'];
            }
        }

        $window_end = $first_start + APPOINTMENT_BOOKING_WINDOW_MINUTES;
        $windows = [];
        foreach ($schedules as $s) {
            $start = max((int)$s['start'], (int)$first_start);
            $end   = min((int)$s['end'], $window_end);
            if (($end - $start) >= APPOINTMENT_SLOT_MINUTES) {
                $windows[] = ['start' => $start, 'end' => $end];
            }
        }
        return $windows;
    }

    function lustre_appt_blocked_ranges($conn, $doctor_id, $date, $day_of_week)
    {
        $stmt = $conn->prepare("
            SELECT start_time, end_time
            FROM doctor_time_blocks
            WHERE doctor_id = ?
            AND (
                (block_type = 'date' AND block_date = ?)
                OR (block_type = 'week' AND ? BETWEEN block_date AND end_date)
                OR (block_type = 'weekly' AND day_of_week = ?)
            )
        ");
        $stmt->bind_param("isss", $doctor_id, $date, $date, $day_of_week);
        $stmt->execute();
        $res = $stmt->get_result();

        $ranges = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $ranges[] = [
                    'start' => lustre_appt_time_to_minutes($row['start_time']),
                    'end'   => lustre_appt_time_to_minutes($row['end_time']),
                ];
            }
        }
        $stmt->close();
        return $ranges;
    }

    function lustre_appt_bookings($conn, $doctor_id, $date, $exclude_appointment_id = 0)
    {
        $sql = "
            SELECT appointment_time, appointment_duration
            FROM appointments
            WHERE doctor_id = ?
            AND appointment_date = ?
            AND status IN ('pending', 'confirmed')
        ";
        if ($exclude_appointment_id > 0) {
            $sql .= " AND id <> ?";
        }

        $stmt = $conn->prepare($sql);
        if ($exclude_appointment_id > 0) {
            $stmt->bind_param("isi", $doctor_id, $date, $exclude_appointment_id);
        } else {
            $stmt->bind_param("is", $doctor_id, $date);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $bookings = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $start = lustre_appt_time_to_minutes($row['appointment_time']);
                $duration = (int)$row['appointment_duration'];
                if ($duration <= 0) {
                    $duration = APPOINTMENT_SLOT_MINUTES;
                }
                $bookings[] = ['start' => $start, 'end' => $start + $duration];
            }
        }
        $stmt->close();
        return $bookings;
    }

    function lustre_appt_slot_usage(array $bookings, $slot_start, $slot_end)
    {
        $count = 0;
        foreach ($bookings as $b) {
            if ($slot_start < $b['end'] && $slot_end > $b['start']) {
                $count++;
            }
        }
        return $count;
    }

    function lustre_appt_slot_hits_lunch_break($slot_start, $slot_end)
    {
        return appointment_interval_overlaps_lunch_break($slot_start * 60, $slot_end * 60);
    }

    function lustre_appt_available_slots($conn, $doctor_id, $date, $exclude_appointment_id = 0)
    {
        $day_of_week = date('l', strtotime($date));
        $schedules = lustre_appt_schedules_for_day($conn, $doctor_id, $day_of_week);
        $windows = lustre_appt_booking_windows($schedules);
        if (empty($windows)) {
            return [];
        }

        $blocked = lustre_appt_blocked_ranges($conn, $doctor_id, $date, $day_of_week);
        $bookings = lustre_appt_bookings($conn, $doctor_id, $date, $exclude_appointment_id);

        $is_today = ($date === date('Y-m-d'));
        $now = time();
        $available = [];

        foreach ($windows as $window) {
            // Start on a real slot boundary even if a legacy schedule starts
            // at an odd time such as 8:15.
            $slot = (int)(ceil($window['start'] / APPOINTMENT_SLOT_MINUTES) * APPOINTMENT_SLOT_MINUTES);
            for (; $slot + APPOINTMENT_SLOT_MINUTES <= $window['end']; $slot += APPOINTMENT_SLOT_MINUTES) {
                $slot_end = $slot + APPOINTMENT_SLOT_MINUTES;

                if ($is_today) {
                    $slot_ts = strtotime($date . ' ' . lustre_appt_minutes_to_time($slot));
                    if ($slot_ts <= $now) {
                        continue;
                    }
                }

                if (lustre_appt_slot_hits_lunch_break($slot, $slot_end)) {
                    continue;
                }

                $is_blocked = false;
                foreach ($blocked as $br) {
                    if ($slot < $br['end'] && $slot_end > $br['start']) {
                        $is_blocked = true;
                        break;
                    }
                }
                if ($is_blocked) {
                    continue;
                }

                if (lustre_appt_slot_usage($bookings, $slot, $slot_end) >= APPOINTMENT_SLOT_CAPACITY) {
                    continue;
                }

                $available[lustre_appt_minutes_to_time($slot)] = true;
            }
        }

        $slots = array_keys($available);
        sort($slots);
        return $slots;
    }

    // True when the slot still sits inside an active schedule and is not
    // covered by a time-off block. Used to find appointments invalidated by a
    // schedule change, independent of the first-4-hours booking window.
    function lustre_appt_slot_within_schedule($conn, $doctor_id, $date, $time, $duration = null)
    {
        $slot_start = lustre_appt_time_to_minutes($time);
        $duration = ($duration === null || (int)$duration <= 0)
            ? APPOINTMENT_SLOT_MINUTES
            : (int)$duration;
        $slot_end = $slot_start + $duration;

        $day_of_week = date('l', strtotime($date));
        $covered = false;
        foreach (lustre_appt_schedules_for_day($conn, $doctor_id, $day_of_week) as $schedule) {
            if ($slot_start >= $schedule['start'] && $slot_end <= $schedule['end']) {
                $covered = true;
                break;
            }
        }
        if (!$covered) {
            return false;
        }

        foreach (lustre_appt_blocked_ranges($conn, $doctor_id, $date, $day_of_week) as $block) {
            if ($slot_start < $block['end'] && $slot_end > $block['start']) {
                return false;
            }
        }

        return true;
    }

    // Returns null when the slot is bookable, otherwise a human-readable error.
    function lustre_appt_validate_slot($conn, $doctor_id, $date, $time, $exclude_appointment_id = 0)
    {
        ensure_appointment_slot_capacity_schema($conn);

        $slot_start = lustre_appt_time_to_minutes($time);
        $slot_end = $slot_start + APPOINTMENT_SLOT_MINUTES;

        if (($slot_start % APPOINTMENT_SLOT_MINUTES) !== 0) {
            return "Please choose one of the available time slots";
        }

        $day_of_week = date('l', strtotime($date));
        $schedules = lustre_appt_schedules_for_day($conn, $doctor_id, $day_of_week);
        $windows = lustre_appt_booking_windows($schedules);

        if (empty($windows)) {
            return "This doctor is not available on the selected date";
        }

        $in_window = false;
        foreach ($windows as $w) {
            if ($slot_start >= $w['start'] && $slot_end <= $w['end']) {
                $in_window = true;
                break;
            }
        }
        if (!$in_window) {
            $hours = (int)(APPOINTMENT_BOOKING_WINDOW_MINUTES / 60);
            return "Appointments can only be booked during the first {$hours} hours of the doctor's availability";
        }

        if (lustre_appt_slot_hits_lunch_break($slot_start, $slot_end)) {
            return "Appointments are unavailable from 12:00 PM to 1:00 PM";
        }

        if ($date === date('Y-m-d') && strtotime($date . ' ' . $time) <= time()) {
            return "This time is no longer available. Please pick another time.";
        }

        $blocked = lustre_appt_blocked_ranges($conn, $doctor_id, $date, $day_of_week);
        foreach ($blocked as $br) {
            if ($slot_start < $br['end'] && $slot_end > $br['start']) {
                return "This time is not available (the doctor has time off in this window). Please pick another time.";
            }
        }

        $bookings = lustre_appt_bookings($conn, $doctor_id, $date, $exclude_appointment_id);
        $usage = lustre_appt_slot_usage($bookings, $slot_start, $slot_end);
        if ($usage >= APPOINTMENT_SLOT_CAPACITY) {
            return "This time slot is already full (" . APPOINTMENT_SLOT_CAPACITY . " patients max). Please pick another time.";
        }

        return null;
    }
}
