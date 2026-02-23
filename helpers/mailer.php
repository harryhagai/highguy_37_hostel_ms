<?php
if (!function_exists('hostel_send_mail')) {
    function hostel_send_mail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $altBody = ''
    ): array {
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($autoloadPath)) {
            return [
                'ok' => false,
                'error' => 'PHPMailer not installed. Run composer install.',
            ];
        }

        require_once $autoloadPath;
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return [
                'ok' => false,
                'error' => 'PHPMailer class not available after autoload.',
            ];
        }

        $mailConfigPath = __DIR__ . '/../config/mail_config.php';
        $config = is_file($mailConfigPath) ? (require $mailConfigPath) : [];

        $host = trim((string)($config['host'] ?? 'smtp.gmail.com'));
        $port = (int)($config['port'] ?? 587);
        $encryption = trim((string)($config['encryption'] ?? 'tls'));
        $username = trim((string)($config['username'] ?? ''));
        $password = (string)($config['password'] ?? '');
        $fromEmail = trim((string)($config['from_email'] ?? $username));
        $fromName = trim((string)($config['from_name'] ?? 'HostelPro System'));

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient email.'];
        }
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid sender email in config.'];
        }
        if ($username === '' || $password === '') {
            return ['ok' => false, 'error' => 'SMTP credentials are missing.'];
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = $encryption;
            $mail->Port = $port;
            $mail->Timeout = 20;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($fromEmail, $fromName !== '' ? $fromName : $fromEmail);
            $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody !== '' ? $altBody : strip_tags($htmlBody);

            $mail->send();

            return ['ok' => true];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
