<?php
require __DIR__ . '/../src/session.php';
include __DIR__ . '/../src/db.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request";
    exit;
}

if (!isset($_SESSION['pwd_reset']) || empty($_SESSION['pwd_reset']['email'])) {
    echo "Session expired. Please request a new code.";
    exit;
}

$res = $_SESSION['pwd_reset'];

// Already verified â€” nothing more to do
if (!empty($res['verified'])) {
    echo "success";
    exit;
}

$otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
if (!preg_match('/^\d{6}$/', $otp)) {
    echo "Verification code must be exactly 6 digits";
    exit;
}

// Rate-limit OTP attempts (max 10 per reset attempt)
if (++$res['otp_attempts'] > 10) {
    unset($_SESSION['pwd_reset']);
    echo "Too many failed attempts. Please request a new code.";
    exit;
}
$_SESSION['pwd_reset'] = $res;

// ---- Verify OTP ----
$token_hash = hash('sha256', $otp);
$stmt = $conn->prepare(
    "SELECT id FROM email_tokens
     WHERE email = ? AND token = ? AND used = 0 AND expires_at > NOW()
     ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param("ss", $res['email'], $token_hash);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    $check = $conn->prepare(
        "SELECT id FROM email_tokens WHERE email = ? AND token = ? AND used = 0 AND expires_at <= NOW() LIMIT 1"
    );
    $check->bind_param("ss", $res['email'], $token_hash);
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
$conn->prepare("UPDATE email_tokens SET used = 1 WHERE email = ? AND token = ? AND used = 0")->execute([$res['email'], $token_hash]);

$_SESSION['pwd_reset']['verified'] = true;
echo "success";

$conn->close();
?>