<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/unique_ids.php';
require_once __DIR__ . '/../src/doctor_capacity.php';
require_once __DIR__ . '/../src/appointment_time_rules.php';
require_once __DIR__ . '/../src/time_blocks_schema.php';
ensure_unique_ids($conn);
ensure_time_blocks_schema($conn);

$conn->query("ALTER TABLE walk_ins ADD COLUMN IF NOT EXISTS test_procedure TEXT NOT NULL DEFAULT ''");

// Ensure the patient_type CHECK includes the 'child' type on existing databases.
$conn->query("ALTER TABLE patients DROP CONSTRAINT IF EXISTS patients_patient_type_check");
$conn->query("ALTER TABLE patients DROP CONSTRAINT IF EXISTS patients_patient_type_check");
$conn->query("ALTER TABLE patients ADD CONSTRAINT patients_patient_type_check CHECK (patient_type IN ('infant','child','adolescent','adult','elderly'))");

$action = $_POST['action'] ?? '';

if ($action === 'add_returning_walkin') {
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $arrival_time = trim($_POST['arrival_time'] ?? '');
    $test_procedure = trim($_POST['test_procedure'] ?? '');
    $weight = trim($_POST['weight_kg'] ?? '');
    $height = trim($_POST['height_cm'] ?? '');
    $blood_pressure = trim($_POST['blood_pressure'] ?? '');
    $heart_rate = trim($_POST['heart_rate'] ?? '');
    $fundal_height = trim($_POST['fundal_height'] ?? '');

    if ($patient_id <= 0) { echo 'Please select a registered patient.'; $conn->close(); exit; }
    if ($doctor_id <= 0) { echo 'Please select a doctor.'; $conn->close(); exit; }
    if ($test_procedure === '') { echo 'Please select a test / procedure.'; $conn->close(); exit; }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $appointment_time)) {
        echo 'Please select an available appointment time.';
        $conn->close();
        exit;
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $arrival_time)) {
        echo 'Please enter a valid arrival time.';
        $conn->close();
        exit;
    }
    if ($blood_pressure === '' || !preg_match('/^\d{2,3}\/\d{2,3}$/', $blood_pressure)) {
        echo 'Blood pressure is required (e.g. 110/70).';
        $conn->close();
        exit;
    }
    if ($heart_rate === '' || !is_numeric($heart_rate) || (int)$heart_rate < 0 || (int)$heart_rate > 250) {
        echo 'Heart rate is required (0-250 bpm).';
        $conn->close();
        exit;
    }
    if ($weight !== '' && (!is_numeric($weight) || (float)$weight <= 0)) {
        echo 'Please provide a valid weight.';
        $conn->close();
        exit;
    }
    if ($height !== '' && (!is_numeric($height) || (float)$height <= 0 || (float)$height > 300)) {
        echo 'Please provide a valid height.';
        $conn->close();
        exit;
    }
    if ($fundal_height !== '' && (!is_numeric($fundal_height) || (float)$fundal_height < 0 || (float)$fundal_height > 50)) {
        echo 'Invalid fundal height.';
        $conn->close();
        exit;
    }
    if (appointment_time_overlaps_lunch_break($appointment_time, 60)) {
        echo 'Appointments are unavailable from 12:00 PM to 1:00 PM.';
        $conn->close();
        exit;
    }

    $date = date('Y-m-d');
    $arrival_hm = date('H:i:s', strtotime($arrival_time));
    $appointment_hm = date('H:i:s', strtotime($appointment_time));
    $weight_value = $weight !== '' ? (float)$weight : null;
    $height_value = $height !== '' ? (float)$height : null;
    $heart_rate_value = (int)$heart_rate;
    $fundal_height_value = $fundal_height !== '' ? (float)$fundal_height : null;

    $pdo = $conn->getPdo();
    if (!$pdo->beginTransaction()) {
        http_response_code(500);
        echo 'Could not start walk-in registration. Please try again.';
        $conn->close();
        exit;
    }
    $abortReturningWalkin = function ($message, $http_status = 400) use ($pdo, $conn) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code($http_status);
        echo $message;
        $conn->close();
        exit;
    };

    $patient_lock = $conn->prepare("SELECT id FROM patients WHERE id = ? FOR UPDATE");
    if (!$patient_lock) $abortReturningWalkin('Could not verify the selected patient. Please try again.', 500);
    $patient_lock->bind_param("i", $patient_id);
    if (!$patient_lock->execute()) {
        $patient_lock->close();
        $abortReturningWalkin('Could not verify the selected patient. Please try again.', 500);
    }
    $patient_lock->store_result();
    $patient_exists = $patient_lock->num_rows > 0;
    $patient_lock->close();
    if (!$patient_exists) $abortReturningWalkin('The selected patient could not be found.');

    $doctor_check = $conn->prepare("SELECT id FROM doctors WHERE id = ?");
    if (!$doctor_check) $abortReturningWalkin('Could not verify the selected doctor. Please try again.', 500);
    $doctor_check->bind_param("i", $doctor_id);
    if (!$doctor_check->execute()) {
        $doctor_check->close();
        $abortReturningWalkin('Could not verify the selected doctor. Please try again.', 500);
    }
    $doctor_check->store_result();
    $doctor_exists = $doctor_check->num_rows > 0;
    $doctor_check->close();
    if (!$doctor_exists) $abortReturningWalkin('The selected doctor could not be found.');

    $daily_limit = doctor_daily_limit_status($conn, $doctor_id, $date, true);
    if ($daily_limit === null) $abortReturningWalkin('Could not verify the selected doctor\'s availability. Please try again.', 500);
    if (!$daily_limit['available']) $abortReturningWalkin('The selected doctor has reached the daily patient limit. Please pick another doctor.');

    $slot_available = doctor_slot_is_available($conn, $doctor_id, $date, $appointment_time);
    if ($slot_available === null) $abortReturningWalkin('Could not verify the selected time. Please try again.', 500);
    if (!$slot_available) $abortReturningWalkin('The selected time is no longer available for this doctor. Choose another time.');

    $walkin = $conn->prepare("INSERT INTO walk_ins (patient_id, doctor_id, arrival_date, arrival_time, test_procedure, status) VALUES (?, ?, ?, ?, ?, 'waiting')");
    if (!$walkin) $abortReturningWalkin('Could not add the patient to the walk-in queue. Please try again.', 500);
    $walkin->bind_param("iisss", $patient_id, $doctor_id, $date, $arrival_hm, $test_procedure);
    if (!$walkin->execute()) {
        error_log('[admin-walkin] Could not create returning patient queue entry: ' . $walkin->error);
        $walkin->close();
        $abortReturningWalkin('Could not add the patient to the walk-in queue. Please try again.', 500);
    }
    $walkin->close();

    $appointment_duration = 60;
    $appointment = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, appointment_duration, service_requested, status, payment_status, reminder_sent) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', 'pending', 1)");
    if (!$appointment) $abortReturningWalkin('Could not create the walk-in appointment. Please try again.', 500);
    $appointment->bind_param("iissis", $patient_id, $doctor_id, $date, $appointment_hm, $appointment_duration, $test_procedure);
    if (!$appointment->execute()) {
        error_log('[admin-walkin] Could not create returning patient appointment: ' . $appointment->error);
        $appointment->close();
        $abortReturningWalkin('Could not create the walk-in appointment. Please try again.', 500);
    }
    $appointment_id = (int)$appointment->insert_id;
    $appointment->close();

    $vitals = $conn->prepare("INSERT INTO vitals (patient_id, appointment_id, visit_date, weight_kg, height_cm, blood_pressure, heart_rate, fundal_height) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$vitals) $abortReturningWalkin('Could not save the patient\'s vitals. Please try again.', 500);
    $vitals->bind_param("iisddsid", $patient_id, $appointment_id, $date, $weight_value, $height_value, $blood_pressure, $heart_rate_value, $fundal_height_value);
    if (!$vitals->execute()) {
        error_log('[admin-walkin] Could not save returning patient vitals: ' . $vitals->error);
        $vitals->close();
        $abortReturningWalkin('Could not save the patient\'s vitals. Please try again.', 500);
    }
    $vitals->close();

    if ($weight_value !== null && $height_value !== null) {
        $profile_update = $conn->prepare("UPDATE patients SET weight_kg = ?, height_cm = ? WHERE id = ?");
        if (!$profile_update) $abortReturningWalkin('Could not update the patient profile. Please try again.', 500);
        $profile_update->bind_param("ddi", $weight_value, $height_value, $patient_id);
    } elseif ($weight_value !== null) {
        $profile_update = $conn->prepare("UPDATE patients SET weight_kg = ? WHERE id = ?");
        if (!$profile_update) $abortReturningWalkin('Could not update the patient profile. Please try again.', 500);
        $profile_update->bind_param("di", $weight_value, $patient_id);
    } elseif ($height_value !== null) {
        $profile_update = $conn->prepare("UPDATE patients SET height_cm = ? WHERE id = ?");
        if (!$profile_update) $abortReturningWalkin('Could not update the patient profile. Please try again.', 500);
        $profile_update->bind_param("di", $height_value, $patient_id);
    } else {
        $profile_update = null;
    }
    if ($profile_update) {
        if (!$profile_update->execute()) {
            $profile_update->close();
            $abortReturningWalkin('Could not update the patient profile. Please try again.', 500);
        }
        $profile_update->close();
    }

    if (!$pdo->commit()) $abortReturningWalkin('Could not save the walk-in registration. Please try again.', 500);
    require_once __DIR__ . '/appointment_mailer.php';
    send_appointment_notification($conn, $appointment_id, 'confirmed');
    echo 'success:' . $patient_id;
} elseif ($action === 'add_walkin') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $weight  = trim($_POST['weight_kg'] ?? '');
    $height  = trim($_POST['height_cm'] ?? '');
    $type    = trim($_POST['patient_type'] ?? '');
    $age     = trim($_POST['age'] ?? '');
    $gender  = isset($_POST['gender']) ? strtolower(trim($_POST['gender'])) : '';
    $doctor_id = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : 0;
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $arrival_time = trim($_POST['arrival_time'] ?? '');
    if ($appointment_time === '' && $arrival_time !== '') {
        // Keep older clients working: they used arrival_time for the appointment slot.
        $appointment_time = $arrival_time;
        $arrival_time = '';
    }
    if ($arrival_time === '') {
        $arrival_time = date('H:i');
    }
    $test_procedure = trim($_POST['test_procedure'] ?? '');
    if ($test_procedure === '') {
        echo 'Please select a test / procedure.';
        $conn->close();
        exit;
    }
    $blood_pressure = trim($_POST['blood_pressure'] ?? '');
    $heart_rate     = trim($_POST['heart_rate'] ?? '');
    $fundal_height  = trim($_POST['fundal_height'] ?? '');

    if ($name === '') { echo 'Patient name is required.'; exit; }
    if ($contact === '') { echo 'Contact number is required.'; exit; }
    if ($doctor_id <= 0) { echo 'Please select a doctor.'; exit; }
    if ($blood_pressure === '' || !preg_match('/^\d{2,3}\/\d{2,3}$/', $blood_pressure)) {
        echo 'Blood pressure is required (e.g. 110/70).';
        exit;
    }
    if ($heart_rate === '' || !is_numeric($heart_rate) || (int)$heart_rate < 0 || (int)$heart_rate > 250) {
        echo 'Heart rate is required (0-250 bpm).';
        exit;
    }
    if ($fundal_height !== '' && (!is_numeric($fundal_height) || (float)$fundal_height < 0 || (float)$fundal_height > 50)) {
        echo 'Invalid fundal height.';
        exit;
    }
    $heartRateVal    = (int)$heart_rate;
    $fundalHeightVal = ($fundal_height !== '') ? (float)$fundal_height : null;

    // Validate doctor exists
    $docCheck = $conn->prepare("SELECT id FROM doctors WHERE id = ?");
    $docCheck->bind_param("i", $doctor_id);
    $docCheck->execute();
    $docCheck->store_result();
    if ($docCheck->num_rows === 0) {
        $docCheck->close();
        echo 'Selected doctor does not exist.';
        exit;
    }
    $docCheck->close();

    // The doctor must still have room today. Same rule the walk-in form uses
    // to filter its dropdown, re-checked here so a stale page cannot overbook.
    $capacity = doctor_capacity_map($conn, date('Y-m-d'));
    $capacityInfo = isset($capacity[$doctor_id]) ? $capacity[$doctor_id] : null;
    if ($capacityInfo && empty($capacityInfo['available'])) {
        echo ($capacityInfo['reason'] === 'not_scheduled')
            ? 'The selected doctor is not scheduled for today.'
            : 'The selected doctor is fully booked for today. Please pick another doctor.';
        exit;
    }

    // Validate the selected appointment slot independently from walk-in arrival.
    if ($appointment_time === '') {
        echo 'Please select an available appointment time.';
        exit;
    } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $appointment_time)) {
        echo 'Invalid appointment time.';
        exit;
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $arrival_time)) {
        echo 'Invalid arrival time.';
        exit;
    }
    if (appointment_time_overlaps_lunch_break($appointment_time, 60)) {
        echo 'Appointments are unavailable from 12:00 PM to 1:00 PM.';
        exit;
    }

    $arrival_date = date('Y-m-d');
    $slot_available = doctor_slot_is_available($conn, $doctor_id, $arrival_date, $appointment_time);
    if ($slot_available === null) {
        http_response_code(500);
        echo 'Could not verify the selected doctor\'s availability. Please try again.';
        exit;
    }
    if (!$slot_available) {
        echo 'The selected time is no longer available for this doctor. Choose another time.';
        exit;
    }

    // Validate patient type against allowed values
    $allowedTypes = ['infant','child','adolescent','adult','elderly'];
    if ($type !== '' && !in_array($type, $allowedTypes)) {
        echo 'Invalid patient type.';
        exit;
    }
    $typeExplicit = ($type !== '');
    if ($type === '') $type = 'adult';

    // Validate gender against allowed values
    if ($gender !== '' && !in_array($gender, ['male','female'], true)) {
        echo 'Invalid gender.';
        exit;
    }
    if ($gender === '') $gender = null;

    // Validate age
    if ($age !== '' && (!is_numeric($age) || (int)$age < 0 || (int)$age > 150)) {
        echo 'Invalid age.';
        exit;
    }

    // Enforce the age band that belongs to the selected patient type
    $ageBands = [
        'infant'     => [1, 2],
        'child'      => [3, 12],
        'adolescent' => [13, 17],
        'adult'      => [18, 59],
        'elderly'    => [60, 150],
    ];
    $band = $ageBands[$type];
    if ($typeExplicit && $age === '') {
        echo 'Age is required for patient type ' . ucfirst($type) . '.';
        exit;
    }
    if ($age !== '') {
        $ageInt = (int)$age;
        if ($ageInt < $band[0] || $ageInt > $band[1]) {
            $expected = ($band[0] === $band[1])
                ? $band[0] . ' year'
                : $band[0] . '-' . (($band[1] >= 150) ? 'above' : $band[1]) . ' years';
            echo ucfirst($type) . ' must be ' . $expected . ' old.';
            exit;
        }
    }

    // Auto-generate email if not provided
    if ($email === '') {
        $email = strtolower(str_replace(' ', '.', trim($name))) . '@walkin.local';
        // Ensure uniqueness
        $check = $conn->prepare("SELECT id FROM patients WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $email = strtolower(str_replace(' ', '.', trim($name))) . '.' . time() . '@walkin.local';
        }
        $check->close();
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM patients WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            echo 'A patient with this email already exists.';
            $check->close();
            exit;
        }
        $check->close();
    }

    // Generate a random password (walk-in clients won't use login)
    $randomPassword = bin2hex(random_bytes(8));
    $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);

    // Resolve optional numeric fields to variables so bind_param receives references
    $weightVal = ($weight !== '') ? (float)$weight : null;
    $heightVal = ($height !== '') ? (float)$height : null;
    $ageVal    = ($age !== '')    ? (int)$age    : null;

    $pdo = $conn->getPdo();
    if (!$pdo->beginTransaction()) {
        http_response_code(500);
        echo 'Could not start walk-in registration. Please try again.';
        $conn->close();
        exit;
    }
    $daily_limit = doctor_daily_limit_status($conn, $doctor_id, $arrival_date, true);
    if ($daily_limit === null) {
        $pdo->rollBack();
        http_response_code(500);
        echo 'Could not verify the selected doctor\'s daily patient limit. Please try again.';
        $conn->close();
        exit;
    }
    if (!$daily_limit['available']) {
        $pdo->rollBack();
        echo 'The selected doctor has reached the daily patient limit. Please pick another doctor.';
        $conn->close();
        exit;
    }
    $slot_available = doctor_slot_is_available($conn, $doctor_id, $arrival_date, $appointment_time);
    if ($slot_available === null) {
        $pdo->rollBack();
        http_response_code(500);
        echo 'Could not verify the selected doctor\'s availability. Please try again.';
        $conn->close();
        exit;
    }
    if (!$slot_available) {
        $pdo->rollBack();
        echo 'The selected time is no longer available for this doctor. Choose another time.';
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO patients (name, email, contact, address, password, is_walk_in, weight_kg, height_cm, patient_type, age, gender) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "ssssssdsis",
        $name,
        $email,
        $contact,
        $address,
        $hashedPassword,
        $weightVal,
        $heightVal,
        $type,
        $ageVal,
        $gender
    );

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;

        // Record the walk-in entry (queue) for the selected doctor
        $arrival_hm   = date('H:i:s', strtotime($arrival_time));
        $appointment_hm = date('H:i:s', strtotime($appointment_time));
        $wi = $conn->prepare("INSERT INTO walk_ins (patient_id, doctor_id, arrival_date, arrival_time, test_procedure, status) VALUES (?, ?, ?, ?, ?, 'waiting')");
        $wi->bind_param("iisss", $newId, $doctor_id, $arrival_date, $arrival_hm, $test_procedure);
        if (!$wi->execute()) {
            $wi->close();
            $stmt->close();
            $pdo->rollBack();
            http_response_code(500);
            echo 'Failed to register walk-in patient.';
            $conn->close();
            exit;
        }
        $wi->close();

        // Book the consultation so it appears in the client's appointments
        $service = ($test_procedure !== '') ? $test_procedure : 'Walk-in Consultation';
        $appointment_duration = 60;
        $ap = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, appointment_duration, service_requested, status, payment_status, reminder_sent) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', 'pending', 1)");
        $ap->bind_param("iissis", $newId, $doctor_id, $arrival_date, $appointment_hm, $appointment_duration, $service);
        if (!$ap->execute()) {
            $ap->close();
            $stmt->close();
            $pdo->rollBack();
            http_response_code(500);
            echo 'Failed to register walk-in patient.';
            $conn->close();
            exit;
        }
        $appointment_id = (int)$ap->insert_id;
        $ap->close();

        // Vitals captured during the consultation, tied to the appointment
        $vt = $conn->prepare("INSERT INTO vitals (patient_id, appointment_id, visit_date, weight_kg, height_cm, blood_pressure, heart_rate, fundal_height) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $vt->bind_param("iisddsid", $newId, $appointment_id, $arrival_date, $weightVal, $heightVal, $blood_pressure, $heartRateVal, $fundalHeightVal);
        $vt->execute();
        $vt->close();

        if (!$pdo->commit()) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $stmt->close();
            http_response_code(500);
            echo 'Failed to save walk-in registration.';
            $conn->close();
            exit;
        }

        require_once __DIR__ . '/appointment_mailer.php';
        send_appointment_notification($conn, $appointment_id, 'confirmed');

        echo "success:" . $newId;
    } else {
        $pdo->rollBack();
        echo "Failed to register walk-in patient.";
    }

    $stmt->close();
} elseif ($action === 'update_status') {
    $wid    = isset($_POST['walkin_id']) ? (int)$_POST['walkin_id'] : 0;
    $status = trim($_POST['status'] ?? '');
    $allowed = array('waiting', 'in_service', 'served', 'cancelled');
    if ($wid <= 0) { echo 'Invalid walk-in.'; $conn->close(); exit; }
    if (!in_array($status, $allowed, true)) { echo 'Invalid status.'; $conn->close(); exit; }
    $upd = $conn->prepare("UPDATE walk_ins SET status = ? WHERE id = ?");
    $upd->bind_param("si", $status, $wid);
    echo $upd->execute() ? 'success' : 'Update failed.';
    $upd->close();
} else {
    http_response_code(400);
    echo 'Unknown action.';
}

$conn->close();
