<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * UI + data tweaks for the jprm_menu taxonomy:
 * - Rename Category/Categories -> Menu/Menus (list/add/edit)
 * - Hide Slug (list/add/edit)
 * - Hide Parent field (list/add/edit)
 * - Enforce no parent on save (menus never hierarchical)
 */
class Menus_Admin {

	const TAX = 'jprm_menu';

	public static function init() : void {
		// Remove "Slug" column on the Menus list screen
		add_filter( 'manage_edit-' . self::TAX . '_columns', [ __CLASS__, 'columns' ] );

		// Inject CSS/JS on BOTH taxonomy admin screens
		add_action( 'admin_head-edit-tags.php', [ __CLASS__, 'inject_css_js' ] );
		add_action( 'admin_head-term.php',      [ __CLASS__, 'inject_css_js' ] );

		// Enforce: no parent stored for this taxonomy
		add_filter( 'wp_insert_term_data', [ __CLASS__, 'force_no_parent' ], 10, 3 );
	}

	/** Drop the "slug" column in the list table */
	public static function columns( $cols ) {
		if ( isset( $cols['slug'] ) ) unset( $cols['slug'] );
		return $cols;
	}

	/**
	 * Force parent=0 on insert/update for this taxonomy (defensive).
	 */
	public static function force_no_parent( $data, $taxonomy, $args ) {
		if ( $taxonomy === self::TAX ) {
			$data['parent'] = 0;
		}
		return $data;
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

				// Search label + placeholder in list view
				var searchLbl = document.querySelector('label[for="tag-search-input"]');
				if (searchLbl) searchLbl.textContent = 'Search Menus:';
				var searchInp = document.getElementById('tag-search-input');
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
