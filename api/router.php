<?php
define('MDC_PAGES', __DIR__ . '/../toddcare-backend/pages');

$page = isset($_GET['page']) ? basename($_GET['page']) : 'index.php';
unset($_GET['page']);

if (!preg_match('/^[a-z0-9_]+\.php$/', $page) || !is_file(MDC_PAGES . '/' . $page)) {
    http_response_code(404);
    exit('Not Found');
}

require MDC_PAGES . '/' . $page;