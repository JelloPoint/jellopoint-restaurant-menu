(function($){
	'use strict';

	/**
	 * Utils
	 */
	function isOurWidget(panelView) {
		try { return panelView?.model?.get('widgetType') === 'jprm_restaurant_menu'; }
		catch(e){ return false; }
	}

	async function fetchSections(menuId){
		if(!menuId){ return {}; }
		try {
			const root  = (window.JPRMRest && JPRMRest.root) ? JPRMRest.root : (window.wpApiSettings ? wpApiSettings.root : '/wp-json/');
			const nonce = (window.JPRMRest && JPRMRest.nonce) ? JPRMRest.nonce : (window.wpApiSettings ? wpApiSettings.nonce : null);
			const url   = root.replace(/\/+$/,'') + '/jprm/v1/sections?menu=' + encodeURIComponent(menuId);

			const headers = nonce ? { 'X-WP-Nonce': nonce } : {};
			const res = await fetch(url, { credentials: 'include', headers });
			if(!res.ok){ return {}; }
			const data = await res.json();
			return (data && typeof data === 'object') ? data : {};
		} catch(e){
			return {};
		}
	}

	/**
	 * Safely set options + value on a control using Elementor's panel API,
	 * falling back to DOM manipulation when needed.
	 */
	function applyOptionsToSections(panelView, optionsMap) {
		// Try official control view first.
		const controlView = panelView.getControlView && panelView.getControlView('sections');
		const newOptions  = optionsMap || {};
		const newKeys     = Object.keys(newOptions);

		// Determine current selection (keep intersection with new options).
		let current = [];
		try {
			const modelVal = panelView.model.getSetting('sections');
			if (Array.isArray(modelVal)) current = modelVal;
		} catch(e){ /* noop */ }

		const keep = current.filter(v => newKeys.includes(String(v)));

		// Update via ControlView (preferred).
		if (controlView && controlView.model) {
			// Set options into the control model then re-render.
			controlView.model.set('options', newOptions);
			// Set the value to intersection of previous selection.
			panelView.model.setSetting('sections', keep);

			// Re-render control UI.
			if (typeof controlView.render === 'function') controlView.render();

			// Ensure select2 reflects the new list.
			const $select = controlView.$el.find('[data-setting="sections"]');
			if ($select.length) {
				$select.val(keep).trigger('change');
			}
			return;
		}

		// Fallback: DOM-only (works but Elementor may re-render later).
		const $panel    = panelView.$el;
		const $sections = $panel.find('[data-setting="sections"]');
		if ($sections.length) {
			const selected = keep.map(String);
			$sections.find('option').remove();
			newKeys.forEach(function(id){
				const opt = new Option(newOptions[id], id, false, selected.includes(id));
				$sections.append(opt);
			});
			$sections.val(selected).trigger('change');
		}
	}

	/**
	 * Wire up the Menu -> Sections dependency.
	 */
	function hookPanel(panelView){
		if(!isOurWidget(panelView)){ return; }

		const controlMenu    = panelView.getControlView && panelView.getControlView('menus');
		const $panel         = panelView.$el;
		const $menuSelectDom = $panel.find('[data-setting="menus"]'); // fallback

		// Helper to get current menu id (string).
		const getMenuId = () => {
			try {
				const v = panelView.model.getSetting('menus');
				if (v === null || v === undefined) return '';
				return Array.isArray(v) ? (v[0] || '') : String(v || '');
			} catch(e) {
				const domVal = $menuSelectDom.val();
				return Array.isArray(domVal) ? (domVal[0] || '') : (domVal || '');
			}
		};

		// Initial population on open.
		(function initialPopulate(){
			const mid = getMenuId();
			if (mid) {
				fetchSections(mid).then(map => applyOptionsToSections(panelView, map));
			} else {
				applyOptionsToSections(panelView, {}); // clear sections when no menu
			}
		})();

		// Listen for changes via control view (preferred).
		if (controlMenu && controlMenu.$el) {
			controlMenu.$el.on('change', '[data-setting="menus"]', function(){
				const mid = getMenuId();
				if (!mid) {
					applyOptionsToSections(panelView, {});
					return;
				}
				fetchSections(mid).then(map => applyOptionsToSections(panelView, map));
			});
		}

		// Fallback: listen directly on DOM (in case control view isn’t available yet).
		$menuSelectDom.on('change', function(){
			const mid = getMenuId();
			if (!mid) {
				applyOptionsToSections(panelView, {});
				return;
			}
			fetchSections(mid).then(map => applyOptionsToSections(panelView, map));
		});
	}

	/**
	 * Elementor editor lifecycle
	 */
	$(window).on('elementor:init', function(){
		// When a widget panel opens.
		elementor.hooks.addAction('panel/open_editor/widget', function(panelView){
			// Delay a tick to allow control views to mount (prevents race conditions).
			setTimeout(function(){ hookPanel(panelView); }, 50);
		});
	});
})(jQuery);
