<?php
/**
 * Price Repository – single point of read/write for 'jprm_price'.
 *
 * Canonical meta key: 'jprm_price' (JSON string).
 * Reads use the schema's public API (from_post).
 * Writes store the array as JSON verbatim (no missing sanitize_cfg).
 */

namespace JelloPoint\RestaurantMenu\Storage;

use JelloPoint\RestaurantMenu\Data\Price_Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Price_Repository {

	/** @var string */
	const META_KEY = 'jprm_price';

	/**
	 * Get normalized price config for a post.
	 * Delegates to Price_Schema::from_post() which already exists.
	 */
	public static function get( int $post_id ) : ?array {
		if ( $post_id <= 0 ) {
			return null;
		}
		// Schema takes care of reading + normalizing from meta.
		$cfg = Price_Schema::from_post( $post_id );
		return is_array( $cfg ) ? $cfg : null;
	}

	/**
	 * Persist a price config array to post meta as canonical JSON.
	 * No sanitize_cfg() call – it doesn't exist in Price_Schema.
	 * Assume upstream UI already validated shape.
	 */
	public static function set( int $post_id, array $cfg ) : bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		$json = wp_json_encode( $cfg );
		if ( false === $json ) {
			return false;
		}
		return (bool) update_post_meta( $post_id, self::META_KEY, $json );
	}

	/**
	 * Delete canonical price config.
	 */
	public static function delete( int $post_id ) : void {
		if ( $post_id > 0 ) {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}
}
