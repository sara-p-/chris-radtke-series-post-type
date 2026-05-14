<?php
/**
 * Plugin Name: Pricing Package Post Type
 * Description: Registers a custom post type for Pricing Packages
 * Version: 1.0
 * Author: Sara Pitt
 * Text Domain: willow-pricing-package
 * Domain Path: /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

load_plugin_textdomain( 'willow-pricing-package', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

require_once 'inc/custom-post-type.php';
require_once 'inc/custom-fields.php';