<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\SidebarService;
use JEALER\G3\Utilities\Image;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\ProductService;
use WP_Post;
class Product extends Components {
    public array $option;
    #[\Override]
    protected function options(): void
    {
        $option       = Option::get(ProductService::OPTION_KEY, [

        ]);
        $this->option = Option::cache(ProductService::OPTION_KEY, $option);
    }
    #[\Override]
    protected function admin(): void
    {
        add_filter('post_updated_messages', [$this, 'resetUpdatedMessages']);
        $this->saveGallery();
        $this->saveProperties();
    }
    #[\Override]
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
        Container::tab('Product', 'general', $args);
        echo '</div>';
    }
    #[\Override]
    protected function settings(): void
    {
        add_settings_section(
            'general',
            null,
            '__return_false',
            'shop-setting'
        );
        register_setting(
            'general',
            ProductService::OPTION_KEY,
        );
        Container::settingFields('shop-setting', 'general', [

        ]);
    }
    #[\Override]
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
    #[\Override]
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
    #[\Override]
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
    #[\Override]
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

    #[\Override]
    protected function metaBox(): void
    {
        Frontend::loadStyle('swiper');
        Frontend::loadScript('swiper');
        Frontend::loadStyle('jui');
        Frontend::loadScript('jui');
        Frontend::loadScript('admin');
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
    public function renderGalleryMetabox(WP_POST $post): void
    {
        wp_nonce_field(ProductService::GALLERY_KEY . '_save', ProductService::GALLERY_KEY . '_nonce');

        $items = get_post_meta($post->ID, ProductService::GALLERY_KEY, true);
        if (!is_array($items)) $items = [];
        ?>
        <div style="overflow: hidden;position:relative" class="hide-if-no-js">
            <div class="swiper-container" id="g3GallerySwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($items as $item) : ?>
                        <div class="swiper-slide">
                            <div class="gallery-item" data-url="<?php echo esc_url($item); ?>">
                                <?php if (preg_match('/\.(mp4|webm|ogg)$/i', $item)) : ?>
                                    <video src="<?php echo esc_url($item); ?>" controls></video>
                                <?php else : ?>
                                    <img src="<?php echo esc_url($item); ?>" />
                                <?php endif; ?>
                                <button class="button is-icon icon-error action-removeGalleryItem" type="button">
                                    <?php echo Image::icon('close'); ?>
                                </button>
                                <input type="hidden" name="<?php echo ProductService::GALLERY_KEY; ?>[]"
                                    value="<?php echo esc_url($item); ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
        <p class="hide-if-no-js">
            <a href="javascript:void(0)" id="action-addToGallery" style="font-weight: 600">+
                <?php _e('Set Gallery', 'G3'); ?>
            </a>
        </p>
        <?php
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
    public function renderSkuMetabox(WP_POST $post): void
    {
        $default = __('Default');
        $remove  = __('Remove current sku tab', 'G3');
        echo '<div class="sku-container"></div><div class="flex gap-2 m-3"><button type="button" class="button" id="add-sku">+ ' . __('Add Sku', 'G3') . '</button></div>';
        $script = <<<EOT
const removeSku = '<button type="button" class="button button-error" id="delete-sku">$remove</button>';
const skuTabs = new JUI.Tabs(false, {
    id: 'sku-tabs',
    active: 0,
    tabs: [
        { title: '$default', content: '这里是选项一的内容', name: 'sku-1' }
    ],
    onChange: function (index) {
        if (index !== 0 && $('#delete-sku').length === 0) {
            $('#add-sku').after(removeSku);
        }
        if (index === 0 && $('#delete-sku').length > 0) {
            $('#delete-sku').remove();
        }
    },
    onDelete: async (index) =>{
        if (skuTabs.tabs.length === 1 && $('#delete-sku').length > 0) {
            $('#delete-sku').remove();
        }
    },
});
document.querySelector('.sku-container').appendChild(skuTabs.el);
EOT;
        wp_add_inline_script('jui', $script);
    }
    public function renderPropertiesMetabox(WP_POST $post): void
    {
        wp_nonce_field(ProductService::PROPERTY_KEY . '_save', ProductService::PROPERTY_KEY . '_nonce');
        $properties = get_post_meta($post->ID, ProductService::PROPERTY_KEY, true);
        if (!is_array($properties)) $properties = [];
        ?>
        <div class="properties-container">
            <?php
            foreach ($properties as $key => $value) {
                ?>
                <div class="property-item">
                    <div class="property-label">
                        <?php echo __('Property', 'G3') . ' ' . ($key + 1); ?>
                    </div>
                    <div class="property-control">
                        <input type="text" name="<?php echo ProductService::PROPERTY_KEY; ?>[<?php echo $key; ?>][name]"
                            id="<?php echo ProductService::PROPERTY_KEY; ?>[<?php echo $key; ?>][name]"
                            value="<?php echo esc_attr($value['name']); ?>" placeholder="<?php _e('Enter property name', 'G3'); ?>">
                        <input type="text" name="<?php echo ProductService::PROPERTY_KEY; ?>[<?php echo $key; ?>][value]"
                            id="<?php echo ProductService::PROPERTY_KEY; ?>[<?php echo $key; ?>][value]"
                            value="<?php echo esc_attr($value['value']); ?>"
                            placeholder="<?php _e('Enter property value', 'G3'); ?>">
                        <div class="property-actions">
                            <button type="button" class="button button-error action-removeProperty">
                                <?php _e('Remove'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <div style="margin-top:8px">
            <button type="button" class="button" id="add-property">+ <?php _e('Add Property', 'G3'); ?></button>
        </div>
        <?php
    }
    private function saveProperties(): void
    {
        add_action('save_post', function ($postId) {
            if (!isset($_POST[ProductService::PROPERTY_KEY . '_nonce']) || !wp_verify_nonce($_POST[ProductService::PROPERTY_KEY . '_nonce'], ProductService::PROPERTY_KEY . '_save')) {
                return;
            }
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            if (isset($_POST[ProductService::PROPERTY_KEY]) && is_array($_POST[ProductService::PROPERTY_KEY])) {
                update_post_meta($postId, ProductService::PROPERTY_KEY, array_values($_POST[ProductService::PROPERTY_KEY]));
            } else {
                delete_post_meta($postId, ProductService::PROPERTY_KEY);
            }
        });
    }
}
