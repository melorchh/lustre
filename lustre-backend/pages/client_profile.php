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
    $last_visit = $conn->query("SELECT MAX(appointment_date) AS last_appt FROM appointments WHERE patient_id = $patient_id")->fetch_assoc();
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
            <a href="admin_clients.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg></span><span class="rail-label">Clients</span></a>
            <a href="admin_patients.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="rail-label">Patients</span></a>
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
            <a href="doctor_clients.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg></span><span class="rail-label">Clients</span></a>
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
                            $date_fmt = (new DateTime($ap['appointment_date']))->format('M j, Y');
                            $time_fmt = date('g:i A', strtotime($ap['appointment_time']));
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
                            <td><strong><?php echo htmlspecialchars($ap['unique_code'] ?? ('APT-' . str_pad((string)$ap['id'], 6, '0', STR_PAD_LEFT))); ?></strong><br><small style="color:#94a3b8">#<?php echo (int)$ap['id']; ?></small></td>
                            <td><?php echo $date_fmt; ?><br><small style="color:#94a3b8"><?php echo $time_fmt; ?></small></td>
                            <td>Dr. <?php echo htmlspecialchars($ap['doctor_name']); ?><br><small style="color:#64748b"><?php echo htmlspecialchars($ap['specialty']); ?></small></td>
                            <td><?php echo htmlspecialchars($ap['service_requested'] ?: 'General consultation'); ?></td>
                            <td><span class="badge <?php echo $status_badge; ?>"><?php echo ucfirst($s); ?></span></td>
                            <td><?php echo !empty($ap['result']) ? nl2br(htmlspecialchars($ap['result'])) : '<span style="color:#94a3b8;font-size:.82rem">No result yet</span>'; ?></td>
                            <td>
                                <?php if ($has_vitals): ?>
                                    <button type="button" class="btn-sm" onclick="openApptVitals(<?php echo $appt_id; ?>, '<?php echo htmlspecialchars($ap['unique_code'] ?? '', ENT_QUOTES); ?>')" style="border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:8px;padding:.28rem .6rem;font-size:.78rem;font-weight:600;cursor:pointer;white-space:nowrap">View Vitals</button>
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
                            <td><?php echo !empty($lt['result']) ? nl2br(htmlspecialchars($lt['result'])) : '<span style="color:#94a3b8;font-size:.82rem">No result yet</span>'; ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="6" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg></div>No lab tests ordered for this client yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
                                    <span style="color:#94a3b8;font-size:.82rem">&mdash;</span>
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
                            $d = new DateTime($v['visit_date']);
                    ?>
                        <tr>
                            <td><strong><?php echo $d->format('M j, Y'); ?></strong></td>
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
        <p id="apptVitalsMeta" style="margin:-.3rem 0 .8rem;color:#64748b;font-size:.85rem"></p>
        <div id="apptVitalsBody" style="min-height:80px"><p style="color:#94a3b8">Loading…</p></div>
    </div>
</div>

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
        if(e.key === 'Escape') closeModal('apptVitalsModal');
    });

    function escapeHtml(s){
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function openApptVitals(appointmentId, code){
        document.getElementById('apptVitalsCode').textContent = code || ('#' + appointmentId);
        document.getElementById('apptVitalsMeta').textContent = '';
        document.getElementById('apptVitalsBody').innerHTML = '<p style="color:#94a3b8">Loading…</p>';
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
                    body.innerHTML = '<p style="color:#94a3b8">No vitals were recorded for this consultation.</p>';
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
</script>
<script src="admin_components.js?v=<?= filemtime(__DIR__ . '/../../admin_components.js') ?>"></script>
<script src="admin_theme.js?v=<?= filemtime(__DIR__ . '/../../admin_theme.js') ?>"></script>
</body>
</html>
