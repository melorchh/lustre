<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
require_once __DIR__ . '/../src/db.php';
$admin_name = $_SESSION["admin_name"];

// Handle status update
if ((($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') && ($_POST['action'] ?? '') === 'update_status') {
    $wid    = isset($_POST['walkin_id']) ? intval($_POST['walkin_id']) : 0;
    $status = trim($_POST['status'] ?? '');
    $allowed = ['waiting','in_service','served','cancelled'];
    if ($wid <= 0) { echo 'Invalid walk-in.'; $conn->close(); exit; }
    if (!in_array($status, $allowed)) { echo 'Invalid status.'; $conn->close(); exit; }
    $upd = $conn->prepare("UPDATE walk_ins SET status = ? WHERE id = ?");
    $upd->bind_param("si", $status, $wid);
    if ($upd->execute()) { echo 'success'; } else { echo 'Update failed.'; }
    $upd->close();
    $conn->close();
    exit;
}

// Selected date (default today)
$sel_date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Stats for the day
$day_total       = $conn->query("SELECT COUNT(*) c FROM walk_ins WHERE arrival_date = '$sel_date'")->fetch_assoc()['c'];
$day_waiting     = $conn->query("SELECT COUNT(*) c FROM walk_ins WHERE arrival_date = '$sel_date' AND status='waiting'")->fetch_assoc()['c'];
$day_in_service  = $conn->query("SELECT COUNT(*) c FROM walk_ins WHERE arrival_date = '$sel_date' AND status='in_service'")->fetch_assoc()['c'];
$day_served      = $conn->query("SELECT COUNT(*) c FROM walk_ins WHERE arrival_date = '$sel_date' AND status='served'")->fetch_assoc()['c'];
$day_cancelled   = $conn->query("SELECT COUNT(*) c FROM walk_ins WHERE arrival_date = '$sel_date' AND status='cancelled'")->fetch_assoc()['c'];

$conn->query("ALTER TABLE walk_ins ADD COLUMN IF NOT EXISTS test_procedure TEXT NOT NULL DEFAULT ''");
$walkins = $conn->query("
    SELECT w.id, w.arrival_time, w.status AS walk_status, w.created_at, w.test_procedure,
           p.name AS patient_name, p.patient_type, p.contact,
           d.id AS doctor_id, d.name AS doctor_name, d.specialty
    FROM walk_ins w
    JOIN patients p ON w.patient_id = p.id
    JOIN doctors  d ON w.doctor_id  = d.id
    WHERE w.arrival_date = '$sel_date'
    ORDER BY d.name ASC, w.arrival_time ASC, w.created_at ASC, w.id ASC
");
$conn->close();

// Assign dynamic queue number per doctor/date
$queues = [];
$rows = [];
$doctors_ordered = [];
if ($walkins) {
    while ($w = $walkins->fetch_assoc()) {
        $qkey = $w['doctor_id'];
        if (!isset($queues[$qkey])) $queues[$qkey] = 0;
        $queues[$qkey]++;
        $w['queue'] = $queues[$qkey];
        if (!isset($doctors_ordered[$qkey])) $doctors_ordered[$qkey] = $w['doctor_name'] . '|' . $w['specialty'];
        $rows[] = $w;
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
    <script>try{if(localStorage.getItem('meTheme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}</script>
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/../../admin.css') ?>">
    <style>
        .walkin-date-filter{width:180px;min-width:180px}
        .walkin-date-filter .admin-datepicker{width:100%}
        .walkin-date-filter .admin-dp-trigger{padding:.55rem .8rem;min-height:42px;font-size:.88rem;width:100%}
        @media (max-width:720px){.walkin-date-filter{width:100%;min-width:0;margin-top:.4rem}}
    </style>
    <title>Walk-ins - LustreMDC Admin</title>
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
            <a href="admin_dashboard.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><span class="rail-label">Dashboard</span></a>
            <a href="admin_appointments.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span><span class="rail-label">Appointments</span></a>
            <a href="admin_patients.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="rail-label">Patients</span></a>
                <a href="admin_walkins.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.6 4.7L18 9.3l-4.4 1.6L12 15.6l-1.6-4.7L6 9.3l4.4-1.6z" style="stroke-width:1.8"/><path d="M5 20h14"/></svg></span><span class="rail-label">Walk-ins</span></a>
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
                <span class="topbar-title">Walk-ins</span>
            </div>
            <div class="topbar-actions">
    <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong><?php echo htmlspecialchars(explode(' ', trim($admin_name))[0]); ?></strong></div>
                <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($admin_name),0,1)); ?></div>
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo $day_total; ?></div><div class="stat-label">Total</div></div>
            <div class="stat-card" style="border-top-color:#f59e0b"><div class="stat-number" style="color:#f59e0b"><?php echo $day_waiting; ?></div><div class="stat-label">Waiting</div></div>
            <div class="stat-card" style="border-top-color:#0ea5e9"><div class="stat-number" style="color:#0ea5e9"><?php echo $day_in_service; ?></div><div class="stat-label">In Service</div></div>
            <div class="stat-card" style="border-top-color:#10b981"><div class="stat-number" style="color:#10b981"><?php echo $day_served; ?></div><div class="stat-label">Served</div></div>
            <div class="stat-card" style="border-top-color:#ef4444"><div class="stat-number" style="color:#ef4444"><?php echo $day_cancelled; ?></div><div class="stat-label">Cancelled</div></div>
        </div>

        <div class="content-card">
            <div class="patients-header">
                <div>
                    <h2 style="margin-bottom:4px;">Walk-ins Queue</h2>
                    <small style="color:#64748b">Dynamic queue number per doctor for the selected date.</small>
                </div>
                <form method="get" action="admin_walkins.php" class="walkin-date-filter" id="walkinDateForm">
                    <input type="date" name="date" value="<?php echo $sel_date; ?>" max="<?php echo date('Y-m-d'); ?>" data-datepicker id="walkinDateInput">
                </form>
            </div>

            <?php if ($walkins && count($rows) > 0): ?>
                <?php foreach ($doctors_ordered as $did => $docinfo):
                    [$doc_name, $doc_spec] = explode('|', $docinfo, 2);
                ?>
                <div class="walkin-doctor-group" style="margin-top:18px">
                    <h3 style="margin:0 0 8px;font-family:var(--font-heading,'Roboto',sans-serif);color:#0f172a;">Dr. <?php echo htmlspecialchars($doc_name); ?> <small style="color:#64748b;font-weight:400">(<?php echo htmlspecialchars($doc_spec); ?>)</small></h3>
                    <div class="table-scroll">
                        <table>
                            <thead><tr><th>Queue</th><th>Patient</th><th>Type</th><th>Arrival</th><th>Test / Procedure</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $w): if ((string)$w['doctor_id'] !== (string)$did) continue;
                                $ws = $w['walk_status'];
                                $wcolors = ['waiting'=>'background:#fef3c7;color:#92400e','in_service'=>'background:#dbeafe;color:#1e40af','served'=>'background:#d1fae5;color:#065f46','cancelled'=>'background:#fee2e2;color:#991b1b'];
                                $wbc = $wcolors[$ws] ?? '';
                                $wt = new DateTime($w['arrival_time']);
                            ?>
                            <tr>
                                <td><span class="badge" style="background:#16a34a;color:#fff;font-size:1rem;font-weight:800">#<?php echo $w['queue']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($w['patient_name']); ?></strong><br><small style="color:#64748b"><?php echo htmlspecialchars($w['contact']); ?></small></td>
                                <td><?php echo htmlspecialchars(ucfirst($w['patient_type'] ?: 'adult')); ?></td>
                                <td><?php echo $wt->format('g:i A'); ?></td>
                                <td><?php echo htmlspecialchars($w['test_procedure'] ?: '—'); ?></td>
                                <td><span class="badge" style="<?php echo $wbc; ?>"><?php echo ucfirst($ws); ?></span></td>
                                <td>
                                    <select class="action-select" onchange="updateWalkinStatus(<?php echo $w['id']; ?>, this.value)">
                                        <?php foreach(['waiting','in_service','served','cancelled'] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo $ws===$opt?'selected':''; ?>><?php echo ucfirst($opt); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.6 4.7L18 9.3l-4.4 1.6L12 15.6l-1.6-4.7L6 9.3l4.4-1.6z" style="stroke-width:1.8"/><path d="M5 20h14"/></svg></div>No walk-ins registered for this date. <a href="admin_dashboard.php" style="color:#16a34a">Register a walk-in</a></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<div id="loadingScreen" class="loading-screen"><div class="spinner"></div><p>Updating...</p></div>
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
    function showToast(msg,type=''){try{if(!window.__noFlash){clearTimeout(window.__flashT);localStorage.setItem('lustreAdminFlash',JSON.stringify({m:String(msg),t:type||''}));window.__flashT=setTimeout(function(){try{localStorage.removeItem('lustreAdminFlash')}catch(e){}},2500);}}catch(e){}const t=document.getElementById('toast');t.textContent=msg;t.className='toast show'+(type?' '+type:'');setTimeout(()=>t.className='toast',3000);}
    function showLoading(){document.getElementById('loadingScreen').classList.add('active');}
    function hideLoading(){document.getElementById('loadingScreen').classList.remove('active');}
    function updateWalkinStatus(id,status){
        showLoading();const fd=new FormData();
        fd.append('action','update_status');
        fd.append('walkin_id',id);
        fd.append('status',status);
        fetch('admin_walkins.php',{method:'POST',body:fd})
        .then(r=>r.text()).then(res=>{hideLoading();
        if(res.trim()==='success')showToast('Status updated!','success');
        else showToast(res,'error');})
        .catch(()=>{hideLoading();showToast('Network error','error');});
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
<script src="admin_datepicker.js?v=<?= filemtime(__DIR__ . '/../../admin_datepicker.js') ?>"></script>
<script>
(function(){
    var inp = document.getElementById('walkinDateInput');
    if(!inp) return;
    var lastVal = inp.value;
    inp.addEventListener('change', function(){
        if(inp.value === lastVal) return;
        lastVal = inp.value;
        var form = document.getElementById('walkinDateForm');
        if(form) form.submit();
    });
})();
</script>
<script src="admin_components.js?v=<?= filemtime(__DIR__ . '/../../admin_components.js') ?>"></script>
<script src="admin_theme.js?v=<?= filemtime(__DIR__ . '/../../admin_theme.js') ?>"></script>
<script>try{var __af=JSON.parse(localStorage.getItem('lustreAdminFlash')||'null');if(__af&&__af.m){localStorage.removeItem('lustreAdminFlash');window.__noFlash=1;showToast(__af.m,__af.t);}}catch(e){}</script>
</body>
</html>
