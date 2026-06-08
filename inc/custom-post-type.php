<?php

function series_post_type() {
    $labels = array(
        'name'               => _x( 'Series', 'Post type name'),
        'singular_name'      => _x( 'Series', 'Post type singular name'),
        'menu_name'          => _x( 'Series', 'Post type name in menu'),
        'add_new'            => _x( 'Add New', 'Add new'),
        'add_new_item'       => __( 'Add New Series' ),
        'edit_item'          => __( 'Edit Series' ),
        'new_item'           => __( 'New Series' ),
        'view_item'          => __( 'View Series' ),
        'search_items'       => __( 'Search Series' ),
        'not_found'          => __( 'No Series found' ),
        'not_found_in_trash' => __( 'No Series found in Trash' ),
    );

    $args = array(
        'labels'        => $labels,
        'public'        => true,
        'hierarchical'  => true,
        'has_archive'   => true,
        'show_in_rest'  => true,
        'taxonomies'    => array( 'projects', 'collections' ),
        'supports'      => array( 'title', 'page-attributes', 'thumbnail', 'custom-fields' ),
        // 'rewrite'    => array( 'slug' => 'work' ),
        'menu_icon'     => 'dashicons-images-alt',
        'capability_type' => 'post'
    );

    register_post_type( 'series', $args );
}
add_action( 'init', 'series_post_type' );

add_action( 'init', function() {

    // 1. Projects taxonomy
    register_taxonomy( 'projects', 'series', [
        'label'        => 'Projects',
        'labels'       => [
            'name'          => 'Projects',
            'singular_name' => 'Project',
            'add_new_item'  => 'Add New Project',
            'edit_item'     => 'Edit Project',
            'search_items'  => 'Search Projects',
        ],
        'hierarchical' => true,
        'public'       => true,
        'show_ui'      => true,
        'show_in_rest' => true,
        'rest_base'    => 'projects',
    ] );

    // 2. Collections taxonomy
    register_taxonomy( 'collections', 'series', [
        'label'        => 'Collections',
        'labels'       => [
            'name'              => 'Collections',
            'singular_name'     => 'Collection',
            'add_new_item'      => 'Add New Collection',
            'new_item_name'     => 'New Collection Name',
            'edit_item'         => 'Edit Collection',
            'update_item'       => 'Update Collection',
            'view_item'         => 'View Collection',
            'search_items'      => 'Search Collections',
            'not_found'         => 'No Collections found',
            'not_found_in_trash'=> 'No Collections found in Trash',
            'menu_name'         => 'Collections',
        ],
        'hierarchical' => true,   // true = like categories, false = like tags
        'public'       => true,
        'show_ui'      => true,
        'show_in_rest' => true,
        'rest_base'    => 'collections',
        // 'rewrite'   => [
        //     'slug'         => 'collections',
        //     'with_front'   => false,
        //     'hierarchical' => true,
        // ],
    ] );

} );