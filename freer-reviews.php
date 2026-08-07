<?php
/**
 * Plugin Name:       Freer Reviews
 * Plugin URI:        https://jacobfrees.com
 * Description:       Collect 5-star reviews on-site, sync Google Business reviews, and follow up by email (thank-you + review request) with a hook layer any mailing plugin can latch onto.
 * Version:           1.7.0
 * Author:            Jacob Frees
 * Author URI:        https://jacobfrees.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       freer-reviews
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FREER_REVIEWS_VERSION', '1.7.0' );
define( 'FREER_REVIEWS_FILE', __FILE__ );
define( 'FREER_REVIEWS_PATH', plugin_dir_path( __FILE__ ) );
define( 'FREER_REVIEWS_URL', plugin_dir_url( __FILE__ ) );
define( 'FREER_REVIEWS_CPT', 'freer_reviews_review' );

require_once FREER_REVIEWS_PATH . 'includes/class-freer-reviews-post-type.php';
require_once FREER_REVIEWS_PATH . 'includes/class-freer-reviews-target.php';
require_once FREER_REVIEWS_PATH . 'includes/class-freer-reviews-antispam.php';
require_once FREER_REVIEWS_PATH . 'includes/class-freer-reviews-admin.php';
require_once FREER_REVIEWS_PATH . 'includes/class-freer-reviews-form.php';
require_once FREER_REVIEWS_PATH . 'includes/class-freer-reviews-display.php';
require_once FREER_REVIEWS_PATH . 'includes/class-freer-reviews-email.php';
require_once FREER_REVIEWS_PATH . 'includes/class-freer-reviews-google.php';
require_once FREER_REVIEWS_PATH . 'includes/class-freer-reviews-schema.php';
require_once FREER_REVIEWS_PATH . 'includes/class-freer-reviews-update.php';

/**
 * Fired on activation: register CPT + default settings, flush rewrite rules.
 */
function freer_reviews_activate() {
	Freer_Reviews_Target::register();
	Freer_Reviews_Post_Type::register();
	flush_rewrite_rules();
	if ( false === get_option( 'freer_reviews_settings' ) ) {
		update_option( 'freer_reviews_settings', Freer_Reviews_Admin::defaults() );
	}
}
register_activation_hook( __FILE__, 'freer_reviews_activate' );

function freer_reviews_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'freer_reviews_deactivate' );

/**
 * Bootstrap everything on init/plugins_loaded.
 */
add_action( 'init', 'freer_reviews_init' );
function freer_reviews_init() {
	Freer_Reviews_Target::register();
	Freer_Reviews_Post_Type::register();
	Freer_Reviews_Antispam::comments_init();
	Freer_Reviews_Form::init();
	Freer_Reviews_Display::init();
	Freer_Reviews_Google::init();
	Freer_Reviews_Schema::init();
	Freer_Reviews_Update::init();
}

add_action( 'plugins_loaded', 'freer_reviews_plugins_loaded' );
function freer_reviews_plugins_loaded() {
	Freer_Reviews_Email::init();
}

if ( is_admin() ) {
	add_action( 'admin_menu', array( 'Freer_Reviews_Admin', 'menu' ) );
	add_action( 'admin_init', array( 'Freer_Reviews_Admin', 'register_settings' ) );
	add_action( 'admin_init', array( 'Freer_Reviews_Admin', 'handle_actions' ) );
	add_action( 'admin_enqueue_scripts', array( 'Freer_Reviews_Admin', 'admin_assets' ) );
	add_filter( 'manage_' . FREER_REVIEWS_CPT . '_posts_columns', array( 'Freer_Reviews_Admin', 'columns' ) );
	add_action( 'manage_' . FREER_REVIEWS_CPT . '_posts_custom_column', array( 'Freer_Reviews_Admin', 'column_content' ), 10, 2 );
	add_filter( 'display_post_states', '__return_empty_array' );
}

/**
 * Uninstall cleanup.
 */
function freer_reviews_uninstall() {
	$posts = get_posts( array( 'post_type' => FREER_REVIEWS_CPT, 'numberposts' => -1, 'post_status' => 'any' ) );
	foreach ( $posts as $p ) {
		wp_delete_post( $p->ID, true );
	}
	delete_option( 'freer_reviews_settings' );
}
register_uninstall_hook( __FILE__, 'freer_reviews_uninstall' );
