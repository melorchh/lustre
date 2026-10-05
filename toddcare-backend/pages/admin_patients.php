<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
require_once __DIR__ . '/../src/db.php';
$admin_name = $_SESSION["admin_name"];

$patients = $conn->query("SELECT p.id, p.name, p.email, p.contact, p.address, p.is_walk_in, p.weight_kg, p.height_cm, p.patient_type, p.age, p.created_at, COUNT(a.id) as appointment_count, MAX(a.appointment_date) as last_appointment FROM patients p LEFT JOIN appointments a ON p.id=a.patient_id GROUP BY p.id, p.name, p.email, p.contact, p.address, p.is_walk_in, p.weight_kg, p.height_cm, p.patient_type, p.age, p.created_at ORDER BY p.created_at DESC");
if (!$patients) {
    die("Query failed: " . $conn->error);
}

$walkin_doc_options = '';
$wl_doc = $conn->query("SELECT id, name, specialty FROM doctors ORDER BY name");
if ($wl_doc) {
    while ($wd = $wl_doc->fetch_assoc()) {
        $walkin_doc_options .= '<option value="' . (int)$wd['id'] . '">Dr. ' . htmlspecialchars($wd['name']) . ' (' . htmlspecialchars($wd['specialty']) . ')</option>';
    }
}
$conn->close();

?><!DOCTYPE html>
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
    <title>Patient Management - LustreMDC Admin</title>
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
                <a href="admin_patients.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="rail-label">Patients</span></a>
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
                    <span class="topbar-title">Patients</span>
                </div>
                <div class="topbar-actions">
    <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                    <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong><?php echo htmlspecialchars(explode(' ', trim($admin_name))[0]); ?></strong></div>
                    <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($admin_name),0,1)); ?></div>
                </div>
            </header>
            
<div class="content-card">
    <div class="patients-header">
        <h2 style="margin-bottom:0;">All Patients</h2>
        <button class="btn-walkin" onclick="openWalkinModal()">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Register Walk-In Client</span>
        </button>
    </div>
    <div class="search-box" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
        <input type="search" id="searchName" placeholder="Search by name..." oninput="filterPatients()">
        <input type="search" id="searchAddr" placeholder="Search by address..." oninput="filterPatients()">
        <input type="search" id="searchAge" placeholder="Age..." oninput="filterPatients()" class="search-age">
    </div>
    <div class="table-scroll">
        <table id="patientsTable">
            <thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Patient</th><th>Age</th><th>Weight</th><th>Height</th><th>Contact</th><th>Address</th><th>Appointments</th><th>Last Visit</th><th>Registered</th></tr></thead>
       <tbody>
<?php while ($p = $patients->fetch_assoc()): 
    $created = !empty($p['created_at']) ? new DateTime($p['created_at']) : null;
    $last_apt = !empty($p['last_appointment']) ? new DateTime($p['last_appointment']) : null;
?>
<tr data-name="<?php echo htmlspecialchars(strtolower($p['name']), ENT_QUOTES); ?>" data-address="<?php echo htmlspecialchars(strtolower($p['address']), ENT_QUOTES); ?>" data-age="<?php echo (int)$p['age']; ?>">
    <td><?php echo htmlspecialchars($p['id']); ?></td>
    <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
    <td><?php if (!empty($p['is_walk_in'])): ?><span class="badge badge-urgent">Walk-In</span><?php else: ?><span class="badge badge-active">Registered</span><?php endif; ?></td>
    <td><?php echo $p['patient_type'] ? '<span class="badge badge-normal">' . htmlspecialchars(ucfirst($p['patient_type'])) . '</span>' : '&mdash;'; ?></td>
    <td><?php echo $p['age'] !== null ? $p['age'] . ' yrs' : '&mdash;'; ?></td>
    <td><?php echo $p['weight_kg'] !== null ? $p['weight_kg'] . ' kg' : '&mdash;'; ?></td>
    <td><?php echo $p['height_cm'] !== null ? $p['height_cm'] . ' cm' : '&mdash;'; ?></td>
    <td><?php echo htmlspecialchars($p['contact']); ?></td>
    <td><?php echo htmlspecialchars($p['address']); ?></td>
    <td><span class="badge badge-active"><?php echo $p['appointment_count']; ?></span></td>
    <td><?php echo $last_apt ? $last_apt->format('M j, Y') : 'Never'; ?></td>
    <td><?php echo $created ? $created->format('M j, Y') : '&mdash;'; ?></td>
</tr>
<?php endwhile; ?>
<?php if ($patients->num_rows === 0): ?>
<tr><td colspan="12" class="empty-state-row"><div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>No patients found. New registrations will appear here.</td></tr>
<?php endif; ?>
</tbody>
        </table>
    </div>
</div>
        </main>
    </div>

<!-- Walk-In Client Modal -->
<div class="modal-overlay" id="walkinModal">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeModal('walkinModal')">&times;</button>
        <h2>Register Walk-In Client</h2>
        <form id="walkinForm">
            <div class="form-grid">
                <div class="form-group">
                    <label>Doctor *</label>
                    <select name="doctor_id" class="form-dropdown" required>
                        <option value="">Select Doctor</option>
                        <?php echo $walkin_doc_options; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Arrival Time</label>
                    <input type="time" name="arrival_time" value="<?php echo date('H:i'); ?>">
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
            <div class="form-group">
                <label>Email <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
                <input type="email" name="email" placeholder="Auto-generated if left blank">
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('walkinModal')">Cancel</button>
                <button type="submit" class="btn-primary">Register Client</button>
            </div>
        </form>
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
        function showToast(msg,type=''){const t=document.getElementById('toast');t.textContent=msg;t.className='toast show'+(type?' '+type:'');setTimeout(()=>t.className='toast',3000);}
        function showLoading(){document.getElementById('loadingScreen').classList.add('active');}
        function hideLoading(){document.getElementById('loadingScreen').classList.remove('active');}
        function openModal(id){document.getElementById(id).classList.add('active');document.body.style.overflow='hidden';}
        function closeModal(id){document.getElementById(id).classList.remove('active');document.body.style.overflow='';}
        function openWalkinModal(){document.getElementById('walkinForm').reset();openModal('walkinModal');}

        document.querySelectorAll('.modal-overlay').forEach(function(overlay){
            overlay.addEventListener('click',function(e){if(e.target===this)closeModal(this.id);});
        });

        document.getElementById('walkinForm').addEventListener('submit',function(e){
            e.preventDefault();
            showLoading();
            var fd=new FormData(this);
            fd.append('action','add_walkin');
            fetch('admin_walkin_action.php',{method:'POST',body:fd})
                .then(function(r){return r.text();})
                .then(function(res){
                    hideLoading();
                    var t=res.trim();
                    if(t.indexOf('success')===0){showToast('Walk-in client registered!','success');closeModal('walkinModal');reloadPage();}
                    else showToast(t,'error');
                })
                .catch(function(){hideLoading();showToast('Network error','error');});
        });

        function reloadPage(){document.body.classList.add('__leaving');setTimeout(function(){window.location.href='admin_patients.php';},215);}
        function filterPatients(){
            const name=document.getElementById('searchName').value.toLowerCase().trim();
            const addr=document.getElementById('searchAddr').value.toLowerCase().trim();
            const age=document.getElementById('searchAge').value.trim();
            document.querySelectorAll('#patientsTable tbody tr[data-name]').forEach(r=>{
                const nameOk=!name||(r.dataset.name||'').indexOf(name)!==-1;
                const addrOk=!addr||(r.dataset.address||'').indexOf(addr)!==-1;
                const ageOk=!age||String(r.dataset.age||'').indexOf(age)!==-1;
                r.style.display=(nameOk&&addrOk&&ageOk)?'':'none';
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
        .content-card{animation:__meIn .42s cubic-bezier(.22,1,.36,1) both}
        .stat-card{animation:__meIn .38s cubic-bezier(.22,1,.36,1) both}
        tr{transition:background .15s}
        .modal-box{animation:__meIn .3s cubic-bezier(.34,1.56,.64,1)}
        .result-modal-box{animation:__meIn .3s cubic-bezier(.34,1.56,.64,1)}
        .doctor-card{transition:transform .2s,box-shadow .22s !important}
        .doctor-card:hover{transform:translateY(-3px) !important}
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
<?php $conn->close(); ?>