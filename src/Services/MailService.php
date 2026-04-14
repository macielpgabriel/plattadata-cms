<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Throwable;

final class MailService
{
    private const BASE_TEMPLATE = <<<'HTML'
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>%s</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; background-color: #f5f5f5;">
    <table width="100%%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table width="100%%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 30px 40px; border-bottom: 3px solid #0f766e;">
                            <img src="%s" alt="Plattadata" style="height: 40px;" onerror="this.style.display='none';this.nextElementSibling.style.display='block';" />
                            <span style="font-size: 24px; font-weight: bold; color: #0f766e; display: none;">Plattadata</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px;">
                            %s
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 40px; background-color: #f9fafb; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; color: #6b7280; text-align: center;">
                                Você está recebendo este e-mail porque possui uma conta na Plattadata.<br>
                                <a href="%s" style="color: #0f766e; text-decoration: underline;">Cancelar inscription</a>
                            </p>
                            <p style="margin: 10px 0 0; font-size: 11px; color: #9ca3af; text-align: center;">
                                &copy; %d Plattadata. Todos os direitos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    public function send(string $to, string $subject, string $htmlBody, bool $useTemplate = true, ?string $attachmentPath = null, ?string $attachmentName = null): bool
    {
        $to = trim($to);
        if ($to === '') {
            return false;
        }

        $enabled = (bool) config('mail.enabled', false);
        $fromName = (string) config('mail.from_name', 'Plattadata');
        $fromAddress = (string) config('mail.from_address', 'no-reply@plattadata.com');
        $baseUrl = rtrim((string) config('app.url', 'https://plattadata.com'), '/');

        if ($useTemplate) {
            $year = date('Y');
            $unsubscribeLink = $baseUrl . '/unsubscribe?email=' . urlencode($to);
            $logoUrl = file_exists($_SERVER['DOCUMENT_ROOT'] . '/img/logo.png') 
                ? $baseUrl . '/img/logo.png' 
                : $baseUrl;
            $htmlBody = sprintf(self::BASE_TEMPLATE, e($subject), $logoUrl, $htmlBody, $unsubscribeLink, $year);
        }

        $sent = false;
        $error = null;

        try {
            if ($enabled) {
                $mailer = env('MAIL_MAILER', 'smtp');

                if ($mailer === 'smtp') {
                    $sent = $this->sendViaSmtp($to, $subject, $htmlBody, $fromName, $fromAddress, $attachmentPath, $attachmentName);
                } else {
                    $headers = [
                        'MIME-Version: 1.0',
                        'Content-type: text/html; charset=UTF-8',
                        'From: ' . $fromName . ' <' . $fromAddress . '>',
                        'Reply-To: ' . $fromAddress,
                        'X-Mailer: Plattadata',
                        'X-Report-Abuse: <' . $fromAddress . '?subject=abuse>',
                        'Return-Path: ' . (string) env('MAIL_SMTP_RETURN_PATH', $fromAddress),
                        'Precedence: bulk',
                    ];
                    $sent = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
                }
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $this->logEmail($to, $subject, $htmlBody, $enabled, $sent, $error);

        return $sent;
    }

    private function sendViaSmtp(string $to, string $subject, string $htmlBody, string $fromName, string $fromAddress, ?string $attachmentPath = null, ?string $attachmentName = null): bool
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        $mail->isSMTP();
        $mail->Host = (string) env('MAIL_SMTP_HOST', 'smtp.gmail.com');
        $mail->Port = (int) env('MAIL_SMTP_PORT', '465');
        $mail->SMTPSecure = env('MAIL_SMTP_ENCRYPTION', 'ssl') === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->SMTPAuth = true;
        $mail->Username = (string) env('MAIL_SMTP_USERNAME', '');
        $mail->Password = (string) env('MAIL_SMTP_PASSWORD', '');
        
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $htmlBody));

        if ($attachmentPath && file_exists($attachmentPath)) {
            $name = $attachmentName ?: basename($attachmentPath);
            $mail->addAttachment($attachmentPath, $name);
        }

        $returnPath = (string) env('MAIL_SMTP_RETURN_PATH', $fromAddress);
        $mail->Sender = $returnPath;

        $mail->addCustomHeader('List-Unsubscribe', '<' . $fromAddress . '?subject=unsubscribe>');
        $mail->addCustomHeader('X-Priority', '3');
        $mail->addCustomHeader('X-Mailer', 'Plattadata');
        $mail->addCustomHeader('X-Report-Abuse', '<' . $fromAddress . '?subject=abuse>');
        $mail->addCustomHeader('Precedence', 'bulk');

        return (bool) $mail->send();
    }

    private function logEmail(string $to, string $subject, string $htmlBody, bool $enabled, bool $sent, ?string $error): void
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO email_logs (recipient, subject, body, enabled, sent, error_message, created_at) VALUES (:recipient, :subject, :body, :enabled, :sent, :error_message, NOW())'
            );
            $stmt->execute([
                'recipient' => $to,
                'subject' => $subject,
                'body' => $htmlBody,
                'enabled' => $enabled ? 1 : 0,
                'sent' => $sent ? 1 : 0,
                'error_message' => $error,
            ]);
        } catch (Throwable) {
        }
    }
}
