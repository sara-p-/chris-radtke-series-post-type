<?php

function pricing_package_post_type() {
    $labels = array(
        'name'               => _x( 'Pricing Packages', 'Post type name'),
        'singular_name'      => _x( 'Pricing Package', 'Post type singular name'),
        'menu_name'          => _x( 'Pricing Packages', 'Post type name in menu'),
        'add_new'            => _x( 'Add New', 'Add new'),
        'add_new_item'       => __( 'Add New Pricing Package' ),
        'edit_item'          => __( 'Edit Pricing Package' ),
        'new_item'           => __( 'New Pricing Package' ),
        'view_item'          => __( 'View Pricing Package' ),
        'search_items'       => __( 'Search Pricing Packages' ),
        'not_found'          => __( 'No Pricing Packages found' ),
        'not_found_in_trash' => __( 'No Pricing Packages found in Trash' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true, 
        'hierarchical' => true,
        'has_archive'        => false, 
        'show_in_rest'       => true, 
        'supports'           => array( 'title', 'custom-fields', 'page-attributes' ), 
        // 'rewrite'            => array( 'slug' => 'pricing-packages' ), 
        'menu_icon'          => 'dashicons-admin-comments',
    );

    register_post_type( 'pricing-package', $args );
}
add_action( 'init', 'pricing_package_post_type' );