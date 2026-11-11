<?php
/**
 * Appointment email and SMS notifications for LustreMDC.
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
     * @param string|null $suggested_date Optional replacement date for cancellation emails
     * @param string|null $suggested_time Optional replacement time for cancellation emails
     * @return bool
     */
    function send_appointment_email($conn, $appointment_id, $event, $suggested_date = null, $suggested_time = null, $reschedule_url = null)
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
                if ($reschedule_url === null) {
                    require_once __DIR__ . '/../src/appointment_reschedule_tokens.php';
                    try {
                        $reschedule_token = create_appointment_reschedule_token($conn, $a['id']);
                    } catch (Throwable $e) {
                        error_log('Unable to create appointment reschedule link (appt=' . $appointment_id . '): ' . $e->getMessage());
                        return false;
                    }
                    $reschedule_url = appointment_page_url('email_reschedule.php', array('token' => $reschedule_token));
                }
                $subject = 'Appointment Cancelled — Reschedule Online — ' . $ref;
                $title   = 'Your appointment has been cancelled.';
                $body    = "Hi {$name},\n\n"
                    . "This is to confirm that appointment {$ref} with {$doc} on {$date} at {$time} has been cancelled.\n\n"
                    . "We're sorry for the inconvenience. ";
                if ($suggested_date !== null && $suggested_time !== null) {
                    $suggested_date_text = date('F j, Y', strtotime($suggested_date));
                    $suggested_time_text = date('g:i A', strtotime($suggested_time));
                    $body .= "Your doctor suggested {$suggested_date_text} at {$suggested_time_text} as a replacement. This is only a suggestion and is not booked yet.\n\n";
                }
                $body .= "Choose a new date and time here:\n"
                    . "{$reschedule_url}\n\n"
                    . "Choose an available date and time without signing in. This secure link expires in 7 days and can only be used once.\n\n"
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

    if (!function_exists('send_appointment_sms')) {
        function send_appointment_sms($conn, $appointment_id, $event, $reschedule_url = null)
        {
            if (!in_array($event, array('confirmed', 'cancelled', 'rescheduled', 'reminder'), true)) {
                return false;
            }

            $stmt = $conn->prepare("
                SELECT a.id, a.appointment_date, a.appointment_time,
                       p.contact AS patient_contact, d.name AS doctor_name
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN doctors d ON a.doctor_id = d.id
                WHERE a.id = ?
            ");
            $stmt->bind_param('i', $appointment_id);
            if (!$stmt->execute()) {
                error_log('Unable to load appointment for SMS (appt=' . $appointment_id . '): ' . $stmt->error);
                $stmt->close();
                return false;
            }
            $result = $stmt->get_result();
            $appointment = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if (!$appointment) {
                error_log('SMS notification skipped: appointment not found (appt=' . $appointment_id . ').');
                return false;
            }
            if (trim((string)$appointment['patient_contact']) === '') {
                error_log('SMS notification skipped: patient has no contact number (appt=' . $appointment_id . ').');
                return false;
            }

            require_once __DIR__ . '/../src/TextBeeSMS.php';

            $reference = 'APPT-' . str_pad($appointment['id'], 6, '0', STR_PAD_LEFT);
            $date = date('M j, Y', strtotime($appointment['appointment_date']));
            $time = date('g:i A', strtotime($appointment['appointment_time']));
            $doctor = 'Dr. ' . $appointment['doctor_name'];

            switch ($event) {
                case 'confirmed':
                    $message = "LustreMDC: Your appointment {$reference} with {$doctor} is confirmed for {$date} at {$time}.";
                    break;
                case 'cancelled':
                    // Keep the link message short: long URL-bearing SMS can be rejected
                    // or split by carriers/devices that do not reliably handle multipart SMS.
                    $message = $reschedule_url !== null
                        ? "LustreMDC: Reschedule (7 days): {$reschedule_url}"
                        : "LustreMDC: Appointment {$reference} cancelled. Please contact the clinic to reschedule.";
                    break;
                case 'rescheduled':
                    $message = "LustreMDC: Appointment {$reference} with {$doctor} is now scheduled for {$date} at {$time}. Your request is pending clinic confirmation.";
                    break;
                case 'reminder':
                    $message = "LustreMDC reminder: Appointment {$reference} with {$doctor} is on {$date} at {$time}. Please arrive on time.";
                    break;
                default:
                    return false;
            }

            return textbee_send_sms($appointment['patient_contact'], $message);
        }
    }

    if (!function_exists('send_appointment_notification')) {
        function send_appointment_notification($conn, $appointment_id, $event, $suggested_date = null, $suggested_time = null)
        {
            $reschedule_url = null;
            if ($event === 'cancelled') {
                try {
                    require_once __DIR__ . '/../src/appointment_reschedule_tokens.php';
                    $token = create_appointment_reschedule_token($conn, $appointment_id);
                    $reschedule_url = appointment_page_url('email_reschedule.php', array('token' => $token));
                } catch (Throwable $e) {
                    error_log('Unable to prepare appointment reschedule notification (appt=' . $appointment_id . '): ' . $e->getMessage());
                }
            }

            $email_sent = send_appointment_email($conn, $appointment_id, $event, $suggested_date, $suggested_time, $reschedule_url);
            $sms_sent = send_appointment_sms($conn, $appointment_id, $event, $reschedule_url);

            return $email_sent || $sms_sent;
        }
    }
}
