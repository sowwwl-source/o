<?php
declare(strict_types=1);

/**
 * Minimal SMTP mail helper for magic links.
 *
 * Env vars:
 * - SOWWWL_SMTP_HOST
 * - SOWWWL_SMTP_PORT (default 587)
 * - SOWWWL_SMTP_USERNAME (optional)
 * - SOWWWL_SMTP_PASSWORD (optional)
 * - SOWWWL_SMTP_ENCRYPTION: tls|ssl|none (default tls)
 * - SOWWWL_MAGIC_LINK_FROM (email)
 * - SOWWWL_MAGIC_LINK_FROM_NAME (optional)
 */

function sowwwl_try_require_vendor_autoload(): void
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
}

function sowwwl_send_email(string $toEmail, string $subject, string $body, ?string &$error = null): bool
{
    $error = null;

    $toEmail = trim($toEmail);
    if ($toEmail === '') {
        $error = 'Missing recipient.';
        return false;
    }

    $from = trim((string) (getenv('SOWWWL_MAGIC_LINK_FROM') ?: ''));
    $fromName = trim((string) (getenv('SOWWWL_MAGIC_LINK_FROM_NAME') ?: ''));

    $host = trim((string) (getenv('SOWWWL_SMTP_HOST') ?: ''));
    $portRaw = trim((string) (getenv('SOWWWL_SMTP_PORT') ?: ''));
    $port = $portRaw !== '' && ctype_digit($portRaw) ? (int) $portRaw : 587;
    $username = (string) (getenv('SOWWWL_SMTP_USERNAME') ?: '');
    $password = (string) (getenv('SOWWWL_SMTP_PASSWORD') ?: '');
    $encryption = strtolower(trim((string) (getenv('SOWWWL_SMTP_ENCRYPTION') ?: 'tls')));

    if ($host !== '') {
        sowwwl_try_require_vendor_autoload();

        $phpMailerClass = 'PHPMailer\\PHPMailer\\PHPMailer';
        if (!class_exists($phpMailerClass)) {
            $error = 'SMTP configured but PHPMailer is not installed (vendor/autoload.php missing).';
            return false;
        }

        try {
            /** @var object $mail */
            $mail = new $phpMailerClass(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;

            if ($encryption === 'none' || $encryption === '') {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = 'ssl';
            } else {
                $mail->SMTPSecure = 'tls';
            }

            $mail->SMTPAuth = (trim($username) !== '');
            if ($mail->SMTPAuth) {
                $mail->Username = $username;
                $mail->Password = $password;
            }

            if ($from !== '') {
                $mail->setFrom($from, $fromName !== '' ? $fromName : $from);
            } else {
                $mail->setFrom('no-reply@' . (string) ($_SERVER['HTTP_HOST'] ?? 'sowwwl.com'), 'sowwwl');
            }

            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return true;
        } catch (Throwable $e) {
            $error = 'SMTP send failed: ' . $e->getMessage();
            return false;
        }
    }

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
    ];
    if ($from !== '') {
        $fromHeader = $fromName !== '' ? sprintf('%s <%s>', $fromName, $from) : $from;
        $headers[] = 'From: ' . $fromHeader;
        $headers[] = 'Reply-To: ' . $fromHeader;
    }

    $ok = @mail($toEmail, $subject, $body, implode("\r\n", $headers));
    if (!$ok) {
        $error = 'mail() failed (no SMTP configured; container may not have sendmail).';
    }

    return $ok;
}

function sowwwl_send_magic_link_email(string $toEmail, string $link, ?string &$error = null): bool
{
    $error = null;

    $toEmail = trim($toEmail);
    if ($toEmail === '') {
        $error = 'Missing recipient.';
        return false;
    }

    $from = trim((string) (getenv('SOWWWL_MAGIC_LINK_FROM') ?: ''));
    $fromName = trim((string) (getenv('SOWWWL_MAGIC_LINK_FROM_NAME') ?: ''));

    $subject = 'Lien de connexion sowwwl.com';
    $body = "Bonjour,\n\nVoici ton lien de connexion (valide ~15 minutes) :\n\n" . $link . "\n\nSi tu n’es pas à l’origine de cette demande, ignore cet email.\n";

    return sowwwl_send_email($toEmail, $subject, $body, $error);
}
