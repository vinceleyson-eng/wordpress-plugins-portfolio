<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RC_Admin {

	private static $instance = null;

	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',            array( $this, 'add_menu' ) );
		add_action( 'admin_post_rc_add',     array( $this, 'handle_add' ) );
		add_action( 'admin_post_rc_update',  array( $this, 'handle_update' ) );
		add_action( 'admin_post_rc_delete',  array( $this, 'handle_delete' ) );
		add_action( 'admin_post_rc_sync',    array( $this, 'handle_sync' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'Category Redirects', 'redirect-categories' ),
			__( 'Category Redirects', 'redirect-categories' ),
			'manage_options',
			'rc-redirects',
			array( $this, 'render_page' ),
			'dashicons-randomize',
			80
		);
	}

	public function enqueue_styles( $hook ) {
		if ( 'toplevel_page_rc-redirects' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'rc-admin',
			RC_PLUGIN_URL . 'admin/admin.css',
			array(),
			RC_VERSION
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require_once RC_PLUGIN_DIR . 'admin/views/manage.php';
	}

	// -------------------------------------------------------------------------
	// Form handlers
	// -------------------------------------------------------------------------

	public function handle_add() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}
		check_admin_referer( 'rc_add_redirect' );

		$slug = sanitize_title( wp_unslash( $_POST['category_slug'] ?? '' ) );
		$url  = esc_url_raw( wp_unslash( $_POST['destination_url'] ?? '' ) );
		$code = in_array( (int) ( $_POST['redirect_code'] ?? 301 ), array( 301, 302 ), true ) ? (int) $_POST['redirect_code'] : 301;
		$enabled = isset( $_POST['enabled'] ) ? 1 : 0;

		if ( empty( $slug ) ) {
			$this->redirect_back( 'error', 'Category slug is required.' );
			return;
		}

		if ( RC_Database::slug_exists( $slug ) ) {
			$this->redirect_back( 'error', "A redirect for <strong>{$slug}</strong> already exists." );
			return;
		}

		RC_Database::insert( array(
			'category_slug'   => $slug,
			'destination_url' => $url,
			'redirect_code'   => $code,
			'enabled'         => $enabled,
			'auto_detected'   => 0,
			'match_status'    => 'manual',
		) );

		$this->redirect_back( 'success', 'Redirect added.' );
	}

	public function handle_update() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}
		check_admin_referer( 'rc_update_redirect' );

		$id   = absint( $_POST['id'] ?? 0 );
		$slug = sanitize_title( wp_unslash( $_POST['category_slug'] ?? '' ) );
		$url  = esc_url_raw( wp_unslash( $_POST['destination_url'] ?? '' ) );
		$code = in_array( (int) ( $_POST['redirect_code'] ?? 301 ), array( 301, 302 ), true ) ? (int) $_POST['redirect_code'] : 301;
		$enabled = isset( $_POST['enabled'] ) ? 1 : 0;

		if ( ! $id || empty( $slug ) ) {
			$this->redirect_back( 'error', 'Invalid request.' );
			return;
		}

		if ( RC_Database::slug_exists( $slug, $id ) ) {
			$this->redirect_back( 'error', "Another redirect for <strong>{$slug}</strong> already exists." );
			return;
		}

		RC_Database::update( $id, array(
			'category_slug'   => $slug,
			'destination_url' => $url,
			'redirect_code'   => $code,
			'enabled'         => $enabled,
			'match_status'    => 'manual',
		) );

		$this->redirect_back( 'success', 'Redirect updated.' );
	}

	public function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'rc_delete_' . $id );

		if ( $id ) {
			RC_Database::delete( $id );
		}

		$this->redirect_back( 'success', 'Redirect deleted.' );
	}

	public function handle_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}
		check_admin_referer( 'rc_sync' );

		$result    = RC_Database::sync_categories();
		$inserted  = (int) $result['inserted'];
		$rematched = (int) $result['rematched'];

		$parts = array();
		if ( $inserted ) {
			$parts[] = "{$inserted} new categor" . ( 1 === $inserted ? 'y' : 'ies' ) . ' added';
		}
		if ( $rematched ) {
			$parts[] = "{$rematched} pending categor" . ( 1 === $rematched ? 'y' : 'ies' ) . ' matched to a destination';
		}
		$msg = $parts
			? implode( ' and ', $parts ) . '.'
			: 'Everything is up to date — no new or unmatched categories found.';

		$this->redirect_back( 'success', $msg );
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private function redirect_back( $type, $message ) {
		wp_safe_redirect( add_query_arg(
			array(
				'page'         => 'rc-redirects',
				'rc_status'    => $type,
				'rc_message'   => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		) );
		exit;
	}
}
