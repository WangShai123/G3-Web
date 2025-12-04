<?php
namespace JEALER\G3\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Mailer Service for sending emails
 * 
 * 邮件发送服务类
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class MailerService {
    public const OPTION_KEY = 'g3_option_mail';

    /**
     * Send email using PHPMailer
     * 
     * 使用 PHPMailer 发送邮件
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $attachments Optional attachments
     * @return bool|string True on success, error message on failure
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function send(string $to, string $subject, string $body, array $attachments = []): bool|string
    {
        try {
            $mail = new PHPMailer(true);

            // SMTP configuration
            self::configureSMTP($mail);

            // Set sender information
            $fromEmail = get_option('admin_email');
            $fromName  = get_bloginfo('name');
            $mail->setFrom($fromEmail, $fromName);

            // Add recipient
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $body;

            // attachments
            foreach ($attachments as $attachment) {
                $mail->addAttachment($attachment);
            }

            // Send
            $mail->send();
            return true;

        }
        catch (PHPMailerException $e) {
            return $e->errorMessage();
        }
        catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Configure SMTP settings
     * 
     * 配置 SMTP 设置
     * 
     * @param PHPMailer $mail PHPMailer instance
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function configureSMTP(PHPMailer $mail): void
    {
        $option = get_option(self::OPTION_KEY);

        if (!isset($option['enable']) || $option['enable'] != '1') return;

        $smtpName   = $option['nickname'] ?? '';
        $smtpHost   = $option['server'] ?? '';
        $smtpPort   = $option['port'] ?? '';
        $smtpUser   = $option['address'] ?? '';
        $smtpPass   = $option['secret'] ?? '';
        $smtpSecure = $option['encryption'] === '1' ? 'ssl' : ($option['encryption'] === '2' ? 'tls' : '');

        if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
            $mail->isSMTP();
            $mail->isHTML(true);
            $mail->CharSet    = 'UTF-8';
            $mail->SMTPAuth   = true;
            $mail->Host       = $smtpHost;
            $mail->Port       = (int) $smtpPort;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = $smtpSecure;
            $mail->setFrom($smtpUser, $smtpName, false);
        }
    }
}