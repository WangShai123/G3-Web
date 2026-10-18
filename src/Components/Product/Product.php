<?php
namespace JEALER\G3\Components;
use JEALER\G3\Core\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\PostService;
use JEALER\G3\Services\ProductService;
use JEALER\G3\Services\SidebarService;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Response;
use JEALER\G3\Utilities\Validator;
use Override;
use WP_Post;
use Exception;

class Product extends Components {

    private ProductService $productService;
    private PostService    $postService;

    protected function start()
    {
        $this->productService = $this->service(ProductService::class);
        $this->postService    = $this->service(PostService::class);
    }

    protected function hooks()
    {
        $this->filter([
            'manage_edit-product_columns'        => [[$this, 'productColumns'], 20, 1],
            'manage_product_posts_custom_column' => [[$this, 'productCustomColumn'], 10, 2],
        ]);
        $this->action([
            'before_delete_post'     => [[$this, 'deleteProductData'], 10, 2],
            'transition_post_status' => [[$this, 'syncSkuStatus'], 10, 3],
        ]);
    }

    protected function defaultOption(): array
    {
        return [
            ProductService::OPTION_KEY => ProductService::optionDefaults(),
        ];
    }

    protected function admin(): void
    {
        add_action('save_post_product', [$this, 'saveSku']);
        add_filter('post_updated_messages', [$this, 'resetUpdatedMessages']);
    }
    protected function adminMenu(): void
    {
        // $shop  = __('Shop', 'G3');
        $shop  = __('Settings');
        $specs = __('Specifications', 'G3');

        add_submenu_page(
            // 'g3-settings',
            'edit.php?post_type=product',
            $shop,
            $shop,
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
            $specs,
            $specs,
            'manage_options',
            'product_specifications',
            [$this, 'renderSpecs'],
            3
        );
    }
    public function render(): void
    {
        $this->createPanel();
    }
    public function renderSpecs(): void
    {
        $specs = __('Specifications', 'G3');
        echo '<div class="wrap"><h1>' . $specs . '</h1>';
        $args = [
            'specifications' => $specs,
            'options'        => __('Options', 'G3')
        ];
        Element::tab('Product', 'specifications', $args);
        echo '</div>';
    }
    protected function adminPanelPage(): string
    {
        return 'shop-settings';
    }
    protected function adminPanels(): array
    {
        return [
            $this->panel('shop-settings', sprintf(__('%s %s', 'G3'), __('Shop', 'G3'), __('Settings')))
                ->tab('general', __('General'))
                ->option(ProductService::OPTION_KEY, ProductService::optionDefaults())
                ->number('safeStock', sprintf(__('%s %s', 'G3'), __('Safe', 'G3'), 'SKU'), __('The minimum stock quantity required to be considered as safe.', 'G3'))
                ->switch('cart', __('Cart', 'G3'))
                ->rowClass('advanced')
                ->switch('download', __('Download', 'G3'), __('Whether to support the product of download type.', 'G3'))
                ->rowClass('advanced'),
        ];
    }

    public function productColumns(array $columns)
    {
        /**
         * 1. unset columns
         */
        unset($columns['author'], $columns['tags']);

        /**
         * 2. add sku columns
         */
        $newColumns = [];

        foreach ($columns as $key => $label) {
            // insert before comments
            if ('views' === $key) {
                $newColumns['sku'] = 'SKU';
            }
            $newColumns[$key] = $label;
        }

        if (!isset($newColumns['sku'])) {
            $newColumns['sku'] = 'SKU';
        }

        return $newColumns;
    }

    public function productCustomColumn(string $columnName, int $postId)
    {
        if ('sku' === $columnName) {
            $skuData = $this->productService->getSkusByProductId($postId);

            $count        = 0;
            $hasLowStock  = false;
            $safeSkuStock = $this->option()['safeStock'] ?? 10;
            foreach ($skuData as $sku) {
                $stock  = isset($sku['stock']) ? (int) $sku['stock'] : 0;
                $count += $stock;
                if ($stock < $safeSkuStock) {
                    $hasLowStock = true;
                }
            }

            $class = $hasLowStock ? ' class="mark-error"' : '';
            echo '<span' . $class . '>' . esc_html($count) . '</span>';
        }
    }

    protected function postType(): void
    {
        $product  = __('Product', 'G3');
        $products = __('Products', 'G3');
        $shop     = __('Shop', 'G3');
        $list     = __('List', 'G3');

        $addNew = __('Add New', 'G3');

        $ss  = __('%s %s', 'G3');
        $sss = __('%s %s %s', 'G3');

        $labels = [
            'name'                  => $products,
            'singular_name'         => $product,
            'menu_name'             => $shop,
            'name_admin_bar'        => $product,
            'add_new'               => $addNew,
            'add_new_item'          => sprintf($ss, $addNew, $products),
            'new_item'              => sprintf(__('New %s', 'G3'), $product),
            'edit_item'             => sprintf($ss, __('Edit'), $product),
            'view_item'             => sprintf(__('View %s', 'G3'), $product),
            'all_items'             => sprintf($ss, __('All', 'G3'), $products),
            'search_items'          => sprintf($ss, __('Search'), $products),
            'parent_item_colon'     => sprintf(__('Parent %s', 'G3') . ':', $product),
            'not_found'             => sprintf(__('No %s', 'G3'), $product),
            'not_found_in_trash'    => sprintf(__('No %s', 'G3'), $product),
            'featured_image'        => __('Cover', 'G3'),
            'set_featured_image'    => __('Set cover', 'G3'),
            'remove_featured_image' => __('Remove cover', 'G3'),
            'use_featured_image'    => __('Use as cover', 'G3'),
            'archives'              => sprintf($ss, $product, __('Archives')),
            'insert_into_item'      => sprintf(__('Insert into %s', 'G3'), $product),
            'uploaded_to_this_item' => sprintf(__('Uploaded to this %s', 'G3'), $product),
            'filter_items_list'     => sprintf($sss, __('Filter', 'G3'), $products, $list),
            'items_list_navigation' => sprintf($sss, $products, $list, __('Navigation')),
            'items_list'            => sprintf($ss, $products, $list),
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
        $product    = __('Product', 'G3');
        $products   = __('Products', 'G3');
        $brand      = __('Brand', 'G3');
        $brands     = __('Brands', 'G3');
        $category   = __('Category');
        $categories = __('Categories');

        $ss = __('%s %s', 'G3');

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
                    'name'          => $categories,
                    'singular_name' => $category,
                    'search_items'  => sprintf($ss, __('Search'), $categories),
                    'not_found'     => sprintf(__('No %s', 'G3'), $category),
                    'all_items'     => __('All Categories'),
                    'menu_name'     => $categories
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
                    'name'              => $brands,
                    'singular_name'     => $brand,
                    'search_items'      => sprintf($ss, __('Search'), $brands),
                    'not_found'         => sprintf(__('No %s', 'G3'), $brand),
                    'all_items'         => sprintf($ss, __('All', 'G3'), $brands),
                    'parent_item'       => sprintf(__('Parent %s', 'G3'), $brand),
                    'parent_item_colon' => sprintf(__('Parent %s', 'G3') . ':', $brand),
                    'edit_item'         => sprintf($ss, __('Edit'), $brand),
                    'update_item'       => sprintf($ss, __('Update'), $brand),
                    'add_new_item'      => sprintf($ss, __('Add New', 'G3'), $brand),
                    'new_item_name'     => sprintf(__('New %s Name', 'G3'), $brand),
                    'menu_name'         => $brands
                ]
            ]
        );
    }
    protected function sidebar(): void
    {
        $shop    = __('Shop', 'G3');
        $product = __('Product', 'G3');
        register_sidebar(
            [
                'name'          => sprintf(__('%s Sidebar', 'G3'), $shop),
                'id'            => 'shop',
                'description'   => sprintf(__('You can add widgets to %s sidebar.', 'G3'), $shop),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );
        register_sidebar(
            [
                'name'          => sprintf(__('%s Sidebar', 'G3'), $product),
                'id'            => 'product',
                'description'   => sprintf(__('You can add widgets to %s sidebar.', 'G3'), $product),
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

        $product = __('Product', 'G3');
        $updated = __('Updated', 'G3');

        $messages[$postType] = array(
            0  => '',
            // Unused. Messages start at index 1.
            1  => $updated,
            2  => $updated,
            3  => $updated,
            4  => $updated,
            /* translators: %s: date and time of the revision */
            5  => isset($_GET['revision']) ? sprintf(__('Restored to version from %s.', 'G3'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6  => __('Published'),
            7  => __('Saved'),
            8  => __('Submitted', 'G3'),
            9  => sprintf(
                __('Scheduled for:<strong>%1$s</strong>.', 'G3'),
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post->post_date))
            ),
            10 => $updated,
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
            G3_ASSETS_URL . '/js/g3.admin.product.min.js',
            ['vanilla-signal', 'vanilla-signal-i18n', 'jui'],
            '1.0.0',
            true
        );
        wp_enqueue_script('g3-admin-product');
        $this->localizeData();
    }

    private function localizeData(): void
    {
        $postId   = get_the_ID();
        $extra    = $this->postService->getExtra($postId);
        $property = $extra['property'] ?? [];
        $gallery  = $extra['gallery'] ?? [];

        $specs       = $this->productService->getSpecs();
        $specOptions = [];
        $skus        = $this->productService->getSkusByProductId($postId);
        if (!$skus) {
            $type = 1;
        } else {
            $type = $skus[0]['type'];
            foreach ($specs as $spec) {
                $specOptions[$spec['id']] = $this->productService->getSpecOptionsBySpecId($spec['id']);
            }
        }

        $data = [
            'nonce' => wp_create_nonce('product_nonce_action'),
            'data'  => [
                'support'  => [
                    'download' => get_option(ProductService::OPTION_KEY)['download'],
                ],
                'data'     => [
                    'type'         => $type,
                    'specs'        => $specs,
                    'spec_options' => $specOptions,
                    'skus'         => $skus,
                ],
                'property' => [
                    'k' => PostService::PROPERTY_NAME,
                    'v' => $property
                ],
                'gallery'  => [
                    'k' => PostService::GALLERY_NAME,
                    'v' => $gallery
                ]
            ]
        ];
        wp_localize_script('g3-admin-product', 'productData', $data);
    }

    public function renderGalleryMetabox(WP_POST $post): void
    {
        wp_nonce_field(PostService::GALLERY_NAME . '_save', PostService::GALLERY_NAME . '_nonce');
        echo '<div id="gallery-app"></div>';
    }

    public function deleteProductData(int $postId, ?WP_Post $post = null): void
    {
        if (!$this->isProductPost($postId, $post)) {
            return;
        }

        $this->productService->clearProductData($postId);
    }

    public function syncSkuStatus(string $newStatus, string $oldStatus, WP_Post $post): void
    {
        $postId = (int) $post->ID;
        if (!$this->isProductPost($postId, $post)) {
            return;
        }

        if ($newStatus === 'publish' && $oldStatus !== 'publish') {
            $this->productService->updateSkuStatusByProductId($postId, 1);
            return;
        }

        if ($newStatus !== 'publish') {
            $this->productService->updateSkuStatusByProductId($postId, 0);
        }
    }

    private function isProductPost(int $postId, ?WP_Post $post = null): bool
    {
        if ($post instanceof WP_Post) {
            return $post->post_type === 'product';
        }

        return get_post_type($postId) === 'product';
    }

    public function renderSkuMetabox(WP_POST $post): void
    {
        echo '<div id="detail-app"><div id="sku-app" data-component></div></div>';
    }
    public function renderPropertiesMetabox(WP_POST $post): void
    {
        echo '<div id="prop-app" data-component></div>';
    }
    public function saveSku($postId): void
    {
        $name = PostService::SKU_NAME;
        if (!isset($_POST[$name]) || !is_array($_POST[$name])) {
            return;
        }

        $skuData        = $_POST[$name];
        $productSpecIds = [];

        $this->productService->deleteProductSpecRelation($postId);

        foreach ($skuData as $skuKey => $skuInfo) {
            // check if it's a valid SKU ID or a temporary key
            $isExistingSku = is_numeric($skuKey);
            $skuSpecs      = isset($skuInfo['specs']) && is_array($skuInfo['specs']) ? $skuInfo['specs'] : [];

            // build SKU base data
            $skuBaseData = [
                'product_id'    => $postId,
                'regular_price' => floatval($skuInfo['regular_price'] ?? 0),
                'cost_price'    => floatval($skuInfo['cost_price'] ?? 0),
                'price'         => floatval($skuInfo['price'] ?? 0),
                'stock'         => intval($skuInfo['stock'] ?? 0),
                'sold'          => intval($skuInfo['sold'] ?? 0),
                // 'weight'        => floatval($skuInfo['weight'] ?? 0),
                // 'unit'          => sanitize_text_field($skuInfo['unit'] ?? ''),
                'type'          => intval($skuInfo['type'] ?? 1),
            ];

            if ($isExistingSku) {
                // update existing sku
                $skuBaseData['id'] = $skuKey;
                $skuId             = $this->productService->updateSku($skuBaseData);
            } else {
                // create new sku
                $skuBaseData['_specs']   = $skuSpecs;
                $skuBaseData['_row_key'] = (string) $skuKey;
                $skuId                   = $this->productService->createSku($skuBaseData);
            }

            // skip specs if sku creation/update failed
            if (!$skuId || $skuId === false) {
                continue;
            }

            $this->productService->deleteSkuSpecRelations($skuId);

            foreach ($skuSpecs as $specId => $specOptionData) {
                if (!is_array($specOptionData)) {
                    continue;
                }

                $currentSpecId = (int) ($specOptionData['spec_id'] ?? $specId);
                $specOptionId  = (int) ($specOptionData['option_id'] ?? 0);

                if ($currentSpecId > 0 && $specOptionId > 0) {
                    $this->productService->addSkuSpecRelation($skuId, $currentSpecId, $specOptionId);
                    $productSpecIds[$currentSpecId] = $currentSpecId;
                }
            }
        }

        foreach ($productSpecIds as $specId) {
            $this->productService->addProductSpecRelations($postId, $specId);
        }

        $this->productService->clearProductCache($postId);
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
            $result = $this->productService->updateSpec($data);

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
            $options = $this->productService->getSpecOptionsBySpecId($id);
            if (count($options) !== 0) {
                Response::ajaxError(__('Cannot delete this spec, it is used in some sku or options.', 'G3'));
            }
            $result = $this->productService->deleteSpec($id);
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

            $result = $this->productService->updateSpecOption($data);

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
                $options = $this->productService->getSpecOptionsBySpecId($specId);

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
                $result = $this->productService->deleteSku($id);
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
