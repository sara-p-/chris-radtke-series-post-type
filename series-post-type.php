<?php
/**
 * Plugin Name: Project Post Type
 * Description: Registers a custom post type for Projects
 * Version: 1.0
 * Author: Sara Pitt
 * Text Domain: crp-project
 * Domain Path: /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

load_plugin_textdomain( 'crp-project', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

require_once 'inc/custom-post-type.php';
require_once 'inc/custom-fields.php';
// require_once 'inc/custom-tax-projects-editor.php';