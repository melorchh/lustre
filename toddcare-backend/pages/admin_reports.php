<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { header("Location: admin_login.php"); exit; }
include __DIR__ . '/../src/db.php';
$admin_name = $_SESSION["admin_name"];

// ---- Build WHERE clause from filters --------------------------------------
$period      = isset($_GET['period']) ? $_GET['period'] : '';
$from        = isset($_GET['from']) ? trim($_GET['from']) : '';
$to          = isset($_GET['to'])   ? trim($_GET['to'])   : '';
$status      = isset($_GET['status']) ? $_GET['status'] : '';
$payment     = isset($_GET['payment']) ? $_GET['payment'] : '';
$doctor_id   = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$group       = isset($_GET['group']) ? $_GET['group'] : 'date'; // date | week | month

// Apply a quick preset period before the manual from/to
if ($period === 'today')       { $from = date('Y-m-d'); $to   = date('Y-m-d'); }
elseif ($period === 'week')    { $from = date('Y-m-d', strtotime('this week Monday')); $to = date('Y-m-d'); }
elseif ($period === 'month')   { $from = date('Y-m-01'); $to = date('Y-m-t'); }
elseif ($period === 'year')    { $from = date('Y-01-01'); $to = date('Y-12-31'); }

$where = "1=1";
$params = [];
$types  = "";
if ($from) { $where .= " AND a.appointment_date >= ?"; $params[] = $from; $types .= "s"; }
if ($to)   { $where .= " AND a.appointment_date <= ?"; $params[] = $to;   $types .= "s"; }
if ($status)  { $where .= " AND a.status = ?";         $params[] = $status;  $types .= "s"; }
if ($payment) { $where .= " AND a.payment_status = ?"; $params[] = $payment; $types .= "s"; }
if ($doctor_id){$where .= " AND a.doctor_id = ?";      $params[] = $doctor_id; $types .= "i"; }

function runQuery($conn, $sql, $params, $types) {
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        return $res;
    }
    return $conn->query($sql);
}

// ---- Summary metrics (respect filters) ------------------------------------
$metrics = [
    'appointments' => 0, 'patients' => 0, 'completed' => 0,
    'cancelled' => 0, 'paid' => 0, 'revenue' => 0,
];

$mRes = runQuery($conn,
    "SELECT
        COUNT(a.id) AS appts,
        COUNT(DISTINCT a.patient_id) AS patients,
SUM((a.status='completed')::int)  AS completed,
        SUM((a.status='cancelled')::int)  AS cancelled,
        SUM((a.payment_status='paid')::int) AS paid
     FROM appointments a WHERE $where", $params, $types);
if ($mRes && $mRow = $mRes->fetch_assoc()) {
    $metrics['appointments'] = (int)$mRow['appts'];
    $metrics['patients']     = (int)$mRow['patients'];
    $metrics['completed']    = (int)$mRow['completed'];
    $metrics['cancelled']    = (int)$mRow['cancelled'];
    $metrics['paid']         = (int)$mRow['paid'];
}
// Revenue: no fee field exists, so estimate from 'paid' appointments at 500 (configurable note)
$metrics['revenue']   = $metrics['paid'] * 500;

// ---- Grouped breakdown -----------------------------------------------------
switch ($group) {
case 'week':
        $labelExpr = "(a.appointment_date - ((extract(isodow from a.appointment_date)::int - 1) * interval '1 day'))::date AS bucket";
        $groupBy   = "(a.appointment_date - ((extract(isodow from a.appointment_date)::int - 1) * interval '1 day'))::date";
        $sortExpr  = $groupBy;
        break;
    case 'month':
        $labelExpr = "to_char(a.appointment_date, 'YYYY-MM') AS bucket";
        $groupBy   = "to_char(a.appointment_date, 'YYYY-MM')";
        $sortExpr  = "MIN(a.appointment_date)";
        break;
    default: // date
        $labelExpr = "a.appointment_date AS bucket";
        $groupBy   = "a.appointment_date";
        $sortExpr  = "a.appointment_date";
}

$gRes = runQuery($conn, "
    SELECT $labelExpr,
        COUNT(a.id) AS appts,
COUNT(DISTINCT a.patient_id) AS patients,
        SUM((a.status='completed')::int)  AS completed,
        SUM((a.status='cancelled')::int)  AS cancelled,
        SUM((a.payment_status='paid')::int) AS paid
    FROM appointments a
    WHERE $where
    GROUP BY $groupBy
    ORDER BY $sortExpr ASC", $params, $types);

// Collect breakdown rows for BOTH the table and the chart
$chart_labels  = [];
$chart_ts      = [];
$chart_appts   = [];
$chart_patients= [];
$chart_done    = [];
$chart_canc    = [];
$chart_paid    = [];
$chart_revenue = [];
$breakdown = [];
if ($gRes) {
    while ($r = $gRes->fetch_assoc()) {
        // Human label based on grouping
        switch ($group) {
            case 'week':
                $label = 'Week of ' . date('M j, Y', strtotime($r['bucket']));
                break;
            case 'month':
                $label = date('M Y', strtotime($r['bucket']));
                break;
            default:
                $label = date('M j, Y', strtotime($r['bucket']));
        }
        $revenue = (int)$r['paid'] * 500;
        $chart_labels[]   = $label;
        $chart_ts[]       = strtotime($r['bucket']) * 1000;
        $chart_appts[]    = (int)$r['appts'];
        $chart_patients[] = (int)$r['patients'];
        $chart_done[]     = (int)$r['completed'];
        $chart_canc[]     = (int)$r['cancelled'];
        $chart_paid[]     = (int)$r['paid'];
        $chart_revenue[]  = $revenue;
        $breakdown[] = array_merge($r, ['label' => $label]);
    }
}
// JSON-encode for the chart JS
$json_labels   = json_encode($chart_labels);
$json_ts       = json_encode($chart_ts);
$json_appts    = json_encode($chart_appts);
$json_patients = json_encode($chart_patients);
$json_done     = json_encode($chart_done);
$json_canc     = json_encode($chart_canc);
$json_paid     = json_encode($chart_paid);
$json_revenue  = json_encode($chart_revenue);
$chart_type    = 'line';

// ---- Doctor list for the dropdown -----------------------------------------
$doctors = $conn->query("SELECT id, name, specialty FROM doctors ORDER BY name");

// Query-string with the "persistent" filters preserved (for preset links),
// so clicking a preset doesn't silently drop your other selections.
function presetUrl($periodVal, $group, $status, $payment, $doctor_id) {
    $q = array();
    if ($periodVal) $q['period'] = $periodVal;
    if ($group)     $q['group']  = $group;
    if ($status)    $q['status'] = $status;
    if ($payment)   $q['payment']= $payment;
    if ($doctor_id) $q['doctor_id'] = $doctor_id;
    return 'admin_reports.php' . ($q ? '?' . http_build_query($q) : '');
}
$preset_urls = array(
    'all'   => 'admin_reports.php',
    'today' => presetUrl('today', $group, $status, $payment, $doctor_id),
    'week'  => presetUrl('week',  $group, $status, $payment, $doctor_id),
    'month' => presetUrl('month', $group, $status, $payment, $doctor_id),
    'year'  => presetUrl('year',  $group, $status, $payment, $doctor_id),
);

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <title>Reports &mdash; LustreMDC Admin</title>
    <style>
        .chart-switch{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:4px 0 14px}
        .chart-switch-label{font-size:.72rem;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:.05em;margin-right:4px}
        .chart-chip{padding:6px 13px;border-radius:20px;font-size:.8rem;font-weight:600;line-height:1;color:#475569;background:#f1f5f9;border:1px solid #e2e8f0;cursor:pointer;font-family:inherit;transition:all .15s}
        .chart-chip:hover{background:#e2e8f0}
        .chart-chip.active{background:var(--green);color:#fff;border-color:var(--green)}
        .chart-box{position:relative;height:340px;width:100%;margin-top:4px}
        .report-meta{display:flex;flex-wrap:wrap;gap:.35rem 1.4rem;color:var(--gray-600);font-size:.9rem;margin:-.25rem 0 1.4rem}
        .report-note{font-size:.8rem;color:var(--gray-400);margin-top:1.2rem;padding-top:1rem;border-top:1px solid var(--gray-100)}
        .report-section{font-size:.95rem;font-weight:700;color:var(--green-dark);text-transform:uppercase;letter-spacing:.04em;margin:1.4rem 0 .75rem}

        .report-toolbar{display:flex;flex-wrap:wrap;gap:.5rem 1.4rem;align-items:flex-end;white-space:nowrap}
        .report-toolbar .field{display:flex;flex-direction:column;gap:5px;flex-shrink:0}
        .report-toolbar .field:last-child{align-self:flex-end}
        .report-toolbar label{font-size:.68rem;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:.05em}
        .report-toolbar .admin-datepicker,.report-toolbar .cust-dropdown{width:155px;min-width:155px}
        .report-toolbar .admin-datepicker .admin-dp-trigger{padding:.55rem .8rem;min-height:42px;font-size:.88rem}
        .report-toolbar .cust-dropdown .cd-btn{padding:.55rem .85rem;min-height:42px;font-size:.88rem}
        .report-toolbar .btn-primary,.report-toolbar .btn-secondary{min-height:42px;padding:.6rem 1.1rem;font-size:.88rem}
        .report-presets{display:flex;gap:6px;align-items:flex-end}
        .report-presets a{padding:.45rem .85rem;border-radius:18px;font-size:.78rem;font-weight:600;line-height:1;color:#475569;background:#f1f5f9;border:1px solid #e2e8f0;text-decoration:none;display:inline-flex;align-items:center;transition:all .15s}
        .report-presets a:hover{background:#e2e8f0}
        .report-presets a.on{background:var(--green);color:#fff;border-color:var(--green)}
        .stats-grid{margin-bottom:0}

        @media (max-width: 900px) {
            .report-toolbar{row-gap:.75rem}
            .report-presets{width:100%;margin-right:0}
            .report-toolbar .admin-datepicker{width:100%;min-width:0}
            .report-toolbar .cust-dropdown{flex:1 1 0;min-width:140px;width:auto}
        }
        @media (max-width: 640px) {
            .report-toolbar .cust-dropdown{flex-basis:100%;width:100%}
            .report-toolbar .btn-primary,.report-toolbar .btn-secondary{width:100%}
        }
        @media print {
            body>*{display:none !important}
            .report-printable{display:block !important}
            body{background:#fff}
            .chart-chip,.chart-switch{display:none !important}
            .chart-box{height:260px}
        }
    </style>
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
            <a href="admin_reports.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15v-4"/><path d="M12 15V7"/><path d="M17 15v-6"/></svg></span><span class="rail-label">Reports</span></a>
            <a href="admin_history.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg></span><span class="rail-label">Archives</span></a>
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
                <span class="topbar-title">Reports</span>
            </div>
            <div class="topbar-actions">
    <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode"><svg class="theme-icon theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41"/></svg><svg class="theme-icon theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                <div class="topbar-hello"><span class="topbar-hello-label">Hello,</span><strong><?php echo htmlspecialchars(explode(' ', trim($admin_name))[0]); ?></strong></div>
                <div class="avatar avatar--topbar"><?php echo strtoupper(substr(trim($admin_name),0,1)); ?></div>
            </div>
        </header>

        <div class="content-card report-filters">
            <form class="report-toolbar" method="get" action="admin_reports.php">
                <div class="report-presets">
                    <a class="<?php echo $period===''?'on':''; ?>" href="<?php echo $preset_urls['all']; ?>">All</a>
                    <a class="<?php echo $period==='today'?'on':''; ?>" href="<?php echo $preset_urls['today']; ?>">Today</a>
                    <a class="<?php echo $period==='week'?'on':''; ?>" href="<?php echo $preset_urls['week']; ?>">This Week</a>
                    <a class="<?php echo $period==='month'?'on':''; ?>" href="<?php echo $preset_urls['month']; ?>">This Month</a>
                    <a class="<?php echo $period==='year'?'on':''; ?>" href="<?php echo $preset_urls['year']; ?>">This Year</a>
                </div>

                <div class="field">
                    <label>From</label>
                    <input type="date" name="from" data-datepicker value="<?php echo htmlspecialchars($from); ?>">
                </div>
                <div class="field">
                    <label>To</label>
                    <input type="date" name="to" data-datepicker value="<?php echo htmlspecialchars($to); ?>">
                </div>
                <div class="field">
                    <label>Group By</label>
                    <select class="filter-select" name="group" onchange="this.form.submit()">
                        <option value="date" <?php echo $group==='date'?'selected':''; ?>>Daily</option>
                        <option value="week" <?php echo $group==='week'?'selected':''; ?>>Weekly</option>
                        <option value="month"<?php echo $group==='month'?'selected':''; ?>>Monthly</option>
                    </select>
                </div>
                <div class="field">
                    <label>Status</label>
                    <select class="filter-select" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending','confirmed','completed','cancelled'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo $status===$st?'selected':''; ?>><?php echo ucfirst($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Payment</label>
                    <select class="filter-select" name="payment">
                        <option value="">All Payments</option>
                        <option value="paid" <?php echo $payment==='paid'?'selected':''; ?>>Paid</option>
                        <option value="pending" <?php echo $payment==='pending'?'selected':''; ?>>Unpaid</option>
                    </select>
                </div>
                <div class="field">
                    <label>Doctor</label>
                    <select class="filter-select" name="doctor_id">
                        <option value="0">All Doctors</option>
                        <?php if ($doctors) while ($d = $doctors->fetch_assoc()): ?>
                        <option value="<?php echo (int)$d['id']; ?>" <?php echo $doctor_id===(int)$d['id']?'selected':''; ?>><?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['specialty']); ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="field">
                    <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                    <button type="submit" class="btn-primary">Generate Report</button>
                </div>
                <div class="field">
                    <button type="button" class="btn-secondary" onclick="window.print()">Print / PDF</button>
                </div>
            </form>
        </div>

        <!-- -- Printable report -- -->
        <div class="report-printable">
            <div class="content-card report-sheet">
                <h2>Patient &amp; Appointment Report</h2>
                <div class="report-meta">
                    <span>Period: <strong><?php
                        if ($from && $to) { echo date('M j, Y', strtotime($from)) . ' &ndash; ' . date('M j, Y', strtotime($to)); }
                        elseif ($from)    { echo 'From ' . date('M j, Y', strtotime($from)); }
                        elseif ($to)      { echo 'Up to ' . date('M j, Y', strtotime($to)); }
                        else              { echo 'All time'; }
                    ?></strong></span>
                    <span>Grouped: <strong><?php echo ucfirst($group); ?></strong></span>
                    <span>Generated: <strong><?php echo date('F j, Y g:i A'); ?></strong></span>
                </div>

                <!-- Summary metrics -->
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-number"><?php echo $metrics['appointments']; ?></div><div class="stat-label">Appointments</div></div>
                    <div class="stat-card" style="border-top-color:#8b5cf6"><div class="stat-number" style="color:#8b5cf6"><?php echo $metrics['patients']; ?></div><div class="stat-label">Unique Patients</div></div>
                    <div class="stat-card" style="border-top-color:#10b981"><div class="stat-number" style="color:#10b981"><?php echo $metrics['completed']; ?></div><div class="stat-label">Completed</div></div>
                    <div class="stat-card" style="border-top-color:#ef4444"><div class="stat-number" style="color:#ef4444"><?php echo $metrics['cancelled']; ?></div><div class="stat-label">Cancelled</div></div>
                    <div class="stat-card" style="border-top-color:#f59e0b"><div class="stat-number" style="color:#f59e0b"><?php echo $metrics['paid']; ?></div><div class="stat-label">Paid Visits</div></div>
                    <div class="stat-card" style="border-top-color:#0ea5e9"><div class="stat-number" style="color:#0ea5e9">&#8369;<?php echo number_format($metrics['revenue']); ?></div><div class="stat-label">Est. Revenue</div></div>
                </div>

                <!-- Chart -->
                <h3 class="report-section"><?php echo ucfirst($group); ?> Patient Trend</h3>
                <div class="chart-switch">
                    <span class="chart-switch-label">Show:</span>
                    <button type="button" class="chart-chip active" data-field="patients">Patients</button>
                    <button type="button" class="chart-chip" data-field="appts">Appointments</button>
                    <button type="button" class="chart-chip" data-field="paid">Paid</button>
                    <button type="button" class="chart-chip" data-field="revenue">Revenue</button>
                </div>
                <div class="chart-box"><canvas id="reportChart"></canvas></div>

                <!-- Breakdown table -->
                <h3 class="report-section"><?php echo ucfirst($group); ?> Breakdown</h3>
                <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo ucfirst($group); ?></th>
                            <th>Appointments</th>
                            <th>Unique Patients</th>
                            <th>Completed</th>
                            <th>Cancelled</th>
                            <th>Paid</th>
                            <th>Est. Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $tot_appts = 0; $tot_pat = 0; $tot_done = 0; $tot_canc = 0; $tot_paid = 0; $tot_rev = 0;
                    $rows = count($breakdown);
                    foreach ($breakdown as $r):
                        $tot_appts += (int)$r['appts']; $tot_pat += (int)$r['patients'];
                        $tot_done  += (int)$r['completed']; $tot_canc += (int)$r['cancelled'];
                        $tot_paid  += (int)$r['paid']; $tot_rev += (int)$r['paid'] * 500;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['label']); ?></strong></td>
                        <td><?php echo (int)$r['appts']; ?></td>
                        <td><?php echo (int)$r['patients']; ?></td>
                        <td><?php echo (int)$r['completed']; ?></td>
                        <td><?php echo (int)$r['cancelled']; ?></td>
                        <td><?php echo (int)$r['paid']; ?></td>
                        <td>&#8369;<?php echo number_format((int)$r['paid'] * 500); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                    <tr><td colspan="7" class="empty-state-row">No records match the selected filters.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <?php if ($rows): ?>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td><?php echo $tot_appts; ?></td>
                            <td><?php echo $tot_pat; ?></td>
                            <td><?php echo $tot_done; ?></td>
                            <td><?php echo $tot_canc; ?></td>
                            <td><?php echo $tot_paid; ?></td>
                            <td>&#8369;<?php echo number_format($tot_rev); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
                </div>

                <div class="report-note">* Estimated revenue = paid visits &#215; &#8369;500. No billing amount is stored in the appointment record. If you track fees, adjust this figure in admin_reports.php.</div>
            </div>
        </div>
    </main>
</div>

<div id="loadingScreen" class="loading-screen"><div class="spinner"></div><p>Loading&#8230;</p></div>
<div id="toast" class="toast"></div>

<script>
    function openSidebar(){document.getElementById('adminSidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');document.body.style.overflow='hidden';}
    function closeSidebar(){document.getElementById('adminSidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');document.body.style.overflow='';}
    function toggleSidebar(){
      var sb=document.getElementById('adminSidebar');
      if(sb.classList.contains('open')){
        sb.classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active');document.body.style.overflow='';
      }else{
        sb.classList.add('open');
        if(window.matchMedia('(max-width: 900px)').matches){document.getElementById('sidebarOverlay').classList.add('active');document.body.style.overflow='hidden';}
      }
    }

    (function(){
        const s=document.createElement('style');
        s.textContent=`
            @keyframes __meIn  {from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
            @keyframes __meFadeOut{from{opacity:1}to{opacity:0}}
            body{animation:__meIn .3s ease both}
            body.__leaving{animation:__meFadeOut .2s ease forwards;pointer-events:none}
            .report-filters{animation:__meIn .42s cubic-bezier(.22,1,.36,1) both;animation-delay:.06s;opacity:0;animation-fill-mode:both}
            .report-sheet{animation:__meIn .42s cubic-bezier(.22,1,.36,1) both;animation-delay:.12s;opacity:0;animation-fill-mode:both}
            .stat-card{opacity:0;animation:__meIn .38s cubic-bezier(.22,1,.36,1) both;animation-fill-mode:both}
            .stats-grid .stat-card:nth-child(1){animation-delay:.03s}
            .stats-grid .stat-card:nth-child(2){animation-delay:.07s}
            .stats-grid .stat-card:nth-child(3){animation-delay:.11s}
            .stats-grid .stat-card:nth-child(4){animation-delay:.15s}
            .stats-grid .stat-card:nth-child(5){animation-delay:.19s}
            .stats-grid .stat-card:nth-child(6){animation-delay:.23s}
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

    /* -- Sales graph (Chart.js) -- */
    (function(){
        var labels  = <?php echo $json_labels ?: '[]'; ?>;
        var ts      = <?php echo $json_ts ?: '[]'; ?>;
        var appts    = <?php echo $json_appts ?: '[]'; ?>;
        var patients = <?php echo $json_patients ?: '[]'; ?>;
        var done     = <?php echo $json_done ?: '[]'; ?>;
        var canc     = <?php echo $json_canc ?: '[]'; ?>;
        var paid     = <?php echo $json_paid ?: '[]'; ?>;
        var revenue  = <?php echo $json_revenue ?: '[]'; ?>;

        // tabular index of {x: ts, y: value} for a true time-series axis
        function series(arr){
            return arr.map(function(v, i){ return { x: ts[i], y: v }; });
        }
        // Linear (time) axis needs at least 2 distinct points; with a single
        // bucket min==max collapses the axis, so fall back to category labels.
        var pointCount = appts.length;
        var timeMode = pointCount >= 2 && ts.length === appts.length && ts[0] !== ts[ts.length-1];

        var datasets = {
            revenue: { label: 'Revenue (₱)', color: '#16a34a', data: revenue, money: true },
            appts:   { label: 'Appointments', color: '#8b5cf6', data: appts, money: false },
            patients:{ label: 'Unique Patients', color: '#0ea5e9', data: patients, money: false },
            paid:    { label: 'Paid Visits', color: '#f59e0b', data: paid, money: false }
        };

        var ctx = document.getElementById('reportChart');
        if (!ctx) return;
        if (!appts.length) {
            ctx.parentNode.innerHTML = '<div class="chart-empty">No data to display for the selected filters.</div>';
            return;
        }

        function fmtMoney(v){ return '₱' + Number(v).toLocaleString('en-US'); }
        function fmtMoneyShort(v){
if (v >= 1000000) return '₱' + (v/1000000).toFixed(1) + 'M';
            if (v >= 1000) return '₱' + (v/1000).toFixed(0) + 'k';
            return '₱' + v;
        }
        var timeUnit = (function(){
            if (!timeMode || ts.length < 2) return 'day';
            var span = (ts[ts.length-1] - ts[0]) / 86400000;
            if (span <= 45) return 'day';
            if (span <= 250) return 'week';
            return 'month';
        })();
        function fmtTime(v){
            var d = new Date(v);
            var mo = d.toLocaleDateString('en-US', { month: 'short' });
            var da = d.getDate();
            var yr = d.getFullYear();
            if (timeUnit === 'day')  return mo + ' ' + da + ', ' + yr;
            if (timeUnit === 'week') return mo + ' ' + da + ', ' + yr;
            return mo + ' ' + yr;
        }

        function buildChart(field){
            var ds = datasets[field] || datasets.appts;
            var dark = (document.documentElement.getAttribute('data-theme')||'light') === 'dark';
            var gridColor = dark ? '#243047' : '#eef2ee';
            var tickColor = dark ? '#8b9bb3' : '#52604f';
            var labelColor = dark ? '#c7d3e2' : '#233026';
            var cfg = {
                type: 'line',
                data: {
                    labels: timeMode ? undefined : labels,
                    datasets: [{
                        label: ds.label,
                        data: timeMode ? series(ds.data) : ds.data,
                        backgroundColor: ds.color + '26',
                        borderColor: ds.color,
                        borderWidth: 2.5,
                        fill: { target: { value: 0 } },
                        tension: 0.4,
                        pointRadius: 3.5,
                        pointHoverRadius: 5,
                        pointBackgroundColor: ds.color,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 1.5,
                        spanGaps: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: true, position: 'top', align: 'end', labels: { boxWidth: 12, boxHeight: 12, usePointStyle: true, pointStyle: 'circle', font: { size: 12 }, color: labelColor } },
                        tooltip: {
                            callbacks: {
                                title: function(items){
                                    var it = items[0];
                                    if (it && it.parsed && typeof it.parsed.x === 'number') {
                                        return fmtTime(it.parsed.x);
                                    }
                                    return it && it.label ? it.label : '';
                                },
                                label: function(c){
                                    var v = c.parsed.y;
                                    return ' ' + ds.label + ': ' + (ds.money ? fmtMoney(v) : v);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: gridColor },
                            border: { display: false },
                            ticks: { precision: 0, padding: 8, color: tickColor, font: { size: 11 }, callback: function(v){ return ds.money ? fmtMoneyShort(v) : v; } }
                        },
                        x: {
                            type: timeMode ? 'linear' : 'category',
                            min: timeMode ? ts[0] : undefined,
                            max: timeMode ? ts[ts.length-1] : undefined,
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                autoSkip: true,
                                maxTicksLimit: timeMode ? 8 : 12,
                                maxRotation: 45,
                                padding: 5,
                                color: tickColor,
                                font: { size: 11 },
                                callback: function(val){
                                    if (timeMode) return fmtTime(val);
                                    return this.getLabelForValue ? this.getLabelForValue(val) : val;
                                }
                            }
                        }
                    }
                }
            };
            if (myChart) { myChart.destroy(); }
            myChart = new Chart(ctx, cfg);
        }

        var myChart = null;
        var currentField = 'appts';
        buildChart(currentField);

        // Rebuild the chart whenever the day/night theme changes
        document.addEventListener('themechange', function(){
            if (myChart && currentField) buildChart(currentField);
        });

        // Metric switcher chips
        document.querySelectorAll('.chart-chip').forEach(function(btn){
            btn.addEventListener('click', function(){
                document.querySelectorAll('.chart-chip').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                currentField = btn.getAttribute('data-field');
                buildChart(currentField);
            });
        });
    })();
</script>
<script src="admin_datepicker.js?v=<?= filemtime(__DIR__ . '/../../admin_datepicker.js') ?>"></script>
<script src="admin_components.js?v=<?= filemtime(__DIR__ . '/../../admin_components.js') ?>"></script>
<script src="admin_theme.js?v=<?= filemtime(__DIR__ . '/../../admin_theme.js') ?>"></script>
</body>
</html>