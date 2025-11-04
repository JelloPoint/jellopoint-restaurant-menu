(function($){
	'use strict';

	/* ---------------------- Config ---------------------- */
	// Update if your control IDs differ:
	const MENU_CONTROL_IDS = [
		'data_menu',      // primary (Data Source → Menu)
		'menu_term',      // alt
		'menu',           // alt
	];

	// All selects that should show the filtered tree:
	const TARGET_CONTROL_SELECTORS = [
		'[data-setting="layout_split_after_section"]',
		'[data-setting="layout_split_after_section2"]',
		// Labels Layout Overrides → section target in repeater:
		'select[name*="section_layouts"][name$="[section_id]"]',
		// (You can add more selectors if you introduce new section-pickers)
	];

	/* ---------------------- Utils ----------------------- */
	function debounce(fn, wait){
		let t=null;
		return function(){ clearTimeout(t); t=setTimeout(()=>fn.apply(this, arguments), wait); };
	}

	function panelRoot($scope){
		// Panel root wrapper; tries to be robust across Elementor versions
		const $p = $scope.closest('.elementor-control-panel, .elementor-editor-active, .elementor-panel');
		return $p.length ? $p : $(document);
	}

	function getMenuIdFromPanel($panel){
		// Try data-setting lookups first
		for (const id of MENU_CONTROL_IDS) {
			const $ctrl = $panel.find('[data-setting="'+id+'"]');
			if ($ctrl.length) {
				const v = $ctrl.val();
				if (v && /^\d+$/.test(String(v))) return parseInt(v,10);
			}
		}
		// Fallback to common name patterns
		const $sel = $panel.find('select[name$="[data_menu]"], select[name$="[menu_term]"], select[name$="[menu]"]');
		if ($sel.length) {
			const v = $sel.val();
			if (v && /^\d+$/.test(String(v))) return parseInt(v,10);
		}
		return 0;
	}

	function formatTreeOptions(sections){
		// sections: [{id,text,level,parent}, ...]
		const opts = [{ value:'', label:'' }]; // keep blank option first
		sections.forEach(s => {
			const indent = s.level > 0 ? Array(s.level + 1).join('— ') : '';
			opts.push({ value:String(s.id), label: indent + s.text });
		});
		return opts;
	}

	function repopulateTargets($panel, options){
		TARGET_CONTROL_SELECTORS.forEach(sel => {
			$panel.find(sel).each(function(){
				const $select = $(this);
				const current = $select.val();

				// When Elementor uses Select2, we must destroy before re-building options
				const hasSelect2 = !!$select.data('select2');
				if (hasSelect2) { $select.select2('destroy'); }

				$select.empty();
				options.forEach(o => {
					$select.append( $('<option/>').attr('value', o.value).text(o.label) );
				});

				// Restore value if it still exists
				if (current && options.some(o => o.value === current)) {
					$select.val(current);
				} else {
					$select.val('');
				}

				// Re-init Select2 if Elementor had it
				if (hasSelect2 && $.fn.select2) {
					$select.select2();
				}

				$select.trigger('change'); // inform Elementor control model
			});
		});
	}

	/* --------------- Fetch with caching ---------------- */
	let _cacheMenuId = 0;
	let _cacheOptions = null;

	function fetchOptionsForMenu(menuId){
		// Return cached if same menu
		if (menuId && _cacheMenuId === menuId && _cacheOptions) {
			return Promise.resolve(_cacheOptions);
		}
		if (!menuId) {
			_cacheMenuId = 0;
			_cacheOptions = [{value:'',label:''}];
			return Promise.resolve(_cacheOptions);
		}
		const ajaxUrl = (window.JPRMSectionsUX && JPRMSectionsUX.ajaxUrl) ? JPRMSectionsUX.ajaxUrl : (window.ajaxurl || '');
		const nonce   = (window.JPRMSectionsUX && JPRMSectionsUX.nonce) ? JPRMSectionsUX.nonce : '';

		return $.ajax({
			url: ajaxUrl,
			method: 'GET',
			dataType: 'json',
			data: { action: 'jprm_sections_for_menu', menu_id: menuId, nonce }
		}).then(resp => {
			const sections = (resp && resp.success && resp.data && Array.isArray(resp.data.sections)) ? resp.data.sections : [];
			const options  = formatTreeOptions(sections);
			_cacheMenuId   = menuId;
			_cacheOptions  = options;
			return options;
		}).catch(() => {
			const options = [{value:'',label:''}];
			_cacheMenuId  = menuId;
			_cacheOptions = options;
			return options;
		});
	}

	/* ----------------- Main refresh -------------------- */
	const refreshAll = debounce(function($panel){
		const menuId = getMenuIdFromPanel($panel);
		fetchOptionsForMenu(menuId).then(opts => {
			repopulateTargets($panel, opts);
		});
	}, 50);

	/* ----------------- Event wiring -------------------- */
	function bindLiveListeners($panel){
		// 1) When the Menu control changes, refresh everyone
		MENU_CONTROL_IDS.forEach(id => {
			$panel.on('change.jprmSectionsUX', '[data-setting="'+id+'"]', function(){
				refreshAll($panel);
			});
		});
		$panel.on('change.jprmSectionsUX', 'select[name$="[data_menu]"], select[name$="[menu_term]"], select[name$="[menu]"]', function(){
			refreshAll($panel);
		});

		// 2) Repeater row add/remove → new selects appear → refresh
		$panel.on('click.jprmSectionsUX', '.elementor-repeater-add, .elementor-repeater-remove', function(){
			setTimeout(()=>refreshAll($panel), 60);
		});

		// 3) Tab/accordion switches rebuild controls → refresh
		$panel.on('click.jprmSectionsUX', '.elementor-panel-navigation-tab, .elementor-control-accordion .elementor-control-title', function(){
			setTimeout(()=>refreshAll($panel), 60);
		});

		// 4) Observe DOM mutations in the panel (Elementor often re-renders chunks)
		const observer = new MutationObserver(debounce(function(){
			refreshAll($panel);
		}, 50));

		observer.observe($panel.get(0), {
			childList: true,
			subtree: true
		});

		// Store to allow later cleanup if needed
		$panel.data('jprmSectionsObserver', observer);
	}

	/* ------------- Elementor hook-in points ----------- */
	$(window).on('elementor:init', function(){

		// When a widget panel opens
		elementor.hooks.addAction('panel/open_editor/widget', function(panel, model, view){
			const $panel = panelRoot(panel.$el || $(document));

			// Avoid double-binding if reopened
			if (!$panel.data('jprmSectionsBound')) {
				bindLiveListeners($panel);
				$panel.data('jprmSectionsBound', true);
			}

			// Initial populate for the active widget instance
			refreshAll($panel);
		});

		// Also refresh when Elementor signals control changes globally
		if (elementor.channels && elementor.channels.editor) {
			elementor.channels.editor.on('change', debounce(function(){
				const $panel = panelRoot($(document));
				refreshAll($panel);
			}, 80));
		}
	});

})(jQuery);
