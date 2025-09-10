<?php
/**
 * Custom Post Types for Wedding RSVP System
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Guest Custom Post Type
 */
function register_guest_post_type() {
    $args = array(
        'label'                 => __('Guests', 'delavina'),
        'description'           => __('Wedding guests and their RSVP information', 'delavina'),
        'labels'                => array(
            'name'                  => _x('Guests', 'Post Type General Name', 'delavina'),
            'singular_name'         => _x('Guest', 'Post Type Singular Name', 'delavina'),
            'menu_name'             => __('Guests', 'delavina'),
            'name_admin_bar'        => __('Guest', 'delavina'),
            'archives'              => __('Guest Archives', 'delavina'),
            'attributes'            => __('Guest Attributes', 'delavina'),
            'parent_item_colon'     => __('Parent Guest:', 'delavina'),
            'all_items'             => __('All Guests', 'delavina'),
            'add_new_item'          => __('Add New Guest', 'delavina'),
            'add_new'               => __('Add New', 'delavina'),
            'new_item'              => __('New Guest', 'delavina'),
            'edit_item'             => __('Edit Guest', 'delavina'),
            'update_item'           => __('Update Guest', 'delavina'),
            'view_item'             => __('View Guest', 'delavina'),
            'view_items'            => __('View Guests', 'delavina'),
            'search_items'          => __('Search Guest', 'delavina'),
            'not_found'             => __('Not found', 'delavina'),
            'not_found_in_trash'    => __('Not found in Trash', 'delavina'),
            'featured_image'        => __('Featured Image', 'delavina'),
            'set_featured_image'    => __('Set featured image', 'delavina'),
            'remove_featured_image' => __('Remove featured image', 'delavina'),
            'use_featured_image'    => __('Use as featured image', 'delavina'),
            'insert_into_item'      => __('Insert into guest', 'delavina'),
            'uploaded_to_this_item' => __('Uploaded to this guest', 'delavina'),
            'items_list'            => __('Guests list', 'delavina'),
            'items_list_navigation' => __('Guests list navigation', 'delavina'),
            'filter_items_list'     => __('Filter guests list', 'delavina'),
        ),
        'supports'              => array('title', 'custom-fields'),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 20,
        'menu_icon'             => 'dashicons-groups',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
        'show_in_graphql'       => true,
        'graphql_single_name'   => 'guest',
        'graphql_plural_name'   => 'guests',
    );
    register_post_type('guest', $args);
}
add_action('init', 'register_guest_post_type', 15);

/**
 * Ensure post type shows in admin menu
 */
function ensure_guest_menu_visibility() {
    global $submenu, $menu;
    
    // Force add Guests menu if it doesn't exist
    if (!menu_page_url('edit.php?post_type=guest', false)) {
        add_menu_page(
            'Guests',
            'Guests', 
            'manage_options',
            'edit.php?post_type=guest',
            '',
            'dashicons-groups',
            25
        );
    }
}
add_action('admin_menu', 'ensure_guest_menu_visibility', 100);

/**
 * Flush rewrite rules on activation to ensure post type is recognized
 */
function delavina_flush_rewrites() {
    register_guest_post_type();
    flush_rewrite_rules();
}

/**
 * Set default title for guest posts
 */
function set_guest_default_title($data, $postarr) {
    if ($data['post_type'] == 'guest' && empty($data['post_title'])) {
        $name = get_field('name', $postarr['ID']) ?: '';
        
        if ($name) {
            $data['post_title'] = trim($name);
        } else {
            $data['post_title'] = 'Guest #' . time();
        }
    }
    return $data;
}
add_filter('wp_insert_post_data', 'set_guest_default_title', 10, 2);

/**
 * Update guest title when ACF fields are saved
 */
function update_guest_title_on_acf_save($post_id) {
    if (get_post_type($post_id) == 'guest') {
        $name = get_field('name', $post_id);
        
        if (!empty($name)) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => trim($name)
            ));
        }
    }
}
add_action('acf/save_post', 'update_guest_title_on_acf_save', 20);