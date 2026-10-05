<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_id"])) { header("Content-Type: text/plain; charset=utf-8"); echo "Unauthorized"; exit; }
require_once __DIR__ . '/../src/db.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo "Invalid request"; exit; }

$doctor_id   = (int)$_SESSION['doctor_id'];
$action      = isset($_GET['action']) ? $_GET['action'] : '';
$appt_id     = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;

if (!$appt_id) { echo "Invalid appointment"; exit; }

// Verify this appointment belongs to the logged-in doctor
$check = $conn->prepare("SELECT doctor_id, status FROM appointments WHERE id = ?");
$check->bind_param("i", $appt_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) { $check->close(); echo "Appointment not found"; $conn->close(); exit; }
$check->bind_result($owner_id, $current_status);
$check->fetch();
$check->close();
if ((int)$owner_id !== $doctor_id) { echo "You are not allowed to modify this appointment"; $conn->close(); exit; }

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
    $result = isset($_POST['result']) ? trim($_POST['result']) : '';
    if ($result === '') { echo "Please enter the appointment result"; $conn->close(); exit; }

    $ok = complete_appointment($conn, $appt_id, $result);
    echo $ok ? "success" : "Failed";
    $conn->close();
    exit;

} elseif ($action === 'save_result') {
    $result = isset($_POST['result']) ? trim($_POST['result']) : '';
    if ($result === '') { echo "Please enter the appointment result"; $conn->close(); exit; }

    $stmt = $conn->prepare(
        "UPDATE appointments SET result = ?, result_date = NOW() WHERE id = ?"
    );
    $stmt->bind_param("si", $result, $appt_id);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();
    $conn->close();
    exit;

} elseif ($action === 'complete') {
    // Allow completing without a result (result optional)
    $result = isset($_POST['result']) ? trim($_POST['result']) : '';
    $ok = complete_appointment($conn, $appt_id, $result);
    echo $ok ? "success" : "Failed";
    $conn->close();
    exit;

} else {
    echo "Unknown action";
}

$conn->close();
?>
