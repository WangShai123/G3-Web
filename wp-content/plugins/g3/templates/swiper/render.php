<?php

use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\SwiperService;

$containerClass = $args['containerClass'];
$swipers        = SwiperService::queryByLocation($args['location']);
?>

<div class="swiper <?php echo esc_attr($containerClass); ?>">
    <div class="swiper-wrapper">
        <?php foreach ($swipers as $item) : ?>
            <?php
            $link   = $item['link'];
            $media  = $item['media'];
            $title  = $item['title'];
            $target = $item['target'] == 0 ? '_self' : '_blank';
            ?>
            <a href="<?php echo esc_url($link); ?>" class="swiper-slide" target="<?php echo esc_attr($target); ?>">
                <?php if (!empty($args['lazy'])) : ?>
                    <img src="<?php echo esc_url($media); ?>" class="swiper-image" loading="lazy"
                        title="<?php echo esc_attr($title); ?>" />
                    <span class="swiper-title"><?php echo esc_html($title); ?></span>
                    <div class="swiper-lazy-preloader"></div>
                <?php else : ?>
                    <img src="<?php echo esc_url($media); ?>" class="swiper-image" title="<?php echo esc_attr($title); ?>" />
                    <span class="swiper-title"><?php echo esc_html($title); ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php
    if (!empty($args['pagination'])) :
        echo '<div class="swiper-pagination"></div>';
    endif;

    if (!empty($args['navigation'])) :
        echo '<div class="swiper-button-prev"></div><div class="swiper-button-next"></div>';
    endif;

    if (!empty($args['scrollbar'])) :
        echo '<div class="swiper-scrollbar"></div>';
    endif;

    echo '</div>';

    Frontend::loadStyle('swiper');
    Frontend::loadScript('swiper');

    wp_add_inline_script('swiper', 'new Swiper(".swiper", ' . json_encode($args) . ')');
