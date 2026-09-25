<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
include __DIR__ . '/../toddcare-backend/src/db.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo "Invalid request";
    $conn->close();
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($username === '' || $password === '') {
    echo "Username and password are required";
    $conn->close();
    exit;
}

// Fetch the account by username only (never compare raw password in SQL)
$stmt = $conn->prepare("SELECT id, password, full_name, must_change_password, failed_attempts, locked_until FROM admin_users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "Invalid username or password";
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->bind_result($id, $stored, $full_name, $must_change, $failed_attempts, $locked_until);
$stmt->fetch();
$stmt->close();

// Lockout check
if (!empty($locked_until)) {
    $lock = new DateTime($locked_until);
    $now  = new DateTime();
    if ($lock > $now) {
        $mins = (int)ceil(($lock->getTimestamp() - $now->getTimestamp()) / 60);
        echo "Too many failed attempts. Account locked. Try again in about $mins minute(s).";
        $conn->close();
        exit;
    }
    // Lock expired -> reset counters
    $conn->query("UPDATE admin_users SET failed_attempts = 0, locked_until = NULL WHERE id = $id");
    $failed_attempts = 0;
}

// Verify: bcrypt hash (new) or legacy plaintext (old accounts, upgrade on success)
$isHash = preg_match('/^\$2[aby]\$/', $stored) === 1;
if ($isHash) {
    $valid = password_verify($password, $stored);
} else {
    $valid = hash_equals($stored, $password);
}

if (!$valid) {
    $failed_attempts++;
    if ($failed_attempts >= 5) {
        $conn->query("UPDATE admin_users SET failed_attempts = 0, locked_until = NOW() + INTERVAL '15 minutes' WHERE id = $id");
        echo "Too many failed attempts. Account locked for 15 minutes.";
    } else {
        $conn->query("UPDATE admin_users SET failed_attempts = $failed_attempts WHERE id = $id");
        $remaining = 5 - $failed_attempts;
        echo "Invalid username or password. $remaining attempt(s) remaining before lockout.";
    }
    $conn->close();
    exit;
}

// Success -> reset counters
$conn->query("UPDATE admin_users SET failed_attempts = 0, locked_until = NULL WHERE id = $id");

// Legacy plaintext found in the DB -> hash it now and force a change (it was exposed)
if (!$isHash) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $upd  = $conn->prepare("UPDATE admin_users SET password = ?, must_change_password = 1 WHERE id = ?");
    $upd->bind_param("si", $hash, $id);
    $upd->execute();
    $upd->close();
    $must_change = 1;
}

// Set session
$_SESSION['admin_id']         = $id;
$_SESSION['admin_name']       = $full_name;
$_SESSION['admin_username']   = $username;
$_SESSION['admin_must_change'] = $must_change ? 1 : 0;

echo $must_change ? "change_password" : "success";

$conn->close();
?>