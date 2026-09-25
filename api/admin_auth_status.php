<?php
require __DIR__ . '/../toddcare-backend/src/session.php';
header('Content-Type: text/plain; charset=utf-8');
if (!isset($_SESSION['admin_id'])) {
    echo 'none';
    exit;
}
echo !empty($_SESSION['admin_must_change']) ? 'change' : 'ok';
?>