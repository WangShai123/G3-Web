<?php
namespace JEALER\G3\Services;
use JEALER\G3\Services\SystemService;

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
                'rewriteRouter.php',
                'whiteList.php'
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
            'parts'     => [
                'header' => ['index.php'],
                'footer' => ['index.php'],
            ],
            'single'    => ['index.php'],
            'tag'       => ['index.php'],
            'taxonomy'  => ['index.php'],
            'templates' => ['index.php'],
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

        // prepare template map for files that need custom content
        $templates = [
            'style.css'              => $this->getStyleCssTemplate($params),
            'header.php'             => $this->getHeaderTemplate($params),
            'footer.php'             => $this->getFooterTemplate(),
            'index.php'              => $this->getIndexTemplate(),
            '404.php'                => $this->getDefaultTemplate(),
            'page.php'               => $this->getDefaultTemplate(),
            'parts/footer/index.php' => $this->getPartsFooterTemplate(),
        ];

        // apply array template to config files (except define.php)
        $arrayTemplate = $this->getArrayTemplate();
        if (isset($structure['config']) && is_array($structure['config'])) {
            foreach ($structure['config'] as $cfg) {
                if ($cfg === 'define.php') {
                    continue;
                }
                $templates['config/' . $cfg] = $arrayTemplate;
            }
        }

        // create project structure and write templates
        $this->createStructure($themePath, $structure, $templates);
    }

    private function createStructure(string $base, array $structure, array $templates = [], string $root = null): void
    {
        if ($root === null) {
            $root = $base;
        }

        foreach ($structure as $key => $value) {
            if (is_array($value)) {
                $dir = $base . '/' . $key;
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                $this->createStructure($dir, $value, $templates, $root);
            } else {
                $file     = $base . '/' . $value;
                $relative = ltrim(str_replace($root, '', $file), '/');
                if (!file_exists($file)) {
                    if (isset($templates[$relative])) {
                        file_put_contents($file, $templates[$relative]);
                    } else {
                        file_put_contents($file, $this->getDefaultContent($value));
                    }
                }
            }
        }
    }

    private function getDefaultContent(string $filename): string
    {
        return preg_match('/index\.php$/', $filename) ? $this->getDefaultTemplate() : '';
    }

    private function getDefaultTemplate(): string
    {
        return <<<EOT
<?php
get_header();

// your default template code here

get_footer();
EOT;
    }

    private function getHeaderTemplate(array $params): string
    {
        return <<<EOT
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
    }

    private function getFooterTemplate(): string
    {
        return <<<EOT
<?php
get_template_part('parts/footer/index');
wp_footer();
?>
</body>

</html>
EOT;
    }

    private function getIndexTemplate(): string
    {
        return <<<EOT
<?php
get_header();
?>
<div style="height:90vh">
    <h1 style="text-align:center">Hello!</h1>
    <p style="text-align:center">Welcome to my website built with G3-Web.</p>
</div>
<?php
get_footer();
EOT;
    }

    private function getPartsFooterTemplate(): string
    {
        return <<<EOT
<?php

use JEALER\G3\Services\SystemService;

?>

<p style="width:100%;text-align:center;color:gray;font-size:.75em">
    <?php echo SystemService::icpHtml(); ?>
    &copy;
    <?php echo date('Y'); ?>
</p>
EOT;
    }

    private function getArrayTemplate(): string
    {
        return <<<EOT
<?php
return [

];
EOT;
    }

    private function getStyleCssTemplate(array $params): string
    {
        return <<<EOT
/*
Theme Name: {$params['name']}
Theme URI: {$params['url']}
Description: {$params['description']}
Author: {$params['author']}
Author URI: {$params['authorUrl']}
Version: {$params['version']}
*/
EOT;
    }

}
