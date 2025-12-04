<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
class Menu extends Components {

    #[\Override]
    protected function init(): void
    {
        $this->menus();
    }
    #[\Override]
    protected function admin(): void
    {
        add_action('wp_nav_menu_item_custom_fields', [$this, 'initMenuItemFields'], 10, 4);
        add_action('wp_update_nav_menu_item', [$this, 'saveMenuItemFields'], 10, 3);
    }
    #[\Override]
    protected function system(): void
    {
        add_filter('nav_menu_css_class', [$this, 'renderMenuItemClasses'], 10, 3);
        add_filter('wp_nav_menu_objects', [$this, 'customFilterMenuItems'], 10, 2);
    }

    private function menus()
    {
        register_nav_menus([
            'desktop-header'           => __('Desktop Header Menu', 'G3'),
            'desktop-header-secondary' => __('Desktop Header Secondary Menu', 'G3'),
            'desktop-footer'           => __('Desktop Footer Menu', 'G3'),
            'desktop-footer-secondary' => __('Desktop Footer Secondary Menu', 'G3'),
            'mobile-menu'              => __('Mobile Menu', 'G3'),
            'shop-menu'                => __('Shop Menu', 'G3'),
        ]);
    }

    /**
     * Init Menu Item Fields
     * 
     * 初始化菜单项目字段
     * 
     * Custom Filter: g3_filter_menu_type
     * Custom Filter: g3_filter_menu_display_type
     * 
     * @param int $item_id
     * @param $item
     * @param int $depth
     * @param $args
     * @return void
     */
    public function initMenuItemFields($item_id, $item, $depth, $args): void
    {
        // one level menu, two level menu extension
        if ($depth === 0 || $depth === 1) {
            // extension: menu type
            $type = get_post_meta($item_id, '_menu_item_menu_type', true);
            $type = !empty($type) ? $type : '';
            $type = sanitize_html_class($type);

            $type_options = [
                ''          => __('General Menu', 'G3'),
                'card-menu' => __('Card Menu', 'G3'),
            ];

            /**
             * Custom Filter: g3_filter_menu_type
             */
            $type_options = apply_filters('g3_filter_menu_type', $type_options);
            ?>
            <p class="field-type description description-wide">
                <label for="edit-menu-item-menu-type-<?php echo $item_id; ?>">
                    <?php _e('Menu Type', 'G3'); ?><br>
                    <select id="edit-menu-item-menu-type-<?php echo $item_id; ?>" class="widefat code edit-menu-item-menu-type"
                        name="menu-item-menu-type[<?php echo $item_id; ?>]">
                        <?php foreach ($type_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($type, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </p>
            <?php
        }
        // extension: display type
        $display_type         = get_post_meta($item_id, '_menu_item_display_type', true);
        $display_type         = !empty($display_type) ? $display_type : '';
        $display_type         = sanitize_html_class($display_type);
        $display_type_options = [
            ''              => __('General', 'G3'),
            'logged-in'     => __('Visible only when logged in', 'G3'),
            'not-logged-in' => __('Visible only when not logged in', 'G3'),
        ];

        /**
         * Custom Filter: g3_filter_menu_display_type
         */
        $display_type_options = apply_filters('g3_filter_menu_display_type', $display_type_options);
        ?>
        <p class="field-display-type description description-wide">
            <label for="edit-menu-item-display-type-<?php echo $item_id; ?>">
                <?php _e('Display Type', 'G3'); ?><br>
                <select id="edit-menu-item-display-type-<?php echo $item_id; ?>"
                    class="widefat code edit-menu-item-display-type" name="menu-item-display-type[<?php echo $item_id; ?>]">
                    <?php foreach ($display_type_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($display_type, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <?php
    }
    public function saveMenuItemFields($menu_id, $menu_item_db_id, $args): void
    {
        if (isset($_REQUEST['menu-item-menu-type'][$menu_item_db_id])) {
            $type = sanitize_text_field($_REQUEST['menu-item-menu-type'][$menu_item_db_id]);
            update_post_meta($menu_item_db_id, '_menu_item_menu_type', $type);
        }

        if (isset($_REQUEST['menu-item-display-type'][$menu_item_db_id])) {
            $display_type = sanitize_text_field($_REQUEST['menu-item-display-type'][$menu_item_db_id]);
            update_post_meta($menu_item_db_id, '_menu_item_display_type', $display_type);
        }
    }
    public function renderMenuItemClasses($classes, $item, $args)
    {
        if ($item->menu_item_parent == 0) {
            $type = get_post_meta($item->ID, '_menu_item_menu_type', true);
            if (!empty($type)) {
                $classes[] = sanitize_html_class($type);
            }
        }
        return $classes;
    }
    public function customFilterMenuItems($items, $args)
    {
        $status = is_user_logged_in();
        foreach ($items as $key => $item) {
            $display_type = get_post_meta($item->ID, '_menu_item_display_type', true);
            if (($display_type === 'logged-in' && !$status) || ($display_type === 'not-logged-in' && $status)) {
                unset($items[$key]);
            }
        }
        return $items;
    }
}
