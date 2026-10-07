<?php
function doctor_specialties_ensure($conn)
{
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $ok = @$conn->query("CREATE TABLE IF NOT EXISTS doctor_specialties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(140) NOT NULL,
        UNIQUE KEY uq_doctor_specialties_name (name)
    )");
    if (!$ok) return;
    $r = $conn->query("SELECT COUNT(*) c FROM doctor_specialties");
    if ($r && (int)$r->fetch_assoc()['c'] === 0) {
        $seed = [
            'Obstetrics & Gynecology',
            'Family Medicine',
            'Pediatrics',
            'Internal Medicine',
            'General Surgery',
            'Anesthesiology',
            'Cardiology',
            'Radiology',
            'Urology',
            'Dermatology',
        ];
        $set = [];
        $doc = $conn->query("SELECT DISTINCT specialty FROM doctors WHERE specialty IS NOT NULL AND specialty <> ''");
        if ($doc) { while ($d = $doc->fetch_assoc()) { $set[trim($d['specialty'])] = true; } }
        foreach ($seed as $s) { $set[$s] = true; }
        $stmt = $conn->prepare("INSERT INTO doctor_specialties (name) VALUES (?)");
        if ($stmt) {
            foreach (array_keys($set) as $s) { $stmt->bind_param("s", $s); @$stmt->execute(); }
            $stmt->close();
        }
    }
}