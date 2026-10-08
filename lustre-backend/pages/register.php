<?php
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request";
    exit;
}

// ---- Require a staged registration and a submitted OTP ----
if (!isset($_SESSION['reg_pending']) || !isset($_POST['otp'])) {
    echo "Session expired or invalid request. Please try registering again.";
    exit;
}

$reg = $_SESSION['reg_pending'];
$otp = trim($_POST['otp']);

if (!preg_match('/^\d{6}$/', $otp)) {
    echo "Verification code must be exactly 6 digits";
    exit;
}

// Rate-limit OTP attempts (max 10 per registration attempt)
if (++$reg['otp_attempts'] > 10) {
    unset($_SESSION['reg_pending']);
    echo "Too many failed attempts. Please register again.";
    exit;
}
$_SESSION['reg_pending'] = $reg;

// ---- Verify OTP ----
$token_hash = hash('sha256', $otp);
$stmt = $conn->prepare(
    "SELECT id FROM email_tokens
     WHERE email = ? AND token = ? AND used = 0 AND expires_at > NOW()
     ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param("ss", $reg['email'], $token_hash);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    $check = $conn->prepare(
        "SELECT id FROM email_tokens WHERE email = ? AND token = ? AND used = 0 AND expires_at <= NOW() LIMIT 1"
    );
    $check->bind_param("ss", $reg['email'], $token_hash);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        echo "This verification code has expired. Please request a new one.";
    } else {
        $check->close();
        echo "Invalid verification code. Please try again.";
    }
    $conn->close();
    exit;
}
$stmt->close();

// Mark the token as used (one-time use)
$conn->prepare("UPDATE email_tokens SET used = 1 WHERE email = ? AND token = ? AND used = 0")->execute([$reg['email'], $token_hash]);

// ---- OTP verified â€” create the account from staged session data ----
$name     = $reg['name'];
$email    = $reg['email'];
$contact  = $reg['contact'];
$address  = $reg['address'];
$password = $reg['password'];
$gender   = isset($reg['gender']) ? $reg['gender'] : '';

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO patients (name, email, contact, address, password, gender) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $name, $email, $contact, $address, $hashed_password, $gender);

if ($stmt->execute()) {
    unset($_SESSION['reg_pending']);
    $_SESSION['just_registered'] = true;
    echo "success";
} else {
    echo "Registration failed: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>