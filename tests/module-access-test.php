<?php
define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/modules/class-module-catalog.php';
require_once dirname( __DIR__ ) . '/includes/modules/class-module-access.php';
$build = true; $entitled = true;
function jprm_fs() { global $build, $entitled; return new class( $build, $entitled ) { private $build; private $entitled; public function __construct( $build, $entitled ) { $this->build = $build; $this->entitled = $entitled; } public function is_premium() { return $this->build; } public function can_use_premium_code() { return $this->entitled; } }; }
function access_check( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); } }
use JelloPoint\RestaurantMenu\Modules\Module_Access;
access_check( Module_Access::allows( 'multiple_prices' ), 'Free module denied.' );
access_check( Module_Access::allows( 'print_pdf' ), 'Active Pro license denied.' );
$entitled = false; access_check( ! Module_Access::allows( 'print_pdf' ), 'Unlicensed Pro module allowed.' );
$entitled = true; $build = false; access_check( ! Module_Access::allows( 'print_pdf' ), 'Free build contains Pro access.' );
echo "Module access matrix checks passed.\n";
