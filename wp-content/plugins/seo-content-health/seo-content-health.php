<?php
/**
 * Plugin Name:       Content Health SEO
 * Plugin URI:        https://example.com/content-health-seo
 * Description:       A unique SEO plugin that combines meta titles, meta descriptions, image alt text, and image optimization into one unified "Content Health Score" — instead of treating SEO and image performance as separate problems.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL v2 or later
 * Text Domain:       content-health-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'SCH_VERSION', '1.0.0' );
define( 'SCH_PLUGIN_FILE', __FILE__ );
define( 'SCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SCH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Default plugin options. Stored under a single option key to keep
 * the options table tidy.
 */
function sch_default_options() {
	return array(
		'post_types'           => array( 'post', 'page' ),
		'title_min'            => 30,
		'title_max'            => 60,
		'desc_min'             => 70,
		'desc_max'             => 160,
		'ai_provider'          => 'none', // 'none' | 'anthropic'
		'ai_api_key'           => '',
		'auto_optimize_uploads'=> 1,
		'webp_quality'         => 75,
		'jpeg_quality'         => 82,
		'serve_webp_frontend'  => 1,
	);
}

function sch_get_options() {
	$saved = get_option( 'sch_options', array() );
	return wp_parse_args( $saved, sch_default_options() );
}

/**
 * Activation: set default options once.
 */
function sch_activate() {
	if ( false === get_option( 'sch_options' ) ) {
		add_option( 'sch_options', sch_default_options() );
	}
}
register_activation_hook( __FILE__, 'sch_activate' );

/**
 * Load plugin classes.
 */
function sch_includes() {
	require_once SCH_PLUGIN_DIR . 'includes/class-sch-meta-fields.php';
	require_once SCH_PLUGIN_DIR . 'includes/class-sch-image-alt.php';
	require_once SCH_PLUGIN_DIR . 'includes/class-sch-image-optimizer.php';
	require_once SCH_PLUGIN_DIR . 'includes/class-sch-health-score.php';
	require_once SCH_PLUGIN_DIR . 'includes/class-sch-ai-assist.php';
	require_once SCH_PLUGIN_DIR . 'includes/class-sch-admin.php';
}
add_action( 'plugins_loaded', 'sch_includes' );

/**
 * Boot the plugin — instantiate each module.
 */
function sch_boot() {
	new SCH_Meta_Fields();
	new SCH_Image_Alt();
	new SCH_Image_Optimizer();
	new SCH_Health_Score();
	new SCH_Admin();
}
add_action( 'plugins_loaded', 'sch_boot', 20 );
