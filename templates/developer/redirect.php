<?php
use JEALER\G3\Utilities\Validator;

$encoded = get_query_var('g3_var_redirect_url');
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
            <a href="<?php bloginfo('url'); ?>" class="j-button is-secondary">
                <?php echo __('Back'); ?>
            </a>
            <a href="<?php echo $url; ?>" class="j-button is-primary">
                <?php echo __('Continue to Visit', 'G3'); ?>
            </a>
        </footer>
    </div>
</div>

<div class="ad">
    ad container here.
</div>

<style>
    body {
        background: var(--ui-bg-subtle);
        display: flex;
        flex-direction: column;
        justify-content: start;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        margin: 0;
    }

    .container h1 {
        text-align: center;
        margin-bottom: 1rem;
        color: var(--ui-fg);
    }

    .wrap {
        padding: 1.5rem;
        border: 1px solid var(--ui-border);
        border-radius: var(--radius-lg);
        background: var(--ui-bg);
    }

    @media (max-width: 768px) {
        .wrap {
            padding: 1rem;
        }
    }

    .wrap main {
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 1px solid var(--ui-border);
    }

    main h2 {
        margin-top: 0;
        font-size: 1rem;
        color: var(--ui-fg);
    }

    main .des {
        font-size: var(--font-size-sm);
        color: var(--ui-fg-muted);
    }

    main .url {
        overflow-wrap: anywhere;
        margin-top: 1rem;
        color: var(--ui-fg-muted);
        font-style: italic;
        font-size: var(--font-size-sm);
    }

    .wrap footer {
        display: flex;
        justify-content: end;
        gap: .5rem;
    }

    footer a {
        text-decoration: none;
    }
</style>
<?php
get_footer();
