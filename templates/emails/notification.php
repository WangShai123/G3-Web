<?php
/**
 * Notification Email Template
 * 
 * 通知邮件模板
 * 
 * 可用变量:
 * - $title: 通知标题
 * - $message: 通知内容
 * - $action_url: 操作链接（可选）
 * - $action_text: 操作按钮文字（可选）
 * 
 * @since 1.0.0
 */

$title       = $title ?? 'Notification';
$message     = $message ?? 'You have a new notification.';
$site_name   = get_bloginfo('name');
$action_url  = $action_url ?? '';
$action_text = $action_text ?? 'View Details';

return [
    'subject' => $title,
    'message' => "
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$title}</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #f8f9fa; padding: 20px; text-align: center; border-bottom: 3px solid #007cba; }
            .content { padding: 20px; }
            .message { background-color: #f8f9fa; padding: 15px; border-left: 4px solid #007cba; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background-color: #007cba; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>{$title}</h1>
            </div>
            <div class='content'>
                <div class='message'>
                    {$message}
                </div>
                " . ($action_url ? "<p style='text-align: center;'><a href='{$action_url}' class='button'>{$action_text}</a></p>" : "") . "
            </div>
            <div class='footer'>
                <p>This notification was sent from {$site_name}.</p>
                <p>If you no longer wish to receive these notifications, please contact the administrator.</p>
            </div>
        </div>
    </body>
    </html>
    "
];
