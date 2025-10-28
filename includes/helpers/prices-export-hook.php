<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Normalize price rows for export.
 * Target shape per item:
 * [
 *   [ 'label_id'=>int|null, 'label_text'=>string, 'numeric'=>float|null, 'price'=>string ],
 *   ...
 * ]
 */
add_filter( 'jprm/export/prices', function( $prices, $post_id ) {

	// 1) If you already have a helper, use it.
	//    Option A: class-based helper
	if ( class_exists( '\JelloPoint\RestaurantMenu\Helpers\Prices' )
	     && method_exists( '\JelloPoint\RestaurantMenu\Helpers\Prices', 'for_export' ) ) {
		$out = \JelloPoint\RestaurantMenu\Helpers\Prices::for_export( $post_id );
		if ( is_array( $out ) ) { return $out; }
	}

	//    Option B: function-based helper
	if ( function_exists( 'jprm_prices_for_export' ) ) {
		$out = jprm_prices_for_export( $post_id );
		if ( is_array( $out ) ) { return $out; }
	}

	// 2) Best-effort fallback from common meta keys (read-only).
	$candidates = [ 'jprm_price_config', 'jprm_price_rows', 'jprm_prices' ];
	foreach ( $candidates as $key ) {
		$raw = get_post_meta( $post_id, $key, true );

		// If stored as JSON string, decode to array.
		if ( is_string( $raw ) && $raw !== '' ) {
			$maybe = json_decode( $raw, true );
			if ( is_array( $maybe ) ) { $raw = $maybe; }
		}

		if ( is_array( $raw ) && ! empty( $raw ) ) {
			$rows = [];
			foreach ( $raw as $row ) {
				if ( ! is_array( $row ) ) { continue; }

				$label_id   = null;
				$label_text = '';

				// Try common keys for label id/text.
				if ( isset( $row['label_id'] ) ) {
					$label_id = (int) $row['label_id'];
				} elseif ( isset( $row['label'] ) && is_numeric( $row['label'] ) ) {
					$label_id = (int) $row['label'];
				}

				if ( isset( $row['label_text'] ) ) {
					$label_text = (string) $row['label_text'];
				} elseif ( isset( $row['label'] ) && ! is_numeric( $row['label'] ) ) {
					$label_text = (string) $row['label'];
				}

				// Numeric price if available; otherwise try to parse from 'price' string.
				$numeric = null;
				if ( isset( $row['numeric'] ) && $row['numeric'] !== '' ) {
					$numeric = (float) $row['numeric'];
				} elseif ( isset( $row['price'] ) ) {
					$numeric = (float) preg_replace( '/[^\d\.,-]/', '', (string) $row['price'] );
					$numeric = (float) str_replace( ',', '.', (string) $numeric );
				}

				$price_str = '';
				if ( isset( $row['price'] ) ) {
					$price_str = (string) $row['price'];
				} elseif ( isset( $row['numeric'] ) ) {
					$price_str = (string) $row['numeric'];
				}

				$rows[] = [
					'label_id'   => $label_id,
					'label_text' => $label_text,
					'numeric'    => $numeric,
					'price'      => $price_str,
				];
			}
			return $rows;
		}
	}

	// 3) Nothing found — return empty array (exporter will still work).
	return [];
}, 10, 2 );
