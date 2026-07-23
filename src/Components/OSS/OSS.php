<?php
namespace JEALER\G3\Components;
use JEALER\G3\Services\COSService;
use JEALER\G3\Services\OSSService;
use Override;

class OSS extends Components {
    private OSSService $ossService;
    private COSService $cosService;

    /** @var array<string, bool> */
    private array $uploadedObjects = [];

    #[Override]
    protected function start(): void
    {
        $this->ossService = $this->getService(OSSService::class);
        $this->cosService = $this->getService(COSService::class);
    }

    #[Override]
    protected function hooks(): void
    {
        $this->filter([
            'wp_handle_upload'                => [[$this, 'uploadHandle'], 50, 1],
            'wp_generate_attachment_metadata' => [[$this, 'uploadAttachmentMetadata'], 100, 2],
            'wp_update_attachment_metadata'   => [[$this, 'uploadAttachmentMetadata'], 100, 2],
            'wp_get_attachment_url'           => [[$this, 'attachmentUrl'], 30, 2],
            'wp_calculate_image_srcset'       => [[$this, 'imageSrcset'], 10, 5],
            'the_content'                     => [[$this, 'contentImageStyle'], 20, 1],
            'post_thumbnail_html'             => [[$this, 'contentImageStyle'], 20, 1],
        ]);

        $this->action([
            'admin_notices'     => [[$this, 'storageNotice'], 10, 0],
            'delete_attachment' => [[$this, 'deleteRemoteAttachment'], 10, 1],
        ]);
    }

    #[Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            'OSS',
            'OSS',
            'manage_options',
            'oss',
            [$this, 'render'],
            14
        );
    }

    #[Override]
    protected function adminPanelPage(): string
    {
        return 'oss';
    }

    #[Override]
    protected function adminPanels(): array
    {
        return [
            $this->panel('oss', 'OSS')
                ->tab('aliyun', __('Aliyun OSS', 'G3'))
                ->option(OSSService::OPTION_KEY, OSSService::defaultOption())
                ->switch('enable', __('Enable', 'G3'), __('Only one cloud storage provider can be enabled at the same time.', 'G3'))
                ->input('bucket', 'Bucket')
                ->select('region', __('Region', 'G3'), OSSService::regionOptions())
                ->input('roleName', 'ECS RAM Role', __('Optional. When configured, ECS RAM Role credentials are used first.', 'G3'))
                ->rowClass('advanced')
                ->input('accessKeyId', 'AccessKey ID')
                ->password('accessKeySecret', 'AccessKey Secret')
                ->switch('internal', __('Internal Endpoint', 'G3'), __('Use the internal OSS endpoint when the site runs on Alibaba Cloud ECS in the same region.', 'G3'))
                ->rowClass('advanced')
                ->input('uploadUrlPath', __('Upload URL', 'G3'), __('Public bucket domain or CDN domain, for example <code>https://static.example.com</code>.', 'G3'))
                ->input('pathPrefix', __('Path Prefix', 'G3'), __('Optional object key prefix. Do not start or end with a slash.', 'G3'))
                ->rowClass('advanced')
                ->input('imageStyle', __('Image Style', 'G3'), __('Optional OSS image process style, for example <code>style/thumb</code> or <code>image/resize,w_1200</code>.', 'G3'))
                ->rowClass('advanced')
                ->switch('originProtect', __('Origin Protect', 'G3'), __('Apply the configured image style to original image URLs.', 'G3'))
                ->rowClass('advanced')
                ->switch('uploadThumb', __('Upload Thumbnails', 'G3'), __('Upload generated image sizes together with the original image.', 'G3'))
                ->switch('localSaving', __('Local Saving', 'G3'), __('Keep local upload files after they are uploaded to cloud storage.', 'G3'))
                ->tab('qcloud', __('Qcloud COS', 'G3'))
                ->option(COSService::OPTION_KEY, COSService::defaultOption())
                ->switch('enable', __('Enable', 'G3'), __('Only one cloud storage provider can be enabled at the same time.', 'G3'))
                ->input('bucket', 'Bucket', __('Do not include APPID unless the bucket name already contains it.', 'G3'))
                ->input('appId', 'APPID')
                ->select('region', __('Region', 'G3'), COSService::regionOptions())
                ->input('secretId', 'SecretId')
                ->password('secretKey', 'SecretKey')
                ->input('uploadUrlPath', __('Upload URL', 'G3'), __('Public bucket domain or CDN domain, for example <code>https://static.example.com</code>.', 'G3'))
                ->input('pathPrefix', __('Path Prefix', 'G3'), __('Optional object key prefix. Do not start or end with a slash.', 'G3'))
                ->rowClass('advanced')
                ->input('imageStyle', __('Image Style', 'G3'), __('Optional COS image process style, for example <code>imageMogr2/thumbnail/1200x</code>.', 'G3'))
                ->rowClass('advanced')
                ->switch('originProtect', __('Origin Protect', 'G3'), __('Apply the configured image style to original image URLs.', 'G3'))
                ->rowClass('advanced')
                ->switch('uploadThumb', __('Upload Thumbnails', 'G3'), __('Upload generated image sizes together with the original image.', 'G3'))
                ->switch('localSaving', __('Local Saving', 'G3'), __('Keep local upload files after they are uploaded to cloud storage.', 'G3')),
        ];
    }

    public function render(): void
    {
        $this->createPanel('aliyun');
    }

    public function storageNotice(): void
    {
        $message = $this->storageNoticeMessage();
        if ($message === '') {
            return;
        }

        if (function_exists('wp_admin_notice')) {
            wp_admin_notice($message, [
                'type'        => 'warning',
                'dismissible' => false,
            ]);
            return;
        }

        echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
    }

    public function uploadHandle(array $upload): array
    {
        $service = $this->activeStorageService();
        if (!$this->shouldHandleUpload($service) || isset($upload['error']) || empty($upload['file'])) {
            return $upload;
        }

        if (str_starts_with((string) ($upload['type'] ?? ''), 'image/')) {
            return $upload;
        }

        $file   = (string) $upload['file'];
        $object = $service->objectName($file);
        if ($this->uploadFileOnce($service, $object, $file) && !$service->shouldKeepLocalFile()) {
            $service->deleteLocalFile($file);
        }

        return $upload;
    }

    public function uploadAttachmentMetadata(mixed $metadata, int $attachmentId): mixed
    {
        $service = $this->activeStorageService();
        if (!$this->shouldHandleUpload($service) || !is_array($metadata)) {
            return $metadata;
        }

        $files = $this->attachmentFiles($attachmentId, $metadata, $service->shouldUploadThumb());
        if (empty($files)) {
            return $metadata;
        }

        $uploaded = [];
        $success  = true;
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $object = $service->objectName($file);
            if (!$this->uploadFileOnce($service, $object, $file)) {
                $success = false;
                continue;
            }

            $uploaded[] = $file;
        }

        if ($success && !$service->shouldKeepLocalFile()) {
            foreach ($uploaded as $file) {
                $service->deleteLocalFile($file);
            }
        }

        return $metadata;
    }

    public function attachmentUrl(string $url, int $attachmentId): string
    {
        $service = $this->activeStorageService();
        if (!$service instanceof OSSService && !$service instanceof COSService) {
            return $url;
        }

        $remoteUrl = $service->remoteUrl($url);
        if ($remoteUrl !== $url && $service->shouldProtectOrigin() && wp_attachment_is_image($attachmentId)) {
            return $service->addImageStyleToUrl($remoteUrl);
        }

        return $remoteUrl;
    }

    public function imageSrcset(array|false $sources, array $sizeArray, string $imageSrc, array $imageMeta, int $attachmentId): array|false
    {
        $service = $this->activeStorageService();
        if ((!$service instanceof OSSService && !$service instanceof COSService) || !is_array($sources)) {
            return $sources;
        }

        foreach ($sources as $width => $source) {
            if (!isset($source['url']) || !is_string($source['url'])) {
                continue;
            }

            $remoteUrl              = $service->remoteUrl($source['url']);
            $sources[$width]['url'] = $service->addImageStyleToUrl($remoteUrl);
        }

        return $sources;
    }

    public function contentImageStyle(string $html): string
    {
        $service = $this->activeStorageService();
        if ((!$service instanceof OSSService && !$service instanceof COSService) || $html === '') {
            return $html;
        }

        if (!class_exists('WP_HTML_Tag_Processor')) {
            return $html;
        }

        $processor = new \WP_HTML_Tag_Processor($html);
        while ($processor->next_tag('img')) {
            $src = $processor->get_attribute('src');
            if (is_string($src)) {
                $processor->set_attribute('src', $service->addImageStyleToUrl($service->remoteUrl($src)));
            }

            $srcset = $processor->get_attribute('srcset');
            if (is_string($srcset)) {
                $processor->set_attribute('srcset', $this->rewriteSrcset($srcset, $service));
            }
        }

        return $processor->get_updated_html();
    }

    public function deleteRemoteAttachment(int $attachmentId): void
    {
        $service = $this->activeStorageService();
        if (!$service instanceof OSSService && !$service instanceof COSService) {
            return;
        }

        $metadata = wp_get_attachment_metadata($attachmentId);
        $metadata = is_array($metadata) ? $metadata : [];
        $objects  = array_map(
            fn(string $file): string => $service->objectName($file),
            $this->attachmentFiles($attachmentId, $metadata, true)
        );

        $service->deleteObjects($objects);
    }

    #[Override]
    protected function defaultOption(): array
    {
        return [
            OSSService::OPTION_KEY => OSSService::defaultOption(),
            COSService::OPTION_KEY => COSService::defaultOption(),
        ];
    }

    private function activeStorageService(): OSSService|COSService|null
    {
        $services = $this->enabledStorageServices();
        if (count($services) !== 1) {
            return null;
        }

        $service = $services[0];
        return $service->configured() ? $service : null;
    }

    private function storageNoticeMessage(): string
    {
        $services = $this->enabledStorageServices();
        if (count($services) > 1) {
            return __('同一时间只能启用一个云存储', 'G3');
        }

        if (count($services) === 1 && !$services[0]->configured()) {
            return __('当前启用的云存储配置不完整', 'G3');
        }

        return '';
    }

    /**
     * @return array<int, OSSService|COSService>
     */
    private function enabledStorageServices(): array
    {
        $services = [];
        if ($this->ossService->enabled()) {
            $services[] = $this->ossService;
        }
        if ($this->cosService->enabled()) {
            $services[] = $this->cosService;
        }

        return $services;
    }

    private function shouldHandleUpload(OSSService|COSService|null $service): bool
    {
        if (!$service instanceof OSSService && !$service instanceof COSService) {
            return false;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        return !is_string($requestUri) || !str_contains($requestUri, '/update.php');
    }

    private function uploadFileOnce(OSSService|COSService $service, string $object, string $file): bool
    {
        $key = get_class($service) . ':' . $object;
        if (isset($this->uploadedObjects[$key])) {
            return true;
        }

        $uploaded = $service->uploadFile($object, $file);
        if ($uploaded) {
            $this->uploadedObjects[$key] = true;
        }

        return $uploaded;
    }

    /**
     * @return array<int, string>
     */
    private function attachmentFiles(int $attachmentId, array $metadata, bool $includeSizes): array
    {
        $files    = [];
        $attached = get_attached_file($attachmentId, true);
        if (is_string($attached) && $attached !== '') {
            $files[] = $attached;
        } elseif (isset($metadata['file']) && is_string($metadata['file'])) {
            $files[] = trailingslashit(wp_get_upload_dir()['basedir'] ?? '') . ltrim($metadata['file'], '/');
        }

        $baseDir = !empty($files) ? dirname($files[0]) : '';

        if (isset($metadata['original_image']) && is_string($metadata['original_image']) && $baseDir !== '') {
            $files[] = $baseDir . '/' . $metadata['original_image'];
        }

        if ($includeSizes && isset($metadata['sizes']) && is_array($metadata['sizes']) && $baseDir !== '') {
            foreach ($metadata['sizes'] as $size) {
                if (is_array($size) && isset($size['file']) && is_string($size['file'])) {
                    $files[] = $baseDir . '/' . $size['file'];
                }
            }
        }

        return array_values(array_unique(array_filter($files, fn($file) => is_string($file) && $file !== '')));
    }

    private function rewriteSrcset(string $srcset, OSSService|COSService $service): string
    {
        $candidates = array_filter(array_map('trim', explode(',', $srcset)));
        $rewritten  = [];

        foreach ($candidates as $candidate) {
            $parts = preg_split('/\s+/', $candidate, 2);
            if (!is_array($parts) || empty($parts[0])) {
                continue;
            }

            $url         = $service->addImageStyleToUrl($service->remoteUrl($parts[0]));
            $rewritten[] = isset($parts[1]) ? $url . ' ' . $parts[1] : $url;
        }

        return implode(', ', $rewritten);
    }
}
