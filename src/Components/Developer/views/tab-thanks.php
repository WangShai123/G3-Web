<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('Thank you to all the open-source programs listed below for their contributions to the world.', 'G3'),
    '',
    'default',
    'mt-4'
);

$list = [
    // server projects
    'WordPress'     => 'https://wordpress.org/',
    'Nginx'         => 'https://nginx.org/',
    'Caddy'         => 'https://caddyserver.com/',
    'PHP'           => 'https://www.php.net/',
    'MySQL'         => 'https://www.mysql.com/',
    'Redis'         => 'https://redis.io/',

    // php projects
    'symfony'       => 'https://symfony.com/',
    'w7corp'        => 'https://github.com/w7corp',
    'mobiledetect'  => 'https://github.com/serbanghita/Mobile-Detect',
    'aliyuncs'      => 'https://github.com/aliyun/aliyun-oss-php-sdk',
    'qcloud'        => 'https://github.com/tencentyun/cos-php-sdk-v5',

    // js projects
    'fingerprintjs' => 'https://github.com/fingerprintjs/fingerprintjs',
    'highlight'     => 'https://github.com/highlightjs/highlight.js',
];

echo '<ol>';
foreach ($list as $name => $url) {
    echo '<li>';
    echo '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $name . '</a>';
    echo '</li>';
}
echo '</ol>';
