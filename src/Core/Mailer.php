<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Mail sending helper.
 *
 * Sends e-mails without any external library.
 * When SMTP_HOST is configured, a native SMTP connection (with optional
 * STARTTLS / SSL) is opened via PHP stream sockets. Otherwise the built-in
 * mail() function is used as a fallback.
 */
class Mailer
{
    private const TIMEOUT = 15;

    public static function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        $smtpHost = defined('SMTP_HOST') ? (string) SMTP_HOST : '';

        if ($smtpHost !== '') {
            return self::sendViaSmtp($to, $subject, $body, $isHtml);
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8',
        ];

        $from = defined('SMTP_FROM') ? (string) SMTP_FROM : '';
        if ($from !== '') {
            $fromName = defined('SMTP_FROM_NAME') ? (string) SMTP_FROM_NAME : $from;
            $headers[] = sprintf('From: %s <%s>', $fromName, $from);
        }

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    // ── Native SMTP implementation ────────────────────────────────────────────

    private static function sendViaSmtp(string $to, string $subject, string $body, bool $isHtml): bool
    {
        try {
            $host       = (string) SMTP_HOST;
            $port       = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
            $user       = defined('SMTP_USER') ? (string) SMTP_USER : '';
            $pass       = defined('SMTP_PASS') ? (string) SMTP_PASS : '';
            $from       = defined('SMTP_FROM') ? (string) SMTP_FROM : $user;
            $fromName   = defined('SMTP_FROM_NAME') ? (string) SMTP_FROM_NAME : 'RSA21-Free';
            $encryption = defined('SMTP_ENCRYPTION') ? strtolower((string) SMTP_ENCRYPTION) : 'tls';

            // SSL wrapping (port 465) vs plain/STARTTLS
            $socketHost = ($encryption === 'ssl') ? 'ssl://' . $host : $host;

            $sock = fsockopen($socketHost, $port, $errno, $errstr, self::TIMEOUT);
            if ($sock === false) {
                throw new \RuntimeException("SMTP connect failed ({$errno}): {$errstr}");
            }
            stream_set_timeout($sock, self::TIMEOUT);

            self::expect($sock, 220);
            self::cmd($sock, 'EHLO ' . self::serverHostname(), 250);

            // Upgrade to TLS if requested (STARTTLS)
            if ($encryption === 'tls') {
                self::cmd($sock, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS negotiation failed.');
                }
                self::cmd($sock, 'EHLO ' . self::serverHostname(), 250);
            }

            // AUTH LOGIN
            if ($user !== '') {
                self::cmd($sock, 'AUTH LOGIN', 334);
                self::cmd($sock, base64_encode($user), 334);
                self::cmd($sock, base64_encode($pass), 235);
            }

            self::cmd($sock, 'MAIL FROM:<' . $from . '>', 250);
            self::cmd($sock, 'RCPT TO:<' . $to . '>', 250);
            self::cmd($sock, 'DATA', 354);

            // Build message
            $boundary = 'RSA21_' . bin2hex(random_bytes(8));
            $message  = self::buildMessage($to, $from, $fromName, $subject, $body, $isHtml, $boundary);

            fwrite($sock, $message . "\r\n.\r\n");
            self::expect($sock, 250);

            self::cmd($sock, 'QUIT', 221);
            fclose($sock);

            return true;
        } catch (Throwable $exception) {
            Logger::error('Mail delivery failed.', ['exception' => $exception->getMessage(), 'recipient' => $to]);

            return false;
        }
    }

    /** Build a minimal RFC 2822 message string. */
    private static function buildMessage(
        string $to,
        string $from,
        string $fromName,
        string $subject,
        string $body,
        bool $isHtml,
        string $boundary
    ): string {
        $encodedSubject  = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $date            = date('r');

        if ($isHtml) {
            $plain = strip_tags($body);
            $msg   = "Date: {$date}\r\n"
                   . "From: {$encodedFromName} <{$from}>\r\n"
                   . "To: {$to}\r\n"
                   . "Subject: {$encodedSubject}\r\n"
                   . "MIME-Version: 1.0\r\n"
                   . "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n"
                   . "\r\n"
                   . "--{$boundary}\r\n"
                   . "Content-Type: text/plain; charset=UTF-8\r\n"
                   . "Content-Transfer-Encoding: base64\r\n"
                   . "\r\n"
                   . chunk_split(base64_encode($plain)) . "\r\n"
                   . "--{$boundary}\r\n"
                   . "Content-Type: text/html; charset=UTF-8\r\n"
                   . "Content-Transfer-Encoding: base64\r\n"
                   . "\r\n"
                   . chunk_split(base64_encode($body)) . "\r\n"
                   . "--{$boundary}--";
        } else {
            $msg = "Date: {$date}\r\n"
                 . "From: {$encodedFromName} <{$from}>\r\n"
                 . "To: {$to}\r\n"
                 . "Subject: {$encodedSubject}\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: base64\r\n"
                 . "\r\n"
                 . chunk_split(base64_encode($body));
        }

        return $msg;
    }

    /** Send a command and assert the expected response code. */
    private static function cmd($sock, string $command, int $expectedCode): string
    {
        fwrite($sock, $command . "\r\n");
        return self::expect($sock, $expectedCode);
    }

    /** Read response lines until the final one and check the status code. */
    private static function expect($sock, int $expectedCode): string
    {
        $response = '';
        do {
            $line = fgets($sock, 512);
            if ($line === false) {
                throw new \RuntimeException('SMTP connection lost while reading response.');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-'); // multi-line response

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException("SMTP error: expected {$expectedCode}, got {$code}. Response: {$response}");
        }

        return $response;
    }

    private static function serverHostname(): string
    {
        return $_SERVER['SERVER_NAME'] ?? gethostname() ?: 'localhost';
    }
}
