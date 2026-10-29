<?php
require __DIR__ . '/../src/session.php';
if (!isset($_SESSION["admin_id"])) { echo "Unauthorized"; exit; }
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/unique_ids.php';
ensure_unique_ids($conn);
require_once __DIR__ . '/../src/staff_auth.php';
ensure_doctor_accounts_table($conn);
require_once __DIR__ . '/../src/doctor_capacity.php';
ensure_doctor_capacity_schema($conn);

$conn->query("ALTER TABLE doctors ADD COLUMN IF NOT EXISTS test_procedures TEXT NOT NULL DEFAULT ''");

function norm_tests($raw)
{
    $lines = array_map('trim', explode("\n", (string)$raw));
    return implode("\n", array_values(array_filter($lines)));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo "Invalid request"; exit; }

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($action === 'add') {
    $name       = isset($_POST['name'])       ? trim($_POST['name'])       : '';
    $specialty  = isset($_POST['specialty'])  ? trim($_POST['specialty'])  : '';
    $schedule   = isset($_POST['schedule'])   ? trim($_POST['schedule'])   : '';
    $experience = isset($_POST['experience']) ? (int)$_POST['experience']  : 0;
    $tests      = norm_tests(isset($_POST['test_procedures']) ? $_POST['test_procedures'] : '');
    $dailyLimit = isset($_POST['daily_limit']) ? (int)$_POST['daily_limit'] : 0;
    if ($dailyLimit < 0) $dailyLimit = 0;

    if (empty($name) || empty($specialty) || empty($schedule)) {
        echo "All fields are required"; exit;
    }

    $stmt = $conn->prepare("INSERT INTO doctors (name, specialty, schedule, experience, test_procedures, daily_limit) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisi", $name, $specialty, $schedule, $experience, $tests, $dailyLimit);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();

} elseif ($action === 'edit') {
    $id         = isset($_POST['doctor_id'])  ? (int)$_POST['doctor_id']   : 0;
    $name       = isset($_POST['name'])       ? trim($_POST['name'])       : '';
    $specialty  = isset($_POST['specialty'])  ? trim($_POST['specialty'])  : '';
    $schedule   = isset($_POST['schedule'])   ? trim($_POST['schedule'])   : '';
    $experience = isset($_POST['experience']) ? (int)$_POST['experience']  : 0;
    $tests      = norm_tests(isset($_POST['test_procedures']) ? $_POST['test_procedures'] : '');
    $dailyLimit = isset($_POST['daily_limit']) ? (int)$_POST['daily_limit'] : 0;
    if ($dailyLimit < 0) $dailyLimit = 0;

    if (!$id || empty($name) || empty($specialty) || empty($schedule)) {
        echo "All fields are required"; exit;
    }

    $stmt = $conn->prepare("UPDATE doctors SET name=?, specialty=?, schedule=?, experience=?, test_procedures=?, daily_limit=? WHERE id=?");
    $stmt->bind_param("sssisii", $name, $specialty, $schedule, $experience, $tests, $dailyLimit, $id);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();

} elseif ($action === 'delete') {
    $id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;

    if (!$id) { echo "Invalid doctor ID"; exit; }

    $stmt = $conn->prepare("DELETE FROM doctors WHERE id=?");
    $stmt->bind_param("i", $id);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();

} elseif ($action === 'add_account') {
    $doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
    $username  = isset($_POST['username'])  ? trim($_POST['username'])  : '';
    $password  = isset($_POST['password'])  ? $_POST['password']        : '';
    $email     = isset($_POST['email'])     ? trim($_POST['email'])     : '';

    if (!$doctor_id) { echo "Please select a doctor"; exit; }
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        echo "Username must be 3-30 characters (letters, numbers, underscore only)";
        exit;
    }
    if (strlen($password) < 8) { echo "Password must be at least 8 characters"; exit; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "A valid email is required for login verification";
        exit;
    }

    $check = $conn->prepare("SELECT id FROM doctors WHERE id = ?");
    $check->bind_param("i", $doctor_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) { $check->close(); echo "Doctor not found"; exit; }
    $check->close();

    $check = $conn->prepare("SELECT id FROM doctor_accounts WHERE doctor_id = ?");
    $check->bind_param("i", $doctor_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) { $check->close(); echo "This doctor already has an account"; exit; }
    $check->close();

    $exists = $conn->prepare("SELECT id FROM doctor_accounts WHERE username = ?");
    $exists->bind_param("s", $username);
    $exists->execute();
    $exists->store_result();
    if ($exists->num_rows > 0) { $exists->close(); echo "Username already taken"; exit; }
    $exists->close();

    if ($email !== '') {
        $existsEmail = $conn->prepare("SELECT id FROM doctor_accounts WHERE LOWER(email) = LOWER(?)");
        $existsEmail->bind_param("s", $email);
        $existsEmail->execute();
        $existsEmail->store_result();
        if ($existsEmail->num_rows > 0) { $existsEmail->close(); echo "Email already used by another doctor account"; exit; }
        $existsEmail->close();
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO doctor_accounts (doctor_id, username, password, email) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $doctor_id, $username, $hashed, $email);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();

} elseif ($action === 'reset_account') {
    $account_id = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;
    $password   = isset($_POST['password'])   ? $_POST['password']        : '';
    $email      = isset($_POST['email'])      ? trim($_POST['email'])     : '';

    if (!$account_id) { echo "Invalid account"; exit; }
    if (strlen($password) < 8 && $email === '') { echo "Password must be at least 8 characters"; exit; }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { echo "Invalid email format"; exit; }

    if ($email !== '') {
        $existsEmail = $conn->prepare("SELECT id FROM doctor_accounts WHERE LOWER(email) = LOWER(?) AND id <> ?");
        $existsEmail->bind_param("si", $email, $account_id);
        $existsEmail->execute();
        $existsEmail->store_result();
        if ($existsEmail->num_rows > 0) { $existsEmail->close(); echo "Email already used by another doctor account"; exit; }
        $existsEmail->close();
    }

    if (strlen($password) >= 8 && $email !== '') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE doctor_accounts SET password = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $hashed, $email, $account_id);
        if ($stmt->execute()) { $stmt->close(); echo "success"; exit; }
        $stmt->close();
        echo "Failed to update account. Please try again.";
    } elseif (strlen($password) >= 8) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE doctor_accounts SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $account_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) { $stmt->close(); echo "success"; exit; }
        $stmt->close();
        echo "Failed to reset password. Please try again.";
    } elseif ($email !== '') {
        $stmt = $conn->prepare("UPDATE doctor_accounts SET email = ? WHERE id = ?");
        $stmt->bind_param("si", $email, $account_id);
        if ($stmt->execute()) { $stmt->close(); echo "success"; exit; }
        $stmt->close();
        echo "Failed to update email. Please try again.";
    } else {
        echo "Nothing to update";
    }

} elseif ($action === 'delete_account') {
    $account_id = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;

    if (!$account_id) { echo "Invalid account"; exit; }

    $stmt = $conn->prepare("DELETE FROM doctor_accounts WHERE id = ?");
    $stmt->bind_param("i", $account_id);
    echo $stmt->execute() ? "success" : "Failed: " . $stmt->error;
    $stmt->close();

} else {
    echo "Unknown action";
}

$conn->close();
?>
