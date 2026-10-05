<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_id"])) { header("Location: doctor_login.php"); exit; }
require_once __DIR__ . '/../src/db.php';

$doctor_id   = (int)$_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'];

// Appointments assigned to THIS doctor
$appointments = $conn->query("
    SELECT a.*,
           p.name AS patient_name, p.email AS patient_email
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = $doctor_id
    ORDER BY
        CASE
            WHEN a.status = 'pending' THEN 1
            WHEN a.status = 'confirmed' THEN 2
            WHEN a.status = 'completed' THEN 3
            WHEN a.status = 'cancelled' THEN 4
        END,
        a.appointment_date DESC,
        a.appointment_time DESC
");

$total_appts   = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id = $doctor_id")->fetch_assoc()['c'];
$confirmed_appts = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id = $doctor_id AND status='confirmed'")->fetch_assoc()['c'];
$completed_appts = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id = $doctor_id AND status='completed'")->fetch_assoc()['c'];
$pending_results = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id = $doctor_id AND status='completed' AND (result IS NULL OR result = '')")->fetch_assoc()['c'];

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
<style>body{font-family:'Source Sans 3',system-ui,sans-serif}</style>
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/../../admin.css') ?>">
    <link rel="stylesheet" href="doctor.css?v=<?= filemtime(__DIR__ . '/../../doctor.css') ?>">
    <title>Appointments - Doctor Portal</title>
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
            <a href="doctor_dashboard.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><span class="rail-label">Dashboard</span></a>
            <a href="doctor_appointments.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 18l3 3 3-3"/></svg></span><span class="rail-label">Appointments</span></a>
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
                <span class="topbar-title">Appointments</span>
            </div>
            <div class="topbar-actions">
                <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong>Dr. <?php echo htmlspecialchars(explode(' ', trim($doctor_name))[0]); ?></strong></div>
                <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($doctor_name),0,1)); ?></div>
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_appts; ?></div>
                <div class="stat-label">Total Appointments</div>
            </div>
            <div class="stat-card" style="border-top-color:#3b82f6">
                <div class="stat-number" style="color:#3b82f6"><?php echo $confirmed_appts; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card" style="border-top-color:#10b981">
                <div class="stat-number" style="color:#10b981"><?php echo $completed_appts; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card" style="border-top-color:#f59e0b">
                <div class="stat-number" style="color:#f59e0b"><?php echo $pending_results; ?></div>
                <div class="stat-label">Pending Results</div>
            </div>
        </div>

        <div class="content-card">
            <h2>My Appointments</h2>
            <div class="search-filter-bar">
                <input type="search" id="searchInput" placeholder="Search by patient name or service...">
                <select id="statusFilter" class="filter-select" onchange="filterTable()">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="table-scroll">
                <table id="apptTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Date &amp; Time</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Result</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($appointments && $appointments->num_rows > 0): ?>
                    <?php while ($ap = $appointments->fetch_assoc()):
                        $s = $ap['status'];
                        $has_result = !empty($ap['result']);
                        $date_fmt = (new DateTime($ap['appointment_date']))->format('M j, Y');
                        $time_fmt = date('g:i A', strtotime($ap['appointment_time']));
                        $status_badge = [
                            'pending'    => '#fef3c7;color:#92400e',
                            'confirmed'  => '#d1fae5;color:#065f46',
                            'completed'  => '#e2e8f0;color:#334155',
                            'cancelled'  => '#fee2e2;color:#991b1b',
                        ][$s] ?? '';
                    ?>
                    <tr data-status="<?php echo $s; ?>">
                        <td>#<?php echo $ap['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($ap['patient_name']); ?></strong><br>
                            <small style="color:#64748b"><?php echo htmlspecialchars($ap['patient_email']); ?></small>
                        </td>
                        <td><?php echo $date_fmt; ?><br><small style="color:#94a3b8"><?php echo $time_fmt; ?></small></td>
                        <td><?php echo htmlspecialchars($ap['service_requested'] ?: 'General consultation'); ?></td>
                        <td><span class="badge" style="background:<?php echo $status_badge; ?>"><?php echo ucfirst($s); ?></span></td>
                        <td>
                            <?php if ($has_result): ?>
                                <span class="badge badge-completed" style="cursor:pointer" onclick="openResultModal(<?php echo $ap['id']; ?>, '<?php echo addslashes(htmlspecialchars($ap['patient_name'])); ?>', '<?php echo addslashes(htmlspecialchars($ap['result'] ?? '')); ?>', true)">Ready</span>
                            <?php else: ?>
                                <span style="color:#94a3b8;font-size:.82rem">No result</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap">
                            <?php if (in_array($s, ['pending', 'confirmed'])): ?>
                                <button class="btn-sm btn-edit" onclick="openResultModal(<?php echo $ap['id']; ?>, '<?php echo addslashes(htmlspecialchars($ap['patient_name'])); ?>', '', false)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> Complete &amp; Result
                                </button>
                            <?php elseif ($s === 'completed' && !$has_result): ?>
                                <button class="btn-sm btn-edit" onclick="openResultModal(<?php echo $ap['id']; ?>, '<?php echo addslashes(htmlspecialchars($ap['patient_name'])); ?>', '', false, true)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> Add Result
                                </button>
                            <?php elseif ($s === 'completed' && $has_result): ?>
                                <button class="btn-sm btn-edit" onclick="openResultModal(<?php echo $ap['id']; ?>, '<?php echo addslashes(htmlspecialchars($ap['patient_name'])); ?>', '<?php echo addslashes(htmlspecialchars($ap['result'] ?? '')); ?>', false, true)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> Edit Result
                                </button>
                            <?php else: ?>
                                <button class="btn-sm" disabled style="opacity:.5;cursor:not-allowed">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> No action
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="7" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>No appointments assigned to you yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Result Entry Modal -->
<div class="result-modal" id="resultModal">
    <div class="result-modal-box">
        <button class="result-close" onclick="closeResultModal()">&#10005;</button>
        <h3 id="modalTitle">Appointment Result</h3>

        <div class="info-row" id="modalInfoRow"></div>

        <div class="form-group">
            <label>Appointment Result / Findings</label>
            <textarea id="modalResult" rows="8" placeholder="Enter the appointment result, diagnosis, findings, and notes here..."></textarea>
        </div>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <button class="btn-primary" onclick="saveResult()" id="saveResultBtn"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Save Result</button>
            <button class="btn-secondary" onclick="closeResultModal()">Cancel</button>
        </div>
    </div>
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

    function showToast(msg,type=''){const t=document.getElementById('toast');t.textContent=msg;t.className='toast show'+(type?' '+type:'');setTimeout(()=>t.className='toast',3500);}
    function showLoading(){document.getElementById('loadingScreen').classList.add('active');}
    function hideLoading(){document.getElementById('loadingScreen').classList.remove('active');}

    let currentApptId = null;
    let isCompleteOnly = false;
    function openResultModal(id, patientName, result, isEditOnly, completedNoResult){
        currentApptId = id;
        isCompleteOnly = !!completedNoResult;
        document.getElementById('modalTitle').textContent = isCompleteOnly ? 'Appointment Result' : 'Complete Appointment &amp; Enter Result';
        document.getElementById('modalTitle').innerHTML = isCompleteOnly ? 'Appointment Result' : 'Complete & Enter Result';
        document.getElementById('modalInfoRow').innerHTML = `
            <div class="info-item"><label>Patient</label><span>${patientName}</span></div>
            <div class="info-item"><label>Appt ID</label><span>#${id}</span></div>`;
        document.getElementById('modalResult').value = result || '';
        document.getElementById('resultModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeResultModal(){
        document.getElementById('resultModal').classList.remove('active');
        document.body.style.overflow = '';
        currentApptId = null;
    }
    function saveResult(){
        if(!currentApptId) return;
        const result = document.getElementById('modalResult').value.trim();
        if(!result){ showToast('Please enter the appointment result','error'); return; }
        showLoading();
        const fd = new FormData();
        fd.append('appointment_id', currentApptId);
        fd.append('result', result);
        const action = isCompleteOnly ? 'save_result' : 'complete_and_result';
        fetch('doctor_result_action.php?action=' + action, { method:'POST', body:fd })
        .then(r=>r.text()).then(res=>{
            hideLoading();
            if(res.trim()==='success'){
                showToast('Result saved successfully!','success');
                closeResultModal();
                document.body.classList.add('__leaving');
                setTimeout(()=>location.reload(),215);
            }
            else showToast(res,'error');
        }).catch(()=>{ hideLoading(); showToast('Error','error'); });
    }

    document.getElementById('resultModal').addEventListener('click', function(e){
        if(e.target === this) closeResultModal();
    });

    function filterTable(){
        const search = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        document.querySelectorAll('#apptTable tbody tr').forEach(row=>{
            const text = row.textContent.toLowerCase();
            const rowStatus = row.dataset.status || '';
            const matchText = text.includes(search);
            const matchStatus = !status || rowStatus === status;
            row.style.display = (matchText && matchStatus) ? '' : 'none';
        });
    }
    document.getElementById('searchInput').addEventListener('input', filterTable);

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
        .content-card{animation:__meIn .42s cubic-bezier(.22,1,.36,1) both;animation-delay:.1s;opacity:0;animation-fill-mode:both}
        .stat-card{opacity:0;animation:__meIn .38s cubic-bezier(.22,1,.36,1) both;animation-fill-mode:both}
        tr{transition:background .12s}
        .result-modal-box{animation:__meIn .3s cubic-bezier(.34,1.56,.64,1)}
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
