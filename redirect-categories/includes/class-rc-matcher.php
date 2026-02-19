<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RC_Matcher
 *
 * Finds the best destination page for a category using a two-pass strategy:
 *   Pass 1 — Slug/title scoring against all published pages + CPTs.
 *   Pass 2 — WordPress full-text search fallback when scoring finds nothing.
 */
class RC_Matcher {

	/** Post slugs/paths to never use as a redirect destination. */
	const SKIP_SLUGS = array(
		'wp-login', 'wp-admin', 'privacy-policy', 'terms-of-service',
		'cart', 'checkout', 'my-account', 'sitemap', 'robots',
		'wp-signup', 'wp-activate', 'sample-page',
	);

	/** Stop words stripped before comparison. */
	const STOP_WORDS = array(
		'and', 'or', 'the', 'a', 'an', 'of', 'in', 'on', 'at',
		'to', 'for', 'is', 'are', 'was', 'were', 'be', 'been',
	);

	/**
	 * Find the best matching published page/CPT for a given category.
	 *
	 * Strategy:
	 *   1. Score all published pages + CPTs (slug/title/path signals).
	 *   2. If best score >= 1, return that result.
	 *   3. Otherwise fall back to WP keyword search using the category name.
	 *
	 * @param WP_Term $category
	 * @return array { url: string, title: string, score: int, match_status: string }
	 */
	public static function find_best_match( $category ) {
		// Pass 1: slug/title scoring.
		$result = self::score_candidates( $category );

		if ( ! empty( $result['url'] ) ) {
			return $result;
		}

		// Pass 2: full-text keyword search fallback.
		return self::search_fallback( $category );
	}

	// -------------------------------------------------------------------------
	// Pass 1 — scoring
	// -------------------------------------------------------------------------

	private static function score_candidates( $category ) {
		$posts = self::get_candidate_posts();

		if ( empty( $posts ) ) {
			return array( 'url' => '', 'title' => '', 'score' => 0, 'match_status' => 'pending' );
		}

		$best_score = 0;
		$best_post  = null;

		foreach ( $posts as $post ) {
			$score = self::score_post( $category, $post );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_post  = $post;
			}
		}

		if ( ! $best_post || $best_score < 1 ) {
			return array( 'url' => '', 'title' => '', 'score' => 0, 'match_status' => 'pending' );
		}

		return array(
			'url'          => get_permalink( $best_post->ID ),
			'title'        => $best_post->post_title,
			'score'        => $best_score,
			'match_status' => $best_score >= 4 ? 'auto_matched' : 'suggested',
		);
	}

	/**
	 * Score a single post against a category.
	 */
	public static function score_post( $category, $post ) {
		$cat_slug  = strtolower( $category->slug );
		$cat_name  = strtolower( $category->name );
		$cat_words = self::tokenize( $cat_name . ' ' . $cat_slug );

		$post_slug  = strtolower( $post->post_name );
		$post_title = strtolower( $post->post_title );
		$post_path  = strtolower( get_page_uri( $post->ID ) );

		// Skip blocklisted pages.
		foreach ( self::SKIP_SLUGS as $skip ) {
			if ( strpos( $post_path, $skip ) !== false ) {
				return 0;
			}
		}

		$score = 0;

		// Exact slug match (+5).
		if ( $cat_slug === $post_slug ) {
			$score += 5;
		}

		// Category slug substring of post path (+3).
		if ( $cat_slug !== $post_slug && strpos( $post_path, $cat_slug ) !== false ) {
			$score += 3;
		}

		// Category name words in post title (+2 each).
		foreach ( $cat_words as $word ) {
			if ( strlen( $word ) > 2 && strpos( $post_title, $word ) !== false ) {
				$score += 2;
			}
		}

		// Category slug words in post slug (+2 each).
		foreach ( self::tokenize( $cat_slug ) as $word ) {
			if ( strlen( $word ) > 2 && strpos( $post_slug, $word ) !== false ) {
				$score += 2;
			}
		}

		// Category words in post URL path (+1 each).
		foreach ( $cat_words as $word ) {
			if ( strlen( $word ) > 2 && strpos( $post_path, $word ) !== false ) {
				$score += 1;
			}
		}

		return $score;
	}

	// -------------------------------------------------------------------------
	// Pass 2 — WP keyword search fallback
	// -------------------------------------------------------------------------

	/**
	 * Use WordPress full-text search to find a page matching the category name.
	 * Tries the full category name first, then each individual word.
	 */
	private static function search_fallback( $category ) {
		$post_types = self::get_post_types();

		// Try the full category name, then individual words (longest first).
		$queries = array( $category->name );
		$words   = self::tokenize( $category->name . ' ' . $category->slug );
		usort( $words, fn( $a, $b ) => strlen( $b ) - strlen( $a ) );
		$queries = array_merge( $queries, $words );

		foreach ( $queries as $keyword ) {
			if ( strlen( $keyword ) < 3 ) {
				continue;
			}

			$results = get_posts( array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				's'              => $keyword,
				'numberposts'    => 5,
				'no_found_rows'  => true,
				'orderby'        => 'relevance',
			) );

			foreach ( $results as $post ) {
				$path = strtolower( get_page_uri( $post->ID ) );
				$skip = false;
				foreach ( self::SKIP_SLUGS as $s ) {
					if ( strpos( $path, $s ) !== false ) {
						$skip = true;
						break;
					}
				}
				if ( $skip ) {
					continue;
				}

				return array(
					'url'          => get_permalink( $post->ID ),
					'title'        => $post->post_title,
					'score'        => 1,
					'match_status' => 'suggested',
				);
			}
		}

		return array( 'url' => '', 'title' => '', 'score' => 0, 'match_status' => 'pending' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function get_candidate_posts() {
		return get_posts( array(
			'post_type'     => self::get_post_types(),
			'post_status'   => 'publish',
			'numberposts'   => -1,
			'orderby'       => 'title',
			'order'         => 'ASC',
			'no_found_rows' => true,
		) );
	}

	private static function get_post_types() {
		$cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
		return array_merge( array( 'page' ), array_values( $cpts ) );
	}

	private static function tokenize( $text ) {
		$text  = strtolower( $text );
		$text  = preg_replace( '/[^a-z0-9\s]/', ' ', $text );
		$words = preg_split( '/[\s\-_]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_diff( $words, self::STOP_WORDS ) );
	}
}
