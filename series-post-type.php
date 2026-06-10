<?php
/**
 * Plugin Name: Series Plugin
 * Description: Registers a CPT, custom fields, and custom blocks for Series
 * Version: 1.0
 * Author: Sara Pitt
 * Text Domain: crp-series
 * Domain Path: /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

load_plugin_textdomain( 'crp-series', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

require_once 'inc/custom-post-type.php';
require_once 'inc/custom-fields.php';