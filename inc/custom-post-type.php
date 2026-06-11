<?php

function work_post_type() {
    $labels = array(
        'name'               => _x( 'Work', 'Post type name'),
        'singular_name'      => _x( 'Work', 'Post type singular name'),
        'menu_name'          => _x( 'Work', 'Post type name in menu'),
        'add_new'            => _x( 'Add New', 'Add new'),
        'add_new_item'       => __( 'Add New Work' ),
        'edit_item'          => __( 'Edit Work' ),
        'new_item'           => __( 'New Work' ),
        'view_item'          => __( 'View Work' ),
        'search_items'       => __( 'Search Work' ),
        'not_found'          => __( 'No Work found' ),
        'not_found_in_trash' => __( 'No Work found in Trash' ),
    );

    $args = array(
        'labels'        => $labels,
        'public'        => true,
        'hierarchical'  => true,
        'has_archive'   => false,
        'show_in_rest'  => true,
        'taxonomies'    => array( 'projects', 'collections' ),
        'supports'      => array( 'title', 'page-attributes', 'thumbnail', 'custom-fields' ),
        'menu_icon'     => 'dashicons-images-alt',
        'capability_type' => 'post'
    );

    register_post_type( 'work', $args );
}
add_action( 'init', 'work_post_type' );

add_action( 'init', function() {

    // 1. Projects taxonomy
    register_taxonomy( 'projects', 'work', [
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
    register_taxonomy( 'collections', 'work', [
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
    ] );

} );