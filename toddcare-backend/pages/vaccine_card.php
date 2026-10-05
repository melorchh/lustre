<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["patient_id"])) {
    header("Location: index.php");
    exit;
}

$patient_id = (int)$_SESSION["patient_id"];
$vacc_id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$conn = null;
require_once __DIR__ . '/../src/db.php';

if ($vacc_id <= 0) {
    $conn->close();
    header("Location: my_appointments.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        v.id,
        v.vaccine_name,
        v.dose_label,
        v.administered_date,
        v.next_due_date,
        v.notes,
        v.created_at,
        p.name     AS patient_name,
        p.age,
        p.patient_type
    FROM vaccinations v
    JOIN patients p ON v.patient_id = p.id
    WHERE v.id = ? AND v.patient_id = ?
");
$stmt->bind_param("ii", $vacc_id, $patient_id);
$stmt->execute();
$vacc = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$vacc) {
    header("Location: my_appointments.php");
    exit;
}

$ref    = 'VACC-' . str_pad($vacc['id'], 6, '0', STR_PAD_LEFT);
$v_date = date('M j, Y', strtotime($vacc['administered_date']));
$v_full = date('F j, Y', strtotime($vacc['administered_date']));
$next   = $vacc['next_due_date'] ? date('M j, Y', strtotime($vacc['next_due_date'])) : null;
$next_full = $vacc['next_due_date'] ? date('F j, Y', strtotime($vacc['next_due_date'])) : null;

$asset_css = 'client/dist/assets/app.css';
$v_css = is_file(__DIR__ . '/../../' . $asset_css) ? filemtime(__DIR__ . '/../../' . $asset_css) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <script>try{if(localStorage.getItem('meTheme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}</script>
    <link rel="icon" href="images/Lustre.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;600;700&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>Vaccination Card &mdash; LustreMDC</title>
    <style>
        :root{
            --green:#0d9488;--green-dark:#0f766e;--green-deep:#115e59;--green-pale:#f0fdfa;--green-pale2:#ccfbf1;
            --blue:#2563eb;--blue-dark:#1d4ed8;--blue-deep:#1e3a8a;--blue-pale:#eff6ff;--blue-pale2:#dbeafe;
            --ink:#1a2e20;--gray-mid:#52604f;--gray-light:#94a3b8;--line:#e7ece8;--bg:#f4f7f5;
            --amber:#d97706;--amber-deep:#92400e;--amber-pale:#fffbeb;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:'Source Sans 3',sans-serif;background:#f4f7f5;color:var(--ink);
            min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:24px 16px}
        .vc-wrap{width:100%;max-width:440px;display:flex;flex-direction:column;align-items:center;gap:18px}
        .vc-top{width:100%;display:flex;align-items:center;justify-content:space-between}
        .vc-brand{display:flex;align-items:center;gap:8px;font-weight:700;color:var(--green-deep);font-size:.95rem;text-decoration:none}
        .vc-brand img{width:30px;height:30px;border-radius:50%}
        .vc-back{color:var(--green-dark);text-decoration:none;font-weight:600;font-size:.85rem}

        /* â”€â”€ The vaccination card (teal + blue) â”€â”€ */
        .vax{width:100%;background:#fff;border-radius:18px;overflow:hidden;
            box-shadow:0 18px 45px rgba(13,148,136,.18);border:1.5px solid var(--line)}
        .vax-head{background:linear-gradient(135deg,var(--green),var(--green-deep));color:#fff;padding:18px 20px;
            display:flex;align-items:center;gap:12px}
        .vax-head-svg{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.18);
            display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .vax-head-text{flex:1;min-width:0}
        .vax-head-title{font-family:'Lora',serif;font-weight:700;font-size:1.02rem;letter-spacing:.04em}
        .vax-head-sub{font-size:.72rem;opacity:.85;margin-top:1px}
        .vax-head-ref{text-align:right;flex-shrink:0}
        .vax-head-ref span{display:block;font-size:.58rem;letter-spacing:.14em;opacity:.75;text-transform:uppercase}
        .vax-head-ref strong{font-size:.95rem;letter-spacing:.04em;font-weight:700}

        .vax-body{padding:20px 20px 16px}
        .vax-row{display:flex;gap:14px;align-items:center;padding-bottom:14px;border-bottom:1.5px dashed #d1d5db}
        .vax-avatar{width:46px;height:46px;border-radius:50%;background:var(--blue-pale);color:var(--blue-dark);
            display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.95rem;flex-shrink:0}
        .vax-patient{flex:1;min-width:0}
        .vax-patient-label{font-size:.62rem;letter-spacing:.12em;text-transform:uppercase;color:var(--gray-light);font-weight:700}
        .vax-patient-name{font-weight:800;font-size:1.05rem;color:var(--ink);word-break:break-word}

        .vax-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding-top:14px}
        .vax-cell span{display:block;font-size:.62rem;letter-spacing:.1em;text-transform:uppercase;color:var(--gray-light);font-weight:700;margin-bottom:3px}
        .vax-cell strong{font-size:.85rem;font-weight:700;color:var(--ink);word-break:break-word}
        .vax-vaccine strong{font-size:1rem;color:var(--green-deep)}
        .vax-dose-badge{display:inline-flex;align-items:center;gap:5px;background:var(--green-pale);color:var(--green-dark);
            border:1px solid var(--green-pale2);padding:3px 10px;border-radius:999px;font-weight:700;font-size:.78rem}

        /* â”€â”€ Next dose / complete strip â”€â”€ */
        .vax-strip{margin-top:16px;border-radius:12px;padding:12px 14px;display:flex;align-items:center;gap:11px}
        .vax-strip--next{background:var(--amber-pale);border:1px solid #fde68a}
        .vax-strip--done{background:var(--green-pale);border:1px solid var(--green-pale2)}
        .vax-strip-icon{width:34px;height:34px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center}
        .vax-strip--next .vax-strip-icon{background:var(--amber);color:#fff}
        .vax-strip--done .vax-strip-icon{background:var(--green);color:#fff}
        .vax-strip-text .lbl{display:block;font-size:.62rem;letter-spacing:.1em;text-transform:uppercase;font-weight:700}
        .vax-strip--next .lbl{color:var(--amber-deep)}
        .vax-strip--done .lbl{color:var(--green-deep)}
        .vax-strip-text strong{font-size:.95rem;font-weight:700;display:block}
        .vax-strip--next strong{color:var(--amber-deep)}
        .vax-strip--done strong{color:var(--green-deep)}
        .vax-strip-text .sub{font-size:.72rem;color:var(--gray-mid)}

        .vax-foot{padding:14px 20px 16px;border-top:1px solid var(--line);background:#f9fafb;display:flex;gap:10px}
        .btn{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:7px;border:none;border-radius:11px;
            padding:11px 14px;font-size:.88rem;font-weight:700;cursor:pointer;text-decoration:none;font-family:inherit;transition:transform .12s ease,background .15s}
        .btn:active{transform:scale(.97)}
        .btn-download{background:var(--green);color:#fff}
        .btn-download:hover{background:var(--green-dark)}
        .btn-back{background:#fff;color:var(--ink);border:1.5px solid var(--line)}
        .btn-back:hover{background:#f3f4f6}

        .vc-foot{text-align:center;color:var(--gray-light);font-size:.72rem}
        @media(max-width:380px){.vax-head{flex-wrap:wrap}.vax-head-ref{width:100%;text-align:left}}
        @media(min-width:769px){html,body{height:auto;overflow:auto}}
    </style>
</head>
<body>
    <div class="vc-wrap">
        <div class="vc-top">
            <a class="vc-brand" href="dashboard.php"><img src="images/Lustre.png" alt="LustreMDC"> LustreMDC</a>
            <a class="vc-back" href="my_appointments.php#health">&larr; Back</a>
        </div>

        <!-- Vaccination card (COVID-style credit-card proportions) -->
        <div class="vax">
            <div class="vax-head">
                <div class="vax-head-svg">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l3 2"/></svg>
                </div>
                <div class="vax-head-text">
                    <div class="vax-head-title">VACCINATION CARD</div>
                    <div class="vax-head-sub">Lustre MDC Clinics &amp; Diagnostics</div>
                </div>
                <div class="vax-head-ref">
                    <span>Record No.</span>
                    <strong><?php echo $ref; ?></strong>
                </div>
            </div>

            <div class="vax-body">
                <div class="vax-row">
                    <div class="vax-avatar"><?php echo htmlspecialchars(strtoupper(substr($vacc['patient_name'], 0, 2))); ?></div>
                    <div class="vax-patient">
                        <div class="vax-patient-label">Patient</div>
                        <div class="vax-patient-name"><?php echo htmlspecialchars($vacc['patient_name']); ?></div>
                    </div>
                </div>

                <div class="vax-grid">
                    <div class="vax-cell vax-vaccine">
                        <span>Vaccine</span>
                        <strong><?php echo htmlspecialchars($vacc['vaccine_name']); ?></strong>
                    </div>
                    <div class="vax-cell">
                        <span>Dose</span>
                        <span class="vax-dose-badge"><?php echo htmlspecialchars($vacc['dose_label']); ?></span>
                    </div>
                    <div class="vax-cell">
                        <span>Date Administered</span>
                        <strong title="<?php echo $v_full; ?>"><?php echo $v_date; ?></strong>
                    </div>
                    <?php if ($next): ?>
                    <div class="vax-cell">
                        <span>Next Dose Due</span>
                        <strong title="<?php echo $next_full; ?>"><?php echo $next; ?></strong>
                    </div>
                    <?php else: ?>
                    <div class="vax-cell">
                        <span>Status</span>
                        <strong>Series Complete</strong>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($next): ?>
                    <div class="vax-strip vax-strip--next">
                        <div class="vax-strip-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        </div>
                        <div class="vax-strip-text">
                            <span class="lbl">Next Dose Schedule</span>
                            <strong><?php echo $next_full; ?></strong>
                            <span class="sub">Visit the clinic on or before this date.</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="vax-strip vax-strip--done">
                        <div class="vax-strip-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </div>
                        <div class="vax-strip-text">
                            <span class="lbl">Schedule Complete</span>
                            <strong>All doses are up to date</strong>
                            <span class="sub">No upcoming dose scheduled for this series.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="vax-foot">
                <a class="btn btn-download" href="vaccine_card_pdf.php?id=<?php echo $vacc['id']; ?>">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download PDF
                </a>
                <a class="btn btn-back" href="my_appointments.php#health">Back to Records</a>
            </div>
        </div>

        <div class="vc-foot">&copy; <?php echo date('Y'); ?> LustreMDC Clinics &amp; Diagnostics</div>
    </div>
</body>
</html>
