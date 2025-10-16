(function($){
	'use strict';

	function isOurWidget(panelView) {
		try {
			return panelView && panelView.model && panelView.model.get('widgetType') === 'jprm_restaurant_menu';
		} catch(e){ return false; }
	}

	async function fetchSections(menuId){
		if(!menuId){ return {}; }
		try {
			const url = window.ajaxurl.replace('admin-ajax.php','') + 'index.php?rest_route=/jprm/v1/sections&menu=' + encodeURIComponent(menuId);
			const res = await fetch(url, { credentials: 'include' });
			if(!res.ok){ return {}; }
			return await res.json();
		} catch(e){ return {}; }
	}

	function setSelect2Options($select, optionsMap){
		// optionsMap: {"12": "Starters", "34": "Mains"}
		// Clear current options (keep selected if still present).
		const selected = $select.val() || [];
		$select.find('option').remove();

		Object.keys(optionsMap).forEach(function(id){
			$select.append(new Option(optionsMap[id], id, false, selected.includes(id)));
		});

		$select.trigger('change'); // refresh select2 UI
	}

	function hookPanel(panelView){
		if(!isOurWidget(panelView)){ return; }

		const $panel = panelView.$el;

		// Menu select (single): data-setting="menus"
		const $menu = $panel.find('[data-setting="menus"]');

		// Sections multiselect: data-setting="sections"
		const $sections = $panel.find('[data-setting="sections"]');

		// If not found, bail (maybe different tab).
		if($menu.length === 0 || $sections.length === 0){ return; }

		// On open, if a menu is already chosen, load its sections.
		let initialMenu = $menu.val();
		if(initialMenu){
			fetchSections(initialMenu).then(map => setSelect2Options($sections, map));
		}

		// On change, fetch and update options.
		$menu.on('change', function(){
			const menuId = $(this).val();
			if(!menuId){
				// No menu selected → show none (or comment next line to show all)
				setSelect2Options($sections, {});
				return;
			}
			fetchSections(menuId).then(map => setSelect2Options($sections, map));
		});
	}

	// Elementor Editor lifecycle
	$(window).on('elementor:init', function(){
		elementor.hooks.addAction('panel/open_editor/widget', hookPanel);
	});
})(jQuery);
