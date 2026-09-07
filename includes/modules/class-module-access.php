<?php
namespace JelloPoint\RestaurantMenu\Modules;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** License access is independent of WordPress roles/nonces and never changes data. */
final class Module_Access {
	public static function allows( string $module ) : bool {
		$tier = Module_Catalog::tier( $module );
		if ( 'free' === $tier ) { return true; }
		if ( 'pro' !== $tier || ! function_exists( 'jprm_fs' ) ) { return false; }
		$sdk = \jprm_fs();
		// Do not use is_paying(): expired non-blocking licenses retain features.
		return is_object( $sdk ) && $sdk->is_premium() && $sdk->can_use_premium_code();
	}

	public static function require_access( string $module ) : void {
		if ( ! self::allows( $module ) ) {
			wp_die( esc_html__( 'This feature requires a Pro license. Your saved data has been retained.', 'jellopoint-restaurant-menu' ), '', [ 'response' => 403 ] );
		}
	}
}
