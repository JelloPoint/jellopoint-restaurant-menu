<?php
/** Build audited, separate editions. Run from the repository: php tools/build-packages.php */
declare(strict_types=1);

final class JPRM_Package_Builder {
	public const PRO_FILES = [
		'assets/admin/import-export.css', 'assets/admin/import-export.js',
		'assets/css/print-document.css',
		'includes/admin/class-admin-import-export.php',
		'includes/admin/class-admin-print-document.php',
		'includes/admin/views/import-export-page.php',
		'includes/data/class-importer.php', 'includes/data/class-exporter.php',
		'includes/data/class-demo-menu.php',
		'includes/data/class-print-document-settings.php',
		'includes/data/class-print-document-builder.php',
		'includes/render/class-print-document-renderer.php',
		'includes/render/print/document.php',
	];

	/** No regex-based PHP parsing: explicitly delimited, named, non-nested regions. */
	public static function transform( string $text, string $path, bool $premium, array $regions, array &$seen ) : string {
		$lines = explode( "\n", str_replace( "\r\n", "\n", $text ) );
		$output = [];
		$active = null;
		foreach ( $lines as $line ) {
			if ( preg_match( '~^(?:// |/\* |<!-- )JPRM_PRO_(BEGIN|END):([a-z0-9-]+)(?: \*/| -->)?$~', $line, $m ) ) {
				$id = $m[2];
				if ( ! isset( $regions[$id] ) || $regions[$id]['file'] !== $path ) {
					throw new RuntimeException( "Unknown or misplaced build region: $path:$id" );
				}
				if ( 'BEGIN' === $m[1] ) {
					if ( null !== $active || isset( $seen[$id] ) ) { throw new RuntimeException( "Nested/duplicate region: $id" ); }
					$active = $id;
					$seen[$id] = true;
					if ( ! $premium && '' !== $regions[$id]['free'] ) { $output[] = rtrim( $regions[$id]['free'], "\n" ); }
				} else {
					if ( $active !== $id ) { throw new RuntimeException( "Unbalanced region: $id" ); }
					$active = null;
				}
				continue;
			}
			if ( false !== strpos( $line, 'JPRM_PRO_BEGIN:' ) || false !== strpos( $line, 'JPRM_PRO_END:' ) ) {
				throw new RuntimeException( "Malformed build marker in $path" );
			}
			if ( $premium || null === $active ) { $output[] = $line; }
		}
		if ( null !== $active ) { throw new RuntimeException( "Unclosed region: $active" ); }
		$text = implode( "\n", $output );
		if ( ! $premium && 'jellopoint-restaurant-menu.php' === $path ) {
			$text = self::replace_once( $text, 'Restaurant Menu Pro', 'Restaurant Menu' );
			$text = self::replace_once( $text, "'is_premium' => true", "'is_premium' => false" );
			$text = self::replace_once( $text, 'set_basename( true, __FILE__ )', 'set_basename( false, __FILE__ )' );
			$text = preg_replace( "/^.*'wp_org_gatekeeper' => .*\n/m", '', $text, 1, $count );
			if ( 1 !== $count ) { throw new RuntimeException( 'Missing gatekeeper transform.' ); }
			$text = str_replace( '// The repository is the Pro edition. tools/build-packages.php creates Free.', '// Free edition. Premium feature implementations are not distributed in this package.', $text );
		}
		return $text;
	}

	private static function replace_once( string $text, string $from, string $to ) : string {
		if ( 1 !== substr_count( $text, $from ) ) { throw new RuntimeException( "Ambiguous edition transform: $from" ); }
		return str_replace( $from, $to, $text );
	}

	/** Allowlist maintained in review. Unknown new source/assets fail rather than leak. */
	public static function build( string $root, string $destination ) : array {
		if ( file_exists( $destination ) ) { throw new RuntimeException( 'Build destination must be new; no existing files will be overwritten.' ); }
		$regions = json_decode( file_get_contents( __DIR__ . '/free-regions.json' ), true, 512, JSON_THROW_ON_ERROR );
		$files = json_decode( file_get_contents( __DIR__ . '/package-files.json' ), true, 512, JSON_THROW_ON_ERROR );
		foreach ( ['includes', 'assets', 'languages', 'vendor/freemius'] as $directory ) {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$root/$directory", FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $entry ) {
				$relative = str_replace( '\\', '/', substr( $entry->getPathname(), strlen( $root ) + 1 ) );
				if ( 'vendor/freemius/composer.json' === $relative ) { continue; }
				if ( $entry->isLink() || ! in_array( $relative, $files, true ) ) {
					throw new RuntimeException( "Unreviewed runtime file (update the manifest deliberately): $relative" );
				}
			}
		}
		foreach ( $files as $path ) {
			if ( ! preg_match( '~^[a-zA-Z0-9_./-]+$~', $path ) || false !== strpos( $path, '..' ) || '/' === $path[0] || is_link( "$root/$path" ) || ! is_file( "$root/$path" ) ) {
				throw new RuntimeException( "Unsafe/missing manifest file: $path" );
			}
		}
		mkdir( $destination, 0775, true );
		$results = [];
		foreach ( ['free' => false, 'pro' => true] as $edition => $premium ) {
			$slug = 'jellopoint-restaurant-menu' . ( $premium ? '-premium' : '' );
			$dir = "$destination/$slug";
			$seen = [];
			$hashes = [];
			foreach ( $files as $path ) {
				if ( ! $premium && in_array( $path, self::PRO_FILES, true ) ) { continue; }
				$content = file_get_contents( "$root/$path" );
				if ( false === $content ) { throw new RuntimeException( "Cannot read $path" ); }
				if ( 0 !== strpos( $path, 'vendor/' ) && in_array( pathinfo( $path, PATHINFO_EXTENSION ), ['php', 'js', 'css'], true ) ) {
					$content = self::transform( $content, $path, $premium, $regions, $seen );
				}
				if ( 'readme.txt' === $path ) {
					if ( ! $premium ) { $content = self::replace_once( $content, '=== JelloPoint – Restaurant Menu Pro ===', '=== JelloPoint – Restaurant Menu ===' ); }
					$content = str_replace( 'This source checkout is the Pro edition. The build process creates separate Free and Pro packages.', $premium ? 'Pro edition: Daily/Weekly Menus, Print/PDF and CSV/JSON Import/Export require Pro access. A non-blocking expired license retains these features; updates and support require renewal.' : 'Free edition: includes Multiple Prices, labels, badges, Menu Builder and reusable Info Blocks. Daily/Weekly Menus, Print/PDF and CSV/JSON Import/Export are available separately in Pro and are not included in this package.', $content );
				}
				if ( 'php' === pathinfo( $path, PATHINFO_EXTENSION ) ) { token_get_all( $content, TOKEN_PARSE ); }
				if ( ! is_dir( dirname( "$dir/$path" ) ) ) { mkdir( dirname( "$dir/$path" ), 0775, true ); }
				if ( false === file_put_contents( "$dir/$path", $content ) ) { throw new RuntimeException( "Cannot write $path" ); }
				$hashes[$path] = hash( 'sha256', $content );
			}
			if ( count( $seen ) !== count( $regions ) ) { throw new RuntimeException( 'Missing build regions: ' . implode( ', ', array_diff( array_keys( $regions ), array_keys( $seen ) ) ) ); }
			$archive = "$destination/$slug.zip";
			$zip = new PharData( $archive, 0, null, Phar::ZIP );
			$entries = [];
			foreach ( array_keys( $hashes ) as $path ) { $entries["$slug/$path"] = "$dir/$path"; }
			$zip->buildFromIterator( new ArrayIterator( $entries ) );
			unset( $zip );
			$results[$edition] = [ 'directory' => $dir, 'zip' => $archive, 'files' => $hashes ];
		}
		file_put_contents( "$destination/build-report.json", json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
		return $results;
	}
}

if ( PHP_SAPI === 'cli' && realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) === __FILE__ ) {
	try {
		$root = dirname( __DIR__ );
		$destination = $argv[1] ?? $root . '/package/build-' . gmdate( 'Ymd-His' );
		foreach ( JPRM_Package_Builder::build( $root, $destination ) as $edition => $result ) { echo "$edition: {$result['zip']}\n"; }
	} catch ( Throwable $e ) { fwrite( STDERR, $e->getMessage() . "\n" ); exit( 1 ); }
}
