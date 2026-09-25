<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["patient_id"])) {
    header("Location: index.php");
    exit;
}

$patient_id = (int)$_SESSION["patient_id"];
$test_id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$conn = null;
include __DIR__ . '/../src/db.php';

if ($test_id <= 0) {
    $conn->close();
    header("Location: my_appointments.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        lt.id,
        lt.test_type,
        lt.priority,
        lt.status,
        lt.scheduled_date,
        lt.created_at,
        d.name     AS doctor_name,
        d.specialty,
        p.name     AS patient_name
    FROM lab_tests lt
    JOIN doctors  d ON lt.doctor_id  = d.id
    JOIN patients p ON lt.patient_id = p.id
    WHERE lt.id = ? AND lt.patient_id = ?
");
$stmt->bind_param("ii", $test_id, $patient_id);
$stmt->execute();
$lab = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$lab) {
    header("Location: my_appointments.php");
    exit;
}

$ref = 'LAB-' . str_pad($lab['id'], 6, '0', STR_PAD_LEFT);
$created = date('F j, Y', strtotime($lab['created_at']));
$sched   = $lab['scheduled_date'] ? date('F j, Y', strtotime($lab['scheduled_date'])) : 'Pending';
$status  = ucfirst($lab['status']);
$prio    = ucfirst($lab['priority']);

$asset_css = 'client/dist/assets/app.css';
$asset_js  = 'client/dist/assets/records.js';
$v_css = is_file(__DIR__ . '/../../' . $asset_css) ? filemtime(__DIR__ . '/../../' . $asset_css) : 0;
$v_js  = is_file(__DIR__ . '/../../' . $asset_js)  ? filemtime(__DIR__ . '/../../' . $asset_js)  : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d9488">
    <script>try{if(localStorage.getItem('meTheme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}</script>
    <link rel="icon" href="images/Lustre.png" type="image/png">
    <link rel="stylesheet" href="<?php echo $asset_css; ?>?v=<?php echo $v_css; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;600;700&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>Lab Test Confirmation &mdash; LustreMDC</title>
    <style>
        :root{
            --green:#0d9488;--green-dark:#0f766e;--green-deep:#115e59;--green-pale:#f0fdfa;
            --ink:#1a2e20;--gray-mid:#52604f;--gray-light:#94a3b8;--line:#e7ece8;--bg:#f4f7f5;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:'Source Sans 3',sans-serif;background:var(--bg);color:var(--ink)}
        .cf-page{max-width:640px;margin:0 auto;padding:28px 16px 48px}
        .cf-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:12px}
        .cf-brand{display:flex;align-items:center;gap:10px;font-family:'Lora',serif;font-weight:700;color:var(--green-deep);font-size:1.05rem}
        .cf-brand img{width:36px;height:36px;border-radius:50%}
        .cf-back{color:var(--green-dark);text-decoration:none;font-weight:600;font-size:0.92rem}
        .cf-card{background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 12px 34px rgba(20,83,45,.10);border:1px solid var(--line)}
        .cf-head{background:linear-gradient(135deg,var(--green-dark),var(--green-deep));color:#fff;padding:22px 24px;display:flex;justify-content:space-between;align-items:center;gap:12px}
        .cf-head h1{font-family:'Lora',serif;font-size:1.2rem;margin:0 0 4px;color:#fff}
        .cf-head p{margin:0;font-size:.88rem;opacity:.88}
        .cf-ref{text-align:right}
        .cf-ref-label{font-size:.66rem;letter-spacing:.12em;text-transform:uppercase;opacity:.75;display:block}
        .cf-ref-value{font-size:1.25rem;font-weight:700;letter-spacing:.03em}
        .cf-body{padding:24px}
        .cf-flag{text-align:center;margin-bottom:18px}
        .cf-flag-badge{display:inline-flex;align-items:center;gap:8px;background:var(--green-pale);color:var(--green-dark);border:1px solid #bbf7d0;padding:7px 16px;border-radius:999px;font-weight:700;font-size:.9rem}
        .cf-info{display:grid;grid-template-columns:1fr 1fr;gap:16px 20px;margin:22px 0}
        .cf-field .cf-lbl{font-size:.7rem;letter-spacing:.08em;text-transform:uppercase;color:var(--gray-light);margin-bottom:3px;font-weight:700}
        .cf-field .cf-val{font-size:1rem;font-weight:600;color:var(--ink);word-break:break-word}
        .cf-field .cf-val.cf-doctor{font-family:'Lora',serif;font-size:1.1rem;color:var(--green-deep)}
        .cf-full{grid-column:1 / -1}
        .cf-actions{display:flex;gap:12px;margin-top:8px;flex-wrap:wrap}
        .btn{flex:1;min-width:150px;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;border-radius:12px;padding:12px 16px;font-size:.95rem;font-weight:700;cursor:pointer;text-decoration:none;font-family:inherit;transition:transform .12s ease}
        .btn:active{transform:scale(.97)}
        .btn-download{background:var(--green);color:#fff}
        .btn-download:hover{background:var(--green-dark)}
        .btn-secondary{background:#eef2ef;color:var(--ink);border:1px solid var(--line)}
        .cf-note{background:#f8faf9;border:1px dashed #cfd8d2;border-radius:12px;padding:10px 14px;font-size:.8rem;color:var(--gray-mid);margin-top:20px}
        .cf-foot{text-align:center;color:var(--gray-light);font-size:.78rem;margin-top:22px}
        .badge-status{padding:3px 10px;border-radius:999px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
        .st-pending{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
        .st-processing{background:#fef9c3;color:#854d0e;border:1px solid #fde047}
        .st-completed{background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd}
        .st-cancelled{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
        @media(max-width:480px){.cf-info{grid-template-columns:1fr}}
        @media(min-width:769px){html,body{height:auto;overflow:auto}}
        html[data-theme="dark"] .cf-card{background:#1a2331;border-color:#2a3542}
        html[data-theme="dark"] .cf-flag-badge{background:rgba(13,148,136,.15);color:#5eead4;border-color:#2f6a5b}
        html[data-theme="dark"] .btn-secondary{background:#26303f;color:#e2e8f0;border-color:#334155}
        html[data-theme="dark"] .cf-note{background:#1e2935;border-color:#33415a}
        html[data-theme="dark"] .cf-page{color:#e2e8f0}
        html[data-theme="dark"] body{background:#0f1722}
        html[data-theme="dark"] .cf-brand,.cf-foot{color:#94a3b8}
    </style>
</head>
<body>
    <div class="cf-page">
        <div class="cf-top">
            <a class="cf-brand" href="dashboard.php"><img src="images/Lustre.png" alt="LustreMDC"> LustreMDC</a>
            <a class="cf-back" href="my_appointments.php">&larr; My Records</a>
        </div>

        <div class="cf-card">
            <div class="cf-head">
                <div>
                    <h1>Lab Test Request</h1>
                    <p>Present this card at the clinic as proof of your lab request</p>
                </div>
                <div class="cf-ref">
                    <span class="cf-ref-label">Reference No.</span>
                    <span class="cf-ref-value"><?php echo $ref; ?></span>
                </div>
            </div>

            <div class="cf-body">
                <div class="cf-flag">
                    <span class="cf-flag-badge">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2"/><path d="M8.5 2h7"/></svg>
                        Test Requested
                    </span>
                </div>

                <div class="cf-info">
                    <div class="cf-field cf-full">
                        <div class="cf-lbl">Patient</div>
                        <div class="cf-val"><?php echo htmlspecialchars($lab['patient_name']); ?></div>
                    </div>
                    <div class="cf-field cf-full">
                        <div class="cf-lbl">Test / Procedure</div>
                        <div class="cf-val"><?php echo htmlspecialchars($lab['test_type']); ?></div>
                    </div>
                    <div class="cf-field">
                        <div class="cf-lbl">Requesting Doctor</div>
                        <div class="cf-val cf-doctor">Dr. <?php echo htmlspecialchars($lab['doctor_name']); ?></div>
                    </div>
                    <div class="cf-field">
                        <div class="cf-lbl">Specialty</div>
                        <div class="cf-val"><?php echo htmlspecialchars($lab['specialty']); ?></div>
                    </div>
                    <div class="cf-field">
                        <div class="cf-lbl">Requested On</div>
                        <div class="cf-val"><?php echo $created; ?></div>
                    </div>
                    <div class="cf-field">
                        <div class="cf-lbl">Scheduled Date</div>
                        <div class="cf-val"><?php echo $sched; ?></div>
                    </div>
                    <div class="cf-field">
                        <div class="cf-lbl">Priority</div>
                        <div class="cf-val"><?php echo $prio; ?></div>
                    </div>
                    <div class="cf-field">
                        <div class="cf-lbl">Status</div>
                        <div class="cf-val"><span class="badge-status st-<?php echo strtolower($lab['status']); ?>"><?php echo $status; ?></span></div>
                    </div>
                </div>

                <div class="cf-actions">
                    <a class="btn btn-download" href="lab_result_pdf.php?id=<?php echo $lab['id']; ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download Proof (PDF)
                    </a>
                    <a class="btn btn-secondary" href="my_appointments.php">Back to Records</a>
                </div>

                <div class="cf-note">
                    For assistance, please present this card (on-screen or printed) at the clinic front desk when availing your laboratory test.
                </div>
            </div>
        </div>

        <div class="cf-foot">&copy; <?php echo date('Y'); ?> LustreMDC Clinics &amp; Diagnostics</div>
    </div>
</body>
</html>
