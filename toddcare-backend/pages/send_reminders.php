<?php
// ===========================================================================
// Automated appointment reminder — run ~1 hour before the schedule.
// Finds confirmed/pending appointments starting in roughly 1 hour and emails
// the patient a reminder. Marks each as reminded so it is never sent twice,
// even if this script runs multiple times within the window.
//
// Vercel does not support server-cron in the way XAMPP's Task Scheduler did,
// so this endpoint is trigger-based instead:
//
//   CLI (local / Task Scheduler):
//     C:\xampp\php\php.exe send_reminders.php
//
//   HTTP (Vercel cron / external scheduler such as cron-job.org or a second
//   serverless provider). Set CRON_SECRET in your environment, then:
//     curl -H "Authorization: Bearer $CRON_SECRET" \
//          https://yourapp.vercel.app/api/send_reminders.php
//
// If you are on a Vercel plan that supports the crons feature, you may add a
// "crons" block to vercel.json — but it is deliberately NOT included by
// default because Hobby plans reject sub-daily schedules.
// ===========================================================================

$secret = getenv('CRON_SECRET');

$isCli = (php_sapi_name() === 'cli');
$authed = false;

if ($isCli) {
    $authed = true;
} else {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (preg_match('/^\s*Bearer\s+(.+)$/i', $authHeader, $m)) {
        $authed = ($secret !== false && $secret !== '' && hash_equals($secret, trim($m[1])));
    }
}

if (!$authed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit(1);
}

include __DIR__ . '/../src/db.php';

// Who to remind: appointments starting in roughly 1 hour.
// Appointment dates/times are stored as local (Asia/Manila) wall-clock values
// on the patients table side, PHP timezone is set in db.php. We compute the
// target window in that same local time to stay consistent with the data.
$now = date('Y-m-d H:i:s');                 // local "now"
$start_at = date('Y-m-d H:i:s', time() + 45 * 60); // appointment starts 45..75 min from now
$end_at   = date('Y-m-d H:i:s', time() + 75 * 60);

$stmt = $conn->prepare("
    SELECT a.id
    FROM appointments a
    WHERE a.reminder_sent = 0
      AND a.status IN ('pending','confirmed')
      AND (a.appointment_date + a.appointment_time)
          BETWEEN ? AND ?
");
$stmt->bind_param('ss', $start_at, $end_at);
$stmt->execute();
$result = $stmt->get_result();

$ids = array();
while ($row = $result->fetch_assoc()) {
    $ids[] = (int)$row['id'];
}
$stmt->close();

echo '[' . date('Y-m-d H:i:s') . '] Found ' . count($ids) . " appointment(s) due for a reminder.\n";

require_once __DIR__ . '/appointment_mailer.php';

$sent = 0;
foreach ($ids as $id) {
    if (send_appointment_email($conn, $id, 'reminder')) {
        $upd = $conn->prepare("UPDATE appointments SET reminder_sent = 1 WHERE id = ?");
        $upd->bind_param('i', $id);
        $upd->execute();
        $upd->close();
        $sent++;
        echo '[' . date('H:i:s') . "] Reminder sent for appointment #{$id}\n";
    } else {
        // Leave reminder_sent = 0 so it retries on the next run.
        echo '[' . date('H:i:s') . "] Failed to send reminder for appointment #{$id} (will retry)\n";
    }
}

echo '[' . date('H:i:s') . "] Done. Sent {$sent} reminder(s).\n";

$conn->close();