<?php
require __DIR__ . '/../toddcare-backend/src/session.php';

if (!isset($_SESSION["patient_id"])) {
    echo "error: Please login first";
    exit;
}

include __DIR__ . '/../toddcare-backend/src/db.php';

$patient_id = $_SESSION["patient_id"];
$action     = isset($_POST['action']) ? trim($_POST['action']) : '';

// Validate patient exists
$chk = $conn->prepare("SELECT id FROM patients WHERE id = ?");
$chk->bind_param("i", $patient_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    echo "error: Patient not found. Please login again.";
    $chk->close();
    $conn->close();
    exit;
}
$chk->close();

if ($action === 'update_profile') {
    $name    = isset($_POST['name'])    ? trim($_POST['name'])    : '';
    $email   = isset($_POST['email'])   ? trim($_POST['email'])   : '';
    $contact = isset($_POST['contact']) ? trim($_POST['contact']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $age_txt = isset($_POST['age'])     ? trim($_POST['age'])     : '';

    if ($name === '' || $email === '' || $contact === '') {
        echo "error: Name, email and contact are required";
        $conn->close();
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "error: Please enter a valid email address";
        $conn->close();
        exit;
    }

    $age = null;
    if ($age_txt !== '') {
        if (!is_numeric($age_txt) || (int)$age_txt < 0 || (int)$age_txt > 150) {
            echo "error: Please enter a valid age";
            $conn->close();
            exit;
        }
        $age = (int)$age_txt;
    }

    // Email must stay unique (excluding this patient)
    $dup = $conn->prepare("SELECT id FROM patients WHERE email = ? AND id <> ?");
    $dup->bind_param("si", $email, $patient_id);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        echo "error: That email is already in use by another account";
        $dup->close();
        $conn->close();
        exit;
    }
    $dup->close();

    $stmt = $conn->prepare("UPDATE patients SET name = ?, email = ?, contact = ?, address = ?, age = ? WHERE id = ?");
    $stmt->bind_param("ssssii", $name, $email, $contact, $address, $age, $patient_id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error: Failed to update profile â€” " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    exit;
}

if ($action === 'update_metrics') {
    $height_txt = isset($_POST['height_cm']) ? trim($_POST['height_cm']) : '';
    $weight_txt = isset($_POST['weight_kg']) ? trim($_POST['weight_kg']) : '';

    $height = null;
    if ($height_txt !== '') {
        if (!is_numeric($height_txt) || (float)$height_txt <= 0 || (float)$height_txt > 300) {
            echo "error: Please enter a valid height (30â€“300 cm)";
            $conn->close();
            exit;
        }
        $height = (float)$height_txt;
    }

    $weight = null;
    if ($weight_txt !== '') {
        if (!is_numeric($weight_txt) || (float)$weight_txt <= 0 || (float)$weight_txt > 500) {
            echo "error: Please enter a valid weight (1â€“500 kg)";
            $conn->close();
            exit;
        }
        $weight = (float)$weight_txt;
    }

    $stmt = $conn->prepare("UPDATE patients SET height_cm = ?, weight_kg = ? WHERE id = ?");
    $stmt->bind_param("ddi", $height, $weight, $patient_id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error: Failed to update measurements â€” " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    exit;
}

echo "error: Invalid request";
$conn->close();
?>