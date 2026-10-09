<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_id"])) { header("Location: doctor_login.php"); exit; }
require_once __DIR__ . '/../src/db.php';

$doctor_id   = (int)$_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'];

// Lab tests assigned to THIS doctor
$lab_tests = $conn->query("
    SELECT lt.*,
           p.name AS patient_name, p.email AS patient_email,
           a.appointment_date, a.appointment_time
    FROM lab_tests lt
    JOIN patients p ON lt.patient_id = p.id
    LEFT JOIN appointments a ON lt.appointment_id = a.id
    WHERE lt.doctor_id = $doctor_id
    ORDER BY lt.created_at DESC
");

$total_tests   = $conn->query("SELECT COUNT(*) c FROM lab_tests WHERE doctor_id = $doctor_id")->fetch_assoc()['c'];
$pending_tests = $conn->query("SELECT COUNT(*) c FROM lab_tests WHERE doctor_id = $doctor_id AND status='pending'")->fetch_assoc()['c'];
$processing    = $conn->query("SELECT COUNT(*) c FROM lab_tests WHERE doctor_id = $doctor_id AND status='processing'")->fetch_assoc()['c'];
$completed     = $conn->query("SELECT COUNT(*) c FROM lab_tests WHERE doctor_id = $doctor_id AND status='completed'")->fetch_assoc()['c'];

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
    <link rel="stylesheet" href="doctor.css?v=<?= filemtime(__DIR__ . '/../../doctor.css') ?>">
    <title>Laboratory - Doctor Portal</title>
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
            <a href="doctor_appointments.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 18l3 3 3-3"/></svg></span><span class="rail-label">Appointments</span></a>
            <a href="doctor_laboratory.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg></span><span class="rail-label">Laboratory</span></a>
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
                <span class="topbar-title">Laboratory</span>
            </div>
            <div class="topbar-actions">
                <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong>Dr. <?php echo htmlspecialchars(explode(' ', trim($doctor_name))[0]); ?></strong></div>
                <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($doctor_name),0,1)); ?></div>
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_tests; ?></div>
                <div class="stat-label">Total Tests</div>
            </div>
            <div class="stat-card" style="border-top-color:#f59e0b">
                <div class="stat-number" style="color:#f59e0b"><?php echo $pending_tests; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card" style="border-top-color:#3b82f6">
                <div class="stat-number" style="color:#3b82f6"><?php echo $processing; ?></div>
                <div class="stat-label">Processing</div>
            </div>
            <div class="stat-card" style="border-top-color:#10b981">
                <div class="stat-number" style="color:#10b981"><?php echo $completed; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <div class="content-card">
            <h2>Lab Tests Assigned to Me</h2>
            <div class="search-filter-bar">
                <input type="search" id="searchInput" placeholder="Search by patient or test type...">
                <select id="statusFilter" class="filter-select" onchange="filterTable()">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="table-scroll">
                <table id="labTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Test Type</th>
                            <th>Priority</th>
                            <th>Scheduled</th>
                            <th>Status</th>
                            <th>Result</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($lab_tests && $lab_tests->num_rows > 0): ?>
                    <?php while ($lt = $lab_tests->fetch_assoc()):
                        $s = $lt['status'];
                        $pr = $lt['priority'];
                        $sched = $lt['scheduled_date'] ? (new DateTime($lt['scheduled_date']))->format('M j, Y') : '&mdash;';
                        $created = (new DateTime($lt['created_at']))->format('M j, Y');
                        $status_badge = [
                            'pending'    => 'badge-pending',
                            'processing' => 'badge-processing',
                            'completed'  => 'badge-completed',
                            'cancelled'  => 'badge-cancelled',
                        ][$s] ?? 'badge-pending';
                        $pr_badge = [
                            'normal' => 'priority-normal',
                            'urgent' => 'priority-urgent',
                            'stat'   => 'priority-stat',
                        ][$pr] ?? 'priority-normal';
                        $has_result = !empty($lt['result']);
                    ?>
                    <tr data-status="<?php echo $s; ?>" data-priority="<?php echo $pr; ?>">
                        <td>#<?php echo $lt['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($lt['patient_name']); ?></strong><br>
                            <small style="color:#64748b"><?php echo htmlspecialchars($lt['patient_email']); ?></small>
                        </td>
                        <td><strong><?php echo htmlspecialchars($lt['test_type']); ?></strong></td>
                        <td><span class="badge <?php echo $pr_badge; ?>"><?php echo strtoupper($pr); ?></span></td>
                        <td><?php echo $sched; ?><br><small style="color:#94a3b8">Req: <?php echo $created; ?></small></td>
                        <td>
                            <select class="action-select" onchange="updateStatus(<?php echo $lt['id']; ?>, this.value)">
                                <?php foreach (['pending','processing','completed'] as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo $s===$opt?'selected':''; ?>><?php echo ucfirst($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <?php if ($has_result): ?>
                                <span class="badge badge-completed" style="cursor:pointer" onclick="openResultModal(<?php echo $lt['id']; ?>, '<?php echo addslashes(htmlspecialchars($lt['test_type'])); ?>', '<?php echo addslashes(htmlspecialchars($lt['patient_name'])); ?>', '<?php echo addslashes(htmlspecialchars($lt['result'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($lt['notes'] ?? '')); ?>', '<?php echo $s; ?>')">Ready</span>
                            <?php else: ?>
                                <span style="color:#94a3b8;font-size:.82rem">No result</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap">
                            <button class="btn-sm btn-edit" onclick="openResultModal(<?php echo $lt['id']; ?>, '<?php echo addslashes(htmlspecialchars($lt['test_type'])); ?>', '<?php echo addslashes(htmlspecialchars($lt['patient_name'])); ?>', '<?php echo addslashes(htmlspecialchars($lt['result'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($lt['notes'] ?? '')); ?>', '<?php echo $s; ?>')">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> Result
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="8" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg></div>No lab tests assigned to you yet.</td></tr>
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
        <h3 id="modalTitle">Enter Lab Result</h3>

        <div class="info-row" id="modalInfoRow"></div>

        <div class="form-group">
            <label>Status</label>
            <select id="modalStatus" class="action-select" style="max-width:200px">
                <option value="pending">Pending</option>
                <option value="processing">Processing</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <div class="form-group">
            <label>Lab Result / Findings</label>
            <textarea id="modalResult" rows="7" placeholder="Enter the lab result, values, and interpretation here..."></textarea>
        </div>

        <div class="form-group">
            <label>Clinical Notes</label>
            <textarea id="modalNotes" rows="3" placeholder="Additional notes or recommendations..."></textarea>
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

    function showToast(msg,type=''){try{if(!window.__noFlash){clearTimeout(window.__flashT);localStorage.setItem('lustreAdminFlash',JSON.stringify({m:String(msg),t:type||''}));window.__flashT=setTimeout(function(){try{localStorage.removeItem('lustreAdminFlash')}catch(e){}},2500);}}catch(e){}const t=document.getElementById('toast');t.textContent=msg;t.className='toast show'+(type?' '+type:'');setTimeout(()=>t.className='toast',3500);}
    function showLoading(){document.getElementById('loadingScreen').classList.add('active');}
    function hideLoading(){document.getElementById('loadingScreen').classList.remove('active');}

    function updateStatus(id, status){
        showLoading();
        const fd = new FormData();
        fd.append('lab_id', id);
        fd.append('status', status);
        fetch('doctor_lab_action.php?action=update_status', { method:'POST', body:fd })
        .then(r=>r.text()).then(res=>{
            hideLoading();
            if(res.trim()==='success'){ showToast('Status updated!','success'); }
            else showToast(res,'error');
        }).catch(()=>{ hideLoading(); showToast('Error','error'); });
    }

    let currentLabId = null;
    function openResultModal(id, testType, patientName, result, notes, status){
        currentLabId = id;
        document.getElementById('modalTitle').textContent = testType;
        document.getElementById('modalInfoRow').innerHTML = `
            <div class="info-item"><label>Patient</label><span>${patientName}</span></div>
            <div class="info-item"><label>Test ID</label><span>#${id}</span></div>`;
        document.getElementById('modalStatus').value = status;
        document.getElementById('modalResult').value = result || '';
        document.getElementById('modalNotes').value  = notes || '';
        document.getElementById('resultModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeResultModal(){
        document.getElementById('resultModal').classList.remove('active');
        document.body.style.overflow = '';
        currentLabId = null;
    }
    function saveResult(){
        if(!currentLabId) return;
        const result = document.getElementById('modalResult').value.trim();
        const notes  = document.getElementById('modalNotes').value.trim();
        const status = document.getElementById('modalStatus').value;
        showLoading();
        const fd = new FormData();
        fd.append('lab_id', currentLabId);
        fd.append('result', result);
        fd.append('notes',  notes);
        fd.append('status', status);
        fetch('doctor_lab_action.php?action=save_result', { method:'POST', body:fd })
        .then(r=>r.text()).then(res=>{
            hideLoading();
            if(res.trim()==='success'){ showToast('Result saved!','success'); closeResultModal(); document.body.classList.add('__leaving');setTimeout(()=>location.reload(),215); }
            else showToast(res,'error');
        }).catch(()=>{ hideLoading(); showToast('Error','error'); });
    }

    document.getElementById('resultModal').addEventListener('click', function(e){
        if(e.target === this) closeResultModal();
    });

    function filterTable(){
        const search = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        document.querySelectorAll('#labTable tbody tr').forEach(row=>{
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
<script>try{var __af=JSON.parse(localStorage.getItem('lustreAdminFlash')||'null');if(__af&&__af.m){localStorage.removeItem('lustreAdminFlash');window.__noFlash=1;showToast(__af.m,__af.t);}}catch(e){}</script>
</body>
</html>