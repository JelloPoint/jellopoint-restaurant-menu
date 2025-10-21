<?php
/**
 * Passive writer for Price Schema v3 – reads current admin fields and writes jprm_price.
 * Always rebuilds from admin fields on save (prevents stale prices).
 */
namespace JelloPoint\RestaurantMenu\Admin\Save;

use JelloPoint\RestaurantMenu\Storage\Price_Schema;
use JelloPoint\RestaurantMenu\Storage\Price_Repository;

// --- Ensure storage classes are loaded (Price_Schema + Price_Repository) ---
if ( ! class_exists( 'JelloPoint\\RestaurantMenu\\Storage\\Price_Schema' ) ) {
    // Adjust the path if your file lives elsewhere:
    $p = dirname( __DIR__, 2 ) . '/storage/class-price-schema.php';
    if ( is_readable( $p ) ) {
        require_once $p;
    }
}

if ( ! class_exists( 'JelloPoint\\RestaurantMenu\\Storage\\Price_Repository' ) ) {
    // Adjust the path if your file lives elsewhere:
    $p = dirname( __DIR__, 2 ) . '/storage/class-price-repository.php';
    if ( is_readable( $p ) ) {
        require_once $p;
    }
}

/*
 * If your existing classes use different names, uncomment ONE of the aliases below
 * and set the right-hand side to your actual class FQCN.
 */
// Example: class is JelloPoint\RestaurantMenu\Storage\Class_Price_Schema
// if ( ! class_exists( 'JelloPoint\\RestaurantMenu\\Storage\\Price_Schema' )
//      && class_exists( 'JelloPoint\\RestaurantMenu\\Storage\\Class_Price_Schema' ) ) {
//     class_alias(
//         'JelloPoint\\RestaurantMenu\\Storage\\Class_Price_Schema',
//         'JelloPoint\\RestaurantMenu\\Storage\\Price_Schema'
//     );
// }

// Example: repository class differs
// if ( ! class_exists( 'JelloPoint\\RestaurantMenu\\Storage\\Price_Repository' )
//      && class_exists( 'JelloPoint\\RestaurantMenu\\Storage\\Class_Price_Repository' ) ) {
//     class_alias(
//         'JelloPoint\\RestaurantMenu\\Storage\\Class_Price_Repository',
//         'JelloPoint\\RestaurantMenu\\Storage\\Price_Repository'
//     );
// }

// Optional one-line debug (remove after testing):
// if ( function_exists('error_log') ) {
//     error_log('[JPRM] Price_Schema loaded? ' . (class_exists('JelloPoint\\RestaurantMenu\\Storage\\Price_Schema') ? 'yes' : 'no') .
//               ' | Price_Repository loaded? ' . (class_exists('JelloPoint\\RestaurantMenu\\Storage\\Price_Repository') ? 'yes' : 'no'));
// }


if ( ! defined('ABSPATH') ) exit;

class MenuItem_V3_Writer {

    const CPT = 'jprm_menu_item';

    public static function init() : void {
        // run after admin meta saves
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'write_v3' ], 50, 2 );
    }

    public static function write_v3( $post_id, $post ) : void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $post_id = (int) $post_id;

        // Determine mode from admin fields (defaults to single)
        $mode = get_post_meta( $post_id, 'jprm_price_mode', true );
        $mode = ($mode === 'multi') ? 'multi' : 'single';

        if ( $mode === 'single' ) {
            // Read single fields
            $amount = Price_Schema::sanitize_price_string( get_post_meta( $post_id, 'jprm_price_amount', true ) );

            $lm   = get_post_meta( $post_id, 'jprm_price_label_mode', true );
            $lm   = ($lm === 'custom') ? 'custom' : 'ref';
            $ref  = get_post_meta( $post_id, 'jprm_price_label_ref', true );
            $cust = get_post_meta( $post_id, 'jprm_price_label_custom', true );
            $icon = (int) get_post_meta( $post_id, 'jprm_price_label_icon_id', true );

            if ( $amount === '' ) { Price_Repository::delete( $post_id ); return; }

            $cfg = Price_Schema::normalize_single( [
                'price'     => $amount,
                'label_ref' => ($lm === 'custom') ? (string)$cust : (string)$ref,
                'icon_id'   => $icon,
                'hide_icon' => false,
            ] );

            // Write canonical meta (always overwrite to avoid stale values)
            Price_Repository::set( $post_id, $cfg );
            return;
        }

        // MULTI mode – read rows JSON from admin UI
        $rows_raw = get_post_meta( $post_id, 'jprm_prices', true );
        $rows = [];
        if ( is_string($rows_raw) && $rows_raw !== '' ) {
            $tmp = json_decode( $rows_raw, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array($tmp) ) $rows = $tmp;
        } elseif ( is_array($rows_raw) ) {
            $rows = $rows_raw;
        }

        $norm_rows = [];
        foreach ( $rows as $r ) {
            if ( ! is_array($r) ) continue;
            if ( empty($r['enabled']) ) continue;

            $label_mode = ( ($r['label_mode'] ?? 'ref') === 'custom' ) ? 'custom' : 'ref';
            $label_ref  = (string) ( $label_mode === 'custom' ? ( $r['label_custom'] ?? '' ) : ( $r['label_ref'] ?? '' ) );
            $icon_id    = isset($r['icon_id']) ? (int)$r['icon_id'] : 0;
            $amount     = Price_Schema::sanitize_price_string( $r['amount'] ?? '' );
            $hide_icon  = ! empty( $r['hide_icon'] );

            if ( $amount === '' ) continue;

            $norm_rows[] = [
                'label_ref' => $label_ref,
                'value'     => $amount,
                'icon_id'   => $icon_id,
                'hide_icon' => $hide_icon,
            ];
        }

        if ( empty( $norm_rows ) ) {
            // No valid rows – remove canonical meta
            Price_Repository::delete( $post_id );
            return;
        }

        $cfg = Price_Schema::normalize_multi( $norm_rows );
        // Always overwrite to reflect latest admin state
        Price_Repository::set( $post_id, $cfg );
    }
}
MenuItem_V3_Writer::init();
