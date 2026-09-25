<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
unset($_SESSION['doctor_account_id']);
unset($_SESSION['doctor_id']);
unset($_SESSION['doctor_name']);
unset($_SESSION['doctor_username']);
header("Location: doctor_login.php");
exit;
?>