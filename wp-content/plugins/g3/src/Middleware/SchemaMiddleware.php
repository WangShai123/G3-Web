<?php
namespace JEALER\G3\Middleware;

use WP_REST_Request;
use WP_Error;

/**
 * Schema Middleware - JSON Schema Validator
 *
 * 用于 REST API 的请求体结构校验
 * 支持 JSON Schema 的简化版本：
 * - type
 * - required
 * - properties
 * - enum
 * - minLength / maxLength
 * - minimum / maximum
 * - items
 */
class SchemaMiddleware implements MiddlewareInterface {
    private array $schema;

    public function __construct(array $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Handle the middleware logic
     * 
     * 验证请求体是否符合指定的 JSON Schema
     * 
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function handle(WP_REST_Request $request): bool|WP_Error
    {
        $data = $request->get_json_params();

        // No content is also considered invalid
        if (!$data) {
            return new WP_Error(
                '400',
                __('Request body should be valid JSON.', 'G3'),
                [
                    'status' => 400
                ]
            );
        }

        $result = $this->validate($data, $this->schema);

        if ($result !== true) {
            return new WP_Error(
                '422',
                __('Schema validation failed', 'G3'),
                [
                    'status' => 422,
                    'errors' => $result
                ]
            );
        }
        return true;
    }

    /**
     * Simple JSON Schema Validator
     * 
     * JSON 数据简单校验，不属于任何 JSON Schema draft 版本。
     * 如果后续需要，再重新实现符合 Draft 7 的 JSON Schema 校验。
     * 
     * @param array $data
     * @param array $schema
     * @return array|true
     */
    private function validate(array $data, array $schema): array|bool
    {
        $errors = [];

        // required
        if (!empty($schema['required'])) {
            foreach ($schema['required'] as $key) {
                if (!\array_key_exists($key, $data)) {
                    $errors[] = "Field '$key' is required.";
                }
            }
        }

        // properties
        if (!empty($schema['properties'])) {
            foreach ($schema['properties'] as $key => $rule) {
                if (!\array_key_exists($key, $data)) continue;

                $value = $data[$key];

                // --- type ---
                if (!empty($rule['type'])) {
                    $type  = $rule['type'];
                    $valid = match ($type) {
                        'string' => \is_string($value),
                        'number' => \is_numeric($value),
                        'integer' => \is_int($value),
                        'boolean' => \is_bool($value),
                        'array' => \is_array($value),
                        'object' => \is_array($value),  // JSON object → PHP array
                        default => true
                    };

                    if (!$valid) {
                        // $errors[] = "Field '$key' must be of type '$type'.";
                        $errors[] = sprintf(__('Field %s must be of type %s.', 'G3'), $key, $type);
                        continue;
                    }
                }

                // --- enum ---
                if (!empty($rule['enum'])) {
                    if (!\in_array($value, $rule['enum'], true)) {
                        $allowed = implode(', ', $rule['enum']);
                        // $errors[] = "Field '$key' must be one of: $allowed.";
                        $errors[] = sprintf(__('Field %s must be one of: %s.', 'G3'), $key, $allowed);
                    }
                }

                // --- string length ---
                if (\is_string($value)) {
                    if (isset($rule['minLength']) && \strlen($value) < $rule['minLength']) {
                        // $errors[] = "Field '$key' must be at least {$rule['minLength']} characters.";
                        $errors[] = sprintf(__('Field %s must be at least %s characters.', 'G3'), $key, $rule['minLength']);
                    }
                    if (isset($rule['maxLength']) && \strlen($value) > $rule['maxLength']) {
                        // $errors[] = "Field '$key' must be no more than {$rule['maxLength']} characters.";
                        $errors[] = sprintf(__('Field %s must be no more than %s characters.', 'G3'), $key, $rule['maxLength']);
                    }
                }

                // --- number range ---
                if (\is_numeric($value)) {
                    if (isset($rule['minimum']) && $value < $rule['minimum']) {
                        // $errors[] = "Field '$key' must be >= {$rule['minimum']}.";
                        $errors[] = sprintf(__('Field %s must be >= %s.', 'G3'), $key, $rule['minimum']);
                    }
                    if (isset($rule['maximum']) && $value > $rule['maximum']) {
                        // $errors[] = "Field '$key' must be <= {$rule['maximum']}.";
                        $errors[] = sprintf(__('Field %s must be <= %s.', 'G3'), $key, $rule['maximum']);
                    }
                }

                // --- array items ---
                if (isset($rule['type']) && $rule['type'] === 'array' && !empty($rule['items'])) {
                    foreach ($value as $idx => $item) {
                        $itemRule = $rule['items'];

                        if (!empty($itemRule['type'])) {
                            $ok = match ($itemRule['type']) {
                                'string' => \is_string($item),
                                'number' => \is_numeric($item),
                                'integer' => \is_int($item),
                                'boolean' => \is_bool($item),
                                'array' => \is_array($item),
                                'object' => \is_array($item),
                                default => true
                            };
                            if (!$ok) {
                                // $errors[] = "Array '$key'[$idx] must be type {$itemRule['type']}.";
                                $errors[] = sprintf(__('Array %s[%s] must be type %s.', 'G3'), $key, $idx, $itemRule['type']);
                            }
                        }
                    }
                }
            }
        }

        return empty($errors) ? true : $errors;
    }
}