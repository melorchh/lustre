<?php
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/api.php';

$wantJson = (isset($_SERVER['HTTP_X_LUSTRE_API']) && $_SERVER['HTTP_X_LUSTRE_API'] === '1');

function dra_out($msg, $isError = false, $status = 400) {
    global $wantJson;
    if ($wantJson) {
        if ($isError) { api_fail($msg, $status); }
        api_ok();
    }
    echo $msg;
    exit;
}

if (!isset($_SESSION["doctor_id"])) {
    if ($wantJson) { api_fail('Unauthorized', 401); }
    header("Content-Type: text/plain; charset=utf-8");
    echo "Unauthorized";
    exit;
}
require_once __DIR__ . '/../src/db.php';

if (!$wantJson) { header('Content-Type: text/plain; charset=utf-8'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { dra_out("Invalid request", true, 405); }

$doctor_id   = (int)$_SESSION['doctor_id'];
$action      = isset($_GET['action']) ? $_GET['action'] : '';
$in          = api_input();
if (empty($in)) { $in = $_POST; }
$appt_id     = isset($in['appointment_id']) ? (int)$in['appointment_id'] : 0;

if (!$appt_id) { dra_out("Invalid appointment", true); }

// Verify this appointment belongs to the logged-in doctor
$check = $conn->prepare("SELECT doctor_id, status FROM appointments WHERE id = ?");
$check->bind_param("i", $appt_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) { $check->close(); dra_out("Appointment not found", true, 404); }
$check->bind_result($owner_id, $current_status);
$check->fetch();
$check->close();
if ((int)$owner_id !== $doctor_id) { dra_out("You are not allowed to modify this appointment", true, 403); }

/**
 * When a vaccination appointment is completed, record the dose that was just
 * administered so it appears on the patient's vaccine card. The service string
 * is like "BCG (Dose 2)" or "Hepatitis B Dose 3".
 */
function record_vaccine_dose($conn, $appt) {
    $svc = ($appt['service_requested'] ?? '');
    if (trim($svc) === '') { return; }

    $vaccine  = trim($svc);
    $dose_num = 1;
    $dose     = 'Dose';
    $pattern  = '/^(.*?)\s*\(?\s*dose\s*(\d+)\s*\)?\s*$/i';
    if (preg_match($pattern, $svc, $m)) {
        $vaccine  = trim($m[1]);
        $dose_num = (int)$m[2];
        $dose     = 'Dose ' . $dose_num;
    }

    $stmt = $conn->prepare("SELECT id FROM vaccinations WHERE patient_id=? AND vaccine_name=? AND dose_label=? AND administered_date=CURRENT_DATE LIMIT 1");
    $stmt->bind_param("iss", $appt['patient_id'], $vaccine, $dose);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        $ins = $conn->prepare("INSERT INTO vaccinations (patient_id, vaccine_name, dose_label, administered_date, notes) VALUES (?, ?, ?, CURRENT_DATE, ?)");
        $note = 'Recorded from completed appointment (' . date('M j, Y') . ').';
        $ins->bind_param("isss", $appt['patient_id'], $vaccine, $dose, $note);
        $ins->execute();
        $ins->close();
    }

    // This dose's due date is now fulfilled: clear the next_due_date on the
    // previous dose of the same vaccine so the client stops showing "Due soon"
    // / "Overdue" for it. Iterate over that vaccine's records and clear any
    // next_due_date that is no longer ahead of the dose just administered.
    $prev = "Dose " . ($dose_num - 1);
    if ($dose_num > 1) {
        $prevId = null;
        $q = $conn->prepare("SELECT id FROM vaccinations WHERE patient_id=? AND vaccine_name=? AND dose_label=? ORDER BY administered_date DESC, id DESC LIMIT 1");
        $q->bind_param("iss", $appt['patient_id'], $vaccine, $prev);
        $q->execute();
        $q->store_result();
        if ($q->num_rows > 0) {
            $q->bind_result($prevId);
            $q->fetch();
        }
        $q->close();
        if ($prevId) {
            $clr = $conn->prepare("UPDATE vaccinations SET next_due_date = NULL WHERE id=?");
            $clr->bind_param("i", $prevId);
            $clr->execute();
            $clr->close();
        }
    }
}

function complete_appointment($conn, $appt_id, $result) {
    $patient = null;
    $check = $conn->prepare("SELECT a.patient_id, a.service_requested FROM appointments a WHERE a.id = ?");
    $check->bind_param("i", $appt_id);
    $check->execute();
    $res = $check->get_result();
    if ($row = $res->fetch_assoc()) { $patient = $row; }
    $check->close();

    if ($result !== '') {
        $stmt = $conn->prepare(
            "UPDATE appointments SET status = 'completed', result = ?, result_date = NOW() WHERE id = ?"
        );
        $stmt->bind_param("si", $result, $appt_id);
    } else {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
        $stmt->bind_param("i", $appt_id);
    }
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok && $patient) {
        record_vaccine_dose($conn, $patient);
    }
    return $ok;
}

if ($action === 'complete_and_result') {
    $result = isset($in['result']) ? trim($in['result']) : '';
    if ($result === '') { dra_out("Please enter the appointment result", true); }
    if ($current_status === 'cancelled') { dra_out("Cannot add a result to a cancelled appointment", true); }

    $ok = complete_appointment($conn, $appt_id, $result);
    dra_out($ok ? "success" : "Failed", !$ok);
    $conn->close();

} elseif ($action === 'save_result') {
    $result = isset($in['result']) ? trim($in['result']) : '';
    if ($result === '') { dra_out("Please enter the appointment result", true); }
    if ($current_status === 'cancelled') { dra_out("Cannot add a result to a cancelled appointment", true); }

    $stmt = $conn->prepare(
        "UPDATE appointments SET result = ?, result_date = NOW() WHERE id = ?"
    );
    $stmt->bind_param("si", $result, $appt_id);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();
    dra_out($ok ? "success" : "Failed: " . $err, !$ok);
    $conn->close();

} elseif ($action === 'complete') {
    // Allow completing without a result (result optional)
    if ($current_status === 'cancelled') { dra_out("Cannot complete a cancelled appointment", true); }
    $result = isset($in['result']) ? trim($in['result']) : '';
    $ok = complete_appointment($conn, $appt_id, $result);
    dra_out($ok ? "success" : "Failed", !$ok);
    $conn->close();

} else {
    dra_out("Unknown action", true);
}

$conn->close();
?>
