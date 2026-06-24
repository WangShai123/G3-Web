<?php
namespace JEALER\G3\Services;
use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\Common;
use WP_Error;
use Exception;
use wpdb;

class ProductService {
    // Option Key
    const OPTION_KEY = 'g3_option_shop';
    // Gallery Key
    const GALLERY_KEY = 'g3_product_gallery';
    // Property Key
    const PROP_KEY = 'g3_product_prop';
    // SKU Table
    const SKU_TABLE = 'g3_sku';
    // Global Specs Table
    const SPECS_TABLE = 'g3_specs';
    // Global Specs Options Table
    const SPECS_OPTIONS_TABLE = 'g3_specs_options';
    // Product Specs Relation Table
    const PRODUCT_SPECS_TABLE = 'g3_product_specs';
    // Sku Specs Relation Table
    const SPECS_RELATIONS_TABLE = 'g3_sku_specs_relations';
    // Product Cache Group
    const CACHE_GROUP = 'g3_products';
    /**
     * Cache Expire Time in Admin
     * 后台管理中缓存的过期时间
     * 有些逻辑和查询仅用于后台管理，设置一个较短的缓存时间
     */
    const EXPIRE_IN_ADMIN = DAY_IN_SECONDS;
    /**
     * Cache Expire Time in Global
     * 全局缓存的过期时间
     * 有些逻辑和查询用于全局且侧重用户端显示，设置一个较长的缓存时间
     */
    const EXPIRE_IN_GLOBAL = WEEK_IN_SECONDS;
    private wpdb $wpdb;
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    /**
     * render spec scope
     * 
     * 渲染规格应用范围
     * 
     * @param int $id
     * @return string
     */
    public static function renderScope(int $id): string
    {
        return match ($id) {
            0       => __('All'),
            1       => __('Product', 'G3'),
            2       => __('Categories'),
            3       => __('Tags'),
            4       => __('Brand', 'G3'),
            default => 'All'
        };
    }

    /**
     * Render SKU TYPE
     * 
     * 渲染SKU类型 1: general, 2: digital, 3: membership, 4: download
     * 
     * @param int $typeId
     * @return string
     */
    public static function renderSkuType(int $typeId): string
    {
        return match ($typeId) {
            1       => __('General Product', 'G3'),
            2       => __('Digital Product', 'G3'),
            3       => __('Membership Product', 'G3'),
            4       => __('Download Product', 'G3'),
            default => __('Unknown')
        };
    }

    /**
     * Update Product Spec
     * 
     * 更新产品规格
     * 
     * @param array $data
     * @return int|bool
     */
    public function updateSpec(array $data): int|bool
    {
        if (!isset($data['name']) || !isset($data['key']) || trim($data['name']) === '' || trim($data['name']) === '') {
            return false;
        }

        $cacheKey = Common::getCacheKey('specs', 'all');
        $table    = $this->wpdb->prefix . self::SPECS_TABLE;
        $id       = $data['id'] ?? 0;

        if ($id > 0) {
            $result = $this->wpdb->update($table, $data, ['id' => $id]);
            if ($result !== false) {
                wp_cache_delete($cacheKey, self::CACHE_GROUP);
                return $id;
            }
        } else {
            $result = $this->wpdb->insert($table, $data);
            if ($result === false) {
                // 检查是否是由于唯一键约束导致的错误
                if ($this->wpdb->last_error && strpos($this->wpdb->last_error, 'Duplicate entry') !== false) {
                    new WP_Error('db_error', $this->wpdb->last_error);
                    return -1;
                }
                return false;
            }
            wp_cache_delete($cacheKey, self::CACHE_GROUP);
            return $this->wpdb->insert_id;
        }
        return false;
    }

    /**
     * Get all specs
     * 
     * 获取所有规格
     * 
     * @return array
     */
    public function getSpecs(): array
    {
        $cacheKey = Common::getCacheKey('specs', 'all');
        $specs    = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($specs === false) {
            $table = $this->wpdb->prefix . self::SPECS_TABLE;
            $specs = $this->wpdb->get_results(
                "SELECT id, name FROM {$table} WHERE status = 1 ORDER BY sort ASC",
                ARRAY_A
            );
            wp_cache_set($cacheKey, $specs, self::CACHE_GROUP, self::EXPIRE_IN_ADMIN);
        }

        return $specs;
    }

    /**
     * Delete a spec
     * 
     * 删除一个规格
     * 
     * @param int $id
     * @return bool|int
     */
    public function deleteSpec(int $id): bool|int
    {
        $result = $this->wpdb->delete($this->wpdb->prefix . ProductService::SPECS_TABLE, ['id' => $id]);
        if ($result !== false) {
            wp_cache_delete(Common::getCacheKey('specs', 'all'), self::CACHE_GROUP);
        }
        return $result;
    }

    /**
     * update a spec option
     * 
     * 更新一个规格选项
     * 
     * @param array $data
     * @return bool|int|WP_Error
     */
    public function updateSpecOption(array $data): bool|int|WP_Error
    {
        $cacheKey = Common::getCacheKey($data['spec_id'], 'option', 'spec');
        $table    = $this->wpdb->prefix . ProductService::SPECS_OPTIONS_TABLE;
        $id       = $data['id'] ?? 0;
        if ($id > 0) {
            $result = $this->wpdb->update($table, $data, ['id' => $id]);
            if ($result !== false) {
                wp_cache_delete($cacheKey, self::CACHE_GROUP);
                return $id;
            }
        } else {
            $result = $this->wpdb->insert($table, $data);
            if ($result) {
                wp_cache_delete($cacheKey, self::CACHE_GROUP);
                return $this->wpdb->insert_id;
            }
            if ($this->wpdb->last_error && strpos($this->wpdb->last_error, 'Duplicate entry') !== false) {
                return new WP_Error('db_error', $this->wpdb->last_error);
            }
        }
        return false;
    }

    /**
     * create sku code
     * 
     * 创建 sku 编码
     * 
     * @param int $productId
     * @param int $typeId
     * @param string $prefix default 'G'
     * @param string $separator default '-'
     * @return string
     */
    public function createSkuCode(int $productId, int $typeId, string $prefix = 'G', string $separator = '-'): string
    {
        /**
         * 临时策略:
         *  - prefix: G
         *  - typeId: 00, 如果不足2位，则前面补0 
         *  - productBrand: 取 slug
         *  - productCategory: 取 slug
         *  - productId: 000000，如果不足6位，则前面补0
         *  - separator: dash
         */

        if ($productId <= 0) {
            throw new Exception("Invalid product ID: {$productId}. Must be greater than 0.");
        }

        if ($typeId <= 0) {
            throw new Exception("Invalid type ID: {$typeId}. Must be greater than 0.");
        }

        $termsData = PostService::getTerms($productId, ['product_brand', 'product_category'], 'product');

        $brandSlug = '';
        if (!empty($termsData['product_brand']) && is_array($termsData['product_brand'])) {
            $brandSlug = $termsData['product_brand'][0]['slug'] ?? '';
        }

        $categoryIds = [];
        if (!empty($termsData['product_category']) && is_array($termsData['product_category'])) {
            $categoryIds = array_column($termsData['product_category'], 'slug');
        }
        $categories = implode('-', $categoryIds);

        $skuCode = $prefix . str_pad($typeId, 2, '0', STR_PAD_LEFT) . "{$separator}{$brandSlug}{$separator}{$categories}{$separator}" . str_pad($productId, 6, '0', STR_PAD_LEFT);

        return $skuCode;
    }

    /**
     * Update SKU
     * 
     * 更新SKU
     * 
     * @param array $data
     * @return int|bool
     */
    public function updateSku(array $data): bool|int
    {
        if (!isset($data['id']) || !isset($data['product_id'])) {
            return false;
        }

        $table = $this->wpdb->prefix . self::SKU_TABLE;
        $skuId = (int) $data['id'];
        unset($data['id']);

        $result = $this->wpdb->update($table, $data, ['id' => $skuId]);

        if ($result !== false) {
            $this->clearProductCache($data['product_id']);
            return $skuId;
        }

        return false;
    }

    /**
     * Create SKU
     * 
     * 创建SKU
     * 
     * @param array $data
     * @return int|bool
     */
    public function createSku(array $data): bool|int
    {
        if (!isset($data['product_id'])) {
            return false;
        }
        if (!isset($data['sku_code']) || !$data['sku_code']) {
            $data['sku_code'] = $this->createSkuCode($data['product_id'], $data['type']);
        }

        $table = $this->wpdb->prefix . self::SKU_TABLE;

        $result = $this->wpdb->insert($table, $data);

        if ($result !== false) {
            $skuId = $this->wpdb->insert_id;
            $this->clearProductCache($data['product_id']);
            return $skuId;
        }

        return false;
    }

    /**
     * delete sku
     * 
     * 删除一条 sku 数据
     *
     * @param int $skuId
     * @return bool
     */
    public function deleteSku($skuId): bool
    {
        if ($skuId <= 0) return false;

        $this->wpdb->query('START TRANSACTION');

        try {
            // 1. delete from sku table
            $skuTable  = $this->wpdb->prefix . self::SKU_TABLE;
            $skuResult = $this->wpdb->delete($skuTable, ['id' => $skuId]);

            if ($skuResult === false) {
                throw new Exception("Failed to delete SKU from table: {$skuTable}");
            }

            // 2. delete from specs_relations
            $relationTable  = $this->wpdb->prefix . self::SPECS_RELATIONS_TABLE;
            $relationResult = $this->wpdb->delete($relationTable, ['sku_id' => $skuId]);

            if ($relationResult === false) {
                throw new Exception("Failed to delete SKU relations from table: {$relationTable}");
            }

            // 3. commit transaction
            $this->wpdb->query('COMMIT');

            // 4. clear cache if needed

            return true;
        }
        catch (Exception $e) {
            // rollback transaction
            $this->wpdb->query('ROLLBACK');

            error_log("Error deleting SKU (ID: {$skuId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete SKU Spec Relations
     * 
     * 删除SKU规格关系
     * 
     * @param int $skuId
     * @return bool|int
     */
    public function deleteSkuSpecRelations(int $skuId): bool|int
    {
        $table = $this->wpdb->prefix . self::SPECS_RELATIONS_TABLE;
        return $this->wpdb->delete($table, ['sku_id' => $skuId]);
    }

    /**
     * Add SKU Spec Relation
     * 
     * 添加SKU规格关系
     * 
     * @param int $skuId
     * @param int $specId
     * @param int $specOptionId
     * @return bool|int
     */
    public function addSkuSpecRelation(int $skuId, int $specId, int $specOptionId): bool|int
    {
        $table = $this->wpdb->prefix . self::SPECS_RELATIONS_TABLE;

        $data = [
            'sku_id'         => $skuId,
            'spec_option_id' => $specOptionId,
        ];

        return $this->wpdb->insert($table, $data);
    }

    /**
     * add product spec relation
     * 
     * 添加商品规格关系
     * 
     * @param int $productId
     * @param int $specId
     * @return int|bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public function addProductSpecRelations(int $productId, int $specId): int|bool
    {
        $table  = $this->wpdb->prefix . self::PRODUCT_SPECS_TABLE;
        $result = $this->wpdb->insert(
            $table,
            [
                'product_id' => $productId,
                'spec_id'    => $specId,
            ]
        );
        if ($result) {
            $key = Common::getCacheKey($productId, 'spec');
            wp_cache_delete($key, self::CACHE_GROUP);
        }
        return $result;
    }

    /**
     * delete product spec relation
     * 
     * 删除商品规格关系
     *
     * @param int $productId
     * @return bool|int
     */
    public function deleteProductSpecRelation($productId): bool|int
    {
        $table  = $this->wpdb->prefix . self::PRODUCT_SPECS_TABLE;
        $result = $this->wpdb->delete($table, ['product_id' => $productId]);
        if ($result) {
            $key = Common::getCacheKey($productId, 'spec');
            wp_cache_delete($key, self::CACHE_GROUP);
        }
        return $result;
    }

    /**
     * check if cart feature available
     * 
     * 检查购物车功能是否可用
     * 
     * @return bool|string
     */
    public static function isCartAvailable()
    {
        return Context::get(self::OPTION_KEY)['cart'] ?? false;
    }

    /**
     * Get Product All SKU, with specs options
     * 
     * 获取商品所有 SKU（含规格选项 ID 列表）
     * 
     * @param int $productId
     * @return array
     */
    public function getSkusByProductId(int $productId)
    {
        $cacheKey = Common::getCacheKey($productId, 'sku', 'product');
        $skus     = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($skus === false) {
            $table = $this->wpdb->prefix . self::SKU_TABLE;
            $rows  = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$table} WHERE product_id = %d ORDER BY id ASC",
                    $productId
                ),
                ARRAY_A
            );

            foreach ($rows as &$row) {
                $row['specs'] = $this->getSkuSpecs($row['id']);
            }

            $skus = $rows ?: [];
            wp_cache_set($cacheKey, $skus, self::CACHE_GROUP, self::EXPIRE_IN_GLOBAL);
        }

        return $skus;
    }

    /**
     * Get Specs By Sku ID
     * 
     * 获取 SKU 关联的规格组合 [ [spec_id=>1, option_id=>2, spec_name=>'颜色', option_name=>'红色'], [spec_id=>1, option_id=>3, spec_name=>'颜色', option_name=>'蓝色'] ] 
     * 
     * @param int $skuId
     * @return array
     */
    private function getSkuSpecs(int $skuId): array
    {
        $relTable          = $this->wpdb->prefix . self::SPECS_RELATIONS_TABLE;
        $specsOptionsTable = $this->wpdb->prefix . self::SPECS_OPTIONS_TABLE;
        $specsTable        = $this->wpdb->prefix . self::SPECS_TABLE;

        // 查询 SKU 关联的规格选项，通过关系表连接规格选项表和规格表获取完整的规格信息
        $relations = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT 
                so.spec_id AS spec_id,
                sr.spec_option_id AS option_id,
                s.name AS spec_name,
                so.name AS option_name
             FROM {$relTable} sr 
             INNER JOIN {$specsOptionsTable} so ON sr.spec_option_id = so.id 
             INNER JOIN {$specsTable} s ON so.spec_id = s.id
             WHERE sr.sku_id = %d 
             ORDER BY s.sort ASC, so.sort ASC",
                $skuId
            ),
            ARRAY_A
        );

        return $relations ?: [];
    }

    /**
     * Get Spec Options By Spec Id
     * 
     * 根据规格ID获取规格选项
     * 
     * @param int $specId 规格ID
     * @param bool $enabled 是否启用
     * @return array
     */
    public function getSpecOptionsBySpecId(int $specId, bool $enabled = true): array
    {
        $cacheKey = Common::getCacheKey($specId, 'option', 'spec');
        $options  = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($options === false) {
            $table       = $this->wpdb->prefix . self::SPECS_OPTIONS_TABLE;
            $whereClause = $enabled ? "WHERE spec_id = %d AND status = 1" : "WHERE spec_id = %d";

            $options = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT id, name, `key` FROM {$table} {$whereClause} ORDER BY sort ASC",
                    $specId
                ),
                ARRAY_A
            );
            wp_cache_set($cacheKey, $options, self::CACHE_GROUP, self::EXPIRE_IN_ADMIN);
        }

        return $options;
    }

    /**
     * Clear Product Cache
     *
     * 清除商品缓存
     *
     * @param int $postId
     * @return void
     */
    public function clearProductCache(int $postId): void
    {
        wp_cache_delete(Common::getCacheKey($postId, 'product'), self::CACHE_GROUP);
        wp_cache_delete(Common::getCacheKey($postId, 'option', 'spec'), self::CACHE_GROUP);
        wp_cache_delete(Common::getCacheKey($postId, 'sku', 'product'), self::CACHE_GROUP);
        wp_cache_delete(Common::getCacheKey($postId, 'spec'), self::CACHE_GROUP);
    }

    /**
     * clear product data while delete product post
     * 
     * @param int $postId
     */
    public function clearProductData(int $postId)
    {
        //@todo delete product related data: sku, specs, cache...
    }














    /**
     * Get Product Base Data with Gallery
     * 
     * 获取商品基础数据+图册
     * 
     * @param int $postId
     * @return array|null
     */
    public function getProduct(int $postId): array|null
    {
        $cacheKey = Common::getCacheKey($postId, 'product');
        $product  = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($product === false) {
            $post = get_post($postId);
            if (!$post || $post->post_type !== 'product') {
                return null;
            }

            // 获取图册（数组）
            $gallery = get_post_meta($postId, self::GALLERY_KEY, true);
            if (!is_array($gallery)) {
                $gallery = [];
            }

            $product = [
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'content'    => $post->post_content,
                'status'     => $post->post_status,
                'thumbnail'  => get_the_post_thumbnail_url($post, 'full'),
                'gallery'    => $gallery,
                'created_at' => $post->post_date,
                'updated_at' => $post->post_modified,
            ];

            wp_cache_set($cacheKey, $product, self::CACHE_GROUP, self::EXPIRE_IN_GLOBAL);
        }

        return $product;
    }

    /**
     * Get Global Specs
     * 
     * 获取全局规格
     * 
     * @param bool $enabled 是否启用
     * @return array
     */
    public function getGlobalSpecs(bool $enabled = true): array
    {
        $cacheKey = 'global_specs';
        $specs    = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($specs === false) {
            $table = $this->wpdb->prefix . self::SPECS_TABLE;
            $where = $enabled ? 'WHERE is_global = 1 AND status = 1' : 'WHERE is_global = 1';
            $specs = $this->wpdb->get_results(
                "SELECT id, name, `key` FROM {$table} {$where} ORDER BY sort ASC",
                ARRAY_A
            );
            wp_cache_set($cacheKey, $specs, self::CACHE_GROUP, self::EXPIRE_IN_ADMIN);
        }

        return $specs;
    }

    /**
     * Get Product Specs
     * 
     * 获取商品关联的规格，主要用于渲染，含 required
     * 
     * @param int $productId 商品ID
     * @return array 商品规格
     */
    public function getProductSpecs(int $productId): array
    {
        $cacheKey  = Common::getCacheKey($productId, 'spec');
        $relations = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($relations === false) {
            $prodSpecTable = $this->wpdb->prefix . self::PRODUCT_SPECS_TABLE;
            $specTable     = $this->wpdb->prefix . self::SPECS_TABLE;

            $relations = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT ps.spec_id, ps.required, s.name, s.`key`
                 FROM {$prodSpecTable} ps
                 INNER JOIN {$specTable} s ON ps.spec_id = s.id
                 WHERE ps.product_id = %d AND s.status = 1
                 ORDER BY ps.sort ASC",
                    $productId
                ),
                ARRAY_A
            );

            wp_cache_set($cacheKey, $relations, self::CACHE_GROUP, self::EXPIRE_IN_GLOBAL);
        }

        return $relations;
    }


}
