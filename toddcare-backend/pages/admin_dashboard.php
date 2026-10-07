<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
require_once __DIR__ . '/../src/db.php';
$admin_name = $_SESSION["admin_name"];
$total_patients    = $conn->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];
$total_doctors     = $conn->query("SELECT COUNT(*) c FROM doctors")->fetch_assoc()['c'];
$total_appointments= $conn->query("SELECT COUNT(*) c FROM appointments")->fetch_assoc()['c'];
$pending_count     = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='pending'")->fetch_assoc()['c'];
$confirmed_count   = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='confirmed'")->fetch_assoc()['c'];
$today_count       = $conn->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date=CURRENT_DATE")->fetch_assoc()['c'];
$recent = $conn->query("
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.payment_status,
           a.reschedule_count, a.last_rescheduled_at, a.appointment_duration,
           p.name as patient_name, d.name as doctor_name, d.specialty
    FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN doctors d ON a.doctor_id=d.id
    ORDER BY a.created_at DESC LIMIT 10
");
$history_map = [];
$hq = $conn->query("SELECT appointment_id, old_date, old_time, new_date, new_time, rescheduled_at FROM reschedule_history ORDER BY rescheduled_at DESC, id DESC");
if ($hq) {
    while ($h = $hq->fetch_assoc()) {
        $history_map[$h['appointment_id']][] = $h;
    }
}
$doc_list = $conn->query("SELECT id, name, specialty FROM doctors ORDER BY name");
$pat_list = $conn->query("SELECT id, name, age, contact FROM patients ORDER BY name");

// Doctor schedules for New Appointment calendar linking (active only)
$sched_map = [];
$sq = $conn->query("SELECT doctor_id, day_of_week, to_char(start_time,'HH24:MI') s, to_char(end_time,'HH24:MI') e FROM doctor_schedules WHERE is_active=1");
if ($sq) {
    while ($row = $sq->fetch_assoc()) {
        $sched_map[$row['doctor_id']][$row['day_of_week']] = [$row['s'], $row['e']];
    }
}

// Doctor time blocks (unavailable / time off)
$tb_map = [];
$tbq = $conn->query("SELECT doctor_id, block_type, block_date, day_of_week, to_char(start_time,'HH24:MI') s, to_char(end_time,'HH24:MI') e FROM doctor_time_blocks");
if ($tbq) {
    while ($row = $tbq->fetch_assoc()) {
        $did = $row['doctor_id'];
        if (!isset($tb_map[$did])) $tb_map[$did] = ['date' => [], 'weekly' => []];
        if ($row['block_type'] === 'date') {
            $tb_map[$did]['date'][] = ['date' => $row['block_date'], 's' => $row['s'], 'e' => $row['e']];
        } else {
            $tb_map[$did]['weekly'][] = ['day' => $row['day_of_week'], 's' => $row['s'], 'e' => $row['e']];
        }
    }
}

// Today's walk-ins (registered walk-in clients)
$walkin_doc_options = '';
$walkin_docs_json = [];
$walkin_specs = [];
$conn->query("ALTER TABLE doctors ADD COLUMN IF NOT EXISTS test_procedures TEXT NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE walk_ins ADD COLUMN IF NOT EXISTS test_procedure TEXT NOT NULL DEFAULT ''");
$wl_doc = $conn->query("SELECT id, name, specialty, test_procedures FROM doctors ORDER BY name");
if ($wl_doc) {
    while ($wd = $wl_doc->fetch_assoc()) {
        $walkin_doc_options .= '<option value="' . (int)$wd['id'] . '">Dr. ' . htmlspecialchars($wd['name']) . ' (' . htmlspecialchars($wd['specialty']) . ')</option>';
        $walkin_docs_json[] = ['id' => (int)$wd['id'], 'name' => $wd['name'], 'specialty' => $wd['specialty'], 'tests' => (string)($wd['test_procedures'] ?? '')];
        $walkin_specs[$wd['specialty']] = true;
    }
}

$today_walkins = $conn->query("
    SELECT w.id, w.arrival_date, w.arrival_time, w.status AS walk_status, w.test_procedure,
           p.name AS patient_name, p.patient_type, d.name AS doctor_name
    FROM walk_ins w
    JOIN patients p ON w.patient_id = p.id
    JOIN doctors d ON w.doctor_id = d.id
    WHERE w.arrival_date = CURRENT_DATE
    ORDER BY w.arrival_time ASC, w.created_at ASC
");
$today_walkin_count = $conn->query("SELECT COUNT(*) c FROM walk_ins WHERE arrival_date = CURRENT_DATE")->fetch_assoc()['c'];
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
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600;700&family=Source+Sans+3:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <script>try{if(localStorage.getItem('meTheme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}</script>
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/../../admin.css') ?>">
    <title>Dashboard - LustreMDC Admin</title>
</head>
<body>
   <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    <div class="admin-layout">
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-brand">
                <span class="brand-icon"><img src="images/Lustre.png" alt="LustreMDC Logo" width="42" height="42" /></span>
                <span class="brand-text"><span>Admin</span></span>
            </div>
            <div class="sidebar-user">
                <div class="avatar avatar--sm"><?php echo strtoupper(substr(trim($admin_name),0,1)); ?></div>
                <div class="sidebar-user-body"><div class="sidebar-user-label">Logged in as</div><div class="sidebar-user-name"><?php echo htmlspecialchars($admin_name); ?></div></div>
            </div>
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><span class="rail-label">Dashboard</span></a>
                <a href="admin_appointments.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span><span class="rail-label">Appointments</span></a>
<a href="admin_patients.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="rail-label">Patients</span></a>
                <a href="admin_walkins.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.6 4.7L18 9.3l-4.4 1.6L12 15.6l-1.6-4.7L6 9.3l4.4-1.6z" style="stroke-width:1.8"/><path d="M5 20h14"/></svg></span><span class="rail-label">Walk-ins</span></a>
                <a href="admin_doctors.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span><span class="rail-label">Doctors</span></a>
                <a href="admin_schedules.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span class="rail-label">Schedules</span></a>
                <a href="admin_laboratory.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg></span><span class="rail-label">Laboratory</span></a>
                <a href="admin_vitals.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span><span class="rail-label">Vitals &amp; Vaccines</span></a>
                <a href="admin_reports.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15v-4"/><path d="M12 15V7"/><path d="M17 15v-6"/></svg></span><span class="rail-label">Reports</span></a>
                <a href="admin_history.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg></span><span class="rail-label">Archives</span></a>
            </nav>
            <div class="sidebar-footer">
                <a href="admin_logout.php" class="btn-logout" data-tip="Sign Out"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span><span class="rail-label">Sign Out</span></a>
            </div>
        </aside>
        <main class="admin-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Open menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                    <span class="topbar-title">Dashboard</span>
                </div>
                <div class="topbar-actions">
    <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                    <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong><?php echo htmlspecialchars(explode(' ', trim($admin_name))[0]); ?></strong></div>
                    <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($admin_name),0,1)); ?></div>
                </div>
            </header>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-number"><?php echo $total_patients; ?></div><div class="stat-label">Patients</div></div>
                <div class="stat-card"><div class="stat-number"><?php echo $total_doctors; ?></div><div class="stat-label">Doctors</div></div>
                <div class="stat-card" style="border-top-color:#f59e0b"><div class="stat-number" style="color:#f59e0b"><?php echo $pending_count; ?></div><div class="stat-label">Pending</div></div>
                <div class="stat-card" style="border-top-color:#10b981"><div class="stat-number" style="color:#10b981"><?php echo $confirmed_count; ?></div><div class="stat-label">Confirmed</div></div>
<div class="stat-card" style="border-top-color:#8b5cf6"><div class="stat-number" style="color:#8b5cf6"><?php echo $today_count; ?></div><div class="stat-label">Today</div></div>
                <div class="stat-card"><div class="stat-number"><?php echo $total_appointments; ?></div><div class="stat-label">Total</div></div>
                <div class="stat-card" style="border-top-color:#f59e0b"><div class="stat-number" style="color:#f59e0b"><?php echo $today_walkin_count; ?></div><div class="stat-label">Walk-ins Today</div></div>
            </div>

            <div class="content-card">
                <h2>Quick Actions</h2>
                <div class="qa-primary">
                    <button class="qa-btn qa-btn--apt" onclick="openNewApptModal()">
                        <span class="qa-btn-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                        <span class="qa-btn-body"><strong>New Appointment</strong><small>Book a slot at the counter</small></span>
                        <span class="qa-btn-arrow"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg></span>
                    </button>
                    <button class="qa-btn qa-btn--walkin" onclick="openWalkinModal()">
                        <span class="qa-btn-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span>
                        <span class="qa-btn-body"><strong>Register Walk-In</strong><small>Add a counter patient</small></span>
                        <span class="qa-btn-arrow"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg></span>
                    </button>
                </div>
                <div class="quick-actions">
                    <a href="admin_patients.php" class="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> View Patients</a>
                    <a href="admin_vitals.php" class="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> Record Vitals</a>
                    <a href="admin_laboratory.php" class="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg> New Lab Test</a>
                    <a href="admin_doctors.php" class="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg> Manage Doctors</a>
<a href="admin_schedules.php" class="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Schedules</a>
                    <a href="admin_walkins.php" class="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.6 4.7L18 9.3l-4.4 1.6L12 15.6l-1.6-4.7L6 9.3l4.4-1.6z" style="stroke-width:1.8"/><path d="M5 20h14"/></svg> View Walk-ins</a>
                    <a href="admin_reports.php" class="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15v-4"/><path d="M12 15V7"/><path d="M17 15v-6"/></svg> View Reports</a>
                </div>
            </div>

            <div class="content-card">
                <h2>Recent Appointments</h2>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>#</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php while ($apt = $recent->fetch_assoc()):
                            $date = new DateTime($apt['appointment_date']);
                            $time = new DateTime($apt['appointment_time']);
                            $s = $apt['status'];
                            $colors = ['pending'=>'background:#fef3c7;color:#92400e','confirmed'=>'background:#d1fae5;color:#065f46','cancelled'=>'background:#fee2e2;color:#991b1b','completed'=>'background:#e2e8f0;color:#334155'];
                            $bc = $colors[$s] ?? '';
                        ?>
                        <tr>
                            <td style="color:#94a3b8;font-weight:700">#<?php echo $apt['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong></td>
                            <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?><br><small style="color:#64748b"><?php echo htmlspecialchars($apt['specialty']); ?></small></td>
                            <td><?php echo $date->format('M j, Y'); ?></td>
                            <td><?php echo $time->format('g:i A'); ?></td>
                            <td>
                                <span class="badge" style="<?php echo $bc; ?>"><?php echo ucfirst($s); ?></span>
                                <?php $dhist = !empty($history_map[$apt['id']]) ? $history_map[$apt['id']] : []; ?>
                                <?php if (!empty($apt['reschedule_count']) || !empty($dhist)): ?>
                                <span class="resched-tip" style="margin-top:4px">
                                    <span class="resched-badge">&#10227; Rescheduled<?php echo $apt['reschedule_count'] > 1 ? ' x' . (int)$apt['reschedule_count'] : ''; ?></span>
                                    <span class="resched-pop">
                                        <?php if (count($dhist) > 0): ?>
                                            <span class="resched-pop-title">Reschedule History</span>
                                            <?php foreach ($dhist as $e):
                                                $od = new DateTime($e['old_date']); $ot = new DateTime($e['old_time']);
                                                $nd = new DateTime($e['new_date']); $nt = new DateTime($e['new_time']);
                                                $rt = new DateTime($e['rescheduled_at']);
                                            ?>
                                            <span class="resched-pop-item">
                                                <span class="resched-change"><span class="resched-old"><?php echo $od->format('M j, Y') . ' &middot; ' . $ot->format('g:i A'); ?><span class="resched-dur"><?php
                                                    $dmin = (int)$apt['appointment_duration'];
                                                    if ($dmin >= 60) { echo floor($dmin/60) . 'h' . (($dmin%60)? ' ' . ($dmin%60) . 'm':''); } else { echo $dmin . 'm'; }
                                                ?></span></span> &rarr; <span class="resched-new"><?php echo $nd->format('M j, Y') . ' ' . $nt->format('g:i A'); ?></span></span>
                                                <span class="resched-pop-time">rescheduled on <?php echo $rt->format('M j, g:i A'); ?></span>
                                            </span>
                                            <?php endforeach; ?>
                                        <?php elseif (!empty($apt['last_rescheduled_at'])): $rd = new DateTime($apt['last_rescheduled_at']); ?>
                                            <span class="resched-pop-title">Reschedule History</span>
                                            <span class="resched-pop-item">
                                                <span class="resched-change"><span class="resched-old">Rescheduled</span> &rarr; <span class="resched-new"><?php echo $date->format('M j, Y') . ' ' . $time->format('g:i A'); ?><span class="resched-dur"><?php
                                                    $dm = (int)$apt['appointment_duration'];
                                                    if ($dm >= 60) { echo floor($dm/60) . 'h' . (($dm%60)? ' ' . ($dm%60) . 'm':''); } else { echo $dm . 'm'; }
                                                ?></span></span></span>
                                                <span class="resched-pop-time">last rescheduled on <?php echo $rd->format('M j, g:i A'); ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="resched-pop-title">Rescheduled</span>
                                            <span class="resched-pop-time">This appointment was rescheduled</span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $apt['payment_status']==='paid'?'badge-active':'badge-inactive'; ?>"><?php echo ucfirst($apt['payment_status']); ?></span></td>
                            <td>
                                <div class="apt-actions" style="display:flex;flex-direction:column;gap:6px;min-width:160px">
                                    <select class="action-select" onchange="updateStatus(<?php echo $apt['id']; ?>, this.value)">
                                        <?php foreach(['pending','confirmed','completed','cancelled'] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo $s===$opt?'selected':''; ?>><?php echo ucfirst($opt); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select class="action-select" onchange="updatePayment(<?php echo $apt['id']; ?>, this.value)">
                                        <option value="pending" <?php echo $apt['payment_status']==='pending'?'selected':''; ?>>Unpaid</option>
                                        <option value="paid" <?php echo $apt['payment_status']==='paid'?'selected':''; ?>>Paid</option>
                                    </select>
                                    <button type="button" class="btn-slip" title="Print appointment slip" onclick="openSlip(<?php echo htmlspecialchars(json_encode([
                                        'patient' => $apt['patient_name'],
                                        'doctor'  => $apt['doctor_name'],
                                        'specialty' => $apt['specialty'],
                                        'date'    => date('F j, Y', strtotime($apt['appointment_date'])),
                                        'time'    => $time->format('g:i A'),
                                        'status'  => ucfirst($apt['status']),
                                        'payment' => ucfirst($apt['payment_status']),
                                    ])); ?>)">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg> Print Slip
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
</table>
                </div>
            </div>

            <div class="content-card">
                <div class="patients-header">
                    <h2 style="margin-bottom:0;">Today's Walk-ins</h2>
                    <a href="admin_walkins.php" class="btn-walkin" style="text-decoration:none">View All Walk-ins</a>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Queue</th><th>Patient</th><th>Type</th><th>Arrival</th><th>Test / Procedure</th><th>Doctor</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php
                        $wi_counts = [];
                        $show_walkin = $today_walkins;
                        if ($show_walkin && $show_walkin->num_rows > 0):
                            while ($tw = $show_walkin->fetch_assoc()):
                                if (!isset($wi_counts[$tw['doctor_name']])) $wi_counts[$tw['doctor_name']] = 0;
                                $wi_counts[$tw['doctor_name']]++;
                                $wnum = $wi_counts[$tw['doctor_name']];
                                $ws = $tw['walk_status'];
                                $wcolors = ['waiting'=>'background:#fef3c7;color:#92400e','in_service'=>'background:#dbeafe;color:#1e40af','served'=>'background:#d1fae5;color:#065f46','cancelled'=>'background:#fee2e2;color:#991b1b'];
                                $wbc = $wcolors[$ws] ?? '';
                                $wt = new DateTime($tw['arrival_time']);
                        ?>
                        <tr>
                            <td><span class="badge" style="background:#16a34a;color:#fff;font-size:1rem;font-weight:800">#<?php echo $wnum; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($tw['patient_name']); ?></strong></td>
                            <td><span class="badge badge-urgent"><?php echo htmlspecialchars(ucfirst($tw['patient_type'])); ?></span></td>
                            <td><?php echo $wt->format('g:i A'); ?></td>
                            <td><?php echo htmlspecialchars($tw['test_procedure'] ?: '—'); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($tw['doctor_name']); ?></td>
                            <td><span class="badge" style="<?php echo $wbc; ?>"><?php echo ucfirst($ws); ?></span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7" style="text-align:center;color:#94a3b8;">No walk-ins registered today yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

<!-- New Appointment Modal -->
<div class="modal-overlay" id="newApptModal">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeModal('newApptModal')">&times;</button>
        <h2>New Appointment</h2>
        <form id="newApptForm">
            <div class="form-group">
                <label>Patient *</label>
                <select name="patient_id" class="form-dropdown" required>
                    <option value="">Select patient...</option>
                    <?php if ($pat_list) while ($pat = $pat_list->fetch_assoc()): ?>
                    <option value="<?php echo (int)$pat['id']; ?>"><?php echo htmlspecialchars($pat['name']); ?><?php echo $pat['age']!==null ? ' (' . (int)$pat['age'] . ' yrs)' : ''; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Doctor *</label>
                <select name="doctor_id" class="form-dropdown" required>
                    <option value="">Select doctor...</option>
                    <?php if ($doc_list) while ($d = $doc_list->fetch_assoc()): ?>
                    <option value="<?php echo (int)$d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['specialty']); ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date" data-datepicker min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Time *</label>
                    <select name="time" class="form-dropdown" required>
                        <option value="">Select time...</option>
                    </select>
                </div>
            </div>
            <p class="form-hint">Pick a doctor to see available dates &amp; times. Duration is 2 hours / visit.</p>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('newApptModal')">Cancel</button>
                <button type="submit" class="btn-primary">Book Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- Walk-In Client Modal -->
<div class="modal-overlay" id="walkinModal">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeModal('walkinModal')">&times;</button>
        <h2>Register Walk-In Client</h2>
<form id="walkinForm">
            <div class="form-grid">
                <div class="form-group">
                    <label>Specialty</label>
                    <select name="specialty" class="form-dropdown" data-searchable>
                        <option value="">All specialties</option>
                        <?php foreach (array_keys($walkin_specs) as $sp): ?>
                        <option value="<?php echo htmlspecialchars($sp); ?>"><?php echo htmlspecialchars($sp); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Doctor *</label>
                    <select name="doctor_id" class="form-dropdown" required>
                        <option value="">Select Doctor</option>
                        <?php echo $walkin_doc_options; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Test / Procedure</label>
                    <select name="test_procedure" class="form-dropdown" data-searchable disabled>
                        <option value="">Select a doctor first</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Arrival Time</label>
                    <select name="arrival_time" class="form-dropdown" data-searchable>
                        <?php
                        $at_val   = date('H:i');
                        $at_label = date('g:i A');
                        $at_min   = ((int)date('G')) * 60 + (int)date('i');
                        $at_grid  = 5;
                        $at_ongrid = ($at_min % $at_grid) === 0;
                        $at_inserted = false;
                        for ($m = 0; $m < 24 * 60; $m += $at_grid) {
                            if (!$at_ongrid && !$at_inserted && $at_min < $m) {
                                echo '<option value="' . $at_val . '" selected>' . $at_label . '</option>';
                                $at_inserted = true;
                            }
                            $v = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                            echo '<option value="' . $v . '"' . ($v === $at_val ? ' selected' : '') . '>' . date('g:i A', strtotime($v)) . '</option>';
                        }
                        if (!$at_ongrid && !$at_inserted) {
                            echo '<option value="' . $at_val . '" selected>' . $at_label . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
<div class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" placeholder="e.g. Juan Dela Cruz" required>
                </div>
                <div class="form-group">
                    <label>Contact Number *</label>
                    <input type="text" name="contact" placeholder="e.g. 09171234567" required>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Gender *</label>
                    <select name="gender" class="form-dropdown" required>
                        <option value="" disabled selected>Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" placeholder="e.g. 123 Sampaguita St, Manila">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Patient Type</label>
                    <select name="patient_type" class="form-dropdown">
                        <option value="">Select Type</option>
                        <option value="newborn">Newborn</option>
                        <option value="infant">Infant</option>
                        <option value="adolescent">Adolescent</option>
                        <option value="adult">Adult</option>
                        <option value="elderly">Elderly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Age</label>
                    <input type="number" name="age" min="0" max="150" placeholder="e.g. 32">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight_kg" step="0.1" min="0" placeholder="e.g. 58.5">
                </div>
                <div class="form-group">
                    <label>Height (cm)</label>
                    <input type="number" name="height_cm" step="0.1" min="0" placeholder="e.g. 160.0">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('walkinModal')">Cancel</button>
                <button type="submit" class="btn-primary">Register Client</button>
            </div>
        </form>
    </div>
</div>

<!-- Print Appointment Slip Modal -->
<div class="modal-overlay" id="slipModal">
    <div class="modal-box slip-modal">
        <button class="modal-close-btn" onclick="closeModal('slipModal')">&times;</button>
        <h2>Appointment Slip</h2>
        <div id="slipBody" class="slip-body"><!-- filled by JS --></div>
        <div class="form-actions">
            <button type="button" class="btn-secondary" onclick="closeModal('slipModal')">Close</button>
            <button type="button" class="btn-primary" onclick="printSlip()">Print Slip</button>
        </div>
    </div>
</div>

    <div id="loadingScreen" class="loading-screen"><div class="spinner"></div><p>Updating...</p></div>
    <div id="toast" class="toast"></div>

    <script>
        function openSidebar(){
			document.getElementById('adminSidebar').classList.add('open');
			document.getElementById('sidebarOverlay').classList.add('active');
			document.body.style.overflow='hidden';
			}
        function closeSidebar(){
			document.getElementById('adminSidebar').classList.remove('open');
			document.getElementById('sidebarOverlay').classList.remove('active');
			document.body.style.overflow='';
			}
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
        function showToast(msg,type=''){try{if(!window.__noFlash){clearTimeout(window.__flashT);localStorage.setItem('lustreAdminFlash',JSON.stringify({m:String(msg),t:type||''}));window.__flashT=setTimeout(function(){try{localStorage.removeItem('lustreAdminFlash')}catch(e){}},2500);}}catch(e){}
			const t=document.getElementById('toast');t.textContent=msg;
			t.className='toast show'+(type?' '+type:'');
			setTimeout(()=>t.className='toast',3000);
			}
			
			
        function showLoading(){
			document.getElementById('loadingScreen').classList.add('active');
			}
        function hideLoading(){document.getElementById('loadingScreen').classList.remove('active');}
        function updateStatus(id,status){
            showLoading();const fd=new FormData();
			fd.append('appointment_id',id);
			fd.append('status',status);
            fetch('admin_update_appointment.php',{method:'POST',body:fd
			})
			.then(r=>r.text()).then(res=>{hideLoading();
			if(res.trim()==='success')showToast('Status updated!','success');
			else showToast(res,'error');})
			.catch(()=>{hideLoading();
			showToast('Error','error');
			});
        }
        function updatePayment(id,payment){
            showLoading();const fd=new FormData();
			fd.append('appointment_id',id);
			fd.append('payment_status',payment);
            fetch('admin_update_appointment.php',{method:'POST',body:fd}).then(r=>r.text()).then(res=>{hideLoading();
			if(res.trim()==='success')showToast('Payment updated!','success');
			else showToast(res,'error');}).catch(()=>{hideLoading();
			showToast('Error','error');});
        }

        // ---- Modals ----
        function openModal(id){document.getElementById(id).classList.add('active');document.body.style.overflow='hidden';}
        function closeModal(id){document.getElementById(id).classList.remove('active');document.body.style.overflow='';}
        function openNewApptModal(){document.getElementById('newApptForm').reset();if(typeof __resetTime==='function')__resetTime();if(typeof __setDateValue==='function'){__setDateValue('');}openModal('newApptModal');}
        function openWalkinModal(){document.getElementById('walkinForm').reset();if(typeof __rebuildWalkinDocs==='function')__rebuildWalkinDocs();openModal('walkinModal');}

        document.querySelectorAll('.modal-overlay').forEach(function(overlay){
            overlay.addEventListener('click',function(e){if(e.target===this)closeModal(this.id);});
        });

        // ---- Walk-in specialty ⇄ doctor link + test/procedure dropdown ----
        var __walkinDocs = <?php echo json_encode($walkin_docs_json); ?>;
        function __walkinEsc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
        function __walkinDoc(doctorId){doctorId=Number(doctorId)||0;for(var i=0;i<__walkinDocs.length;i++){if(__walkinDocs[i].id===doctorId)return __walkinDocs[i];}return null;}
        function __rebuildWalkinProcs(){
            var f=document.getElementById('walkinForm'); if(!f) return;
            var docSel=f.querySelector('select[name="doctor_id"]');
            var procSel=f.querySelector('select[name="test_procedure"]');
            if(!procSel) return;
            var did=docSel?Number(docSel.value)||0:0;
            var d=__walkinDoc(did);
            var html;
            if(!did){ html='<option value="">Select a doctor first</option>'; procSel.disabled=true; }
            else{
                html='<option value="">Select Test / Procedure</option>';
                var seen={};
                (String(d?d.tests:'').split('\n')||[]).forEach(function(ln){
                    ln=ln.trim();
                    if(ln && !seen[ln]){ seen[ln]=1; html+='<option value="'+__walkinEsc(ln)+'">'+__walkinEsc(ln)+'</option>'; }
                });
                procSel.disabled=false;
            }
            procSel.innerHTML=html;
            procSel.value='';
            if(window.__fdResync) window.__fdResync(procSel);
        }
        function __rebuildWalkinDocs(){
            var f=document.getElementById('walkinForm'); if(!f) return;
            var specSel=f.querySelector('select[name="specialty"]');
            var docSel=f.querySelector('select[name="doctor_id"]');
            if(!specSel||!docSel) return;
            var sp=specSel.value;
            var keep=Number(docSel.value)||0;
            var html='<option value="">Select Doctor</option>';
            for(var i=0;i<__walkinDocs.length;i++){
                var d=__walkinDocs[i];
                if(sp && d.specialty!==sp) continue;
                html+='<option value="'+d.id+'"'+(keep===d.id?' selected':'')+'>Dr. '+__walkinEsc(d.name)+' ('+__walkinEsc(d.specialty)+')</option>';
            }
            docSel.innerHTML=html;
            docSel.value=(keep && docSel.querySelector('option[value="'+keep+'"]'))?String(keep):'';
            if(window.__fdResync) window.__fdResync(docSel);
            __rebuildWalkinProcs();
        }
        (function(){
            var f=document.getElementById('walkinForm'); if(!f||f.dataset.specBound) return;
            f.dataset.specBound='1';
            var specSel=f.querySelector('select[name="specialty"]');
            var docSel=f.querySelector('select[name="doctor_id"]');
            if(specSel) specSel.addEventListener('change', __rebuildWalkinDocs);
            if(docSel) docSel.addEventListener('change', function(){
                var d=__walkinDoc(docSel.value);
                if(specSel){ specSel.value = d?d.specialty:''; }
                __rebuildWalkinProcs();
            });
            f.addEventListener('reset', function(){ setTimeout(__rebuildWalkinDocs, 0); });
        })();

        // Walk-in submit
        function bindWalkin(){var f=document.getElementById('walkinForm');if(!f||f.dataset.bound)return;f.dataset.bound='1';
            f.addEventListener('submit',function(e){
                e.preventDefault();showLoading();var fd=new FormData(this);fd.append('action','add_walkin');
                fetch('admin_walkin_action.php',{method:'POST',body:fd})
                    .then(function(r){return r.text();})
                    .then(function(res){hideLoading();var t=res.trim();
                        if(t.indexOf('success')===0){showToast('Walk-in client registered!','success');closeModal('walkinModal');reloadPage();}
                        else showToast(t,'error');})
                    .catch(function(){hideLoading();showToast('Network error','error');});
            });
        }
        bindWalkin();

        // New appointment submit
        function bindNewAppt(){var f=document.getElementById('newApptForm');if(!f||f.dataset.bound)return;f.dataset.bound='1';
            f.addEventListener('submit',function(e){
                e.preventDefault();showLoading();var fd=new FormData(this);
                fetch('admin_book_appointment.php',{method:'POST',body:fd})
                    .then(function(r){return r.text();})
                    .then(function(res){hideLoading();var t=res.trim();
                        if(t.indexOf('success')===0){showToast('Appointment booked!','success');closeModal('newApptModal');reloadPage();}
                        else showToast(t,'error');})
                    .catch(function(){hideLoading();showToast('Network error','error');});
            });
        }
        bindNewAppt();

        // ---- Link New Appointment calendar + time to doctor availability ----
        var __docSched = <?php echo json_encode($sched_map); ?>;
        var __docTimeBlocks = <?php echo json_encode($tb_map); ?>;
        var __dateInput = document.querySelector('#newApptForm input[name="date"]');
        var __timeSel   = document.querySelector('#newApptForm select[name="time"]');
        var __docInput  = document.querySelector('#newApptForm select[name="doctor_id"]');
        var __days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        var __fmtDate = function(d){return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2);};
        var __toMin = function(hms){var p=hms.split(':');return parseInt(p[0],10)*60+parseInt(p[1],10);};
        var __toHMS = function(m){var h=Math.floor(m/60),mm=m%60;return __pad2(h)+':'+__pad2(mm);};
        var __pad2 = function(n){return String(n).padStart(2,'0');};
        var __fmt12 = function(hms){var p=hms.split(':'),h=parseInt(p[0],10),am= h<12?'AM':'PM',hh=h%12||12;return hh+':'+p[1]+' '+am;};
        var DUR      = 120;
        var TIME_STEP = 30;
        function __isSlotBlocked(doctorId, dateStr, slotStartMin, slotEndMin){
            var tb=__docTimeBlocks[doctorId];
            if(!tb)return false;
            var d=new Date(dateStr+'T00:00:00');
            var dayName=__days[d.getDay()];
            // Check specific date blocks
            var dateBlocks=tb['date']||[];
            for(var i=0;i<dateBlocks.length;i++){
                if(dateBlocks[i].date===dateStr){
                    var bs=__toMin(dateBlocks[i].s), be=__toMin(dateBlocks[i].e);
                    if(slotStartMin<be && slotEndMin>bs)return true;
                }
            }
            // Check weekly blocks
            var weekBlocks=tb['weekly']||[];
            for(var i=0;i<weekBlocks.length;i++){
                if(weekBlocks[i].day===dayName){
                    var bs=__toMin(weekBlocks[i].s), be=__toMin(weekBlocks[i].e);
                    if(slotStartMin<be && slotEndMin>bs)return true;
                }
            }
            return false;
        }
        function __isDateFullyBlocked(doctorId, dateStr){
            var tb=__docTimeBlocks[doctorId];
            if(!tb)return false;
            var d=new Date(dateStr+'T00:00:00');
            var dayName=__days[d.getDay()];
            var sched=__docSched[doctorId];
            if(!sched||!sched[dayName])return false;
            var schedStart=__toMin(sched[dayName][0]), schedEnd=__toMin(sched[dayName][1]);
            // Check specific date blocks
            var dateBlocks=tb['date']||[];
            for(var i=0;i<dateBlocks.length;i++){
                if(dateBlocks[i].date===dateStr){
                    var bs=__toMin(dateBlocks[i].s), be=__toMin(dateBlocks[i].e);
                    if(bs<=schedStart && be>=schedEnd)return true;
                }
            }
            // Check weekly blocks
            var weekBlocks=tb['weekly']||[];
            for(var i=0;i<weekBlocks.length;i++){
                if(weekBlocks[i].day===dayName){
                    var bs=__toMin(weekBlocks[i].s), be=__toMin(weekBlocks[i].e);
                    if(bs<=schedStart && be>=schedEnd)return true;
                }
            }
            return false;
        }
        function __docAvailDates(doctorId,count){
            var days=__docSched[doctorId]?Object.keys(__docSched[doctorId]):[];
            var out=[], d=new Date();
            d.setHours(0,0,0,0);
            while(out.length<count+90){
                var dayName=__days[d.getDay()];
                if(days.indexOf(dayName)!==-1 && !__isDateFullyBlocked(doctorId,__fmtDate(d)))out.push(new Date(d));
                if(out.length>=count)break;
                d.setDate(d.getDate()+1);
            }
            return out;
        }
        function __setDateValue(v){
            if(!__dateInput)return;
            if(typeof __dateInput.__fdSetValue==='function'){ __dateInput.__fdSetValue(v); }
            else { __dateInput.value=v; }
        }
        function __setDateFilter(did,avail){
            if(!__dateInput)return;
            var set={}; (avail||[]).forEach(function(a){set[__fmtDate(a)]=1;});
            __dateInput.__dpAllowed=function(str){return !!set[str];};
            if(typeof __dateInput.__fdRefresh==='function'){ __dateInput.__fdRefresh(); }
        }
        function __resetTime(){
            if(!__timeSel)return;
            __timeSel.innerHTML='<option value="">Select time...</option>';
            if(window.__fdResync){window.__fdResync(__timeSel);}
        }
        var __timeReq = 0;
        function __populateTimes(did,dateStr){
            var req = ++__timeReq;
            if(!__timeSel)return;
            if(!did||!dateStr){__resetTime();return;}
            if(!__docSched[did]){__resetTime();return;}
            __timeSel.innerHTML='<option value="">Loading times...</option>';
            if(window.__fdResync){window.__fdResync(__timeSel);}
            fetch('get_available_times.php?doctor_id='+encodeURIComponent(did)+'&date='+encodeURIComponent(dateStr))
            .then(function(r){return r.json();})
            .then(function(list){
                if(req!==__timeReq||!__timeSel)return;
                var html='<option value="">Select time...</option>';
                if(Array.isArray(list)&&list.length){
                    for(var i=0;i<list.length;i++){
                        var v=list[i];
                        html+='<option value="'+v+'">'+__fmt12(v)+' - '+__fmt12(__toHMS(__toMin(v)+DUR))+'</option>';
                    }
                } else {
                    html+='<option value="">No slots available</option>';
                }
                __timeSel.innerHTML=html;
                if(window.__fdResync){window.__fdResync(__timeSel);}
            })
            .catch(function(){
                if(req!==__timeReq||!__timeSel)return;
                __timeSel.innerHTML='<option value="">Select time...</option><option value="">Failed to load times</option>';
                if(window.__fdResync){window.__fdResync(__timeSel);}
            });
        }
        function __refreshApptTime(){
            var did=__docInput?__docInput.value:'';
            var sel=__dateInput?__dateInput.value:'';
            __populateTimes(did,sel);
        }
        function __refreshApptDate(){
            var did=__docInput?__docInput.value:'';
            if(!did||!__dateInput){__resetTime();return;}
            if(!__docSched[did]){
                if(__dateInput.min)__dateInput.min='';
                if(__dateInput.max)__dateInput.max='';
                __dateInput.__dpAllowed=null;
                if(typeof __dateInput.__fdRefresh==='function'){ __dateInput.__fdRefresh(); }
                __resetTime();
                return;
            }
            var avail=__docAvailDates(did,90);
            __dateInput.min=__fmtDate(avail[0]);
            __dateInput.max=__fmtDate(avail[avail.length-1]);
            __setDateFilter(did,avail);
            var sel=__dateInput.value;
            if(!sel){
                __setDateValue(__fmtDate(avail[0]));
                __refreshApptTime();
                return;
            }
            var ok=avail.some(function(a){return __fmtDate(a)===sel;});
            if(!ok){
                __setDateValue('');
                __resetTime();
                showToast('That date is not a working day for this doctor','error');
            } else {
                __refreshApptTime();
            }
        }
        if(__docInput){
            __docInput.addEventListener('change',function(){
                __setDateValue('');
                __refreshApptDate();
            });
        }
        if(__dateInput){
            __dateInput.addEventListener('change',function(){
                if(!__dateInput.value)return;
                var did=__docInput?__docInput.value:'';
                if(!did){__setDateValue('');showToast('Please select a doctor first','error');return;}
                if(!__docSched[did]){__refreshApptTime();return;}
                var avail=__docAvailDates(did,90);
                var ok=avail.some(function(a){return __fmtDate(a)===__dateInput.value;});
                if(!ok){
                    __setDateValue('');
                    __resetTime();
                    showToast('That date is not a working day for this doctor','error');
                } else {
                    __refreshApptTime();
                }
            });
        }
        if(__timeSel){
            __timeSel.addEventListener('change',function(){ if(!__timeSel.value)__resetTime(); });
        }

        // Print appointment slip
        function openSlip(d){
            var slip=document.getElementById('slipBody');
            slip.innerHTML=
                '<div class="slip-head"><div class="slip-logo">LustreMDC</div><div class="slip-clinic">Clinic Appointment Slip</div></div>'+
                '<div class="slip-row"><span>Patient:</span><strong>'+d.patient+'</strong></div>'+
                '<div class="slip-row"><span>Doctor:</span><strong>Dr. '+d.doctor+'</strong></div>'+
                '<div class="slip-row"><span>Specialty:</span><strong>'+d.specialty+'</strong></div>'+
                '<div class="slip-row"><span>Date:</span><strong>'+d.date+'</strong></div>'+
                '<div class="slip-row"><span>Time:</span><strong>'+d.time+'</strong></div>'+
                '<div class="slip-row"><span>Status:</span><strong>'+d.status+'</strong></div>'+
                '<div class="slip-row"><span>Payment:</span><strong>'+d.payment+'</strong></div>'+
                '<div class="slip-foot">Please arrive 10-15 minutes early. Show this slip at the counter.</div>';
            slip.setAttribute('data-print', '1');
            openModal('slipModal');
        }
        function printSlip(){
            var slip=document.getElementById('slipBody');
            var content=document.getElementById('slipModal').innerHTML;
            var w=window.open('','_blank','width=460,height=640');
            if(!w){showToast('Popup blocked â€” allow popups to print','error');return;}
            w.document.write('<!DOCTYPE html><html><head><title>Appointment Slip</title>'+
                '<style>'+
                'body{font-family:Arial,Helvetica,sans-serif;color:#111;padding:24px;margin:0}'+
                '.wrap{border:2px solid #16a34a;border-radius:14px;padding:22px;max-width:360px;margin:0 auto}'+
                '.head{text-align:center;border-bottom:2px solid #e2e8f0;padding-bottom:14px;margin-bottom:16px}'+
                '.head .name{font-size:20px;font-weight:800;color:#16a34a;letter-spacing:.5px}'+
                '.head .sub{font-size:12px;color:#475569;margin-top:2px}'+
                '.row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:14px}'+
                '.row span{color:#64748b}.row strong{color:#0f172a}'+
                '.foot{text-align:center;font-size:12px;color:#64748b;margin-top:16px}'+
                '.print-btn{display:none}'+
                '</style></head><body><div class="wrap">'+
                '<div class="head"><div class="name">LustreMDC</div><div class="sub">CLINIC APPOINTMENT SLIP</div></div>'+
                slip.innerHTML+
                '<div class="foot">Please arrive 10-15 minutes early. Show this slip at the counter.</div>'+
                '</div><script>window.onload=function(){window.print();}<\/script></body></html>');
            w.document.close();
            showToast('Slip ready to print','success');
        }
        function reloadPage(){document.body.classList.add('__leaving');setTimeout(function(){window.location.href='admin_dashboard.php';},215);}

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
        .content-card{animation:__meIn .42s cubic-bezier(.22,1,.36,1) both;animation-delay:.12s;opacity:0;animation-fill-mode:both}
        .stat-card{opacity:0;animation:__meIn .38s cubic-bezier(.22,1,.36,1) both;animation-fill-mode:both}
        .stats-grid .stat-card:nth-child(1){animation-delay:.04s}
        .stats-grid .stat-card:nth-child(2){animation-delay:.09s}
        .stats-grid .stat-card:nth-child(3){animation-delay:.14s}
        .stats-grid .stat-card:nth-child(4){animation-delay:.19s}
        .stats-grid .stat-card:nth-child(5){animation-delay:.24s}
        .stats-grid .stat-card:nth-child(6){animation-delay:.29s}
        tr{transition:background .12s}
        .action-select{transition:border-color .15s}
        .modal-box{animation:__meIn .3s cubic-bezier(.34,1.56,.64,1)}
        .result-modal-box{animation:__meIn .3s cubic-bezier(.34,1.56,.64,1)}
        .nav-item{transition:all .18s !important}
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
<script src="admin_datepicker.js?v=<?= filemtime(__DIR__ . '/../../admin_datepicker.js') ?>"></script>
<script src="admin_components.js?v=<?= filemtime(__DIR__ . '/../../admin_components.js') ?>"></script>
<script src="admin_theme.js?v=<?= filemtime(__DIR__ . '/../../admin_theme.js') ?>"></script>
<script>try{var __af=JSON.parse(localStorage.getItem('lustreAdminFlash')||'null');if(__af&&__af.m){localStorage.removeItem('lustreAdminFlash');window.__noFlash=1;showToast(__af.m,__af.t);}}catch(e){}</script>
</body>
</html>
