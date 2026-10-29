<?php

function appointment_interval_overlaps_lunch_break($start_seconds, $end_seconds) {
    return $start_seconds < (13 * 60 * 60) && $end_seconds > (12 * 60 * 60);
}

function appointment_time_overlaps_lunch_break($time, $duration_minutes = 60) {
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', (string)$time)) {
        return false;
    }

    $parts = explode(':', $time);
    $start_seconds = ((int)$parts[0] * 60 * 60) + ((int)$parts[1] * 60);
    if (isset($parts[2])) {
        $start_seconds += (int)$parts[2];
    }

    $end_seconds = $start_seconds + ((int)$duration_minutes * 60);
    return appointment_interval_overlaps_lunch_break($start_seconds, $end_seconds);
}
