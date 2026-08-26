<?php
namespace JEALER\G3\Utilities;

final class Cache {

    /**
     * Get Cache Key
     *
     * 获取缓存键名
     *
     * @param string $id
     * @param string $subFolder
     * @param string $prefix
     * @return string
     */
    public static function key($id, $subFolder = '', $prefix = ''): string
    {
        return ($subFolder ? "{$subFolder}:" : '') . ($prefix ? "{$prefix}_" : '') . "{$id}";
    }
}