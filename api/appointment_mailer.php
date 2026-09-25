<?php
/**
 * Appointment email notifications for LustreMDC.
 *
 * Reusable helper that looks up an appointment and emails the patient
 * for key events. Designed to be non-fatal: any SMTP failure is logged and
 * swallowed so it never breaks the main booking/admin flow.
 *
 * Usage:
 *   require __DIR__ . '/appointment_mailer.php';
 *   send_appointment_email($conn, $appointment_id, 'booked');   // or 'confirmed','cancelled','completed','rescheduled'
 */

if (!function_exists('send_appointment_email')) {

    /**
     * @param mysqli $conn           Active DB connection
     * @param int    $appointment_id Appointment to notify about
     * @param string $event          booked | confirmed | cancelled | completed | rescheduled
     * @return bool
     */
    function send_appointment_email($conn, $appointment_id, $event)
    {
        $allowed = array('booked', 'confirmed', 'cancelled', 'completed', 'rescheduled', 'reminder');
        if (!in_array($event, $allowed, true)) {
            return false;
        }

        // Look up the appointment with patient + doctor details
        $stmt = $conn->prepare("
            SELECT
                a.id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.payment_status,
                a.service_requested,
                p.name     AS patient_name,
                p.email    AS patient_email,
                d.name     AS doctor_name,
                d.specialty
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors  d ON a.doctor_id  = d.id
            WHERE a.id = ?
        ");
        $stmt->bind_param('i', $appointment_id);
        $stmt->execute();
        $a = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$a || empty($a['patient_email'])) {
            return false;
        }

        $ref   = 'APPT-' . str_pad($a['id'], 6, '0', STR_PAD_LEFT);
        $date  = date('F j, Y', strtotime($a['appointment_date']));
        $time  = date('g:i A', strtotime($a['appointment_time']));
        $name  = $a['patient_name'];
        $to    = $a['patient_email'];
        $doc   = 'Dr. ' . $a['doctor_name'] . ' (' . $a['specialty'] . ')';
        $svc   = 'Service:  ' . (trim((string)$a['service_requested']) !== '' ? $a['service_requested'] : 'N/A') . "\n";

        switch ($event) {
            case 'booked':
                $subject = 'Appointment Booked â€” ' . $ref;
                $title   = 'Your appointment request has been received.';
                $body    = "Hi {$name},\n\n"
                    . "We received your appointment request with reference {$ref}.\n\n"
                    . "Doctor:   {$doc}\n"
                    . "{$svc}"
                    . "Date:     {$date}\n"
                    . "Time:     {$time}\n"
                    . "Status:   Pending confirmation\n\n"
                    . "Our clinic will confirm your appointment shortly. You can view or manage it in your patient dashboard.\n\n"
                    . "â€” LustreMDC Clinics & Diagnostics";
                break;

            case 'confirmed':
                $subject = 'Appointment Confirmed â€” ' . $ref;
                $title   = 'Your appointment is confirmed.';
                $body    = "Hi {$name},\n\n"
                    . "Great news! Your appointment has been confirmed.\n\n"
                    . "Reference: {$ref}\n"
                    . "Doctor:   {$doc}\n"
                    . "{$svc}"
                    . "Date:     {$date}\n"
                    . "Time:     {$time}\n\n"
                    . "Please arrive on time and present your booking reference at the front desk.\n\n"
                    . "â€” LustreMDC Clinics & Diagnostics";
                break;

            case 'cancelled':
                $subject = 'Appointment Cancelled â€” ' . $ref;
                $title   = 'Your appointment has been cancelled.';
                $body    = "Hi {$name},\n\n"
                    . "This is to confirm that appointment {$ref} with {$doc} on {$date} at {$time} has been cancelled.\n\n"
                    . "If this was a mistake or you'd like to rebook, please do so through your patient dashboard or contact the clinic.\n\n"
                    . "â€” LustreMDC Clinics & Diagnostics";
                break;

            case 'completed':
                $subject = 'Appointment Completed â€” ' . $ref;
                $title   = 'Thank you for your visit.';
                $body    = "Hi {$name},\n\n"
                    . "Your appointment {$ref} with {$doc} has been marked as completed.\n\n"
                    . "Thank you for choosing LustreMDC Clinics & Diagnostics. We hope to see you again!\n\n"
                    . "â€” LustreMDC Clinics & Diagnostics";
                break;

            case 'rescheduled':
                $subject = 'Appointment Rescheduled â€” ' . $ref;
                $title   = 'Your appointment has been rescheduled.';
                $body    = "Hi {$name},\n\n"
                    . "Your appointment has been rescheduled.\n\n"
                    . "Reference: {$ref}\n"
                    . "Doctor:   {$doc}\n"
                    . "New Date:  {$date}\n"
                    . "New Time:  {$time}\n\n"
                    . "Please review the updated schedule and contact the clinic if you have any questions.\n\n"
                    . "â€” LustreMDC Clinics & Diagnostics";
                break;

            case 'reminder':
                $subject = 'Appointment Reminder â€” ' . $ref . ' in 1 hour';
                $title   = 'Your appointment is coming up.';
                $body    = "Hi {$name},\n\n"
                    . "This is a friendly reminder that your appointment starts in about 1 hour.\n\n"
                    . "Reference: {$ref}\n"
                    . "Doctor:   {$doc}\n"
                    . "Date:     {$date}\n"
                    . "Time:     {$time}\n\n"
                    . "Please arrive on time and present your booking reference at the front desk.\n\n"
                    . "â€” LustreMDC Clinics & Diagnostics";
                break;

            default:
                return false;
        }

        try {
            require_once __DIR__ . '/../toddcare-backend/src/SMTPMailer.php';
            $mail = new SMTPMailer();
            return $mail->send($to, $subject, $body, $name);
        } catch (Exception $e) {
            error_log('Appointment email failed (event=' . $event . ', appt=' . $appointment_id . '): ' . $e->getMessage());
            return false;
        }
    }
}
