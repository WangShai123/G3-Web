<?php

namespace JEALER\G3\Services;

use JEALER\G3\Services\SystemService;


/**
 * Theme Generator Service
 * 
 * 主题生成服务
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class ThemeGeneratorService {

    public static ThemeGeneratorService $instance;

    public function __construct()
    {
    }

    public static function run(): ThemeGeneratorService
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create(array $params): void
    {
        $themeBasePath = dirname(__DIR__, 4) . '/themes';
        $themePath     = $themeBasePath . '/' . $params['folder'];

        // define project structure
        $structure = [
            'assets'    => [
                'css'        => ['main.css'],
                'fonts'      => [],
                'images'     => [],
                'javascript' => ['main.js'],
                'languages'  => [],
                'audios'     => [],
                'videos'     => [],
            ],
            'dist'      => [
                'css'        => ['main.css'],
                'fonts'      => [],
                'images'     => [],
                'javascript' => ['main.js'],
                'languages'  => [],
                'audios'     => [],
                'videos'     => [],
            ],
            'public'    => [
                'css'        => ['main.css'],
                'fonts'      => [],
                'images'     => [],
                'javascript' => ['main.js'],
                'languages'  => [],
                'audios'     => [],
                'videos'     => [],
            ],
            'config'    => [
                'aspects.php',
                'components.php',
                'define.php',
                'encrypt.php',
                'queue.php',
                'rewriteRouter.php'
            ],
            'src'       => [
                'Aspects'     => [],
                'Attributes'  => [],
                'Cache'       => [],
                'Components'  => [],
                'Controllers' => [],
                'Examples'    => [],
                'Includes'    => [],
                'Middleware'  => [],
                'Queue'       => [],
                'Services'    => [],
                'Utilities'   => [],
            ],
            'archive'   => ['index.php'],
            'category'  => ['index.php'],
            'editor'    => ['index.php'],
            'my'        => ['index.php'],
            'parts'     => [],
            'single'    => ['index.php'],
            'tag'       => ['index.php'],
            'taxonomy'  => ['index.php'],
            'templates' => [],
            'user'      => ['index.php'],
            '404.php',
            'footer.php',
            'functions.php',
            'header.php',
            'index.php',
            'page.php',
            'README.md',
            'style.css',
            'screenshot.png',
            'license.txt'
        ];

        // create project structure
        $this->createStructure($themePath, $structure);

        // write style.css file
        $styleCss = <<<EOT
/*
Theme Name: {$params['name']}
Theme URI: {$params['url']}
Description: {$params['description']}
Author: {$params['author']}
Author URI: {$params['authorUrl']}
Version: {$params['version']}
*/
EOT;
        file_put_contents($themePath . '/style.css', $styleCss);

        // write header.php file
        $headerPhp = <<<EOT
<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\PostService;
?>
<!DOCTYPE html>
<html lang="<?php bloginfo('language'); ?>" <?php Frontend::htmlClass(); ?>>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="generator" content="G3-Web">
    <meta name="author" content="{$params['author']}">
    <meta name="robots" content="index,follow">
    <title><?php echo PostService::getTitle(); ?></title>
    <meta name="description" content="<?php echo PostService::getDescription(); ?>">
    <meta name="keywords" content="<?php echo PostService::getKeywords(); ?>">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
EOT;
        file_put_contents($themePath . '/header.php', $headerPhp);

        // write footer.php
        $footerPhp = <<<EOT
<?php

get_template_part('parts/footer/general');
wp_footer();
?>
</body>

</html>
EOT;
        file_put_contents($themePath . '/footer.php', $footerPhp);

        // write index.php
        $icp      = SystemService::icpHtml();
        $indexPhp = <<<EOT
<?php
get_header();
?>
<div style="height:95vh;display:flex;flex-direction:column;justify-content:space-between;">
    <div>
        <h1 style="text-align:center">Hello!</h1>
        <p style="text-align:center">Welcome to my website built with G3-Web.</p>
    </div>
    <p style="text-align:center;color:gray;font-size:.75rem">{$icp}</p>
</div>
<?php
get_footer();
EOT;
        file_put_contents($themePath . '/index.php', $indexPhp);

        // write components.php & rewriteRouter with return array
        $returnArray = <<<EOT
<?php
return [

];
EOT;
        file_put_contents($themePath . '/config/components.php', $returnArray);
        file_put_contents($themePath . '/config/rewriteRouter.php', $returnArray);

        // write /parts/footer/general.php
        $footerGeneralPhp = <<<EOT
<?php

use JEALER\G3\Services\SystemService;

?>

<div class="flex justify-end items-center px-4">
    <p style="color:gray;font-size:.75em">
        <?php echo SystemService::icpHtml(); ?>
        &copy;
        <?php echo date('Y'); ?>
    </p>
</div>
EOT;
        file_put_contents($themePath . '/parts/footer/general.php', $footerGeneralPhp);

    }

    private function createStructure(string $base, array $structure): void
    {
        foreach ($structure as $key => $value) {
            if (is_array($value)) {
                $dir = $base . '/' . $key;
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                $this->createStructure($dir, $value);
            } else {
                $file = $base . '/' . $value;
                // file_put_contents($file, '');
                if (!file_exists($file)) {
                    file_put_contents($file, $this->getDefaultContent($value));
                }
            }
        }
    }

    private function getDefaultContent(string $filename): string
    {
        if (preg_match('/default\.php$/', $filename)) {
            return "<?php\n\nget_header();\n// your default template code here\nget_footer();";
        }
        return '';
    }
}
