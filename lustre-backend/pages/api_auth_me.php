<?php
// GET  -> {ok, role, id, name, username, mustChange}
// Shared identity endpoint for both React staff portals. 401 when no
// staff session exists, so the client can bounce to the right login page.
require_once __DIR__ . '/../src/api.php';

if (!empty($_SESSION['admin_id'])) {
    api_ok(array(
        'role'       => 'admin',
        'portal'     => 'admin',
        'id'         => (int)$_SESSION['admin_id'],
        'name'       => (string)($_SESSION['admin_name'] ?? ''),
        'username'   => (string)($_SESSION['admin_username'] ?? ''),
        'mustChange' => !empty($_SESSION['admin_must_change']),
        'loginUrl'   => 'admin_login.php',
        'logoutUrl'  => 'admin_logout.php',
        'changePasswordUrl' => 'admin_change_password.php',
    ));
}

if (!empty($_SESSION['doctor_id'])) {
    api_ok(array(
        'role'       => 'doctor',
        'portal'     => 'doctor',
        'id'         => (int)$_SESSION['doctor_id'],
        'accountId'  => (int)($_SESSION['doctor_account_id'] ?? 0),
        'name'       => (string)($_SESSION['doctor_name'] ?? ''),
        'username'   => (string)($_SESSION['doctor_username'] ?? ''),
        'mustChange' => false,
        'loginUrl'   => 'doctor_login.php',
        'logoutUrl'  => 'doctor_logout.php',
        'changePasswordUrl' => 'doctor_change_password.php',
    ));
}

api_fail('Unauthorized', 401);
