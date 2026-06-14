<?php
namespace JEALER\G3\Core;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Components\ComponentManager;
use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\System;
use Exception;

/**
 * Component Loader
 * 
 * 组件加载器
 * 
 * 简易版G3组件系统
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class ComponentLoader {
    private array      $loadedComponents = [];
    private bool       $initialized      = false;
    private ?Container $container;
    public function __construct()
    {
        if (!isset($this->container)) {
            $this->container = Container::run();
        }
    }

    /**
     * 加载组件系统
     * 
     * @return void
     */
    public function load(): void
    {
        if ($this->initialized) {
            return;
        }

        /**
         * 读取配置文件（兼容模式）
         */
        $config = $this->getConfig();

        if (isset($config['enabled']) && $config['enabled'] === false) {
            return;
        }

        /**
         * 预加载所有配置到Parameter系统
         * @deprecated 简化组件系统，降低用户心智负担。使用 Context 替代 Parameter
         */
        // $configs = $config['configs'];
        // $this->preloadConfigs($configs);

        // 按需加载
        $components = $config['components'];
        foreach ($components as $componentName => $enabled) {
            if ($enabled === true || $enabled === '1') {
                $this->loadComponent($componentName);
            }
        }

        $this->initialized = true;
    }

    /**
     * 获取配置（支持主题覆盖，与原系统完全相同）
     * 
     * @return array
     */
    private function getConfig(): array
    {
        $config = require G3_PlUGIN_DIR . '/config/components.php';

        $themeConfig = get_stylesheet_directory() . '/config/components.php';
        if (file_exists($themeConfig)) {
            $themeData = require $themeConfig;

            if (isset($themeData['enabled']) && $themeData['enabled'] === false) {
                $themeData = [];
            }

            $config = array_merge($config, $themeData);
        }

        return $config;
    }

    /**
     * 加载单个组件（自动处理文件路径和类名）
     * 
     * @param string $componentName 组件名称
     * @return void
     */
    private function loadComponent(string $componentName): void
    {
        try {
            // 解析组件文件路径（支持主题覆盖）
            $componentInfo = $this->resolveComponentFile($componentName);

            if (!$componentInfo) {
                error_log("[G3 ComponentLoader] Component file not found: {$componentName}");
                return;
            }

            require_once $componentInfo['file_path'];

            if (!class_exists($componentInfo['class_name'])) {
                error_log("[G3 ComponentLoader] Component class not found: {$componentInfo['class_name']}");
                return;
            }

            if (!$this->container->has($componentInfo['class_name'])) {
                $this->container->setRawDefinition($componentInfo['class_name'], $componentInfo['class_name']::class);
            }
            $component = $this->container->get($componentInfo['class_name']);

            // 存储组件实例
            $this->loadedComponents[$componentName] = $component;

            // 注册组件生命周期钩子
            ComponentManager::run()->registerComponent($component);

            // $source = $componentInfo['is_theme_override'] ? 'theme' : 'plugin';
            // if (defined('WP_DEBUG') && WP_DEBUG) {
            //     error_log("[G3 Debug][ComponentLoader] Component loaded from {$source}: {$componentName}");
            // }
        }
        catch (Exception $e) {
            error_log("[G3 ComponentLoader] Failed to load component {$componentName}: " . $e->getMessage());
        }
    }

    /**
     * 解析组件文件路径，用户主题组件，优先级最高，覆盖系统组件
     * 
     * @param string $componentName 组件名称
     * @return array|null
     */
    private function resolveComponentFile(string $componentName): ?array
    {
        $className = ucfirst($componentName);

        $themeComponentFile = get_stylesheet_directory() . "/src/Components/{$className}/{$className}.php";
        if (file_exists($themeComponentFile)) {
            return [
                'file_path'         => $themeComponentFile,
                'class_name'        => "JEALER\\G3\\Components\\{$className}",
                'is_theme_override' => true
            ];
        }

        $pluginComponentFile = G3_PlUGIN_DIR . "/src/Components/{$className}/{$className}.php";
        if (file_exists($pluginComponentFile)) {
            return [
                'file_path'         => $pluginComponentFile,
                'class_name'        => "JEALER\\G3\\Components\\{$className}",
                'is_theme_override' => false
            ];
        }

        return null;
    }

    /**
     * 获取已加载的组件
     * 
     * @return array
     */
    public function getLoadedComponents(): array
    {
        return $this->loadedComponents;
    }

    /**
     * 获取单个组件实例
     * 
     * @param string $componentName 组件名称
     * @return object|null
     */
    public function getComponent(string $componentName): ?object
    {
        return $this->loadedComponents[$componentName] ?? null;
    }

    /**
     * 检查组件是否已加载
     * 
     * @param string $componentName 组件名称
     * @return bool
     */
    public function isComponentLoaded(string $componentName): bool
    {
        return isset($this->loadedComponents[$componentName]);
    }

    private function _preloadConfigs(array $componentConfig): void
    {
        foreach ($componentConfig as $key => $config) {
            foreach ($config as $k => $v) {
                Context::set($v['key'], $v['default']);
            }
        }
    }

    /**
     * @deprecated 1.0.0
     * 删除 Parameter 相关方案
     */
    private function preloadConfigs(array $componentConfig): void
    {
        $container = Container::run();

        // 1. 加载插件默认配置
        $pluginConfig = G3_PlUGIN_DIR . '/config/components.php';
        $configData   = require $pluginConfig;

        // 2. 主题配置覆盖
        $themeConfig = get_stylesheet_directory() . '/config/components.php';
        if (file_exists($themeConfig)) {
            $themeData = require $themeConfig;
            if (is_array($themeData)) {
                $configData = array_merge($configData, $themeData);
            }
        }

        // 3. 处理 configs 配置
        if (!isset($configData['configs'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[G3 Debug][ComponentLoader] No configs section found in components.php');
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[G3 Debug][ComponentLoader] Processing configs: ' . print_r($configData['configs'], true));
        }

        foreach ($configData['configs'] as $componentName => $configGroups) {
            $componentKey = strtolower($componentName);

            foreach ($configGroups as $groupName => $groupConfig) {
                $optionKey    = $groupConfig['key'] ?? '';
                $defaultValue = $groupConfig['default'] ?? [];

                if (empty($optionKey)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("[G3 Debug][ComponentLoader] Empty option key for {$componentName}.{$groupName}");
                    }
                    continue;
                }

                $optionData = get_option($optionKey, $defaultValue);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("[G3 Debug][ComponentLoader] Loading config {$componentName}.{$groupName} from {$optionKey}: " . print_r($optionData, true));
                }

                // 自动映射到Parameter系统
                // $parameterPrefix = ($groupName === 'main')
                //     ? "config.{$componentKey}"
                //     : "config.{$componentKey}.{$groupName}";
                $parameterPrefix = "config.{$componentKey}.{$groupName}";

                // 将配置数据注入到Parameter管理器
                if (is_array($optionData)) {
                    foreach ($optionData as $key => $value) {
                        $parameterKey = "{$parameterPrefix}.{$key}";
                        $container->setParameter($parameterKey, $value);

                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("[G3 Debug][ComponentLoader] Set parameter: {$parameterKey} = " . print_r($value, true));
                        }
                    }

                    // 同时设置整个配置组
                    $container->setParameter($parameterPrefix, $optionData);

                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("[G3 Debug][ComponentLoader] Set parameter group: {$parameterPrefix} = " . print_r($optionData, true));
                    }
                }
            }
        }
    }
}