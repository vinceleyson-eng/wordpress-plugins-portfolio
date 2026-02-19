<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RC_Redirects {

	/**
	 * Hooked to `template_redirect`.
	 * If the current page is a category archive with a matching enabled redirect, fire the 301/302.
	 */
	public static function maybe_redirect() {
		if ( ! is_category() ) {
			return;
		}

		$queried = get_queried_object();
		if ( ! $queried || empty( $queried->slug ) ) {
			return;
		}

		$row = RC_Database::get_by_slug( $queried->slug );
		if ( ! $row ) {
			return;
		}

		$code = in_array( (int) $row->redirect_code, array( 301, 302 ), true ) ? (int) $row->redirect_code : 301;
		wp_redirect( $row->destination_url, $code );
		exit;
	}
}
