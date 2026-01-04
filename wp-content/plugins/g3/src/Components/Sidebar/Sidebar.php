<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components;
use Override;

class Sidebar extends Components {
    #[Override]
    protected function init(): void
    {
    }

    private function initSidebars(): void
    {
        register_sidebar(
            [
                'name'          => __('Homepage Sidebar', 'G3'),
                'id'            => 'home',
                'description'   => __('You can add widgets to homepage sidebar.', 'G3'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );
        register_sidebar(
            [
                'name'          => __('Archive Sidebar', 'G3'),
                'id'            => 'archive',
                'description'   => __('You can add widgets to archive sidebar.', 'G3'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );

        register_sidebar(
            [
                'name'          => __('Search Sidebar', 'G3'),
                'id'            => 'search',
                'description'   => __('You can add widgets to search sidebar.', 'G3'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );

        register_sidebar(
            [
                'name'          => __('My Page Sidebar', 'G3'),
                'id'            => 'my',
                'description'   => __('You can add widgets to my page sidebar.', 'G3'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );
        register_sidebar(
            [
                'name'          => __('User Page Sidebar', 'G3'),
                'id'            => 'user',
                'description'   => __('You can add widgets to user page sidebar.', 'G3'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );
        register_sidebar(
            [
                'name'          => __('News Sidebar', 'G3'),
                'id'            => 'news',
                'description'   => __('You can add widgets to news sidebar.', 'G3'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );
        register_sidebar(
            [
                'name'          => __('Circle Sidebar', 'G3'),
                'id'            => 'circle',
                'description'   => __('You can add widgets to circle sidebar.', 'G3'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );
        register_sidebar(
            [
                'name'          => __('Announcement Sidebar', 'G3'),
                'id'            => 'announcement',
                'description'   => __('You can add widgets to announcement sidebar.', 'G3'),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );
        register_sidebar([
            'name'          => __('Activity Sidebar', 'G3'),
            'id'            => 'activity',
            'description'   => __('You can add widgets to activity sidebar.', 'G3'),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]);
        register_sidebar([
            'name'          => __('Forum Sidebar', 'G3'),
            'id'            => 'forum',
            'description'   => __('You can add widgets to forum sidebar.', 'G3'),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]);
    }
}