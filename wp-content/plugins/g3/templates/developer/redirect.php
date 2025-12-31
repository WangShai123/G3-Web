<?php
use JEALER\G3\Utilities\Validator;

$encoded = get_query_var('redirect_url');
if (!$encoded) return;

$url = rawurldecode($encoded);

if (!Validator::safeRedirectUrl($url)) {
    wp_die(
        __('The URL you visited is illegal.', 'G3'),
        __('Warning', 'G3') . ' - ' . G3_NAME,
        [
            'response'  => 400,
            'charset'   => 'utf-8',
            'back_link' => true,
            'exit'      => true
        ]
    );
}

get_header();
?>

<div class="container">
    <h1><?php echo get_bloginfo('name'); ?></h1>
    <div class="wrap">
        <main>
            <h2 class="title">
                <?php
                echo __('Safe Reminder', 'G3');
                ?>
            </h2>
            <div class="des">
                <?php
                echo __('You are about to leave this site, please note your account and security.', 'G3');
                ?>
            </div>
            <div class="url"><?php echo $url; ?></div>
        </main>
        <footer>
            <a href="<?php echo $url; ?>">
                <?php echo __('Continue to Visit', 'G3'); ?>
            </a>
        </footer>
    </div>
</div>

<style>
    body {
        background-color: #eff2f5;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 10px;
        margin: 0;
        height: calc(100vh - 20px);
    }

    .container {
        display: block;
        margin-top: -64px;
    }

    .container h1 {
        margin-top: 0;
        text-align: center;
        margin-bottom: 16px;
        font-size: 1.8rem;
    }

    .wrap {
        padding: 24px;
        background-color: #f7f7f7;
        border: 1px solid #babbbc;
        border-radius: 4px;
    }


    @media (max-width: 768px) {
        .wrap {
            padding: 16px;
        }
    }

    .wrap main {
        padding-bottom: 16px;
        margin-bottom: 16px;
        border-bottom: 1px solid #babbbc;
    }

    main h2 {
        margin-top: 0;
        font-size: 1rem;
        color: #333;
    }

    main .des {
        font-size: 14px;
        color: #444;
    }

    main .url {
        overflow-wrap: anywhere;
        margin-top: 16px;
        color: #666;
        font-style: italic;
        font-size: 13px;
        max-width: 600px;
    }

    .wrap footer {
        display: flex;
        justify-content: end;
    }

    footer a {
        padding: 8px 12px;
        background-color: #0f49bd;
        border-radius: 4px;
        text-decoration: none;
        color: #fff;
        font-size: 14px;
    }
</style>

<?php
get_footer();