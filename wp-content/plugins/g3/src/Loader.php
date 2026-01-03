<?php
namespace JEALER\G3;

use JEALER\G3\Helper;
use JEALER\G3\Utilities\Common;

class Loader {
    public static $instance = null;
    public function __construct()
    {
        $this->init();
        Helper::loader();
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
        if (!$loader instanceof Helper) {
            $loader = Helper::run();
        }
        add_action('init', [$this, 'textDomain']);
    }
    public function textDomain()
    {
        load_plugin_textdomain('G3', false, 'g3/public/languages');
    }
}