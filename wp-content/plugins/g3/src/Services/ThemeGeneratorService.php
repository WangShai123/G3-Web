<?php
namespace JEALER\G3\Services;

class ThemeGeneratorService {
    public static $instance = null;
    public function __construct()
    {
    }

    public static function run()
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
            'public'    => [
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
            'config'    => [
                'aop.php',
                'components.php',
                'define.php',
                'encrypt.php',
                'rewriteRouter.php',
            ],
            'src'       => [
                'Aspects'     => [],
                'Components'  => [],
                'Controllers' => [],
                'Middleware'  => [],
            ],
            'archive'   => ['default.php'],
            'category'  => ['default.php'],
            'editor'    => ['default.php'],
            'my'        => ['default.php'],
            'parts'     => [],
            'single'    => ['default.php'],
            'tag'       => ['default.php'],
            'taxonomy'  => ['default.php'],
            'templates' => [],
            'user'      => ['default.php'],
            '404.php',
            'footer.php',
            'functions.php',
            'header.php',
            'index.php',
            'page.php',
            'readme.txt',
            'style.css',
            'screenshot.png',
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
use JEALER\G3\Utilities\Post;
?>
<!DOCTYPE html>
<html lang="<?php bloginfo('language'); ?>" <?php html_class(); ?>>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="{$params['author']}">
    <meta name="robots" content="index,follow">
    <title><?php echo Post::getTitle(); ?></title>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
EOT;
        file_put_contents($themePath . '/header.php', $headerPhp);

        // write footer.php
        $footerPhp = <<<EOT
<?php wp_footer(); ?>
</body>

</html>
EOT;
        file_put_contents($themePath . '/footer.php', $footerPhp);

        // write index.php
        $indexPhp = <<<EOT
<?php
get_header();
?>
<h1 style="text-align: center">Hello!</h1>
<p style="text-align: center">Welcome to my website based on G3-Web.</p>
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