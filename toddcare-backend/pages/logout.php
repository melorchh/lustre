<?php
require __DIR__ . '/../src/session.php';
session_destroy();
header("Location: index.php");
exit;
?>