<?php
/**
 * ---------------------------------------------------------------------
 * JEALER G3 组件系统
 * work with vuejs
 * @废弃
 * @since 1.0.0
 * ---------------------------------------------------------------------
 */

/**
 * 组件实例
 * @global mixed $COMPONENTS
 * @since 1.0.0
 */
global $COMPONENTS;

/**
 * 当前组件实例
 * @global mixed $CURRENT_COMPONENT
 * @since 1.0.0
 */
global $CURRENT_COMPONENT;

/**
 * 初始化组件系统
 * @since 1.0.0
 */
JL_ComponentSystem::init();

/**
 * 组件系统类
 * @since 1.0.0
 * @author tmsh
 * @author Wang Shai
 */
class JL_ComponentSystem {

    /**
     * init component system
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    static public function init(): void
    {
        global $COMPONENTS;
        // init component system instance
        if ($COMPONENTS === null) {
            $COMPONENTS = array();
        }
        // init front-end components
        add_action("init", 'init_component', 99);
        // init back-end components
        add_action("admin_init", 'admin_init_component', 99);
    }

    /**
     * end component system
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    static public function end(): void
    {
        global $COMPONENTS;

        // end component system instance
        $COMPONENTS = null;
    }

    /**
     * init component
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    static public function init_component(): void
    {
        global $COMPONENTS;
        foreach ($COMPONENTS as $component) {
            $component->init_early();
            $component->init();
            $component->init_later();
        }
    }

    /**
     * init admin component
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    static public function admin_init_component(): void
    {
        global $COMPONENTS;
        foreach ($COMPONENTS as $component) {
            $component->admin_init();
        }
    }

    /**
     * add component
     * @param string $slug
     * @param string $component_class_name
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    static public function add_component($slug, $component_class_name)
    {
        if ($component_class_name == null) {
            return;
        }
        if (!class_exists($component_class_name)) {
            wp_die('Sorry! ' . $component_class_name . ' COMPONENT does not exit.');
        }

        global $COMPONENTS;
        if (!array_key_exists($slug, $COMPONENTS)) {
            $COMPONENTS[$slug] = $component_class_name::createInstance($component_class_name);
        }
    }
    static public function do_component($slug, $func = "display", $args = null)
    {
        global $COMPONENTS;
        if (array_key_exists($slug, $COMPONENTS)) {
            return $COMPONENTS[$slug]->$func($args);
        }
        return false;
    }
}

/**
 * 组件基类
 * @since 1.0.0
 * @author tmsh
 * @author Wang Shai
 */
class _JL_BaseComponent {
    // 最大嵌套层级数
    public int $MAX_LEVEL = 64;
    // 组件实例计数
    static protected int $reference = 0;
    // 子组件列表
    public array $children = array();
    // 父组件
    public mixed $parent = null;
    final private function __construct()
    {
        // 实例计数增加
        self::$reference++;
        // 如果实例超过最大层级,抛出错误
        if (self::$reference > $this->MAX_LEVEL) {
            die();
        }
    }
    // 设置父组件
    // @param mixed $p 父组件实例
    final public function set_parent($p): void
    {
        $this->parent = $p;
    }
    final public function get_parent()
    {
        return $this->parent;
    }
    final public static function createInstance($classname, $p = null)
    {
        if (!class_exists($classname)) {
            if (WP_DEBUG) {
                wp_die('Sorry! ' . $classname . ' CLASS does not exit.');
            }
            return null;
        } else {
            $component = (new $classname);
            $component->create();
            $component->set_parent($p);
            self::$reference--;
            return $component;
        }
    }
    final public function add_component($slug, $component_name)
    {
        if (!array_key_exists($slug, $this->children)) {
            return $this->children[$slug] = $component_name::createInstance($component_name, $this);
        }
        return false;
    }
    final public function do_component($slug, $func = "display", $args = null): bool
    {
        if (array_key_exists($slug, $this->children)) {
            return $this->children[$slug]->$func($args);
        }
        return false;
    }
    final public function do_parent_component($func = "display", $args = null): bool
    {
        if ($this->get_parent() != null) {
            return $this->get_parent()->$func($args);
        }
        return false;
    }
    // 加载组件相关模板
    // @param  string $filename 模板文件名称
    final public function get_template($filename): void
    {
        global $CURRENT_COMPONENT;
        $temp              = $CURRENT_COMPONENT;
        $CURRENT_COMPONENT = $this;
        load_template($filename, false);
        $CURRENT_COMPONENT = $temp;
    }
    // 定位模板
    // @param  array  $template_names 模板文件名称列表
    // @param  bool   $load           是否加载模板
    // @param  bool   $require_once   是否只加载一次
    final public function locate_template($template_names, $load = false, $require_once = true): void
    {
        global $CURRENT_COMPONENT;
        $temp              = $CURRENT_COMPONENT;
        $CURRENT_COMPONENT = $this;
        locate_template($template_names, $load, $require_once);
        $CURRENT_COMPONENT = $temp;
    }
    public function create()
    {
    }
    public function admin_init(): void
    {
        $this->do_children_component("admin_init");
    }
    public function init_early(): void
    {
        $this->do_children_component("init_early");
    }
    public function init(): void
    {
        $this->do_children_component("init");
    }
    public function init_later(): void
    {
        $this->do_children_component("init_later");
    }
    public function display_early(): void
    {
        $this->do_children_component("display_early");
    }
    public function display(): void
    {
        $this->do_children_component("display");
    }
    public function display_later(): void
    {
        $this->do_children_component("display_later");
    }
    public function do_children_component($func = "display", $args = null): void
    {
        foreach ($this->children as $component) {
            $component->$func($args);
        }
    }
}

/**
 * 添加组件
 * @param string $slug 组件标识
 * @param string $component_name 组件类名
 * @return mixed 组件实例
 * @since 1.0.0
 * @author Wang Shai
 */
function add_component(string $slug, string $component_name)
{
    return JL_ComponentSystem::add_component($slug, $component_name);
}

/**
 * 执行组件方法
 * @param string $slug 组件标识
 * @param string $func 方法名称
 * @param mixed $args 参数
 * @return mixed
 * @since 1.0.0
 * @author Wang Shai
 */
function do_component(string $slug, string $func = "display", mixed $args = null): mixed
{
    return JL_ComponentSystem::do_component($slug, $func, $args);
}

/**
 * 初始化前台组件
 * @since 1.0.0
 * @author Wang Shai
 */
function init_component()
{
    return JL_ComponentSystem::init_component();
}
/**
 * 初始化后台组件
 * @since 1.0.0
 * @author Wang Shai
 */
function admin_init_component()
{
    return JL_ComponentSystem::admin_init_component();
}

/**
 * 获取当前组件实例
 * @return mixed 当前组件实例
 * @since 1.0.0
 * @author Wang Shai
 */
function get_the_component()
{
    global $CURRENT_COMPONENT;
    return $CURRENT_COMPONENT;
}

/**
 * 执行当前组件方法
 * @param string $func 方法名称
 * @param mixed|null $argc 参数
 * @return mixed
 * @since 1.0.0
 * @author Wang Shai
 */
function do_the_component(string $func = "display", mixed $argc = null): mixed
{
    $com = get_the_component();
    if ($com == null) {
        return false;
    }
    return get_the_component()->$func($argc);
}

/**
 * 获取组件中的公开属性
 * @param string $slug 组件标识
 * @param string $property 属性名称
 * @return mixed
 * @since 1.0.0
 * @author Wang Shai
 */
function do_property(string $slug, string $property): mixed
{
    global $COMPONENTS;
    if (array_key_exists($slug, $COMPONENTS)) {
        $component = $COMPONENTS[$slug];
        if (property_exists($component, $property)) {
            return $component->$property;
        }
    }
    return null;
}