<?php
function lab_test_types_ensure($conn)
{
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $ok = @$conn->query("CREATE TABLE IF NOT EXISTS lab_test_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(140) NOT NULL,
        UNIQUE KEY uq_lab_test_types_name (name)
    )");
    if (!$ok) return;
    $r = $conn->query("SELECT COUNT(*) c FROM lab_test_types");
    if ($r && (int)$r->fetch_assoc()['c'] === 0) {
        $seed = [
            'Complete Blood Count (CBC)',
            'Blood Typing & Cross-matching',
            'Urinalysis',
            'Pregnancy Test (Serum hCG)',
            'Pap Smear',
            'Transvaginal Ultrasound',
            'Prenatal Panel',
            'Glucose Tolerance Test (GTT)',
            'Thyroid Function Test',
            'Sexually Transmitted Infection (STI) Panel',
            'Hormonal Panel (FSH, LH, Estrogen)',
            'Cervical Culture & Sensitivity',
            'Coagulation Profile (PT/PTT)',
            'Hepatitis B Surface Antigen',
            'HIV Screening',
            'Rubella Antibody Test',
            'VDRL / Syphilis Test',
            'Fetal Anomaly Scan',
            'Non-Stress Test (NST)',
            'Amniotic Fluid Index (AFI)',
        ];
        $stmt = $conn->prepare("INSERT INTO lab_test_types (name) VALUES (?)");
        if ($stmt) {
            foreach ($seed as $s) { $stmt->bind_param("s", $s); @$stmt->execute(); }
            $stmt->close();
        }
    }
}