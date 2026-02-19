<?php
/**
 * Plugin Name: Redirect Categories
 * Description: 301 redirect WordPress category archive pages to custom destination URLs, with an admin dashboard to manage and auto-detect category redirects.
 * Version:     1.0.0
 * Author:      BidView
 * License:     GPL-2.0+
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RC_VERSION',     '1.0.1' );
define( 'RC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'RC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'RC_PLUGIN_FILE', __FILE__ );

/**
 * Load plugin files.
 */
function rc_load_files() {
	$files = array(
		'includes/class-rc-matcher.php',
		'includes/class-rc-database.php',
		'includes/class-rc-redirects.php',
		'admin/class-rc-admin.php',
	);
	foreach ( $files as $file ) {
		$path = RC_PLUGIN_DIR . $file;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
rc_load_files();

/**
 * Activation: create DB table and set a flag so the first admin page load
 * triggers a full sync (including matcher). Matching via get_permalink() is
 * unreliable inside register_activation_hook's limited WP context.
 */
function rc_activate() {
	RC_Database::create_table();
	update_option( 'rc_needs_initial_sync', 1 );
}
register_activation_hook( RC_PLUGIN_FILE, 'rc_activate' );

/**
 * On every admin load, apply any pending schema changes (dbDelta handles new columns safely).
 * Also runs the initial category sync on first load after activation.
 */
function rc_maybe_upgrade() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Run dbDelta whenever the stored schema version is behind the plugin version.
	$stored_version = get_option( 'rc_db_version', '0' );
	if ( version_compare( $stored_version, RC_VERSION, '<' ) ) {
		RC_Database::create_table(); // dbDelta is idempotent — adds missing columns, never drops.
		update_option( 'rc_db_version', RC_VERSION );
	}

	// First-load sync after activation.
	if ( get_option( 'rc_needs_initial_sync' ) ) {
		delete_option( 'rc_needs_initial_sync' );
		RC_Database::sync_categories();
	}
}
add_action( 'admin_init', 'rc_maybe_upgrade' );

/**
 * Boot frontend redirect handler.
 */
add_action( 'template_redirect', array( 'RC_Redirects', 'maybe_redirect' ) );

/**
 * Boot admin.
 */
if ( is_admin() ) {
	add_action( 'init', array( 'RC_Admin', 'init' ) );
}
