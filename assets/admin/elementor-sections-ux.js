(function($){
	'use strict';

	// --- CONFIG: adjust control IDs here if yours differ ---
	const MENU_CONTROL_IDS = [
		'data_menu',          // typical
		'menu_term',          // sometimes used
		'menu',               // fallback name
	];

	const TARGET_CONTROL_SELECTORS = [
		'[data-setting="layout_split_after_section"]',
		'[data-setting="layout_split_after_section2"]',
		// Repeater field: section_layouts[*][section_id]
		'select[name*="section_layouts"][name$="[section_id]"]',
		// Add other selectors if needed
	];

	function panelRoot($scope){
		// Elementor v3: panel elements live inside .elementor-control-panel
		return $scope.closest('.elementor-control-panel, .elementor-editor-active');
	}

	function getCurrentMenuId($panel){
		for (const id of MENU_CONTROL_IDS) {
			const $ctrl = $panel.find('[data-setting="'+id+'"]');
			if ($ctrl.length) {
				const val = $ctrl.val();
				if (val && /^\d+$/.test(String(val))) return parseInt(val,10);
			}
		}
		// Try also a <select name="..."> fallbacks
		const $sel = $panel.find('select[name$="[data_menu]"], select[name$="[menu_term]"], select[name$="[menu]"]');
		if ($sel.length) {
			const v = $sel.val();
			if (v && /^\d+$/.test(String(v))) return parseInt(v,10);
		}
		return 0;
	}

	function formatTreeOptions(sections){
		// sections: [{id,text,level,parent},...]
		const opts = [{ value: '', label: '' }]; // blank first option
		sections.forEach(s => {
			const indent = (s.level > 0) ? Array(s.level + 1).join('— ') : '';
			opts.push({
				value: String(s.id),
				label: indent + s.text
			});
		});
		return opts;
	}

	function repopulateTargets($panel, options){
		// options: [{value,label},...]
		TARGET_CONTROL_SELECTORS.forEach(sel => {
			$panel.find(sel).each(function(){
				const $select = $(this);
				const current = $select.val();
				$select.empty();
				options.forEach(o => {
					const $opt = $('<option/>').attr('value', o.value).text(o.label);
					$select.append($opt);
				});
				// Try to keep old value if it still exists
				if (current && options.some(o => o.value === current)) {
					$select.val(current);
				} else {
					$select.val('');
				}
				$select.trigger('change');
			});
		});
	}

	function fetchAndPopulate($panel){
		const menuId = getCurrentMenuId($panel);
		if (!menuId) { repopulateTargets($panel, [{value:'',label:''}]); return; }

		$.ajax({
			url: (window.JPRMSectionsUX ? JPRMSectionsUX.ajaxUrl : ajaxurl),
			method: 'GET',
			dataType: 'json',
			data: {
				action: 'jprm_sections_for_menu',
				menu_id: menuId,
				nonce: window.JPRMSectionsUX ? JPRMSectionsUX.nonce : ''
			}
		}).done(function(resp){
			const sections = (resp && resp.success && resp.data && Array.isArray(resp.data.sections)) ? resp.data.sections : [];
			const options  = formatTreeOptions(sections);
			repopulateTargets($panel, options);
		}).fail(function(){
			repopulateTargets($panel, [{value:'',label:''}]);
		});
	}

	// Observe panel open & control changes
	$(window).on('elementor:init', function(){

		// When a widget is selected / panel is opened
		elementor.hooks.addAction('panel/open_editor/widget', function(panel, model, view){
			const $panel = panel.$el || $(document);
			// Initial populate
			fetchAndPopulate( panelRoot($panel) );

			// Watch changes on the menu control(s)
			MENU_CONTROL_IDS.forEach(id => {
				$panel.on('change', '[data-setting="'+id+'"]', function(){
					fetchAndPopulate( panelRoot($panel) );
				});
			});

			// Also watch repeater add/remove events to repopulate new row selects
			$panel.on('click', '.elementor-repeater-add, .elementor-repeater-remove', function(){
				setTimeout(function(){ fetchAndPopulate( panelRoot($panel) ); }, 50);
			});
		});
	});
})(jQuery);
