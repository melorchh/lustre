<?php
// ============================================================================
// LustreBot — server-side proxy for the Groq chat completions API.
//
// The landing-page chatbot previously called api.groq.com directly from the
// browser with an embedded API key (a leaked secret). This proxy keeps the key
// in the environment (GROQ_API_KEY / GROQ_MODEL), adds a same-origin guard,
// and forwards the request server-to-server.
//
// Request (POST, JSON):
//   { "model": "...", "messages": [ {role, content}, ... ] }
// Response: passthrough of Groq's /chat/completions JSON.
// ============================================================================

header('Content-Type: application/json; charset=utf-8');

// Local env fallback (env.local.php is gitignored; used for local XAMPP runs).
$__LUSTRE_env = __DIR__ . '/../env.local.php';
if (is_file($__LUSTRE_env)) {
    $__LUSTRE_vals = include $__LUSTRE_env;
    if (is_array($__LUSTRE_vals)) {
        foreach ($__LUSTRE_vals as $__k => $__v) {
            if (!getenv($__k)) {
                putenv($__k . '=' . $__v);
            }
            $_ENV[$__k] = $__v;
        }
    }
    unset($__LUSTRE_vals);
}
unset($__LUSTRE_env);

function LustreBot_env($key, $default = '')
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = isset($_ENV[$key]) ? $_ENV[$key] : '';
    }
    if ($v === '' || $v === false) {
        $v = isset($_SERVER[$key]) ? $_SERVER[$key] : '';
    }
    return ($v === '' || $v === false) ? $default : $v;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$apiKey = LustreBot_env('GROQ_API_KEY');
if ($apiKey === '') {
    http_response_code(503);
    echo json_encode(['error' => 'AI assistant is not configured.']);
    exit;
}

// Same-origin guard: only browser pages served from this host may use the AI.
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
if ($host !== '') {
    $origin = isset($_SERVER['HTTP_ORIGIN'])
        ? $_SERVER['HTTP_ORIGIN']
        : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
    $originHost = parse_url($origin, PHP_URL_HOST);
    if ($originHost !== null && $originHost !== '' && strtolower($originHost) !== strtolower($host)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['messages']) || !is_array($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$model = LustreBot_env('GROQ_MODEL', 'qwen/qwen3.8-27b');

$payload = array(
    'model'       => $model,
    'messages'    => array_slice($input['messages'], 0, 21),
    'max_tokens'  => isset($input['max_tokens']) ? (int)$input['max_tokens'] : 500,
    'temperature' => 0.6,
);

$ctx = stream_context_create(array(
    'http' => array(
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\nAuthorization: Bearer " . $apiKey . "\r\n",
        'content'       => json_encode($payload),
        'timeout'       => 30,
        'ignore_errors' => true,
    ),
));

$resp = @file_get_contents('https://api.groq.com/openai/v1/chat/completions', false, $ctx);

if ($resp === false || $resp === '') {
    http_response_code(502);
    echo json_encode(['error' => 'Upstream request failed']);
    exit;
}

$data = json_decode($resp, true);
if (isset($data['error'])) {
    http_response_code(502);
}

echo $resp;
