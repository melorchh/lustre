<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_id"])) { header("Content-Type: text/plain; charset=utf-8"); echo "Unauthorized"; exit; }
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/appointment_slots.php';
require_once __DIR__ . '/../src/doctor_capacity.php';
require_once __DIR__ . '/../src/time_blocks_schema.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo "Invalid request"; exit; }

$doctor_id   = (int)$_SESSION['doctor_id'];
$action      = isset($_GET['action']) ? $_GET['action'] : '';
$appt_id     = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;

if (!$appt_id) { echo "Invalid appointment"; exit; }

// Verify this appointment belongs to the logged-in doctor
$check = $conn->prepare("SELECT doctor_id, status, patient_id FROM appointments WHERE id = ?");
$check->bind_param("i", $appt_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) { $check->close(); echo "Appointment not found"; $conn->close(); exit; }
$check->bind_result($owner_id, $current_status, $appointment_patient_id);
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
    if ($current_status === 'cancelled') { echo "Cannot add a result to a cancelled appointment"; $conn->close(); exit; }

    $ok = complete_appointment($conn, $appt_id, $result);
    echo $ok ? "success" : "Failed";
    $conn->close();
    exit;

} elseif ($action === 'save_result') {
    $result = isset($_POST['result']) ? trim($_POST['result']) : '';
    if ($result === '') { echo "Please enter the appointment result"; $conn->close(); exit; }
    if ($current_status === 'cancelled') { echo "Cannot add a result to a cancelled appointment"; $conn->close(); exit; }

    $followup_date = isset($_POST['followup_date']) ? trim($_POST['followup_date']) : '';
    $followup_time = isset($_POST['followup_time']) ? trim($_POST['followup_time']) : '';
    if (($followup_date === '') !== ($followup_time === '')) {
        echo "Choose both a follow-up date and time";
        $conn->close();
        exit;
    }

    if ($followup_date !== '') {
        if ($current_status !== 'completed') {
            echo "Follow-up appointments can only be scheduled for completed visits";
            $conn->close();
            exit;
        }

        $selected_date = strtotime($followup_date);
        if ($selected_date === false || date('Y-m-d', $selected_date) !== $followup_date || $followup_date <= date('Y-m-d')) {
            echo "Follow-up date must be a valid future date";
            $conn->close();
            exit;
        }
        if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $followup_time)) {
            echo "Invalid follow-up time";
            $conn->close();
            exit;
        }

        ensure_time_blocks_schema($conn);
        ensure_doctor_capacity_schema($conn);
        ensure_appointment_slot_capacity_schema($conn);
        $pdo = $conn->getPdo();
        if (!$pdo->beginTransaction()) {
            http_response_code(500);
            echo "Could not start follow-up scheduling. Please try again.";
            $conn->close();
            exit;
        }

        $patient_lock = $conn->prepare("SELECT id, email, is_walk_in FROM patients WHERE id = ? FOR UPDATE");
        $patient_lock->bind_param("i", $appointment_patient_id);
        if (!$patient_lock->execute()) {
            $patient_lock->close();
            $pdo->rollBack();
            http_response_code(500);
            echo "Could not verify the patient account. Please try again.";
            $conn->close();
            exit;
        }
        $patient_lock->store_result();
        if ($patient_lock->num_rows === 0) {
            $patient_lock->close();
            $pdo->rollBack();
            echo "Patient account not found";
            $conn->close();
            exit;
        }
        $patient_lock->bind_result($followup_patient_id, $patient_email, $patient_is_walk_in);
        $patient_lock->fetch();
        $patient_lock->close();

        $locked_appointment = $conn->prepare("SELECT status FROM appointments WHERE id = ? AND doctor_id = ? AND patient_id = ? FOR UPDATE");
        $locked_appointment->bind_param("iii", $appt_id, $doctor_id, $appointment_patient_id);
        if (!$locked_appointment->execute()) {
            $locked_appointment->close();
            $pdo->rollBack();
            http_response_code(500);
            echo "Could not verify the completed appointment. Please try again.";
            $conn->close();
            exit;
        }
        $locked_appointment->bind_result($locked_status);
        if (!$locked_appointment->fetch() || $locked_status !== 'completed') {
            $locked_appointment->close();
            $pdo->rollBack();
            echo "Follow-up appointments can only be scheduled for completed visits";
            $conn->close();
            exit;
        }
        $locked_appointment->close();

        $active = $conn->prepare("SELECT id FROM appointments WHERE patient_id = ? AND status IN ('pending', 'confirmed') AND appointment_date >= CURRENT_DATE LIMIT 1");
        $active->bind_param("i", $appointment_patient_id);
        if (!$active->execute()) {
            $active->close();
            $pdo->rollBack();
            http_response_code(500);
            echo "Could not verify the patient's active appointments. Please try again.";
            $conn->close();
            exit;
        }
        $active->store_result();
        if ($active->num_rows > 0) {
            $active->close();
            $pdo->rollBack();
            echo "The patient already has an active appointment";
            $conn->close();
            exit;
        }
        $active->close();

        $daily_limit = doctor_daily_limit_status($conn, $doctor_id, $followup_date, true);
        if ($daily_limit === null) {
            $pdo->rollBack();
            http_response_code(500);
            echo "Could not verify the doctor's daily patient limit. Please try again.";
            $conn->close();
            exit;
        }
        if (!$daily_limit['available']) {
            $pdo->rollBack();
            echo "The doctor has reached the daily patient limit for that date";
            $conn->close();
            exit;
        }
        $slot_error = lustre_appt_validate_slot($conn, $doctor_id, $followup_date, $followup_time);
        if ($slot_error !== null) {
            $pdo->rollBack();
            echo $slot_error;
            $conn->close();
            exit;
        }

        $result_update = $conn->prepare("UPDATE appointments SET result = ?, result_date = NOW() WHERE id = ? AND doctor_id = ? AND status = 'completed'");
        $result_update->bind_param("sii", $result, $appt_id, $doctor_id);
        if (!$result_update->execute()) {
            $result_update->close();
            $pdo->rollBack();
            http_response_code(500);
            echo "Could not save the consultation result. Please try again.";
            $conn->close();
            exit;
        }
        $result_update->close();

        $followup_duration = APPOINTMENT_SLOT_MINUTES;
        $followup_time_with_seconds = $followup_time . ':00';
        $followup_service = 'Follow-up consultation';
        $followup_insert = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, appointment_duration, service_requested, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending')");
        $followup_insert->bind_param("iissis", $appointment_patient_id, $doctor_id, $followup_date, $followup_time_with_seconds, $followup_duration, $followup_service);
        if (!$followup_insert->execute()) {
            $followup_insert->close();
            $pdo->rollBack();
            http_response_code(500);
            echo "Could not create the follow-up appointment. Please try again.";
            $conn->close();
            exit;
        }
        $followup_appointment_id = (int)$followup_insert->insert_id;
        $followup_insert->close();

        if (!$pdo->commit()) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo "Could not save the follow-up appointment. Please try again.";
            $conn->close();
            exit;
        }

        $is_walk_in_patient = in_array(strtolower((string)$patient_is_walk_in), ['1', 't', 'true'], true);
        if (!$is_walk_in_patient && !empty($patient_email)) {
            require_once __DIR__ . '/appointment_mailer.php';
            try {
                $email_sent = send_appointment_email($conn, $followup_appointment_id, 'booked');
            } catch (Throwable $e) {
                error_log('Follow-up appointment email failed (appt=' . $followup_appointment_id . '): ' . $e->getMessage());
                $email_sent = false;
            }
            if (!$email_sent) {
                error_log('Follow-up appointment email could not be sent (appt=' . $followup_appointment_id . ', patient=' . $appointment_patient_id . ')');
            }
        }
        echo "success";
        $conn->close();
        exit;
    }

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
    if ($current_status === 'cancelled') { echo "Cannot complete a cancelled appointment"; $conn->close(); exit; }
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
