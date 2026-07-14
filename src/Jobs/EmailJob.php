<?php
namespace JEALER\G3\Jobs;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Queue\Queue;
use JEALER\G3\Core\Queue\Job;
use JEALER\G3\Services\LogService;
use JEALER\G3\Services\MailerService;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\System;
use WP_Error;
use Throwable;
use Exception;

/**
 * Email Job
 * 
 * 专门用于处理邮件发送的队列任务
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class EmailJob extends Job {

    private LogService $log;

    public function __construct()
    {
        $this->log = Container::use(LogService::class);
    }

    public function handle(array $data): void
    {
        error_log('[G3 EmailJob] handle ready');

        // 验证必需的邮件数据
        $this->validateEmailData($data);

        $to          = $data['to'];
        $subject     = $data['subject'];
        $messages    = $data['messages'];
        $headers     = $data['headers'] ?? [];
        $attachments = $data['attachments'] ?? [];

        // 记录开始发送邮件
        error_log("[G3 EmailJob] Starting to send email to: {$to}");
        error_log("[G3 EmailJob] Subject: {$subject}");

        try {
            // 处理邮件头
            $processedHeaders = $this->processHeaders($headers);

            // 处理附件
            $processedAttachments = $this->processAttachments($attachments);

            // 发送邮件
            $result = MailerService::send($to, $subject, $messages, $processedAttachments, $processedHeaders);

            error_log('[G3 EmailJob] Email sent result: ' . print_r($result, true));

            if ($result !== true) {
                throw new Exception('Failed to send email');
            }

            error_log("[G3 EmailJob] Email sent successfully to: {$to}");

            // 记录发送成功的邮件
            $this->logEmailSent($data);

        }
        catch (Exception $e) {
            error_log("[G3 EmailJob] Failed to send email: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 处理任务失败
     * 
     * @param array $data 邮件数据
     * @param Throwable $exception 异常
     * @return void
     */
    public function failed(array $data, Throwable $exception): void
    {
        $to      = $data['to'] ?? 'unknown';
        $subject = $data['subject'] ?? 'unknown';

        $errorMessage = sprintf(
            'Failed to send email to %s with subject "%s": %s',
            $to,
            $subject,
            $exception->getMessage()
        );

        error_log("[G3 EmailJob] " . $errorMessage);

        // 记录失败的邮件到数据库（可选）
        $this->logEmailFailed($data, $exception);

        // 发送失败通知给管理员（可选）
        $this->notifyAdminOfFailure($data, $exception);
    }

    /**
     * 验证邮件数据
     * 
     * @param array $data 邮件数据
     * @return void
     * @throws Exception
     */
    private function validateEmailData(array $data): void
    {
        $required = ['to', 'subject', 'messages'];

        foreach ($required as $field) {
            // if (empty($data[$field])) {
            //     throw new Exception("Missing required email field: {$field}");
            // }
            if (empty($data[$field])) {
                // 获取调用栈信息，找出调用者
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 10);

                // 查找调用者信息
                $callerInfo = $this->getCallerInfo($backtrace);

                $errorMessage = sprintf(
                    "Missing required email field: %s. Called from: %s:%d in %s::%s(). Full data provided: %s",
                    $field,
                    $callerInfo['file'],
                    $callerInfo['line'],
                    $callerInfo['class'] ?? 'unknown',
                    $callerInfo['function'] ?? 'unknown',
                    json_encode($data, JSON_UNESCAPED_UNICODE)
                );

                throw new Exception($errorMessage);
            }
        }

        // 验证邮件地址格式
        if (!is_email($data['to'])) {
            throw new Exception("Invalid email address: {$data['to']}");
        }

        // 验证邮件主题长度
        if (strlen($data['subject']) > 998) {
            throw new Exception("Email subject too long (max 998 characters)");
        }
    }

    /**
     * 处理邮件头
     * 
     * @param array $headers 原始邮件头
     * @return array 处理后的邮件头（键值对格式）
     */
    private function processHeaders(array $headers = []): array
    {
        $config = MailerService::getConfig();
        $from   = $config['address'] ?? get_option('admin_email');

        // 默认邮件头（与MailerService::send兼容）
        $defaultHeaders = [
            'Content-Type' => 'text/html; charset=UTF-8',
            'From'         => $from,
        ];

        // 处理传入的邮件头
        $processedHeaders = $defaultHeaders;

        foreach ($headers as $key => $value) {
            if (is_numeric($key) && is_string($value)) {
                // 处理字符串格式的头（如 'Content-Type: text/html'）
                if (strpos($value, ':') !== false) {
                    list($headerName, $headerValue)      = explode(':', $value, 2);
                    $processedHeaders[trim($headerName)] = trim($headerValue);
                }
            } else {
                // 处理键值对格式的头
                $processedHeaders[$key] = $value;
            }
        }

        return $processedHeaders;
    }

    /**
     * 处理附件
     * 
     * @param array $attachments 附件路径数组
     * @return array 验证后的附件路径数组
     */
    private function processAttachments(array $attachments): array
    {
        $processedAttachments = [];

        foreach ($attachments as $attachment) {
            if (is_string($attachment) && file_exists($attachment)) {
                $processedAttachments[] = $attachment;
            } else {
                error_log("[G3 EmailJob] Warning: Attachment not found or invalid: " . print_r($attachment, true));
            }
        }

        return $processedAttachments;
    }

    /**
     * Log when email is sent to the database.
     * 
     * 记录邮件发送成功的日志。
     * 
     * @param array $data
     * @return int|WP_Error
     */
    private function logEmailSent(array $data): int|WP_Error
    {
        return $this->log->create(
            'email',
            'success',
            'info',
            'email',
            get_current_user_id(),
            System::ip() ?: null,
            [
                'to'           => $data['to'],
                'subject'      => $data['subject'],
                'status'       => 'sent',
                'sent_at'      => current_time('mysql', true),
                'message_hash' => md5($data['messages']),
            ]
        );
    }

    /**
     * Log when email fails to be sent to the database.
     * 
     * 记录邮件发送失败的日志。
     * 
     * @param array $data
     * @param Throwable $exception
     * @return int|WP_Error
     */
    private function logEmailFailed(array $data, Throwable $exception): int|WP_Error
    {
        return $this->log->create(
            'email',
            'failed',
            'error',
            'email',
            get_current_user_id(),
            System::ip() ?: null,
            [
                'to'            => $data['to'] ?? 'unknown',
                'subject'       => $data['subject'] ?? 'unknown',
                'status'        => 'failed',
                'error_message' => $exception->getMessage(),
                'failed_at'     => current_time('mysql', true),
            ]
        );
    }

    /**
     * Notify admin of email failure.
     * 
     * 通知管理员邮件发送失败。
     * 
     * @param array $data
     * @param Throwable $exception
     * @return void
     */
    private function notifyAdminOfFailure(array $data, Throwable $exception): void
    {
        // Get admin email
        $adminEmail = get_option('admin_email');
        if (!$adminEmail) {
            return;
        }

        // Avoid infinite loop (if sending email to admin also fails)
        if (isset($data['is_admin_notification'])) {
            return;
        }

        $subject  = '[' . get_bloginfo('name') . '] Email Delivery Failed';
        $messages = sprintf(
            "An email failed to be delivered:\n\n" .
            "To: %s\n" .
            "Subject: %s\n" .
            "Error: %s\n" .
            "Time: %s\n\n" .
            "Please check your email configuration.",
            $data['to'] ?? 'unknown',
            $data['subject'] ?? 'unknown',
            $exception->getMessage(),
            current_time('mysql')
        );

        // Send notification directly, not through the queue
        MailerService::send($adminEmail, $subject, $messages);
    }

    /**
     * Send email
     * 
     * 创建邮件任务的静态方法
     * 
     * @param string $to
     * @param string $subject
     * @param string $messages
     * @param array $headers Optional
     * @param array $attachments Optional
     * @param int $delay second, Optional
     * @return mixed job ID
     */
    public static function send(
        string $to,
        string $subject,
        string $messages,
        array $headers = [],
        array $attachments = [],
        int $delay = 0
    )
    {
        $data = [
            'to'          => $to,
            'subject'     => $subject,
            'messages'    => $messages,
            'headers'     => $headers,
            'attachments' => $attachments,
        ];

        $queue = Queue::driver();
        return $queue->push(self::class, $data, $delay);
    }

    /**
     * batch send
     * 
     * 批量发送邮件
     * 
     * @param array $recipients
     * @param string $subject
     * @param string $messages
     * @param array $headers Optional
     * @param array $attachments Optional
     * @param int $delay second, Optional
     * @return array job IDs array
     */
    public static function sendBatch(
        array $recipients,
        string $subject,
        string $messages,
        array $headers = [],
        array $attachments = [],
        int $delay = 0
    ): array
    {
        $jobIds = [];

        foreach ($recipients as $to) {
            if (is_email($to)) {
                $jobIds[] = self::send($to, $subject, $messages, $headers, $attachments, $delay);
            }
        }

        return $jobIds;
    }

    /**
     * Send template email
     * 
     * 发送模板邮件
     * 
     * @param string $to
     * @param string $template
     * @param array $variables
     * @param array $headers Optional
     * @param array $attachments Optional
     * @param int $delay second, Optional
     * @return mixed job ID
     */
    public static function sendTemplate(
        string $to,
        string $template,
        array $variables = [],
        array $headers = [],
        array $attachments = [],
        int $delay = 0
    )
    {

        $templateData = self::loadEmailTemplate($template, $variables);

        if (!$templateData) {
            throw new Exception("[G3 EmailJob] Email template not found: {$template}");
        }

        return self::send(
            $to,
            $templateData['subject'],
            $templateData['messages'],
            $headers,
            $attachments,
            $delay
        );
    }

    /**
     * load email template
     * 
     * 加载邮件模板
     * 
     * @param string $template template name
     * @param array $variables template variables
     * @return array|null template data
     */
    private static function loadEmailTemplate(string $template, array $variables = []): ?array
    {
        // template file path
        $templatePath = dirname(__DIR__, 2) . "/templates/emails/{$template}.php";

        if (!file_exists($templatePath)) {
            return null;
        }

        // extract variables to current scope
        extract($variables);

        ob_start();
        include $templatePath;
        $content = ob_get_clean();

        // parse template content (assuming template file returns array)
        if (is_array($content)) {
            return $content;
        }

        // if template only returns HTML content, assume subject is missing
        return [
            'subject'  => $variables['subject'] ?? 'No Subject',
            'messages' => $content,
        ];
    }

    /**
     * get caller information from backtrace
     * 
     * 获取调用者信息
     * 
     * @param array $backtrace 调用栈
     * @return array 包含调用者信息的数组
     */
    private function getCallerInfo(array $backtrace): array
    {
        $relevantFrame = null;

        foreach ($backtrace as $frame) {
            // 跳过当前类的方法和验证相关方法
            if (
                isset($frame['class']) &&
                strpos($frame['class'], 'EmailJob') !== false
            ) {
                continue;
            }

            // 跳过PHP内部函数
            if (!isset($frame['file']) || !isset($frame['line'])) {
                continue;
            }

            // 跳过系统函数
            if (!isset($frame['class']) && !isset($frame['function'])) {
                continue;
            }

            // 跳过框架内部方法
            if (
                isset($frame['class']) && (
                    strpos($frame['class'], 'Queue') !== false ||
                    strpos($frame['class'], 'MailerService') !== false
                )
            ) {
                continue;
            }

            $relevantFrame = $frame;
            break;
        }

        if (!$relevantFrame) {
            // 如果没找到合适的调用者，返回第一个非当前类的调用栈
            foreach ($backtrace as $frame) {
                if (isset($frame['file']) && isset($frame['line'])) {
                    $relevantFrame = $frame;
                    break;
                }
            }
        }

        return [
            'file'     => $relevantFrame['file'] ?? 'unknown',
            'line'     => $relevantFrame['line'] ?? 'unknown',
            'class'    => $relevantFrame['class'] ?? 'unknown',
            'function' => $relevantFrame['function'] ?? 'unknown',
        ];
    }
}
