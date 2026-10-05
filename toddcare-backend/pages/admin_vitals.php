<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
require_once __DIR__ . '/../src/db.php';
$admin_name = $_SESSION["admin_name"];

$patients = $conn->query("SELECT id, name, email FROM patients ORDER BY name");
$doctors = $conn->query("SELECT id, name, specialty FROM doctors ORDER BY name");
$appt_slots = [];
for ($t = strtotime('08:00'); $t < strtotime('17:00'); $t += 1800) {
    $appt_slots[] = date('H:i', $t);
}

$selected_id = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;
$selected_name = '';
$vitals_list = null;
$vacc_list = null;
$summary = ['weight' => '&mdash;', 'bp' => '&mdash;', 'visit' => '&mdash;', 'next_due' => '&mdash;'];

if ($patients && $patients->num_rows > 0) {
    if ($selected_id <= 0) {
        $first = $patients->fetch_assoc();
        $selected_id = (int)$first['id'];
        $selected_name = $first['name'];
        $patients->data_seek(0);
    } else {
        $byId = $conn->query("SELECT name FROM patients WHERE id=$selected_id");
        if ($byId && $r = $byId->fetch_assoc()) $selected_name = $r['name'];
        $patients->data_seek(0);
    }

    $vitals_list = $conn->query("SELECT * FROM vitals WHERE patient_id=$selected_id ORDER BY visit_date DESC");
    $vacc_list   = $conn->query("SELECT * FROM vaccinations WHERE patient_id=$selected_id ORDER BY administered_date DESC");

    $last = $conn->query("SELECT weight_kg, blood_pressure, visit_date FROM vitals WHERE patient_id=$selected_id ORDER BY visit_date DESC LIMIT 1")->fetch_assoc();
    if ($last) {
        $summary['weight'] = $last['weight_kg'] ? $last['weight_kg'] . ' kg' : '&mdash;';
        $summary['bp']     = $last['blood_pressure'] ? $last['blood_pressure'] : '&mdash;';
        $summary['visit']  = (new DateTime($last['visit_date']))->format('M j, Y');
    }
    $next = $conn->query("SELECT vaccine_name, dose_label, next_due_date FROM vaccinations WHERE patient_id=$selected_id AND next_due_date IS NOT NULL AND next_due_date >= CURRENT_DATE ORDER BY next_due_date ASC LIMIT 1")->fetch_assoc();
    if ($next) {
        $summary['next_due'] = $next['vaccine_name'] . ' ' . $next['dose_label'] . ' &mdash; ' . (new DateTime($next['next_due_date']))->format('M j, Y');
    } else {
        $summary['next_due'] = 'None scheduled';
    }
} else {
    $selected_id = 0;
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
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/../../admin.css') ?>">
    <title>Vitals &amp; Vaccines &mdash; LustreMDC Admin</title>
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
            <a href="admin_schedules.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span class="rail-label">Schedules</span></a>
            <a href="admin_laboratory.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/><path d="M7 15h10"/></svg></span><span class="rail-label">Laboratory</span></a>
            <a href="admin_vitals.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span><span class="rail-label">Vitals &amp; Vaccines</span></a>
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
                    <span class="topbar-title">Vitals &amp; Vaccines</span>
                </div>
                <div class="topbar-actions">
    <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                    <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong><?php echo htmlspecialchars(explode(' ', trim($admin_name))[0]); ?></strong></div>
                    <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($admin_name),0,1)); ?></div>
                </div>
            </header>

        <?php if ($selected_id > 0): ?>
        <div class="patient-picker">
            <label for="patientSelect">Patient:</label>
            <select id="patientSelect" class="form-dropdown" onchange="location.href='admin_vitals.php?patient='+this.value">
                <?php
                $patients->data_seek(0);
                while ($p = $patients->fetch_assoc()):
                    $selP = (int)$p['id'] === $selected_id ? 'selected' : '';
                ?>
                <option value="<?php echo $p['id']; ?>" <?php echo $selP; ?>><?php echo htmlspecialchars($p['name']); ?> &mdash; <?php echo htmlspecialchars($p['email']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="summary-chips">
            <div class="summary-chip"><label>Patient</label><strong><?php echo htmlspecialchars($selected_name); ?></strong></div>
            <div class="summary-chip"><label>Last Visit</label><strong><?php echo $summary['visit']; ?></strong></div>
            <div class="summary-chip"><label>Latest Weight</label><strong><?php echo $summary['weight']; ?></strong></div>
            <div class="summary-chip"><label>Latest BP</label><strong><?php echo $summary['bp']; ?></strong></div>
            <div class="summary-chip"><label>Next Vaccine Due</label><strong style="font-size:1rem"><?php echo $summary['next_due']; ?></strong></div>
        </div>

        <div class="content-card">
            <div class="section-head">
                <h2>Vitals Log</h2>
                <button class="btn-add" onclick="openVitalModal()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Visit</button>
            </div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Visit Date</th><th>Weight</th><th>Height</th><th>Blood Pressure</th><th>Heart Rate</th><th>Fundal Height</th><th>Notes</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($vitals_list && $vitals_list->num_rows > 0):
                        while ($v = $vitals_list->fetch_assoc()):
                            $d = new DateTime($v['visit_date']);
                    ?>
                        <tr>
                            <td><strong><?php echo $d->format('M j, Y'); ?></strong></td>
                            <td><?php echo $v['weight_kg'] ? $v['weight_kg'] . ' kg' : '&mdash;'; ?></td>
                            <td><?php echo $v['height_cm'] ? $v['height_cm'] . ' cm' : '&mdash;'; ?></td>
                            <td><span class="badge badge-active"><?php echo $v['blood_pressure'] ? $v['blood_pressure'] : '&mdash;'; ?></span></td>
                            <td><?php echo $v['heart_rate'] ? $v['heart_rate'] . ' bpm' : '&mdash;'; ?></td>
                            <td><?php echo $v['fundal_height'] ? $v['fundal_height'] . ' cm' : '&mdash;'; ?></td>
                            <td><?php echo $v['notes'] ? htmlspecialchars($v['notes']) : '&mdash;'; ?></td>
                            <td>
                                <button class="btn-sm btn-delete" onclick="deleteVital(<?php echo (int)$v['id']; ?>)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="8" class="empty-state-row">
                            <div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
                            No vitals recorded yet for this patient.
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-card">
            <div class="section-head">
                <h2>Vaccination Record</h2>
                <button class="btn-add" onclick="openVaccineModal()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Vaccination</button>
            </div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Vaccine</th><th>Dose</th><th>Administered</th><th>Next Due</th><th>Notes</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($vacc_list && $vacc_list->num_rows > 0):
                        $today = date('Y-m-d');
                        while ($vc = $vacc_list->fetch_assoc()):
                            $given = new DateTime($vc['administered_date']);
                            $due = $vc['next_due_date'];
                            $dueStatus = '';
                            if ($due) {
                                $dueDiff = (strtotime($due) - strtotime($today)) / 86400;
                                $dueStatus = $dueDiff <= 14 ? 'badge-due' : 'badge-done';
                            }
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($vc['vaccine_name']); ?></strong></td>
                            <td><span class="badge badge-normal"><?php echo htmlspecialchars($vc['dose_label']); ?></span></td>
                            <td><?php echo $given->format('M j, Y'); ?></td>
                            <td>
                                <?php if ($due): ?>
                                    <span class="badge <?php echo $dueStatus; ?>"><?php echo (new DateTime($due))->format('M j, Y'); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-done">Complete</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $vc['notes'] ? htmlspecialchars($vc['notes']) : '&mdash;'; ?></td>
                            <td>
                                <button class="btn-sm btn-delete" onclick="deleteVaccine(<?php echo (int)$vc['id']; ?>)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="6" class="empty-state-row">
                            <div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0L11.5 5.85 9.9 4.25a5.4 5.4 0 0 0-7.65 7.65l1.6 1.6-6.4 6.4a1 1 0 0 0 0 1.41l2.83 2.83a1 1 0 0 0 1.41 0l6.4-6.4 1.6 1.6a5.4 5.4 0 0 0 7.65-7.65l-1.6-1.6 1.27-1.27a5.4 5.4 0 0 0 0-7.65z"/></svg></div>
                            No vaccinations recorded yet for this patient.
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <div class="content-card">
            <div class="empty-state-row">
                <div class="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></div>
                No patients yet. Add patients to start recording vitals and vaccinations.
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Add Vitals Modal -->
<div class="modal-overlay" id="vitalModal">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeModal('vitalModal')">&times;</button>
        <h2>Add Vitals / Visit</h2>
        <form id="vitalForm">
            <input type="hidden" name="patient_id" value="<?php echo $selected_id; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Visit Date *</label>
                    <input type="date" name="visit_date" data-datepicker max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Weight (kg) *</label>
                    <input type="number" name="weight_kg" step="0.1" min="0" placeholder="e.g. 58.5" required>
                </div>
                <div class="form-group">
                    <label>Height (cm)</label>
                    <input type="number" name="height_cm" step="0.1" min="0" max="300" placeholder="e.g. 160.5">
                </div>
                <div class="form-group">
                    <label>Blood Pressure *</label>
                    <input type="text" name="blood_pressure" placeholder="e.g. 110/70" required>
                </div>
                <div class="form-group">
                    <label>Heart Rate (bpm) *</label>
                    <input type="number" name="heart_rate" min="0" max="250" placeholder="e.g. 78" required>
                </div>
               
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" placeholder="Findings, symptoms, or next steps..."></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('vitalModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Visit</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Vaccination Modal -->
<div class="modal-overlay" id="vaccineModal">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeModal('vaccineModal')">&times;</button>
        <h2>Add Vaccination</h2>
        <form id="vaccineForm">
            <input type="hidden" name="patient_id" value="<?php echo $selected_id; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Vaccine Name *</label>
                    <select name="vaccine_name" class="form-dropdown" required>
                        <option value="">Select Vaccine</option>
                        <option>Tetanus Toxoid (TT)</option>
                        <option>Diptheria-Tetanus (DT)</option>
                        <option>Hepatitis B</option>
                        <option>Influenza</option>
                        <option>COVID-19</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="form-group" id="otherVaccineGroup" style="display:none">
                    <label>Specify Vaccine</label>
                    <input type="text" name="vaccine_other" placeholder="Enter vaccine name">
                </div>
<div class="form-group">
                    <label>Dose Given</label>
                    <select name="dose_label" class="form-dropdown">
                        <option value="Dose 1" selected>Dose 1</option>
                        <option value="Dose 2">Dose 2</option>
                        <option value="Dose 3">Dose 3</option>
                        <option value="Dose 4">Dose 4</option>
                        <option value="Booster">Booster</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Administered Date *</label>
                    <input type="date" name="administered_date" data-datepicker max="<?php echo date('Y-m-d'); ?>" required>
                </div>
<div class="form-group">
                    <label>Next Due Date</label>
                    <input type="date" name="next_due_date" data-datepicker min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Doctor (for booking)</label>
                    <select name="doctor_id" class="form-dropdown">
                        <?php
                        $firstDoctor = true;
                        if ($doctors) { $doctors->data_seek(0); while ($doc = $doctors->fetch_assoc()): ?>
                        <option value="<?php echo (int)$doc['id']; ?>" <?php echo $firstDoctor ? 'selected' : ''; ?>><?php echo htmlspecialchars($doc['name']); ?></option>
                        <?php $firstDoctor = false; endwhile; }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Preferred Time (booking)</label>
                    <select name="appointment_time" class="form-dropdown">
                        <?php foreach ($appt_slots as $slot): ?>
                        <option value="<?php echo $slot; ?>:00" <?php echo $slot === '09:00' ? 'selected' : ''; ?>><?php echo date('g:i A', strtotime($slot)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="form-hint">Set <strong>Dose Given</strong> to the dose administered now. If you also set a <strong>Next Due Date</strong>, the system automatically books a pending appointment on the patient's side for the <strong>next dose</strong> (e.g. Dose 1 given now &rarr; appointment for Dose 2 on the due date), using the chosen doctor and time (the system picks the next free slot if your preference is taken).</p>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" placeholder="Batch, remarks, or next-dose instructions..."></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('vaccineModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Vaccination</button>
            </div>
        </form>
    </div>
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
    var selectedPatient = <?php echo $selected_id; ?>;

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
    function openModal(id){document.getElementById(id).classList.add('active');document.body.style.overflow='hidden';}
    function closeModal(id){document.getElementById(id).classList.remove('active');document.body.style.overflow='';}

    /* Confirmation modal (replaces native confirm) */
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

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e){ if(e.target === this) closeModal(this.id); });
    });

    function openVitalModal(){
        document.getElementById('vitalForm').reset();
        var f = document.getElementById('vitalForm');
        f.querySelectorAll('.field-error').forEach(function(el){ el.classList.remove('field-error'); });
        var datePicker = document.querySelector('#vitalModal .admin-datepicker');
        if (datePicker) datePicker.classList.remove('has-error');
        var vd = f.querySelector('input[name="visit_date"]');
        var today = new Date();
        var iso = today.getFullYear() + '-' + String(today.getMonth()+1).padStart(2,'0') + '-' + String(today.getDate()).padStart(2,'0');
        if (vd) vd.value = iso;
        openModal('vitalModal');
        if (vd && vd.__fdSetValue) vd.__fdSetValue(iso);
    }
    function openVaccineModal(){
        document.getElementById('vaccineForm').reset();
        document.getElementById('otherVaccineGroup').style.display='none';
        var ad = document.getElementById('vaccineForm').querySelector('input[name="administered_date"]');
        var today = new Date();
        var iso = today.getFullYear() + '-' + String(today.getMonth()+1).padStart(2,'0') + '-' + String(today.getDate()).padStart(2,'0');
        if (ad) ad.value = iso;
        openModal('vaccineModal');
        if (ad && ad.__fdSetValue) ad.__fdSetValue(iso);
        if (ad && ad.__fdRefresh) ad.__fdRefresh();
    }

    function reloadPage(){ document.body.classList.add('__leaving'); setTimeout(()=>window.location.href='admin_vitals.php?patient='+selectedPatient, 215); }

    document.getElementById('vitalForm').addEventListener('submit', function(e){
        e.preventDefault();
        var labels = {
            'visit_date': 'Visit Date',
            'weight_kg': 'Weight',
            'blood_pressure': 'Blood Pressure',
            'heart_rate': 'Heart Rate',
            'fundal_height': 'Fundal Height'
        };
        var reqs = this.querySelectorAll('[required]');
        var firstField = null;
        reqs.forEach(function(inp){
            var ok = inp.value && inp.value.trim() !== '';
            if (inp.name === 'blood_pressure' && ok && !/^\d{2,3}\/\d{2,3}$/.test(inp.value.trim())) ok = false;
            if (inp.name === 'heart_rate' && ok && (!(parseInt(inp.value,10) >= 0) || parseInt(inp.value,10) > 250)) ok = false;
            inp.classList.toggle('field-error', !ok);
            if (!ok && !firstField) firstField = inp;
        });
        if (firstField) {
            showToast('Please fill in ' + (labels[firstField.name] || 'all required fields') + '.', 'error');
            var dp = document.querySelector('#vitalModal .admin-datepicker');
            if (dp) dp.classList.toggle('has-error', firstField.name === 'visit_date');
            if (firstField.name !== 'visit_date') firstField.focus();
            else if (dp) dp.querySelector('.admin-dp-trigger').focus();
            return;
        }
        showLoading();
        const fd = new FormData(this);
        fd.append('action', 'add_vital');
        fetch('admin_vitals_action.php', { method:'POST', body: fd })
            .then(r=>r.text()).then(res=>{
                hideLoading();
                if(res.trim()==='success'){ showToast('Vitals saved & patient profile updated','success'); closeModal('vitalModal'); reloadPage(); }
                else showToast(res.trim(),'error');
            }).catch(()=>{ hideLoading(); showToast('Network error','error'); });
    });

    function deleteVital(id){
        confirmAction('Delete this vitals record? This cannot be undone.', function(){
            showLoading();
            const fd = new FormData();
            fd.append('action','delete_vital');
            fd.append('patient_id', selectedPatient);
            fd.append('vital_id', id);
            fetch('admin_vitals_action.php', { method:'POST', body: fd })
                .then(r=>r.text()).then(res=>{
                    hideLoading();
                    if(res.trim()==='success'){ showToast('Deleted!','success'); reloadPage(); }
                    else showToast(res.trim(),'error');
                }).catch(()=>{ hideLoading(); showToast('Network error','error'); });
        });
    }

    document.getElementById('vaccineForm').addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(this);
        const vname = (fd.get('vaccine_name')||'').trim();
        const vgiven = (fd.get('administered_date')||'').trim();
        if(vname === '' || vname === 'Other'){ showToast('Please select a vaccine.','error'); return; }
        if(vgiven === ''){ showToast('Please set the Administered Date.','error'); return; }
        if(fd.get('vaccine_name') === 'Other'){
            const custom = (fd.get('vaccine_other')||'').trim();
            if(!custom){ showToast('Please enter the vaccine name.','error'); return; }
            fd.set('vaccine_name', custom);
        }
showLoading();
        fd.append('action', 'add_vaccination');
        fetch('admin_vitals_action.php', { method:'POST', body: fd })
            .then(r=>r.text()).then(res=>{
                hideLoading();
                if(res.trim()==='success'){ showToast('Vaccination saved!','success'); closeModal('vaccineModal'); reloadPage(); }
                else {
                    try {
                        var j = JSON.parse(res);
                        if (j && j.success) {
                            closeModal('vaccineModal');
                            if (j.auto_scheduled) {
                                var dt = new Date(j.auto_scheduled.date + 'T00:00:00');
                                var ds = isNaN(dt.getTime()) ? j.auto_scheduled.date : dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                                showToast('Vaccination saved! Appointment auto-scheduled for ' + j.auto_scheduled.next_dose + ' on ' + ds + '.', 'success');
                            } else {
                                showToast('Vaccination saved!','success');
                            }
                            reloadPage();
                            return;
                        }
                    } catch(e){}
                    showToast(res.trim(),'error');
                }
            }).catch(()=>{ hideLoading(); showToast('Network error','error'); });
    });

    document.getElementById('vaccineForm').querySelector('select[name="vaccine_name"]').addEventListener('change', function(){
        document.getElementById('otherVaccineGroup').style.display = this.value === 'Other' ? '' : 'none';
    });

    function deleteVaccine(id){
        confirmAction('Delete this vaccination record? This cannot be undone.', function(){
            showLoading();
            const fd = new FormData();
            fd.append('action','delete_vaccination');
            fd.append('patient_id', selectedPatient);
            fd.append('vaccination_id', id);
            fetch('admin_vitals_action.php', { method:'POST', body: fd })
                .then(r=>r.text()).then(res=>{
                    hideLoading();
                    if(res.trim()==='success'){ showToast('Deleted!','success'); reloadPage(); }
                    else showToast(res.trim(),'error');
                }).catch(()=>{ hideLoading(); showToast('Network error','error'); });
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
        .content-card{animation:__meIn .42s cubic-bezier(.22,1,.36,1) both;animation-delay:.1s;opacity:0;animation-fill-mode:both}
        .summary-chips .summary-chip{opacity:0;animation:__meIn .38s cubic-bezier(.22,1,.36,1) both;animation-fill-mode:both}
        .summary-chips .summary-chip:nth-child(1){animation-delay:.04s}
        .summary-chips .summary-chip:nth-child(2){animation-delay:.09s}
        .summary-chips .summary-chip:nth-child(3){animation-delay:.14s}
        .summary-chips .summary-chip:nth-child(4){animation-delay:.19s}
        .summary-chips .summary-chip:nth-child(5){animation-delay:.24s}
        .modal-box{animation:__meIn .3s cubic-bezier(.34,1.56,.64,1)}
        tr{transition:background .12s}
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
</body>
</html>