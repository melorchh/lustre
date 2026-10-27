<?php
// Shared per-view data builders for the staff portals.
//
// Both the JSON endpoints (api_admin_data.php / api_doctor_data.php) and the
// thin PHP page-shells (admin_*.php / doctor_*.php) call these, so SQL lives in
// exactly one place and the React pages get an identical bundle either way.

if (defined('LUSTRE_STAFF_DATA_LOADED')) {
    return;
}
define('LUSTRE_STAFF_DATA_LOADED', true);

require_once __DIR__ . '/api.php';
require_once __DIR__ . '/doctor_capacity.php';

function staff_count($conn, $sql)
{
    $r = $conn->query($sql);
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}

/**
 * Build the admin view bundle.
 *
 * @param mysqli_compat $conn
 * @param string $view
 * @param array  $params optional: patient, date, period, from, to, status, payment, doctor_id, group
 * @return array
 */
function admin_view_data($conn, $view, array $params = array())
{
    $get = function ($k, $d = '') use ($params) {
        return isset($params[$k]) && $params[$k] !== '' ? $params[$k] : $d;
    };

    // doctors.daily_limit is read by the doctors view; make sure older
    // databases have it before the SELECT runs.
    ensure_doctor_capacity_schema($conn);

    switch ($view) {

    case 'dashboard':
        $todayWalkins = api_all($conn->query(
            "SELECT w.id, w.arrival_date, w.arrival_time, w.status, w.test_procedure,
                    p.name AS patient_name, p.patient_type, p.contact,
                    d.id AS doctor_id, d.name AS doctor_name, d.specialty
             FROM walk_ins w JOIN patients p ON w.patient_id=p.id JOIN doctors d ON w.doctor_id=d.id
             WHERE w.arrival_date = CURRENT_DATE
             ORDER BY d.name ASC, w.arrival_time ASC, w.created_at ASC, w.id ASC"));
        $queueByDoctor = array();
        foreach ($todayWalkins as $i => $w) {
            $doc = (string)$w['doctor_name'];
            $queueByDoctor[$doc] = ($queueByDoctor[$doc] ?? 0) + 1;
            $todayWalkins[$i]['queue'] = $queueByDoctor[$doc];
        }
        return array(
            'adminName' => (string)($_SESSION['admin_name'] ?? ''),
            'name'      => (string)($_SESSION['admin_name'] ?? ''),
            'stats' => array(
                'patients'     => staff_count($conn, "SELECT COUNT(*) c FROM patients"),
                'doctors'      => staff_count($conn, "SELECT COUNT(*) c FROM doctors"),
                'totalPatients' => staff_count($conn, "SELECT COUNT(*) c FROM patients"),
                'totalDoctors'  => staff_count($conn, "SELECT COUNT(*) c FROM doctors"),
                'totalAppointments' => staff_count($conn, "SELECT COUNT(*) c FROM appointments"),
                'todayAppointments' => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE appointment_date=CURRENT_DATE"),
                'pendingAppointments' => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE status='pending'"),
                'confirmedAppointments' => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE status='confirmed'"),
                'walkinsToday' => staff_count($conn, "SELECT COUNT(*) c FROM walk_ins WHERE arrival_date=CURRENT_DATE"),
                'pendingLabs' => staff_count($conn, "SELECT COUNT(*) c FROM lab_tests WHERE status='pending'"),
            ),
            'recentAppointments' => api_all($conn->query(
                "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.payment_status,
                        a.reschedule_count, a.last_rescheduled_at, a.appointment_duration,
                        p.name AS patient_name, d.name AS doctor_name, d.specialty
                 FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN doctors d ON a.doctor_id=d.id
                 ORDER BY a.created_at DESC LIMIT 10")),
            'rescheduleHistory' => api_all($conn->query(
                "SELECT appointment_id, old_date, old_time, new_date, new_time, rescheduled_at
                 FROM reschedule_history ORDER BY rescheduled_at DESC, id DESC")),
            'todayWalkins' => $todayWalkins,
            'patientsList' => api_all($conn->query("SELECT id, name, age, contact FROM patients ORDER BY name")),
            'doctors' => api_all($conn->query("SELECT id, name, specialty, test_procedures FROM doctors ORDER BY name")),
            'specialties' => array_values(array_filter(array_map(function ($r) { return $r['specialty']; },
                api_all($conn->query("SELECT DISTINCT specialty FROM doctors ORDER BY specialty"))))),
            'todayAppointments' => api_all($conn->query(
                "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.payment_status,
                        p.name AS patient_name, d.name AS doctor_name, d.specialty
                 FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN doctors d ON a.doctor_id=d.id
                 WHERE a.appointment_date=CURRENT_DATE ORDER BY a.appointment_time ASC")),
            'doctorsOnDuty' => api_all($conn->query(
                "SELECT d.name, d.specialty, COUNT(a.id) AS appointments
                 FROM doctors d LEFT JOIN appointments a ON d.id=a.doctor_id AND a.appointment_date=CURRENT_DATE
                 GROUP BY d.id, d.name, d.specialty ORDER BY d.name")),
            'topServices' => api_all($conn->query(
                "SELECT COALESCE(NULLIF(a.service_requested,''),'Consultation') AS service, COUNT(*) AS count
                 FROM appointments a GROUP BY service ORDER BY count DESC LIMIT 6")),
            'weeklyCounts' => api_all($conn->query(
                "SELECT to_char(appointment_date,'Dy') AS label, COUNT(*) AS count
                 FROM appointments WHERE appointment_date >= CURRENT_DATE - INTERVAL '6 days'
                 GROUP BY label ORDER BY MIN(appointment_date)")),
            'walkinStats' => array(
                'today'     => staff_count($conn, "SELECT COUNT(*) c FROM walk_ins WHERE arrival_date=CURRENT_DATE"),
                'pending'   => staff_count($conn, "SELECT COUNT(*) c FROM walk_ins WHERE arrival_date=CURRENT_DATE AND status='waiting'"),
                'completed' => staff_count($conn, "SELECT COUNT(*) c FROM walk_ins WHERE arrival_date=CURRENT_DATE AND status='served'"),
            ),
        );

    case 'appointments':
        require_once __DIR__ . '/unique_ids.php';
        ensure_unique_ids($conn);
        return array(
            'stats' => array(
                'total'     => staff_count($conn, "SELECT COUNT(*) c FROM appointments"),
                'pending'   => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE status='pending'"),
                'confirmed' => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE status='confirmed'"),
                'completed' => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE status='completed'"),
                'today'     => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE appointment_date=CURRENT_DATE"),
            ),
            'appointments' => api_all($conn->query(
                "SELECT a.id, a.patient_id, a.unique_code, a.appointment_date, a.appointment_time, a.status,
                        a.payment_status, a.is_urgent, a.reschedule_count, a.updated_at, a.appointment_duration,
                        a.service_requested, a.result, a.result_date, a.last_rescheduled_at,
                        (SELECT COUNT(*) FROM vitals v WHERE v.appointment_id = a.id) AS vital_count,
                        p.name AS patient_name, p.email AS patient_email, p.address AS patient_address, p.age AS patient_age,
                        d.name AS doctor_name, d.specialty
                 FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN doctors d ON a.doctor_id=d.id
                 ORDER BY a.appointment_date DESC, a.appointment_time DESC")),
            'rescheduleHistory' => api_all($conn->query(
                "SELECT appointment_id, old_date, old_time, new_date, new_time, rescheduled_at
                 FROM reschedule_history ORDER BY rescheduled_at DESC, id DESC")),
            'patientsList' => api_all($conn->query("SELECT id, name, age, contact, address FROM patients ORDER BY name")),
            'doctors' => api_all($conn->query("SELECT id, name, specialty, schedule, test_procedures, daily_limit FROM doctors ORDER BY name")),
            'specialties' => array_values(array_filter(array_map(function ($r) { return $r['specialty']; },
                api_all($conn->query("SELECT DISTINCT specialty FROM doctors ORDER BY specialty"))))),
        );

    case 'clients':
    case 'patients':
        require_once __DIR__ . '/unique_ids.php';
        ensure_unique_ids($conn);
        return array(
            'patients' => api_all($conn->query(
                "SELECT p.id, p.unique_code, p.name, p.email, p.contact, p.address, p.is_walk_in,
                        p.weight_kg, p.height_cm, p.patient_type, p.age, p.gender, p.created_at,
                        COUNT(a.id) AS appointment_count, MAX(a.appointment_date) AS last_appointment
                 FROM patients p LEFT JOIN appointments a ON p.id=a.patient_id
                 GROUP BY p.id, p.unique_code, p.name, p.email, p.contact, p.address, p.is_walk_in,
                          p.weight_kg, p.height_cm, p.patient_type, p.age, p.gender, p.created_at
                 ORDER BY p.created_at DESC")),
            'appointments' => api_all($conn->query(
                "SELECT a.patient_id, a.id, a.unique_code, a.appointment_date, a.appointment_time,
                        a.service_requested, a.status, d.name AS doctor_name
                 FROM appointments a JOIN doctors d ON a.doctor_id = d.id
                 ORDER BY a.appointment_date DESC, a.appointment_time DESC")),
            'doctors' => api_all($conn->query("SELECT id, name, specialty, test_procedures FROM doctors ORDER BY name")),
            'stats' => array(
                'total'   => staff_count($conn, "SELECT COUNT(*) c FROM patients"),
                'active'  => staff_count($conn, "SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE status IN ('pending','confirmed')"),
                'walkins' => staff_count($conn, "SELECT COUNT(*) c FROM patients WHERE is_walk_in = 1 OR is_walk_in = TRUE"),
            ),
        );

    case 'doctors':
        require_once __DIR__ . '/staff_auth.php';
        ensure_doctor_accounts_table($conn);
        // Same one-time seed the legacy page ran so every doctor offers the
        // default consultation services. Flagged so later removals stay removed.
        $conn->query("CREATE TABLE IF NOT EXISTS app_settings (
            skey VARCHAR(64) PRIMARY KEY,
            sval VARCHAR(255) NOT NULL DEFAULT ''
        )");
        $seeded = $conn->query("SELECT sval FROM app_settings WHERE skey = 'default_test_procedures_v2'");
        if (!$seeded || (int)$seeded->num_rows === 0) {
            $other    = 'Other (specify below)';
            $defaults = array('General Consultation', 'Follow-up Consultation', 'Diagnostic Procedure');
            $rows     = $conn->query("SELECT id, test_procedures FROM doctors");
            if ($rows) {
                $upd = $conn->prepare("UPDATE doctors SET test_procedures = ? WHERE id = ?");
                while ($row = $rows->fetch_assoc()) {
                    $orig  = array_values(array_filter(array_map('trim', explode("\n", (string)($row['test_procedures'] ?? '')))));
                    $lines = array_values(array_filter($orig, function ($x) use ($other) { return $x !== $other; }));
                    $changed = count($lines) !== count($orig);
                    foreach ($defaults as $d) {
                        if (!in_array($d, $lines, true)) { $lines[] = $d; $changed = true; }
                    }
                    if ($changed) {
                        $val = implode("\n", $lines);
                        $id  = (int)$row['id'];
                        $upd->bind_param("si", $val, $id);
                        $upd->execute();
                    }
                }
                $upd->close();
            }
            $conn->query("INSERT INTO app_settings (skey, sval) VALUES ('default_test_procedures_v2', '1') ON CONFLICT (skey) DO NOTHING");
        }
        $accounts = api_all($conn->query(
            "SELECT da.id AS id, da.id AS account_id, da.username, da.created_at, d.id AS doctor_id, d.name AS doctor_name, d.specialty
             FROM doctor_accounts da JOIN doctors d ON da.doctor_id=d.id ORDER BY d.name"));
        return array(
            'doctors' => api_all($conn->query(
                "SELECT d.id, d.name, d.specialty, d.schedule, d.experience, d.created_at, d.test_procedures,
                        COUNT(a.id) AS appointment_count
                 FROM doctors d LEFT JOIN appointments a ON d.id=a.doctor_id
                 GROUP BY d.id, d.name, d.specialty, d.schedule, d.experience, d.created_at, d.test_procedures
                 ORDER BY d.name")),
            'accounts' => $accounts,
            'specialties' => array_values(array_filter(array_map(function ($r) { return $r['specialty']; }, api_all($conn->query("SELECT DISTINCT specialty FROM doctors ORDER BY specialty"))))),
            'stats' => array(
                'total'        => staff_count($conn, "SELECT COUNT(*) c FROM doctors"),
                'withAccounts' => staff_count($conn, "SELECT COUNT(*) c FROM doctor_accounts"),
            ),
        );

    case 'schedules':
        return array(
            'schedules' => api_all($conn->query(
                "SELECT ds.*, d.name AS doctor_name, d.specialty FROM doctor_schedules ds JOIN doctors d ON ds.doctor_id=d.id
                 ORDER BY d.name, array_position(ARRAY['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'], ds.day_of_week)")),
            'doctors' => api_all($conn->query("SELECT id, name, specialty FROM doctors ORDER BY name")),
            'blocks' => api_all($conn->query(
                "SELECT tb.*, d.name AS doctor_name FROM doctor_time_blocks tb JOIN doctors d ON tb.doctor_id=d.id
                 ORDER BY tb.created_at DESC, d.name")),
        );

    case 'laboratory':
        return array(
            'tests' => api_all($conn->query(
                "SELECT lt.*, p.name AS patient_name, p.email AS patient_email,
                        d.name AS doctor_name, d.specialty, a.appointment_date, a.appointment_time
                 FROM lab_tests lt JOIN patients p ON lt.patient_id=p.id JOIN doctors d ON lt.doctor_id=d.id
                 LEFT JOIN appointments a ON lt.appointment_id=a.id ORDER BY lt.created_at DESC")),
            'patients' => api_all($conn->query("SELECT id, name, email FROM patients ORDER BY name")),
            'doctors' => api_all($conn->query("SELECT id, name, specialty FROM doctors ORDER BY name")),
            'testTypes' => array(
                'Complete Blood Count (CBC)',
                'Blood Typing',
                'Blood Sugar (FBS/RBS)',
                'HbA1c',
                'Lipid Profile',
                'Liver Function Test (LFT)',
                'Kidney Function Test (KFT)',
                'Urinalysis',
                'Pregnancy Test (hCG)',
                'Pap Smear',
                'Ultrasound (Obstetric)',
                'Ultrasound (Pelvic)',
                'Chest X-Ray',
                'ECG / EKG',
                'Hepatitis B Screening',
                'HIV Screening',
                'Thyroid Function Test',
                'VDRL / Syphilis Test',
                'Fetal Anomaly Scan',
                'Non-Stress Test (NST)',
                'Amniotic Fluid Index (AFI)',
                'Other',
            ),
            'stats' => array(
                'total'       => staff_count($conn, "SELECT COUNT(*) c FROM lab_tests"),
                'pending'     => staff_count($conn, "SELECT COUNT(*) c FROM lab_tests WHERE status='pending'"),
                'in_progress' => staff_count($conn, "SELECT COUNT(*) c FROM lab_tests WHERE status='processing'"),
                'completed'   => staff_count($conn, "SELECT COUNT(*) c FROM lab_tests WHERE status='completed'"),
            ),
        );

    case 'vitals':
        $patients = api_all($conn->query("SELECT id, name, email FROM patients ORDER BY name"));
        $selectedId = (int)$get('patient', 0);
        $selectedName = '';
        if ($selectedId <= 0 && !empty($patients)) {
            $selectedId = (int)$patients[0]['id'];
        }
        if ($selectedId > 0) {
            $pStmt = $conn->prepare("SELECT name FROM patients WHERE id = ?");
            $pStmt->bind_param("i", $selectedId);
            $pStmt->execute();
            $pRow = api_one($pStmt->get_result());
            $pStmt->close();
            if ($pRow) { $selectedName = (string)$pRow['name']; }
        }
        $vaccList = array();
        $summary  = array('next_due' => 'None scheduled');
        if ($selectedId > 0) {
            $vStmt = $conn->prepare(
                "SELECT vc.id, vc.patient_id, vc.vaccine_name, vc.dose_label, vc.administered_date,
                        vc.next_due_date, vc.notes, vc.created_at
                 FROM vaccinations vc WHERE vc.patient_id = ? ORDER BY vc.administered_date DESC");
            $vStmt->bind_param("i", $selectedId);
            $vStmt->execute();
            $vaccList = api_all($vStmt->get_result());
            $vStmt->close();

            $nStmt = $conn->prepare(
                "SELECT vaccine_name, dose_label, next_due_date FROM vaccinations
                 WHERE patient_id = ? AND next_due_date IS NOT NULL AND next_due_date >= CURRENT_DATE
                 ORDER BY next_due_date ASC LIMIT 1");
            $nStmt->bind_param("i", $selectedId);
            $nStmt->execute();
            $next = api_one($nStmt->get_result());
            $nStmt->close();
            if ($next) {
                $summary['next_due'] = $next['vaccine_name'] . ' ' . $next['dose_label'] . ' — '
                    . date('M j, Y', strtotime($next['next_due_date']));
            }
        }
        $slots = array();
        for ($t = strtotime('08:00'); $t < strtotime('17:00'); $t += 1800) {
            $slots[] = date('H:i', $t) . ':00';
        }
        return array(
            'patients'    => $patients,
            'doctors'     => api_all($conn->query("SELECT id, name, specialty FROM doctors ORDER BY name")),
            'selectedId'  => $selectedId,
            'selectedName'=> $selectedName,
            'summary'     => $summary,
            'slots'       => $slots,
            'vaccinations'=> $vaccList,
            'allVaccinations' => api_all($conn->query(
                "SELECT vc.id, vc.patient_id, vc.vaccine_name, vc.dose_label, vc.administered_date,
                        vc.next_due_date, vc.notes, vc.created_at, p.name AS patient_name
                 FROM vaccinations vc JOIN patients p ON vc.patient_id=p.id ORDER BY vc.administered_date DESC")),
            'stats' => array(
                'total'     => staff_count($conn, "SELECT COUNT(*) c FROM vaccinations"),
                'thisMonth' => staff_count($conn, "SELECT COUNT(*) c FROM vaccinations WHERE date_trunc('month', administered_date)=date_trunc('month', CURRENT_DATE)"),
                'dueSoon'   => staff_count($conn, "SELECT COUNT(*) c FROM vaccinations WHERE next_due_date IS NOT NULL AND next_due_date >= CURRENT_DATE AND next_due_date <= CURRENT_DATE + INTERVAL '30 days'"),
            ),
        );

    case 'walkins':
        $sel_date = (string)$get('date', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sel_date)) { $sel_date = date('Y-m-d'); }
        $stats  = array('total' => 0, 'waiting' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0);
        $sstmt = $conn->prepare("SELECT
            COUNT(*) AS total,
            SUM((status='waiting')::int) AS waiting,
            SUM((status='in_service')::int) AS in_service,
            SUM((status='served')::int) AS served,
            SUM((status='cancelled')::int) AS cancelled
            FROM walk_ins WHERE arrival_date = ?");
        $sstmt->bind_param("s", $sel_date);
        $sstmt->execute();
        $srow = api_one($sstmt->get_result());
        $sstmt->close();
        if ($srow) {
            $stats['total']       = (int)$srow['total'];
            $stats['waiting']     = (int)$srow['waiting'];
            $stats['in_progress'] = (int)$srow['in_service'];
            $stats['completed']   = (int)$srow['served'];
            $stats['cancelled']   = (int)$srow['cancelled'];
        }
        $wstmt = $conn->prepare(
            "SELECT w.id, w.arrival_date, w.arrival_time, w.status, w.created_at, w.test_procedure,
                    p.name AS patient_name, p.patient_type, p.contact,
                    d.id AS doctor_id, d.name AS doctor_name, d.specialty
             FROM walk_ins w JOIN patients p ON w.patient_id=p.id JOIN doctors d ON w.doctor_id=d.id
             WHERE w.arrival_date = ? ORDER BY d.name ASC, w.arrival_time ASC, w.created_at ASC, w.id ASC");
        $wstmt->bind_param("s", $sel_date);
        $wstmt->execute();
        $walkins = api_all($wstmt->get_result());
        $wstmt->close();
        $queues = array();
        foreach ($walkins as $i => $w) {
            $did = (int)$w['doctor_id'];
            $queues[$did] = ($queues[$did] ?? 0) + 1;
            $walkins[$i]['queue'] = $queues[$did];
        }
        return array(
            'date'    => $sel_date,
            'walkins' => $walkins,
            'stats'   => $stats,
            'doctors' => api_all($conn->query("SELECT id, name, specialty, test_procedures FROM doctors ORDER BY name")),
            'tests'   => array_values(array_filter(array_map('trim', explode("\n",
                "General Consultation\nFollow-up Consultation\nDiagnostic Procedure\nLaboratory Test\nPrenatal Checkup\nVaccination")))),
        );

    case 'reports':
        $period  = (string)$get('period');
        $from    = (string)$get('from');
        $to      = (string)$get('to');
        $status  = (string)$get('status');
        $payment = (string)$get('payment');
        $doctorId= (int)$get('doctor_id', 0);
        $group   = (string)$get('group', 'date');
        if ($period === 'today')     { $from = date('Y-m-d'); $to = date('Y-m-d'); }
        elseif ($period === 'week')  { $from = date('Y-m-d', strtotime('this week Monday')); $to = date('Y-m-d'); }
        elseif ($period === 'month') { $from = date('Y-m-01'); $to = date('Y-m-t'); }
        elseif ($period === 'year')  { $from = date('Y-01-01'); $to = date('Y-12-31'); }

        $where = "1=1"; $paramsArr = array(); $types = "";
        if ($from)     { $where .= " AND a.appointment_date >= ?"; $paramsArr[] = $from;     $types .= "s"; }
        if ($to)       { $where .= " AND a.appointment_date <= ?"; $paramsArr[] = $to;       $types .= "s"; }
        if ($status)   { $where .= " AND a.status = ?";            $paramsArr[] = $status;   $types .= "s"; }
        if ($payment)  { $where .= " AND a.payment_status = ?";    $paramsArr[] = $payment;  $types .= "s"; }
        if ($doctorId) { $where .= " AND a.doctor_id = ?";         $paramsArr[] = $doctorId; $types .= "i"; }

        $run = function ($sql) use ($conn, $paramsArr, $types) {
            if (!empty($paramsArr)) {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$paramsArr);
                $stmt->execute();
                $res = $stmt->get_result();
                $stmt->close();
                return $res;
            }
            return $conn->query($sql);
        };

        $summary = array('total' => 0, 'patients' => 0, 'completed' => 0, 'cancelled' => 0, 'revenue' => 0);
        $mRes = $run("SELECT COUNT(a.id) AS appts, COUNT(DISTINCT a.patient_id) AS patients,
                             SUM((a.status='completed')::int) AS completed, SUM((a.status='cancelled')::int) AS cancelled,
                             SUM((a.payment_status='paid')::int) AS paid
                      FROM appointments a WHERE $where");
        if ($mRes && $mRow = $mRes->fetch_assoc()) {
            $summary['total']     = (int)$mRow['appts'];
            $summary['patients']  = (int)$mRow['patients'];
            $summary['completed'] = (int)$mRow['completed'];
            $summary['cancelled'] = (int)$mRow['cancelled'];
            $summary['revenue']   = (int)$mRow['paid'] * 500;
        }

        switch ($group) {
            case 'week':
                $labelExpr = "(a.appointment_date - ((extract(isodow from a.appointment_date)::int - 1) * interval '1 day'))::date AS bucket";
                $groupBy = "(a.appointment_date - ((extract(isodow from a.appointment_date)::int - 1) * interval '1 day'))::date";
                $sortExpr = $groupBy; break;
            case 'month':
                $labelExpr = "to_char(a.appointment_date, 'YYYY-MM') AS bucket";
                $groupBy = "to_char(a.appointment_date, 'YYYY-MM')";
                $sortExpr = "MIN(a.appointment_date)"; break;
            default:
                $labelExpr = "a.appointment_date AS bucket";
                $groupBy = "a.appointment_date";
                $sortExpr = "a.appointment_date";
        }
        $gRes = $run("SELECT $labelExpr, COUNT(a.id) AS appts, COUNT(DISTINCT a.patient_id) AS patients,
                             SUM((a.status='completed')::int) AS completed, SUM((a.status='cancelled')::int) AS cancelled,
                             SUM((a.payment_status='paid')::int) AS paid
                      FROM appointments a WHERE $where GROUP BY $groupBy ORDER BY $sortExpr ASC");
        $breakdown = array();
        if ($gRes) {
            while ($gRow = $gRes->fetch_assoc()) {
                $bucket = (string)$gRow['bucket'];
                if ($group === 'week') {
                    $label = 'Week of ' . date('M j, Y', strtotime($bucket));
                } elseif ($group === 'month') {
                    $label = date('M Y', strtotime($bucket . '-01'));
                } else {
                    $label = date('M j, Y', strtotime($bucket));
                }
                $paid = (int)$gRow['paid'];
                $breakdown[] = array(
                    'bucket'    => $bucket,
                    'label'     => $label,
                    'appts'     => (int)$gRow['appts'],
                    'patients'  => (int)$gRow['patients'],
                    'completed' => (int)$gRow['completed'],
                    'cancelled' => (int)$gRow['cancelled'],
                    'paid'      => $paid,
                    'revenue'   => $paid * 500,
                );
            }
        }

        $byDoctor = api_all($run("SELECT d.name AS doctor_name, d.specialty, COUNT(a.id) AS count
                      FROM appointments a JOIN doctors d ON a.doctor_id=d.id WHERE $where
                      GROUP BY d.id, d.name, d.specialty ORDER BY count DESC"));
        $byService = api_all($run("SELECT COALESCE(NULLIF(a.service_requested,''),'Consultation') AS service_requested, COUNT(*) AS count
                      FROM appointments a WHERE $where GROUP BY service_requested ORDER BY count DESC"));
        $byStatus = api_all($run("SELECT a.status AS month, COUNT(*) AS count FROM appointments a WHERE $where GROUP BY a.status ORDER BY count DESC"));
        $byPayment = api_all($run("SELECT a.payment_status AS month, COUNT(*) AS count FROM appointments a WHERE $where GROUP BY a.payment_status ORDER BY count DESC"));

        return array(
            'range'     => array('from' => $from, 'to' => $to),
            'filters'   => array('period' => $period, 'group' => $group, 'status' => $status,
                                 'payment' => $payment, 'doctor_id' => $doctorId),
            'summary'   => $summary,
            'metrics'   => array(
                'appointments' => $summary['total'],
                'patients'     => $summary['patients'],
                'completed'    => $summary['completed'],
                'cancelled'    => $summary['cancelled'],
                'paid'         => (int)round($summary['revenue'] / 500),
                'revenue'      => $summary['revenue'],
            ),
            'breakdown' => $breakdown,
            'byDay'     => $breakdown,
            'doctors'   => api_all($conn->query("SELECT id, name, specialty FROM doctors ORDER BY name")),
            'byDoctor'  => $byDoctor,
            'byService' => $byService,
            'byStatus'  => $byStatus,
            'byPayment' => $byPayment,
        );

    case 'history':
        $appointments = api_all($conn->query(
            "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.payment_status,
                    a.created_at, a.updated_at, a.reschedule_count, a.last_rescheduled_at, a.appointment_duration,
                    p.name AS patient_name, p.email AS patient_email, p.contact,
                    p.address AS patient_address, p.age AS patient_age, d.name AS doctor_name, d.specialty
             FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN doctors d ON a.doctor_id=d.id
             WHERE a.status IN ('completed','cancelled') ORDER BY a.updated_at DESC"));
        $labTests = api_all($conn->query(
            "SELECT lt.id, lt.test_type, lt.priority, lt.status, lt.scheduled_date, lt.result, lt.notes,
                    lt.created_at, lt.updated_at,
                    p.name AS patient_name, p.email AS patient_email, d.name AS doctor_name, d.specialty
             FROM lab_tests lt JOIN patients p ON lt.patient_id=p.id JOIN doctors d ON lt.doctor_id=d.id
             WHERE lt.status IN ('completed','cancelled') ORDER BY lt.updated_at DESC"));
        $vitals = api_all($conn->query(
            "SELECT v.id, v.visit_date, v.weight_kg, v.blood_pressure, v.heart_rate, v.fundal_height,
                    v.notes, v.created_at, p.name AS patient_name, p.email AS patient_email
             FROM vitals v JOIN patients p ON v.patient_id=p.id ORDER BY v.visit_date DESC"));
        $vaccinations = api_all($conn->query(
            "SELECT va.id, va.vaccine_name, va.dose_label, va.administered_date, va.next_due_date,
                    va.notes, va.created_at, p.name AS patient_name, p.email AS patient_email
             FROM vaccinations va JOIN patients p ON va.patient_id=p.id ORDER BY va.administered_date DESC"));
        $historyMap = array();
        $hRows = api_all($conn->query(
            "SELECT appointment_id, old_date, old_time, new_date, new_time, rescheduled_at
             FROM reschedule_history ORDER BY rescheduled_at DESC, id DESC"));
        foreach ($hRows as $h) {
            $historyMap[(int)$h['appointment_id']][] = $h;
        }
        $archiveDays = array('appointments' => array(), 'labtests' => array(), 'vitals' => array(), 'vaccinations' => array());
        foreach ($appointments as $a) {
            $day = substr((string)$a['updated_at'], 0, 10);
            $archiveDays['appointments'][$day] = ($archiveDays['appointments'][$day] ?? 0) + 1;
        }
        foreach ($labTests as $l) {
            $day = substr((string)$l['updated_at'], 0, 10);
            $archiveDays['labtests'][$day] = ($archiveDays['labtests'][$day] ?? 0) + 1;
        }
        foreach ($vitals as $v) {
            $day = substr((string)$v['created_at'], 0, 10);
            $archiveDays['vitals'][$day] = ($archiveDays['vitals'][$day] ?? 0) + 1;
        }
        foreach ($vaccinations as $vc) {
            $day = substr((string)$vc['created_at'], 0, 10);
            $archiveDays['vaccinations'][$day] = ($archiveDays['vaccinations'][$day] ?? 0) + 1;
        }
        return array(
            'stats' => array(
                'allAppointments' => staff_count($conn, "SELECT COUNT(*) c FROM appointments"),
                'appointments'    => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE status IN ('completed','cancelled')"),
                'completed'       => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE status='completed'"),
                'cancelled'       => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE status='cancelled'"),
                'paid'            => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE payment_status='paid'"),
                'labDone'         => staff_count($conn, "SELECT COUNT(*) c FROM lab_tests WHERE status='completed'"),
                'vitals'          => staff_count($conn, "SELECT COUNT(*) c FROM vitals"),
                'vaccinations'    => staff_count($conn, "SELECT COUNT(*) c FROM vaccinations"),
                'walkins'         => staff_count($conn, "SELECT COUNT(*) c FROM walk_ins WHERE status='served'"),
            ),
            'appointments'  => $appointments,
            'labTests'      => $labTests,
            'vitals'        => $vitals,
            'vaccinations'  => $vaccinations,
            'rescheduleHistory' => $historyMap,
            'archiveDays'   => $archiveDays,
            'walkins' => api_all($conn->query(
                "SELECT w.id, w.arrival_date, w.arrival_time, w.status, w.test_procedure, w.created_at,
                        p.name AS patient_name, d.name AS doctor_name
                 FROM walk_ins w JOIN patients p ON w.patient_id=p.id JOIN doctors d ON w.doctor_id=d.id
                 WHERE w.status='served' ORDER BY w.arrival_date DESC")),
        );

    case 'profile':
        return array(
            'adminName' => (string)($_SESSION['admin_name'] ?? ''),
            'username'  => (string)($_SESSION['admin_username'] ?? ''),
            'mustChange'=> !empty($_SESSION['admin_must_change']),
        );
    }
    return array();
}

/**
 * Build the shared patient-profile bundle (admin or doctor viewers).
 *
 * @param int $patientId
 * @param int $doctorId  when > 0, results are limited to that doctor
 */
function client_profile_data($conn, $patientId, $doctorId = 0)
{
    require_once __DIR__ . '/unique_ids.php';
    ensure_unique_ids($conn);
    require_once __DIR__ . '/../pages/walkin_schema.php';
    walkins_ensure_result_column($conn);

    $patient = null;
    if ($patientId > 0) {
        $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->bind_param("i", $patientId);
        $stmt->execute();
        $patient = api_one($stmt->get_result());
        $stmt->close();
    }
    if (!$patient) {
        return array('patient' => null, 'appointments' => array(), 'vitals' => array(), 'labs' => array(), 'lastVisit' => null);
    }

    $apptStmt = $conn->prepare(
        "SELECT a.*, d.name AS doctor_name, d.specialty
         FROM appointments a JOIN doctors d ON a.doctor_id = d.id
         WHERE a.patient_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    $apptStmt->bind_param("i", $patientId);
    $apptStmt->execute();
    $appointments = api_all($apptStmt->get_result());
    $apptStmt->close();

    $vitStmt = $conn->prepare(
        "SELECT * FROM vitals WHERE patient_id = ? ORDER BY visit_date DESC");
    $vitStmt->bind_param("i", $patientId);
    $vitStmt->execute();
    $vitals = api_all($vitStmt->get_result());
    $vitStmt->close();

    $labStmt = $conn->prepare(
        "SELECT lt.*, d.name AS doctor_name, d.specialty
         FROM lab_tests lt JOIN doctors d ON lt.doctor_id = d.id
         WHERE lt.patient_id = ? ORDER BY lt.created_at DESC");
    $labStmt->bind_param("i", $patientId);
    $labStmt->execute();
    $labs = api_all($labStmt->get_result());
    $labStmt->close();

    $lastVisit = null;
    if (!empty($appointments)) {
        $lastVisit = $appointments[0]['appointment_date'];
    }

    return array(
        'patient'      => $patient,
        'appointments' => $appointments,
        'vitals'       => $vitals,
        'labs'         => $labs,
        'lastVisit'    => $lastVisit,
    );
}

/**
 * Build the doctor view bundle.
 */
function doctor_view_data($conn, $view, array $params = array())
{
    $doctor_id = (int)($_SESSION['doctor_id'] ?? 0);

    switch ($view) {

    case 'dashboard':
        return array(
            'stats' => array(
                'total'          => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id"),
                'todayAppointments' => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND appointment_date=CURRENT_DATE AND status IN ('confirmed','pending')"),
                'pending'        => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND status='pending'"),
                'completed'      => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND status='completed'"),
                'pendingLabs'    => staff_count($conn, "SELECT COUNT(*) c FROM lab_tests WHERE doctor_id=$doctor_id AND status='pending'"),
            ),
            'todayAppointments' => api_all($conn->query(
                "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.patient_id, a.unique_code,
                        p.name AS patient_name, a.service_requested
                 FROM appointments a JOIN patients p ON a.patient_id=p.id
                 WHERE a.doctor_id=$doctor_id AND a.appointment_date=CURRENT_DATE
                 ORDER BY a.appointment_time ASC")),
            'upcomingAppointments' => api_all($conn->query(
                "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.patient_id, a.unique_code,
                        p.name AS patient_name, a.service_requested
                 FROM appointments a JOIN patients p ON a.patient_id=p.id
                 WHERE a.doctor_id=$doctor_id AND a.appointment_date >= CURRENT_DATE AND a.status IN ('pending','confirmed')
                 ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 6")),
            'recentLabs' => api_all($conn->query(
                "SELECT lt.id, lt.patient_id, lt.test_type, lt.status, lt.created_at, p.name AS patient_name
                 FROM lab_tests lt JOIN patients p ON lt.patient_id=p.id
                 WHERE lt.doctor_id=$doctor_id ORDER BY lt.created_at DESC LIMIT 6")),
            'scheduleToday' => api_all($conn->query(
                "SELECT to_char(start_time,'HH24:MI') AS start_time, to_char(end_time,'HH24:MI') AS end_time
                 FROM doctor_schedules WHERE doctor_id=$doctor_id AND is_active=1
                   AND day_of_week = to_char(CURRENT_DATE,'FMDay')")),
        );

    case 'appointments':
        return array(
            'stats' => array(
                'total'     => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id"),
                'pending'   => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND status='pending'"),
                'completed' => staff_count($conn, "SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND status='completed'"),
            ),
            'appointments' => api_all($conn->query(
                "SELECT a.*, p.name AS patient_name, p.email AS patient_email
                 FROM appointments a JOIN patients p ON a.patient_id=p.id
                 WHERE a.doctor_id=$doctor_id
                 ORDER BY CASE WHEN a.status='pending' THEN 1 WHEN a.status='confirmed' THEN 2
                               WHEN a.status='completed' THEN 3 WHEN a.status='cancelled' THEN 4 END,
                          a.appointment_date DESC, a.appointment_time DESC")),
        );

    case 'clients':
        require_once __DIR__ . '/unique_ids.php';
        ensure_unique_ids($conn);
        return array(
            'stats' => array(
                'total'  => staff_count($conn, "SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE doctor_id=$doctor_id"),
                'active' => staff_count($conn, "SELECT COUNT(DISTINCT a.patient_id) c FROM appointments a WHERE a.doctor_id=$doctor_id AND a.status IN ('pending','confirmed')"),
            ),
            'patients' => api_all($conn->query(
                "SELECT p.id, p.unique_code, p.name, p.email, p.contact, p.address, p.is_walk_in,
                        p.weight_kg, p.height_cm, p.patient_type, p.age, p.gender, p.created_at,
                        COUNT(a.id) AS appointment_count, MAX(a.appointment_date) AS last_appointment
                 FROM patients p JOIN appointments a ON p.id=a.patient_id
                 WHERE a.doctor_id=$doctor_id
                 GROUP BY p.id, p.unique_code, p.name, p.email, p.contact, p.address, p.is_walk_in,
                          p.weight_kg, p.height_cm, p.patient_type, p.age, p.gender, p.created_at
                 ORDER BY MAX(a.appointment_date) DESC")),
        );

    case 'laboratory':
        return array(
            'stats' => array(
                'total'     => staff_count($conn, "SELECT COUNT(*) c FROM lab_tests WHERE doctor_id=$doctor_id"),
                'pending'   => staff_count($conn, "SELECT COUNT(*) c FROM lab_tests WHERE doctor_id=$doctor_id AND status='pending'"),
                'in_progress' => staff_count($conn, "SELECT COUNT(*) c FROM lab_tests WHERE doctor_id=$doctor_id AND status='processing'"),
                'completed' => staff_count($conn, "SELECT COUNT(*) c FROM lab_tests WHERE doctor_id=$doctor_id AND status='completed'"),
            ),
            'tests' => api_all($conn->query(
                "SELECT lt.*, p.name AS patient_name, p.email AS patient_email, a.appointment_date, a.appointment_time
                 FROM lab_tests lt JOIN patients p ON lt.patient_id=p.id
                 LEFT JOIN appointments a ON lt.appointment_id=a.id
                 WHERE lt.doctor_id=$doctor_id ORDER BY lt.created_at DESC")),
        );

    case 'schedule':
        return array(
            'schedules' => api_all($conn->query(
                "SELECT * FROM doctor_schedules WHERE doctor_id=$doctor_id
                 ORDER BY array_position(ARRAY['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'], day_of_week)")),
            'blocks' => api_all($conn->query(
                "SELECT * FROM doctor_time_blocks WHERE doctor_id=$doctor_id ORDER BY created_at DESC")),
        );

    case 'profile':
        return array(
            'doctorName' => (string)($_SESSION['doctor_name'] ?? ''),
            'username'   => (string)($_SESSION['doctor_username'] ?? ''),
        );
    }
    return array();
}
