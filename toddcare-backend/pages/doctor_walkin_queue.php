<?php
// ============================================================================
// Doctor walk-in queue — JSON endpoint.
//   GET  : today's queue for the logged-in doctor (queue # in arrival order)
//   POST : update a walk-in's status (scoped to this doctor)
// ============================================================================
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_id"])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}
require_once __DIR__ . '/../src/db.php';

$doctor_id = (int)$_SESSION['doctor_id'];
$action    = $_POST['action'] ?? '';

if ($action === 'update_status') {
    $wid    = isset($_POST['walkin_id']) ? intval($_POST['walkin_id']) : 0;
    $status = trim($_POST['status'] ?? '');
    $allowed = ['waiting', 'in_service', 'served', 'cancelled'];
    if ($wid <= 0)      { echo 'Invalid walk-in.'; $conn->close(); exit; }
    if (!in_array($status, $allowed, true)) { echo 'Invalid status.'; $conn->close(); exit; }

    $upd = $conn->prepare("UPDATE walk_ins SET status = ? WHERE id = ? AND doctor_id = ?");
    $upd->bind_param("sii", $status, $wid, $doctor_id);
    if ($upd->execute()) { echo 'success'; } else { echo 'Update failed.'; }
    $upd->close();
    $conn->close();
    exit;
}

// GET — today's queue for this doctor
$today   = date('Y-m-d');
$walkins = $conn->query("
    SELECT w.id, w.arrival_time, w.status,
           p.name AS patient_name, p.contact
    FROM walk_ins w
    JOIN patients p ON w.patient_id = p.id
    WHERE w.doctor_id = $doctor_id AND w.arrival_date = '$today'
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
            'id'      => (int)$w['id'],
            'queue'   => $n,
            'name'    => $w['patient_name'],
            'contact' => $w['contact'],
            'arrival' => date('g:i A', strtotime($w['arrival_time'])),
            'status'  => $w['status'],
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
