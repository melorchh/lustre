<?php
// POST  -> {ok}  — role-aware password change for the React staff portals.
// Accepts {currentPassword, newPassword, confirmPassword} (JSON or form POST).
// Rules mirrored verbatim from admin_change_password.php / doctor_change_password.php.
require_once __DIR__ . '/../src/api.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_fail('Method not allowed', 405);
}

$current = api_post_str('current_password');
$new     = api_post_str('new_password');
$confirm = api_post_str('confirm_password');

if ($current === '' || $new === '' || $confirm === '') {
    api_fail('All fields are required.');
}

if ($new !== $confirm) {
    api_fail('New password and confirmation do not match.');
}
if (strlen($new) < 8) {
    api_fail('New password must be at least 8 characters long.');
}
if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
    api_fail('New password must contain both letters and numbers.');
}

// ---- Admin session ----
if (!empty($_SESSION['admin_id'])) {
    $admin_id = (int)$_SESSION['admin_id'];

    $stmt = $conn->prepare("SELECT password FROM admin_users WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        api_fail('Account not found. Please log in again.');
    }
    $stmt->bind_result($stored);
    $stmt->fetch();
    $stmt->close();

    $isHash    = preg_match('/^\$2[aby]\$/', $stored) === 1;
    $okCurrent = $isHash ? password_verify($current, $stored) : hash_equals($stored, $current);
    if (!$okCurrent) {
        api_fail('Current password is incorrect.');
    }
    if (($isHash && password_verify($new, $stored)) || (!$isHash && hash_equals($stored, $new))) {
        api_fail('New password must be different from the current password.');
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd  = $conn->prepare("UPDATE admin_users SET password = ?, must_change_password = 0, password_changed_at = NOW(), failed_attempts = 0, locked_until = NULL WHERE id = ?");
    $upd->bind_param("si", $hash, $admin_id);
    if (!$upd->execute()) {
        $upd->close();
        api_fail('Failed to update password. Please try again.');
    }
    $upd->close();
    $_SESSION['admin_must_change'] = 0;
    api_ok();
}

// ---- Doctor session ----
if (!empty($_SESSION['doctor_account_id'])) {
    $account_id = (int)$_SESSION['doctor_account_id'];

    $stmt = $conn->prepare("SELECT password FROM doctor_accounts WHERE id = ?");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        api_fail('Account not found. Please log in again.');
    }
    $stmt->bind_result($stored);
    $stmt->fetch();
    $stmt->close();

    $isHash    = preg_match('/^\$2[aby]\$/', $stored) === 1;
    $okCurrent = $isHash ? password_verify($current, $stored) : hash_equals($stored, $current);
    if (!$okCurrent) {
        api_fail('Current password is incorrect.');
    }
    if (($isHash && password_verify($new, $stored)) || (!$isHash && hash_equals($stored, $new))) {
        api_fail('New password must be different from the current password.');
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd  = $conn->prepare("UPDATE doctor_accounts SET password = ? WHERE id = ?");
    $upd->bind_param("si", $hash, $account_id);
    if (!$upd->execute()) {
        $upd->close();
        api_fail('Failed to update password. Please try again.');
    }
    $upd->close();
    api_ok();
}

api_fail('Unauthorized', 401);
