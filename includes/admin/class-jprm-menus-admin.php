<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * UI tweaks for the jprm_menu taxonomy screens:
 * - Rename "Category/Categories" wording to "Menu/Menus"
 * - Hide Slug on add + edit
 * - Remove Slug column in the list table
 * Works on both edit-tags.php (list/add) and term.php (single edit)
 */
class Menus_Admin {

	const TAX = 'jprm_menu';

	public static function init() : void {
		// Remove "Slug" column on the Menus list screen
		add_filter( 'manage_edit-' . self::TAX . '_columns', [ __CLASS__, 'columns' ] );

		// Inject CSS/JS on BOTH taxonomy admin screens
		add_action( 'admin_head-edit-tags.php', [ __CLASS__, 'inject_css_js' ] );
		add_action( 'admin_head-term.php',      [ __CLASS__, 'inject_css_js' ] );
	}

	/** Drop the "slug" column in the list table */
	public static function columns( $cols ) {
		if ( isset( $cols['slug'] ) ) unset( $cols['slug'] );
		return $cols;
	}

	/** CSS/JS polish – runs on edit-tags.php and term.php; no GET reliance */
	public static function inject_css_js() : void {
		?>
		<style>
			/* Hide Slug on add + edit for jprm_menu */
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .form-field.term-slug-wrap,
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .term-slug-wrap {
				display: none !important;
			}
		</style>
		<script>
		(function(){
			function onReady(fn){ if(document.readyState!=='loading'){fn();}else{document.addEventListener('DOMContentLoaded',fn);} }

			function renameOnListOrAdd(){
				// Only act on jprm_menu screens
				if (!document.body.classList.contains('taxonomy-<?php echo esc_js(self::TAX); ?>')) return;

				// Left add box title: "Add Category" -> "Add Menu"
				var addHdr = document.querySelector('.wrap .form-wrap > h2') || document.querySelector('.tag-add-form h2');
				if (addHdr && /Add\s+Category/i.test(addHdr.textContent)) addHdr.textContent = 'Add Menu';

				// Parent label on add form (if hierarchical)
				var parentAdd = document.querySelector('#addtag .form-field.term-parent-wrap > label');
				if (parentAdd) parentAdd.textContent = 'Parent Menu';

				// Search label + placeholder in list view
				var searchLbl = document.querySelector('label[for="tag-search-input"]');
				if (searchLbl) searchLbl.textContent = 'Search Menus:';
				var searchInp = document.getElementById('tag-search-input');
				if (searchInp) searchInp.placeholder = 'Search Menus';

				// Page title (best-effort)
				var h1 = document.querySelector('.wrap > h1');
				if (h1) {
					h1.textContent = h1.textContent.replace(/Categories/gi, 'Menus').replace(/\bCategory\b/gi, 'Menu');
				}

				// Subsubsub filters may say "All Categories"
				document.querySelectorAll('.subsubsub a').forEach(function(a){
					a.textContent = a.textContent.replace(/Categories/gi, 'Menus').replace(/\bCategory\b/gi, 'Menu');
				});
			}

			function renameOnSingleEdit(){
				// Only act on jprm_menu term.php
				if (!document.body.classList.contains('taxonomy-<?php echo esc_js(self::TAX); ?>')) return;

				// Parent label on edit table row
				var parentEdit = document.querySelector('.edit-tag-form .form-field.term-parent-wrap th label');
				if (parentEdit) parentEdit.textContent = 'Parent Menu';

				// Page title
				var h1 = document.querySelector('.wrap > h1');
				if (h1) {
					h1.textContent = h1.textContent.replace(/Categories/gi, 'Menus').replace(/\bCategory\b/gi, 'Menu');
				}
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
