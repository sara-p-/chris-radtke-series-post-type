<?php
/**
 * Plugin Name: Work Plugin
 * Description: Registers a CPT, custom fields, and custom blocks for Work
 * Version: 1.0
 * Author: Sara Pitt
 * Text Domain: crp-work
 * Domain Path: /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

load_plugin_textdomain( 'crp-work', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

require_once 'inc/custom-post-type.php';
require_once 'inc/custom-fields.php';


add_action( 'init', function() {
    foreach ( array( 'work-hero', 'work-items', 'work-statement' ) as $block ) {
        $result = register_block_type( __DIR__ . '/build/blocks/' . $block );
        if ( ! $result ) {
            error_log( 'FAILED to register: ' . __DIR__ . '/build/blocks/' . $block );
        }
    }
} );


/**
 * Register custom block category.
 */
function crpb_register_work_block_category( $categories ) {
    $custom_category = [
        'slug'  => 'work',
        'title' => __( 'Work CPT Blocks', 'work' ),
        'icon'  => null,
    ];

    // Prepend to the beginning of the list
    return array_merge( $categories, [ $custom_category ] );
}
add_filter( 'block_categories_all', 'crpb_register_work_block_category', 10, 2 );