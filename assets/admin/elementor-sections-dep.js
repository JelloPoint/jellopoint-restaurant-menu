(function ($) {
	'use strict';

	/**
	 * REST fetch helper
	 */
	async function fetchSections(menuId) {
		if (!menuId) return {};
		try {
			const root =
				(window.JPRMRest && JPRMRest.root)
					? JPRMRest.root
					: (window.wpApiSettings ? wpApiSettings.root : '/wp-json/');
			const nonce =
				(window.JPRMRest && JPRMRest.nonce)
					? JPRMRest.nonce
					: (window.wpApiSettings ? wpApiSettings.nonce : null);
			const url = root.replace(/\/+$/, '') + '/jprm/v1/sections?menu=' + encodeURIComponent(menuId);
			const headers = nonce ? { 'X-WP-Nonce': nonce } : {};
			const res = await fetch(url, { credentials: 'include', headers });
			if (!res.ok) return {};
			const data = await res.json();
			return data && typeof data === 'object' ? data : {};
		} catch (e) {
			return {};
		}
	}

	/**
	 * Apply options to Sections control
	 */
	function applySectionsOptions(panel, options) {
		try {
			const control = panel.getControlView('sections');
			const map = options || {};
			const ids = Object.keys(map);
			const current = panel.model.getSetting('sections') || [];
			const keep = current.filter(v => ids.includes(String(v)));

			control.model.set('options', map);
			panel.model.setSetting('sections', keep);
			control.render();

			const $sel = control.$el.find('[data-setting="sections"]');
			if ($sel.length) {
				$sel.val(keep).trigger('change');
			}
		} catch (e) {
			// fallback: DOM
			const $sel = panel.$el.find('[data-setting="sections"]');
			if ($sel.length) {
				const current = $sel.val() || [];
				$sel.find('option').remove();
				for (const id in options) {
					$sel.append(new Option(options[id], id, false, current.includes(id)));
				}
				$sel.trigger('change');
			}
		}
	}

	/**
	 * Core logic to hook Menu control change
	 */
	async function bindDependency(panel) {
		if (!panel?.model?.get('widgetType') || panel.model.get('widgetType') !== 'jprm_restaurant_menu') return;

		const getMenuVal = () => {
			const val = panel.model.getSetting('menus');
			return Array.isArray(val) ? val[0] : val || '';
		};

		async function refreshSections() {
			const menuId = getMenuVal();
			if (!menuId) {
				applySectionsOptions(panel, {});
				return;
			}
			const map = await fetchSections(menuId);
			applySectionsOptions(panel, map);
		}

		// Initial fill
		await refreshSections();

		// Watch for Menu control changes (poll until exists)
		let tries = 0;
		const watchInterval = setInterval(() => {
			const $menuSel = panel.$el.find('[data-setting="menus"]');
			if ($menuSel.length || tries++ > 40) {
				clearInterval(watchInterval);
				if ($menuSel.length) {
					$menuSel.on('change', refreshSections);
				}
			}
		}, 250);
	}

	/**
	 * Wait until Elementor editor + panel API are ready
	 */
	function startHook() {
		const hook = (panel) => {
			setTimeout(() => bindDependency(panel), 200);
		};

		// Newer Elementor versions
		if (elementor?.on) {
			elementor.on('panel:open_editor', hook);
		}

		// Older fallback
		elementor.hooks.addAction('panel/open_editor/widget', hook);
	}

	// Ensure we attach after the editor is fully booted
	if (window.elementor && elementor.on) {
		startHook();
	} else {
		$(window).on('elementor/editor/init', startHook);
	}
})(jQuery);
