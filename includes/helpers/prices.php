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

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Return the complete label map used for price badges/labels.
 * Source: option 'jprm_price_labels_v2' via JPRM_Labels_Store::all()
 */
if ( ! function_exists( 'jprm_build_label_map' ) ) {
	function jprm_build_label_map() : array {
		if ( class_exists( '\JPRM_Labels_Store' ) ) {
			$all = \JPRM_Labels_Store::all();
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
 * Map ONLY the widget controls we actually support in the renderer.
 *
 * @param int    $post_id
 * @param string $label_presentation 'text' | 'icon' | 'icon_text'
 * @param string $label_position     'left' | 'right'
 * @param array  $label_map          (unused here; kept for signature stability)
 * @param array  $currency_opts      (unused here; renderer outputs stored string)
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
			'presentation' => in_array( $label_presentation, [ 'text', 'icon', 'icon_text' ], true )
				? $label_presentation
				: 'icon_text',
			'order_class'  => ( $label_position === 'left' )
				? 'jp-order--label-left'
				: 'jp-order--label-right',
		];

		// Renderer reads & normalizes via Price_Schema internally.
		$html = Price_Renderer::render_from_meta( $post_id, $opts );
		return is_string( $html ) ? $html : '';
	}
}
