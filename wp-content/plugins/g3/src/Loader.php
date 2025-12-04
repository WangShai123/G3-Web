<?php
namespace JEALER\G3;
use JEALER\G3\JL;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Common;
class Loader
{
    public static $instance = null;
    public function __construct()
    {
        $this->init();
        Components::loader();
    }
    public static function run(): Loader
    {
        if (!isset(self::$instance)) {
            self::$instance = Common::singleton(__CLASS__);
        }
        return self::$instance;
    }
    private function init()
    {
        global $loader;
        if (!$loader instanceof JL) {
            $loader = Common::singleton('JEALER\G3\JL');
        }
        add_action('init', [$this, 'textDomain']);
    }
    public function textDomain()
    {
        load_plugin_textdomain('G3', false, 'g3/public/languages');
    }
}