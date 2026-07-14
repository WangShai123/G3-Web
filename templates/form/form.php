<?php
use JEALER\G3\Utilities\Frontend;

get_header();
get_template_part('parts/header/index');
Frontend::css('jui');
Frontend::esm('g3.form');
?>
<div class="container flex-container contact-form-container">
    <h2>
        <?php _e('Contact us', 'G3'); ?>
    </h2>
    <form class="j-form is-vertical is-item-vertical" id="contact-form" data-form="contact">
        <div class="form-item">
            <label for="title" class="item-label is-required"><?php _e('Title') ?>
            </label>
            <div class="form-control">
                <input type="text" class="j-input" id="title" name="title" placeholder="Enter title" />
            </div>
        </div>
        <div class="form-item">
            <label for="email" class="item-label is-required"><?php _e('Email') ?></label>
            <div class="form-control">
                <input type="email" class="j-input" id="email" name="email" placeholder="Enter email"
                    autocomplete="email" />
            </div>
        </div>
        <div class="form-item">
            <label for="phone" class="item-label"><?php _e('Phone', 'G3') ?>
            </label>
            <div class="form-control">
                <input type="tel" class="j-input" id="phone" name="phone" placeholder="Enter phone"
                    autocomplete="tel" />
            </div>
        </div>
        <div class="form-item">
            <label for="content" class="item-label is-required"><?php _e('Content') ?>
            </label>
            <div class="form-control">
                <textarea class="j-textarea" id="content" name="content" placeholder="Enter content"></textarea>
            </div>
        </div>
        <div class="form-buttons">
            <button type="submit" class="j-button is-primary" id="submit-contact">
                <?php _e('Submit') ?>
            </button>
            <button type="reset" class="j-button is-ghost">
                <?php _e('Reset', 'G3') ?>
            </button>
        </div>
    </form>
</div>
<style>
    .contact-form-container {
        padding-block: 40px;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }

    #contact-form {
        width: 100%;
        max-width: 480px;
        border: 1px solid var(--ui-border);
        border-radius: var(--radius-md);
        padding: 1.5rem;
    }

    @media (min-width: 768px) {
        .contact-form-container {
            padding-block: 80px;
        }
    }
</style>
<?php
get_footer();
