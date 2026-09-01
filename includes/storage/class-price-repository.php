<?php
/**
 * Price Repository – single point of read/write for 'jprm_price'.
 *
 * Canonical meta key: 'jprm_price' (JSON string).
 * Legacy array values remain readable; new writes are normalized JSON.
 */

namespace JelloPoint\RestaurantMenu\Storage;

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
		$cfg = Price_Schema::from_post( $post_id );
		return is_array( $cfg ) ? $cfg : null;
	}

	/**
	 * Persist a normalized price config to post meta as canonical JSON.
	 */
	public static function set( int $post_id, array $cfg ) : bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		$cfg = Price_Schema::normalize( $cfg );
		if ( empty( $cfg ) ) {
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
