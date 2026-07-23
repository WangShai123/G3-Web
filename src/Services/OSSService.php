<?php
namespace JEALER\G3\Services;
use AlibabaCloud\Credentials\Credential;
use JEALER\G3\Core\Service\Service;
use OSS\Core\OssException;
use OSS\Credentials\StaticCredentialsProvider;
use OSS\OssClient;
use Throwable;

class OSSService extends Service {
    public const OPTION_KEY = 'g3_option_aliyun_oss';

    public static function defaultOption(): array
    {
        return [
            'enable'          => '0',
            'bucket'          => '',
            'region'          => '',
            'roleName'        => '',
            'accessKeyId'     => '',
            'accessKeySecret' => '',
            'internal'        => '0',
            'uploadUrlPath'   => '',
            'pathPrefix'      => '',
            'imageStyle'      => '',
            'originProtect'   => '0',
            'uploadThumb'     => '1',
            'localSaving'     => '1',
        ];
    }

    public static function regionOptions(): array
    {
        return [
            ''               => __('Please Select', 'G3'),
            'cn-hangzhou'    => 'cn-hangzhou',
            'cn-shanghai'    => 'cn-shanghai',
            'cn-qingdao'     => 'cn-qingdao',
            'cn-beijing'     => 'cn-beijing',
            'cn-zhangjiakou' => 'cn-zhangjiakou',
            'cn-huhehaote'   => 'cn-huhehaote',
            'cn-wulanchabu'  => 'cn-wulanchabu',
            'cn-shenzhen'    => 'cn-shenzhen',
            'cn-heyuan'      => 'cn-heyuan',
            'cn-guangzhou'   => 'cn-guangzhou',
            'cn-chengdu'     => 'cn-chengdu',
            'cn-hongkong'    => 'cn-hongkong',
            'us-west-1'      => 'us-west-1',
            'us-east-1'      => 'us-east-1',
            'ap-southeast-1' => 'ap-southeast-1',
            'ap-southeast-2' => 'ap-southeast-2',
            'ap-southeast-3' => 'ap-southeast-3',
            'ap-southeast-5' => 'ap-southeast-5',
            'ap-northeast-1' => 'ap-northeast-1',
            'eu-central-1'   => 'eu-central-1',
            'eu-west-1'      => 'eu-west-1',
            'me-east-1'      => 'me-east-1',
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
        $option     = $this->option();
        $hasKeyPair = $this->clean($option['accessKeyId'] ?? '') !== '' && $this->clean($option['accessKeySecret'] ?? '') !== '';
        $hasRole    = $this->clean($option['roleName'] ?? '') !== '';

        return $this->enabled()
            && $this->clean($option['bucket'] ?? '') !== ''
            && $this->clean($option['region'] ?? '') !== ''
            && $this->clean($option['uploadUrlPath'] ?? '') !== ''
            && ($hasKeyPair || $hasRole);
    }

    public function getClient(): ?OssClient
    {
        if (!$this->configured() || !class_exists(OssClient::class)) {
            return null;
        }

        if (($this->cache['client'] ?? null) instanceof OssClient) {
            return $this->cache['client'];
        }

        try {
            $option                = $this->option();
            $this->cache['client'] = new OssClient([
                'endpoint'         => $this->endpoint(),
                'provider'         => $this->credentialsProvider($option),
                'region'           => $this->clean($option['region'] ?? ''),
                'signatureVersion' => OssClient::OSS_SIGNATURE_VERSION_V4,
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
        if (!$client instanceof OssClient) {
            return false;
        }

        try {
            return (bool) $client->doesBucketExist($this->bucket());
        }
        catch (Throwable $e) {
            $this->logError($e);
            return false;
        }
    }

    public function uploadFile(string $object, string $file): bool
    {
        $client = $this->getClient();
        if (!$client instanceof OssClient || $object === '' || !is_file($file) || !is_readable($file)) {
            return false;
        }

        try {
            $client->uploadFile($this->bucket(), $object, $file);
            return true;
        }
        catch (Throwable $e) {
            $this->logError($e);
            return false;
        }
    }

    public function deleteObject(string $object): bool
    {
        $client = $this->getClient();
        if (!$client instanceof OssClient || $object === '') {
            return false;
        }

        try {
            $client->deleteObject($this->bucket(), $object);
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
        if (!$client instanceof OssClient) {
            return false;
        }

        try {
            foreach (array_chunk($objects, 1000) as $chunk) {
                $client->deleteObjects($this->bucket(), $chunk);
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
        if (!$client instanceof OssClient || $object === '') {
            return [];
        }

        try {
            $meta = $client->getObjectMeta($this->bucket(), $object);
            return is_array($meta) ? $meta : [];
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
        if ($style === '' || !$this->isImageUrl($url) || str_contains($url, 'x-oss-process=')) {
            return $url;
        }

        if (str_starts_with($style, '?') || str_starts_with($style, '!')) {
            return $url . $style;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'x-oss-process=' . ltrim($style, '?&');
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
        return $this->clean($this->option()['bucket'] ?? '');
    }

    private function endpoint(): string
    {
        $option = $this->option();
        $region = $this->clean($option['region'] ?? '');
        if ($region === '') {
            return '';
        }

        $internal = ($option['internal'] ?? '0') === '1' ? '-internal' : '';
        return 'https://oss-' . $region . $internal . '.aliyuncs.com';
    }

    private function credentialsProvider(array $option): StaticCredentialsProvider
    {
        $roleName = $this->clean($option['roleName'] ?? '');
        if ($roleName !== '' && class_exists(Credential::class)) {
            $credential = (new Credential([
                'type'      => 'ecs_ram_role',
                'role_name' => $roleName,
            ]))->getCredential();

            return new StaticCredentialsProvider(
                (string) $credential->getAccessKeyId(),
                (string) $credential->getAccessKeySecret(),
                (string) $credential->getSecurityToken()
            );
        }

        return new StaticCredentialsProvider(
            $this->clean($option['accessKeyId'] ?? ''),
            $this->clean($option['accessKeySecret'] ?? '')
        );
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
            error_log('[G3 OSS] ' . $e->getMessage());
        }
    }
}
