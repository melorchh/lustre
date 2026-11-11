<?php
// ============================================================================
// Doctor walk-in queue — JSON endpoint.
//   GET  : today's active queue for the logged-in doctor (queue # in arrival order)
//   POST : update a walk-in's status (scoped to this doctor)
// ============================================================================
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_id"])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/walkin_schema.php';
walkins_ensure_result_column($conn);

$doctor_id = (int)$_SESSION['doctor_id'];
$action    = $_POST['action'] ?? '';

// Mirror a walk-in result onto the matching appointment (same patient,
// doctor, and date — latest appointment wins) so the result shows up in
// client_profile.php, doctor_appointments.php, and the admin side.
// If no appointment exists for that walk-in, create a completed one so the
// consultation still lands in the patient's records.
function walkins_mirror_result_to_appointment($conn, $doctor_id, $wid, $result, $complete = false) {
    if ($result === '' && !$complete) return true;

    $res = $conn->query(
        "SELECT a.id FROM appointments a
         JOIN walk_ins w ON a.patient_id = w.patient_id
                       AND a.doctor_id = w.doctor_id
                       AND a.appointment_date = w.arrival_date
         WHERE w.id = " . (int)$wid . " AND w.doctor_id = " . (int)$doctor_id . "
         ORDER BY a.id DESC LIMIT 1"
    );
    if ($res && ($r = $res->fetch_assoc())) {
        $aid = (int)$r['id'];
        $upd = $conn->prepare("UPDATE appointments SET result = CASE WHEN ? <> '' THEN ? ELSE result END, result_date = CASE WHEN ? <> '' THEN NOW() ELSE result_date END, status = CASE WHEN ? = 1 AND status <> 'cancelled' THEN 'completed' ELSE status END WHERE id = ? AND doctor_id = ?");
        $complete_int = $complete ? 1 : 0;
        $upd->bind_param("sssiii", $result, $result, $result, $complete_int, $aid, $doctor_id);
        if (!$upd->execute()) {
            error_log('Unable to complete appointment for served walk-in ' . (int)$wid . ': ' . $upd->error);
            $upd->close();
            return false;
        }
        $upd->close();
        return true;
    }
    // No appointment found — create a completed one for this walk-in.
    $w = $conn->query(
        "SELECT w.patient_id, w.arrival_date, w.arrival_time, w.test_procedure
         FROM walk_ins w
         WHERE w.id = " . (int)$wid . " AND w.doctor_id = " . (int)$doctor_id
    );
    if (!$w || !($wr = $w->fetch_assoc())) {
        error_log('Unable to find walk-in for appointment record (walk-in=' . (int)$wid . ').');
        return false;
    }
    $service = ($wr['test_procedure'] !== null && $wr['test_procedure'] !== '') ? $wr['test_procedure'] : 'Walk-in consultation';
    $ins = $conn->prepare(
        "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, appointment_duration, service_requested, status, payment_status, reminder_sent, result, result_date)
         VALUES (?, ?, ?, ?, 60, ?, 'completed', 'pending', 1, ?, CASE WHEN ? <> '' THEN NOW() ELSE NULL END)"
    );
    $ins->bind_param(
        "iisssss",
        $wr['patient_id'],
        $doctor_id,
        $wr['arrival_date'],
        $wr['arrival_time'],
        $service,
        $result,
        $result
    );
    if (!$ins->execute()) {
        error_log('Unable to create appointment record for served walk-in ' . (int)$wid . ': ' . $ins->error);
        $ins->close();
        return false;
    }
    $ins->close();
    return true;
}

if ($action === 'update_status') {
    $wid    = isset($_POST['walkin_id']) ? intval($_POST['walkin_id']) : 0;
    $status = trim($_POST['status'] ?? '');
    $allowed = ['waiting', 'in_service', 'served', 'cancelled'];
    if ($wid <= 0)      { echo 'Invalid walk-in.'; $conn->close(); exit; }
    if (!in_array($status, $allowed, true)) { echo 'Invalid status.'; $conn->close(); exit; }

    $has_result = array_key_exists('result', $_POST);
    $result = $has_result ? trim($_POST['result']) : null;
    if ($status === 'served' && !$has_result) {
        $existing = $conn->prepare("SELECT result FROM walk_ins WHERE id = ? AND doctor_id = ?");
        $existing->bind_param("ii", $wid, $doctor_id);
        if (!$existing->execute()) {
            error_log('Unable to load result for served walk-in ' . $wid . ': ' . $existing->error);
            $existing->close();
            echo 'Could not load walk-in result.';
            $conn->close();
            exit;
        }
        $existing->bind_result($result);
        if (!$existing->fetch()) {
            $existing->close();
            echo 'Walk-in not found.';
            $conn->close();
            exit;
        }
        $existing->close();
        $result = (string)$result;
        $has_result = true;
    }

    $needs_appointment_sync = $status === 'served' || ($has_result && $result !== '');
    $pdo = $conn->getPdo();
    $transaction_started = false;
    if ($needs_appointment_sync) {
        $transaction_started = $pdo->beginTransaction();
        if (!$transaction_started) {
            http_response_code(500);
            echo 'Could not save the walk-in visit.';
            $conn->close();
            exit;
        }
    }

    if ($has_result) {
        $upd = $conn->prepare("UPDATE walk_ins SET status = ?, result = ? WHERE id = ? AND doctor_id = ?");
        $upd->bind_param("ssii", $status, $result, $wid, $doctor_id);
        $updated = $upd->execute();
    } else {
        $upd = $conn->prepare("UPDATE walk_ins SET status = ? WHERE id = ? AND doctor_id = ?");
        $upd->bind_param("sii", $status, $wid, $doctor_id);
        $updated = $upd->execute();
    }

    if (!$updated) {
        if ($transaction_started) $pdo->rollBack();
        echo 'Update failed.';
    } elseif ($needs_appointment_sync && !walkins_mirror_result_to_appointment($conn, $doctor_id, $wid, (string)$result, $status === 'served')) {
        $pdo->rollBack();
        echo 'Could not save the walk-in visit.';
    } elseif ($transaction_started && !$pdo->commit()) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo 'Could not save the walk-in visit.';
    } else {
        echo 'success';
    }
    $upd->close();
    $conn->close();
    exit;
}

if ($action === 'save_result') {
    $wid = isset($_POST['walkin_id']) ? intval($_POST['walkin_id']) : 0;
    $result = trim($_POST['result'] ?? '');
    if ($wid <= 0) { echo 'Invalid walk-in.'; $conn->close(); exit; }

    $upd = $conn->prepare("UPDATE walk_ins SET result = ? WHERE id = ? AND doctor_id = ?");
    $upd->bind_param("sii", $result, $wid, $doctor_id);
    if ($upd->execute()) {
        if (walkins_mirror_result_to_appointment($conn, $doctor_id, $wid, $result)) {
            echo 'success';
        } else {
            echo 'Could not save consultation result.';
        }
    } else { echo 'Update failed.'; }
    $upd->close();
    $conn->close();
    exit;
}

// GET — today's queue for this doctor
$today   = date('Y-m-d');
$walkins = $conn->query("
    SELECT w.id, w.arrival_time, w.status, w.result, w.test_procedure,
           p.id AS patient_id, p.name AS patient_name, p.contact,
           (SELECT a.id FROM appointments a
             WHERE a.patient_id = w.patient_id AND a.doctor_id = w.doctor_id
               AND a.appointment_date = w.arrival_date
             ORDER BY a.id DESC LIMIT 1) AS appointment_id
    FROM walk_ins w
    JOIN patients p ON w.patient_id = p.id
    WHERE w.doctor_id = $doctor_id AND w.arrival_date = '$today'
      AND w.status IN ('waiting', 'in_service')
    ORDER BY w.arrival_time ASC, w.created_at ASC, w.id ASC
");

$rows = [];
$n = 0;
$waiting = 0;
$in_service = 0;
if ($walkins) {
    while ($w = $walkins->fetch_assoc()) {
        $n++;
        if ($w['status'] === 'waiting')    $waiting++;
        if ($w['status'] === 'in_service') $in_service++;
        $rows[] = [
            'id'        => (int)$w['id'],
            'queue'     => $n,
            'patient_id'=> (int)$w['patient_id'],
            'appointment_id' => ($w['appointment_id'] !== null && $w['appointment_id'] !== '') ? (int)$w['appointment_id'] : null,
            'name'      => $w['patient_name'],
            'contact'   => $w['contact'],
            'arrival'   => date('g:i A', strtotime($w['arrival_time'])),
            'status'    => $w['status'],
            'procedure' => (string)($w['test_procedure'] ?? ''),
            'result'    => (string)($w['result'] ?? ''),
        ];
    }
}
$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'ok'         => true,
    'date'       => $today,
    'total'      => $n,
    'waiting'    => $waiting,
    'in_service' => $in_service,
    'rows'       => $rows,
]);
