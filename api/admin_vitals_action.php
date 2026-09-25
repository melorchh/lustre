<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }

include __DIR__ . '/../toddcare-backend/src/db.php';

$action = $_POST['action'] ?? '';
$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;

if ($patient_id <= 0) { http_response_code(400); echo 'Invalid patient.'; exit; }

/**
 * Find a free (doctor, time) slot on a given date for auto-booking vaccine
 * doses. Prefers the chosen doctor and preferred time, then falls back to
 * other half-hour slots, then to other doctors. Returns [doctor_id, 'H:i:s']
 * or null when nothing is free. Avoids the appointments unique key
 * (doctor_id, appointment_date, appointment_time).
 */
function find_vaccine_slot($conn, $date, $pref_doctor, $pref_time) {
    $slots = [];
    for ($t = strtotime('08:00'); $t < strtotime('17:00'); $t += 1800) {
        $slots[] = date('H:i:s', $t);
    }
    $pref = $pref_time !== '' ? date('H:i:s', strtotime($pref_time)) : null;
    if ($pref && in_array($pref, $slots, true)) {
        $slots = array_merge([$pref], array_values(array_diff($slots, [$pref])));
    }

    $docs = [];
    if ($pref_doctor > 0) $docs[] = $pref_doctor;
    $all = $conn->query("SELECT id FROM doctors ORDER BY id");
    if ($all) {
        while ($d = $all->fetch_assoc()) {
            $did = (int)$d['id'];
            if (!in_array($did, $docs, true)) $docs[] = $did;
        }
    }
    if (!$docs) return null;

    $check = $conn->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status IN ('pending','confirmed') LIMIT 1");
    foreach ($docs as $did) {
        foreach ($slots as $slot) {
            $check->bind_param("iss", $did, $date, $slot);
            $check->execute();
            $taken = $check->get_result()->fetch_assoc() !== null;
            if (!$taken) { $check->close(); return [$did, $slot]; }
        }
    }
    $check->close();
    return null;
}

switch ($action) {
    case 'add_vital':
        $visit_date = trim($_POST['visit_date'] ?? '');
        $weight     = trim($_POST['weight_kg'] ?? '');
        $height     = trim($_POST['height_cm'] ?? '');
        $bp         = trim($_POST['blood_pressure'] ?? '');
        $hr         = trim($_POST['heart_rate'] ?? '');
        $fh         = trim($_POST['fundal_height'] ?? '');
        $notes      = trim($_POST['notes'] ?? '');

        if ($visit_date === '') { echo 'Please provide the visit date.'; exit; }
        if ($weight === '' || (float)$weight <= 0) { echo 'Please provide a valid weight.'; exit; }
        if ($height !== '' && (!is_numeric($height) || (float)$height <= 0 || (float)$height > 300)) { echo 'Please enter a valid height (30â€“300 cm).'; exit; }

        $w = (float)$weight;
        $h = $height !== '' ? (float)$height : null;
        $b = $bp !== '' ? $bp : null;
        $r = $hr !== '' ? (int)$hr : null;
        $f = $fh !== '' ? (float)$fh : null;
        $n = $notes !== '' ? $notes : null;

        $stmt = $conn->prepare("INSERT INTO vitals (patient_id, visit_date, weight_kg, height_cm, blood_pressure, heart_rate, fundal_height, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "isddsids",
            $patient_id,
            $visit_date,
            $w,
            $h,
            $b,
            $r,
            $f,
            $n
        );
        if ($stmt->execute()) {
            echo 'success';
            // When weight/height was recorded at a visit, keep the patient's
            // profile up to date so the patient portal and admin side show the
            // latest measurements. Height only syncs when one was provided.
            if ($h !== null) {
                $upd = $conn->prepare("UPDATE patients SET weight_kg = ?, height_cm = ? WHERE id = ?");
                $upd->bind_param("ddi", $w, $h, $patient_id);
                $upd->execute();
                $upd->close();
            } else {
                $upd = $conn->prepare("UPDATE patients SET weight_kg = ? WHERE id = ?");
                $upd->bind_param("di", $w, $patient_id);
                $upd->execute();
                $upd->close();
            }
        } else {
            echo 'Failed to save vitals.';
        }
        $stmt->close();
        break;

    case 'delete_vital':
        $id = (int)($_POST['vital_id'] ?? 0);
        if ($id <= 0) { echo 'Invalid record.'; exit; }
        $stmt = $conn->prepare("DELETE FROM vitals WHERE id=? AND patient_id=?");
        $stmt->bind_param("ii", $id, $patient_id);
        echo $stmt->execute() ? 'success' : 'Failed to delete record.';
        $stmt->close();
        break;

    case 'add_vaccination':
        $vaccine  = trim($_POST['vaccine_name'] ?? '');
        $dose     = trim($_POST['dose_label'] ?? '');
        $given    = trim($_POST['administered_date'] ?? '');
        $due      = trim($_POST['next_due_date'] ?? '');
        $notes    = trim($_POST['notes'] ?? '');
        $pref_doctor = (int)($_POST['doctor_id'] ?? 0);
        $pref_time   = trim($_POST['appointment_time'] ?? '');

        if ($vaccine === '' || $given === '') { echo 'Vaccine name and administered date are required.'; exit; }
        if ($dose === '') $dose = 'Dose';

        $du = $due !== '' ? $due : null;
        $no = $notes !== '' ? $notes : null;

        $stmt = $conn->prepare("INSERT INTO vaccinations (patient_id, vaccine_name, dose_label, administered_date, next_due_date, notes)
                                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "isssss",
            $patient_id,
            $vaccine,
            $dose,
            $given,
            $du,
            $no
        );
        $ok = $stmt->execute();
        $stmt->close();

        // Auto-book a pending appointment for the NEXT dose when a next-due date was set.
        $autoScheduled = null;
        if ($ok && $du) {
            $nextLabel = $dose;
            if (preg_match('/^(.*?)(\d+)\s*$/', $dose, $mm)) {
                $nextLabel = $mm[1] . ((int)$mm[2] + 1);
            }
            $svc = $vaccine . ' (' . $nextLabel . ')';
            $slot = find_vaccine_slot($conn, $du, $pref_doctor, $pref_time);
            if ($slot) {
                try {
                    $ins = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, service_requested, status, notes)
                                           VALUES (?, ?, ?, ?, ?, 'pending', ?)");
                    $note = 'Auto-scheduled from vaccination record (' . date('M j, Y') . ').';
                    $ins->bind_param("iissss", $patient_id, $slot[0], $du, $slot[1], $svc, $note);
                    $ins->execute();
                    $ins->close();
                    $autoScheduled = [
                        'next_dose' => $nextLabel,
                        'date'      => $du,
                        'time'      => substr($slot[1], 0, 5),
                    ];
                } catch (mysqli_sql_exception $e) {
                    // never block the vaccination save because of a booking issue
                }
            }
        }

        if ($ok) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'auto_scheduled' => $autoScheduled]);
        } else {
            echo 'Failed to save vaccination.';
        }
        break;

    case 'delete_vaccination':
        $id = (int)($_POST['vaccination_id'] ?? 0);
        if ($id <= 0) { echo 'Invalid record.'; exit; }
        $stmt = $conn->prepare("DELETE FROM vaccinations WHERE id=? AND patient_id=?");
        $stmt->bind_param("ii", $id, $patient_id);
        echo $stmt->execute() ? 'success' : 'Failed to delete record.';
        $stmt->close();
        break;

    default:
        http_response_code(400);
        echo 'Unknown action.';
}

$conn->close();