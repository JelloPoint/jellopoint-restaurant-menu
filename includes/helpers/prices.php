<?php
/**
 * Thin, global function wrappers so the widget can call a stable API.
 * Delegates to your existing classes:
 *  - JPRM_Labels_Store (global namespace)
 *  - JelloPoint\RestaurantMenu\Storage\Price_Repository
 *  - JelloPoint\RestaurantMenu\Render\Price_Renderer
 */

use JelloPoint\RestaurantMenu\Render\Price_Renderer;
use JelloPoint\RestaurantMenu\Storage\Price_Repository;

/**
 * Return the complete label map used for price badges/labels.
 * Source: option 'jprm_price_labels_v2' via JPRM_Labels_Store::all()
 */
if ( ! function_exists( 'jprm_build_label_map' ) ) {
	function jprm_build_label_map() : array {
		if ( class_exists( '\JPRM_Labels_Store' ) ) {
			$all = \JPRM_Labels_Store::all(); // returns array as defined by your store
			return is_array( $all ) ? $all : [];
		}
		return [];
	}
}

/**
 * Read the canonical price config for a post from meta 'jprm_price' (JSON v3).
 * Source: Price_Repository::get( $post_id )
 */
if ( ! function_exists( 'jprm_read_price_config' ) ) {
	function jprm_read_price_config( int $post_id ) : array {
		$cfg = Price_Repository::get( $post_id );
		return is_array( $cfg ) ? $cfg : [];
	}
}

/**
 * Render the price group HTML for a given post using your renderer.
 * We pass through the widget’s options verbatim so the renderer can use them.
 */
if ( ! function_exists( 'jprm_render_pricegroup_html' ) ) {
	function jprm_render_pricegroup_html(
		int $post_id,
		string $label_presentation,
		string $label_position,
		array $label_map,
		array $currency_opts
	) : string {
		$opts = [
			'label_presentation' => $label_presentation, // 'text' | 'icon' | 'icon_text'
			'label_position'     => $label_position,     // 'left' | 'right'
			'label_map'          => $label_map,          // output of jprm_build_label_map()
			'currency'           => $currency_opts,      // ['show'=>bool,'symbol'=>string,'position'=>'before|after','spacing'=>'none|thin|normal']
		];

		// Your renderer supports rendering directly from post meta (jprm_price).
		$html = Price_Renderer::render_from_meta( $post_id, $opts );
		return is_string( $html ) ? $html : '';
	}
}
