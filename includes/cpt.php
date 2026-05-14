<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function fxforms_register_cpt(): void
{
    register_post_type(FXFORMS_CPT, [
        'labels'              => [
            'name'               => __('Forms', 'fx-forms'),
            'singular_name'      => __('Form', 'fx-forms'),
            'menu_name'          => __('FX Forms', 'fx-forms'),
            'name_admin_bar'     => __('Form', 'fx-forms'),
            'add_new'            => __('Add New', 'fx-forms'),
            'add_new_item'       => __('Add New Form', 'fx-forms'),
            'edit_item'          => __('Edit Form', 'fx-forms'),
            'new_item'           => __('New Form', 'fx-forms'),
            'all_items'          => __('All Forms', 'fx-forms'),
            'search_items'       => __('Search Forms', 'fx-forms'),
            'not_found'          => __('No forms found', 'fx-forms'),
            'not_found_in_trash' => __('No forms found in Trash', 'fx-forms'),
        ],
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => false,
        'menu_position'       => 25,
        'menu_icon'           => 'dashicons-email-alt',
        'supports'            => ['title'],
        'has_archive'         => false,
        'rewrite'             => false,
        'hierarchical'        => false,
        'map_meta_cap'        => true,
    ]);
}
