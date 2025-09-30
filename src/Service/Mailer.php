<?php
declare(strict_types=1);

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    private static ?array $cachedConfig = null;

    /**
     * Send an email using PHPMailer with config from env or paramMAIL.txt.
     */
    public static function send(string $toEmail, string $toName, string $subject, string $html, string $text): bool
    {
        $cfg = self::config();
        $mail = new PHPMailer(true);
        try {
            // Force UTF-8 to avoid mojibake like "Ã©" in emails
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            if (!empty($cfg['transport']) && $cfg['transport'] === 'mail') {
                $mail->isMail();
            } else {
                $mail->isSMTP();
                $mail->Host = $cfg['host'] ?? '127.0.0.1';
                $mail->Port = (int)($cfg['port'] ?? 1025);
                $mail->SMTPAuth = (bool)($cfg['auth'] ?? false);
                if (!empty($cfg['secure'])) {
                    $mail->SMTPSecure = $cfg['secure']; // 'tls' or 'ssl'
                }
                if (!empty($cfg['username'])) $mail->Username = $cfg['username'];
                if (!empty($cfg['password'])) $mail->Password = $cfg['password'];
            }

            $from = $cfg['from'] ?? 'no-reply@example.com';
            $fromName = $cfg['from_name'] ?? 'Champions League';
            $mail->setFrom($from, $fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('[Mailer] send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Load config from environment or paramMAIL.txt
     */
    private static function config(): array
    {
        if (self::$cachedConfig !== null) return self::$cachedConfig;

        $cfg = [
            'host' => getenv('SMTP_HOST') ?: null,
            'port' => getenv('SMTP_PORT') ?: null,
            'username' => getenv('SMTP_USER') ?: null,
            'password' => getenv('SMTP_PASS') ?: null,
            'secure' => getenv('SMTP_SECURE') ?: null, // tls / ssl
            'auth' => getenv('SMTP_AUTH') ? (strtolower((string)getenv('SMTP_AUTH')) !== 'false') : null,
            'from' => getenv('SMTP_FROM') ?: null,
            'from_name' => getenv('SMTP_FROM_NAME') ?: null,
            'transport' => getenv('MAIL_TRANSPORT') ?: null, // 'smtp' (default) or 'mail'
        ];

        // If not set, try paramMAIL.txt at project root
        $file = __DIR__ . '/../../paramMAIL.txt';
        if (is_file($file)) {
            foreach (file($file) as $line) {
                if (!preg_match('/^(\w+)\s+(.*)$/', trim($line), $m)) continue;
                $key = strtoupper($m[1]);
                $val = trim($m[2]);
                switch ($key) {
                    case 'SMTP_HOST': $cfg['host'] = $cfg['host'] ?? $val; break;
                    case 'SMTP_PORT': $cfg['port'] = $cfg['port'] ?? $val; break;
                    case 'SMTP_USER': $cfg['username'] = $cfg['username'] ?? $val; break;
                    case 'SMTP_PASS': $cfg['password'] = $cfg['password'] ?? $val; break;
                    case 'SMTP_SECURE': $cfg['secure'] = $cfg['secure'] ?? $val; break;
                    case 'SMTP_AUTH': $cfg['auth'] = $cfg['auth'] ?? (strtolower($val) !== 'false'); break;
                    case 'SMTP_FROM': $cfg['from'] = $cfg['from'] ?? $val; break;
                    case 'SMTP_FROM_NAME': $cfg['from_name'] = $cfg['from_name'] ?? $val; break;
                    case 'MAIL_TRANSPORT': $cfg['transport'] = $cfg['transport'] ?? $val; break;
                }
            }
        }

        // Sensible defaults: dev Mailhog if nothing provided
        if (empty($cfg['host']) && empty($cfg['transport'])) {
            $cfg['host'] = '127.0.0.1';
            $cfg['port'] = $cfg['port'] ?? 1025;
            $cfg['auth'] = false;
        }
        // Fallback from/from_name
        $cfg['from'] = $cfg['from'] ?? 'no-reply@' . (getenv('HTTP_HOST') ?: 'localhost');
        $cfg['from_name'] = $cfg['from_name'] ?? 'Champions League';

        self::$cachedConfig = $cfg;
        return $cfg;
    }
}
