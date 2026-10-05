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

if (!function_exists('toddcare_env')) {
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

/**
 * Aborts the request with a response that is safe to show publicly.
 *
 * The diagnostic detail (host, port, user, SQLSTATE) only ever goes to the
 * server/function log via error_log(). The visitor gets one of two fixed
 * strings, so a misconfiguration is never confused with a transient outage:
 *
 *   $retryable = true   server could not be reached  -> 503 + Retry-After
 *   $retryable = false  configuration is wrong      -> 500, retrying is futile
 */
function toddcare_db_fail($reason, $detail = '', $retryable = false)
{
    error_log('[toddcare-db] ' . $reason . ' :: ' . $detail);

    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, 'Database connection failed: ' . $reason . PHP_EOL);
        fwrite(STDERR, '  ' . $detail . PHP_EOL);
        exit(1);
    }

    http_response_code($retryable ? 503 : 500);
    header('Content-Type: text/plain; charset=utf-8');
    if ($retryable) {
        header('Retry-After: 60');
    }
    echo $retryable
        ? 'Service temporarily unavailable. Please try again later.'
        : 'The service is misconfigured. Please contact the administrator.';
    exit(1);
}

// DB_HOST defaults to localhost above, so an unset variable would otherwise
// surface as an opaque "connection refused" from the Lambda itself. Call it out
// by name instead. Only DB_HOST is checked: an empty DB_PASSWORD is already
// reported precisely by the 28P01 branch below.
if (toddcare_env('DB_HOST') === '') {
    toddcare_db_fail(
        'DB_HOST is not set',
        'set DB_HOST/DB_PORT/DB_USER/DB_PASSWORD in the Vercel project environment '
        . '(ap-southeast-1 pooler: host=aws-0-ap-southeast-1.pooler.supabase.com, '
        . 'port=6543, user=postgres.<project-ref>)'
    );
}

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_SILENT,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 10,
    ]);
} catch (\Exception $e) {
    $sqlstate = isset($e->errorInfo[0]) ? (string) $e->errorInfo[0] : '';
    $detail   = 'sqlstate=' . ($sqlstate === '' ? '(none)' : $sqlstate)
              . ' host=' . $dbHost . ' port=' . $dbPort
              . ' user=' . $dbUser . ' :: ' . $e->getMessage();

    // SQLSTATE class 08 = connection exception (DNS, TCP timeout, refused).
    if (strncmp($sqlstate, '08', 2) === 0) {
        toddcare_db_fail(
            'database unreachable',
            $detail,
            true
        );
    }

    // Class 28 = invalid authorization, 3D000 = database does not exist. The
    // usual cause is a pooler username missing its "<project-ref>." prefix.
    if (strncmp($sqlstate, '28', 2) === 0 || $sqlstate === '3D000') {
        toddcare_db_fail(
            'database rejected the credentials or database name',
            $detail
        );
    }

    toddcare_db_fail('unexpected database error', $detail);
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
