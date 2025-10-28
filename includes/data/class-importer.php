<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class JPRM_Importer {

	/**
	 * Parse and (optionally) commit an import. (Stub)
	 *
	 * @param array $file Array from $_FILES['...']
	 * @param array $opts Options (dry_run, create_missing_terms, attach_images)
	 * @return array Report with messages for the admin UI.
	 */
	public static function run( array $file, array $opts = [] ): array {
		$report = [
			'dry_run' => ! empty( $opts['dry_run'] ),
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'warnings'=> [],
			'errors'  => [],
		];

		// TODO: detect CSV vs JSON, delegate to parsers, then validate/map.

		$report['warnings'][] = 'Importer not yet implemented (stub).';
		return $report;
	}
}
