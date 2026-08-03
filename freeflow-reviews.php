<?php
/**
 * Plugin Name:       Freer Reviews
 * Plugin URI:        https://jacobfrees.com
 * Description:       Collect 5-star reviews on-site, sync Google Business reviews, and follow up by email (thank-you + review request) with a hook layer any mailing plugin can latch onto.
 * Version:           1.2.0
 * Author:            Jacob Frees
 * Author URI:        https://jacobfrees.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       freeflow-reviews
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FFR_VERSION', '1.2.0' );
define( 'FFR_FILE', __FILE__ );
define( 'FFR_PATH', plugin_dir_path( __FILE__ ) );
define( 'FFR_URL', plugin_dir_url( __FILE__ ) );
define( 'FFR_CPT', 'ffr_review' );

require_once FFR_PATH . 'includes/class-ffr-post-type.php';
require_once FFR_PATH . 'includes/class-ffr-target.php';
require_once FFR_PATH . 'includes/class-ffr-admin.php';
require_once FFR_PATH . 'includes/class-ffr-form.php';
require_once FFR_PATH . 'includes/class-ffr-display.php';
require_once FFR_PATH . 'includes/class-ffr-email.php';
require_once FFR_PATH . 'includes/class-ffr-google.php';
require_once FFR_PATH . 'includes/class-ffr-schema.php';
require_once FFR_PATH . 'includes/class-ffr-update.php';

/**
 * Fired on activation: register CPT + default settings, flush rewrite rules.
 */
function ffr_activate() {
	FFR_Target::register();
	FFR_Post_Type::register();
	flush_rewrite_rules();
	if ( false === get_option( 'ffr_settings' ) ) {
		update_option( 'ffr_settings', FFR_Admin::defaults() );
	}
}
register_activation_hook( __FILE__, 'ffr_activate' );

function ffr_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ffr_deactivate' );

/**
 * Bootstrap everything on init/plugins_loaded.
 */
add_action( 'init', 'ffr_init' );
function ffr_init() {
	FFR_Target::register();
	FFR_Post_Type::register();
	FFR_Form::init();
	FFR_Display::init();
	FFR_Google::init();
	FFR_Schema::init();
	FFR_Update::init();
}

add_action( 'plugins_loaded', 'ffr_plugins_loaded' );
function ffr_plugins_loaded() {
	FFR_Email::init();
}

if ( is_admin() ) {
	add_action( 'admin_menu', array( 'FFR_Admin', 'menu' ) );
	add_action( 'admin_init', array( 'FFR_Admin', 'register_settings' ) );
	add_action( 'admin_init', array( 'FFR_Admin', 'handle_actions' ) );
	add_filter( 'manage_' . FFR_CPT . '_posts_columns', array( 'FFR_Admin', 'columns' ) );
	add_action( 'manage_' . FFR_CPT . '_posts_custom_column', array( 'FFR_Admin', 'column_content' ), 10, 2 );
	add_filter( 'display_post_states', '__return_empty_array' );
}

/**
 * Uninstall cleanup.
 */
function ffr_uninstall() {
	$posts = get_posts( array( 'post_type' => FFR_CPT, 'numberposts' => -1, 'post_status' => 'any' ) );
	foreach ( $posts as $p ) {
		wp_delete_post( $p->ID, true );
	}
	delete_option( 'ffr_settings' );
}
register_uninstall_hook( __FILE__, 'ffr_uninstall' );
