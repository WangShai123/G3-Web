<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use Qcloud\Cos\Client;
use Throwable;

class COSService extends Service {
    public const OPTION_KEY = 'g3_option_qcloud_cos';

    public static function defaultOption(): array
    {
        return [
            'enable'        => '0',
            'bucket'        => '',
            'appId'         => '',
            'region'        => '',
            'secretId'      => '',
            'secretKey'     => '',
            'uploadUrlPath' => '',
            'pathPrefix'    => '',
            'imageStyle'    => '',
            'originProtect' => '0',
            'uploadThumb'   => '1',
            'localSaving'   => '1',
        ];
    }

    public static function regionOptions(): array
    {
        return [
            ''                 => __('Please Select', 'G3'),
            'ap-beijing'       => 'ap-beijing',
            'ap-nanjing'       => 'ap-nanjing',
            'ap-shanghai'      => 'ap-shanghai',
            'ap-guangzhou'     => 'ap-guangzhou',
            'ap-chengdu'       => 'ap-chengdu',
            'ap-chongqing'     => 'ap-chongqing',
            'ap-hongkong'      => 'ap-hongkong',
            'ap-singapore'     => 'ap-singapore',
            'ap-mumbai'        => 'ap-mumbai',
            'ap-seoul'         => 'ap-seoul',
            'ap-bangkok'       => 'ap-bangkok',
            'ap-tokyo'         => 'ap-tokyo',
            'na-siliconvalley' => 'na-siliconvalley',
            'na-ashburn'       => 'na-ashburn',
            'na-toronto'       => 'na-toronto',
            'sa-saopaulo'      => 'sa-saopaulo',
            'eu-frankfurt'     => 'eu-frankfurt',
            'eu-moscow'        => 'eu-moscow',
        ];
    }

    public function option(): array
    {
        $option = get_option(self::OPTION_KEY, self::defaultOption());
        return is_array($option) ? array_merge(self::defaultOption(), $option) : self::defaultOption();
    }

    public function enabled(): bool
    {
        return ($this->option()['enable'] ?? '0') === '1';
    }

    public function configured(): bool
    {
        $option = $this->option();

        return $this->enabled()
            && $this->clean($option['bucket'] ?? '') !== ''
            && $this->clean($option['appId'] ?? '') !== ''
            && $this->clean($option['region'] ?? '') !== ''
            && $this->clean($option['secretId'] ?? '') !== ''
            && $this->clean($option['secretKey'] ?? '') !== ''
            && $this->clean($option['uploadUrlPath'] ?? '') !== '';
    }

    public function getClient(): ?Client
    {
        if (!$this->configured() || !class_exists(Client::class)) {
            return null;
        }

        if (($this->cache['client'] ?? null) instanceof Client) {
            return $this->cache['client'];
        }

        try {
            $option                = $this->option();
            $this->cache['client'] = new Client([
                'region'      => $this->clean($option['region'] ?? ''),
                'schema'      => is_ssl() ? 'https' : 'http',
                'credentials' => [
                    'secretId'  => $this->clean($option['secretId'] ?? ''),
                    'secretKey' => $this->clean($option['secretKey'] ?? ''),
                ],
            ]);
            return $this->cache['client'];
        }
        catch (Throwable $e) {
            $this->logError($e);
            return null;
        }
    }

    public function checkBucket(): bool
    {
        $client = $this->getClient();
        if (!$client instanceof Client) {
            return false;
        }

        try {
            $client->HeadBucket(['Bucket' => $this->bucket()]);
            return true;
        }
        catch (Throwable $e) {
            $this->logError($e);
            return false;
        }
    }

    public function uploadFile(string $object, string $file): bool
    {
        $client = $this->getClient();
        if (!$client instanceof Client || $object === '' || !is_file($file) || !is_readable($file)) {
            return false;
        }

        $body = fopen($file, 'rb');
        if ($body === false) {
            return false;
        }

        try {
            $client->upload($this->bucket(), $object, $body);
            return true;
        }
        catch (Throwable $e) {
            $this->logError($e);
            return false;
        }
        finally {
            fclose($body);
        }
    }

    public function deleteObject(string $object): bool
    {
        $client = $this->getClient();
        if (!$client instanceof Client || $object === '') {
            return false;
        }

        try {
            $client->DeleteObject([
                'Bucket' => $this->bucket(),
                'Key'    => $object,
            ]);
            return true;
        }
        catch (Throwable $e) {
            $this->logError($e);
            return false;
        }
    }

    public function deleteObjects(array $objects): bool
    {
        $objects = array_values(array_filter(array_unique($objects), fn($object) => is_string($object) && $object !== ''));
        if (empty($objects)) {
            return true;
        }

        $client = $this->getClient();
        if (!$client instanceof Client) {
            return false;
        }

        try {
            foreach (array_chunk($objects, 1000) as $chunk) {
                $client->DeleteObjects([
                    'Bucket'  => $this->bucket(),
                    'Objects' => array_map(fn(string $object): array => ['Key' => $object], $chunk),
                    'Quiet'   => true,
                ]);
            }
            return true;
        }
        catch (Throwable $e) {
            $this->logError($e);
            return false;
        }
    }

    public function getFileMeta(string $object): array
    {
        $client = $this->getClient();
        if (!$client instanceof Client || $object === '') {
            return [];
        }

        try {
            $meta = $client->HeadObject([
                'Bucket' => $this->bucket(),
                'Key'    => $object,
            ]);
            return is_object($meta) && method_exists($meta, 'toArray') ? $meta->toArray() : [];
        }
        catch (Throwable $e) {
            $this->logError($e);
            return [];
        }
    }

    public function uploadPathPrefix(): string
    {
        return trim($this->clean($this->option()['pathPrefix'] ?? ''), '/');
    }

    public function uploadUrlPath(): string
    {
        return untrailingslashit(esc_url_raw($this->option()['uploadUrlPath'] ?? ''));
    }

    public function imageStyle(): string
    {
        return $this->clean($this->option()['imageStyle'] ?? '');
    }

    public function shouldUploadThumb(): bool
    {
        return ($this->option()['uploadThumb'] ?? '1') === '1';
    }

    public function shouldKeepLocalFile(): bool
    {
        return ($this->option()['localSaving'] ?? '1') === '1';
    }

    public function shouldProtectOrigin(): bool
    {
        return ($this->option()['originProtect'] ?? '0') === '1';
    }

    public function objectName(string $file): string
    {
        $upload  = wp_get_upload_dir();
        $basedir = isset($upload['basedir']) ? wp_normalize_path((string) $upload['basedir']) : '';
        $file    = wp_normalize_path($file);

        if ($basedir !== '' && str_starts_with($file, trailingslashit($basedir))) {
            $object = ltrim(substr($file, strlen(trailingslashit($basedir))), '/');
        } else {
            $object = basename($file);
        }

        $prefix = $this->uploadPathPrefix();
        return $prefix === '' ? $object : $prefix . '/' . ltrim($object, '/');
    }

    public function remoteUrl(string $localUrl): string
    {
        $upload  = wp_get_upload_dir();
        $baseurl = isset($upload['baseurl']) ? untrailingslashit((string) $upload['baseurl']) : '';
        if ($baseurl === '' || !str_starts_with($localUrl, $baseurl)) {
            return $localUrl;
        }

        $relative = ltrim(substr($localUrl, strlen($baseurl)), '/');
        $prefix   = $this->uploadPathPrefix();
        $object   = $prefix === '' ? $relative : $prefix . '/' . $relative;

        return $this->uploadUrlPath() . '/' . $this->encodeObjectPath($object);
    }

    public function addImageStyleToUrl(string $url): string
    {
        $style = $this->imageStyle();
        if ($style === '' || !$this->isImageUrl($url) || str_contains($url, 'ci-process=') || str_contains($url, 'imageMogr2')) {
            return $url;
        }

        if (str_starts_with($style, '?') || str_starts_with($style, '!')) {
            return $url . $style;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . ltrim($style, '?&');
    }

    public function isImageUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            return false;
        }

        return (bool) preg_match('/\.(?:jpe?g|png|gif|webp|avif|bmp|svg)$/i', $path);
    }

    public function deleteLocalFile(string $file): void
    {
        if ($file !== '' && is_file($file) && str_starts_with(wp_normalize_path($file), wp_normalize_path(wp_get_upload_dir()['basedir'] ?? ''))) {
            wp_delete_file($file);
        }
    }

    private function bucket(): string
    {
        $option = $this->option();
        $bucket = $this->clean($option['bucket'] ?? '');
        $appId  = $this->clean($option['appId'] ?? '');

        return $appId !== '' && !str_ends_with($bucket, '-' . $appId) ? $bucket . '-' . $appId : $bucket;
    }

    private function encodeObjectPath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
    }

    private function clean(mixed $value): string
    {
        return trim(is_scalar($value) ? (string) $value : '');
    }

    private function logError(Throwable $e): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[G3 COS] ' . $e->getMessage());
        }
    }
}
