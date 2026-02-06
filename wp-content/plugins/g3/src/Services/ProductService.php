<?php
namespace JEALER\G3\Services;

use Exception;
use WP_Error;

class ProductService {

    /**
     * Option Key
     * 
     * 配置项键名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    const OPTION_KEY = 'g3_option_shop';

    /**
     * Gallery Key
     * 
     * 商品相册配置项键名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    const GALLERY_KEY = 'g3_product_gallery';

    /**
     * Property Key
     * 
     * 商品属性配置项键名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    const PROP_KEY = 'g3_product_prop';

    /**
     * SKU Table
     */
    const SKU_TABLE = 'g3_sku';
    /**
     * Global Specs Table
     */
    const SPECS_TABLE = 'g3_specs';
    /**
     * Global Specs Options Table
     */
    const SPECS_OPTIONS_TABLE = 'g3_specs_options';
    /**
     * Product Specs Relation Table
     */
    const PRODUCT_SPECS_TABLE = 'g3_product_specs';
    /**
     * Sku Specs Relation Table
     */
    const SPECS_RELATIONS_TABLE = 'g3_sku_specs_relations';

    /**
     * Product Cache Group
     */
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

    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get Cache Key
     * 
     * 获取缓存键名
     * 
     * @param string $key 缓存键名
     * @param string $suffix 缓存键名后缀
     * @return string 缓存键名
     * @since 1.0.0
     * @author Wang Shai
     */
    private function getCacheKey($postId, $suffix = ''): string
    {
        return "product_{$postId}" . ($suffix ? "_{$suffix}" : '');
    }

    public static function renderScope(int $id)
    {
        return match ($id) {
            0 => __('All'),
            1 => __('Product', 'G3'),
            2 => __('Category'),
            3 => __('Tag'),
            4 => __('Brand'),
            default => 'All'
        };
    }

    /**
     * Update Product Spec
     * 
     * @param array $data
     * @return int|bool
     */
    public function updateSpec(array $data): int|bool
    {
        if (!isset($data['name']) || !isset($data['key']) || empty($data['name']) || empty($data['key'])) {
            return false;
        }
        $cacheKey = 'specs_names';
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
    public function getSpecs(): array
    {
        $cacheKey = 'specs_names';
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
    public function deleteSpec(int $id): bool|int
    {
        return $this->wpdb->delete($this->wpdb->prefix . ProductService::SPECS_TABLE, ['id' => $id]);
    }
    public function updateSpecOption(array $data): bool|int
    {
        $cacheKey = "spec_options_{$data['spec_id']}";
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
                new WP_Error('db_error', $this->wpdb->last_error);
                return -1;
            }
        }
        return false;
    }

    /**
     * Render SKU TYPE
     * 
     * 渲染SKU类型 1: general, 2: digital, 3: membership, 4: download
     * 
     * @param int $typeId
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public function renderSkuType(int $typeId): string
    {
        return match ($typeId) {
            1 => __('General Product', 'G3'),
            2 => __('Digital Product', 'G3'),
            3 => __('Membership Product', 'G3'),
            4 => __('Download Product', 'G3'),
            default => 'Unknown'
        };
    }

    public function generateSkuCode(int $productId, int $typeId): string
    {
        /**
         * SKU CODE RULE:
         * @todo
         * 临时策略:
         *  - prefix: G
         *  - typeId: 00, 如果不足2位，则前面补0 
         *  - productId: 000000，如果不足6位，则前面补0
         *  - dash: 用 - 连接
         */
        return 'G-' . str_pad($typeId, 2, '0', STR_PAD_LEFT) . '-' . str_pad($productId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Update SKU
     * 
     * 更新SKU
     * 
     * @param array $data
     * @return int|bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public function updateSku(array $data)
    {
        if (!isset($data['id']) || !isset($data['product_id'])) {
            return false;
        }

        $table = $this->wpdb->prefix . self::SKU_TABLE;
        $skuId = $data['id'];
        unset($data['id']);

        $result = $this->wpdb->update($table, $data, ['id' => $skuId]);

        if ($result !== false) {
            // 清除缓存
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
     * @since 1.0.0
     * @author Wang Shai
     */
    public function createSku(array $data)
    {
        if (!isset($data['product_id'])) {
            return false;
        }
        if (!isset($data['sku_code']) || !$data['sku_code']) {
            $data['sku_code'] = $this->generateSkuCode($data['product_id'], $data['type']);
        }

        $table = $this->wpdb->prefix . self::SKU_TABLE;

        $result = $this->wpdb->insert($table, $data);

        if ($result !== false) {
            $skuId = $this->wpdb->insert_id;
            // 清除缓存
            $this->clearProductCache($data['product_id']);
            return $skuId;
        }

        return false;
    }

    /**
     * Delete SKU Spec Relations
     * 
     * 删除SKU规格关系
     * 
     * @param int $skuId
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public function deleteSkuSpecRelations(int $skuId): bool
    {
        $table = $this->wpdb->prefix . self::SPECS_RELATIONS_TABLE;
        return $this->wpdb->delete($table, ['sku_id' => $skuId]) !== false;
    }

    /**
     * Add SKU Spec Relation
     * 
     * 添加SKU规格关系
     * 
     * @param int $skuId
     * @param int $specId
     * @param int $specOptionId
     * @return 
     * @since 1.0.0
     * @author Wang Shai
     */
    public function addSkuSpecRelation(int $skuId, int $specId, int $specOptionId)
    {
        $table = $this->wpdb->prefix . self::SPECS_RELATIONS_TABLE;

        $data = [
            'sku_id'         => $skuId,
            'spec_option_id' => $specOptionId,
        ];

        // $result = $this->wpdb->insert($table, $data);
        // return $result;
        return $this->wpdb->insert($table, $data) !== false;
    }








    /**
     * Get Product Base Data with Gallery
     * 
     * 获取商品基础数据+图册
     * 
     * @param int $postId
     * @return array|null
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getProduct(int $postId): array|null
    {
        $cacheKey = $this->getCacheKey($postId);
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
     * Get Product All SKU, with specs options
     * 
     * 获取商品所有 SKU（含规格选项 ID 列表）
     * 
     * @param int $productId
     * @return array
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getSkusByProductId(int $productId)
    {
        $cacheKey = $this->getCacheKey($productId, 'skus');
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

            // 补充每个 SKU 的 spec_option_ids（用于前端渲染规格组合）
            foreach ($rows as &$row) {
                // $row['spec_option_ids'] = $this->getSkuSpecOptionIds($row['id']);
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
     * @since 1.0.0
     * @author Wang Shai
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
     * Get Spec Option IDs By Sku ID
     * 
     * 获取 SKU 关联的规格选项 ID 列表
     * 
     * @param int $skuId
     * @return array
     * @since 1.0.0
     * @author Wang Shai
     */
    private function getSkuSpecOptionIds(int $skuId): array
    {
        $relTable = $this->wpdb->prefix . self::SPECS_RELATIONS_TABLE;
        return $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT spec_option_id FROM {$relTable} WHERE sku_id = %d",
                $skuId
            )
        );
    }

    /**
     * Get Global Specs
     * 
     * 获取全局规格
     * 
     * @param bool $enabled 是否启用
     * @return array
     * @since 1.0.0
     * @author Wang Shai
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
     * Get Spec Options By Spec Id
     * 
     * 根据规格ID获取规格选项
     * 
     * @param int $specId 规格ID
     * @param bool $enabled 是否启用
     * @return array
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getSpecOptionsBySpecId(int $specId, bool $enabled = true): array
    {
        $cacheKey = "spec_options_{$specId}";
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
     * Get Product Specs
     * 
     * 获取商品关联的规格，主要用于渲染，含 required
     * 
     * @param int $productId 商品ID
     * @return array 商品规格
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getProductSpecs(int $productId): array
    {
        $cacheKey  = $this->getCacheKey($productId, 'specs');
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

    /**
     * Clear Product Cache
     *
     * 清除商品缓存
     *
     * @param int $postId
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function clearProductCache(int $postId): void
    {
        wp_cache_delete($this->getCacheKey($postId), self::CACHE_GROUP);
        wp_cache_delete($this->getCacheKey($postId, 'skus'), self::CACHE_GROUP);
        wp_cache_delete($this->getCacheKey($postId, 'specs'), self::CACHE_GROUP);

        // 如果影响了全局规格缓存（如删除了最后一个使用某规格的商品？一般不需要）
        // 可选择不清除 global_specs 缓存，因其由规格管理页控制
    }

}