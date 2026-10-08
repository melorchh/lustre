<?php
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/staff_auth.php';

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

$result = verify_admin_login($conn, $username, $password);
echo $result['msg'];

$conn->close();
?>
