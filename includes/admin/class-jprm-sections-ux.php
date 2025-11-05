<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lightweight UI tweaks for the jprm_section taxonomy:
 * - List page: "Add Section" button text
 * - Edit page: hide Slug, "Edit Section" heading, "Parent Section" label
 */
class Sections_UX {

	const TAX = 'jprm_section';

	public static function init() : void {
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
			function isSectionScreen(){ return document.body.classList.contains('taxonomy-<?php echo esc_js(self::TAX); ?>'); }

			function tweakListPage(){
				if (!isSectionScreen()) return;
				// Left add box submit button -> "Add Section"
				var addSubmit = document.querySelector('#addtag input#submit, #addtag button#submit, .tag-add-form input[type="submit"], .tag-add-form button[type="submit"]');
				if (addSubmit) {
					if (addSubmit.tagName === 'INPUT') addSubmit.value = 'Add Section';
					else addSubmit.textContent = 'Add Section';
					addSubmit.setAttribute('aria-label', 'Add Section');
				}
			}

			function tweakEditPage(){
				if (!isSectionScreen()) return;

				// H1: "Edit Category" -> "Edit Section" (best-effort; if not present, leave as-is)
				var h1 = document.querySelector('.wrap > h1');
				if (h1 && /Category/i.test(h1.textContent)) {
					h1.textContent = h1.textContent.replace(/Category/gi, 'Section');
				}

				// Robustly rename Parent label to "Parent Section" on term.php.
				// Different WP versions / themes render the label in different places.
				var candidates = [
					// Classic table layout
					'.edit-tag-form .form-field.term-parent-wrap th label',
					// Sometimes the label is a div/label combo
					'.edit-tag-form .form-field.term-parent-wrap label',
					// Fallback: any label inside the parent wrap
					'.term-parent-wrap label'
				];
				var renamed = false;
				for (var i=0; i<candidates.length; i++) {
					var el = document.querySelector(candidates[i]);
					if (el) {
						el.textContent = 'Parent Section';
						renamed = true;
					}
				}

				// Also adjust any ARIA / title attributes that might show tooltips/help
				var parentSelect = document.querySelector('.term-parent-wrap select');
				if (parentSelect) {
					parentSelect.setAttribute('aria-label', 'Parent Section');
					parentSelect.setAttribute('title', 'Parent Section');
				}
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
