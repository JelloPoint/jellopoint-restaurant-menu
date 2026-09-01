<?php
/** Bundled, safely installable Dietary Badges and Price Labels. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'JPRM_Default_Data' ) ) :
class JPRM_Default_Data {
	/** Return the standard badge rows. */
	public static function badge_defaults() : array {
		$rows = [
			[ 'Vegan', 'vegan', 'leaf.svg' ],
			[ 'Vegetarian', 'vegetarian', 'vegetarian.svg' ],
			[ 'Gluten-Free', 'gluten-free', 'gluten-free.svg' ],
			[ 'Dairy-Free', 'dairy-free', 'dairy-free.svg' ],
			[ 'Spicy', 'spicy', 'spicy.svg' ],
			[ 'Contains Nuts', 'contains-nuts', 'nuts.svg' ],
			[ 'Contains Alcohol', 'contains-alcohol', 'glass.svg' ],
			[ 'Halal', 'halal', 'halal.svg' ],
		];

		return array_map( static function( array $row, int $order ) : array {
			return [
				'name' => $row[0], 'slug' => $row[1], 'icon_id' => 0,
				'icon_url' => self::icon_url( $row[2] ), 'active' => true, 'order' => $order,
			];
		}, $rows, array_keys( $rows ) );
	}

	/** Return the standard price-label rows. */
	public static function label_defaults() : array {
		$rows = [
			[ 'Glass', 'glass', 'glass.svg' ],
			[ 'Bottle', 'bottle', 'bottle.svg' ],
			[ '250 ml', '250-ml', 'measure.svg' ],
			[ '330 ml', '330-ml', 'measure.svg' ],
			[ '500 ml', '500-ml', 'measure.svg' ],
			[ '750 ml', '750-ml', 'bottle.svg' ],
			[ 'Per person', 'per-person', 'person.svg' ],
			[ 'Supplement', 'supplement', 'plus.svg' ],
		];

		return array_map( static function( array $row, int $order ) : array {
			return [
				'id' => $row[1], 'slug' => $row[1], 'label' => $row[0], 'icon_id' => 0,
				'icon_url' => self::icon_url( $row[2] ), 'active' => true, 'order' => $order,
			];
		}, $rows, array_keys( $rows ) );
	}

	/** Merge defaults without replacing user-controlled values. */
	public static function merge_rows( array $existing, array $defaults ) : array {
		$added = 0;
		$icons_added = 0;
		$indexes = [];
		foreach ( $existing as $index => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$slug = sanitize_title( (string) ( $row['slug'] ?? $row['id'] ?? '' ) );
			if ( '' !== $slug ) { $indexes[ $slug ] = $index; }
		}

		foreach ( $defaults as $default ) {
			$slug = sanitize_title( (string) ( $default['slug'] ?? '' ) );
			if ( isset( $indexes[ $slug ] ) ) {
				$index = $indexes[ $slug ];
				$icon_id = (int) ( $existing[ $index ]['icon_id'] ?? 0 );
				$icon_url = (string) ( $existing[ $index ]['icon_url'] ?? '' );
				if ( $icon_id <= 0 && '' === $icon_url ) {
					$existing[ $index ]['icon_url'] = (string) $default['icon_url'];
					++$icons_added;
				}
				continue;
			}

			$default['order'] = count( $existing );
			$existing[] = $default;
			$indexes[ $slug ] = count( $existing ) - 1;
			++$added;
		}

		return [ 'rows' => array_values( $existing ), 'added' => $added, 'icons_added' => $icons_added ];
	}

	/** Install missing defaults and return a short result report. */
	public static function install_missing() : array {
		$badges = get_option( 'jprm_dietary_badges', [] );
		$labels = get_option( 'jprm_price_labels_v2', [] );
		$badges = is_array( $badges ) ? $badges : [];
		$labels = is_array( $labels ) ? $labels : [];

		$badge_result = self::merge_rows( $badges, self::badge_defaults() );
		$label_result = self::merge_rows( $labels, self::label_defaults() );
		update_option( 'jprm_dietary_badges', $badge_result['rows'] );
		update_option( 'jprm_price_labels_v2', $label_result['rows'] );

		return [
			'badges_added' => $badge_result['added'], 'badge_icons_added' => $badge_result['icons_added'],
			'labels_added' => $label_result['added'], 'label_icons_added' => $label_result['icons_added'],
		];
	}

	private static function icon_url( string $file ) : string {
		return trailingslashit( JPRM_PLUGIN_URL ) . 'assets/icons/defaults/' . $file;
	}
}
endif;
