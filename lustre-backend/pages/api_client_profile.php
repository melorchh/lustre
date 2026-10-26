<?php
// GET  ?id=<patient_id> -> {ok, patient, appointments, vitals, vitalsByAppt, labTests, lastVisit}
// Shared by both React staff portals (client_profile.php replacement).
require_once __DIR__ . '/../src/api.php';
require_once __DIR__ . '/../src/unique_ids.php';
api_require_staff();
ensure_unique_ids($conn);

$patient_id = api_int('id', 0);
if ($patient_id <= 0) {
    api_fail('Patient id is required');
}

$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = api_one($stmt->get_result());
$stmt->close();

if (!$patient) {
    api_fail('Patient not found', 404);
}

$appointments = api_all($conn->query(
    "SELECT a.*, d.name AS doctor_name, d.specialty
     FROM appointments a JOIN doctors d ON a.doctor_id = d.id
     WHERE a.patient_id = $patient_id
     ORDER BY a.appointment_date DESC, a.appointment_time DESC"));

$vitals = api_all($conn->query(
    "SELECT * FROM vitals WHERE patient_id = $patient_id ORDER BY visit_date DESC"));

$vitals_by_appt = array();
$vres = $conn->query(
    "SELECT appointment_id, COUNT(*) AS cnt FROM vitals
     WHERE patient_id = $patient_id AND appointment_id IS NOT NULL GROUP BY appointment_id");
while ($vr = $vres->fetch_assoc()) {
    $vitals_by_appt[(int)$vr['appointment_id']] = (int)$vr['cnt'];
}

$lab_tests = api_all($conn->query(
    "SELECT lt.*, d.name AS doctor_name
     FROM lab_tests lt JOIN doctors d ON lt.doctor_id = d.id
     WHERE lt.patient_id = $patient_id ORDER BY lt.created_at DESC"));

$last_visit = api_one($conn->query(
    "SELECT MAX(appointment_date) AS last_appt FROM appointments WHERE patient_id = $patient_id"));

api_ok(array(
    'patient'      => $patient,
    'appointments' => $appointments,
    'vitals'       => $vitals,
    'vitalsByAppt' => $vitals_by_appt,
    'labTests'     => $lab_tests,
    'lastVisit'    => $last_visit ? $last_visit['last_appt'] : null,
));
