<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RC_Database {

	const TABLE = 'rc_redirects';

	/**
	 * Create the custom table on activation.
	 */
	public static function create_table() {
		global $wpdb;

		$table   = $wpdb->prefix . self::TABLE;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id              INT          NOT NULL AUTO_INCREMENT,
			category_slug   VARCHAR(200) NOT NULL DEFAULT '',
			destination_url VARCHAR(500) NOT NULL DEFAULT '',
			redirect_code   SMALLINT     NOT NULL DEFAULT 301,
			enabled         TINYINT(1)   NOT NULL DEFAULT 1,
			auto_detected   TINYINT(1)   NOT NULL DEFAULT 0,
			match_status    VARCHAR(20)  NOT NULL DEFAULT 'pending',
			created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY category_slug (category_slug)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Sync WordPress categories into the table (non-destructive).
	 * - Inserts new categories that aren't in the table yet.
	 * - Re-runs matching for existing rows that still have no destination URL.
	 *
	 * @return array { inserted: int, rematched: int }
	 */
	public static function sync_categories() {
		global $wpdb;

		$categories = get_categories( array( 'hide_empty' => false ) );
		$inserted   = 0;
		$rematched  = 0;
		$table      = $wpdb->prefix . self::TABLE;

		foreach ( $categories as $cat ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, destination_url FROM {$table} WHERE category_slug = %s LIMIT 1",
					$cat->slug
				)
			);

			if ( ! $existing ) {
				// Brand new category — insert with auto-match.
				$match = RC_Matcher::find_best_match( $cat );
				self::insert( array(
					'category_slug'   => $cat->slug,
					'destination_url' => $match['url'],
					'redirect_code'   => 301,
					'enabled'         => 1,
					'auto_detected'   => 1,
					'match_status'    => $match['match_status'],
				) );
				$inserted++;

			} elseif ( empty( $existing->destination_url ) ) {
				// Already in table but never got a URL — re-run matching now.
				$match = RC_Matcher::find_best_match( $cat );
				if ( ! empty( $match['url'] ) ) {
					$wpdb->update(
						$table,
						array(
							'destination_url' => $match['url'],
							'match_status'    => $match['match_status'],
							'enabled'         => 1,
						),
						array( 'id' => $existing->id ),
						array( '%s', '%s', '%d' ),
						array( '%d' )
					);
					$rematched++;
				}
			}
			// If destination_url is already set, leave it untouched.
		}

		return array( 'inserted' => $inserted, 'rematched' => $rematched );
	}

	/**
	 * Get all redirect rows.
	 * Order: pending (no URL) first, then by slug.
	 */
	public static function get_all() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY (destination_url = '') DESC, category_slug ASC"
		);
	}

	/**
	 * Get a single enabled row by slug (used by the redirect handler).
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE category_slug = %s AND enabled = 1 AND destination_url != ''
				 LIMIT 1",
				$slug
			)
		);
	}

	/**
	 * Get a single row by ID (for the edit form).
	 */
	public static function get_by_id( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) )
		);
	}

	/**
	 * Insert a new redirect row.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$valid_statuses = array( 'pending', 'suggested', 'auto_matched', 'manual' );

		return $wpdb->insert(
			$wpdb->prefix . self::TABLE,
			array(
				'category_slug'   => sanitize_title( $data['category_slug'] ),
				'destination_url' => esc_url_raw( $data['destination_url'] ?? '' ),
				'redirect_code'   => in_array( (int) ( $data['redirect_code'] ?? 301 ), array( 301, 302 ), true ) ? (int) $data['redirect_code'] : 301,
				'enabled'         => isset( $data['enabled'] ) ? (int) (bool) $data['enabled'] : 1,
				'auto_detected'   => isset( $data['auto_detected'] ) ? (int) (bool) $data['auto_detected'] : 0,
				'match_status'    => in_array( $data['match_status'] ?? 'pending', $valid_statuses, true ) ? $data['match_status'] : 'pending',
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Update an existing redirect row.
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$valid_statuses = array( 'pending', 'suggested', 'auto_matched', 'manual' );

		return $wpdb->update(
			$wpdb->prefix . self::TABLE,
			array(
				'category_slug'   => sanitize_title( $data['category_slug'] ),
				'destination_url' => esc_url_raw( $data['destination_url'] ?? '' ),
				'redirect_code'   => in_array( (int) ( $data['redirect_code'] ?? 301 ), array( 301, 302 ), true ) ? (int) $data['redirect_code'] : 301,
				'enabled'         => isset( $data['enabled'] ) ? (int) (bool) $data['enabled'] : 1,
				'match_status'    => in_array( $data['match_status'] ?? 'manual', $valid_statuses, true ) ? $data['match_status'] : 'manual',
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a redirect row by ID.
	 */
	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete(
			$wpdb->prefix . self::TABLE,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	/**
	 * Check if a slug already exists, optionally excluding a given row ID.
	 */
	public static function slug_exists( $slug, $exclude_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE category_slug = %s AND id != %d",
				sanitize_title( $slug ),
				absint( $exclude_id )
			)
		);
		return (int) $count > 0;
	}
}
