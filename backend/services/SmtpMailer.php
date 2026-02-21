<?php
/**
 * SmtpMailer — lightweight SMTP client (no Composer / no PHPMailer required)
 * Supports: plain SMTP, SMTP with STARTTLS, SMTP over SSL (port 465)
 */
class SmtpMailer
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption; // 'none' | 'tls' | 'ssl'
    private $fromEmail;
    private $fromName;
    private $timeout = 30;

    /** @var resource|null */
    private $socket = null;

    public function __construct(array $config)
    {
        $this->host       = $config['host']       ?? '';
        $this->port       = (int)($config['port'] ?? 587);
        $this->username   = $config['username']   ?? '';
        $this->password   = $config['password']   ?? '';
        $this->encryption = strtolower($config['encryption'] ?? 'tls');
        $this->fromEmail  = $config['from_email'] ?? $config['username'] ?? '';
        $this->fromName   = $config['from_name']  ?? '';
    }

    /**
     * Send an email.
     *
     * @param string       $toEmail
     * @param string       $toName
     * @param string       $subject
     * @param string       $htmlBody
     * @param string|null  $textBody   Optional plain-text fallback
     * @param array        $attachments [ ['path'=>..., 'name'=>...], ... ]
     * @return array ['success'=>bool, 'error'=>string|null]
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $attachments = []
    ): array {
        try {
            $this->connect();
            $this->authenticate();
            $this->sendMessage($toEmail, $toName, $subject, $htmlBody, $textBody, $attachments);
            $this->disconnect();
            return ['success' => true, 'error' => null];
        } catch (Exception $e) {
            $this->disconnect();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Connection ──────────────────────────────────────────────────────────

    private function connect(): void
    {
        if ($this->encryption === 'ssl') {
            $address = 'ssl://' . $this->host;
        } else {
            $address = $this->host;
        }

        $errno  = 0;
        $errstr = '';
        $this->socket = @fsockopen($address, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new Exception("Cannot connect to {$this->host}:{$this->port} — {$errstr} ({$errno})");
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Read greeting
        $greeting = $this->readResponse();
        if (!$this->isOk($greeting, 220)) {
            throw new Exception("Bad greeting: $greeting");
        }

        // EHLO
        $ehlo = $this->sendCommand("EHLO " . gethostname());
        if (!$this->isOk($ehlo, 250)) {
            // Fallback to HELO
            $helo = $this->sendCommand("HELO " . gethostname());
            if (!$this->isOk($helo, 250)) {
                throw new Exception("HELO failed: $helo");
            }
        }

        // STARTTLS upgrade for port 587 / 'tls'
        if ($this->encryption === 'tls') {
            $starttls = $this->sendCommand("STARTTLS");
            if (!$this->isOk($starttls, 220)) {
                throw new Exception("STARTTLS failed: $starttls");
            }

            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("TLS handshake failed");
            }

            // Re-EHLO after upgrade
            $ehlo2 = $this->sendCommand("EHLO " . gethostname());
            if (!$this->isOk($ehlo2, 250)) {
                throw new Exception("Post-TLS EHLO failed: $ehlo2");
            }
        }
    }

    private function authenticate(): void
    {
        if (empty($this->username)) return;

        $auth = $this->sendCommand("AUTH LOGIN");
        if (!$this->isOk($auth, 334)) {
            throw new Exception("AUTH LOGIN not accepted: $auth");
        }

        $user = $this->sendCommand(base64_encode($this->username));
        if (!$this->isOk($user, 334)) {
            throw new Exception("Username not accepted: $user");
        }

        $pass = $this->sendCommand(base64_encode($this->password));
        if (!$this->isOk($pass, 235)) {
            throw new Exception("Authentication failed (wrong credentials?): $pass");
        }
    }

    private function sendMessage(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody,
        array $attachments
    ): void {
        $fromFormatted = $this->fromName
            ? '"' . $this->encodeHeader($this->fromName) . '" <' . $this->fromEmail . '>'
            : $this->fromEmail;

        $toFormatted = $toName
            ? '"' . $this->encodeHeader($toName) . '" <' . $toEmail . '>'
            : $toEmail;

        // MAIL FROM
        $from = $this->sendCommand("MAIL FROM: <{$this->fromEmail}>");
        if (!$this->isOk($from, 250)) {
            throw new Exception("MAIL FROM rejected: $from");
        }

        // RCPT TO
        $rcpt = $this->sendCommand("RCPT TO: <{$toEmail}>");
        if (!$this->isOk($rcpt, 250) && !$this->isOk($rcpt, 251)) {
            throw new Exception("RCPT TO rejected: $rcpt");
        }

        // DATA
        $data = $this->sendCommand("DATA");
        if (!$this->isOk($data, 354)) {
            throw new Exception("DATA command failed: $data");
        }

        // Build MIME message
        $boundary = '==_Part_' . md5(uniqid());
        $altBoundary = '==_Alt_' . md5(uniqid());

        $headers  = "From: {$fromFormatted}\r\n";
        $headers .= "To: {$toFormatted}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . time() . "." . md5(uniqid()) . "@" . $this->host . ">\r\n";

        if (!empty($attachments)) {
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
            $body .= $this->buildAlternativeParts($textBody, $htmlBody, $altBoundary);
            $body .= "--{$boundary}\r\n";

            foreach ($attachments as $att) {
                $body .= $this->buildAttachmentPart($att);
            }
            $body .= "--{$boundary}--\r\n";
        } else {
            // multipart/alternative only
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n";
            $body = $this->buildAlternativeParts($textBody, $htmlBody, $altBoundary);
        }

        // Write message then end with \r\n.\r\n
        $this->write($headers . "\r\n" . $body . "\r\n.\r\n");

        $resp = $this->readResponse();
        if (!$this->isOk($resp, 250)) {
            throw new Exception("Message not accepted: $resp");
        }
    }

    private function buildAlternativeParts(?string $text, string $html, string $boundary): string
    {
        $plain = $text ?? strip_tags($html);
        $out   = "--{$boundary}\r\n";
        $out  .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $out  .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $out  .= chunk_split(base64_encode($plain)) . "\r\n";
        $out  .= "--{$boundary}\r\n";
        $out  .= "Content-Type: text/html; charset=UTF-8\r\n";
        $out  .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $out  .= chunk_split(base64_encode($html)) . "\r\n";
        $out  .= "--{$boundary}--\r\n";
        return $out;
    }

    private function buildAttachmentPart(array $att): string
    {
        if (empty($att['path']) || !file_exists($att['path'])) return '';
        $name    = $att['name'] ?? basename($att['path']);
        $mime    = $att['mime'] ?? 'application/octet-stream';
        $content = base64_encode(file_get_contents($att['path']));
        $out  = "Content-Type: {$mime}; name=\"{$name}\"\r\n";
        $out .= "Content-Transfer-Encoding: base64\r\n";
        $out .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
        $out .= chunk_split($content) . "\r\n";
        return $out;
    }

    private function disconnect(): void
    {
        if ($this->socket) {
            @fwrite($this->socket, "QUIT\r\n");
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    // ── Socket helpers ───────────────────────────────────────────────────────

    private function sendCommand(string $command): string
    {
        $this->write($command . "\r\n");
        return $this->readResponse();
    }

    private function write(string $data): void
    {
        if (!$this->socket) throw new Exception("Not connected");
        fwrite($this->socket, $data);
    }

    private function readResponse(): string
    {
        $response = '';
        while ($line = fgets($this->socket, 512)) {
            $response .= $line;
            // Multi-line responses: "250-..." continues, "250 ..." is final
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return trim($response);
    }

    private function isOk(string $response, int $expectedCode): bool
    {
        return strpos($response, (string)$expectedCode) === 0;
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return addslashes($value);
    }

    // ── Static factory from DB settings ──────────────────────────────────────

    public static function fromSettings(array $settings): self
    {
        return new self([
            'host'       => $settings['smtp_host']       ?? '',
            'port'       => $settings['smtp_port']       ?? 587,
            'username'   => $settings['smtp_username']   ?? '',
            'password'   => $settings['smtp_password']   ?? '',
            'encryption' => $settings['smtp_encryption'] ?? 'tls',
            'from_email' => $settings['smtp_from_email'] ?? $settings['smtp_username'] ?? '',
            'from_name'  => $settings['smtp_from_name']  ?? '',
        ]);
    }

    /**
     * Quick connectivity test — returns ['success'=>bool, 'error'=>string|null]
     */
    public static function testConnection(array $settings): array
    {
        try {
            $mailer = self::fromSettings($settings);
            $mailer->connect();
            $mailer->authenticate();
            $mailer->disconnect();
            return ['success' => true, 'error' => null];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
