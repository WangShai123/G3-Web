<?php

use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Image;

echo Element::tip(
    __('HTML element demo in admin panel.', 'G3'),
    '',
    'default',
    'mt-4'
);

function _renderIconList(array $list)
{
    foreach ($list as $icon) {
        $_ = Image::icon($icon);
        echo "<icon>{$_}<div>{$icon}</div></icon>";
    }
}
?>
<div class="mt-4">
    <h3><?php _e('Button', 'G3'); ?></h3>
    <div class="flex gap-2">
        <button class="button">button</button>
        <button class="button button-primary">primary</button>
        <button class="button button-error">error</button>
        <button class="button" disabled>disabled</button>
    </div>
    <div class="mt-3 flex flex-col">
        <code>&lt;button class="button"&gt;button&lt;/button&gt;</code>
        <code>&lt;button class="button button-primary"&gt;primary&lt;/button&gt;</code>
        <code>&lt;button class="button button-error"&gt;error&lt;/button&gt;</code>
    </div>
    <div class="flex gap-2 mt-4">
        <button class="button is-icon"><?php echo Image::icon('wordpress'); ?></button>
        <button class="button is-icon icon-error"><?php echo Image::icon('close'); ?></button>
    </div>
    <div class="mt-3 flex flex-col">
        <code>&lt;button class="button is-icon"&gt;{svgIconElement}&lt;/button&gt;</code>
        <code>&lt;button class="button is-icon icon-error"&gt;{svgIconElement}&lt;/button&gt;</code>
    </div>
</div>

<h3><?php _e('Icon'); ?></h3>
<p class="flex flex-col">
    <code>&lt;?php</code>
    <code>use JEALER\G3\Utilities\Image;</code>
    <code>echo Image::icon('iconName');</code>
</p>
<div>
    <h4 class="icon-title"><?php _e('System', 'G3'); ?></h4>
    <div class="flex flex-wrap icon-wrap">
        <?php _renderIconList([
            'close',
            'delete',
            'shield-check',
            'shield-keyhole',
            'loader',
            'search',
            'menu',
            'more'
        ]); ?>
    </div>

    <h4 class="icon-title"><?php _e('Finance', 'G3'); ?></h4>
    <div class="flex flex-wrap icon-wrap">
        <?php _renderIconList([
            'cart',
            'vip',
            'dollar',
            'rmb',
            'euro'
        ]); ?>
    </div>

    <h4 class="icon-title"><?php _e('User'); ?></h4>
    <div class="flex flex-wrap icon-wrap">
        <?php _renderIconList([
            'user',
            'admin'
        ]); ?>
    </div>

    <h4 class="icon-title"><?php _e('Design', 'G3'); ?></h4>
    <div class="flex flex-wrap icon-wrap">
        <?php _renderIconList([
            'palette'
        ]); ?>
    </div>

    <h4 class="icon-title"><?php _e('Development', 'G3'); ?></h4>
    <div class="flex flex-wrap icon-wrap">
        <?php _renderIconList([
            'bug',
            'code',
            'terminal',
            'command',
            'php',
            'javascript'
        ]); ?>
    </div>

    <h4 class="icon-title">Logo</h4>
    <div class="flex flex-wrap icon-wrap">
        <?php _renderIconList([
            'wordpress',
            'tiktok',
            'wechat',
            'wechatPay',
            'alipay',
        ]); ?>
    </div>
</div>

<style>
    .icon-title {
        margin: 8px 0;
    }

    .icon-wrap icon {
        flex: 0 0 80px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 8px 0;
    }

    .icon-wrap icon:hover {
        color: var(--brandColor);
        outline: 1px solid var(--brandColor);
        cursor: pointer;
        user-select: none;
    }

    .icon-wrap svg {
        width: 1.5rem;
        text-align: center;
    }

    .icon-wrap div {
        font-size: 10px;
    }
</style>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const { copy, toast, q } = jui;
        for (const item of document.querySelectorAll('.icon-wrap icon')) {
            item.addEventListener('click', function () {
                const iconText = q('div', item).textContent;
                copyText = `Image::icon('${iconText}')`;
                copy(copyText);
                toast.success('<?php _e('Copied'); ?> ' + iconText);
            });
        }
    });
</script>