<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * UI + data tweaks for the jprm_menu taxonomy:
 * - Rename Category/Categories -> Menu/Menus (list/add/edit)
 * - Hide Slug & Parent (list/add/edit)
 * - Remove Slug column (list)
 * - Ensure no invalid columns are added to wp_terms on insert/update
 *   (parent belongs to wp_term_taxonomy, never to wp_terms)
 */
class Menus_Admin {

	const TAX = 'jprm_menu';

	public static function init() : void {
		// Remove "Slug" column on the Menus list screen
		add_filter( 'manage_edit-' . self::TAX . '_columns', [ __CLASS__, 'columns' ] );

		// Inject CSS/JS on BOTH taxonomy admin screens
		add_action( 'admin_head-edit-tags.php', [ __CLASS__, 'inject_css_js' ] );
		add_action( 'admin_head-term.php',      [ __CLASS__, 'inject_css_js' ] );

		/**
		 * IMPORTANT:
		 * - Never allow non-wp_terms columns (e.g. 'parent') into the $data array that inserts into wp_terms.
		 * - If parent needs normalizing, do it via wp_insert_term_args; core uses args for wp_term_taxonomy.
		 */
		add_filter( 'wp_insert_term_data', [ __CLASS__, 'sanitize_terms_table_data' ], 10, 3 );
		add_filter( 'wp_insert_term_args', [ __CLASS__, 'sanitize_parent_arg' ], 10, 2 );
	}

	/** Drop the "slug" column in the list table */
	public static function columns( $cols ) {
		if ( isset( $cols['slug'] ) ) unset( $cols['slug'] );
		return $cols;
	}

	/**
	 * Keep only valid wp_terms columns: name, slug, term_group.
	 * DO NOT pass 'parent' here — that belongs in wp_term_taxonomy and is handled from $args.
	 *
	 * @param array  $data     Data for wp_terms insert/update.
	 * @param string $taxonomy Current taxonomy.
	 * @param array  $args     Arguments including parent for wp_term_taxonomy.
	 * @return array
	 */
	public static function sanitize_terms_table_data( $data, $taxonomy, $args ) {
		// Only affect our taxonomy (extend array if you want the same behavior for others)
		if ( $taxonomy !== self::TAX ) {
			return $data;
		}

		$allowed = [ 'name', 'slug', 'term_group' ];
		$clean   = array_intersect_key( (array) $data, array_flip( $allowed ) );

		// Ensure required keys exist (WordPress will set defaults but we keep it tidy)
		if ( ! isset( $clean['term_group'] ) ) {
			$clean['term_group'] = 0;
		}

		return $clean;
	}

	/**
	 * Ensure 'parent' (used for wp_term_taxonomy) is numeric and sane.
	 * Core reads parent from $args, not from $data.
	 *
	 * @param array  $args     Insert/update args for wp_term_taxonomy.
	 * @param string $taxonomy Current taxonomy.
	 * @return array
	 */
	public static function sanitize_parent_arg( $args, $taxonomy ) {
		if ( $taxonomy !== self::TAX ) {
			return $args;
		}
		// If parent is missing or invalid, normalize to 0 (menus are effectively non-hierarchical in your UI)
		if ( ! isset( $args['parent'] ) || ! is_numeric( $args['parent'] ) ) {
			$args['parent'] = 0;
		} else {
			$args['parent'] = (int) $args['parent'];
			if ( $args['parent'] < 0 ) {
				$args['parent'] = 0;
			}
		}
		return $args;
	}

	/** CSS/JS polish – runs on edit-tags.php and term.php; no GET reliance */
	public static function inject_css_js() : void {
		?>
		<style>
			/* Hide Slug + Parent on add + edit for jprm_menu */
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .form-field.term-slug-wrap,
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .term-slug-wrap,
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .form-field.term-parent-wrap,
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .term-parent-wrap {
				display: none !important;
			}
		</style>
		<script>
		(function(){
			function onReady(fn){ if(document.readyState!=='loading'){fn();}else{document.addEventListener('DOMContentLoaded',fn);} }

			function renameOnListOrAdd(){
				if (!document.body.classList.contains('taxonomy-<?php echo esc_js(self::TAX); ?>')) return;

				// Left add box title: "Add Category" -> "Add Menu"
				var addHdr = document.querySelector('.wrap .form-wrap > h2') || document.querySelector('.tag-add-form h2');
				if (addHdr && /Add\s+Category/i.test(addHdr.textContent)) addHdr.textContent = 'Add Menu';

				// Left add box submit button text (covers input/button variants)
				var addSubmit = document.querySelector('#addtag input#submit, #addtag button#submit, .tag-add-form input[type="submit"], .tag-add-form button[type="submit"]');
				if (addSubmit) {
					if (addSubmit.tagName === 'INPUT') addSubmit.value = 'Add Menu';
					else addSubmit.textContent = 'Add Menu';
					addSubmit.setAttribute('aria-label', 'Add Menu');
				}

				// Search label + placeholder (top right)
				var searchLbl = document.querySelector('label[for="tag-search-input"]') ||
				                document.querySelector('.search-form label') ||
				                document.querySelector('.search-box label');
				if (searchLbl) searchLbl.textContent = 'Search Menus:';

				var searchInp = document.getElementById('tag-search-input') ||
				                document.querySelector('.search-form input[type="search"], .search-form input[type="text"]');
				if (searchInp) searchInp.placeholder = 'Search Menus';

				// Page title & subnav text
				var h1 = document.querySelector('.wrap > h1');
				if (h1) h1.textContent = h1.textContent.replace(/Categories/gi, 'Menus').replace(/\bCategory\b/gi, 'Menu');

				document.querySelectorAll('.subsubsub a').forEach(function(a){
					a.textContent = a.textContent.replace(/Categories/gi, 'Menus').replace(/\bCategory\b/gi, 'Menu');
				});

				// Ensure hidden parent select doesn't accidentally submit non-zero
				var parentAdd = document.querySelector('#addtag .form-field.term-parent-wrap select');
				if (parentAdd) parentAdd.value = '0';
			}

			function renameOnSingleEdit(){
				if (!document.body.classList.contains('taxonomy-<?php echo esc_js(self::TAX); ?>')) return;

				// Page title
				var h1 = document.querySelector('.wrap > h1');
				if (h1) h1.textContent = h1.textContent.replace(/Categories/gi, 'Menus').replace(/\bCategory\b/gi, 'Menu');

				// Ensure hidden parent on edit stays zero
				var parentEdit = document.querySelector('.edit-tag-form .form-field.term-parent-wrap select');
				if (parentEdit) parentEdit.value = '0';
			}

			onReady(function(){
				renameOnListOrAdd();
				renameOnSingleEdit();
			});
		})();
		</script>
		<?php
	}
}
