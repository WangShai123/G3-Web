<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\ProductService;
use JEALER\G3\Services\SidebarService;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Image;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Response;
use JEALER\G3\Utilities\Validator;
use Override;
use WP_Post;
use Exception;

class Product extends Components {
    public array $option;
    /**
     * @var ProductService $service
     */
    public ProductService $service;
    protected function options(): void
    {
        $this->option  = Option::get(ProductService::OPTION_KEY, [
            'cart'     => '1',
            'download' => '1',
        ]);
        $this->service = Container::run()->get(ProductService::class);
    }
    protected function form(): void
    {
        add_action('save_post_product', [$this, 'saveProduct']);
        if (!isset($_REQUEST['page']) || $_REQUEST['page'] !== 'shop-settings') return;
        $this->option = Option::cache(ProductService::OPTION_KEY, $this->option);
    }
    protected function admin(): void
    {
        add_filter('post_updated_messages', [$this, 'resetUpdatedMessages']);
        add_action('deleted_post', [$this, 'deletedProduct']);
    }
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Shop', 'G3'),
            __('Shop', 'G3'),
            'manage_options',
            'shop-settings',
            [$this, 'render'],
            5
        );
        add_submenu_page(
            'edit.php?post_type=product',
            'SKU',
            'SKU',
            'manage_options',
            'product_sku',
            function () {
                require_once 'views/sku.php';
            },
            2
        );
        add_submenu_page(
            'edit.php?post_type=product',
            __('Specifications', 'G3'),
            __('Specifications', 'G3'),
            'manage_options',
            'product_specifications',
            [$this, 'renderSpecs'],
            3
        );
    }
    public function render(): void
    {
        echo '<div class="wrap"><h1>' . __('Shop', 'G3') . '</h1>';
        $args = [
            'general' => __('General')
        ];
        Element::tab('Product', 'general', $args);
        echo '</div>';
    }
    public function renderSpecs(): void
    {
        echo '<div class="wrap">';
        $args = [
            'specifications' => __('Specifications', 'G3'),
            'options'        => __('Options', 'G3')
        ];
        Element::tab('Product', 'specifications', $args);
        echo '</div>';
    }
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
            [
                'id'       => 'cart',
                'title'    => __('Cart', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        ProductService::OPTION_KEY,
                        $this->option,
                        'cart',
                        __('Cart', 'G3'),
                        __('Whether to enable cart feature.', 'G3')
                    );
                },
                'args'     => ['className' => 'advanced']
            ],
            [
                'id'       => 'download',
                'title'    => __('Download', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        ProductService::OPTION_KEY,
                        $this->option,
                        'download',
                        __('Download', 'G3'),
                        __('Whether to support the product of download type.', 'G3')
                    );
                },
                'args'     => ['className' => 'advanced']
            ]
        ]);
    }
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
            'show_in_rest'       => true,
            'supports'           => ['title', 'editor', 'comments', 'revisions', 'author', 'excerpt', 'thumbnail'],
            'menu_icon'          => 'dashicons-cart',
            'taxonomies'         => ['product_category', 'product_brand', 'post_tag'],
        ];
        register_post_type('product', $args);
    }
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
    protected function metaBox(): void
    {
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
    protected function adminScripts(): void
    {
        if (!Validator::screen('product')) return;

        wp_register_script(
            'g3-admin-product',
            G3_ASSETS_URL . '/javascript/g3.admin.product.min.js',
            ['vanilla-signal', 'vanilla-signal-i18n', 'jui'],
            '1.0.0',
            true
        );
        wp_enqueue_script('g3-admin-product');
        $this->localizeData();
    }

    private function localizeData(): void
    {
        $postId = get_the_ID();
        $props  = get_post_meta($postId, ProductService::PROP_KEY, true) ?: [];

        $specs       = $this->service->getSpecs();
        $specOptions = [];
        $skus        = $this->service->getSkusByProductId($postId);
        if (!$skus) {
            $type = 1;
        } else {
            $type = $skus[0]['type'];
            foreach ($specs as $spec) {
                $specOptions[$spec['id']] = $this->service->getSpecOptionsBySpecId($spec['id']);
            }
        }

        $data = [
            'nonce' => wp_create_nonce('product_nonce_action'),
            'data'  => [
                'support'  => [
                    'download' => $this->option['download'],
                ],
                'data'     => [
                    'type'         => $type,
                    'specs'        => $specs,
                    'spec_options' => $specOptions,
                    'skus'         => $skus,
                ],
                'property' => [
                    'key'   => ProductService::PROP_KEY,
                    'props' => $props
                ],
                'gallery'  => [
                    'key'    => ProductService::GALLERY_KEY,
                    'photos' => get_post_meta($postId, ProductService::GALLERY_KEY, true)
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

    public function deletedProduct($postId)
    {
        $this->service->clearProductData($postId);
    }

    public function renderSkuMetabox(WP_POST $post): void
    {
        echo '<div id="detail-app"><div id="sku-app"></div></div>';
    }
    public function renderPropertiesMetabox(WP_POST $post): void
    {
        wp_nonce_field(ProductService::PROP_KEY . '_save', ProductService::PROP_KEY . '_nonce');
        echo '<div id="prop-app"></div>';
    }
    public function saveProduct($postId)
    {
        $this->saveGallery($postId);
        $this->saveProperties($postId);
        $this->saveSku($postId);
    }
    private function saveGallery($postId): void
    {
        if (!isset($_POST[ProductService::GALLERY_KEY . '_nonce']) || !wp_verify_nonce($_POST[ProductService::GALLERY_KEY . '_nonce'], ProductService::GALLERY_KEY . '_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        if (isset($_POST[ProductService::GALLERY_KEY]) && is_array($_POST[ProductService::GALLERY_KEY])) {
            update_post_meta($postId, ProductService::GALLERY_KEY, array_values($_POST[ProductService::GALLERY_KEY]));
        } else {
            delete_post_meta($postId, ProductService::GALLERY_KEY);
        }
    }
    private function saveProperties($postId): void
    {
        if (!isset($_POST[ProductService::PROP_KEY . '_nonce']) || !wp_verify_nonce($_POST[ProductService::PROP_KEY . '_nonce'], ProductService::PROP_KEY . '_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        if (isset($_POST[ProductService::PROP_KEY]) && is_array($_POST[ProductService::PROP_KEY])) {
            update_post_meta($postId, ProductService::PROP_KEY, array_values($_POST[ProductService::PROP_KEY]));
        } else {
            delete_post_meta($postId, ProductService::PROP_KEY);
        }
    }
    private function saveSku($postId): void
    {
        if (!isset($_POST['g3_sku']) || !is_array($_POST['g3_sku'])) {
            return;
        }

        $skusData = $_POST['g3_sku'];

        foreach ($skusData as $skuKey => $skuInfo) {

            // check if it's a valid SKU ID or a temporary key
            $isExistingSku = is_numeric($skuKey);

            // build SKU base data
            $skuBaseData = [
                'product_id'    => $postId,
                'regular_price' => floatval($skuInfo['regular_price'] ?? 0),
                'price'         => floatval($skuInfo['price'] ?? 0),
                'weight'        => floatval($skuInfo['weight'] ?? 0),
                'unit'          => sanitize_text_field($skuInfo['unit'] ?? ''),
                'stock'         => intval($skuInfo['stock'] ?? 0),
                'sold'          => intval($skuInfo['sold'] ?? 0),
                'type'          => intval($skuInfo['type'] ?? 1),
            ];

            if ($isExistingSku) {
                // update existing sku
                $skuBaseData['id'] = $skuKey;
                $skuId             = $this->service->updateSku($skuBaseData);
            } else {
                // create new sku
                $skuId = $this->service->createSku($skuBaseData);
            }

            // skip specs if sku creation/update failed
            if (!$skuId || $skuId === false) {
                continue;
            }

            if (isset($skuInfo['specs']) && is_array($skuInfo['specs'])) {
                // delete existing spec relations
                $this->service->deleteSkuSpecRelations($skuId);
                $this->service->deleteProductSpecRelation($postId);

                // add new spec relations
                foreach ($skuInfo['specs'] as $specId => $specOptionData) {
                    if (is_numeric($specId) && isset($specOptionData['option_id']) && is_numeric($specOptionData['option_id'])) {
                        $this->service->addSkuSpecRelation($skuId, $specId, $specOptionData['option_id']);
                        $this->service->addProductSpecRelations($postId, $specId);
                    }
                }
            }
        }

        $this->service->clearProductCache($postId);
    }


    protected function ajax(): void
    {
        add_action('wp_ajax_g3_update_spec', function () {
            $fields = $_POST['fields'] ?? [];
            $name   = $fields['name'] ?? '';
            $key    = $fields['key'] ?? '';
            if (
                !is_admin()
                || !current_user_can('manage_options')
                || empty($name) || empty($key)
            ) {
                Response::ajaxIllegal();
            }

            $owner_id = maybe_serialize(array_values(array_unique(array_filter(array_map('intval', array_filter(array_map('trim', explode(',', (string) ($fields['owner_ids'] ?? ''))), fn($v) => $v !== '' && ctype_digit($v))), fn($n) => $n > 0))));

            $data   = [
                'id'        => $fields['id'] ?? 0,
                'name'      => $name,
                'key'       => $key,
                'is_global' => (int) $fields['is_global'] ?? 1,
                'scope'     => (int) $fields['scope'] ?? 0,
                'owner_ids' => $owner_id,
                'status'    => $fields['status'] ?? 1,
                'sort'      => $fields['sort'] ?? 0,
            ];
            $result = $this->service->updateSpec($data);

            if ($result === -1) {
                Response::ajaxError(__('Name or Key already exists.', 'G3'));
            }

            if ($result) {
                Response::ajaxUpdated();
            } else {
                Response::ajaxFailed();
            }
        });

        add_action('wp_ajax_g3_delete_spec', function () {
            $id = (int) $_POST['id'] ?? 0;
            if (
                !$id
                || !is_admin()
                || !current_user_can('manage_options')
            ) {
                Response::ajaxIllegal();
            }
            $options = $this->service->getSpecOptionsBySpecId($id);
            if (count($options) !== 0) {
                Response::ajaxError(__('Cannot delete this spec, it is used in some sku or options.', 'G3'));
            }
            $result = $this->service->deleteSpec($id);
            if ($result) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });

        add_action('wp_ajax_g3_update_spec_option', function () {
            $fields  = $_POST['fields'] ?? [];
            $name    = $fields['name'] ?? '';
            $key     = $fields['key'] ?? '';
            $spec_id = $fields['spec_id'] ?? '';
            if (
                !is_admin()
                || !current_user_can('manage_options')
                || empty($name) || empty($key) || empty($spec_id)
            ) {
                Response::ajaxIllegal();
            }

            $data = [
                'id'      => $fields['id'] ?? 0,
                'spec_id' => (int) $spec_id,
                'name'    => $name,
                'key'     => $key,
                'sort'    => $fields['sort'] ?? 0,
                'status'  => $fields['status'] ?? 1,
            ];

            $result = $this->service->updateSpecOption($data);

            if (is_wp_error($result)) {
                Response::ajaxError($result->get_error_message());
            }

            if ($result) {
                Response::ajaxUpdated();
            } else {
                Response::ajaxFailed();
            }
        });
        add_action('wp_ajax_g3_delete_spec_option', function () {
            $id = (int) ($_POST['id'] ?? 0);

            if (
                !$id
                || !is_admin()
                || !current_user_can('manage_options')
            ) {
                Response::ajaxIllegal();
            }

            // check if there is any SKU using this spec option
            global $wpdb;
            $relations_table = $wpdb->prefix . ProductService::SPECS_RELATIONS_TABLE;
            $relation_count  = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$relations_table} WHERE spec_option_id = %d",
                    $id
                )
            );

            if ($relation_count > 0) {
                Response::ajaxError(__('Cannot delete this spec option, it is used in some SKUs.', 'G3'));
            }

            // get spec id for clear cache
            $table       = $wpdb->prefix . ProductService::SPECS_OPTIONS_TABLE;
            $spec_option = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT spec_id FROM {$table} WHERE id = %d",
                    $id
                )
            );

            if (!$spec_option) {
                Response::ajaxError(__('Spec option not found.', 'G3'));
            }

            $result = $wpdb->delete(
                $table,
                ['id' => $id],
                ['%d']
            );

            if ($result !== false) {
                $cache_key = "option:spec_{$spec_option->spec_id}";
                wp_cache_delete($cache_key, ProductService::CACHE_GROUP);

                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });

        add_action('wp_ajax_g3_get_spec_options', function () {
            $specId = (int) ($_POST['spec_id'] ?? 0);
            if (
                !$specId
                || !is_admin()
                || !current_user_can('manage_options')
            ) {
                Response::ajaxIllegal();
            }
            try {
                $options = $this->service->getSpecOptionsBySpecId($specId);

                wp_send_json_success([
                    'options' => $options
                ]);
            }
            catch (Exception $e) {
                error_log('Error getting spec options: ' . $e->getMessage());
                Response::ajaxError('Failed to get spec options.');
            }
        });

        add_action('wp_ajax_g3_delete_sku', function () {
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id || !is_admin() || !current_user_can('manage_options')) {
                Response::ajaxIllegal();
            }
            try {
                $result = $this->service->deleteSku($id);
                if ($result) {
                    Response::ajaxDeleted();
                } else {
                    Response::ajaxFailed();
                }
            }
            catch (Exception $e) {
                error_log('Error delete sku: ' . $e->getMessage());
                Response::ajaxError('Failed to delete sku.');
            }
        });
    }
}
