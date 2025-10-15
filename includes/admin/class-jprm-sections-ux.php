<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lightweight UI tweaks for the jprm_section taxonomy:
 * - List page: "Add Section" button text
 * - Edit page: hide Slug, "Edit Section" heading, "Parent Section" label
 * (non-destructive; only CSS/JS in admin)
 */
class Sections_UX {

	const TAX = 'jprm_section';

	public static function init() : void {
		// Inject on both list/add (edit-tags.php) and single edit (term.php)
		add_action( 'admin_head-edit-tags.php', [ __CLASS__, 'inject_css_js' ] );
		add_action( 'admin_head-term.php',      [ __CLASS__, 'inject_css_js' ] );
	}

	public static function inject_css_js() : void {
		?>
		<style>
			/* Hide Slug on add + edit for jprm_section */
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .form-field.term-slug-wrap,
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .term-slug-wrap {
				display: none !important;
			}
		</style>
		<script>
		(function(){
			function ready(fn){ if(document.readyState!=='loading'){fn();} else {document.addEventListener('DOMContentLoaded',fn);} }

			function tweakListPage(){
				if (!document.body.classList.contains('taxonomy-<?php echo esc_js(self::TAX); ?>')) return;

				// Left add box submit button text -> "Add Section"
				var addSubmit = document.querySelector('#addtag input#submit, #addtag button#submit, .tag-add-form input[type="submit"], .tag-add-form button[type="submit"]');
				if (addSubmit) {
					if (addSubmit.tagName === 'INPUT') addSubmit.value = 'Add Section';
					else addSubmit.textContent = 'Add Section';
					addSubmit.setAttribute('aria-label', 'Add Section');
				}
			}

			function tweakEditPage(){
				if (!document.body.classList.contains('taxonomy-<?php echo esc_js(self::TAX); ?>')) return;

				// Change "Edit Category" heading -> "Edit Section" (term.php)
				var h1 = document.querySelector('.wrap > h1');
				if (h1 && /Edit\s+Category/i.test(h1.textContent)) {
					h1.textContent = h1.textContent.replace(/Edit\s+Category/i, 'Edit Section');
				}

				// Parent Category -> Parent Section
				var parentLabel = document.querySelector('.edit-tag-form .form-field.term-parent-wrap th label');
				if (parentLabel) parentLabel.textContent = 'Parent Section';
			}

			ready(function(){
				tweakListPage();
				tweakEditPage();
			});
		})();
		</script>
		<?php
	}
}
