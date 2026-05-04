<?php

function testimonial_post_type() {
    $labels = array(
        'name'               => _x( 'Testimonials', 'Post type name'),
        'singular_name'      => _x( 'Testimonial', 'Post type singular name'),
        'menu_name'          => _x( 'Testimonials', 'Post type name in menu'),
        'add_new'            => _x( 'Add New', 'Add new'),
        'add_new_item'       => __( 'Add New Testimonial' ),
        'edit_item'          => __( 'Edit Testimonial' ),
        'new_item'           => __( 'New Testimonial' ),
        'view_item'          => __( 'View Testimonial' ),
        'search_items'       => __( 'Search Testimonials' ),
        'not_found'          => __( 'No Testimonials found' ),
        'not_found_in_trash' => __( 'No Testimonials found in Trash' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true, 
        'has_archive'        => false, 
        'show_in_rest'       => true, 
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom_fields' ), 
        'rewrite'            => array( 'slug' => 'testimonials' ), 
        'menu_icon'          => 'dashicons-admin-comments',
    );

    register_post_type( 'testimonial', $args );
}
add_action( 'init', 'testimonial_post_type' );