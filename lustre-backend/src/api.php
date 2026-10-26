<?php
// ============================================================================
// JSON API helpers for the React staff portals (admin & doctor).
//
// Endpoints built on this file live in lustre-backend/pages/ as api_*.php and
// are served like every other page (vercel.json rewrite -> api/router.php).
// Responses always follow the {ok: bool, ...} convention established by
// client_vitals_api.php; failures use proper HTTP status codes so the React
// client can redirect to login on 401.
//
//   require_once __DIR__ . '/../src/api.php';   // starts session + $conn
//   $admin_id = api_require_admin();
//   api_ok(array('appointments' => $rows));
// ============================================================================

if (defined('LUSTRE_API_LOADED')) {
    return;
}
define('LUSTRE_API_LOADED', true);

require_once __DIR__ . '/session.php'; // also loads db.php -> $conn

function api_json($payload, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_ok($payload = array())
{
    api_json(array_merge(array('ok' => true), $payload));
}

function api_fail($error, $status = 400)
{
    api_json(array('ok' => false, 'error' => $error), $status);
}

/** @return int admin id, or the request is terminated with 401 JSON. */
function api_require_admin()
{
    if (empty($_SESSION['admin_id'])) {
        api_fail('Unauthorized', 401);
    }
    return (int)$_SESSION['admin_id'];
}

/** @return int doctor id, or the request is terminated with 401 JSON. */
function api_require_doctor()
{
    if (empty($_SESSION['doctor_id'])) {
        api_fail('Unauthorized', 401);
    }
    return (int)$_SESSION['doctor_id'];
}

/** @return array{role: 'admin'|'doctor', id: int} or terminated with 401 JSON. */
function api_require_staff()
{
    if (!empty($_SESSION['admin_id'])) {
        return array('role' => 'admin', 'id' => (int)$_SESSION['admin_id']);
    }
    if (!empty($_SESSION['doctor_id'])) {
        return array('role' => 'doctor', 'id' => (int)$_SESSION['doctor_id']);
    }
    api_fail('Unauthorized', 401);
}

function api_int($key, $default = 0)
{
    $src = isset($_GET[$key]) ? $_GET[$key] : (isset($_POST[$key]) ? $_POST[$key] : null);
    if ($src === null || $src === '') {
        return $default;
    }
    return (int)$src;
}

function api_str($key, $default = '')
{
    $src = isset($_GET[$key]) ? $_GET[$key] : (isset($_POST[$key]) ? $_POST[$key] : null);
    if ($src === null) {
        return $default;
    }
    return trim((string)$src);
}

/** Merge POST form fields with a JSON request body (fetch with JSON.stringify). */
function api_input()
{
    $raw = file_get_contents('php://input');
    if ($raw !== '' && isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return $_POST;
}

function api_post_str($key, $default = '')
{
    $in = api_input();
    return isset($in[$key]) && $in[$key] !== '' ? trim((string)$in[$key]) : $default;
}

function api_post_int($key, $default = 0)
{
    $in = api_input();
    return isset($in[$key]) && $in[$key] !== '' ? (int)$in[$key] : $default;
}

/** Fetch all rows of a mysqli-compat result as an associative array. */
function api_all($result)
{
    $rows = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/** First row of a mysqli-compat result, or null. */
function api_one($result)
{
    if ($result && ($row = $result->fetch_assoc())) {
        return $row;
    }
    return null;
}
