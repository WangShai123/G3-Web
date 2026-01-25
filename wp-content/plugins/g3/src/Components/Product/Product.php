<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components;
use JEALER\G3\Services\ProductService;
use JEALER\G3\Services\SidebarService;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Image;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Validator;
use Override;
use WP_Post;

class Product extends Components {
    public array $option;

    #[Override]
    protected function options(): void
    {
        $this->option = Option::get(ProductService::OPTION_KEY, [

        ]);
    }
    #[Override]
    protected function form(): void
    {
        if (!isset($_REQUEST['page']) || $_REQUEST['page'] !== 'shop-settings') return;
        $this->option = Option::cache(ProductService::OPTION_KEY, $this->option);
    }
    #[Override]
    protected function admin(): void
    {
        add_filter('post_updated_messages', [$this, 'resetUpdatedMessages']);
        $this->saveGallery();
        $this->saveProperties();
    }
    #[Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'digital-operations',
            __('Shop', 'G3'),
            __('Shop', 'G3'),
            'manage_options',
            'shop-setting',
            [$this, 'render'],
            5
        );
    }
    public function render(): void
    {
        echo '<div class="wrap"><h1>' . __('Shop', 'G3') . '</h1>';
        $args = [
            'general' => __('General', 'G3')
        ];
        Element::tab('Product', 'general', $args);
        echo '</div>';
    }
    #[Override]
    protected function settings(): void
    {
        add_settings_section(
            'general',
            null,
            '__return_false',
            'shop-settings'
        );
        register_setting(
            'general',
            ProductService::OPTION_KEY,
        );
        Element::settingFields('shop-settings', 'general', [

        ]);
    }
    #[Override]
    protected function postType(): void
    {
        $labels = [
            'name'                  => __('Products', 'G3'),
            'singular_name'         => __('Product', 'G3'),
            'menu_name'             => __('Shop', 'G3'),
            'name_admin_bar'        => __('Product', 'G3'),
            'add_new'               => __('Add New', 'G3'),
            'add_new_item'          => __('Add New Product', 'G3'),
            'new_item'              => __('New Product', 'G3'),
            'edit_item'             => __('Edit Product', 'G3'),
            'view_item'             => __('View Product', 'G3'),
            'all_items'             => __('All Products', 'G3'),
            'search_items'          => __('Search Products', 'G3'),
            'parent_item_colon'     => __('Parent Product:', 'G3'),
            'not_found'             => __('No products found.', 'G3'),
            'not_found_in_trash'    => __('No products found in trash.', 'G3'),
            'featured_image'        => __('Cover Image', 'G3'),
            'set_featured_image'    => __('Set cover image', 'G3'),
            'remove_featured_image' => __('Remove cover image', 'G3'),
            'use_featured_image'    => __('Use as cover image', 'G3'),
            'archives'              => __('Product Archives', 'G3'),
            'insert_into_item'      => __('Insert into product', 'G3'),
            'uploaded_to_this_item' => __('Uploaded to this product', 'G3'),
            'filter_items_list'     => __('Filter products list', 'G3'),
            'items_list_navigation' => __('Products list navigation', 'G3'),
            'items_list'            => __('Products list', 'G3'),
        ];
        $args   = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'product'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            // 'supports'           => ['title', 'editor', 'comments', 'revisions', 'author', 'excerpt', 'thumbnail', 'post-formats'],
            'supports'           => ['title', 'editor', 'comments', 'revisions', 'author', 'excerpt', 'thumbnail'],
            'menu_icon'          => 'dashicons-cart',
            'taxonomies'         => ['product_category', 'product_brand', 'post_tag'],
        ];
        register_post_type('product', $args);
    }
    #[Override]
    protected function taxonomy(): void
    {
        register_taxonomy(
            'product_category',
            ['product'],
            [
                'hierarchical'      => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'has_archive'       => true,
                'rewrite'           => ['slug' => 'product_category'],
                'labels'            => [
                    'name'              => __('Product Categories', 'G3'),
                    'singular_name'     => __('Product Category', 'G3'),
                    'search_items'      => __('Search Product Categories', 'G3'),
                    'not_found'         => __('No product categories found.', 'G3'),
                    'all_items'         => __('All Product Categories', 'G3'),
                    'parent_item'       => __('Parent Product Category', 'G3'),
                    'parent_item_colon' => __('Parent Product Category:', 'G3'),
                    'edit_item'         => __('Edit Product Category', 'G3'),
                    'update_item'       => __('Update Product Category', 'G3'),
                    'add_new_item'      => __('Add New Product Category', 'G3'),
                    'new_item_name'     => __('New Product Category Name', 'G3'),
                    'menu_name'         => __('Categories', 'G3')
                ]
            ]
        );
        register_taxonomy(
            'product_brand',
            ['product'],
            [
                'hierarchical'      => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'has_archive'       => true,
                'rewrite'           => ['slug' => 'product_brand'],
                'labels'            => [
                    'name'              => __('Product Brands', 'G3'),
                    'singular_name'     => __('Product Brand', 'G3'),
                    'search_items'      => __('Search Brands', 'G3'),
                    'not_found'         => __('No brands found.', 'G3'),
                    'all_items'         => __('All Brands', 'G3'),
                    'parent_item'       => __('Parent Brand', 'G3'),
                    'parent_item_colon' => __('Parent Brand:', 'G3'),
                    'edit_item'         => __('Edit Brand', 'G3'),
                    'update_item'       => __('Update Brand', 'G3'),
                    'add_new_item'      => __('Add New Brand', 'G3'),
                    'new_item_name'     => __('New Brand Name', 'G3'),
                    'menu_name'         => __('Brands', 'G3')
                ]
            ]
        );
    }
    #[Override]
    protected function sidebar(): void
    {
        register_sidebar(
            [
                'name'          => __('Shop Sidebar', 'G3'),
                'id'            => 'shop',
                'description'   => __('You can add widgets to shop sidebar.', 'G3'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );
        register_sidebar(
            [
                'name'          => __('Product Sidebar', 'G3'),
                'id'            => 'product',
                'description'   => __('You can add widgets to product sidebar.', 'G3'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );
    }
    #[Override]
    protected function widgets(): void
    {
        SidebarService::registerWidget('ProductWidget', __DIR__);
    }
    public function resetUpdatedMessages($messages)
    {
        $post           = get_post();
        $postType       = 'product';
        $postTypeObject = get_post_type_object($postType);

        $messages[$postType] = array(
            0  => '',
            // Unused. Messages start at index 1.
            1  => __('Product updated.', 'G3'),
            2  => __('Custom field updated.', 'G3'),
            3  => __('Custom field deleted.', 'G3'),
            4  => __('Product updated.', 'G3'),
            /* translators: %s: date and time of the revision */
            5  => isset($_GET['revision']) ? sprintf(__('Product restored to version from %s.', 'G3'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6  => __('Product published.', 'G3'),
            7  => __('Product saved.', 'G3'),
            8  => __('Product submitted.', 'G3'),
            9  => sprintf(
                __('Product scheduled for:<strong>%1$s</strong>.', 'G3'),
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post->post_date))
            ),
            10 => __('Product draft updated.', 'G3'),
        );

        if ($postTypeObject->publicly_queryable) {
            $permalink = get_permalink($post->ID);

            $view_link               = sprintf(' <a href="%s">%s</a>', esc_url($permalink), __('View Product', 'G3'));
            $messages[$postType][1] .= $view_link;
            $messages[$postType][6] .= $view_link;
            $messages[$postType][9] .= $view_link;

            $preview_permalink        = add_query_arg('preview', 'true', $permalink);
            $preview_link             = sprintf('<a target="_blank" href="%s">%s</a>', esc_url($preview_permalink), __('Preview Product', 'G3'));
            $messages[$postType][8]  .= $preview_link;
            $messages[$postType][10] .= $preview_link;
        }

        return $messages;
    }

    #[Override]
    protected function metaBox(): void
    {
        Frontend::loadStyle('swiper');
        Frontend::loadScript('swiper');
        add_meta_box(
            'g3-metabox-gallery',
            __('Product Gallery', 'G3'),
            [$this, 'renderGalleryMetabox'],
            'product',
            'side',
            'default'
        );
        add_meta_box(
            'g3-metabox-sku',
            __('Product SKU', 'G3'),
            [$this, 'renderSkuMetabox'],
            'product',
            'normal',
            'default'
        );
        add_meta_box(
            'g3-metabox-properties',
            __('Product Properties', 'G3'),
            [$this, 'renderPropertiesMetabox'],
            'product',
            'normal',
            'default'
        );
    }

    #[Override]
    protected function adminScripts(): void
    {
        // only load in product editor page
        if (!Validator::screen('product')) return;

        wp_register_script(
            'g3-admin-product',
            G3_DIST_URL . '/javascript/g3.admin.product.min.js',
            array('jquery'),
            '1.0.0',
            true // 在 footer 加载
        );
        wp_enqueue_script('g3-admin-product');

        Frontend::loadStyle('ht.table');
        Frontend::loadScript('ht.table');

        $this->localizeData();
    }

    private function localizeData(): void
    {
        $postId = get_the_ID();
        $spec   = get_post_meta($postId, ProductService::SPEC_KEY, true) ?: 'single';
        $prop   = get_post_meta($postId, ProductService::PROP_KEY, true) ?: [];

        $data = [
            'nonce' => wp_create_nonce('product_nonce_action'),
            'data'  => [
                'spec'    => [
                    'key'           => ProductService::SPEC_KEY,
                    'type'          => $spec,
                    'originalPrice' => '100.00',
                    'salePrice'     => '80.00',
                    'weight'        => '1.00',
                    'stock'         => '1000',
                    'sold'          => '20'
                ],
                'prop'    => [
                    'key'   => ProductService::PROP_KEY,
                    'items' => $prop
                ],
                'gallery' => [
                    'key'   => ProductService::GALLERY_KEY,
                    'items' => get_post_meta($postId, ProductService::GALLERY_KEY, true)
                ]
            ]
        ];
        wp_localize_script('g3-admin-product', 'productData', $data);
    }

    public function renderGalleryMetabox(WP_POST $post): void
    {
        wp_nonce_field(ProductService::GALLERY_KEY . '_save', ProductService::GALLERY_KEY . '_nonce');
        echo '<div id="gallery-app"></div>';
    }
    private function saveGallery(): void
    {
        add_action('save_post', function ($postId) {
            if (!isset($_POST[ProductService::GALLERY_KEY . '_nonce']) || !wp_verify_nonce($_POST[ProductService::GALLERY_KEY . '_nonce'], ProductService::GALLERY_KEY . '_save')) {
                return;
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

            if (isset($_POST[ProductService::GALLERY_KEY]) && is_array($_POST[ProductService::GALLERY_KEY])) {
                update_post_meta($postId, ProductService::GALLERY_KEY, array_values($_POST[ProductService::GALLERY_KEY]));
            } else {
                delete_post_meta($postId, ProductService::GALLERY_KEY);
            }
        });
    }

    private function escapeJsString($str)
    {
        return str_replace(['\\', '"', "'", "\r", "\n"], ['\\\\', '\\"', "\\'", '\\r', '\\n'], $str);
    }

    public function renderSkuMetabox(WP_POST $post): void
    {
        echo '<div id="specification-app"></div>';
        echo '<div id="sku-app"></div>';
    }
    public function renderPropertiesMetabox(WP_POST $post): void
    {
        wp_nonce_field(ProductService::PROP_KEY . '_save', ProductService::PROP_KEY . '_nonce');
        echo '<div id="prop-app"></div>';
        wp_enqueue_style('table', 'https://cdn.jsdelivr.net/npm/handsontable/styles/handsontable.min.css', [], '16.2.0');
    }
    private function saveProperties(): void
    {
        add_action('save_post', function ($postId) {
            if (!isset($_POST[ProductService::PROP_KEY . '_nonce']) || !wp_verify_nonce($_POST[ProductService::PROP_KEY . '_nonce'], ProductService::PROP_KEY . '_save')) {
                return;
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

            if (isset($_POST[ProductService::PROP_KEY]) && is_array($_POST[ProductService::PROP_KEY])) {
                update_post_meta($postId, ProductService::PROP_KEY, array_values($_POST[ProductService::PROP_KEY]));
            } else {
                delete_post_meta($postId, ProductService::PROP_KEY);
            }
        });
    }
}
