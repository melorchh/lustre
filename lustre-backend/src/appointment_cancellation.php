<?php
// Cancels future appointments that a schedule change made impossible and
// emails each affected patient a link to pick a new date and time.

require_once __DIR__ . '/appointment_slots.php';
require_once __DIR__ . '/time_blocks_schema.php';
require_once __DIR__ . '/../pages/appointment_mailer.php';

if (!function_exists('cancel_unavailable_appointments')) {
    /**
     * Cancel upcoming appointments that no longer fit an active schedule.
     *
     * @return int Number of appointments cancelled.
     */
    function cancel_unavailable_appointments($conn, $doctor_id)
    {
        $doctor_id = (int)$doctor_id;
        if ($doctor_id <= 0) {
            return 0;
        }

        ensure_time_blocks_schema($conn);

        $stmt = $conn->prepare("
            SELECT id, appointment_date, appointment_time, appointment_duration
            FROM appointments
            WHERE doctor_id = ?
              AND appointment_date >= CURRENT_DATE
              AND status IN ('pending', 'confirmed')
            ORDER BY appointment_date, appointment_time
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $doctor_id);
        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $rows = [];
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();

        $to_cancel = [];
        foreach ($rows as $row) {
            if (!lustre_appt_slot_within_schedule(
                $conn,
                $doctor_id,
                $row['appointment_date'],
                $row['appointment_time'],
                isset($row['appointment_duration']) ? $row['appointment_duration'] : null
            )) {
                $to_cancel[] = (int)$row['id'];
            }
        }
        if (empty($to_cancel)) {
            return 0;
        }

        $upd = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'confirmed')");
        if (!$upd) {
            return 0;
        }

        $cancelled = 0;
        foreach ($to_cancel as $appointment_id) {
            $upd->bind_param("i", $appointment_id);
            if ($upd->execute() && $upd->affected_rows > 0) {
                $cancelled++;
                send_appointment_email($conn, $appointment_id, 'cancelled');
            }
        }
        $upd->close();

        return $cancelled;
    }
}
