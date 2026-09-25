<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
include __DIR__ . '/../toddcare-backend/src/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($email) || empty($password)) {
        echo "Email and password are required";
        exit;
    }
    
    $stmt = $conn->prepare("SELECT id, password FROM patients WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows === 0) {
        echo "Invalid email or password";
        $stmt->close();
        exit;
    }
    
    $stmt->bind_result($id, $hashed_password);
    $stmt->fetch();
    $stmt->close();
    
    if (password_verify($password, $hashed_password)) {
        $_SESSION['patient_id'] = $id;
        $is_new = !empty($_SESSION['just_registered']);
        unset($_SESSION['just_registered']);
        $_SESSION['last_login'] = time();
        echo $is_new ? "success:new" : "success";
    } else {
        echo "Invalid email or password";
    }
    
    $conn->close();
}
?>