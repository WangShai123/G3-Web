<?php

namespace JEALER\G3\Services;

use JEALER\G3\Jobs\EmailJob;
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

    /**
     * Option key for mail options
     * 
     * 邮件选项的键
     * 
     * @var string
     * @access public
     */
    const OPTION_KEY = 'g3_option_mail';

    /**
     * Option key for mail template options
     * 
     * 邮件模板选项的键
     * 
     * @var string
     * @access public
     */
    const TEMPLATE_OPTION_KEY = 'g3_option_mail_template';

    /**
     * Send email using PHPMailer
     * 
     * 使用 PHPMailer 发送邮件
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $messages Email messages
     * @param array $attachments Optional attachments
     * @param array $headers Optional headers
     * @return bool|string True on success, error message on failure
     */
    public static function send(string $to, string $subject, string $messages, array $attachments = [], array $headers = []): bool|string
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

            // Add custom headers
            foreach ($headers as $name => $value) {
                $mail->addCustomHeader($name, $value);
            }

            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $messages;

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
     */
    private static function configureSMTP(PHPMailer $mail, array $config): void
    {
        if (!empty($config['server']) && !empty($config['address']) && !empty($config['secret'])) {
            $mail->isSMTP();
            $mail->SMTPAuth   = true;
            $mail->Host       = $config['server'];
            $mail->Port       = (int) $config['port'];
            $mail->From       = $config['address'];
            $mail->FromName   = $config['nickname'];
            $mail->Username   = $config['address'];
            $mail->Password   = $config['secret'];
            $mail->SMTPSecure = $config['encryption'];
        }
    }

    /**
     * Get mail configuration
     * 
     * 获取邮件配置
     * 
     * @return array|false Mail configuration or false if not configured
     */
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
