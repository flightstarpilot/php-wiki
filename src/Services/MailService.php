<?php
declare(strict_types=1);

namespace Wiki\Services;

use Wiki\Models\Settings;

/**
 * Mail dispatcher.
 *
 * Prefers PHPMailer if present at /vendor/PHPMailer/PHPMailer.php.
 * Falls back to PHP's built-in mail() when PHPMailer is unavailable.
 *
 * Download instructions for PHPMailer (no Composer):
 *   https://github.com/PHPMailer/PHPMailer/releases
 *   Place PHPMailer.php, SMTP.php, Exception.php under /vendor/PHPMailer/
 */
class MailService
{
    public static function send(string $to, string $subject, string $body, array $options = []): array
    {
        if (!Settings::getBool('mail.enabled')) {
            return ['ok' => false, 'message' => 'Mail is disabled'];
        }

        $from      = (string)Settings::get('mail.from_address', '');
        $fromName  = self::render((string)Settings::get('mail.from_name', '{site_name}'));
        $transport = (string)Settings::get('mail.transport', 'smtp');

        if ($from === '') {
            return ['ok' => false, 'message' => 'From address is empty'];
        }

        $phpMailer = WIKI_ROOT . '/vendor/PHPMailer/PHPMailer.php';
        if (is_file($phpMailer)) {
            return self::sendWithPhpMailer($phpMailer, $from, $fromName, $to, $subject, $body, $transport, $options);
        }

        return self::sendWithNativeMail($from, $fromName, $to, $subject, $body);
    }

    private static function sendWithPhpMailer(
        string $phpMailerPath,
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $body,
        string $transport,
        array $options
    ): array {
        $dir = dirname($phpMailerPath);
        require_once $dir . '/Exception.php';
        require_once $phpMailerPath;
        require_once $dir . '/SMTP.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->CharSet = 'UTF-8';
            if ($transport === 'smtp') {
                $mail->isSMTP();
                $mail->Host       = (string)Settings::get('mail.host', '');
                $mail->Port       = (int)Settings::get('mail.port', 587);
                $mail->SMTPAuth   = true;
                $mail->Username   = (string)Settings::get('mail.username', '');
                $mail->Password   = self::decrypt((string)Settings::get('mail.password', ''));

                $enc = (string)Settings::get('mail.encryption', 'tls');
                if ($enc === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($enc === 'tls') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }
            } elseif ($transport === 'sendmail') {
                $mail->isSendmail();
            } else {
                $mail->isMail();
            }

            $mail->setFrom($from, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(!empty($options['html']));
            $mail->Body    = $body;
            if (!empty($options['html']) && !empty($options['alt'])) {
                $mail->AltBody = (string)$options['alt'];
            }
            $mail->send();
            return ['ok' => true, 'message' => 'Sent'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private static function sendWithNativeMail(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $body
    ): array {
        $headers = 'From: ' . self::encodeHeader($fromName) . ' <' . $from . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $ok = @mail($to, self::encodeHeader($subject), $body, $headers);
        return ['ok' => (bool)$ok, 'message' => $ok ? 'Sent via mail()' : 'mail() failed'];
    }

    private static function encodeHeader(string $s): string
    {
        if (preg_match('/[^\x20-\x7e]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }

    /** Render {placeholder} tokens in a mail template. */
    public static function render(string $text, array $vars = []): string
    {
        $vars += [
            'site_name'    => (string)Settings::get('site.name', 'Wiki'),
            'site_url'     => (string)Settings::get('site.base_url', url('')),
            'default_lang' => (string)Settings::get('site.default_lang', 'en-US'),
        ];

        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', (string)$v, $text);
        }
        return $text;
    }

    private static function decrypt(string $blob): string
    {
        if ($blob === '') return '';
        $key = (string)config('app.key', '');
        if ($key === '') return $blob;

        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 17) return $blob;

        $iv  = substr($raw, 0, 16);
        $enc = substr($raw, 16);
        $out = openssl_decrypt($enc, 'AES-256-CBC', hex2bin($key), OPENSSL_RAW_DATA, $iv);
        return $out === false ? '' : $out;
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') return '';
        $key = (string)config('app.key', '');
        if ($key === '' || strlen($key) !== 64) return $plain;

        $iv = random_bytes(16);
        $c  = openssl_encrypt($plain, 'AES-256-CBC', hex2bin($key), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $c);
    }
}