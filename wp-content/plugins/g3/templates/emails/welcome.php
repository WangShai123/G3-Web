<?php
/**
 * Welcome Email Template
 * 
 * 欢迎邮件模板
 * 
 * 可用变量:
 * - $user_name: 用户名
 * - $site_name: 网站名称
 * - $login_url: 登录链接
 * 
 * @since 1.0.0
 */

$user_name = $user_name ?? 'User';
$site_name = $site_name ?? get_bloginfo('name');
$login_url = $login_url ?? wp_login_url();

return [
    'subject' => "Welcome to {$site_name}!",
    'message' => "
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Welcome to {$site_name}</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .button { display: inline-block; padding: 10px 20px; background-color: #007cba; color: white; text-decoration: none; border-radius: 5px; }
            .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to {$site_name}!</h1>
            </div>
            <div class='content'>
                <p>Hello {$user_name},</p>
                <p>Welcome to {$site_name}! We're excited to have you as part of our community.</p>
                <p>Your account has been successfully created. You can now log in and start exploring all the features we have to offer.</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$login_url}' class='button'>Login to Your Account</a>
                </p>
                <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
                <p>Best regards,<br>The {$site_name} Team</p>
            </div>
            <div class='footer'>
                <p>This email was sent from {$site_name}. If you didn't create an account, please ignore this email.</p>
            </div>
        </div>
    </body>
    </html>
    "
];