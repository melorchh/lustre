<?php
/**
 * SMTP configuration for LustreMDC.
 *
 * Credentials now come from environment variables (see .env.example):
 *   SMTP_HOST, SMTP_PORT, SMTP_SECURE, SMTP_USERNAME,
 *   SMTP_PASSWORD, SMTP_FROM, SMTP_FROM_NAME
 *
 * A gitignored env.local.php fallback (see db.php) may also provide them, so
 * a local XAMPP checkout can keep using hardcoded values without exposing
 * them in source control.
 */

if (!function_exists('smtp_env')) {
    function smtp_env($key, $default = '')
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
}

return array(
    'host'       => smtp_env('SMTP_HOST', 'smtp.gmail.com'),
    'port'       => (int)smtp_env('SMTP_PORT', '587'),
    'secure'     => smtp_env('SMTP_SECURE', 'tls'),   // tls (587) or ssl (465)
    'username'   => smtp_env('SMTP_USERNAME', ''),
    'password'   => smtp_env('SMTP_PASSWORD', ''),
    'from_email' => smtp_env('SMTP_FROM', ''),
    'from_name'  => smtp_env('SMTP_FROM_NAME', 'LustreMDC Clinics & Diagnostics'),
);