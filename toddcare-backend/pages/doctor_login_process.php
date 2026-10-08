<?php
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/staff_auth.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request";
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($username) || empty($password)) {
    echo "Username and password are required";
    exit;
}

$result = verify_doctor_login($conn, $username, $password);
echo $result['msg'];

$conn->close();
?>
