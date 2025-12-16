<!-- G3 Plugin — AI Assistant Guidance -->
# G3 Plugin — AI Assistant Guidance

This document contains concise, actionable instructions for AI coding agents working on this repository. It explains the architecture, common patterns, developer workflows, and examples to help you be productive immediately.

- **Project Type:** WordPress plugin (entry: loader.php) using Composer and PSR-4 autoloading.
- **Key Requirements:** PHP >= 8.3, WordPress >= 6.5, extensions: fileinfo, curl, openssl, and optional Redis.
- **Main Namespaces:** JEALER\G3\ - plugin source is under `wp-content/plugins/g3/src`.

**Big Picture Architecture**
- `loader.php`: plugin bootstrap and register activation/deactivation hooks — plugin entrypoint.
- `src/Loader.php` and `src/JL.php`: central bootstrapping and global helper logic. Check [../wp-content/plugins/g3/loader.php](../wp-content/plugins/g3/loader.php) and [../wp-content/plugins/g3/src/Loader.php](../wp-content/plugins/g3/src/Loader.php) for details.
- `src/Components`: each subfolder is a self-contained Component (business system). They follow a lifecycle defined in `src/Components.php` (options/system/init/admin/adminMenu/sidebar/widgets/postType/taxonomy). See `Post` component as an example.
- `src/Services`: encapsulated services used by components (e.g., `PostService`, `ThemeGeneratorService`).
- `src/Controllers` and the plugin `Router` and `Rewrite` provide API and template routing. Themes can add controllers in a parallel folder `src/Controllers` under the theme (namespace `G3\Controllers`).
- `config/` defines plugin default configs (components map, rewrite rules). Theme overrides are supported via `G3_THEME_CONFIG_DIR` (get_stylesheet_directory()/config).
- `public/` and `dist/`: frontend assets and distribution artifacts. `ThemeGeneratorService` scaffolds theme structure (see `CreateCommand`).

**Developer Workflows & Commands**
- Install dependencies: `cd wp-content/plugins/g3 && composer install`.
- Console tools (bin/console.php): `php ../wp-content/plugins/g3/bin/console.php G3:create` to scaffold a theme project; `G3:test` or other registered commands available — see [../wp-content/plugins/g3/bin/console.php](../wp-content/plugins/g3/bin/console.php) and [../wp-content/plugins/g3/src/Commands](../wp-content/plugins/g3/src/Commands).
- Run a reflection inspector (tests): `php wp-content/plugins/g3/tests/inspect.php JEALER\G3\Components\Post` to inspect classes.
- Debugging: enable `WP_DEBUG` in WordPress; plugin respects debug flows (e.g., Components::initialize, add_action('parse_request') rewrite checks).
- Activate/Deactivate plugin via WordPress UI or `wp-cli`: `wp plugin activate g3`/`wp plugin deactivate g3`.

**Codebase Conventions & Patterns**
- Components lifecycle methods: `options()`, `system()`, `init()`, `admin()`, `adminMenu()`, `sidebar()`, `widgets()`, etc. Implement these in new Components.
- Configuration is merged at runtime: plugin `config/components.php` + theme `config/components.php` override. Follow that pattern to enable/disable components by name.
- Theme overrides:
  - Theme-specific logic (controllers/components) should be placed in the theme folder using the G3-provided layout. See `ThemeGeneratorService` and the scaffolded `src/` layout.
  - Theme controllers live under `theme/src/Controllers` and should use the `G3\Controllers` namespace.
- Services: prefer `Services` classes for business logic, keep Components thin by delegating heavy logic to services (e.g., `PostService`, `ThemeGeneratorService`).
- Options: use `Utilities\Option::get` and `Option::cache` for config persistence; UI elements are created by `Utilities\Container`.
- Router/REST: Use `Router` under `src/Router.php` and register REST endpoints in controllers; plugin will discover both plugin and theme controllers.

 - Attributes & AOP: The plugin uses PHP Attributes for defining REST routes, middleware and schema validation (see `src/Attributes/RestRouter.php`, `src/Attributes/Middleware.php`, `src/Attributes/Schema.php`). `src/Aop.php` and `config/aop.php` implement AOP behavior.

**Common Tasks & Examples**
- Add new component:
  1. Create `wp-content/plugins/g3/src/Components/YourComponent/YourComponent.php` with class `JEALER\G3\Components\YourComponent` extending `Components`.
  2. Implement lifecycle methods (`options`, `init`, `admin` as needed).
  3. Add component name to `config/components.php` to enable it by default, or add it to a theme `config/components.php` to enable per-theme.
- Add theme-level controller:
  1. Create theme folder `themes/your-theme/src/Controllers/MyController.php` with namespace `G3\Controllers`.
  2. Controller will be discovered automatically by plugin Router.
- Update assets: edit `public/` and run bundling/publish manually if necessary. Avoid direct vendor edits.

**Integration Points & External Dependencies**
- Third-party libs are managed by Composer (`symfony/console`, `guzzle`, `easywechat`). Avoid changing vendor code — update `composer.json` if adding or upgrading packages.
- Licensing verification: `src/JL.php::verify()` sends license requests to `https://api.jealer.com`. Be careful with testing credentials or exposing keys in public PRs.

**Testing & Debugging**
- Minimal test helpers are provided in `tests/inspect.php` to reflect on class structure.
- Use `WP_DEBUG` and local WordPress environment to test plugin activation flows and rewrite behaviors.

**Files to Inspect for Context**
- Plugin entrypoint: loader.php — [../wp-content/plugins/g3/loader.php](../wp-content/plugins/g3/loader.php)
- Bootstrapping: [../wp-content/plugins/g3/src/Loader.php](../wp-content/plugins/g3/src/Loader.php)
- Core glue: [../wp-content/plugins/g3/src/JL.php](../wp-content/plugins/g3/src/JL.php)
- Components base & example: [../wp-content/plugins/g3/src/Components.php](../wp-content/plugins/g3/src/Components.php), [../wp-content/plugins/g3/src/Components/Post/Post.php](../wp-content/plugins/g3/src/Components/Post/Post.php)
- Configuration: [../wp-content/plugins/g3/config/components.php](../wp-content/plugins/g3/config/components.php), [../wp-content/plugins/g3/config/define.php](../wp-content/plugins/g3/config/define.php)
- Theme generator & commands: [../wp-content/plugins/g3/src/Services/ThemeGeneratorService.php](../wp-content/plugins/g3/src/Services/ThemeGeneratorService.php), [../wp-content/plugins/g3/bin/console.php](../wp-content/plugins/g3/bin/console.php), [../wp-content/plugins/g3/src/Commands/CreateCommand.php](../wp-content/plugins/g3/src/Commands/CreateCommand.php)

If anything is unclear or you need more specifics (e.g., how to scaffold a new REST controller, how to run a local WordPress setup, or test workflows), ask for the task and I will expand the instruction set with explicit examples and commands.
