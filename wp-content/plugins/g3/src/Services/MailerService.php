<?php
namespace JEALER\G3\Services;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Exception;

/**
 * Mailer Service for sending emails
 * 
 * 邮件发送服务类
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class MailerService {
    const OPTION_KEY = 'g3_option_mail';

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
            // check if PHPMailer exists
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                // try to manually include PHPMailer files
                if (file_exists(ABSPATH . WPINC . '/PHPMailer/PHPMailer.php')) {
                    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
                    require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
                    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
                } else {
                    return __('PHPMailer library not found.', 'G3');
                }
                // check again
                if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    return __('Failed to load PHPMailer library.', 'G3');
                }
            }


            $mail = new PHPMailer(true);

            $config = self::getConfig();

            if (!$config || !is_array($config)) {
                return __('Mailer service is not configured.', 'G3');
            }

            // SMTP configuration
            self::configureSMTP($mail, $config);

            // Set sender information
            $fromEmail = $config['address'];
            $fromName  = $config['nickname'];
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
    private static function configureSMTP(PHPMailer $mail, array $config): void
    {
        if (!empty($config['server']) && !empty($config['address']) && !empty($config['secret'])) {
            $mail->isSMTP();
            $mail->SMTPAuth   = true;
            $mail->Host       = $config['server'];
            $mail->Port       = (int) $config['port'];
            $mail->Username   = $config['address'];
            $mail->Password   = $config['secret'];
            $mail->SMTPSecure = $config['encryption'];
        }
    }

    public static function getConfig(): array|false
    {
        $option = get_option(self::OPTION_KEY);

        if (!isset($option['enable']) || $option['enable'] != '1') return false;

        return [
            'enable'     => $option['enable'] ?? '1',
            'nickname'   => $option['nickname'] ?? '',
            'server'     => $option['server'] ?? '',
            'port'       => $option['port'] ?? '',
            'address'    => $option['address'] ?? '',
            'secret'     => $option['secret'] ?? '',
            'encryption' => $option['encryption'] === '1' ? 'ssl' : ($option['encryption'] === '2' ? 'tls' : '')
        ];
    }
}