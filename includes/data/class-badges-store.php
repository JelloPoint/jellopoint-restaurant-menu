<?php
namespace JelloPoint\RestaurantMenu\Badges;

if ( ! defined('ABSPATH') ) exit;

/**
 * Canonical storage for Dietary Badges (admin list + renderer).
 * Uses option key that actually exists in your DB: jprm_dietary_badges_v1
 *
 * Row structure:
 * [
 *   'name'    => (string),
 *   'icon_id' => (int),
 *   'icon_url'=> (string),
 *   'active'  => (bool),
 *   'order'   => (int)
 * ]
 */
final class Store {
	const OPTION_KEY = 'jprm_dietary_badges_v1';

	public static function instance() : self {
		static $o = null;
		if ( ! $o ) { $o = new self(); }
		return $o;
	}

	/** Return rows as a normalized array (sorted by 'order'). */
	public function get_rows() : array {
		$rows = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $rows ) ) return [];

		// Normalize + sort
		$out = [];
		foreach ( $rows as $r ) {
			$out[] = [
				'name'     => isset($r['name']) ? (string)$r['name'] : '',
				'icon_id'  => isset($r['icon_id']) ? (int)$r['icon_id'] : 0,
				'icon_url' => isset($r['icon_url']) ? (string)$r['icon_url'] : '',
				'active'   => ! empty($r['active']),
				'order'    => isset($r['order']) ? (int)$r['order'] : 0,
			];
		}
		usort($out, fn($a,$b) => $a['order'] <=> $b['order']);
		return $out;
	}

	/** Save rows from admin screen. */
	public function save_rows( $rows ) : void {
		if ( ! is_array( $rows ) ) {
			update_option( self::OPTION_KEY, [] );
			return;
		}
		$clean = [];
		foreach ( $rows as $i => $r ) {
			$clean[] = [
				'name'     => sanitize_text_field( $r['name'] ?? '' ),
				'icon_id'  => (int) ( $r['icon_id'] ?? 0 ),
				'icon_url' => esc_url_raw( $r['icon_url'] ?? '' ),
				'active'   => ! empty( $r['active'] ),
				'order'    => (int) ( $r['order'] ?? (int)$i ),
			];
		}
		// Reindex to keep it tidy
		$clean = array_values( $clean );
		update_option( self::OPTION_KEY, $clean, false );
	}

	/** Blank row template for the admin "Add Row" button. */
	public function blank_row() : array {
		return [
			'name'     => '',
			'icon_id'  => 0,
			'icon_url' => '',
			'active'   => true,
			'order'    => 0,
		];
	}
}
