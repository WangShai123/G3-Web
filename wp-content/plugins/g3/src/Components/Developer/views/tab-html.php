<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Image;

Frontend::loadScript('jui');
echo Element::tip(
    __('HTML element demo in admin panel.', 'G3'),
    'default',
    'mt-4'
);
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
        <icon title="aaaaa">
            <?php echo Image::icon('close'); ?>
            <div>close</div>
        </icon>
        <icon>
            <?php echo Image::icon('delete'); ?>
            <div>delete</div>
        </icon>
    </div>

    <h4 class="icon-title"><?php _e('Development', 'G3'); ?></h4>
    <div class="flex flex-wrap icon-wrap">
        <icon>
            <?php echo Image::icon('bug'); ?>
            <div>bug</div>
        </icon>
        <icon>
            <?php echo Image::icon('code'); ?>
            <div>code</div>
        </icon>
        <icon>
            <?php echo Image::icon('terminal'); ?>
            <div>terminal</div>
        </icon>
        <icon>
            <?php echo Image::icon('command'); ?>
            <div>command</div>
        </icon>
        <icon>
            <?php echo Image::icon('php'); ?>
            <div>php</div>
        </icon>
        <icon>
            <?php echo Image::icon('javascript'); ?>
            <div>javascript</div>
        </icon>
    </div>

    <h4 class="icon-title">Logo</h4>
    <div class="flex flex-wrap icon-wrap">
        <icon>
            <?php echo Image::icon('wordpress'); ?>
            <div>wordpress</div>
        </icon>
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
        function copy(text) {
            const $temp = document.createElement("input");
            document.body.appendChild($temp);
            $temp.value = text;
            $temp.select();
            document.execCommand("copy");
            $temp.remove();
        }
        document.querySelectorAll('.icon-wrap icon').forEach(function (item) {
            item.addEventListener('click', function () {
                let iconText = item.querySelector('div').textContent;
                copy(iconText);
                jui.toast.success('<?php _e('Copied'); ?> ' + iconText, 2000);
            });
        });
    });
</script>