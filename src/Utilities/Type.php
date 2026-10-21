<?php
namespace JEALER\G3\Utilities;
use InvalidArgumentException;

final class Type {

    /**
     * Convert array to JSON string
     *
     * 将数组转换为 JSON 字符串，不转义 Unicode & 斜杠
     *
     * @param array $array
     * @return string
     */
    public static function arrayToJson(array $array): string
    {
        $result = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $result === false ? '{}' : $result;
    }

    /**
     * Convert JSON string to array
     *
     * 将 JSON 字符串转换为数组
     *
     * @param string $json
     * @return array
     */
    public static function jsonToArray(string $json): array
    {
        if ($json === '') return [];

        $result = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $result;
    }

    public static function jsonToObject(string $json): object
    {
        $result = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        if (!is_object($result)) {
            throw new InvalidArgumentException('Invalid JSON input: expected a JSON object string.');
        }

        return $result;
    }

    /**
     * Exclude array keys from unset
     * 
     * 从数组中移除指定的键值对
     * 
     * @param  array $array
     * @param  array $unset
     * @return array
     */
    public static function arrayExcept(array $array, ?array $unset = null): array
    {
        return match (true) {
            $unset === null || $unset === [] => $array,
            default                          => array_diff_key($array, array_flip($unset))
        };
    }

    public static function arrayOption(mixed $option): array
    {
        return is_array($option) ? $option : [];
    }
}
