<?php
namespace JEALER\G3;

use JEALER\G3\Components;

/**
 * Rewrite Router
 * 
 * Rewrite路由管理器 - 重构WordPress重写规则书写方式
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class Rewrite {
    /** @var array rewrite config */
    private array $config = [];

    /** @var self single instance */
    private static ?self $instance = null;

    private function __construct()
    {
        $this->loadConfig();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfig(): void
    {
        // get plugin default config
        $pluginConfigFile = WP_PLUGIN_DIR . '/g3/config/rewriteRouter.php';
        $pluginConfig     = [];

        if (file_exists($pluginConfigFile)) {
            $pluginConfig = require $pluginConfigFile;
        }

        // get user theme config
        $themeConfigFile = get_stylesheet_directory() . '/config/rewriteRouter.php';
        $themeConfig     = [];

        if (file_exists($themeConfigFile)) {
            $themeConfig = require $themeConfigFile;
        }

        // merge config, theme config override plugin config
        $config = array_merge($pluginConfig, $themeConfig);

        $this->config = $config;
    }

    /**
     * check if rewrite rules need flush based on config hash, 24-hour expiration
     * 
     * 检查是否需要刷新rewrite规则（基于配置哈希），24小时有效期
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private function checkIfFlushNeeded(): void
    {
        // get stored config hash (using transient, 24-hour expiration)
        $storedHash = get_transient('g3_rewrite_config_hash');

        // calculate current config hash
        $currentHash = md5(serialize($this->config));

        // if hash not match, config changed, need flush
        if ($storedHash !== $currentHash) {
            // save new hash (using transient, 24-hour expiration)
            set_transient('g3_rewrite_config_hash', $currentHash, 24 * HOUR_IN_SECONDS);

            // immediately flush rewrite rules
            self::flushRewriteRules();
        }
    }

    /**
     * verify current rewrite rules against config
     * 
     * 验证当前rewrite规则是否与配置一致
     * 
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    private function verifyRewriteRules(): bool
    {
        global $wp_rewrite;

        // if rewrite rules not loaded, skip verify
        if (!isset($wp_rewrite->rules) || !is_array($wp_rewrite->rules)) {
            return false;
        }

        // check each config rule against current rewrite rules
        foreach ($this->config as $url => $route) {
            if (!is_array($route) || !isset($route['var']) || !isset($route['path'])) {
                continue;
            }

            $var = $route['var'];
            // build expected query string, support multiple query vars
            if (is_array($var)) {
                // handle multiple query vars
                $queryParts = [];
                foreach ($var as $index => $varName) {
                    $matchIndex   = $index + 1;
                    $queryParts[] = $varName . '=$matches[' . $matchIndex . ']';
                }
                $expectedQuery = 'index.php?' . implode('&', $queryParts);
            } else {
                // handle single query var
                $expectedQuery = 'index.php?' . $var . '=$matches[1]';
            }

            // check if rule exists and matches expected query
            if (!isset($wp_rewrite->rules[$url]) || $wp_rewrite->rules[$url] !== $expectedQuery) {
                // rule not match or not exists
                return false;
            }
        }

        return true;
    }

    /**
     * get rewrite config
     * 
     * 获取rewrite配置
     * 
     * @return array rewrite config
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * register rewrite rules
     * 
     * 注册rewrite规则
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function registerRewriteRules(): void
    {
        foreach ($this->config as $url => $route) {
            // ensure route is an array and contains necessary keys
            if (!is_array($route) || !isset($route['var']) || !isset($route['path'])) {
                continue;
            }

            // Check dependency
            $dependency = $route['dependency'] ?? true;
            if (!$this->isDependencySatisfied($dependency)) {
                // Skip this route if dependency is not satisfied
                continue;
            }

            $var = $route['var'];

            // build expected query string, support multiple query vars
            if (is_array($var)) {
                // handle multiple query vars
                $queryParts = [];
                foreach ($var as $index => $varName) {
                    $matchIndex   = $index + 1;
                    $queryParts[] = $varName . '=$matches[' . $matchIndex . ']';
                }
                $query = 'index.php?' . implode('&', $queryParts);
            } else {
                // handle single query var
                $query = 'index.php?' . $var . '=$matches[1]';
            }

            // add rewrite rule
            add_rewrite_rule($url, $query, 'top');
        }

        // check if flush needed, based on config hash
        // $this->checkIfFlushNeeded();
    }

    /**
     * Flush rewrite rules
     * 
     * 立即刷新rewrite规则（用于插件激活等需要立即生效的场景）
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function flushRewriteRules(): void
    {
        $instance = self::getInstance();
        $instance->registerRewriteRules();
        flush_rewrite_rules();
    }

    /**
     * check and fix rewrite rules
     * 
     * 主动检查并修复rewrite规则。在请求早期调用，确保规则一致性
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function checkAndFixRewriteRules(): void
    {
        // verify current rewrite rules against config
        if (!$this->verifyRewriteRules()) {
            self::flushRewriteRules();
        }
    }

    /**
     * Automatically Register query variables
     * 
     * 自动注册查询变量
     * 
     * @param array $vars registered query vars
     * @return array 
     * @since 1.0.0
     * @author Wang Shai
     */
    public function registerQueryVars(array $vars): array
    {
        foreach ($this->config as $route) {
            if (is_array($route) && isset($route['var'])) {
                $var = $route['var'];
                if (is_array($var)) {
                    // if var is array, add all query vars
                    $vars = array_merge($vars, $var);
                } else {
                    // if var is string, add it directly
                    $vars[] = $var;
                }
            }
        }
        return array_unique($vars);
    }

    /**
     * Bind template dispatch
     * 
     * 绑定模板分发
     * 
     * @param string $template
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public function bindTemplateDispatch(string $template): string
    {
        global $wp_query;

        // check if query vars match any route
        foreach ($this->config as $url => $route) {

            // ensure route is an array and contains necessary keys
            if (!is_array($route) || !isset($route['var']) || !isset($route['path'])) {
                continue;
            }

            // Check dependency
            $dependency = $route['dependency'] ?? true;
            if (!$this->isDependencySatisfied($dependency)) {
                // Skip this route if dependency is not satisfied
                continue;
            }

            $var          = $route['var'];
            $hasMatch     = false;
            $matchedValue = null;

            // Check if route matches current request
            if (is_array($var)) {
                // Handle multiple query vars
                foreach ($var as $varName) {
                    if (isset($wp_query->query_vars[$varName]) && !empty($wp_query->query_vars[$varName])) {
                        $hasMatch     = true;
                        $matchedValue = $wp_query->query_vars[$varName];
                        break;
                    }
                }
            } else {
                // Handle single query var
                if (isset($wp_query->query_vars[$var]) && !empty($wp_query->query_vars[$var])) {
                    $hasMatch     = true;
                    $matchedValue = $wp_query->query_vars[$var];
                }
            }

            // if found match, determine which template to load
            if ($hasMatch) {
                $templateFile = $route['path']; // Default template

                // Check for priority matching rules
                if (isset($route['priority']) && is_array($route['priority'])) {
                    // Look for a matching value in the priority rules
                    foreach ($route['priority'] as $param) {
                        if (isset($param['value']) && $param['value'] == $matchedValue) {
                            error_log('test priority callback: ' . $param['value']);
                            $templateFile = $param['path'];
                            break;
                        }
                    }
                }

                // Get the actual template path
                $themeTemplate = get_stylesheet_directory() . '/templates/' . $templateFile;
                if (file_exists($themeTemplate)) {
                    return $themeTemplate;
                }

                $pluginTemplate = WP_PLUGIN_DIR . '/g3/templates/' . $templateFile;
                if (file_exists($pluginTemplate)) {
                    return $pluginTemplate;
                }
            }
        }

        // if no match found, return original template to avoid interfering with WordPress default template loading
        return $template;
    }

    /**
     * Check if a route dependency is satisfied
     * 
     * 检查路由依赖是否满足
     * 
     * @param mixed $dependency
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    private function isDependencySatisfied($dependency): bool
    {
        // Default to true if no dependency specified
        if ($dependency === null) {
            return true;
        }

        // If boolean, return as is
        if (is_bool($dependency)) {
            return $dependency;
        }

        // If string, treat as component name
        if (is_string($dependency)) {
            // Convert first letter to uppercase
            $componentName = ucfirst($dependency);

            // Check if component is registered in the Components class
            if (
                property_exists(Components::class, 'components') &&
                is_array(Components::$components)
            ) {
                return array_key_exists($componentName, Components::$components);
            }

            return false;
        }

        // If callable (function), execute and return result
        if (is_callable($dependency)) {
            return (bool) call_user_func($dependency);
        }

        // For any other case, assume dependency is satisfied
        return true;
    }
}
