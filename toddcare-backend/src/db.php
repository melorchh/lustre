<?php
// ============================================================================
// Database configuration — PostgreSQL via PDO (Supabase on Vercel, MySQL/XAMPP
// for local development is deliberately NOT supported anymore: the whole SQL
// surface was migrated to PostgreSQL in plans/vercel-migration.md).
//
// Connection settings come from environment variables (see .env.example):
//   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_SSLMODE
//
// A fallback file env.local.php (gitignored) may return an array of defaults
// so the app can also run from a plain XAMPP/CLI checkout without exporting
// env vars. Command-line usage (send_reminders.php) also reads these.
// ============================================================================

if (defined('TODDCARE_DB_LOADED')) {
    return;
}
define('TODDCARE_DB_LOADED', true);

require_once __DIR__ . '/mysqli_compat.php';

date_default_timezone_set('Asia/Manila');

function toddcare_env($key, $default = '')
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = isset($_ENV[$key]) ? $_ENV[$key] : '';
    }
    if ($v === '' || $v === false) {
        $v = isset($_SERVER[$key]) ? $_SERVER[$key] : '';
    }
    return ($v === false || $v === '') ? $default : $v;
}

// Optional local fallback: array of DB_*/SMTP_*/GROQ_*/CRON_* overrides.
$__env_local = __DIR__ . '/../env.local.php';
if (is_file($__env_local)) {
    $__env_vals = include $__env_local;
    if (is_array($__env_vals)) {
        foreach ($__env_vals as $__k => $__v) {
            putenv($__k . '=' . $__v);
            $_ENV[$__k] = $__v;
        }
    }
    unset($__env_vals);
}
unset($__env_local);

$dbHost   = toddcare_env('DB_HOST', 'localhost');
$dbPort   = toddcare_env('DB_PORT', '5432');
$dbName   = toddcare_env('DB_NAME', 'postgres');
$dbUser   = toddcare_env('DB_USER', 'postgres');
$dbPass   = toddcare_env('DB_PASSWORD', '');
$dbSsl    = toddcare_env('DB_SSLMODE', 'require');

$dsn = 'pgsql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName;
if ($dbSsl !== '' && $dbSsl !== 'disable') {
    $dsn .= ';sslmode=' . $dbSsl;
}

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_SILENT,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 10,
    ]);
} catch (\Exception $e) {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, 'Database connection failed.' . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
     echo 'DB ERR: ' . $e->getMessage();
    exit(1);
}

// All timestamps are stored as local (Asia/Manila) wall-clock values. Keeping
// the session in the same zone makes NOW()/CURRENT_TIMESTAMP agree with the
// date/time values written by PHP.
try {
    $pdo->exec("SET TIME ZONE 'Asia/Manila'");
} catch (\Exception $e) {
    // Non-fatal: comparisons still work if the server zone coincides.
}

$conn = new DbConn($pdo);
