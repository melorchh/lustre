<?php
require __DIR__ . '/../toddcare-backend/src/session.php';

// Clear admin session
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_username']);

// Redirect to login
header("Location: admin_login.php");
exit;
?>