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

if (!function_exists('appointment_page_url')) {
    /**
     * Build an absolute URL to a patient-facing page. Uses APP_URL when set,
     * otherwise derives the public origin/path from the current request.
     */
    function appointment_page_url($page, array $query = array())
    {
        $base = '';
        if (function_exists('lustre_env')) {
            $base = (string)lustre_env('APP_URL', '');
        }
        if ($base === '') {
            $base = (string)getenv('APP_URL');
        }
        if ($base === '' && isset($_SERVER['APP_URL'])) {
            $base = (string)$_SERVER['APP_URL'];
        }

        if ($base === '') {
            $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off';
            $scheme = $https ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'localhost';
            $script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '/';
            $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
            $base = $scheme . '://' . $host . $dir;
        }

        $url = rtrim($base, '/') . '/' . ltrim($page, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }
}

if (!function_exists('send_appointment_email')) {

    /**
     * @param mysqli $conn           Active DB connection
     * @param int    $appointment_id Appointment to notify about
     * @param string $event          booked | confirmed | cancelled | completed | rescheduled
     * @return bool
     */
    function send_appointment_email($conn, $appointment_id, $event)
    {
        $allowed = array('booked', 'confirmed', 'cancelled', 'completed', 'rescheduled', 'reminder', 'result');
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
                a.result,
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
                $subject = 'Appointment Booked — ' . $ref;
                $title   = 'Your appointment request has been received.';
                $body    = "Hi {$name},\n\n"
                    . "We received your appointment request with reference {$ref}.\n\n"
                    . "Doctor:   {$doc}\n"
                    . "{$svc}"
                    . "Date:     {$date}\n"
                    . "Time:     {$time}\n"
                    . "Status:   Pending confirmation\n\n"
                    . "Our clinic will confirm your appointment shortly. You can view or manage it in your patient dashboard.\n\n"
                    . "— LustreMDC Clinics & Diagnostics";
                break;

            case 'confirmed':
                $subject = 'Appointment Confirmed — ' . $ref;
                $title   = 'Your appointment is confirmed.';
                $body    = "Hi {$name},\n\n"
                    . "Great news! Your appointment has been confirmed.\n\n"
                    . "Reference: {$ref}\n"
                    . "Doctor:   {$doc}\n"
                    . "{$svc}"
                    . "Date:     {$date}\n"
                    . "Time:     {$time}\n\n"
                    . "Please arrive on time and present your booking reference at the front desk.\n\n"
                    . "— LustreMDC Clinics & Diagnostics";
                break;

            case 'cancelled':
                $reschedule_url = appointment_page_url('my_appointments.php', array('reschedule' => $a['id']));
                $subject = 'Appointment Cancelled — Reschedule Online — ' . $ref;
                $title   = 'Your appointment has been cancelled.';
                $body    = "Hi {$name},\n\n"
                    . "This is to confirm that appointment {$ref} with {$doc} on {$date} at {$time} has been cancelled.\n\n"
                    . "We're sorry for the inconvenience. You can choose a new date and time here:\n"
                    . "{$reschedule_url}\n\n"
                    . "Sign in to your patient account if prompted, then pick the date and time that works best for you.\n\n"
                    . "— LustreMDC Clinics & Diagnostics";
                break;

            case 'completed':
                $subject = 'Appointment Completed — ' . $ref;
                $title   = 'Thank you for your visit.';
                $body    = "Hi {$name},\n\n"
                    . "Your appointment {$ref} with {$doc} has been marked as completed.\n\n"
                    . "Thank you for choosing LustreMDC Clinics & Diagnostics. We hope to see you again!\n\n"
                    . "— LustreMDC Clinics & Diagnostics";
                break;

            case 'rescheduled':
                $subject = 'Appointment Rescheduled — ' . $ref;
                $title   = 'Your appointment has been rescheduled.';
                $body    = "Hi {$name},\n\n"
                    . "Your appointment has been rescheduled.\n\n"
                    . "Reference: {$ref}\n"
                    . "Doctor:   {$doc}\n"
                    . "New Date:  {$date}\n"
                    . "New Time:  {$time}\n\n"
                    . "Please review the updated schedule and contact the clinic if you have any questions.\n\n"
                    . "— LustreMDC Clinics & Diagnostics";
                break;

            case 'reminder':
                $subject = 'Appointment Reminder — ' . $ref . ' in 1 hour';
                $title   = 'Your appointment is coming up.';
                $body    = "Hi {$name},\n\n"
                    . "This is a friendly reminder that your appointment starts in about 1 hour.\n\n"
                    . "Reference: {$ref}\n"
                    . "Doctor:   {$doc}\n"
                    . "Date:     {$date}\n"
                    . "Time:     {$time}\n\n"
                    . "Please arrive on time and present your booking reference at the front desk.\n\n"
                    . "— LustreMDC Clinics & Diagnostics";
                break;

            case 'result':
                $subject = 'Appointment Result - ' . $ref;
                $title   = 'Your appointment result is ready.';
                $result  = trim((string)$a['result']);
                $body    = "Hi {$name},\n\n"
                    . "The result / findings for your appointment {$ref} are now available.\n\n"
                    . "Doctor:   {$doc}\n"
                    . "{$svc}"
                    . "Date:     {$date}\n"
                    . "Time:     {$time}\n\n"
                    . "Result / Findings:\n"
                    . "{$result}\n\n"
                    . "You can also view and download the full result from your patient dashboard under My Appointments.\n\n"
                    . "- LustreMDC Clinics & Diagnostics";
                break;

            default:
                return false;
        }

        try {
            require_once __DIR__ . '/../src/SMTPMailer.php';
            $mail = new SMTPMailer();
            return $mail->send($to, $subject, $body, $name);
        } catch (Exception $e) {
            error_log('Appointment email failed (event=' . $event . ', appt=' . $appointment_id . '): ' . $e->getMessage());
            return false;
        }
    }
}
