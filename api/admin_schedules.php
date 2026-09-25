<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
include __DIR__ . '/../toddcare-backend/src/db.php';
$admin_name = $_SESSION["admin_name"];

$schedules = $conn->query("SELECT ds.*, d.name as doctor_name, d.specialty FROM doctor_schedules ds JOIN doctors d ON ds.doctor_id=d.id ORDER BY d.name, array_position(ARRAY['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'], ds.day_of_week)");
$doctors_list = $conn->query("SELECT id, name, specialty FROM doctors ORDER BY name");
$time_blocks = $conn->query("SELECT tb.*, d.name as doctor_name FROM doctor_time_blocks tb JOIN doctors d ON tb.doctor_id=d.id ORDER BY tb.created_at DESC, d.name");
$doctor_options = '';
while ($doc = $doctors_list->fetch_assoc()) {
    $doctor_options .= '<option value="' . (int)$doc['id'] . '">Dr. ' . htmlspecialchars($doc['name']) . ' - ' . htmlspecialchars($doc['specialty']) . '</option>';
}
$time_options = '';
for ($h = 6; $h <= 22; $h++) {
    foreach (['00', '30'] as $min) {
        $v = sprintf('%02d:%s', $h, $min);
        $time_options .= '<option value="' . $v . '">' . date('g:i A', strtotime($v)) . '</option>';
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
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/../admin.css') ?>">
    <title>Schedule Management - LustreMDC Admin</title>
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
                <a href="admin_walkins.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.6 4.7L18 9.3l-4.4 1.6L12 15.6l-1.6-4.7L6 9.3l4.4-1.6z" style="stroke-width:1.8"/><path d="M5 20h14"/></svg></span><span class="rail-label">Walk-ins</span></a>
                <a href="admin_doctors.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span><span class="rail-label">Doctors</span></a>
                <a href="admin_schedules.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span class="rail-label">Schedules</span></a>
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
                    <span class="topbar-title">Schedules</span>
                </div>
                <div class="topbar-actions">
    <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                    <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong><?php echo htmlspecialchars(explode(' ', trim($admin_name))[0]); ?></strong></div>
                    <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($admin_name),0,1)); ?></div>
                </div>
            </header>
            
<div class="content-card">
    <h2>Add New Schedule</h2>
    <form id="addScheduleForm">
        <div class="form-grid">
            <div class="form-group">
                <label>Doctor</label>
                <select name="doctor_id" class="form-dropdown" required>
                    <option value="">Select Doctor</option>
                    <?php echo $doctor_options; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Available From (Day)</label>
                <select name="start_day" id="startDay" class="form-dropdown" required>
                    <option value="">Select Start Day</option>
                    <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day): ?>
                    <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>To (Day)</label>
                <select name="end_day" id="endDay" class="form-dropdown" required>
                    <option value="">Select End Day</option>
                    <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day): ?>
                    <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Start Time</label>
                <select name="start_time" class="form-dropdown" required>
                    <option value="">Select Start Time</option>
                    <?php echo $time_options; ?>
                </select>
            </div>
            <div class="form-group">
                <label>End Time</label>
                <select name="end_time" class="form-dropdown" required>
                    <option value="">Select End Time</option>
                    <?php echo $time_options; ?>
                </select>
            </div>
        </div>
        <p id="scheduleRangeHint" class="form-hint" style="display:none;margin:.5rem 0 .9rem;font-weight:600;color:var(--green);"></p>
        <button type="submit" class="btn-primary">Add Schedule</button>
    </form>
</div>
<div class="content-card">
    <h2>All Schedules</h2>
    <div class="table-scroll">
        <table>
            <thead><tr><th>ID</th><th>Doctor</th><th>Specialty</th><th>Day</th><th>Start</th><th>End</th><th>Action</th></tr></thead>
            <tbody>
            <?php while ($schedule = $schedules->fetch_assoc()):
                $start = new DateTime($schedule['start_time']);
                $end   = new DateTime($schedule['end_time']);
            ?>
            <tr>
                <td><?php echo $schedule['id']; ?></td>
                <td><strong>Dr. <?php echo htmlspecialchars($schedule['doctor_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($schedule['specialty']); ?></td>
                <td><?php echo $schedule['day_of_week']; ?></td>
                <td><?php echo $start->format('g:i A'); ?></td>
                <td><?php echo $end->format('g:i A'); ?></td>
                <td><button class="btn-sm btn-delete" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)">Delete</button></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="content-card">
    <h2>Unavailable Times (Time Off)</h2>
    <p style="color:#64748b;margin-bottom:1.25rem;font-size:0.93rem;">
        Block specific times when a doctor is unavailable (e.g. leaves at 2PM and returns at 5PM). Patients won't be able to book those slots.
    </p>
    <form id="addBlockForm">
        <div class="form-grid">
            <div class="form-group">
                <label>Doctor</label>
                <select name="doctor_id" id="blockDoctor" class="form-dropdown" required>
                    <option value="">Select Doctor</option>
                    <?php echo $doctor_options; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Block Type</label>
                <select name="block_type" id="blockType" class="form-dropdown" required>
                    <option value="date">Specific date</option>
                    <option value="weekly">Every week (day of week)</option>
                </select>
            </div>
            <div class="form-group" id="blockDateGroup">
                <label>Date</label>
                <input type="date" name="block_date" id="blockDate" data-datepicker min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group" id="blockDayGroup" style="display:none;">
                <label>Day of Week</label>
                <select name="day_of_week" id="blockDay" class="form-dropdown">
                    <option value="">Select Day</option>
                    <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
                    <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Start Time</label>
                <select name="start_time" class="form-dropdown" required>
                    <option value="">Select Start Time</option>
                    <?php echo $time_options; ?>
                </select>
            </div>
            <div class="form-group">
                <label>End Time</label>
                <select name="end_time" class="form-dropdown" required>
                    <option value="">Select End Time</option>
                    <?php echo $time_options; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Reason (optional)</label>
                <input type="text" name="reason" placeholder="e.g. Out 2PM-5PM" maxlength="255">
            </div>
        </div>
        <button type="submit" class="btn-primary">Add Time Block</button>
    </form>
</div>
<div class="content-card">
    <h2>Current Time Blocks</h2>
    <div class="table-scroll">
        <table>
            <thead><tr><th>ID</th><th>Doctor</th><th>Type</th><th>Date / Day</th><th>Start</th><th>End</th><th>Reason</th><th>Action</th></tr></thead>
            <tbody>
            <?php if ($time_blocks && $time_blocks->num_rows > 0): ?>
                <?php while ($tb = $time_blocks->fetch_assoc()):
                    $tb_start = new DateTime($tb['start_time']);
                    $tb_end   = new DateTime($tb['end_time']);
                    $when = $tb['block_type'] === 'weekly'
                        ? 'Every ' . $tb['day_of_week']
                        : date('M j, Y', strtotime($tb['block_date']));
                ?>
                <tr>
                    <td><?php echo $tb['id']; ?></td>
                    <td><strong>Dr. <?php echo htmlspecialchars($tb['doctor_name']); ?></strong></td>
                    <td><?php echo $tb['block_type'] === 'weekly' ? 'Weekly' : 'Date'; ?></td>
                    <td><?php echo htmlspecialchars($when); ?></td>
                    <td><?php echo $tb_start->format('g:i A'); ?></td>
                    <td><?php echo $tb_end->format('g:i A'); ?></td>
                    <td><?php echo htmlspecialchars($tb['reason'] ?? ''); ?></td>
                    <td><button class="btn-sm btn-delete" onclick="deleteBlock(<?php echo $tb['id']; ?>)">Remove</button></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align:center;color:#94a3b8;">No time blocks yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
        </main>
    </div>
    <div id="loadingScreen" class="loading-screen"><div class="spinner"></div><p>Processing...</p></div>
    <div id="toast" class="toast"></div>
    <div class="confirm-overlay" id="confirmOverlay">
        <div class="confirm-box">
            <div class="confirm-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></div>
            <h2>Confirm Deletion</h2>
            <p id="confirmMsg">Delete this record? This cannot be undone.</p>
            <div class="confirm-actions">
                <button type="button" class="btn-secondary" id="confirmCancel" onclick="confirmCancel()">Cancel</button>
                <button type="button" class="btn-danger" id="confirmOk">Yes, Delete</button>
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

        /* Confirm modal (replaces native confirm) */
        let __confirmCb = null;
        function confirmAction(msg, cb){
            document.getElementById('confirmMsg').textContent = msg || 'Delete this record? This cannot be undone.';
            __confirmCb = cb;
            document.getElementById('confirmOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
            const cf = document.getElementById('confirmCancel');
            if (cf) cf.focus();
        }
        function confirmCancel(){
            document.getElementById('confirmOverlay').classList.remove('active');
            document.body.style.overflow = '';
            __confirmCb = null;
        }
        document.getElementById('confirmOk').addEventListener('click', function(){
            const cb = __confirmCb;
            confirmCancel();
            if(cb) cb();
        });
        document.getElementById('confirmOverlay').addEventListener('click', function(e){
            if(e.target === this) confirmCancel();
        });
        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape') confirmCancel();
        });
        
        let isDeleting = false;
        document.getElementById('addScheduleForm').addEventListener('submit',function(e){
            e.preventDefault();showLoading();
            fetch('admin_schedule_action.php?action=add',{method:'POST',body:new FormData(this)})
            .then(r=>r.text()).then(result=>{hideLoading();if(result.trim()==='success'){showToast('Schedule added!','success');document.body.classList.add('__leaving');setTimeout(()=>location.reload(),215);}else{showToast(result,'error');}})
            .catch(()=>{hideLoading();showToast('Error','error');});
        });
        function deleteSchedule(id){
            if(isDeleting)return;
            confirmAction('Delete this schedule? This cannot be undone.', function(){
                isDeleting=true;showLoading();
                fetch('admin_schedule_action.php?action=delete',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'schedule_id='+id})
                .then(r=>r.text()).then(result=>{hideLoading();isDeleting=false;if(result.trim()==='success'){showToast('Deleted!','success');document.body.classList.add('__leaving');setTimeout(()=>location.reload(),215);}else{showToast(result,'error');}})
                .catch(()=>{hideLoading();isDeleting=false;showToast('Error','error');});
            });
        }

        // -- Add Schedule: day-range (From -> To) --
        var dayOrder = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        var startDayEl = document.getElementById('startDay');
        var endDayEl = document.getElementById('endDay');
        var rangeHint = document.getElementById('scheduleRangeHint');
        function updateRangeHint(){
            var s = startDayEl.value, e = endDayEl.value;
            if (!s || !e) { rangeHint.style.display = 'none'; return; }
            var si = dayOrder.indexOf(s), ei = dayOrder.indexOf(e);
            if (ei < si) { endDayEl.value = s; e = s; ei = si; }
            var days = dayOrder.slice(si, ei + 1);
            rangeHint.textContent = 'Doctor available: ' + (days.length > 1 ? s + ' to ' + e + ' (' + days.length + ' days)' : s);
            rangeHint.style.display = '';
        }
        startDayEl.addEventListener('change', updateRangeHint);
        endDayEl.addEventListener('change', updateRangeHint);
        document.addEventListener('DOMContentLoaded', updateRangeHint);

        // -- Unavailable Time Blocks --
        function toggleBlockType(){
            const type = document.getElementById('blockType').value;
            document.getElementById('blockDateGroup').style.display = (type === 'date') ? '' : 'none';
            document.getElementById('blockDayGroup').style.display = (type === 'weekly') ? '' : 'none';
            document.getElementById('blockDate').required = (type === 'date');
            document.getElementById('blockDay').required = (type === 'weekly');
        }
        document.getElementById('blockType').addEventListener('change', toggleBlockType);
        toggleBlockType();

        document.getElementById('addBlockForm').addEventListener('submit',function(e){
            e.preventDefault(); showLoading();
            fetch('admin_schedule_action.php?action=add_block',{method:'POST',body:new FormData(this)})
            .then(r=>r.text()).then(result=>{hideLoading();if(result.trim()==='success'){showToast('Time block added!','success');document.body.classList.add('__leaving');setTimeout(()=>location.reload(),215);}else{showToast(result,'error');}})
            .catch(()=>{hideLoading();showToast('Error','error');});
        });

        function deleteBlock(id){
            if(isDeleting)return;
            confirmAction('Remove this time block?', function(){
                isDeleting=true;showLoading();
                fetch('admin_schedule_action.php?action=delete_block',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'block_id='+id})
                .then(r=>r.text()).then(result=>{hideLoading();isDeleting=false;if(result.trim()==='success'){showToast('Removed!','success');document.body.classList.add('__leaving');setTimeout(()=>location.reload(),215);}else{showToast(result,'error');}})
                .catch(()=>{hideLoading();isDeleting=false;showToast('Error','error');});
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
<script src="admin_datepicker.js?v=<?= filemtime(__DIR__ . '/../admin_datepicker.js') ?>"></script>
<script src="admin_components.js?v=<?= filemtime(__DIR__ . '/../admin_components.js') ?>"></script>
<script src="admin_theme.js?v=<?= filemtime(__DIR__ . '/../admin_theme.js') ?>"></script>
</body>
</html>