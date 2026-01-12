<?php
use JEALER\G3\Services\AuthService;
use JEALER\G3\Utilities\Frontend;

Frontend::loadStyle('jui');
get_header();

echo AuthService::loginElement(
    '<button class="j-button is-primary" data-login-element="login">' . __('Login', 'G3') . '</button>',
    true
);

get_footer();