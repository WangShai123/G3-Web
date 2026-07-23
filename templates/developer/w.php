<?php
use JEALER\G3\Components\Developer;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Utilities\Frontend;
Frontend::css('jui');
$dev = Container::run()->get(Developer::class);
$t   = $dev->time();
?>

<div class="wrap">
    <h1>
        <?php _e('Welcome!', 'G3'); ?>
        <?php if ($t) _e('Your G3 Web has been activated.', 'G3'); ?>
    </h1>
    <div class="mt-4 welcome-wrap">
        <div class="j-tip <?php echo $t ? 'is-success' : 'is-danger'; ?>">
            <div class="tip-title">
                <?php echo __('Tip', 'G3'); ?>
            </div>
            <div class="tip-content">
                <div>
                    <?php
                    if ($t) {
                        echo sprintf(__('Your G3-Web expires in %s', 'G3'), $t);
                    } else {
                        echo '<span style="color: var(--tip-color)">';
                        echo __('Part of the advanced features need to be activated.', 'G3') . ' ';
                        echo sprintf(__('Click <a href="%s"><b>HERE</b></a> to activate.', 'G3'), admin_url('admin.php?page=g3-verify-license'));
                        echo '</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .welcome h2 {
        font-size: 24px;
        line-height: 1.5;
        margin: 20px 0;
    }

    .welcome h3 {
        font-size: 18px;
        line-height: 1.5;
        margin: 16px 0 12px 0;
    }

    .welcome div,
    .welcome p {
        margin: 8px 0;
        line-height: 1.25;
    }

    .welcome-wrap ul {
        list-style: square;
        margin-left: 12px;
    }

    .functions {
        display: flex;
        flex-wrap: wrap;
        width: 100%;
        gap: 1px;
        background-color: var(--ui-bg);
        border: 1px solid var(--ui-border);
        user-select: none;
    }

    .functions div {
        flex: 1 0 120px;
        height: 96px;
        background-color: #F7F9FF;
        padding: 16px;
        margin: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
    }

    .functions div:nth-child(even) {
        background-color: var(--ui-surface-raised);
    }

    .functions div:hover {
        background-color: var(--ui-indigo);
        color: #fff;
        outline-offset: 1.5px;
        outline: 1.5px solid var(--ui-indigo);
        z-index: 1;
    }

    .j-tip .tip-icon {
        color: var(--tip-color);
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const { For, jsx, insert } = vanillaSignal;
        const { q } = jui
        const items = [
            { text: 'SEO' },
            { text: '<?php _e('Post Extension', 'G3'); ?>' },
            { text: '<?php _e('Content Distribution', 'G3'); ?>' },
            { text: '<?php _e('Frontend Editor', 'G3'); ?>' },
            { text: '<?php _e('Activity Feature', 'G3'); ?>' },
            { text: '<?php _e('Announcement Feature', 'G3'); ?>' },
            { text: '<?php _e('Community System', 'G3'); ?>' },
            { text: '<?php _e('Ads Management', 'G3'); ?>' },
            { text: '<?php _e('Shop System', 'G3'); ?>' },
            { text: '<?php _e('SKU System', 'G3'); ?>' },
            { text: '<?php _e('Orders System', 'G3'); ?>' },
            { text: '<?php _e('Payment System', 'G3'); ?>' },
            { text: '<?php _e('Balance Management', 'G3'); ?>' },
            { text: '<?php _e('Transaction System', 'G3'); ?>' },
            { text: '<?php _e('Credits System', 'G3'); ?>' },
            { text: 'Tokens Support' },
            { text: '<?php _e('Store Management System', 'G3'); ?>' },
            { text: '<?php _e('Wechat OA Support', 'G3'); ?>' },
            { text: '<?php _e('Membership System', 'G3'); ?>' },
            { text: '<?php _e('Marketing Tools', 'G3'); ?>' },
            { text: '<?php _e('Social Login', 'G3'); ?>' },
            { text: '<?php _e('Subscribe Login', 'G3'); ?>' },
            { text: '<?php _e('Multi-theme Mode', 'G3'); ?>' },
            { text: '<?php _e('Cloud File Storage', 'G3'); ?>' },
            { text: 'SMTP' },
            { text: '<?php _e('Custom Email Template', 'G3'); ?>' },
            { text: '<?php _e('Object Cache', 'G3'); ?>' },
            { text: '<?php _e('File Cache', 'G3'); ?>' },
            { text: '<?php _e('Queue Service', 'G3'); ?>' },
            { text: '<?php _e('Custom Admin', 'G3'); ?>' },
            { text: '<?php _e('Security Extension', 'G3'); ?>' },
            { text: '<?php _e('Junk Cleaner', 'G3'); ?>' },
            { text: '<?php _e('Developer Mode', 'G3'); ?>' }
        ]
        const welcome = jsx('div', {
            className: 'welcome',
            children: [
                jsx`<h2><?php _e('Tips for First Time Users', 'G3') ?></h2>`,
                jsx('div', {
                    className: 'features',
                    children: jsx('ul', {
                        children: [
                            jsx`<li><?php echo sprintf(__('Install and activate the <a href="%s">G3WebDesktop</a> theme to preview the template and its features.', 'G3'), admin_url('theme-install.php?search=g3WebDesktop')); ?></li>`,
                            jsx`<li><?php echo sprintf(__('Learn more about G3-Web Plugin by <a href="%s" target="_blank">Documents</a>  and <a href="%s" target="_blank">Courses</a>.', 'G3'), esc_url('https://www.jealer.com/documents/'), esc_url('https://www.jealer.com/courses/')); ?></li>`,
                            jsx`<li><?php _e('Authorized users can get free business consulting services once a quarter in the live broadcast.', 'G3'); ?></li>`
                        ]
                    })
                }),

                jsx`<h2><?php _e('G3-Web helps you easily create a clean, modern and full-fledged website.', 'G3'); ?></h2>`,

                jsx`<h3><?php _e('No matter who you are, G3-Web has got you covered.', 'G3'); ?></h3>`,
                jsx('ul', {
                    children: [
                        jsx`<li><?php _e('For site / business owners: G3-Web provides complete support and services for your all business needs.', 'G3'); ?></li>`,
                        jsx`<li><?php _e('For theme developers: G3-Web provides a set of comprehensive development tools to help you create professional-level WordPress themes quickly.', 'G3'); ?></li>`,
                        jsx`<li><?php _e('For designers: G3-Web helps you easily turn design proposals into immediately implementable systematic solutions.', 'G3'); ?></li>`,
                        jsx`<li><?php _e('For sales: G3-Web helps you easily build digital solutions that suit a variety of customers.', 'G3'); ?></li>`,
                        jsx`<li><?php _e('For beginners: G3-Web provides a simple and easy-to-use interface and tutorial to help you learn and build your first website.', 'G3'); ?></li>`
                    ]
                }),

                jsx`<h3><?php _e('Free and Helpful Courses', 'G3'); ?></h3>`,
                jsx`<p><?php _e('Even if you have no technical knowledge, our course can still help you systematically learn about Internet application technologies in 7-14 days and enable you to independently complete website development.', 'G3'); ?></p>`,

                jsx`<h3><?php _e('Over 30 Subsystem Components', 'G3'); ?></h3>`,
                jsx('div', {
                    className: 'functions',
                    children: For({
                        each: items,
                        key: (item, index) => index,
                        children: (item) => jsx`<div>${item().text}</div>`
                    })
                }),
                jsx`<h3><?php _e('Lazy Load', 'G3') ?></h3>`,
                jsx`<p><?php _e('All functional components are loaded only on demand, ensuring excellent runtime performance. Furthermore, users can freely combine and override them as needed.', 'G3'); ?></p>`,

                jsx`<h3><?php _e('Useful Development Features', 'G3'); ?></h3>`,
                jsx('div', {
                    className: 'features',
                    children: jsx('ul', {
                        children: [
                            jsx`<li><b>Rewrite:</b> <?php _e('Easy to create custom rewrite rules and templates through configuration.', 'G3'); ?></li>`,
                            jsx`<li><b>Controller:</b> <?php _e('Easy to create REST-Api through Controller and Router Attribute.', 'G3'); ?></li>`,
                            jsx`<li><b>Middleware:</b> <?php _e('Easy to build a strong and secure REST-Api through Middleware Attribute.', 'G3'); ?></li>`,
                            jsx`<li><b>Schema:</b> <?php _e('Easy to build a strong and secure REST-Api through Schema Attribute.', 'G3'); ?></li>`,
                            jsx`<li><b>AOP:</b> <?php _e('Easy to implement intercept, log, etc. through AOP configuration and AOP Attribute.', 'G3'); ?></li>`,
                            jsx`<li><b>DI:</b> <?php _e('Easy to get and manage objects & dependencies by DI container.', 'G3'); ?></li>`,
                            jsx`<li><b>Queue:</b> <?php _e('Easy to handle asynchronous tasks through Queue.', 'G3'); ?></li>`
                        ]
                    })
                }),

                jsx`<h3><?php _e('Flexible Payment Terms', 'G3'); ?></h3>`,
                jsx('ul', {
                    children: [
                        jsx`<li><?php echo '280 RMB/' . __('Year'); ?></li>`,
                        jsx`<li><?php _e('Free Forever after the third year.', 'G3'); ?></li>`
                    ]
                }),
            ]
        })
        insert(q('.welcome-wrap'), welcome)
    });
</script>