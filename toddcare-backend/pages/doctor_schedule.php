<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["doctor_id"])) { header("Location: doctor_login.php"); exit; }
require_once __DIR__ . '/../src/db.php';

$doctor_id   = (int)$_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'];

$schedules = $conn->query("SELECT * FROM doctor_schedules WHERE doctor_id = $doctor_id ORDER BY array_position(ARRAY['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'], day_of_week)");
$time_blocks = $conn->query("SELECT * FROM doctor_time_blocks WHERE doctor_id = $doctor_id ORDER BY created_at DESC");

$sched_by_day = [];
while ($s = $schedules->fetch_assoc()) { $sched_by_day[$s['day_of_week']] = $s; }

$time_options = '';
for ($h = 6; $h <= 22; $h++) {
    foreach (['00', '30'] as $min) {
        $v = sprintf('%02d:%s', $h, $min);
        $time_options .= '<option value="' . $v . '">' . date('g:i A', strtotime($v)) . '</option>';
    }
}
$conn->close();
function me_time($t){ return (new DateTime($t))->format('g:i A'); }
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
    <title>Availability - Doctor Portal</title>
    <style>
        .day-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem; }
        .day-card { background:var(--white); border:1.5px solid var(--gray-100); border-radius:14px; padding:1.1rem 1.2rem; display:flex; flex-direction:column; gap:.85rem; transition:box-shadow .2s,border-color .2s; }
        .day-card.active-ok { border-top:3px solid var(--green); }
        .day-card.inactive { opacity:.72; }
        .day-card-head { display:flex; align-items:center; justify-content:space-between; }
        .day-card-head h3 { margin:0; font-size:1rem; font-family:'Lora',serif; }
        .switch { position:relative; width:42px; height:24px; flex-shrink:0; }
        .switch input { opacity:0; width:0; height:0; }
        .switch .slider { position:absolute; inset:0; background:#cbd5e1; border-radius:24px; cursor:pointer; transition:.2s; }
        .switch .slider::before { content:''; position:absolute; width:18px; height:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
        .switch input:checked + .slider { background:var(--green); }
        .switch input:checked + .slider::before { transform:translateX(18px); }
        .day-time { display:flex; align-items:center; gap:.5rem; font-size:.95rem; }
        .badge-notset { color:#94a3b8; font-size:.85rem; font-weight:600; letter-spacing:.02em; }
    </style>
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
                <a href="doctor_laboratory.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg></span><span class="rail-label">Laboratory</span></a>
                <a href="doctor_schedule.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span class="rail-label">Availability</span></a>
            </nav>
            <div class="sidebar-footer">
                <a href="doctor_logout.php" class="btn-logout" data-tip="Sign Out"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span><span class="rail-label">Sign Out</span></a>
            </div>
        </aside>

        <main class="admin-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Open menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                    <span class="topbar-title">Availability</span>
                </div>
                <div class="topbar-actions">
    <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                    <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong>Dr. <?php echo htmlspecialchars(explode(' ', trim($doctor_name))[0]); ?></strong></div>
                    <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($doctor_name),0,1)); ?></div>
                </div>
            </header>

            <div class="content-card">
                <h2>My Weekly Availability</h2>
                <p style="color:#64748b;margin-bottom:1.25rem;font-size:0.93rem;">
                    Set which days you are available and toggle each day on or off. Patients can only book on days that are switched on.
                </p>
                <div class="day-grid">
                <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day):
                    $sched = $sched_by_day[$day] ?? null;
                    $has = $sched !== null;
                    $active = $has && (int)$sched['is_active'] === 1;
                ?>
                    <div class="day-card <?php echo $has ? ($active ? 'active-ok' : 'inactive') : ''; ?>">
                        <div class="day-card-head">
                            <h3><?php echo $day; ?></h3>
                            <?php if ($has): ?>
                            <label class="switch" title="Toggle availability">
                                <input type="checkbox" <?php echo $active ? 'checked' : ''; ?> onchange="toggleDay(<?php echo (int)$sched['id']; ?>, this.checked)">
                                <span class="slider"></span>
                            </label>
                            <?php endif; ?>
                        </div>
                        <?php if ($has): ?>
                            <div class="day-time">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span><?php echo me_time($sched['start_time']); ?> &ndash; <?php echo me_time($sched['end_time']); ?></span>
                            </div>
                            <div style="display:flex;gap:.5rem">
                                <button class="btn-sm btn-edit" onclick="openEditModal(<?php echo (int)$sched['id']; ?>, '<?php echo $day; ?>', '<?php echo $sched['start_time']; ?>', '<?php echo $sched['end_time']; ?>')">Edit</button>
                                <button class="btn-sm btn-delete" onclick="deleteSchedule(<?php echo (int)$sched['id']; ?>)">Remove</button>
                            </div>
                        <?php else: ?>
                            <span class="badge-notset">Not set &mdash; unavailable</span>
                            <button class="btn-sm btn-edit" onclick="openAddModal('<?php echo $day; ?>')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Set Availability</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>

            <div class="content-card">
                <h2>Time Off (Blocked Hours)</h2>
                <p style="color:#64748b;margin-bottom:1.25rem;font-size:0.93rem;">
                    Block specific times within an available day when you are busy (e.g. out 2PM&ndash;5PM). Patients won't be able to book those slots.
                </p>
                <form id="addBlockForm">
                    <div class="form-grid">
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
                                <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
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
                <h2>My Time Blocks</h2>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>ID</th><th>Type</th><th>Date / Day</th><th>Start</th><th>End</th><th>Reason</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if ($time_blocks && $time_blocks->num_rows > 0): ?>
                            <?php while ($tb = $time_blocks->fetch_assoc()):
                                $when = $tb['block_type'] === 'weekly'
                                    ? 'Every ' . $tb['day_of_week']
                                    : date('M j, Y', strtotime($tb['block_date']));
                            ?>
                            <tr>
                                <td><?php echo $tb['id']; ?></td>
                                <td><?php echo $tb['block_type'] === 'weekly' ? 'Weekly' : 'Date'; ?></td>
                                <td><?php echo htmlspecialchars($when); ?></td>
                                <td><?php echo me_time($tb['start_time']); ?></td>
                                <td><?php echo me_time($tb['end_time']); ?></td>
                                <td><?php echo htmlspecialchars($tb['reason'] ?? ''); ?></td>
                                <td><button class="btn-sm btn-delete" onclick="deleteBlock(<?php echo $tb['id']; ?>)">Remove</button></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;color:#94a3b8;">No time blocks.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Availability Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box">
            <button class="modal-close-btn" onclick="closeModal('addModal')">&times;</button>
            <h2>Set Availability</h2>
            <p id="addDayLabel" style="color:#64748b;margin-bottom:1.25rem;font-size:0.95rem;"></p>
            <form id="addForm">
                <input type="hidden" name="day_of_week" id="addDay">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Start Time</label>
                        <select name="start_time" class="form-dropdown" required>
                            <option value="">Select Start</option>
                            <?php echo $time_options; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <select name="end_time" class="form-dropdown" required>
                            <option value="">Select End</option>
                            <?php echo $time_options; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Availability Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <button class="modal-close-btn" onclick="closeModal('editModal')">&times;</button>
            <h2>Edit Availability</h2>
            <p id="editDayLabel" style="color:#64748b;margin-bottom:1.25rem;font-size:0.95rem;"></p>
            <form id="editForm">
                <input type="hidden" name="schedule_id" id="editScheduleId">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Start Time</label>
                        <select name="start_time" id="editStart" class="form-dropdown" required>
                            <option value="">Select Start</option>
                            <?php echo $time_options; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <select name="end_time" id="editEnd" class="form-dropdown" required>
                            <option value="">Select End</option>
                            <?php echo $time_options; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="loadingScreen" class="loading-screen"><div class="spinner"></div><p>Processing...</p></div>
    <div id="toast" class="toast"></div>
    <div class="confirm-overlay" id="confirmOverlay">
        <div class="confirm-box">
            <div class="confirm-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></div>
            <h2>Confirm Removal</h2>
            <p id="confirmMsg">Delete this record? This cannot be undone.</p>
            <div class="confirm-actions">
                <button type="button" class="btn-secondary" id="confirmCancel" onclick="confirmCancel()">Cancel</button>
                <button type="button" class="btn-danger" id="confirmOk">Yes, Remove</button>
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
        function openModal(id){document.getElementById(id).classList.add('active');document.body.style.overflow='hidden';}
        function closeModal(id){document.getElementById(id).classList.remove('active');document.body.style.overflow='';}

        let __confirmCb = null;
        function confirmAction(msg, cb){
            document.getElementById('confirmMsg').textContent = msg || 'Remove this record?';
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

        function openAddModal(day){
            document.getElementById('addDay').value = day;
            document.getElementById('addDayLabel').textContent = 'Set your availability for ' + day + '.';
            document.getElementById('addForm').reset();
            openModal('addModal');
        }
        document.getElementById('addForm').addEventListener('submit', function(e){
            e.preventDefault(); showLoading();
            fetch('doctor_schedule_action.php?action=add',{method:'POST',body:new FormData(this)})
            .then(r=>r.text()).then(res=>{hideLoading();if(res.trim()==='success'){showToast('Availability added!','success');closeModal('addModal');document.body.classList.add('__leaving');setTimeout(()=>location.reload(),215);}else{showToast(res,'error');}})
            .catch(()=>{hideLoading();showToast('Error','error');});
        });

        function openEditModal(id, day, start, end){
            document.getElementById('editScheduleId').value = id;
            document.getElementById('editDayLabel').textContent = 'Edit availability for ' + day + '.';
            document.getElementById('editStart').value = start;
            document.getElementById('editEnd').value = end;
            openModal('editModal');
        }
        document.getElementById('editForm').addEventListener('submit', function(e){
            e.preventDefault(); showLoading();
            fetch('doctor_schedule_action.php?action=edit',{method:'POST',body:new FormData(this)})
            .then(r=>r.text()).then(res=>{hideLoading();if(res.trim()==='success'){showToast('Availability updated!','success');closeModal('editModal');document.body.classList.add('__leaving');setTimeout(()=>location.reload(),215);}else{showToast(res,'error');}})
            .catch(()=>{hideLoading();showToast('Error','error');});
        });

        function toggleDay(id, checked){
            const fd = new FormData();
            fd.append('schedule_id', id);
            fd.append('is_active', checked ? '1' : '0');
            fetch('doctor_schedule_action.php?action=toggle_active',{method:'POST',body:fd})
            .then(r=>r.text()).then(res=>{
                if(res.trim()==='success'){ showToast('Availability updated!','success'); }
                else { showToast(res,'error'); }
            })
            .catch(()=>{showToast('Error','error');});
        }

        let isDeleting = false;
        function deleteSchedule(id){
            if(isDeleting)return;
            confirmAction('Remove this weekly availability?', function(){
                isDeleting=true;showLoading();
                fetch('doctor_schedule_action.php?action=delete',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'schedule_id='+id})
                .then(r=>r.text()).then(res=>{hideLoading();isDeleting=false;if(res.trim()==='success'){showToast('Removed!','success');document.body.classList.add('__leaving');setTimeout(()=>location.reload(),215);}else{showToast(res,'error');}})
                .catch(()=>{hideLoading();isDeleting=false;showToast('Error','error');});
            });
        }

        function deleteBlock(id){
            if(isDeleting)return;
            confirmAction('Remove this time block?', function(){
                isDeleting=true;showLoading();
                fetch('doctor_schedule_action.php?action=delete_block',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'block_id='+id})
                .then(r=>r.text()).then(res=>{hideLoading();isDeleting=false;if(res.trim()==='success'){showToast('Removed!','success');document.body.classList.add('__leaving');setTimeout(()=>location.reload(),215);}else{showToast(res,'error');}})
                .catch(()=>{hideLoading();isDeleting=false;showToast('Error','error');});
            });
        }

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
            fetch('doctor_schedule_action.php?action=add_block',{method:'POST',body:new FormData(this)})
            .then(r=>r.text()).then(res=>{hideLoading();if(res.trim()==='success'){showToast('Time block added!','success');document.body.classList.add('__leaving');setTimeout(()=>location.reload(),215);}else{showToast(res,'error');}})
            .catch(()=>{hideLoading();showToast('Error','error');});
        });

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
            tr{transition:background .15s}
            .modal-box{animation:__meIn .3s cubic-bezier(.34,1.56,.64,1)}
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
</body>
</html>