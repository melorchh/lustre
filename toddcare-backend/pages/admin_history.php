<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
include __DIR__ . '/../src/db.php';
$admin_name = $_SESSION["admin_name"];

// -- Stats ------------------------------------------------------------------
$total_all  = $conn->query("SELECT COUNT(*) c FROM appointments")->fetch_assoc()['c'];
$completed  = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='completed'")->fetch_assoc()['c'];
$cancelled  = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='cancelled'")->fetch_assoc()['c'];
$paid_count = $conn->query("SELECT COUNT(*) c FROM appointments WHERE payment_status='paid'")->fetch_assoc()['c'];
$lab_done   = $conn->query("SELECT COUNT(*) c FROM lab_tests WHERE status='completed'")->fetch_assoc()['c'];

// -- Archived Appointments -------------------------------------------------
$appointments = $conn->query("
    SELECT a.id, a.appointment_date, a.appointment_time, a.status,
           a.payment_status, a.created_at, a.updated_at,
           a.reschedule_count, a.last_rescheduled_at, a.appointment_duration,
           p.name AS patient_name, p.email AS patient_email, p.contact,
           p.address AS patient_address, p.age AS patient_age,
           d.name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN doctors  d ON a.doctor_id  = d.id
    WHERE a.status IN ('completed','cancelled')
    ORDER BY a.updated_at DESC
");

// Reschedule history, grouped by appointment (most recent first)
$archive_history_map = [];
$ahq = $conn->query("SELECT appointment_id, old_date, old_time, new_date, new_time, rescheduled_at FROM reschedule_history ORDER BY rescheduled_at DESC, id DESC");
if ($ahq) {
    while ($ah = $ahq->fetch_assoc()) {
        $archive_history_map[$ah['appointment_id']][] = $ah;
    }
}

// Group archived appointments into day folders (by archive date, newest first)
$apt_groups = [];
if ($appointments) {
    while ($apt = $appointments->fetch_assoc()) {
        $day = date('Y-m-d', strtotime($apt['updated_at']));
        $apt_groups[$day][] = $apt;
    }
}
krsort($apt_groups);

// -- Archived Lab Tests ----------------------------------------------------
$lab_tests = $conn->query("
    SELECT lt.id, lt.test_type, lt.priority, lt.status,
           lt.scheduled_date, lt.result, lt.notes,
           lt.created_at, lt.updated_at,
           p.name AS patient_name, p.email AS patient_email,
           d.name AS doctor_name, d.specialty
    FROM lab_tests lt
    JOIN patients p ON lt.patient_id = p.id
    JOIN doctors  d ON lt.doctor_id  = d.id
    WHERE lt.status IN ('completed','cancelled')
    ORDER BY lt.updated_at DESC
");

// Group archived lab tests into day folders (by archive date, newest first)
$lab_groups = [];
if ($lab_tests) {
    while ($lt = $lab_tests->fetch_assoc()) {
        $day = date('Y-m-d', strtotime($lt['updated_at']));
        $lab_groups[$day][] = $lt;
    }
}
krsort($lab_groups);

// -- Vitals records ----------------------------------------------------------
$vitals = $conn->query("
    SELECT v.id, v.visit_date, v.weight_kg, v.blood_pressure, v.heart_rate,
           v.fundal_height, v.notes, v.created_at,
           p.name AS patient_name, p.email AS patient_email
    FROM vitals v
    JOIN patients p ON v.patient_id = p.id
    ORDER BY v.visit_date DESC
");
$vitals_count = $vitals ? (int)$vitals->num_rows : 0;

// Group vitals into day folders (by recorded date, newest first)
$vital_groups = [];
if ($vitals) {
    while ($vt = $vitals->fetch_assoc()) {
        $day = date('Y-m-d', strtotime($vt['created_at']));
        $vital_groups[$day][] = $vt;
    }
}
krsort($vital_groups);

// -- Vaccination records -----------------------------------------------------
$vaccinations = $conn->query("
    SELECT va.id, va.vaccine_name, va.dose_label, va.administered_date,
           va.next_due_date, va.notes, va.created_at,
           p.name AS patient_name, p.email AS patient_email
    FROM vaccinations va
    JOIN patients p ON va.patient_id = p.id
    ORDER BY va.administered_date DESC
");
$vacc_count = $vaccinations ? (int)$vaccinations->num_rows : 0;

// Group vaccinations into day folders (by recorded date, newest first)
$vacc_groups = [];
if ($vaccinations) {
    while ($vc = $vaccinations->fetch_assoc()) {
        $day = date('Y-m-d', strtotime($vc['created_at']));
        $vacc_groups[$day][] = $vc;
    }
}
krsort($vacc_groups);

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
    <style>
        .archive-day-row{cursor:pointer}
        .archive-day-cell{background:#f8fafc !important;border-left:3px solid #16a34a;padding:.6rem 1rem !important}
        .archive-day-row:hover .archive-day-cell{background:#f1f5f9 !important}
        .archive-day-ic{color:#16a34a;vertical-align:middle;margin-right:.55rem}
        .archive-day-label{font-weight:700;color:#0f172a;font-size:.9rem;vertical-align:middle}
        .archive-day-count{color:#64748b;font-size:.78rem;margin-left:.6rem;vertical-align:middle}
        .archive-day-arrow{float:right;color:#94a3b8;transition:transform .2s ease;vertical-align:middle;line-height:0;margin-top:.2rem}
        .daypdf-btn{display:inline-flex;align-items:center;gap:.32rem;height:30px;padding:0 .72rem 0 .52rem;border:1px solid #16a34a;background:#16a34a;color:#fff;border-radius:999px;cursor:pointer;font-family:inherit;font-size:.72rem;font-weight:700;white-space:nowrap;letter-spacing:.03em;line-height:1;box-shadow:0 2px 6px rgba(22,163,74,.3);transition:background .15s ease,transform .15s ease,box-shadow .15s ease}
        .daypdf-btn:hover{background:#15803d;transform:translateY(-1px);box-shadow:0 3px 10px rgba(22,163,74,.45)}
        .daypdf-btn.active{background:#0f766e;border-color:#0f766e;outline:2px solid #99f6e4;outline-offset:2px;box-shadow:0 2px 8px rgba(15,118,110,.4)}
        .daypdf-btn svg{display:block}
        .daypdf-group{margin-left:auto;display:inline-flex;align-items:center;gap:.5rem;flex-wrap:wrap}
        .archive-day-row.collapsed .archive-day-arrow{transform:rotate(-90deg)}
    </style>
    <title>Archives &mdash; LustreMDC Admin</title>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="admin-layout">
    <!-- -- Sidebar -- -->
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
            <a href="admin_dashboard.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><span class="rail-label">Dashboard</span></a>
            <a href="admin_appointments.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span><span class="rail-label">Appointments</span></a>
            <a href="admin_patients.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="rail-label">Patients</span></a>
                <a href="admin_walkins.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.6 4.7L18 9.3l-4.4 1.6L12 15.6l-1.6-4.7L6 9.3l4.4-1.6z" style="stroke-width:1.8"/><path d="M5 20h14"/></svg></span><span class="rail-label">Walk-ins</span></a>
            <a href="admin_doctors.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span><span class="rail-label">Doctors</span></a>
            <a href="admin_schedules.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span class="rail-label">Schedules</span></a>
            <a href="admin_laboratory.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg></span><span class="rail-label">Laboratory</span></a>
            <a href="admin_vitals.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span><span class="rail-label">Vitals &amp; Vaccines</span></a>
            <a href="admin_reports.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15v-4"/><path d="M12 15V7"/><path d="M17 15v-6"/></svg></span><span class="rail-label">Reports</span></a>
            <a href="admin_history.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg></span><span class="rail-label">Archives</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="admin_logout.php" class="btn-logout" data-tip="Sign Out"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span><span class="rail-label">Sign Out</span></a>
        </div>
    </aside>

    <!-- -- Main -- -->
    <main class="admin-content">
        <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Open menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                    <span class="topbar-title">Archives</span>
                </div>
                <div class="topbar-actions">
    <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                    <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong><?php echo htmlspecialchars(explode(' ', trim($admin_name))[0]); ?></strong></div>
                    <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($admin_name),0,1)); ?></div>
                </div>
            </header>

        <!-- -- Hero -- -->
        <div class="archive-hero">
            <div class="archive-hero-tag"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Record Archive</div>
            <h1>History &amp; Archives</h1>
            <p>Complete archive of all completed and cancelled appointments and lab tests.</p>
        </div>

        <!-- -- Stats -- -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_all; ?></div>
                <div class="stat-label">All Appointments</div>
            </div>
            <div class="stat-card" style="border-top-color:#10b981">
                <div class="stat-number" style="color:#10b981"><?php echo $completed; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card" style="border-top-color:#ef4444">
                <div class="stat-number" style="color:#ef4444"><?php echo $cancelled; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
            <div class="stat-card" style="border-top-color:#0ea5e9">
                <div class="stat-number" style="color:#0ea5e9"><?php echo $paid_count; ?></div>
                <div class="stat-label">Payments Collected</div>
            </div>
<div class="stat-card" style="border-top-color:#8b5cf6">
                <div class="stat-number" style="color:#8b5cf6"><?php echo $lab_done; ?></div>
                <div class="stat-label">Lab Tests Done</div>
            </div>
            <div class="stat-card" style="border-top-color:#a855f7">
                <div class="stat-number" style="color:#a855f7"><?php echo $vitals_count; ?></div>
                <div class="stat-label">Vitals Recorded</div>
            </div>
            <div class="stat-card" style="border-top-color:#f43f5e">
                <div class="stat-number" style="color:#f43f5e"><?php echo $vacc_count; ?></div>
                <div class="stat-label">Vaccinations</div>
            </div>
        </div>

        <!-- -- Tabs -- -->
        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('appointments',this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Appointments
                <span class="tab-count" id="aptCount">0</span>
            </button>
<button class="tab-btn" onclick="switchTab('labtests',this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg> Lab Tests
                <span class="tab-count" id="labCount">0</span>
            </button>
            <button class="tab-btn" onclick="switchTab('vitals',this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> Vitals
                <span class="tab-count" id="vitalCount">0</span>
            </button>
            <button class="tab-btn" onclick="switchTab('vaccinations',this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0L11.5 5.85 9.9 4.25a5.4 5.4 0 0 0-7.65 7.65l1.6 1.6-6.4 6.4a1 1 0 0 0 0 1.41l2.83 2.83a1 1 0 0 0 1.41 0l6.4-6.4 1.6 1.6a5.4 5.4 0 0 0 7.65-7.65l-1.6-1.6 1.27-1.27a5.4 5.4 0 0 0 0-7.65z"/></svg> Vaccinations
                <span class="tab-count" id="vaccCount">0</span>
            </button>
        </div>

        <!-- ========================
             TAB 1 &mdash; APPOINTMENTS
        ======================== -->
        <div class="tab-panel active" id="tab-appointments">
            <div class="content-card">
                <div class="search-filter-bar">
                    <input type="search" id="aptSearch" placeholder="Search patient name..." oninput="filterApt()">
                    <input type="search" id="aptAddrSearch" placeholder="Search address..." oninput="filterApt()">
                    <input type="search" id="aptAgeSearch" placeholder="Search age..." oninput="filterApt()" class="sfb-age">
                    <select id="aptStatusFilter" class="filter-select" onchange="filterApt()">
                        <option value="">All Statuses</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
<select id="aptPayFilter" class="filter-select" onchange="filterApt()">
                        <option value="">All Payments</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Unpaid</option>
                    </select>
                    <span class="daypdf-group">
                        <?php foreach ($apt_groups as $gday => $apts): ?>
                            <button type="button" class="daypdf-btn" data-day="<?php echo $gday; ?>" title="Download <?php echo date('M j, Y', strtotime($gday)); ?> as PDF" onclick="window.location.href='archive_day_pdf.php?tab=appointments&day=<?php echo $gday; ?>'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><span><?php echo date('M j', strtotime($gday)); ?></span></button>
                        <?php endforeach; ?>
                    </span>
                    <span class="result-count" id="aptResultCount"></span>
                </div>
                <div class="table-scroll">
                    <table id="aptTable">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Archived On</th>
                            </tr>
                        </thead>
<tbody id="aptBody">
                        <?php
                        $apt_rows = 0;
                        foreach ($apt_groups as $gday => $apts):
                            $gdate = new DateTime($gday);
                        ?>
                        <tr class="archive-day-row" data-day="<?php echo $gday; ?>" onclick="toggleDay(this)">
                            <td colspan="7" class="archive-day-cell">
                                <span class="archive-day-ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
                                <span class="archive-day-label"><?php echo $gdate->format('l, F j, Y'); ?></span>
                                <span class="archive-day-count"><?php echo count($apts); ?> record<?php echo count($apts) !== 1 ? 's' : ''; ?></span>
                                <span class="archive-day-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                            </td>
                        </tr>
                        <?php foreach ($apts as $apt): ?>
                        <?php
                                $apt_rows++;
                                $s       = $apt['status'];
                                $ps      = $apt['payment_status'];
                                $date    = new DateTime($apt['appointment_date']);
                                $time    = new DateTime($apt['appointment_time']);
                                $updated = new DateTime($apt['updated_at']);
                        ?>
                        <tr data-status="<?php echo $s; ?>" data-payment="<?php echo $ps; ?>" data-name="<?php echo htmlspecialchars(strtolower($apt['patient_name']), ENT_QUOTES); ?>" data-address="<?php echo htmlspecialchars(strtolower($apt['patient_address'] ?? ''), ENT_QUOTES); ?>" data-age="<?php echo (int)$apt['patient_age']; ?>">
                            <td>
                                <div class="cell-name"><?php echo htmlspecialchars($apt['patient_name']); ?></div>
                                <div class="cell-sub"><?php echo htmlspecialchars($apt['patient_email']); ?></div>
                                <?php if ($apt['contact']): ?><div class="cell-sub"><?php echo htmlspecialchars($apt['contact']); ?></div><?php endif; ?>
                            </td>
                            <td>
                                <strong>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></strong><br>
                                <small style="color:#64748b"><?php echo htmlspecialchars($apt['specialty']); ?></small>
                            </td>
                            <td><?php echo $date->format('M j, Y'); ?></td>
                            <td><?php echo $time->format('g:i A'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $s; ?>"><?php echo ucfirst($s); ?></span>
                                <?php $ahist = !empty($archive_history_map[$apt['id']]) ? $archive_history_map[$apt['id']] : []; ?>
                                <?php if (!empty($apt['reschedule_count']) || !empty($ahist)): ?>
                                <span class="resched-tip" style="display:block;margin-top:4px">
                                    <span class="resched-badge">&#10227; Rescheduled<?php echo $apt['reschedule_count'] > 1 ? ' x' . (int)$apt['reschedule_count'] : ''; ?></span>
                                    <span class="resched-pop">
                                        <?php if (count($ahist) > 0): ?>
                                            <span class="resched-pop-title">Reschedule History</span>
                                            <?php foreach ($ahist as $e):
                                                $od = new DateTime($e['old_date']); $ot = new DateTime($e['old_time']);
                                                $nd = new DateTime($e['new_date']); $nt = new DateTime($e['new_time']);
                                                $rt = new DateTime($e['rescheduled_at']);
                                            ?>
                                            <span class="resched-pop-item">
                                                <span class="resched-change"><span class="resched-old"><?php echo $od->format('M j, Y') . ' &middot; ' . $ot->format('g:i A'); ?></span> &rarr; <span class="resched-new"><?php echo $nd->format('M j, Y') . ' ' . $nt->format('g:i A'); ?></span></span>
                                                <span class="resched-pop-time">rescheduled on <?php echo $rt->format('M j, g:i A'); ?></span>
                                            </span>
                                            <?php endforeach; ?>
                                        <?php elseif (!empty($apt['last_rescheduled_at'])): $ard = new DateTime($apt['last_rescheduled_at']); ?>
                                            <span class="resched-pop-title">Reschedule History</span>
                                            <span class="resched-pop-item">
                                                <span class="resched-change"><span class="resched-old">Rescheduled</span> &rarr; <span class="resched-new"><?php echo $date->format('M j, Y') . ' ' . $time->format('g:i A'); ?></span></span>
                                                <span class="resched-pop-time">last rescheduled on <?php echo $ard->format('M j, g:i A'); ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="resched-pop-title">Rescheduled</span>
                                            <span class="resched-pop-time">This appointment was rescheduled</span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $ps === 'paid' ? 'paid' : 'unpaid'; ?>">
                                    <?php echo $ps === 'paid' ? 'Paid' : 'Unpaid'; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $updated->format('M j, Y'); ?><br>
                                <small style="color:#94a3b8"><?php echo $updated->format('g:i A'); ?></small>
                                <div style="margin-top:7px">
                                    <button type="button" class="btn-slip" title="Print appointment slip" onclick="openSlip(<?php echo htmlspecialchars(json_encode([
                                        'patient' => $apt['patient_name'],
                                        'doctor'  => $apt['doctor_name'],
                                        'specialty' => $apt['specialty'],
                                        'date'    => $date->format('F j, Y'),
                                        'time'    => $time->format('g:i A'),
                                        'status'  => ucfirst($s),
                                        'payment' => ucfirst($ps),
                                    ])); ?>)">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg> Print Slip
                                    </button>
                                </div>
</td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php if ($apt_rows === 0): ?>
                        <tr><td colspan="7" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><circle cx="9" cy="10" r="1"/><circle cx="15" cy="10" r="1"/><path d="M9 16h6"/></svg></div>No archived appointments yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================
             TAB 2 &mdash; LAB TESTS
        ======================== -->
        <div class="tab-panel" id="tab-labtests">
            <div class="content-card">
<div class="search-filter-bar">
                    <input type="search" id="labSearchPatient" placeholder="Search patient..." oninput="filterLab()">
                    <input type="search" id="labSearchDoctor" placeholder="Search doctor..." oninput="filterLab()">
                    <input type="search" id="labSearchTest" placeholder="Search test type..." oninput="filterLab()">
                    <select id="labStatusFilter" class="filter-select" onchange="filterLab()">
                        <option value="">All Statuses</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <select id="labPriorityFilter" class="filter-select" onchange="filterLab()">
                        <option value="">All Priorities</option>
                        <option value="normal">Normal</option>
                        <option value="urgent">Urgent</option>
                        <option value="stat">STAT</option>
                    </select>
                    <span class="daypdf-group">
                        <?php foreach ($lab_groups as $gday => $labrows): ?>
                            <button type="button" class="daypdf-btn" data-day="<?php echo $gday; ?>" title="Download <?php echo date('M j, Y', strtotime($gday)); ?> as PDF" onclick="window.location.href='archive_day_pdf.php?tab=labtests&day=<?php echo $gday; ?>'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><span><?php echo date('M j', strtotime($gday)); ?></span></button>
                        <?php endforeach; ?>
                    </span>
                    <span class="result-count" id="labResultCount"></span>
                </div>
                <div class="table-scroll">
                    <table id="labTable">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Test Type</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Scheduled</th>
                                <th>Result</th>
                                <th>Archived On</th>
                            </tr>
                        </thead>
<tbody id="labBody">
                        <?php
                        $lab_rows = 0;
                        foreach ($lab_groups as $gday => $labrows):
                            $gdate = new DateTime($gday);
                        ?>
                        <tr class="archive-day-row" data-day="<?php echo $gday; ?>" onclick="toggleDay(this)">
                            <td colspan="8" class="archive-day-cell">
                                <span class="archive-day-ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
                                <span class="archive-day-label"><?php echo $gdate->format('l, F j, Y'); ?></span>
                                <span class="archive-day-count"><?php echo count($labrows); ?> record<?php echo count($labrows) !== 1 ? 's' : ''; ?></span>
                                <span class="archive-day-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                            </td>
                        </tr>
                        <?php foreach ($labrows as $lt): ?>
                        <?php
                                $lab_rows++;
                                $ls         = $lt['status'];
                                $pr         = $lt['priority'];
                                $sched      = $lt['scheduled_date'] ? (new DateTime($lt['scheduled_date']))->format('M j, Y') : '&mdash;';
                                $updated    = new DateTime($lt['updated_at']);
                                $has_result = !empty($lt['result']);
                                $result_esc  = htmlspecialchars($lt['result'] ?? '', ENT_QUOTES);
                                $notes_esc   = htmlspecialchars($lt['notes']  ?? '', ENT_QUOTES);
                                $patient_esc = htmlspecialchars($lt['patient_name'], ENT_QUOTES);
                                $doctor_esc  = htmlspecialchars($lt['doctor_name'],  ENT_QUOTES);
                                $test_esc    = htmlspecialchars($lt['test_type'],    ENT_QUOTES);
                                $patient_lc  = htmlspecialchars(strtolower($lt['patient_name']), ENT_QUOTES);
                                $doctor_lc   = htmlspecialchars(strtolower($lt['doctor_name']),  ENT_QUOTES);
                                $test_lc     = htmlspecialchars(strtolower($lt['test_type']),    ENT_QUOTES);
                        ?>
                        <tr data-status="<?php echo $ls; ?>" data-priority="<?php echo $pr; ?>" data-name="<?php echo $patient_lc; ?>" data-doctor="<?php echo $doctor_lc; ?>" data-test="<?php echo $test_lc; ?>">
                            <td>
                                <div class="cell-name"><?php echo htmlspecialchars($lt['patient_name']); ?></div>
                                <div class="cell-sub"><?php echo htmlspecialchars($lt['patient_email']); ?></div>
                            </td>
                            <td>
                                <strong>Dr. <?php echo htmlspecialchars($lt['doctor_name']); ?></strong><br>
                                <small style="color:#64748b"><?php echo htmlspecialchars($lt['specialty']); ?></small>
                            </td>
                            <td><strong><?php echo htmlspecialchars($lt['test_type']); ?></strong></td>
                            <td><span class="badge badge-<?php echo $pr; ?>"><?php echo strtoupper($pr); ?></span></td>
                            <td><span class="badge badge-<?php echo $ls; ?>"><?php echo ucfirst($ls); ?></span></td>
                            <td><?php echo $sched; ?></td>
                            <td>
                                <?php if ($has_result): ?>
                                <button class="btn-view" onclick="viewResult('<?php echo $test_esc; ?>','<?php echo $patient_esc; ?>','<?php echo $doctor_esc; ?>','<?php echo $sched; ?>','<?php echo $result_esc; ?>','<?php echo $notes_esc; ?>','<?php echo $ls; ?>')"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/></svg> View</button>
                                <?php else: ?>
                                <span style="color:#94a3b8;font-size:.82rem">No result</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $updated->format('M j, Y'); ?><br>
                                <small style="color:#94a3b8"><?php echo $updated->format('g:i A'); ?></small>
                            </td>
</tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php if ($lab_rows === 0): ?>
                        <tr><td colspan="8" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><circle cx="9" cy="10" r="1"/><circle cx="15" cy="10" r="1"/><path d="M9 16h6"/></svg></div>No archived lab tests yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================
             TAB 3 &mdash; VITALS
        ======================== -->
        <div class="tab-panel" id="tab-vitals">
            <div class="content-card">
                <div class="search-filter-bar">
                    <input type="search" id="vitalSearch" placeholder="Search patient name&#8230;" oninput="filterVitals()">
                    <span class="daypdf-group">
                        <?php foreach ($vital_groups as $gday => $vitrows): ?>
                            <button type="button" class="daypdf-btn" data-day="<?php echo $gday; ?>" title="Download <?php echo date('M j, Y', strtotime($gday)); ?> as PDF" onclick="window.location.href='archive_day_pdf.php?tab=vitals&day=<?php echo $gday; ?>'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><span><?php echo date('M j', strtotime($gday)); ?></span></button>
                        <?php endforeach; ?>
                    </span>
                    <span class="result-count" id="vitalResultCount"></span>
                </div>
                <div class="table-scroll">
                    <table id="vitalTable">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Visit Date</th>
                                <th>Weight</th>
                                <th>Blood Pressure</th>
                                <th>Heart Rate</th>
                                <th>Fundal Height</th>
                                <th>Notes</th>
                                <th>Recorded On</th>
                            </tr>
                        </thead>
                        <tbody id="vitalBody">
                        <?php
                        $vital_rows = 0;
                        foreach ($vital_groups as $gday => $vitrows):
                            $gdate = new DateTime($gday);
                        ?>
                        <tr class="archive-day-row" data-day="<?php echo $gday; ?>" onclick="toggleDay(this)">
                            <td colspan="8" class="archive-day-cell">
                                <span class="archive-day-ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
                                <span class="archive-day-label"><?php echo $gdate->format('l, F j, Y'); ?></span>
                                <span class="archive-day-count"><?php echo count($vitrows); ?> record<?php echo count($vitrows) !== 1 ? 's' : ''; ?></span>
                                <span class="archive-day-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                            </td>
                        </tr>
                        <?php foreach ($vitrows as $vt): ?>
                        <?php
                                $vital_rows++;
                                $vdate = new DateTime($vt['visit_date']);
                                $vcreated = new DateTime($vt['created_at']);
                                $vname = htmlspecialchars(strtolower($vt['patient_name']), ENT_QUOTES);
                        ?>
                        <tr data-name="<?php echo $vname; ?>">
                            <td>
                                <div class="cell-name"><?php echo htmlspecialchars($vt['patient_name']); ?></div>
                                <div class="cell-sub"><?php echo htmlspecialchars($vt['patient_email']); ?></div>
                            </td>
                            <td><strong><?php echo $vdate->format('M j, Y'); ?></strong></td>
                            <td><?php echo $vt['weight_kg'] ? $vt['weight_kg'] . ' kg' : '&mdash;'; ?></td>
                            <td><span class="badge badge-active"><?php echo $vt['blood_pressure'] ? htmlspecialchars($vt['blood_pressure']) : '&mdash;'; ?></span></td>
                            <td><?php echo $vt['heart_rate'] ? $vt['heart_rate'] . ' bpm' : '&mdash;'; ?></td>
                            <td><?php echo $vt['fundal_height'] ? $vt['fundal_height'] . ' cm' : '&mdash;'; ?></td>
                            <td>
                                <span class="cell-sub" style="max-width:220px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo $vt['notes'] ? htmlspecialchars($vt['notes']) : '&mdash;'; ?></span>
                            </td>
                            <td>
                                <?php echo $vcreated->format('M j, Y'); ?><br>
                                <small style="color:#94a3b8"><?php echo $vcreated->format('g:i A'); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php if ($vital_rows === 0): ?>
                        <tr><td colspan="8" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>No vitals recorded yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================
             TAB 4 &mdash; VACCINATIONS
        ======================== -->
        <div class="tab-panel" id="tab-vaccinations">
            <div class="content-card">
                <div class="search-filter-bar">
                    <input type="search" id="vaccSearchPatient" placeholder="Search patient..." oninput="filterVaccinations()">
                    <input type="search" id="vaccSearchVaccine" placeholder="Search vaccine..." oninput="filterVaccinations()">
                    <input type="search" id="vaccSearchDose" placeholder="Search dose..." oninput="filterVaccinations()">
                    <span class="daypdf-group">
                        <?php foreach ($vacc_groups as $gday => $vacrows): ?>
                            <button type="button" class="daypdf-btn" data-day="<?php echo $gday; ?>" title="Download <?php echo date('M j, Y', strtotime($gday)); ?> as PDF" onclick="window.location.href='archive_day_pdf.php?tab=vaccinations&day=<?php echo $gday; ?>'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><span><?php echo date('M j', strtotime($gday)); ?></span></button>
                        <?php endforeach; ?>
                    </span>
                    <span class="result-count" id="vaccResultCount"></span>
                </div>
                <div class="table-scroll">
                    <table id="vaccTable">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Vaccine</th>
                                <th>Dose</th>
                                <th>Administered</th>
                                <th>Next Due</th>
                                <th>Notes</th>
                                <th>Recorded On</th>
                            </tr>
                        </thead>
                        <tbody id="vaccBody">
                        <?php
                        $vacc_rows = 0;
                        foreach ($vacc_groups as $gday => $vacrows):
                            $gdate = new DateTime($gday);
                        ?>
<tr class="archive-day-row" data-day="<?php echo $gday; ?>" onclick="toggleDay(this)">
                            <td colspan="7" class="archive-day-cell">
                                <span class="archive-day-ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
                                <span class="archive-day-label"><?php echo $gdate->format('l, F j, Y'); ?></span>
                                <span class="archive-day-count"><?php echo count($vacrows); ?> record<?php echo count($vacrows) !== 1 ? 's' : ''; ?></span>
                                <span class="archive-day-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                            </td>
                        </tr>
                        <?php foreach ($vacrows as $vc): ?>
                        <?php
                                $vacc_rows++;
                                $vgiven = new DateTime($vc['administered_date']);
                                $vdue = $vc['next_due_date'];
                                $vcreated = new DateTime($vc['created_at']);
                                $vaccName = htmlspecialchars(strtolower($vc['patient_name']), ENT_QUOTES);
                                $vaccVaccine = htmlspecialchars(strtolower($vc['vaccine_name']), ENT_QUOTES);
                                $vaccDose = htmlspecialchars(strtolower($vc['dose_label']), ENT_QUOTES);
                                $dueStatus = '';
                                if ($vdue) {
                                    $dueDiff = (strtotime($vdue) - strtotime(date('Y-m-d'))) / 86400;
                                    $dueStatus = $dueDiff <= 14 ? 'badge-due' : 'badge-done';
                                }
                        ?>
                        <tr data-name="<?php echo $vaccName; ?>" data-vaccine="<?php echo $vaccVaccine; ?>" data-dose="<?php echo $vaccDose; ?>">
                            <td>
                                <div class="cell-name"><?php echo htmlspecialchars($vc['patient_name']); ?></div>
                                <div class="cell-sub"><?php echo htmlspecialchars($vc['patient_email']); ?></div>
                            </td>
                            <td><strong><?php echo htmlspecialchars($vc['vaccine_name']); ?></strong></td>
                            <td><span class="badge badge-normal"><?php echo htmlspecialchars($vc['dose_label']); ?></span></td>
                            <td><strong><?php echo $vgiven->format('M j, Y'); ?></strong></td>
                            <td>
                                <?php if ($vdue): ?>
                                    <span class="badge <?php echo $dueStatus; ?>"><?php echo (new DateTime($vdue))->format('M j, Y'); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-done">Complete</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="cell-sub" style="max-width:220px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo $vc['notes'] ? htmlspecialchars($vc['notes']) : '&mdash;'; ?></span>
                            </td>
                            <td>
                                <?php echo $vcreated->format('M j, Y'); ?><br>
                                <small style="color:#94a3b8"><?php echo $vcreated->format('g:i A'); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php if ($vacc_rows === 0): ?>
                        <tr><td colspan="7" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0L11.5 5.85 9.9 4.25a5.4 5.4 0 0 0-7.65 7.65l1.6 1.6-6.4 6.4a1 1 0 0 0 0 1.41l2.83 2.83a1 1 0 0 0 1.41 0l6.4-6.4 1.6 1.6a5.4 5.4 0 0 0 7.65-7.65l-1.6-1.6 1.27-1.27a5.4 5.4 0 0 0 0-7.65z"/></svg></div>No vaccination records yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- -- Print Slip Modal -- -->
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

<!-- -- Result Modal -- -->
<div class="result-modal" id="resultModal">
    <div class="result-modal-box">
        <button class="result-close" onclick="closeResultModal()">&#10005;</button>
        <h3 id="rvTitle">Lab Result</h3>
        <div class="result-meta-grid">
            <div class="result-meta-item"><label>Patient</label><span id="rvPatient">&mdash;</span></div>
            <div class="result-meta-item"><label>Doctor</label><span id="rvDoctor">&mdash;</span></div>
            <div class="result-meta-item"><label>Scheduled</label><span id="rvDate">&mdash;</span></div>
            <div class="result-meta-item"><label>Status</label><span id="rvStatus">&mdash;</span></div>
        </div>
        <div class="result-divider"></div>
        <div class="result-section-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/></svg> Findings / Result</div>
        <div class="result-content-box" id="rvResult"></div>
        <div id="rvNotesWrap" style="display:none">
            <div class="result-section-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg> Clinical Notes</div>
            <div class="result-content-box" id="rvNotes"></div>
        </div>
    </div>
</div>

<div id="loadingScreen" class="loading-screen"><div class="spinner"></div><p>Loading&#8230;</p></div>
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
    function showToast(msg,type=''){const t=document.getElementById('toast');t.textContent=msg;t.className='toast show'+(type?' '+type:'');setTimeout(()=>t.className='toast',3200);}

    function switchTab(name,btn){
        document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
        document.getElementById('tab-'+name).classList.add('active');
btn.classList.add('active');
    }

    function toggleDay(header){
        const collapsed=header.classList.toggle('collapsed');
        let sib=header.nextElementSibling;
        while(sib&&!sib.classList.contains('archive-day-row')){
            if(!collapsed) sib.style.display='';
            else sib.style.display='none';
            sib=sib.nextElementSibling;
        }
        const day=header.dataset.day;
        if(day){
            const tbl=header.closest('tbody');
            tbl.querySelectorAll('.daypdf-btn[data-day]').forEach(b=>b.classList.toggle('active', !collapsed && b.dataset.day===day));
        }
    }
    function expandAllDays(tbody){
        tbody.querySelectorAll('.archive-day-row').forEach(h=>{
            h.classList.remove('collapsed');
            let sib=h.nextElementSibling;
            while(sib&&!sib.classList.contains('archive-day-row')){ sib.style.display=''; sib=sib.nextElementSibling; }
        });
        tbody.querySelectorAll('.daypdf-btn.active').forEach(b=>b.classList.remove('active'));
    }
    function syncDayHeaders(tbody){
        let header=null, visible=0;
        tbody.querySelectorAll('tr').forEach(tr=>{
            if(tr.classList.contains('archive-day-row')){
                if(header) header.style.display=visible>0?'':'none';
                header=tr; visible=0;
            } else {
                if(tr.style.display!=='none') visible++;
            }
        });
        if(header) header.style.display=visible>0?'':'none';
    }

    function filterApt(){
        const name=document.getElementById('aptSearch').value.toLowerCase().trim();
        const address=document.getElementById('aptAddrSearch').value.toLowerCase().trim();
        const age=document.getElementById('aptAgeSearch').value.trim();
        const status=document.getElementById('aptStatusFilter').value;
        const payment=document.getElementById('aptPayFilter').value;
        const tbody=document.getElementById('aptBody');
        expandAllDays(tbody);
        let v=0;
        tbody.querySelectorAll('tr[data-status]').forEach(row=>{
            const rName=row.dataset.name||'';
            const rAddr=row.dataset.address||'';
            const rAge=row.dataset.age||'';
            const nameOk=!name||rName.indexOf(name)!==-1;
            const addrOk=!address||rAddr.indexOf(address)!==-1;
            const ageOk=!age||String(rAge).indexOf(age)!==-1;
            const ok=nameOk&&addrOk&&ageOk&&(!status||row.dataset.status===status)&&(!payment||row.dataset.payment===payment);
            row.style.display=ok?'':'none'; if(ok)v++;
        });
        syncDayHeaders(tbody);
        document.getElementById('aptResultCount').textContent=v+' record'+(v!==1?'s':'')+' shown';
    }

function filterLab(){
        const patient=document.getElementById('labSearchPatient').value.toLowerCase().trim();
        const doctor=document.getElementById('labSearchDoctor').value.toLowerCase().trim();
        const test=document.getElementById('labSearchTest').value.toLowerCase().trim();
        const status=document.getElementById('labStatusFilter').value;
        const priority=document.getElementById('labPriorityFilter').value;
        const tbody=document.getElementById('labBody');
        expandAllDays(tbody);
        let v=0;
        tbody.querySelectorAll('tr[data-status]').forEach(row=>{
            const nameOk=!patient||(row.dataset.name||'').indexOf(patient)!==-1;
            const doctorOk=!doctor||(row.dataset.doctor||'').indexOf(doctor)!==-1;
            const testOk=!test||(row.dataset.test||'').indexOf(test)!==-1;
            const ok=nameOk&&doctorOk&&testOk&&(!status||row.dataset.status===status)&&(!priority||row.dataset.priority===priority);
            row.style.display=ok?'':'none'; if(ok)v++;
        });
        syncDayHeaders(tbody);
        document.getElementById('labResultCount').textContent=v+' record'+(v!==1?'s':'')+' shown';
    }

    function filterVitals(){
        const search=document.getElementById('vitalSearch').value.toLowerCase().trim();
        const tbody=document.getElementById('vitalBody');
        expandAllDays(tbody);
        let v=0;
        tbody.querySelectorAll('tr[data-name]').forEach(row=>{
            const rName=row.dataset.name||'';
            const ok=!search||rName.indexOf(search)!==-1;
            row.style.display=ok?'':'none'; if(ok)v++;
        });
        syncDayHeaders(tbody);
        document.getElementById('vitalResultCount').textContent=v+' record'+(v!==1?'s':'')+' shown';
    }

    function filterVaccinations(){
        const patient=document.getElementById('vaccSearchPatient').value.toLowerCase().trim();
        const vaccine=document.getElementById('vaccSearchVaccine').value.toLowerCase().trim();
        const dose=document.getElementById('vaccSearchDose').value.toLowerCase().trim();
        const tbody=document.getElementById('vaccBody');
        expandAllDays(tbody);
        let v=0;
        tbody.querySelectorAll('tr[data-name]').forEach(row=>{
            const nameOk=!patient||(row.dataset.name||'').indexOf(patient)!==-1;
            const vaccineOk=!vaccine||(row.dataset.vaccine||'').indexOf(vaccine)!==-1;
            const doseOk=!dose||(row.dataset.dose||'').indexOf(dose)!==-1;
            const ok=nameOk&&vaccineOk&&doseOk;
            row.style.display=ok?'':'none'; if(ok)v++;
        });
        syncDayHeaders(tbody);
        document.getElementById('vaccResultCount').textContent=v+' record'+(v!==1?'s':'')+' shown';
    }

    function viewResult(testType,patient,doctor,date,result,notes,status){
        document.getElementById('rvTitle').textContent=testType;
        document.getElementById('rvPatient').textContent=patient;
        document.getElementById('rvDoctor').textContent='Dr. '+doctor;
        document.getElementById('rvDate').textContent=date;
        document.getElementById('rvStatus').textContent=status.charAt(0).toUpperCase()+status.slice(1);
        const resEl=document.getElementById('rvResult');
        resEl.textContent=result||'No result recorded.';
        resEl.classList.toggle('empty',!result);
        const hasNotes=notes&&notes.trim();
        document.getElementById('rvNotesWrap').style.display=hasNotes?'':'none';
        document.getElementById('rvNotes').textContent=notes||'';
        document.getElementById('resultModal').classList.add('active');
        document.body.style.overflow='hidden';
    }
    function closeResultModal(){
        document.getElementById('resultModal').classList.remove('active');
        document.body.style.overflow='';
    }
    document.getElementById('resultModal').addEventListener('click',function(e){if(e.target===this)closeResultModal();});

    // ---- Modal helpers ----
    function openModal(id){document.getElementById(id).classList.add('active');document.body.style.overflow='hidden';}
    function closeModal(id){document.getElementById(id).classList.remove('active');document.body.style.overflow='';}

    // ---- Print appointment slip ----
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
        openModal('slipModal');
    }
    function printSlip(){
        var slip=document.getElementById('slipBody');
        var w=window.open('','_blank','width=460,height=640');
        if(!w){showToast('Popup blocked \u2014 allow popups to print','error');return;}
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
            '</style></head><body><div class="wrap">'+
            '<div class="head"><div class="name">LustreMDC</div><div class="sub">CLINIC APPOINTMENT SLIP</div></div>'+
            slip.innerHTML+
            '<div class="foot">Please arrive 10-15 minutes early. Show this slip at the counter.</div>'+
            '</div><script>window.onload=function(){window.print();}<\/script></body></html>');
        w.document.close();
    }

(function(){
        const a=document.querySelectorAll('#aptBody tr[data-status]').length;
        const l=document.querySelectorAll('#labBody tr[data-status]').length;
        const v=document.querySelectorAll('#vitalBody tr[data-name]').length;
        const x=document.querySelectorAll('#vaccBody tr[data-name]').length;
        document.getElementById('aptCount').textContent=a;
        document.getElementById('labCount').textContent=l;
        document.getElementById('vitalCount').textContent=v;
        document.getElementById('vaccCount').textContent=x;
        document.getElementById('aptResultCount').textContent=a+' record'+(a!==1?'s':'')+' shown';
        document.getElementById('labResultCount').textContent=l+' record'+(l!==1?'s':'')+' shown';
        document.getElementById('vitalResultCount').textContent=v+' record'+(v!==1?'s':'')+' shown';
        document.getElementById('vaccResultCount').textContent=x+' record'+(x!==1?'s':'')+' shown';
    })();

    /* -- Smooth Transitions &mdash; same as every other admin page -- */
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
            .archive-hero{animation:__meIn .45s cubic-bezier(.22,1,.36,1) both}
            .stat-card{opacity:0;animation:__meIn .38s cubic-bezier(.22,1,.36,1) both;animation-fill-mode:both}
.stats-grid .stat-card:nth-child(1){animation-delay:.04s}
            .stats-grid .stat-card:nth-child(2){animation-delay:.09s}
            .stats-grid .stat-card:nth-child(3){animation-delay:.14s}
            .stats-grid .stat-card:nth-child(4){animation-delay:.19s}
            .stats-grid .stat-card:nth-child(5){animation-delay:.24s}
            .stats-grid .stat-card:nth-child(6){animation-delay:.29s}
            .stats-grid .stat-card:nth-child(7){animation-delay:.34s}
            .content-card{opacity:0;animation:__meIn .42s cubic-bezier(.22,1,.36,1) both;animation-delay:.12s;animation-fill-mode:both}
            tr{transition:background .12s}
            @keyframes __tabIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
            .tab-panel.active{animation:__tabIn .28s cubic-bezier(.22,1,.36,1)}
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
<script src="admin_components.js?v=<?= filemtime(__DIR__ . '/../../admin_components.js') ?>"></script>
<script src="admin_theme.js?v=<?= filemtime(__DIR__ . '/../../admin_theme.js') ?>"></script>
</body>
</html>
