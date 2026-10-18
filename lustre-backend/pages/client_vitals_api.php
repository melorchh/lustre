<?php
require __DIR__ . '/../src/session.php';

$is_admin  = isset($_SESSION['admin_id']);
$is_doctor = isset($_SESSION['doctor_id']);
if (!$is_admin && !$is_doctor) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/unique_ids.php';
ensure_unique_ids($conn);

header('Content-Type: application/json');

$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$patient_id     = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($appointment_id <= 0 && $patient_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'appointment_id or patient_id is required']);
    exit;
}

$appt = null;
if ($appointment_id > 0) {
    $stmt = $conn->prepare("
        SELECT a.id, a.patient_id, a.unique_code, a.appointment_date, a.appointment_time, a.service_requested, a.status,
               d.name AS doctor_name
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $appt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$appt) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Appointment not found']);
        exit;
    }
    $patient_id = (int)$appt['patient_id'];
} else {
    $pstmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
    $pstmt->bind_param("i", $patient_id);
    $pstmt->execute();
    $prow = $pstmt->get_result()->fetch_assoc();
    $pstmt->close();

    if (!$prow) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Patient not found']);
        exit;
    }
}

$vitals = [];
if ($appointment_id > 0) {
    $vstmt = $conn->prepare("
        SELECT visit_date, weight_kg, height_cm, blood_pressure, heart_rate, fundal_height, notes
        FROM vitals
        WHERE appointment_id = ?
        ORDER BY visit_date DESC, id DESC
    ");
    $vstmt->bind_param("i", $appointment_id);
} else {
    $vstmt = $conn->prepare("
        SELECT visit_date, weight_kg, height_cm, blood_pressure, heart_rate, fundal_height, notes
        FROM vitals
        WHERE patient_id = ?
        ORDER BY visit_date DESC, id DESC
        LIMIT 12
    ");
    $vstmt->bind_param("i", $patient_id);
}
$vstmt->execute();
$vres = $vstmt->get_result();
while ($v = $vres->fetch_assoc()) {
    $vitals[] = $v;
}
$vstmt->close();

echo json_encode([
    'ok'      => true,
    'scope'   => $appointment_id > 0 ? 'appointment' : 'patient',
    'patient' => ['id' => (int)$patient_id],
    'appointment' => $appt ? [
        'id'                => (int)$appt['id'],
        'unique_code'       => $appt['unique_code'],
        'appointment_date'  => $appt['appointment_date'],
        'appointment_time'  => $appt['appointment_time'],
        'service_requested' => $appt['service_requested'],
        'status'            => $appt['status'],
        'doctor_name'       => $appt['doctor_name'],
    ] : null,
    'vitals' => $vitals,
]);

$conn->close();
