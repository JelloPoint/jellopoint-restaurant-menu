
<?php
/* Minimal bootstrap for Menu Item admin fixes.
 * NOTE: This does NOT change admin menus or CPTs.
 * It only loads a small JS shim on the jprm_menu_item edit screen.
 */
if ( defined('ABSPATH') ) {
    $shim = __DIR__ . '/includes/jprm-menuitem-admin-shim.php';
    if ( file_exists( $shim ) ) {
        require_once $shim;
    }
}
