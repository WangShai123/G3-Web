<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\SwiperService;

$containerClass = $args['containerClass'];
$swipers        = SwiperService::queryByLocation($args['location']);
$params         = $args;
unset($params['containerClass'], $params['location']);
$data = [];
if (!empty($swipers)) {
    foreach ($swipers as $swiper) {
        if (isset($swiper['status']) && $swiper['status'] != 1) {
            continue;
        }
        $item   = [
            'image'  => isset($swiper['media']) ? esc_url($swiper['media']) : '',
            'url'    => isset($swiper['link']) ? esc_url($swiper['link']) : '',
            'title'  => isset($swiper['title']) ? esc_attr($swiper['title']) : '',
            'sort'   => isset($swiper['sort']) ? intval($swiper['sort']) : 0,
            'target' => isset($swiper['target']) ? esc_attr($swiper['target']) : '_blank',
        ];
        $data[] = $item;
    }
}
$params['data'] = $data;
echo '<div class="' . esc_attr($containerClass) . '"></div>';

Frontend::css('jui');
Frontend::umd('jui');
$jsonParams = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
wp_add_inline_script('jui', 'const {Swiper} = jui;console.log(' . $jsonParams . '); new Swiper(".j-swiper-container", ' . $jsonParams . ').build();');