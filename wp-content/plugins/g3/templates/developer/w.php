<?php
use JEALER\G3\Components\Developer;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Image;
Frontend::loadStyle('jui');
$time = Developer::time();
?>

<div class="wrap">
    <h1>
        <?php _e('Welcome!', 'G3'); ?>
        <?php _e('Your G3 Web has been activated.', 'G3'); ?>
    </h1>
    <div class="mt-4 welcome-wrap">
        <div class="j-tip <?php echo $time ? 'is-success' : 'is-danger'; ?>">
            <div class="tip-title">
                <?php echo __('Tip', 'G3'); ?>
            </div>
            <div class="tip-content">
                <div>
                    <?php
                    if ($time) {
                        echo sprintf(__('Your G3-Web expires in %s', 'G3'), $time);
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

        <div class="welcome">
            <h2><?php echo __('Tips for First Time Users', 'G3') ?></h2>
            <div class="features">
                <ul>
                    <li>
                        <?php
                        echo sprintf(
                            __('Install and activate the <a href="%s">G3-Desktop theme</a> to preview the template and its features.', 'G3'),
                            admin_url('theme-install.php?search=g3-web')
                        );
                        ?>
                    </li>
                    <li>
                        <?php
                        echo sprintf(
                            __('Install and activate the <a href="%s">G3-Mobile theme</a> to preview the mobile template and its features if you need.', 'G3'),
                            admin_url('theme-install.php?search=g3-mobile')
                        );
                        ?>
                    </li>
                    <li>
                        <?php
                        echo sprintf(
                            __('Learn more about G3-Web Plugin by <a href="%s" target="_blank">Documents</a>  and <a href="%s" target="_blank">Courses</a>.', 'G3'),
                            esc_url('https://www.jealer.com/documents/'),
                            esc_url('https://www.jealer.com/courses/')
                        );
                        ?>
                    </li>
                    <li>
                        <?php _e('Authorized users can get free business consulting services once a quarter in the live broadcast.', 'G3'); ?>
                    </li>
                </ul>
            </div>


            <h2><?php echo __('G3-Web helps you easily create a clean, modern and full-fledged website.', 'G3'); ?>
            </h2>
            <h3><?php _e('Useful Development Features', 'G3'); ?></h3>
            <div class="features">
                <ul>
                    <li>
                        <b>Rewrite:</b>
                        <?php _e('Easy to create custom rewrite rules and templates through configuration.', 'G3'); ?>
                    </li>
                    <li>
                        <b>Controller:</b>
                        <?php _e('Easy to create REST-Api through Controller and Router Attribute.', 'G3'); ?>
                    </li>
                    <li>
                        <b>Middleware:</b>
                        <?php _e('Easy to build a strong and secure REST-Api through Middleware Attribute.', 'G3'); ?>
                    </li>
                    <li>
                        <b>Schema:</b>
                        <?php _e('Easy to build a strong and secure REST-Api through Schema Attribute.', 'G3'); ?>
                    </li>
                    <li>
                        <b>AOP:</b>
                        <?php
                        _e('Easy to implement intercept, log, etc. through AOP configuration and AOP Attribute.', 'G3');
                        ?>
                    </li>
                </ul>
            </div>
            <h3><?php _e('Over 20 Subsystem Components', 'G3'); ?></h3>
            <div class="functions">
                <div><?php echo __('Cache') . ' ' . __('Support'); ?></div>
                <div>SEO <?php _e('Support'); ?></div>
                <div><?php _e('Post Extension', 'G3'); ?></div>
                <div><?php _e('Social Login', 'G3'); ?></div>
                <div><?php _e('Content Distribution', 'G3'); ?></div>
                <div><?php _e('Membership System', 'G3'); ?></div>
                <div><?php _e('Shop System', 'G3'); ?></div>
                <div><?php _e('SKU System', 'G3'); ?></div>
                <div><?php _e('Payment System', 'G3'); ?></div>
                <div><?php _e('Order System', 'G3'); ?></div>
                <div><?php _e('Balance Management', 'G3'); ?></div>
                <div><?php _e('Transaction System', 'G3'); ?></div>
                <div><?php _e('Credits System', 'G3'); ?></div>
                <div>Tokens Support</div>
                <div><?php _e('Store Management System', 'G3'); ?></div>
                <div><?php _e('Ads Management', 'G3'); ?></div>
                <div><?php _e('Activity Feature', 'G3'); ?></div>
                <div><?php _e('Announcement Feature', 'G3'); ?></div>
                <div><?php _e('Community System', 'G3'); ?></div>
                <div><?php _e('Wechat OA Support', 'G3'); ?></div>
                <div><?php _e('Multiple Themes Mode', 'G3'); ?></div>
                <div><?php _e('OSS Support', 'G3'); ?></div>
                <div><?php _e('SMTP Support', 'G3'); ?></div>
                <div><?php _e('Marketing Tools', 'G3'); ?></div>
                <div><?php _e('Security Tools', 'G3'); ?></div>
                <div><?php _e('Developer Mode', 'G3'); ?></div>
                <div><?php _e('Custom Admin', 'G3'); ?></div>
            </div>
            <h3><?php _e('Free and Helpful Courses', 'G3'); ?></h3>
            <p><?php _e('Even if you have no technical knowledge, our course can still help you
                systematically learn about Internet application technologies in 7-14 days and enable you to independently complete
                website development.', 'G3'); ?></p>
            <h3><?php _e('Flexible Payment Terms', 'G3'); ?></h3>
            <ul>
                <li><?php echo '240 RMB/' . __('Year', 'G3'); ?></li>
                <li><?php _e('Free Forever after the third year.', 'G3'); ?></li>
            </ul>
            <h3><?php _e('No matter who you are, G3-Web has got you covered.', 'G3'); ?></h3>
            <ul>
                <li>
                    <?php
                    _e('For site / business owners: G3-Web provides complete support and services for your all business needs.', 'G3');
                    ?>
                </li>
                <li>
                    <?php
                    _e('For theme developers: G3-Web provides a set of comprehensive development tools to help you create professional-level WordPress themes quickly.', 'G3');
                    ?>
                </li>
                <li>
                    <?php _e('For designers: G3-Web helps you easily turn design proposals into immediately implementable systematic solutions.', 'G3'); ?>
                </li>
                <li>
                    <?php _e('For sales: G3-Web helps you easily build digital solutions that suit a variety of customers.', 'G3'); ?>
                </li>
                <li>
                    <?php _e('For beginners: G3-Web provides a simple and easy-to-use interface and tutorial to help you learn and build your first website.', 'G3'); ?>
                </li>
            </ul>
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
        background-color: var(--gray-a3);
        border: 1px solid var(--gray-a1);
        user-select: none;
    }

    .functions div {
        flex: 1 0 120px;
        height: 96px;
        background-color: var(--indigo-2);
        padding: 16px;
        margin: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
    }

    .functions div:nth-child(even) {
        background-color: var(--gray-1);
    }

    .functions div:hover {
        background-color: var(--indigo-9);
        color: white;
        outline-offset: 1.5px;
        outline: 1.5px solid var(--indigo-9);
        z-index: 1;
    }

    .j-tip .tip-icon {
        color: var(--tip-color);
    }
</style>