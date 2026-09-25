<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
include __DIR__ . '/../toddcare-backend/src/db.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request";
    exit;
}

if (!isset($_SESSION['pwd_reset']) || empty($_SESSION['pwd_reset']['email']) || empty($_SESSION['pwd_reset']['verified'])) {
    echo "Session expired. Please repeat the password reset process.";
    exit;
}

// ---- Validate the new password (same rules as registration) ----
$password = isset($_POST['password']) ? $_POST['password'] : '';
if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    echo "Password must be at least 8 characters and include uppercase, lowercase, and a number";
    exit;
}

$email  = $_SESSION['pwd_reset']['email'];
$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE patients SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $hashed, $email);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    unset($_SESSION['pwd_reset']);
    echo "success";
} else {
    echo "Password update failed. Please try again.";
}

$stmt->close();
$conn->close();
?>