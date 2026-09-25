<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
session_destroy();
header("Location: index.php");
exit;
?>