<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * UI tweaks for the jprm_menu taxonomy screens:
 * - Rename "Category/Categories" wording to "Menu/Menus"
 * - Hide Slug on add + edit
 * - Remove Slug column in the list table
 */
class Menus_Admin {

	const TAX_MENU = 'jprm_menu';

	public static function init() : void {
		// Remove "Slug" column on the Menus list screen
		add_filter( 'manage_edit-' . self::TAX_MENU . '_columns', [ __CLASS__, 'columns' ] );

		// Inject small CSS/JS to rename labels & hide fields on the add/edit screens
		add_action( 'admin_head-edit-tags.php',   [ __CLASS__, 'inject_admin_css_js' ] );
	}

	/**
	 * Drop the "slug" column for a cleaner list table.
	 */
	public static function columns( $cols ) {
		if ( isset( $cols['slug'] ) ) {
			unset( $cols['slug'] );
		}
		return $cols;
	}

	/**
	 * Front-end polish via CSS/JS (edit-tags.php only, and only for jprm_menu).
	 * - Hide the Slug field (add + edit forms)
	 * - Rename common labels ("Add Category" -> "Add Menu", etc.)
	 */
	public static function inject_admin_css_js() : void {
		$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : ''; // phpcs:ignore
		if ( $tax !== self::TAX_MENU ) return;
		?>
		<style>
			/* Hide Slug on add + edit */
			.taxonomy-<?php echo esc_attr( self::TAX_MENU ); ?> .form-field.term-slug-wrap,
			.taxonomy-<?php echo esc_attr( self::TAX_MENU ); ?> .term-slug-wrap { display: none !important; }
		</style>
		<script>
		(function(){
			function renameAddForm(){
				// Left add box title
				var hdr = document.querySelector('.wrap .form-wrap > h2') || document.querySelector('.tag-add-form h2');
				if (hdr) {
					// Some WP versions show "Add New Category" or "Add Category"
					hdr.textContent = 'Add Menu';
				}
				// Parent label (only relevant if taxonomy is hierarchical)
				var parentLbl = document.querySelector('#addtag .form-field.term-parent-wrap > label');
				if (parentLbl) parentLbl.textContent = 'Parent Menu';
			}
			function renameEditForm(){
				var form = document.querySelector('.edit-tag-form');
				if (!form) return;
				// Parent label on edit table row (if hierarchical)
				var parentEdit = form.querySelector('.form-field.term-parent-wrap th label');
				if (parentEdit) parentEdit.textContent = 'Parent Menu';
			}
			function renameSearchAndHeadings(){
				// Search label + placeholder
				var searchLbl = document.querySelector('label[for="tag-search-input"]');
				if (searchLbl) searchLbl.textContent = 'Search Menus:';
				var searchInp = document.getElementById('tag-search-input');
				if (searchInp) {
					// If WP put a placeholder like "Search Categories"
					searchInp.placeholder = 'Search Menus';
				}
				// The big page title often includes the taxonomy singular/plural automatically.
				// We can't reliably change that server-side without re-registering the taxonomy,
				// but many admin skins have a secondary H1 we can update safely:
				var h1 = document.querySelector('.wrap > h1');
				if (h1 && /Categories/i.test(h1.textContent)) {
					h1.textContent = h1.textContent.replace(/Categories/gi, 'Menus');
				}
				// Subsubsub (views) sometimes show "All Categories" — adjust if present
				document.querySelectorAll('.subsubsub a').forEach(function(a){
					a.textContent = a.textContent.replace(/Categories/gi, 'Menus');
					a.textContent = a.textContent.replace(/Category/gi, 'Menu');
				});
			}

			document.addEventListener('DOMContentLoaded', function(){
				renameAddForm();
				renameEditForm();
				renameSearchAndHeadings();
			});
		})();
		</script>
		<?php
	}
}
