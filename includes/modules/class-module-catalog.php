<?php
namespace JelloPoint\RestaurantMenu\Modules;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Product ownership only. This catalog does not grant or verify a license. */
final class Module_Catalog {
	public static function all() : array {
		return [
			'menu_core' => [ 'tier' => 'free', 'dependencies' => [] ],
			'multiple_prices' => [ 'tier' => 'pro', 'dependencies' => [ 'menu_core' ] ],
			'info_blocks' => [ 'tier' => 'free', 'dependencies' => [ 'menu_core' ] ],
			'daily_weekly_menus' => [ 'tier' => 'pro', 'dependencies' => [ 'menu_core' ] ],
			'print_pdf' => [ 'tier' => 'pro', 'dependencies' => [ 'menu_core', 'multiple_prices', 'info_blocks' ] ],
			'import_export' => [ 'tier' => 'pro', 'dependencies' => [ 'menu_core', 'multiple_prices' ] ],
		];
	}

	public static function tier( string $module ) : ?string {
		return self::all()[ $module ]['tier'] ?? null;
	}

	public static function is_pro( string $module ) : bool {
		return 'pro' === self::tier( $module );
	}
}
