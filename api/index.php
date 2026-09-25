<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
// If not logged in, redirect to landing page
if (!isset($_SESSION["patient_id"])) {
    header("Location: landing.php");
    exit;
}

include __DIR__ . '/../toddcare-backend/src/db.php';

$patient_id = $_SESSION["patient_id"];
$user_name  = 'Patient';

$stmt = $conn->prepare("SELECT name FROM patients WHERE id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

$doctors = [];
$res = $conn->query("SELECT id, name, specialty, schedule, experience FROM doctors ORDER BY name");
if ($res) {
    $doctors = $res->fetch_all(MYSQLI_ASSOC);
}

$appointments = [];
$stmt = $conn->prepare("
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.payment_status, a.created_at,
           a.result, a.result_date,
           d.name as doctor_name, d.specialty
    FROM appointments a JOIN doctors d ON a.doctor_id = d.id
    WHERE a.patient_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$lab_tests = [];
$stmt = $conn->prepare("
    SELECT lt.id, lt.test_type, lt.priority, lt.status, lt.scheduled_date, lt.result, lt.notes, lt.created_at,
           d.name as doctor_name, d.specialty
    FROM lab_tests lt JOIN doctors d ON lt.doctor_id = d.id
    WHERE lt.patient_id = ? ORDER BY lt.created_at DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$lab_tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$vitals = [];
$stmt = $conn->prepare("SELECT id, visit_date, weight_kg, blood_pressure, heart_rate, fundal_height, notes, created_at FROM vitals WHERE patient_id=? ORDER BY visit_date DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$vitals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$vaccinations = [];
$stmt = $conn->prepare("SELECT id, vaccine_name, dose_label, administered_date, next_due_date, notes, created_at FROM vaccinations WHERE patient_id=? ORDER BY administered_date DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$vaccinations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$lab_categories = [
    'Blood Tests' => ['Complete Blood Count (CBC)','Fasting Blood Sugar (FBS)','Lipid Profile','Uric Acid','Creatinine / BUN','Blood Typing & Cross-matching','Coagulation Profile (PT/PTT)','Hepatitis B Surface Antigen','HIV Screening'],
    'Urine & Stool Tests' => ['Urinalysis','Fecalysis (Stool Exam)','Occult Blood Test'],
    'Infectious Disease Tests' => ['Dengue NS1 / IgG / IgM','COVID-19 Antigen / RT-PCR','VDRL / Syphilis Test','Rubella Antibody Test','Sexually Transmitted Infection (STI) Panel'],
    'Pregnancy & Hormonal' => ['Pregnancy Test (Serum hCG)','Thyroid Function Test','Prenatal Panel','Hormonal Panel (FSH, LH, Estrogen)','Glucose Tolerance Test (GTT)','Pap Smear','Transvaginal Ultrasound'],
    'Imaging & Monitoring' => ['Fetal Anomaly Scan','Non-Stress Test (NST)','Amniotic Fluid Index (AFI)','Cervical Culture & Sensitivity'],
    'Other' => ['Other (specify below)'],
];

$conn->close();

$asset_css = 'client/dist/assets/app.css';
$asset_js  = 'client/dist/assets/app.js';
$v_css = is_file(__DIR__ . '/../' . $asset_css) ? filemtime(__DIR__ . '/../' . $asset_css) : 0;
$v_js  = is_file(__DIR__ . '/../' . $asset_js)  ? filemtime(__DIR__ . '/../' . $asset_js)  : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0d9488">
  <title>LustreMDC &mdash; Book Appointment</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;600;700&family=Source+Sans+3:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" href="images/Lustre.png" type="image/png">
  <script>try{if(localStorage.getItem('meTheme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}</script>
  <script>
    window.__MEDEXPERT__ = {
      patientName: <?php echo json_encode($user_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      doctors: <?php echo json_encode($doctors, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      appointments: <?php echo json_encode($appointments, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      labTests: <?php echo json_encode($lab_tests, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      vitals: <?php echo json_encode($vitals, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      vaccinations: <?php echo json_encode($vaccinations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      categories: <?php echo json_encode($lab_categories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    };
  </script>
  <link rel="stylesheet" href="<?php echo $asset_css; ?>?v=<?php echo $v_css; ?>">
</head>
<body>
  <div id="root"></div>
  <script type="module" src="<?php echo $asset_js; ?>?v=<?php echo $v_js; ?>"></script>
</body>
</html>