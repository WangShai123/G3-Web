<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\SwiperService;
$container      = $args['container'];
$containerId    = $args['container_id'];
$containerClass = $args['container_class'];
$lazy           = !empty($args['lazy']);
$swipers        = SwiperService::queryByLocation($args['location']);
?>

<<?php echo $container; ?>
    id="<?php echo esc_attr($containerId); ?>"
    class="<?php echo esc_attr($containerClass); ?>"
    >
    <div class="swiper-wrapper">
        <?php foreach ($swipers as $item) : ?>
            <?php
            $link   = $item->link;
            $media  = $item->media;
            $title  = $item->title;
            $target = $item->target == 0 ? '_self' : '_blank';
            ?>
            <a href="<?php echo esc_url($link); ?>" class="swiper-slide" target="<?php echo esc_attr($target); ?>">
                <?php if ($lazy) : ?>
                    <img data-src="<?php echo esc_url($media); ?>" class="swiper-image swiper-lazy"
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

    <?php if (!empty($args['pagination'])) : ?>
        <div class="<?php echo esc_attr($args['pagination_class']); ?>"></div>
    <?php endif; ?>

    <?php if (!empty($args['navigation'])) : ?>
        <div class="<?php echo esc_attr($args['navigation_prev_class']); ?>"></div>
        <div class="<?php echo esc_attr($args['navigation_next_class']); ?>"></div>
    <?php endif; ?>

    <?php if (!empty($args['scroll'])) : ?>
        <div class="<?php echo esc_attr($args['scroll_class']); ?>"></div>
    <?php endif; ?>
</<?php echo $container; ?>>

<script type="text/javascript">
    const swiperElement = '#<?php echo esc_js($containerId); ?>';

    const swiperOptions = {
        direction: '<?php echo !empty($args['horizontal']) ? "horizontal" : "vertical"; ?>',
        speed: <?php echo intval($args['speed'] ?? 300); ?>,
        loop: <?php echo !empty($args['loop']) ? "true" : "false"; ?>,

        <?php if (!empty($args['autoplay'])) : ?>
                    autoplay: { delay: <?php echo intval($args['autoplay_delay'] ?? 3000); ?> },
        <?php endif; ?>

        <?php if (!empty($args['pagination'])) : ?>
                    pagination: { el: '.<?php echo esc_js($args['pagination_class']); ?>' },
        <?php endif; ?>

        <?php if (!empty($args['navigation'])) : ?>
                    navigation: {
                nextEl: '.<?php echo esc_js($args['navigation_next_class']); ?>',
                prevEl: '.<?php echo esc_js($args['navigation_prev_class']); ?>'
            },
        <?php endif; ?>

        <?php if (!empty($args['scroll'])) : ?>
                    scrollbar: {
                el: '.<?php echo esc_js($args['scroll_class']); ?>',
                hide: <?php echo !empty($args['scroll_hide']) ? "true" : "false"; ?>
            },
        <?php endif; ?>

        <?php if (!empty($args['lazy'])) : ?>
                    lazy: { loadPrevNext: true }
        <?php endif; ?>
    };
</script>

<?php
Frontend::loadStyle('swiper');
Frontend::loadScript('swiper');
wp_add_inline_script('swiper', 'new Swiper(swiperElement, swiperOptions);');
?>