<?php
require __DIR__ . '/../src/session.php';

$is_admin  = isset($_SESSION['admin_id']);
$is_doctor = isset($_SESSION['doctor_id']);

if (!$is_admin && !$is_doctor) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/unique_ids.php';
ensure_unique_ids($conn);

$display_name = $is_admin ? $_SESSION['admin_name'] : $_SESSION['doctor_name'];
$doctor_id    = $is_doctor ? (int)$_SESSION['doctor_id'] : 0;

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$patient = null;
$appointments = null;
$vitals_list = null;
$vitals_by_appt = [];
$lab_tests = null;

if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($patient) {
    $appointments = $conn->query("
        SELECT a.*, d.name AS doctor_name, d.specialty
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.patient_id = $patient_id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $vitals_list = $conn->query("SELECT * FROM vitals WHERE patient_id = $patient_id ORDER BY visit_date DESC");

    $vitals_by_appt = [];
    $vres = $conn->query("SELECT appointment_id, COUNT(*) AS cnt FROM vitals WHERE patient_id = $patient_id AND appointment_id IS NOT NULL GROUP BY appointment_id");
    if ($vres) {
        while ($vr = $vres->fetch_assoc()) {
            $vitals_by_appt[(int)$vr['appointment_id']] = (int)$vr['cnt'];
        }
    }

    $lab_tests = $conn->query("
        SELECT lt.*, d.name AS doctor_name
        FROM lab_tests lt
        JOIN doctors d ON lt.doctor_id = d.id
        WHERE lt.patient_id = $patient_id
        ORDER BY lt.created_at DESC
    ");

    $created = !empty($patient['created_at']) ? new DateTime($patient['created_at']) : null;
    $last_visit = ['last_appt' => null];
    $lv_res = $conn->query("SELECT MAX(appointment_date) AS last_appt FROM appointments WHERE patient_id = $patient_id");
    if ($lv_res) {
        $lv_row = $lv_res->fetch_assoc();
        if ($lv_row) {
            $last_visit = $lv_row;
        }
    }
    $last_visit_fmt = (!empty($last_visit['last_appt'])) ? (new DateTime($last_visit['last_appt']))->format('M j, Y') : 'Never';

} else {
    $patient_id = 0;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#16a34a">
    <link rel="icon" href="images/Lustre.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
    <script>try{if(localStorage.getItem('meTheme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}</script>
    <link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/../../admin.css') ?>">
    <?php if ($is_doctor): ?><link rel="stylesheet" href="doctor.css?v=<?= filemtime(__DIR__ . '/../../doctor.css') ?>"><?php endif; ?>
    <title>Client Profile - LustreMDC <?php echo $is_admin ? 'Admin' : 'Doctor'; ?></title>
</head>
<body<?php echo $is_doctor ? ' class="doctor-page"' : ''; ?>>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="admin-layout">
    <?php if ($is_admin): ?>
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-brand">
            <span class="brand-icon"><img src="images/Lustre.png" alt="LustreMDC Logo" width="42" height="42" /></span>
            <span class="brand-text"><span>Admin</span></span>
        </div>
        <div class="sidebar-user">
            <div class="avatar avatar--sm"><?php echo strtoupper(substr(trim($display_name),0,1)); ?></div>
            <div class="sidebar-user-body"><div class="sidebar-user-label">Logged in as</div><div class="sidebar-user-name"><?php echo htmlspecialchars($display_name); ?></div></div>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><span class="rail-label">Dashboard</span></a>
            <a href="admin_appointments.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span><span class="rail-label">Appointments</span></a>
            <a href="admin_clients.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><span class="rail-label">Patients</span></a>
            <a href="admin_walkins.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.6 4.7L18 9.3l-4.4 1.6L12 15.6l-1.6-4.7L6 9.3l4.4-1.6z" style="stroke-width:1.8"/><path d="M5 20h14"/></svg></span><span class="rail-label">Walk-ins</span></a>
            <a href="admin_doctors.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span><span class="rail-label">Doctors</span></a>
            <a href="admin_schedules.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span class="rail-label">Schedules</span></a>
            <a href="admin_laboratory.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg></span><span class="rail-label">Laboratory</span></a>
            <a href="admin_vitals.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span><span class="rail-label">Vaccines</span></a>
            <a href="admin_reports.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15v-4"/><path d="M12 15V7"/><path d="M17 15v-6"/></svg></span><span class="rail-label">Reports</span></a>
            <a href="admin_history.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg></span><span class="rail-label">Archives</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="admin_logout.php" class="btn-logout" data-tip="Sign Out"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span><span class="rail-label">Sign Out</span></a>
        </div>
    </aside>
    <?php else: ?>
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-brand">
            <span class="brand-icon"><img src="images/Lustre.png" alt="LustreMDC Logo" width="42" height="42" /></span>
            <span class="brand-text"><span>Doctor</span></span>
        </div>
        <div class="sidebar-user">
            <div class="avatar avatar--sm"><?php echo strtoupper(substr(trim($display_name),0,1)); ?></div>
            <div class="sidebar-user-body"><div class="sidebar-user-label">Signed in as</div><div class="sidebar-user-name">Dr. <?php echo htmlspecialchars($display_name); ?></div></div>
        </div>
        <nav class="sidebar-nav">
            <a href="doctor_dashboard.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><span class="rail-label">Dashboard</span></a>
            <a href="doctor_appointments.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 18l3 3 3-3"/></svg></span><span class="rail-label">Appointments</span></a>
            <a href="doctor_clients.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><span class="rail-label">Patients</span></a>
            <a href="doctor_laboratory.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg></span><span class="rail-label">Laboratory</span></a>
            <a href="doctor_schedule.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span class="rail-label">Availability</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="doctor_logout.php" class="btn-logout" data-tip="Sign Out"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span><span class="rail-label">Sign Out</span></a>
        </div>
    </aside>
    <?php endif; ?>

    <main class="admin-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Open menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <span class="topbar-title">Client Profile</span>
            </div>
            <div class="topbar-actions">
                <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong><?php echo htmlspecialchars(explode(' ', trim($display_name))[0]); ?></strong></div>
                <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($display_name),0,1)); ?></div>
            </div>
        </header>

        <?php if ($patient): ?>
        <div class="summary-chips">
            <div class="summary-chip"><label>Client ID</label><strong><?php echo htmlspecialchars($patient['unique_code'] ?? ('CL-' . str_pad((string)$patient['id'], 6, '0', STR_PAD_LEFT))); ?></strong></div>
            <div class="summary-chip"><label>Client</label><strong><?php echo htmlspecialchars($patient['name']); ?></strong></div>
            <div class="summary-chip"><label>Last Visit</label><strong><?php echo $last_visit_fmt; ?></strong></div>
            <div class="summary-chip"><label>Registered</label><strong><?php echo $created ? $created->format('M j, Y') : '&mdash;'; ?></strong></div>
        </div>

        <!-- Client Information -->
        <div class="content-card">
            <h2>Client Information</h2>
            <div class="stats-grid" style="margin-top:.4rem">
                <div class="stat-card">
                    <div class="stat-number" style="font-size:1.15rem"><?php echo htmlspecialchars($patient['name']); ?></div>
                    <div class="stat-label">Full Name</div>
                </div>
                <div class="stat-card" style="border-top-color:#3b82f6">
                    <div class="stat-number" style="font-size:1.15rem;color:#3b82f6"><?php echo $patient['age'] !== null ? (int)$patient['age'] . ' yrs' : '&mdash;'; ?></div>
                    <div class="stat-label">Age<?php echo $patient['patient_type'] ? ' &middot; ' . htmlspecialchars(ucfirst($patient['patient_type'])) : ''; ?></div>
                </div>
                <div class="stat-card" style="border-top-color:#10b981">
                    <div class="stat-number" style="font-size:1.15rem;color:#10b981"><?php echo $patient['gender'] ? htmlspecialchars(ucfirst($patient['gender'])) : '&mdash;'; ?></div>
                    <div class="stat-label">Gender</div>
                </div>
                <div class="stat-card" style="border-top-color:#f59e0b">
                    <div class="stat-number" style="font-size:1.05rem;color:#f59e0b">
                        <?php
                        $w = $patient['weight_kg'] !== null ? $patient['weight_kg'] . ' kg' : '&mdash;';
                        $h = $patient['height_cm'] !== null ? $patient['height_cm'] . ' cm' : '&mdash;';
                        echo $w . ' / ' . $h;
                        ?>
                    </div>
                    <div class="stat-label">Weight / Height</div>
                </div>
            </div>
            <div class="table-scroll" style="margin-top:.8rem">
                <table>
                    <tbody>
                        <tr>
                            <th style="width:180px">Status</th>
                            <td><?php if (!empty($patient['is_walk_in'])): ?><span class="badge badge-urgent">Walk-In Client</span><?php else: ?><span class="badge badge-active">Registered Client</span><?php endif; ?></td>
                        </tr>
                        <tr><th>Email</th><td><?php echo htmlspecialchars($patient['email']); ?></td></tr>
                        <tr><th>Contact</th><td><?php echo htmlspecialchars($patient['contact']); ?></td></tr>
                        <tr><th>Address</th><td><?php echo $patient['address'] !== '' && $patient['address'] !== null ? htmlspecialchars($patient['address']) : '&mdash;'; ?></td></tr>
                        <tr><th>Registered On</th><td><?php echo $created ? $created->format('M j, Y') : '&mdash;'; ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Appointments -->
        <div class="content-card">
            <h2>Appointments</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Appointment ID</th>
                            <th>Date &amp; Time</th>
                            <th>Doctor</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Result / Findings</th>
                            <th>Vitals</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($appointments && $appointments->num_rows > 0):
                        while ($ap = $appointments->fetch_assoc()):
                            $s = $ap['status'];
                            $date_fmt = !empty($ap['appointment_date']) ? (new DateTime($ap['appointment_date']))->format('M j, Y') : '&mdash;';
                            $time_fmt = !empty($ap['appointment_time']) ? date('g:i A', strtotime($ap['appointment_time'])) : '&mdash;';
                            $status_badge = [
                                'pending'    => 'badge-pending',
                                'confirmed'  => 'badge-active',
                                'completed'  => 'badge-completed',
                                'cancelled'  => 'badge-cancelled',
                            ][$s] ?? 'badge-pending';
                            $appt_id = (int)$ap['id'];
                            $has_vitals = isset($vitals_by_appt[$appt_id]);
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($ap['unique_code'] ?? ('APT-' . str_pad((string)$ap['id'], 6, '0', STR_PAD_LEFT))); ?></strong><br><small class="cell-sub">#<?php echo (int)$ap['id']; ?></small></td>
                            <td><?php echo $date_fmt; ?><br><small class="cell-sub"><?php echo $time_fmt; ?></small></td>
                            <td>Dr. <?php echo htmlspecialchars($ap['doctor_name']); ?><br><small class="cell-sub"><?php echo htmlspecialchars($ap['specialty']); ?></small></td>
                            <td><?php echo htmlspecialchars($ap['service_requested'] ?: 'General consultation'); ?></td>
                            <td><span class="badge <?php echo $status_badge; ?>"><?php echo ucfirst($s); ?></span></td>
                            <td class="findings-cell">
                                <?php
                                $show_findings_action = $is_doctor && (int)$ap['doctor_id'] === $doctor_id && $s === 'confirmed';
                                $has_result    = !empty($ap['result']);
                                $recorded_fmt  = !empty($ap['result_date']) ? date('M j, Y g:i A', strtotime($ap['result_date'])) : '';
                                $result_attr   = $has_result ? str_replace(["\r\n", "\r", "\n"], '&#10;', htmlspecialchars($ap['result'], ENT_QUOTES)) : '';
                                $date_txt = ($date_fmt === '&mdash;') ? '' : $date_fmt;
                                $time_txt = ($time_fmt === '&mdash;') ? '' : $time_fmt;
                                $when_attr = trim($date_txt . ($date_txt && $time_txt ? ' · ' : '') . $time_txt);
                                if ($when_attr === '') { $when_attr = 'Not scheduled'; }
                                ?>
                                <?php if ($has_result || !$show_findings_action): ?>
                                    <?php if ($has_result): ?>
                                        <span class="muted-sub">&mdash;</span>
                                    <?php else: ?>
                                        <span class="muted-sub">No result yet</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($has_result || $show_findings_action): ?>
                                    <div class="findings-actions">
                                        <?php if ($has_result): ?>
                                            <button type="button" class="btn-sm btn-view js-result-view"
                                                data-apt="<?php echo $appt_id; ?>"
                                                data-code="<?php echo htmlspecialchars($ap['unique_code'] ?? ('APT-' . str_pad((string)$ap['id'], 6, '0', STR_PAD_LEFT)), ENT_QUOTES); ?>"
                                                data-patient="<?php echo htmlspecialchars($patient['name'], ENT_QUOTES); ?>"
                                                data-doctor="<?php echo htmlspecialchars('Dr. ' . $ap['doctor_name'] . ' (' . $ap['specialty'] . ')', ENT_QUOTES); ?>"
                                                data-service="<?php echo htmlspecialchars($ap['service_requested'] ?: 'General consultation', ENT_QUOTES); ?>"
                                                data-when="<?php echo htmlspecialchars($when_attr, ENT_QUOTES); ?>"
                                                data-status="<?php echo htmlspecialchars($s, ENT_QUOTES); ?>"
                                                data-recorded="<?php echo htmlspecialchars($recorded_fmt, ENT_QUOTES); ?>"
                                                data-result="<?php echo $result_attr; ?>"
                                                onclick="openResultView(this)">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                                View Result
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($show_findings_action): ?>
                                            <button type="button" class="btn-sm btn-edit" onclick="openResultModal(<?php echo $appt_id; ?>, '<?php echo addslashes(htmlspecialchars($patient['name'])); ?>', '<?php echo str_replace(["\r\n", "\r", "\n"], '\n', addslashes(htmlspecialchars($ap['result'] ?? ''))); ?>')">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                                Findings
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($has_vitals): ?>
                                    <button type="button" class="btn-sm btn-vitals" title="View patient vitals" onclick="openApptVitals(<?php echo $appt_id; ?>, '<?php echo htmlspecialchars($ap['unique_code'] ?? '', ENT_QUOTES); ?>')"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg> Vitals</button>
                                <?php else: ?>
                                    <span class="muted-sub">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>No appointments recorded for this client yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Lab Tests -->
        <div class="content-card">
            <h2>Lab Tests</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Test Type</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Ordered By</th>
                            <th>Scheduled</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($lab_tests && $lab_tests->num_rows > 0):
                        while ($lt = $lab_tests->fetch_assoc()):
                            $priority_badge = ['normal' => 'badge-normal', 'urgent' => 'badge-urgent', 'stat' => 'badge-urgent'][$lt['priority']] ?? 'badge-normal';
                            $lab_status_badge = [
                                'pending'     => 'badge-pending',
                                'processing'  => 'badge-active',
                                'completed'   => 'badge-completed',
                                'cancelled'   => 'badge-cancelled',
                            ][$lt['status']] ?? 'badge-pending';
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lt['test_type']); ?></strong></td>
                            <td><span class="badge <?php echo $priority_badge; ?>"><?php echo ucfirst($lt['priority']); ?></span></td>
                            <td><span class="badge <?php echo $lab_status_badge; ?>"><?php echo ucfirst($lt['status']); ?></span></td>
                            <td>Dr. <?php echo htmlspecialchars($lt['doctor_name']); ?></td>
                            <td><?php echo !empty($lt['scheduled_date']) ? (new DateTime($lt['scheduled_date']))->format('M j, Y') : '&mdash;'; ?></td>
                            <td class="findings-cell"><?php echo !empty($lt['result']) ? nl2br(htmlspecialchars($lt['result'])) : '<span class="muted-sub">No result yet</span>'; ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="6" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg></div>No lab tests ordered for this client yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Vitals -->
        <div class="content-card">
            <h2>Vitals</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Visit Date</th>
                            <th>Weight</th>
                            <th>Height</th>
                            <th>Blood Pressure</th>
                            <th>Heart Rate</th>
                            <th>Fundal Height</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($vitals_list && $vitals_list->num_rows > 0):
                        while ($v = $vitals_list->fetch_assoc()):
                            $d = !empty($v['visit_date']) ? new DateTime($v['visit_date']) : null;
                    ?>
                        <tr>
                            <td><strong><?php echo $d ? $d->format('M j, Y') : '&mdash;'; ?></strong></td>
                            <td><?php echo $v['weight_kg'] ? $v['weight_kg'] . ' kg' : '&mdash;'; ?></td>
                            <td><?php echo $v['height_cm'] ? $v['height_cm'] . ' cm' : '&mdash;'; ?></td>
                            <td><span class="badge badge-active"><?php echo $v['blood_pressure'] ? htmlspecialchars($v['blood_pressure']) : '&mdash;'; ?></span></td>
                            <td><?php echo $v['heart_rate'] ? $v['heart_rate'] . ' bpm' : '&mdash;'; ?></td>
                            <td><?php echo $v['fundal_height'] ? $v['fundal_height'] . ' cm' : '&mdash;'; ?></td>
                            <td><?php echo $v['notes'] ? htmlspecialchars($v['notes']) : '&mdash;'; ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>No vitals recorded for this client yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <div class="content-card">
            <div class="empty-state-row">
                <div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></div>
                Client not found.
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Appointment Vitals Modal -->
<div class="modal-overlay" id="apptVitalsModal">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeModal('apptVitalsModal')">&times;</button>
        <h2>Vitals — <span id="apptVitalsCode">&nbsp;</span></h2>
        <p id="apptVitalsMeta" class="muted-sub" style="margin:-.3rem 0 .8rem;font-size:.85rem"></p>
        <div id="apptVitalsBody" style="min-height:80px"><p class="muted-sub">Loading…</p></div>
    </div>
</div>

<style>
    .findings-actions{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.55rem}
    .findings-actions .btn-sm{padding:.35rem .7rem;font-size:.76rem;min-height:32px}
    .result-preview{white-space:normal;word-break:break-word}
    .complete-check{display:flex;align-items:center;gap:.5rem;font-size:.86rem;color:var(--gray-700,#334155);margin:-.2rem 0 .9rem;cursor:pointer;user-select:none}
    .complete-check input{width:16px;height:16px;accent-color:#16a34a;cursor:pointer}
    .result-view-body{margin-top:.7rem;background:var(--green-pale,#f0fdf4);border:1px solid rgba(22,163,74,.25);border-radius:12px;padding:.85rem 1rem;font-size:.9rem;line-height:1.65;color:inherit;white-space:pre-wrap;word-break:break-word}
    .result-view-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem;align-items:center}
    .result-view-actions a.btn-sm{text-decoration:none}
</style>

<!-- Appointment Result Modal (view / print / send) -->
<div class="modal-overlay" id="resultViewModal">
    <div class="modal-box" style="width:min(720px,94vw)">
        <button class="modal-close-btn" onclick="closeModal('resultViewModal')">&times;</button>
        <h2>Appointment Result</h2>
        <p id="resultViewMeta" class="muted-sub" style="margin:-.3rem 0 .8rem;font-size:.85rem"></p>
        <div id="resultViewBody" style="min-height:60px"></div>
        <div class="result-view-actions">
            <a class="btn-sm btn-view" id="resultViewPdf" href="#" target="_blank" rel="noopener">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Print / Download PDF
            </a>
            <button type="button" class="btn-sm btn-edit" id="resultViewSend" onclick="sendResultToPatient()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                Send to Patient
            </button>
            <button type="button" class="btn-secondary" onclick="closeModal('resultViewModal')">Close</button>
        </div>
        <p id="resultViewNote" class="muted-sub" style="margin:.7rem 0 0;font-size:.82rem"></p>
    </div>
</div>

<?php if ($is_doctor): ?>

<!-- Findings Entry Modal (doctors only) -->
<div class="result-modal" id="resultModal">
    <div class="result-modal-box">
        <button class="result-close" onclick="closeResultModal()">&#10005;</button>
        <h3 id="modalTitle">Consultation Findings</h3>

        <div class="info-row" id="modalInfoRow"></div>

        <div class="form-group">
            <label>Appointment Result / Findings</label>
            <textarea id="modalResult" rows="8" placeholder="Enter the appointment result, diagnosis, findings, and notes here..."></textarea>
        </div>

        <label class="complete-check">
            <input type="checkbox" id="modalComplete">
            <span>Mark this appointment as completed</span>
        </label>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <button class="btn-primary" onclick="saveResult()" id="saveResultBtn"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Save Findings</button>
            <button class="btn-secondary" onclick="closeResultModal()">Cancel</button>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="loadingScreen" class="loading-screen"><div class="spinner"></div><p>Processing...</p></div>
<div id="toast" class="toast"></div>

<script>
    function openSidebar(){document.getElementById('adminSidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');document.body.style.overflow='hidden';}
    function closeSidebar(){document.getElementById('adminSidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');document.body.style.overflow='';}
    function toggleSidebar(){
      var sb=document.getElementById('adminSidebar');
      if(sb.classList.contains('open')){
        sb.classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
        document.body.style.overflow='';
      }else{
        sb.classList.add('open');
        if(window.matchMedia('(max-width: 900px)').matches){
          document.getElementById('sidebarOverlay').classList.add('active');
          document.body.style.overflow='hidden';
        }
      }
    }

    function openModal(id){document.getElementById(id).classList.add('active');document.body.style.overflow='hidden';}
    function closeModal(id){document.getElementById(id).classList.remove('active');document.body.style.overflow='';}
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e){ if(e.target === this) closeModal(this.id); });
    });
    document.addEventListener('keydown', function(e){
        if(e.key !== 'Escape') return;
        if(document.getElementById('resultModal')) closeResultModal();
        closeModal('resultViewModal');
        closeModal('apptVitalsModal');
    });

    function escapeHtml(s){
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function openApptVitals(appointmentId, code){
        document.getElementById('apptVitalsCode').textContent = code || ('#' + appointmentId);
        document.getElementById('apptVitalsMeta').textContent = '';
        document.getElementById('apptVitalsBody').innerHTML = '<p class="muted-sub">Loading…</p>';
        openModal('apptVitalsModal');
        fetch('client_vitals_api.php?appointment_id=' + encodeURIComponent(appointmentId))
            .then(function(r){ return r.json(); })
            .then(function(j){
                var body = document.getElementById('apptVitalsBody');
                if (!j || !j.ok) {
                    body.innerHTML = '<p style="color:#dc2626">' + escapeHtml((j && j.error) || 'Failed to load vitals.') + '</p>';
                    return;
                }
                var a = j.appointment;
                document.getElementById('apptVitalsMeta').textContent =
                    (a.appointment_date || '') + ' · ' + String(a.appointment_time || '').slice(0,5) + ' · Dr. ' + (a.doctor_name || '');
                if (!j.vitals || !j.vitals.length) {
                    body.innerHTML = '<p class="muted-sub">No vitals were recorded for this consultation.</p>';
                    return;
                }
                var rows = '';
                j.vitals.forEach(function(v){
                    rows +=
                        '<tr><th style="width:180px">Visit Date</th><td>' + escapeHtml(v.visit_date) + '</td></tr>' +
                        '<tr><th>Weight</th><td>' + (v.weight_kg ? escapeHtml(v.weight_kg) + ' kg' : '&mdash;') + '</td></tr>' +
                        '<tr><th>Height</th><td>' + (v.height_cm ? escapeHtml(v.height_cm) + ' cm' : '&mdash;') + '</td></tr>' +
                        '<tr><th>Blood Pressure</th><td><span class="badge badge-active">' + (v.blood_pressure ? escapeHtml(v.blood_pressure) : '&mdash;') + '</span></td></tr>' +
                        '<tr><th>Heart Rate</th><td>' + (v.heart_rate ? escapeHtml(v.heart_rate) + ' bpm' : '&mdash;') + '</td></tr>' +
                        '<tr><th>Fundal Height</th><td>' + (v.fundal_height ? escapeHtml(v.fundal_height) + ' cm' : '&mdash;') + '</td></tr>' +
                        '<tr><th>Notes</th><td>' + (v.notes ? escapeHtml(v.notes) : '&mdash;') + '</td></tr>';
                });
                body.innerHTML = '<div class="table-scroll"><table><tbody>' + rows + '</tbody></table></div>';
            })
            .catch(function(){
                document.getElementById('apptVitalsBody').innerHTML = '<p style="color:#dc2626">Network error.</p>';
            });
    }

    /* -- Appointment result viewer: print + email (admin & doctor) -- */
    function showToast(msg,type){
        var t=document.getElementById('toast');
        if(!t)return;
        t.textContent=msg;
        t.className='toast show'+(type?' '+type:'');
        setTimeout(function(){t.className='toast';},3500);
    }
    function showLoading(){var l=document.getElementById('loadingScreen');if(l)l.classList.add('active');}
    function hideLoading(){var l=document.getElementById('loadingScreen');if(l)l.classList.remove('active');}

    var currentResultId = null;
    function openResultView(btn){
        var d = btn.dataset;
        var draft = d.status !== 'completed';
        currentResultId = parseInt(d.apt, 10) || null;

        document.getElementById('resultViewMeta').innerHTML =
            '<strong>' + escapeHtml(d.patient) + '</strong> &middot; ' + escapeHtml(d.code) +
            ' &middot; <span class="badge ' + (draft ? 'badge-pending' : 'badge-completed') + '">' +
            escapeHtml(draft ? 'Draft' : d.status.charAt(0).toUpperCase() + d.status.slice(1)) + '</span>';

        var rows =
            '<tr><th style="width:190px">Doctor</th><td>' + escapeHtml(d.doctor) + '</td></tr>' +
            '<tr><th>Service</th><td>' + escapeHtml(d.service) + '</td></tr>' +
            '<tr><th>Schedule</th><td>' + escapeHtml(d.when) + '</td></tr>' +
            (d.recorded ? '<tr><th>Result Recorded On</th><td>' + escapeHtml(d.recorded) + '</td></tr>' : '');

        document.getElementById('resultViewBody').innerHTML =
            '<div class="table-scroll"><table><tbody>' + rows + '</tbody></table></div>' +
            '<div class="result-view-body">' + escapeHtml(d.result) + '</div>';

        var pdf  = document.getElementById('resultViewPdf');
        var send = document.getElementById('resultViewSend');
        var note = document.getElementById('resultViewNote');
        pdf.href = 'appointment_result_pdf.php?id=' + encodeURIComponent(d.apt);
        if (draft) {
            pdf.style.display = 'none';
            send.style.display = 'none';
            note.textContent = 'This result is still a draft. Printing and sending to the patient become available once the appointment is marked completed.';
        } else {
            pdf.style.display = '';
            send.style.display = '';
            note.textContent = '';
        }
        openModal('resultViewModal');
    }
    function sendResultToPatient(){
        if(!currentResultId) return;
        if(!window.confirm('Email this result to the patient?')) return;
        showLoading();
        var fd = new FormData();
        fd.append('appointment_id', currentResultId);
        fetch('send_appointment_result.php', { method:'POST', body:fd })
        .then(function(r){return r.text();}).then(function(res){
            hideLoading();
            if(res.trim()==='success') showToast('Result sent to the patient!','success');
            else showToast(res,'error');
        }).catch(function(){ hideLoading(); showToast('Error','error'); });
    }

    <?php if ($is_doctor): ?>
    /* -- Appointment findings entry (doctors only) -- */
    var currentApptId = null;
    function openResultModal(id, patientName, result){
        currentApptId = id;
        document.getElementById('modalInfoRow').innerHTML =
            '<div class="info-item"><label>Patient</label><span>'+escapeHtml(patientName)+'</span></div>' +
            '<div class="info-item"><label>Appt ID</label><span>#'+escapeHtml(String(id))+'</span></div>';
        document.getElementById('modalResult').value = result || '';
        document.getElementById('modalComplete').checked = false;
        document.getElementById('resultModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeResultModal(){
        var m = document.getElementById('resultModal');
        if (m) m.classList.remove('active');
        document.body.style.overflow = '';
        currentApptId = null;
    }
    function saveResult(){
        if(!currentApptId) return;
        var result = document.getElementById('modalResult').value.trim();
        if(!result){ showToast('Please enter the appointment result','error'); return; }
        var complete = document.getElementById('modalComplete').checked;
        showLoading();
        var fd = new FormData();
        fd.append('appointment_id', currentApptId);
        fd.append('result', result);
        var action = complete ? 'complete_and_result' : 'save_result';
        fetch('doctor_result_action.php?action=' + action, { method:'POST', body:fd })
        .then(function(r){return r.text();}).then(function(res){
            hideLoading();
            if(res.trim()==='success'){
                showToast(complete ? 'Appointment completed!' : 'Findings saved!','success');
                closeResultModal();
                try{sessionStorage.setItem('lustreShowResult', String(currentApptId));}catch(e){}
                document.body.classList.add('__leaving');
                setTimeout(function(){location.reload();},215);
            }
            else showToast(res,'error');
        }).catch(function(){ hideLoading(); showToast('Error','error'); });
    }
    document.getElementById('resultModal').addEventListener('click', function(e){
        if(e.target === this) closeResultModal();
    });
    <?php endif; ?>

    /* -- LustreMDC Smooth Transitions -- */
    (function(){
      const s=document.createElement('style');
      s.textContent=`
        @keyframes __meIn  {from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
        @keyframes __meOut {from{opacity:1;transform:translateY(0)}to{opacity:0;transform:translateY(-6px)}}
        @keyframes __meFade {from{opacity:0}to{opacity:1}}
        @keyframes __meFadeOut {from{opacity:1}to{opacity:0}}
        body{animation:__meFade .4s ease both}
        body.__leaving{animation:__meFadeOut .22s ease forwards;pointer-events:none}
        button:not(:disabled):active{transform:scale(.965) !important}
        .content-card{animation:__meIn .42s cubic-bezier(.22,1,.36,1) both;animation-delay:.1s;opacity:0;animation-fill-mode:both}
        .stat-card{opacity:0;animation:__meIn .38s cubic-bezier(.22,1,.36,1) both;animation-fill-mode:both}
        tr{transition:background .12s}
        .stats-grid .stat-card:nth-child(1){animation-delay:.04s}
        .stats-grid .stat-card:nth-child(2){animation-delay:.09s}
        .stats-grid .stat-card:nth-child(3){animation-delay:.14s}
        .stats-grid .stat-card:nth-child(4){animation-delay:.19s}
      `;
      document.head.appendChild(s);
      document.addEventListener('click',function(e){
        const link=e.target.closest('a[href]');
        if(!link)return;
        const href=link.getAttribute('href');
        if(!href||href.startsWith('#')||href.startsWith('javascript')||href.startsWith('http')||link.getAttribute('target')==='_blank')return;
        e.preventDefault();
        document.body.classList.add('__leaving');
        setTimeout(()=>{window.location.href=href;},215);
      });
    })();

    /* -- Re-open the result modal right after a result/findings was saved -- */
    (function(){
        var id = null;
        try{
            id = sessionStorage.getItem('lustreShowResult');
            if(id) sessionStorage.removeItem('lustreShowResult');
        }catch(e){}
        id = parseInt(id, 10);
        if(!id) return;
        var btn = document.querySelector('.js-result-view[data-apt="' + id + '"]');
        if(btn) openResultView(btn);
    })();
</script>
<script src="admin_components.js?v=<?= filemtime(__DIR__ . '/../../admin_components.js') ?>"></script>
<script src="admin_theme.js?v=<?= filemtime(__DIR__ . '/../../admin_theme.js') ?>"></script>
</body>
</html>
