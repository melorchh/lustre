<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
include __DIR__ . '/../toddcare-backend/src/db.php';
$admin_name = $_SESSION["admin_name"];

// Stats
$total_appointments = $conn->query("SELECT COUNT(*) c FROM appointments")->fetch_assoc()['c'];
$pending_count      = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='pending'")->fetch_assoc()['c'];
$confirmed_count    = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='confirmed'")->fetch_assoc()['c'];
$completed_count    = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='completed'")->fetch_assoc()['c'];
$today_count        = $conn->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date=CURRENT_DATE")->fetch_assoc()['c'];

// All appointments
$appointments = $conn->query("
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.payment_status,
           a.is_urgent, a.reschedule_count, a.updated_at, a.appointment_duration,
           a.service_requested,
           p.name AS patient_name, p.email AS patient_email,
           p.address AS patient_address, p.age AS patient_age,
           d.name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN doctors  d ON a.doctor_id  = d.id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");

// Reschedule history, grouped by appointment (most recent first)
$history_map = [];
$hq = $conn->query("SELECT appointment_id, old_date, old_time, new_date, new_time, rescheduled_at FROM reschedule_history ORDER BY rescheduled_at DESC, id DESC");
if ($hq) {
    while ($h = $hq->fetch_assoc()) {
        $history_map[$h['appointment_id']][] = $h;
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600;700&family=Source+Sans+3:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <script>try{if(localStorage.getItem('meTheme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}</script>
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/../admin.css') ?>">
    <title>Appointments - LustreMDC Admin</title>
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
            <a href="admin_appointments.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span><span class="rail-label">Appointments</span></a>
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

    <!-- -- Main content -- -->
    <main class="admin-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Open menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <span class="topbar-title">Appointments</span>
            </div>
            <div class="topbar-actions">
    <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong><?php echo htmlspecialchars(explode(' ', trim($admin_name))[0]); ?></strong></div>
                <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($admin_name),0,1)); ?></div>
            </div>
        </header>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo $total_appointments; ?></div><div class="stat-label">Total</div></div>
            <div class="stat-card" style="border-top-color:#f59e0b"><div class="stat-number" style="color:#f59e0b"><?php echo $pending_count; ?></div><div class="stat-label">Pending</div></div>
            <div class="stat-card" style="border-top-color:#10b981"><div class="stat-number" style="color:#10b981"><?php echo $confirmed_count; ?></div><div class="stat-label">Confirmed</div></div>
            <div class="stat-card" style="border-top-color:#0ea5e9"><div class="stat-number" style="color:#0ea5e9"><?php echo $completed_count; ?></div><div class="stat-label">Completed</div></div>
            <div class="stat-card" style="border-top-color:#8b5cf6"><div class="stat-number" style="color:#8b5cf6"><?php echo $today_count; ?></div><div class="stat-label">Today</div></div>
        </div>

        <!-- Appointments table -->
        <div class="content-card">
            <h2>All Appointments</h2>

            <!-- Filter Toolbar -->
            <div class="apt-toolbar">
                <input type="search" id="aptSearch" class="apt-search" placeholder="Search patient name..." oninput="filterApt()">
                <input type="search" id="aptAddrSearch" class="apt-search" placeholder="Search address..." oninput="filterApt()">
                <input type="search" id="aptAgeSearch" class="apt-search apt-search--age" placeholder="Age..." oninput="filterApt()">
                <select id="aptStatusFilter" class="apt-select filter-select" onchange="filterApt()">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select id="aptPriorityFilter" class="apt-select filter-select" onchange="filterApt()">
                    <option value="">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="normal">Normal</option>
                </select>
            </div>

            <div class="table-scroll">
                <table id="aptTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Service / Test</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($appointments && $appointments->num_rows > 0):
                        while ($apt = $appointments->fetch_assoc()):
                            $s     = $apt['status'];
                            $ps    = $apt['payment_status'];
                            $urgent = $apt['is_urgent'];
                            $prio  = !empty($urgent) ? 'urgent' : 'normal';
                            $date  = new DateTime($apt['appointment_date']);
                            $time  = new DateTime($apt['appointment_time']);
                            $updated = new DateTime($apt['updated_at']);
                    ?>
                    <tr data-status="<?php echo $s; ?>" data-priority="<?php echo $prio; ?>" data-name="<?php echo htmlspecialchars(strtolower($apt['patient_name']), ENT_QUOTES); ?>" data-address="<?php echo htmlspecialchars(strtolower($apt['patient_address'] ?? ''), ENT_QUOTES); ?>" data-age="<?php echo (int)$apt['patient_age']; ?>">
                        <td>#<?php echo $apt['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong><br>
                            <small style="color:#64748b"><?php echo htmlspecialchars($apt['patient_email']); ?></small>
                        </td>
                        <td>
                            Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?><br>
                            <small style="color:#64748b"><?php echo htmlspecialchars($apt['specialty']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($apt['service_requested'] ?: '—'); ?></td>
                        <td><?php echo $date->format('M j, Y'); ?></td>
                        <td><?php echo $time->format('g:i A'); ?></td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <select class="action-select" onchange="updateStatus(<?php echo $apt['id']; ?>, this.value)">
                                    <?php foreach(['pending','confirmed','completed','cancelled'] as $opt): ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $s===$opt?'selected':''; ?>><?php echo ucfirst($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php $hist = !empty($history_map[$apt['id']]) ? $history_map[$apt['id']] : []; ?>
                                <?php if (!empty($apt['reschedule_count']) || !empty($hist)): ?>
                                <span class="resched-tip">
                                    <span class="resched-badge">&#10227; Rescheduled<?php echo $apt['reschedule_count'] > 1 ? ' x' . (int)$apt['reschedule_count'] : ''; ?></span>
                                    <span class="resched-pop">
                                        <?php if (count($hist) > 0): ?>
                                            <span class="resched-pop-title">Reschedule History</span>
                                            <?php foreach ($hist as $e):
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
                            </div>
                        </td>
                        <td>
                            <select class="action-select" onchange="updatePayment(<?php echo $apt['id']; ?>, this.value)">
                                <option value="pending" <?php echo $ps==='pending'?'selected':''; ?>>Unpaid</option>
                                <option value="paid" <?php echo $ps==='paid'?'selected':''; ?>>Paid</option>
                            </select>
                        </td>
                        <td><?php echo $updated->format('M j, Y'); ?><br><small style="color:#94a3b8"><?php echo $updated->format('g:i A'); ?></small>
                            <div style="margin-top:6px">
                                <button type="button" class="btn-slip" title="Print appointment slip" onclick="openSlip(<?php echo htmlspecialchars(json_encode([
                                    'patient' => $apt['patient_name'],
                                    'doctor'  => $apt['doctor_name'],
                                    'specialty' => $apt['specialty'],
                                    'service' => $apt['service_requested'],
                                    'date'    => $date->format('F j, Y'),
                                    'time'    => $time->format('g:i A'),
                                    'status'  => ucfirst($s),
                                    'payment' => ucfirst($ps),
                                ])); ?>)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg> Slip
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="9" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>No appointments found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
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
    </main>
</div>

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
    function showToast(msg,type=''){const t=document.getElementById('toast');t.textContent=msg;t.className='toast show'+(type?' '+type:'');setTimeout(()=>t.className='toast',3200);}

    function showLoading(){document.getElementById('loadingScreen').classList.add('active');}
    function hideLoading(){document.getElementById('loadingScreen').classList.remove('active');}
    function updateStatus(id,status){
        showLoading();const fd=new FormData();
        fd.append('appointment_id',id);
        fd.append('status',status);
        fetch('admin_update_appointment.php',{method:'POST',body:fd})
        .then(r=>r.text()).then(res=>{hideLoading();
        if(res.trim()==='success')showToast('Status updated!','success');
        else{showToast(res,'error');}})
        .catch(()=>{hideLoading();showToast('Error','error');});
    }
    function updatePayment(id,payment){
        showLoading();const fd=new FormData();
        fd.append('appointment_id',id);
        fd.append('payment_status',payment);
        fetch('admin_update_appointment.php',{method:'POST',body:fd}).then(r=>r.text()).then(res=>{hideLoading();
        if(res.trim()==='success')showToast('Payment updated!','success');
        else{showToast(res,'error');}}).catch(()=>{hideLoading();showToast('Error','error');});
    }

    function filterApt(){
        const name=document.getElementById('aptSearch').value.toLowerCase().trim();
        const address=document.getElementById('aptAddrSearch').value.toLowerCase().trim();
        const age=document.getElementById('aptAgeSearch').value.trim();
        const status=document.getElementById('aptStatusFilter').value;
        const priority=document.getElementById('aptPriorityFilter').value;
        document.querySelectorAll('#aptTable tbody tr[data-status]').forEach(row=>{
            const rName=row.dataset.name||'';
            const rAddr=row.dataset.address||'';
            const rAge=row.dataset.age||'';
            const nameOk=!name||rName.indexOf(name)!==-1;
            const addrOk=!address||rAddr.indexOf(address)!==-1;
            const ageOk=!age||String(rAge).indexOf(age)!==-1;
            const ok=nameOk&&addrOk&&ageOk&&(!status||row.dataset.status===status)&&(!priority||row.dataset.priority===priority);
            row.style.display=ok?'':'none';
        });
    }

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
            '<div class="slip-row"><span>Service:</span><strong>'+(d.service?d.service:'—')+'</strong></div>'+
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
            '</style></head><body><div class="wrap">'+
            '<div class="head"><div class="name">LustreMDC</div><div class="sub">CLINIC APPOINTMENT SLIP</div></div>'+
            slip.innerHTML+
            '<div class="foot">Please arrive 10-15 minutes early. Show this slip at the counter.</div>'+
            '</div><script>window.onload=function(){window.print();}<\/script></body></html>');
        w.document.close();
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
<script src="admin_components.js?v=<?= filemtime(__DIR__ . '/../admin_components.js') ?>"></script>
<script src="admin_theme.js?v=<?= filemtime(__DIR__ . '/../admin_theme.js') ?>"></script>
</body>
</html>
