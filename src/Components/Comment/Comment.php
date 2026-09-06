<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Services\CommentService;
use JEALER\G3\Services\SidebarService;
use JEALER\G3\Utilities\Element;
use Override;

class Comment extends Components {

    protected function prepareInAdmin(): void
    {
        $this->autoclosePingback();
        add_action('admin_footer-options-discussion.php', [$this, 'hideSettingsInDiscussionOptions']);
    }
    private function autoclosePingback(): void
    {
        update_option('default_pingback_flag', '');
        update_option('default_ping_status', 'closed');
    }

    public function hideSettingsInDiscussionOptions(): void
    {
        echo <<<HTML
<style>
h2.title,
h2.title + p,
h2.title + p + table,
label[for=default_pingback_flag],
label[for=default_pingback_flag] + br,
label[for=default_ping_status],
label[for=default_ping_status] + br
{display: none !important;}
</style>
HTML;
    }

    // protected function widgets(): void
    // {
    //     SidebarService::registerWidget('CommentWidget', __DIR__);
    // }

    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Comments'),
            __('Comments'),
            'manage_options',
            'g3-comments',
            [$this, 'render'],
            3,
        );
    }

    public function render()
    {
        $this->createPanel();
    }

    protected function adminPanelPage(): string
    {
        return 'g3-comments';
    }

    protected function adminPanels(): array
    {
        return [
            $this->panel('g3-comments', __('Comments'))
                ->tab('general', __('General'))
                ->option(CommentService::OPTION_KEY, CommentService::optionDefaults())
                ->switch('enable', __('Allow Comments'), __('Individual posts may override these settings. Changes here will only be applied to new posts.'))
                ->number('perPage', __('Items Per Page', 'G3'))
                ->number('maxLength', sprintf(__('%s %s', 'G3'), __('Comment'), __('Maximum Character Count', 'G3')))
                ->number('throttle', __('Throttle', 'G3'), sprintf(__('The frequency of %s transmission by users. The default setting is once every 5 seconds.', 'G3'), __('Comment')))
                ->switch('moderation', __('Comment Moderation'), __('Comment must be manually approved'))
                ->switch('hasApproved', __('Before a comment appears'), __('Comment author must have a previously approved comment'))
                ->textarea('moderationKeys', __('Comment Moderation'), __('One word or IP address per line.', 'G3'))
                ->textarea('disallowedKeys', __('Disallowed Comment Keys'), __('One word or IP address per line.', 'G3'))
                ->html('notification', __('Email Notification', 'G3'), Element::description(sprintf(__('It is advisable to disable <a href="%s">the email notification option</a> for WordPress comments.', 'G3'), admin_url('options-discussion.php'))))
        ];
    }
}
