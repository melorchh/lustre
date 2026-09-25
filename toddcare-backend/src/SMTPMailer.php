<?php
/**
 * Self-contained SMTP mailer (no Composer / PHPMailer required).
 * Uses PHP stream sockets so it works on any PHP 7.4+ build.
 *
 * Configure credentials in SMTPConfig.php, then use:
 *   $mail = new SMTPMailer();
 *   $ok   = $mail->send($to, $subject, $plainBody);
 */

if (!class_exists('SMTPMailer')) {
    class SMTPMailer
    {
        private $host;
        private $port;
        private $username;
        private $password;
        private $fromEmail;
        private $fromName;
        private $secure; // 'tls' or 'ssl'

        public function __construct()
        {
            $cfg = require __DIR__ . '/SMTPConfig.php';

            $this->host      = $cfg['host'];
            $this->port      = $cfg['port'];
            $this->username  = $cfg['username'];
            $this->password  = $cfg['password'];
            $this->fromEmail = $cfg['from_email'];
            $this->fromName  = $cfg['from_name'];
            $this->secure    = $cfg['secure'];
        }

        /**
         * Send a plain-text email.
         *
         * @param string      $to      Recipient email address
         * @param string      $subject Email subject (UTF-8)
         * @param string      $body    Plain-text body
         * @param string|null $toName  Optional recipient display name
         *
         * @return bool True on success
         *
         * @throws Exception On SMTP errors
         */
        public function send($to, $subject, $body, $toName = null)
        {
            $fromHeader = $this->fromName
                ? sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($this->fromName), $this->fromEmail)
                : $this->fromEmail;

            $toHeader = $toName
                ? sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($toName), $to)
                : $to;

            $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

            $boundary = '=_boundary_' . md5(uniqid('', true));
            $headers  = array(
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                'From: ' . $fromHeader,
                'To: ' . $toHeader,
                'Subject: ' . $subject,
                'Reply-To: ' . $this->fromEmail,
                'Date: ' . date('r'),
                'Message-ID: <' . uniqid('med', true) . '@' . $this->host . '>',
                'X-Mailer: LustreMDC SMTPMailer'
            );

            $message = "--" . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $body . "\r\n"
                . "--" . $boundary . "--\r\n";

            $conn = $this->connect();

            $this->expect($conn, '220');

            $this->ehlo($conn);
            $this->expect($conn, '250');

            // Start TLS and re-EHLO
            if ($this->secure === 'tls') {
                fwrite($conn, "STARTTLS\r\n");
                $this->expect($conn, '220');

                if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                    fclose($conn);
                    throw new Exception('Failed to enable TLS encryption.');
                }

                $this->ehlo($conn);
                $this->expect($conn, '250');
            }

            // Authenticate if credentials provided
            if ($this->username !== '' && $this->password !== '') {
                fwrite($conn, "AUTH LOGIN\r\n");
                $this->expect($conn, '334');
                fwrite($conn, base64_encode($this->username) . "\r\n");
                $this->expect($conn, '334');
                fwrite($conn, base64_encode($this->password) . "\r\n");
                $this->expect($conn, '235');
            }

            fwrite($conn, 'MAIL FROM: <' . $this->fromEmail . ">\r\n");
            $this->expect($conn, '250');

            fwrite($conn, 'RCPT TO: <' . $to . ">\r\n");
            $this->expect($conn, '250');

            fwrite($conn, "DATA\r\n");
            $this->expect($conn, '354');

            fwrite($conn, implode("\r\n", $headers) . "\r\n\r\n" . $message . "\r\n.\r\n");
            $this->expect($conn, '250');

            fwrite($conn, "QUIT\r\n");
            fclose($conn);

            return true;
        }

        private function connect()
        {
            $sslPrefix = ($this->secure === 'ssl') ? 'ssl://' : '';
            $remote    = $sslPrefix . $this->host . ':' . $this->port;

            $conn = @stream_socket_client($remote, $errno, $errstr, 30);
            if (!$conn) {
                throw new Exception("Could not connect to SMTP server ($remote): $errstr");
            }
            stream_set_timeout($conn, 30);
            return $conn;
        }

        private function ehlo($conn)
        {
            fwrite($conn, 'EHLO ' . gethostname() . "\r\n");
        }

        private function expect($conn, $code)
        {
            $raw = '';
            $last = '';
            do {
                $line = fgets($conn, 515);
                if ($line === false) {
                    break;
                }
                $raw .= $line;
                $last = $line;
            } while (strlen($line) >= 4 && $line[3] === '-');

            // Validate against the LAST status line (the final "250 ..." code)
            if (strpos($last, $code . ' ') !== 0) {
                throw new Exception("Unexpected SMTP response, expected $code, got: " . trim($raw));
            }
            return trim($last);
        }
    }
}