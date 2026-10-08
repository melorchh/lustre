<?php
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/staff_auth.php';

// GET = the endpoint was opened directly (e.g. the About page Sign In link)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: landing.php?signin=1');
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$email    = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($email === '' || $password === '') {
    echo "Email and password are required";
    exit;
}

/* 1) Patient account (lookup by email) */
$stmt = $conn->prepare("SELECT id, password FROM patients WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $hashed_password);
    $stmt->fetch();
    $stmt->close();

    if (password_verify($password, $hashed_password)) {
        unset($_SESSION['doctor_account_id'], $_SESSION['doctor_id'], $_SESSION['doctor_name'], $_SESSION['doctor_username']);
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_username'], $_SESSION['admin_must_change']);
        $_SESSION['patient_id'] = $id;
        $is_new = !empty($_SESSION['just_registered']);
        unset($_SESSION['just_registered']);
        $_SESSION['last_login'] = time();
        echo $is_new ? "success:new" : "success";
        $conn->close();
        exit;
    }
} else {
    $stmt->close();
}

/* 2) Admin account (lookup by username — whatever was typed in the field) */
$admin = verify_admin_login($conn, $email, $password);
if ($admin['found']) {
    if ($admin['ok']) {
        unset($_SESSION['patient_id'], $_SESSION['just_registered']);
        unset($_SESSION['doctor_account_id'], $_SESSION['doctor_id'], $_SESSION['doctor_name'], $_SESSION['doctor_username']);
    }
    echo $admin['ok'] ? ($admin['must_change'] ? "success:admin:change" : "success:admin") : $admin['msg'];
    $conn->close();
    exit;
}

/* 3) Doctor account (lookup by username) */
$doctor = verify_doctor_login($conn, $email, $password);
if ($doctor['found']) {
    if ($doctor['ok']) {
        unset($_SESSION['patient_id'], $_SESSION['just_registered']);
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_username'], $_SESSION['admin_must_change']);
    }
    echo $doctor['ok'] ? "success:doctor" : $doctor['msg'];
    $conn->close();
    exit;
}

echo "Invalid email or password";
$conn->close();
?>
