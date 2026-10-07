<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_id"])) { header("Location: doctor_login.php"); exit; }
require_once __DIR__ . '/../src/db.php';

$doctor_id   = (int)$_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'];

function qc($conn, $sql) {
    $r = $conn->query($sql);
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}

$count_tests      = qc($conn, "SELECT COUNT(*) c FROM lab_tests WHERE doctor_id=$doctor_id");
$count_pending    = qc($conn, "SELECT COUNT(*) c FROM lab_tests WHERE doctor_id=$doctor_id AND status='pending'");
$count_processing = qc($conn, "SELECT COUNT(*) c FROM lab_tests WHERE doctor_id=$doctor_id AND status='processing'");
$count_completed  = qc($conn, "SELECT COUNT(*) c FROM lab_tests WHERE doctor_id=$doctor_id AND status='completed'");
$count_today_appts    = qc($conn, "SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND appointment_date=CURRENT_DATE AND status IN ('confirmed','pending')");
$count_pending_results = qc($conn, "SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND status='completed' AND (result IS NULL OR result = '')");
$active_days = qc($conn, "SELECT COUNT(DISTINCT day_of_week) c FROM doctor_schedules WHERE doctor_id=$doctor_id AND is_active=1");

$pending_results_list = $conn->query("
    SELECT a.id, a.appointment_date, a.appointment_time, a.service_requested,
           p.name AS patient_name, p.email AS patient_email
    FROM appointments a JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = $doctor_id AND a.status='completed' AND (a.result IS NULL OR a.result = '')
    ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 8
");

$recent_tests = $conn->query("
    SELECT lt.id, lt.test_type, lt.status, lt.created_at, p.name AS patient_name
    FROM lab_tests lt JOIN patients p ON lt.patient_id = p.id
    WHERE lt.doctor_id = $doctor_id
    ORDER BY lt.created_at DESC LIMIT 6
");

$upcoming_appts = $conn->query("
    SELECT a.appointment_date, a.appointment_time, a.status, p.name AS patient_name, a.service_requested
    FROM appointments a JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = $doctor_id AND a.appointment_date >= CURRENT_DATE AND a.status IN ('pending','confirmed')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 6
");

// Today's walk-in queue for this doctor (queue # assigned in arrival order)
require_once __DIR__ . '/walkin_schema.php';
walkins_ensure_result_column($conn);
$walkin_queue = [];
$queue_waiting = 0;
$queue_today = date('Y-m-d');
$walkins = $conn->query("
    SELECT w.id, w.arrival_time, w.status, w.result,
           p.name AS patient_name, p.contact
    FROM walk_ins w
    JOIN patients p ON w.patient_id = p.id
    WHERE w.doctor_id = $doctor_id AND w.arrival_date = '$queue_today'
    ORDER BY w.arrival_time ASC, w.created_at ASC, w.id ASC
");
if ($walkins) {
    $qn = 0;
    while ($w = $walkins->fetch_assoc()) {
        $qn++;
        if ($w['status'] === 'waiting') $queue_waiting++;
        $walkin_queue[] = [
            'id'      => (int)$w['id'],
            'queue'   => $qn,
            'name'    => $w['patient_name'],
            'contact' => $w['contact'],
            'arrival' => date('g:i A', strtotime($w['arrival_time'])),
            'status'  => $w['status'],
            'result'  => (string)($w['result'] ?? ''),
        ];
    }
}

$conn->close();

function fill_color($s) {
    return ['pending' => '#f59e0b', 'processing' => '#3b82f6', 'completed' => '#10b981', 'cancelled' => '#ef4444'][$s] ?? '#94a3b8';
}
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
    <link rel="stylesheet" href="doctor.css?v=<?= filemtime(__DIR__ . '/../../doctor.css') ?>">
    <title>Dashboard - Doctor Portal</title>
</head>
<body class="doctor-page">
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="admin-layout">
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-brand">
                <span class="brand-icon"><img src="images/Lustre.png" alt="LustreMDC Logo" width="42" height="42" /></span>
                <span class="brand-text"><span>Doctor</span></span>
            </div>
            <div class="sidebar-user">
                <div class="avatar avatar--sm"><?php echo strtoupper(substr(trim($doctor_name),0,1)); ?></div>
                <div class="sidebar-user-body"><div class="sidebar-user-label">Signed in as</div><div class="sidebar-user-name">Dr. <?php echo htmlspecialchars($doctor_name); ?></div></div>
            </div>
            <nav class="sidebar-nav">
                <a href="doctor_dashboard.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><span class="rail-label">Dashboard</span></a>
                <a href="doctor_appointments.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 18l3 3 3-3"/></svg></span><span class="rail-label">Appointments</span></a>
                <a href="doctor_laboratory.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg></span><span class="rail-label">Laboratory</span></a>
                <a href="doctor_schedule.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span class="rail-label">Availability</span></a>
            </nav>
            <div class="sidebar-footer">
                <a href="doctor_logout.php" class="btn-logout" data-tip="Sign Out"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span><span class="rail-label">Sign Out</span></a>
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
                    <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong>Dr. <?php echo htmlspecialchars(explode(' ', trim($doctor_name))[0]); ?></strong></div>
                    <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($doctor_name),0,1)); ?></div>
                </div>
            </header>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $count_tests; ?></div>
                    <div class="stat-label">Total Lab Tests</div>
                </div>
                <div class="stat-card" style="border-top-color:#f59e0b">
                    <div class="stat-number" style="color:#f59e0b"><?php echo $count_pending; ?></div>
                    <div class="stat-label">Pending Lab Tests</div>
                </div>
                <div class="stat-card" style="border-top-color:#3b82f6">
                    <div class="stat-number" style="color:#3b82f6"><?php echo $count_today_appts; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
                <div class="stat-card" style="border-top-color:#0ea5e9">
                    <div class="stat-number" id="queueWaitingStat" style="color:#0ea5e9"><?php echo $queue_waiting; ?></div>
                    <div class="stat-label">Walk-ins Waiting</div>
                </div>
                <div class="stat-card" style="border-top-color:#f59e0b">
                    <div class="stat-number" style="color:#f59e0b"><?php echo $count_pending_results; ?></div>
                    <div class="stat-label">Appointments Awaiting Result</div>
                </div>
                <div class="stat-card" style="border-top-color:#10b981">
                    <div class="stat-number" style="color:#10b981"><?php echo $active_days; ?>/7</div>
                    <div class="stat-label">Available Days</div>
                </div>
            </div>

            <div class="content-card">
                <div class="patients-header">
                    <div>
                        <h2 style="margin-bottom:4px;">Walk-in Queue (Today)</h2>
                        <small style="color:#64748b">Walk-ins registered by the front desk, in arrival order. <span id="queueUpdated">Auto-refreshes every 20s</span></small>
                    </div>
                    <button type="button" class="btn-sm btn-edit" onclick="refreshQueue(true)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                        Refresh
                    </button>
                </div>
                <div class="table-scroll">
                    <table id="queueTable">
                        <thead><tr><th>Queue</th><th>Patient</th><th>Arrival</th><th>Status</th></tr></thead>
                        <tbody id="queueBody">
                        <?php if (count($walkin_queue) > 0): ?>
                            <?php foreach ($walkin_queue as $w):
                                $wcolors = ['waiting'=>'background:#fef3c7;color:#92400e','in_service'=>'background:#dbeafe;color:#1e40af','served'=>'background:#d1fae5;color:#065f46','cancelled'=>'background:#fee2e2;color:#991b1b'];
                                $wbc = $wcolors[$w['status']] ?? '';
                            ?>
                            <tr>
                                <td><span class="badge" style="background:#16a34a;color:#fff;font-size:1rem;font-weight:800">#<?php echo $w['queue']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($w['name']); ?></strong><br><small style="color:#64748b"><?php echo htmlspecialchars($w['contact']); ?></small></td>
                                <td><?php echo htmlspecialchars($w['arrival']); ?></td>
                                <td style="white-space:nowrap">
                                    <span class="badge" style="<?php echo $wbc; ?>"><?php echo ucfirst(str_replace('_',' ',$w['status'])); ?></span>
                                    <select class="action-select" data-id="<?php echo $w['id']; ?>" data-prev="<?php echo htmlspecialchars($w['status']); ?>" onchange="updateWalkinStatus(this)" aria-label="Queue status">
                                        <?php foreach (['waiting','in_service','served','cancelled'] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo $w['status']===$opt?'selected':''; ?>><?php echo ucfirst(str_replace('_',' ',$opt)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($w['status'] === 'served'): ?>
                                    <button type="button" class="btn-sm btn-edit" style="margin-left:6px" onclick="openWalkinResultModal(<?php echo (int)$w['id']; ?>, 'edit')"><?php echo ($w['result'] !== '') ? 'Result' : 'Add Result'; ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="empty-state-row" id="queueEmpty"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.6 4.7L18 9.3l-4.4 1.6L12 15.6l-1.6-4.7L6 9.3l4.4-1.6z" style="stroke-width:1.8"/><path d="M5 20h14"/></svg></div>No walk-ins registered yet today.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
                    <h2 style="margin-bottom:0;">Upcoming Appointments</h2>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                        <a href="doctor_appointments.php" class="btn-sm btn-edit" style="text-decoration:none">Manage Appointments</a>
                        <a href="doctor_schedule.php" class="btn-sm btn-edit" style="text-decoration:none">Manage Availability</a>
                    </div>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Service</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if ($upcoming_appts && $upcoming_appts->num_rows > 0): ?>
                            <?php while ($ap = $upcoming_appts->fetch_assoc()):
                                $colors = ['pending'=>'background:#fef3c7;color:#92400e','confirmed'=>'background:#d1fae5;color:#065f46','cancelled'=>'background:#fee2e2;color:#991b1b','completed'=>'background:#e2e8f0;color:#334155'];
                                $bc = $colors[$ap['status']] ?? '';
                            ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($ap['appointment_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($ap['appointment_time'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($ap['patient_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($ap['service_requested'] ?: 'General consultation'); ?></td>
                                <td><span class="badge" style="<?php echo $bc; ?>"><?php echo ucfirst($ap['status']); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;color:#94a3b8;">No upcoming appointments.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
                    <h2 style="margin-bottom:0;">Pending Results</h2>
                    <a href="doctor_appointments.php" class="btn-sm btn-edit" style="text-decoration:none">All Appointments</a>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Patient</th><th>Service</th><th>Date</th><th>Time</th><th>Result</th></tr></thead>
                        <tbody>
                        <?php if ($pending_results_list && $pending_results_list->num_rows > 0): ?>
                            <?php while ($pr = $pending_results_list->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($pr['patient_name']); ?></strong><br>
                                    <small style="color:#64748b"><?php echo htmlspecialchars($pr['patient_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($pr['service_requested'] ?: 'General consultation'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($pr['appointment_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($pr['appointment_time'])); ?></td>
                                <td style="white-space:nowrap">
                                    <button class="btn-sm btn-edit" onclick="openDashResultModal(<?php echo (int)$pr['id']; ?>, '<?php echo addslashes(htmlspecialchars($pr['patient_name'])); ?>', '<?php echo addslashes(htmlspecialchars($pr['service_requested'] ?: 'General consultation')); ?>')">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> Write Result
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;color:#94a3b8;">No appointments awaiting a result. Well done!</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
                    <h2 style="margin-bottom:0;">Recent Lab Tests</h2>
                    <a href="doctor_laboratory.php" class="btn-sm btn-edit" style="text-decoration:none">Open Laboratory</a>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>ID</th><th>Patient</th><th>Test Type</th><th>Status</th><th>Requested</th></tr></thead>
                        <tbody>
                        <?php if ($recent_tests && $recent_tests->num_rows > 0): ?>
                            <?php while ($lt = $recent_tests->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $lt['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($lt['patient_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($lt['test_type']); ?></td>
                                <td>
                                    <span class="badge" style="background:<?php echo fill_color($lt['status']); ?>21;color:<?php echo fill_color($lt['status']); ?>"><?php echo ucfirst($lt['status']); ?></span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($lt['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;color:#94a3b8;">No lab tests assigned yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="loadingScreen" class="loading-screen"><div class="spinner"></div><p>Processing...</p></div>
    <div id="toast" class="toast"></div>

    <!-- Write Result Modal -->
    <div class="result-modal" id="dashResultModal">
        <div class="result-modal-box">
            <button class="result-close" onclick="closeDashResultModal()">&#10005;</button>
            <h3>Write Appointment Result</h3>
            <div class="info-row" id="dashModalInfoRow"></div>
            <div class="form-group">
                <label>Appointment Result / Findings</label>
                <textarea id="dashResult" rows="8" placeholder="Enter the appointment result, diagnosis, findings, and notes here..."></textarea>
            </div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                <button class="btn-primary" onclick="saveDashResult()"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Save Result</button>
                <button class="btn-secondary" onclick="closeDashResultModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Walk-in Result Modal -->
    <div class="result-modal" id="walkinResultModal">
        <div class="result-modal-box">
            <button class="result-close" onclick="closeWalkinResultModal()">&#10005;</button>
            <h3 id="wqResultTitle">Walk-in Result</h3>
            <div class="info-row" id="wqResultInfoRow"></div>
            <div class="form-group">
                <label>Result / Findings</label>
                <textarea id="wqResultText" rows="8" placeholder="Enter the result, diagnosis, findings, and notes for this walk-in..."></textarea>
            </div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                <button class="btn-primary" onclick="saveWalkinResult()"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Save Result</button>
                <button class="btn-secondary" onclick="closeWalkinResultModal()">Cancel</button>
            </div>
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
        function showToast(msg,type=''){const t=document.getElementById('toast');t.textContent=msg;t.className='toast show'+(type?' '+type:'');setTimeout(()=>t.className='toast',3000);}
        function showLoading(){document.getElementById('loadingScreen').classList.add('active');}
        function hideLoading(){document.getElementById('loadingScreen').classList.remove('active');}

        let dashApptId = null;
        function openDashResultModal(id, patientName, service){
            dashApptId = id;
            document.getElementById('dashModalInfoRow').innerHTML =
                '<div class="info-item"><label>Patient</label><span>'+patientName+'</span></div>' +
                '<div class="info-item"><label>Service</label><span>'+service+'</span></div>' +
                '<div class="info-item"><label>Appt ID</label><span>#'+id+'</span></div>';
            document.getElementById('dashResult').value = '';
            document.getElementById('dashResultModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeDashResultModal(){
            document.getElementById('dashResultModal').classList.remove('active');
            document.body.style.overflow = '';
            dashApptId = null;
        }
        function saveDashResult(){
            if(!dashApptId) return;
            const result = document.getElementById('dashResult').value.trim();
            if(!result){ showToast('Please enter the appointment result','error'); return; }
            showLoading();
            const fd = new FormData();
            fd.append('appointment_id', dashApptId);
            fd.append('result', result);
            fetch('doctor_result_action.php?action=save_result', { method:'POST', body: fd })
            .then(r=>r.text()).then(res=>{
                hideLoading();
                if(res.trim()==='success'){
                    showToast('Result saved successfully!','success');
                    closeDashResultModal();
                    document.body.classList.add('__leaving');
                    setTimeout(()=>location.reload(),215);
                }
                else showToast(res,'error');
            }).catch(()=>{ hideLoading(); showToast('Error','error'); });
        }
        document.getElementById('dashResultModal').addEventListener('click', function(e){
            if(e.target === this) closeDashResultModal();
        });

        /* -- Walk-in queue (live) -- */
        const WQ_STATUSES = ['waiting','in_service','served','cancelled'];
        const WQ_COLORS = {
            waiting:'background:#fef3c7;color:#92400e',
            in_service:'background:#dbeafe;color:#1e40af',
            served:'background:#d1fae5;color:#065f46',
            cancelled:'background:#fee2e2;color:#991b1b'
        };
        const WQ_EMPTY = '<tr><td colspan="4" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.6 4.7L18 9.3l-4.4 1.6L12 15.6l-1.6-4.7L6 9.3l4.4-1.6z" style="stroke-width:1.8"/><path d="M5 20h14"/></svg></div>No walk-ins registered yet today.</td></tr>';
        function wqEsc(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
        function wqLabel(s){return s.charAt(0).toUpperCase()+s.slice(1).replace(/_/g,' ');}
        function wqRow(w){
            const opts = WQ_STATUSES.map(s=>'<option value="'+s+'"'+(w.status===s?' selected':'')+'>'+wqLabel(s)+'</option>').join('');
            const resultBtn = w.status==='served'
                ? ' <button type="button" class="btn-sm btn-edit" style="margin-left:4px" onclick="openWalkinResultModal('+w.id+',\'edit\')">'+((w.result&&w.result.length)?'Result':'Add Result')+'</button>'
                : '';
            return '<tr>' +
                '<td><span class="badge" style="background:#16a34a;color:#fff;font-size:1rem;font-weight:800">#'+w.queue+'</span></td>' +
                '<td><strong>'+wqEsc(w.name)+'</strong><br><small style="color:#64748b">'+wqEsc(w.contact)+'</small></td>' +
                '<td>'+wqEsc(w.arrival)+'</td>' +
                '<td style="white-space:nowrap">' +
                    '<span class="badge" style="'+(WQ_COLORS[w.status]||'')+'">'+wqLabel(w.status)+'</span> ' +
                    '<select class="action-select" data-id="'+w.id+'" data-prev="'+wqEsc(w.status)+'" onchange="updateWalkinStatus(this)" aria-label="Queue status">'+opts+'</select>' +
                    resultBtn +
                '</td></tr>';
        }
        let wqBusy = false, wqPending = false;
        function refreshQueue(manual){
            if (wqBusy){ if (manual) wqPending = true; return; }
            wqBusy = true; wqPending = false;
            fetch('doctor_walkin_queue.php')
            .then(r=>r.json())
            .then(d=>{
                if(!d || !d.ok) return;
                const body = document.getElementById('queueBody');
                if (body) body.innerHTML = d.rows.length ? d.rows.map(wqRow).join('') : WQ_EMPTY;
                const stat = document.getElementById('queueWaitingStat');
                if (stat) stat.textContent = d.waiting;
                const upd = document.getElementById('queueUpdated');
                if (upd) upd.textContent = 'Updated ' + new Date().toLocaleTimeString([], {hour:'numeric',minute:'2-digit',second:'2-digit'});
                if (manual) showToast('Queue refreshed','success');
            })
            .catch(()=>{ if (manual) showToast('Could not refresh the queue','error'); })
            .finally(()=>{ wqBusy = false; if (wqPending) refreshQueue(false); });
        }
        function updateWalkinStatus(sel){
            const id = sel.getAttribute('data-id');
            if (sel.value === 'served') {
                openWalkinResultModal(id, 'served');
                return;
            }
            const fd = new FormData();
            fd.append('action','update_status');
            fd.append('walkin_id', id);
            fd.append('status', sel.value);
            showLoading();
            fetch('doctor_walkin_queue.php', {method:'POST', body:fd})
            .then(r=>r.text())
            .then(res=>{
                hideLoading();
                if(res.trim()==='success'){
                    sel.setAttribute('data-prev', sel.value);
                    showToast('Queue status updated','success');
                } else {
                    showToast(res,'error');
                    sel.value = sel.getAttribute('data-prev') || 'waiting';
                }
                wqPending = true;
                refreshQueue();
            })
            .catch(()=>{ hideLoading(); showToast('Network error','error'); sel.value = sel.getAttribute('data-prev') || 'waiting'; wqPending = true; refreshQueue(); });
        }

        /* -- Walk-in result modal -- */
        let wqModalMode = 'edit';
        let wqModalId = null;
        function openWalkinResultModal(id, mode){
            wqModalId = id;
            wqModalMode = mode || 'edit';
            const ta = document.getElementById('wqResultText');
            if (ta) ta.value = '';
            const title = document.getElementById('wqResultTitle');
            if (title) title.textContent = wqModalMode === 'served' ? 'Mark Served · Input Result' : 'Walk-in Result';
            const info = document.getElementById('wqResultInfoRow');
            if (info) info.innerHTML = '<div class="info-item"><label>Patient</label><span>Loading&hellip;</span></div>';
            const m = document.getElementById('walkinResultModal');
            m.classList.add('active');
            document.body.style.overflow = 'hidden';
            fetch('doctor_walkin_queue.php')
            .then(r=>r.json())
            .then(d=>{
                if(!d || !d.ok || wqModalId !== id) return;
                const row = (d.rows||[]).find(x=>x.id===id);
                if(!row) return;
                if (info) info.innerHTML =
                    '<div class="info-item"><label>Queue</label><span>#'+row.queue+'</span></div>' +
                    '<div class="info-item"><label>Patient</label><span>'+wqEsc(row.name)+'</span></div>' +
                    '<div class="info-item"><label>Status</label><span>'+wqLabel(row.status)+'</span></div>';
                if (ta && wqModalMode === 'edit') ta.value = row.result || '';
            })
            .catch(()=>{});
        }
        function closeWalkinResultModal(){
            const m = document.getElementById('walkinResultModal');
            if (m) m.classList.remove('active');
            document.body.style.overflow = '';
            if (wqModalMode === 'served' && wqModalId) {
                const sel = document.querySelector('#queueBody select[data-id="'+wqModalId+'"]');
                if (sel) sel.value = sel.getAttribute('data-prev') || 'waiting';
            }
            wqModalId = null;
            wqModalMode = 'edit';
        }
        function saveWalkinResult(){
            if(!wqModalId) return;
            const result = document.getElementById('wqResultText').value.trim();
            if(!result && wqModalMode !== 'served'){
                showToast('Please enter the result','error');
                return;
            }
            showLoading();
            const fd = new FormData();
            fd.append('walkin_id', wqModalId);
            fd.append('result', result);
            if (wqModalMode === 'served'){
                fd.append('action','update_status');
                fd.append('status','served');
            } else {
                fd.append('action','save_result');
            }
            fetch('doctor_walkin_queue.php', {method:'POST', body:fd})
            .then(r=>r.text())
            .then(res=>{
                hideLoading();
                if(res.trim()==='success'){
                    showToast('Result saved successfully!','success');
                    const m = document.getElementById('walkinResultModal');
                    m.classList.remove('active');
                    document.body.style.overflow = '';
                    wqModalId = null;
                    wqModalMode = 'edit';
                    wqPending = true;
                    refreshQueue();
                } else {
                    showToast(res,'error');
                }
            })
            .catch(()=>{ hideLoading(); showToast('Network error','error'); });
        }
        document.getElementById('walkinResultModal').addEventListener('click', function(e){
            if(e.target === this) closeWalkinResultModal();
        });
        setInterval(()=>{ if(!document.hidden) refreshQueue(); }, 20000);
        document.addEventListener('visibilitychange', ()=>{ if(!document.hidden) refreshQueue(); });

        /* -- LustreMDC Smooth Transitions -- */
        (function(){
          const s=document.createElement('style');
          s.textContent=`
            @keyframes __meIn  {from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
            @keyframes __meFade {from{opacity:0}to{opacity:1}}
            @keyframes __meFadeOut {from{opacity:1}to{opacity:0}}
            body{animation:__meFade .4s ease both}
            body.__leaving{animation:__meFadeOut .22s ease forwards;pointer-events:none}
            button:not(:disabled):active{transform:scale(.965) !important}
            .content-card{animation:__meIn .42s cubic-bezier(.22,1,.36,1) both}
            .stat-card{animation:__meIn .38s cubic-bezier(.22,1,.36,1) both}
            tr{transition:background .15s}
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