<?php
namespace JEALER\G3\Services;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\System;
use WP_User_Query;

class SitemapService {
    private const PER_PAGE = 4000;
    private string $siteUrl;
    private string $sitemapDir;
    private string $sitemapUrl;
    public function __construct()
    {
        $this->siteUrl    = rtrim(home_url('/'), '/');
        $this->sitemapDir = rtrim(ABSPATH, '/\\') . DIRECTORY_SEPARATOR . 'sitemap';
        $this->sitemapUrl = $this->siteUrl . '/sitemap';
    }
    public function handleRequest(): void
    {
        if (!$this->enabled() || get_query_var('g3_var_sitemap') !== 'endpoint') {
            wp_redirect(home_url('404'));
            exit;
        }

        $page = $this->requestPage();
        $xml  = $this->generateIndex($page);

        $this->writeStaticFiles();

        header('Content-Type: application/xml; charset=utf-8');
        echo $xml;
        exit;
    }
    private function enabled(): bool
    {
        $option = Option::get(SystemService::SECURITY_OPTION_KEY, [
            'sitemap' => '1',
        ]);
        $v      = $option['sitemap'] ?? '1';
        return (string) $v === '1';
    }
    private function requestPage(): int
    {
        $value = get_query_var('g3_var_sitemap_page');
        if (is_numeric($value)) {
            return max(1, (int) $value);
        }
        return 1;
    }
    public function writeStaticFiles(): void
    {
        if (!$this->ensureDirectory($this->sitemapDir)) {
            return;
        }

        System::writeFile($this->sitemapDir . DIRECTORY_SEPARATOR . 'index.html', $this->generateHtmlIndex());
        $homePages = $this->homePages();
        for ($page = 1; $page <= $homePages; $page++) {
            System::writeFile(
                $this->sitemapDir . DIRECTORY_SEPARATOR . sprintf('home-%d.xml', $page),
                $this->generateHomeUrlSet($page)
            );
        }

        foreach ($this->publicPostTypes() as $postType) {
            $pages = $this->postTypePages($postType);
            for ($page = 1; $page <= $pages; $page++) {
                $filename = sprintf('posts-%s-%d.xml', $postType, $page);
                System::writeFile(
                    $this->sitemapDir . DIRECTORY_SEPARATOR . $filename,
                    $this->generatePostTypeUrlSet($postType, $page)
                );
            }
        }

        foreach ($this->publicTaxonomies() as $taxonomy) {
            $pages = $this->taxonomyPages($taxonomy);
            for ($page = 1; $page <= $pages; $page++) {
                $filename = sprintf('taxonomies-%s-%d.xml', $taxonomy, $page);
                System::writeFile(
                    $this->sitemapDir . DIRECTORY_SEPARATOR . $filename,
                    $this->generateTaxonomyUrlSet($taxonomy, $page)
                );
            }
        }

        $userPages = $this->userPages();
        for ($page = 1; $page <= $userPages; $page++) {
            System::writeFile(
                $this->sitemapDir . DIRECTORY_SEPARATOR . sprintf('users-%d.xml', $page),
                $this->generateUsersUrlSet($page)
            );
        }

        $userDir = $this->sitemapDir . DIRECTORY_SEPARATOR . 'user';
        if ($this->ensureDirectory($userDir)) {
            foreach ($this->publicUsers() as $user) {
                System::writeFile(
                    $userDir . DIRECTORY_SEPARATOR . $this->usernameHash($user->user_nicename ?: $user->user_login) . '.xml',
                    $this->generateUrlSet([
                        [
                            'loc'     => $this->userPageUrl($user),
                            'lastmod' => $this->userLastmod($user),
                        ],
                    ])
                );
            }
        }
    }
    private function generateIndex(int $page = 1): string
    {
        return $this->xmlDocument('sitemapindex', $this->indexEntries($page), [
            'local file url: ' . $this->sitemapUrl . '/index.html',
        ]);
    }
    private function generateHtmlIndex(): string
    {
        $siteName    = $this->siteName();
        $description = get_bloginfo('description');
        $title       = 'Sitemap - ' . $siteName;
        $keywords    = $siteName . ', sitemap';

        $html  = '<!doctype html>' . "\n";
        $html .= '<html lang="en">' . "\n";
        $html .= '<head>' . "\n";
        $html .= '  <meta charset="utf-8">' . "\n";
        $html .= '  <title>' . esc_html($title) . '</title>' . "\n";
        $html .= '  <meta name="description" content="' . esc_attr($description) . '">' . "\n";
        $html .= '  <meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
        $html .= '</head>' . "\n";
        $html .= '<body>' . "\n";
        $html .= '  <ul>' . "\n";

        foreach ($this->allIndexEntries() as $entry) {
            if (empty($entry['loc'])) {
                continue;
            }

            $url   = esc_url($entry['loc']);
            $html .= '    <li><a href="' . $url . '">' . esc_html($entry['loc']) . '</a></li>' . "\n";
        }

        $html .= '  </ul>' . "\n";
        $html .= '</body>' . "\n";
        $html .= '</html>' . "\n";
        return $html;
    }
    private function indexEntries(int $page): array
    {
        return array_slice($this->allIndexEntries(), max(0, ($page - 1) * self::PER_PAGE), self::PER_PAGE);
    }
    private function allIndexEntries(): array
    {
        $entries = [
            [
                'loc'     => $this->sitemapUrl . '/home-1.xml',
                'lastmod' => wp_date('c', time()),
            ],
        ];

        foreach ($this->publicPostTypes() as $postType) {
            $pages = $this->postTypePages($postType);
            for ($i = 1; $i <= $pages; $i++) {
                $entries[] = [
                    'loc'     => sprintf('%s/posts-%s-%d.xml', $this->sitemapUrl, $postType, $i),
                    'lastmod' => $this->postTypeLastmod($postType),
                ];
            }
        }

        foreach ($this->publicTaxonomies() as $taxonomy) {
            $pages = $this->taxonomyPages($taxonomy);
            for ($i = 1; $i <= $pages; $i++) {
                $entries[] = [
                    'loc'     => sprintf('%s/taxonomies-%s-%d.xml', $this->sitemapUrl, $taxonomy, $i),
                    'lastmod' => wp_date('c', time()),
                ];
            }
        }

        $userPages = $this->userPages();
        for ($i = 1; $i <= $userPages; $i++) {
            $entries[] = [
                'loc'     => sprintf('%s/users-%d.xml', $this->sitemapUrl, $i),
                'lastmod' => wp_date('c', time()),
            ];
        }

        return $entries;
    }
    private function generateHomeUrlSet(int $page): string
    {
        $entries = [
            [
                'loc'     => $this->siteUrl . '/',
                'lastmod' => wp_date('c', time()),
            ],
        ];

        return $this->generateUrlSet(array_slice($entries, max(0, ($page - 1) * self::PER_PAGE), self::PER_PAGE));
    }
    private function generatePostTypeUrlSet(string $postType, int $page): string
    {
        $posts = get_posts([
            'post_type'              => $postType,
            'post_status'            => 'publish',
            'posts_per_page'         => self::PER_PAGE,
            'paged'                  => $page,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $entries = [];
        foreach ($posts as $postId) {
            $permalink = get_permalink($postId);
            if (!$permalink) {
                continue;
            }

            $entries[] = [
                'loc'     => $permalink,
                'lastmod' => get_post_modified_time('c', true, $postId),
            ];
        }

        return $this->generateUrlSet($entries);
    }
    private function generateTaxonomyUrlSet(string $taxonomy, int $page): string
    {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'number'     => self::PER_PAGE,
            'offset'     => ($page - 1) * self::PER_PAGE,
            'orderby'    => 'term_id',
            'order'      => 'ASC',
        ]);

        $entries = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $link = get_term_link($term);
                if (is_wp_error($link)) {
                    continue;
                }

                $entries[] = [
                    'loc'     => $link,
                    'lastmod' => wp_date('c', time()),
                ];
            }
        }

        return $this->generateUrlSet($entries);
    }
    private function generateUsersUrlSet(int $page): string
    {
        $query = new WP_User_Query([
            'number'  => self::PER_PAGE,
            'paged'   => $page,
            'orderby' => 'ID',
            'order'   => 'ASC',
            'fields'  => ['ID', 'user_login', 'user_nicename', 'display_name', 'user_registered'],
        ]);

        $entries = [];
        foreach ($query->get_results() as $user) {
            $username  = $user->user_nicename ?: $user->user_login;
            $entries[] = [
                'loc'     => $this->userPageUrl($user),
                'lastmod' => $this->userLastmod($user),
            ];
        }

        return $this->generateUrlSet($entries);
    }
    private function generateUrlSet(array $entries): string
    {
        return $this->xmlDocument('urlset', $entries);
    }
    private function xmlDocument(string $root, array $entries, array $comments = []): string
    {
        $item = $root === 'sitemapindex' ? 'sitemap' : 'url';
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        foreach ($comments as $comment) {
            $xml .= '<!-- ' . esc_html($comment) . ' -->' . "\n";
        }
        $xml .= '<' . $root . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($entries as $entry) {
            if (empty($entry['loc'])) {
                continue;
            }

            $xml .= '  <' . $item . '>' . "\n";
            $xml .= '    <loc>' . esc_url($entry['loc']) . '</loc>' . "\n";
            if (!empty($entry['lastmod'])) {
                $xml .= '    <lastmod>' . esc_html($entry['lastmod']) . '</lastmod>' . "\n";
            }
            $xml .= '  </' . $item . '>' . "\n";
        }

        $xml .= '</' . $root . '>' . "\n";
        return $xml;
    }
    private function publicPostTypes(): array
    {
        $postTypes = get_post_types([
            'public' => true,
        ], 'names');

        unset($postTypes['attachment']);

        return array_values($postTypes);
    }
    private function siteName(): string
    {
        return get_bloginfo('name') ?: parse_url($this->siteUrl, PHP_URL_HOST) ?: 'Site';
    }
    private function publicTaxonomies(): array
    {
        return array_values(get_taxonomies([
            'public' => true,
        ], 'names'));
    }
    private function homePages(): int
    {
        return 1;
    }
    private function postTypePages(string $postType): int
    {
        $counts = wp_count_posts($postType);
        $total  = isset($counts->publish) ? (int) $counts->publish : 0;

        return max(1, (int) ceil($total / self::PER_PAGE));
    }
    private function taxonomyPages(string $taxonomy): int
    {
        $count = wp_count_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
        ]);

        $total = is_wp_error($count) ? 0 : (int) $count;
        return max(1, (int) ceil($total / self::PER_PAGE));
    }
    private function userPages(): int
    {
        $query = new WP_User_Query([
            'number' => 1,
            'fields' => 'ID',
        ]);

        return max(1, (int) ceil((int) $query->get_total() / self::PER_PAGE));
    }
    private function publicUsers(): array
    {
        $query = new WP_User_Query([
            'number'  => -1,
            'orderby' => 'ID',
            'order'   => 'ASC',
            'fields'  => ['ID', 'user_login', 'user_nicename', 'display_name', 'user_registered'],
        ]);

        return $query->get_results();
    }
    private function postTypeLastmod(string $postType): string
    {
        $posts = get_posts([
            'post_type'              => $postType,
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        return $posts ? get_post_modified_time('c', true, $posts[0]) : wp_date('c', time());
    }
    private function userLastmod(object $user): string
    {
        $timestamp = isset($user->user_registered) ? strtotime($user->user_registered) : false;
        return wp_date('c', $timestamp ?: time());
    }
    private function usernameHash(string $username): string
    {
        return substr(hash('sha256', $username), 0, 16);
    }
    private function userPageUrl(object $user): string
    {
        $username = $user->user_nicename ?: $user->user_login;
        return $this->siteUrl . '/user/' . $this->usernameHash($username) . '/';
    }
    private function ensureDirectory(string $directory): bool
    {
        return is_dir($directory) || wp_mkdir_p($directory);
    }
}