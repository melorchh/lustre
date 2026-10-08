<?php
define('LUSTRE_PAGES', __DIR__ . '/../lustre-backend/pages');

$page = isset($_GET['page']) ? basename($_GET['page']) : 'index.php';
unset($_GET['page']);

if (!preg_match('/^[a-z0-9_]+\.php$/', $page) || !is_file(LUSTRE_PAGES . '/' . $page)) {
    http_response_code(404);
    exit('Not Found');
}

require LUSTRE_PAGES . '/' . $page;
