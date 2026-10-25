<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/unique_ids.php';
ensure_unique_ids($conn);

$conn->query("ALTER TABLE walk_ins ADD COLUMN IF NOT EXISTS test_procedure TEXT NOT NULL DEFAULT ''");

// Ensure the patient_type CHECK includes the 'child' type on existing databases.
$conn->query("ALTER TABLE patients DROP CONSTRAINT IF EXISTS patients_patient_type_check");
$conn->query("ALTER TABLE patients DROP CONSTRAINT IF EXISTS patients_patient_type_check");
$conn->query("ALTER TABLE patients ADD CONSTRAINT patients_patient_type_check CHECK (patient_type IN ('infant','child','adolescent','adult','elderly'))");

$action = $_POST['action'] ?? '';

if ($action === 'add_walkin') {
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
    $arrival_time = trim($_POST['arrival_time'] ?? '');
    $test_procedure = trim($_POST['test_procedure'] ?? '');
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

    // Validate arrival time (default: now)
    if ($arrival_time === '') {
        $arrival_time = date('H:i');
    } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $arrival_time)) {
        echo 'Invalid arrival time.';
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
        $arrival_date = date('Y-m-d');
        $arrival_hm   = date('H:i:s', strtotime($arrival_time));
        $wi = $conn->prepare("INSERT INTO walk_ins (patient_id, doctor_id, arrival_date, arrival_time, test_procedure, status) VALUES (?, ?, ?, ?, ?, 'waiting')");
        $wi->bind_param("iisss", $newId, $doctor_id, $arrival_date, $arrival_hm, $test_procedure);
        $wi->execute();
        $wi->close();

        // Book the consultation so it appears in the client's appointments
        $service = ($test_procedure !== '') ? $test_procedure : 'Walk-in Consultation';
        $ap = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, service_requested, status, payment_status, reminder_sent) VALUES (?, ?, ?, ?, ?, 'confirmed', 'pending', 1)");
        $ap->bind_param("iisss", $newId, $doctor_id, $arrival_date, $arrival_hm, $service);
        $ap->execute();
        $appointment_id = (int)$ap->insert_id;
        $ap->close();

        // Vitals captured during the consultation, tied to the appointment
        $vt = $conn->prepare("INSERT INTO vitals (patient_id, appointment_id, visit_date, weight_kg, height_cm, blood_pressure, heart_rate, fundal_height) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $vt->bind_param("iisddsid", $newId, $appointment_id, $arrival_date, $weightVal, $heightVal, $blood_pressure, $heartRateVal, $fundalHeightVal);
        $vt->execute();
        $vt->close();

        echo "success:" . $newId;
    } else {
        echo "Failed to register walk-in patient.";
    }

    $stmt->close();
} else {
    http_response_code(400);
    echo 'Unknown action.';
}

$conn->close();
