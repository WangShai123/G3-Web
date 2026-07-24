<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
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
class MailerService extends Service {
    private array $performance = [];

    // Option key for mail options
    const OPTION_KEY = 'g3_option_mail';

    public function __construct()
    {
        parent::__construct();
        $this->performance = get_option(SystemService::PERFORMANCE_OPTION_KEY, []);
    }

    public function sendMail(string $mailTo, string $subject, string $messages, array $attachments = [], array $headers = [], int $delay = 0): bool|string
    {
        $queue = $this->performance['email'] ?? '0';
        if ($queue === '1') {
            return EmailJob::send($mailTo, $subject, $messages, $headers, $attachments, $delay);
        } else {
            return self::send($mailTo, $subject, $messages, $attachments, $headers);
        }
    }

    /**
     * Render a mail template from the current theme first, then the plugin.
     *
     * @param string $template Template name in templates/mail without .php.
     * @param array $variables Template variables.
     * @param array $defaults Default mail data.
     * @return array|null
     */
    public static function renderTemplate(string $template, array $variables = [], array $defaults = []): ?array
    {
        $templatePath = self::templatePath($template);
        if ($templatePath === null) {
            return null;
        }

        $data = array_merge(self::defaultTemplateVariables(), $variables);
        extract($data, EXTR_SKIP);

        ob_start();
        $returned = include $templatePath;
        $content  = ob_get_clean();

        if (is_array($returned)) {
            $mail = $returned;
        } else {
            $mail = [
                'subject' => $defaults['subject'] ?? __('Notifications'),
                'message' => $content,
            ];
        }

        $message = $mail['message'] ?? $mail['messages'] ?? $mail['body'] ?? $defaults['message'] ?? $defaults['body'] ?? '';

        return [
            'to'      => $mail['to'] ?? $defaults['to'] ?? '',
            'subject' => $mail['subject'] ?? $defaults['subject'] ?? __('Notifications'),
            'message' => $message,
            'body'    => $message,
            'headers' => $mail['headers'] ?? $defaults['headers'] ?? '',
        ];
    }

    public static function sendTemplateMail(string $to, string $template, array $variables = [], array $defaults = []): bool|string
    {
        $mail = self::renderTemplate($template, $variables, array_merge($defaults, ['to' => $to]));
        if ($mail === null) {
            return '[G3 Error] Email template not found.';
        }

        return self::send(
            $to,
            (string) $mail['subject'],
            (string) $mail['message'],
            [],
            is_array($mail['headers']) ? $mail['headers'] : []
        );
    }

    public static function messageHtml(string $title, string $content, string $actionUrl = '', string $actionText = ''): string
    {
        $siteName = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $homeUrl  = home_url('/');
        $button   = '';

        if ($actionUrl !== '' && $actionText !== '') {
            $button = sprintf(
                '<p style="margin:28px 0;text-align:center;"><a href="%s" style="display:inline-block;padding:12px 18px;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:4px;">%s</a></p>',
                esc_url($actionUrl),
                esc_html($actionText)
            );
        }

        return sprintf(
            '<!doctype html><html><head><meta charset="UTF-8"><title>%1$s</title></head><body style="margin:0;background:#f6f7f9;color:#1f2937;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;line-height:1.7;"><div style="max-width:640px;margin:0 auto;padding:32px 16px;"><div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;"><div style="padding:22px 28px;border-bottom:1px solid #e5e7eb;"><h1 style="margin:0;font-size:20px;line-height:1.35;color:#111827;">%2$s</h1></div><div style="padding:26px 28px;font-size:15px;">%3$s%4$s</div><div style="padding:18px 28px;background:#f9fafb;color:#6b7280;font-size:12px;">%5$s<br><a href="%6$s" style="color:#2563eb;">%6$s</a></div></div></div></body></html>',
            esc_attr($title),
            esc_html($title),
            wp_kses_post($content),
            $button,
            esc_html(sprintf(__('This email was sent from %s.', 'G3'), $siteName)),
            esc_url($homeUrl)
        );
    }

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
                    return '[G3 Error] PHPMailer library not found.';
                }
                // check again
                if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    return '[G3 Error] Failed to load PHPMailer library.';
                }
            }

            $mail = new PHPMailer(true);

            $config = self::getConfig();

            if (!$config || !is_array($config)) {
                return '[G3 Error] Mailer service is not configured.';
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
            $result = $mail->send();
            return $result;
        }
        catch (PHPMailerException $e) {
            return $e->errorMessage();
        }
        catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private static function templatePath(string $template): ?string
    {
        $template = trim(str_replace('\\', '/', $template), '/');
        if ($template === '' || str_contains($template, "\0") || str_contains($template, '..')) {
            return null;
        }

        $relative = "templates/mail/{$template}.php";
        $paths    = [];

        if (function_exists('get_stylesheet_directory')) {
            $paths[] = trailingslashit(get_stylesheet_directory()) . $relative;
        }

        $paths[] = dirname(__DIR__, 2) . '/' . $relative;

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function defaultTemplateVariables(): array
    {
        return [
            'siteName'   => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            'siteUrl'    => home_url('/'),
            'adminEmail' => get_option('admin_email'),
        ];
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

    public static function resetLinkExpiredMsg(): string
    {
        return __('<strong>Error:</strong> Your password reset link appears to be invalid. Please request a new link.', 'G3');
    }
}
