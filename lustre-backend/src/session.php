<?php
// ============================================================================
// DB-backed PHP sessions for serverless (Vercel) deployments.
//
// Default PHP sessions are file-based, which does not work on Vercel's
// read-only / ephemeral function filesystem. This file swaps in a session
// handler that stores session data in the app_sessions table (see
// supabase/schema.sql) and then starts the session.
//
// It is included in place of the old session_start(); call. db.php is loaded
// here (it is guarded against double-inclusion), so every page that needs a
// session is guaranteed to have a working $conn too.
// ============================================================================

if (defined('LUSTRE_SESSION_LOADED')) {
    return;
}
define('LUSTRE_SESSION_LOADED', true);

require_once __DIR__ . '/db.php';

class LustreSessionHandler implements SessionHandlerInterface
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName)
    {
        return $this->pdo !== null;
    }

    #[\ReturnTypeWillChange]
    public function close()
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function read($id)
    {
        try {
            $stmt = $this->pdo->prepare('SELECT data FROM app_sessions WHERE session_id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (string)$row['data'] : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data)
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO app_sessions (session_id, data, last_active)
                 VALUES (?, ?, CURRENT_TIMESTAMP)
                 ON CONFLICT (session_id)
                 DO UPDATE SET data = EXCLUDED.data, last_active = CURRENT_TIMESTAMP'
            );
            return $stmt->execute([$id, $data]) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function destroy($id)
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE session_id = ?');
            $stmt->execute([$id]);
        } catch (\Throwable $e) {
            // ignore
        }
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc($maxLifetime)
    {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM app_sessions WHERE last_active < CURRENT_TIMESTAMP - (? * INTERVAL \'1 second\')'
            );
            $stmt->execute([$maxLifetime]);
        } catch (\Throwable $e) {
            // ignore
        }
        return true;
    }
}

$handlerPdo = (isset($conn) && $conn instanceof DbConn) ? $conn->getPdo() : null;
session_set_save_handler(new LustreSessionHandler($handlerPdo), true);

$sessionHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_name('lustre_sess');
session_set_cookie_params(array(
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $sessionHttps,
    'httponly' => true,
    'samesite' => 'Lax',
));

session_start();

unset($handlerPdo);
unset($sessionHttps);
