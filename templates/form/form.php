<?php
use JEALER\G3\Utilities\Frontend;

get_header();
get_template_part('parts/header/index');
Frontend::css('jui');
Frontend::esm('vanilla-signal-i18n');
Frontend::esm('jui');
Frontend::esm('g3.form');
?>
<div class="container flex-container contact-form-container">
    <h2><?php _e('Contact us', 'G3'); ?></h2>
</div>
<style>
    .contact-form-container {
        padding-block: 40px;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }

    .contact-form-container .j-form {
        width: 100%;
        max-width: 480px;
        border: 1px solid var(--ui-border);
        border-radius: var(--radius-md);
        padding: 1.5rem;
    }

    @media (min-width: 768px) {
        .contact-form-container {
            padding-block: 60px;
        }
    }
</style>
<?php
get_footer();
