(function ($) {
	'use strict';

	/* -------------------- CONFIG -------------------- */
	// Menu control names/selectors
	const MENU_KEYS = ['data_menu', 'menus'];
	const MENU_SELECTORS = [
		'[data-setting="data_menu"]',
		'[data-setting="menus"]',
		'select[name$="[data_menu]"]',
		'select[name$="[menus]"]',
	];

	// Target section selects (names and DOM selectors)
	const TARGET_KEYS = [
		'sections',
		'layout_split_after_section',
		'layout_split_after_section2',
		'section_id', // repeater inner control
	];
	const TARGET_SELECTORS = [
		'[data-setting="sections"]',
		'[data-setting="layout_split_after_section"]',
		'[data-setting="layout_split_after_section2"]',
		'select[name*="section_layouts"][name$="[section_id]"]'
	];

	/* -------------------- UTIL -------------------- */
	const LOG = '[JPRM Sections]';
	function log(){ /* console.log.apply(console, [LOG].concat([].slice.call(arguments))); */ }

	function ajaxUrl() {
		if (window.JPRMSectionsUX && JPRMSectionsUX.ajaxUrl) return JPRMSectionsUX.ajaxUrl;
		if (typeof ajaxurl !== 'undefined') return ajaxurl;
		return '/wp-admin/admin-ajax.php';
	}
	function ajaxNonce(){ return (window.JPRMSectionsUX && JPRMSectionsUX.nonce) ? JPRMSectionsUX.nonce : ''; }

	function getPanelView() {
		try { return elementor.getPanelView().getCurrentPanelView(); } catch(e) { return null; }
	}

	function getSetting(model, keys) {
		for (const k of keys) {
			const v = (model.getSetting ? model.getSetting(k) : model.get(k));
			if (v !== undefined && v !== null && String(v).length) return v;
		}
		return null;
	}

	function currentMenuIdFromModel(model) {
		const v = getSetting(model, MENU_KEYS);
		const first = Array.isArray(v) ? (v[0] || '') : v;
		return /^\d+$/.test(String(first)) ? parseInt(first, 10) : 0;
	}

	function toOptions(nodes) {
		const opts = [{ value:'', label:'' }];
		nodes.forEach(n => {
			const lvl = Number(n.level || 0);
			const ind = lvl > 0 ? Array(lvl + 1).join('— ') : '';
			opts.push({ value: String(n.id), label: ind + n.text });
		});
		return opts;
	}

	function applyOptionsToSelect($select, opts, prev) {
		$select.find('option').remove();
		opts.forEach(o => $select.append(new Option(o.label, o.value, false, false)));
		if (prev && (Array.isArray(prev) ? prev.length : prev)) {
			$select.val(prev).trigger('change');
		} else {
			$select.val('').trigger('change');
		}
	}

	/* -------------------- AJAX + CACHE -------------------- */
	const cache = new Map(); // menuId -> nodes[]

	async function fetchNodes(menuId) {
		const url = ajaxUrl();
		const params = new URLSearchParams();
		params.set('action', 'jprm_sections_for_menu');
		params.set('nonce', ajaxNonce());
		params.set('menu_id', String(menuId));
		const res = await fetch(url + '?' + params.toString(), { method: 'GET', credentials: 'include' });
		const json = await res.json().catch(() => null);
		if (!json || !json.success || !json.data || !Array.isArray(json.data.sections)) return [];
		return json.data.sections;
	}

	async function getNodes(menuId) {
		if (!menuId) return [];
		if (cache.has(menuId)) return cache.get(menuId);
		const nodes = await fetchNodes(menuId);
		cache.set(menuId, nodes);
		return nodes;
	}

	/* -------------------- CORE APPLY -------------------- */
	function applyToControlView(view, optionsMap) {
		if (!view || !view.model) return false;
		const name = view.model.get('name');
		if (!name) return false;

		// Is it one of our target controls?
		if (!(TARGET_KEYS.includes(name) || name === 'section_id')) return false;

		// Convert options array -> object for model
		const optsObj = {};
		optionsMap.forEach(o => { if (o.value !== undefined) optsObj[o.value] = o.label; });

		// Keep previous value if possible
		let prev = null;
		try {
			const $sel = view.$el.find('select,[data-setting="'+name+'"]').first();
			prev = $sel.length ? $sel.val() : view.getControlValue();
		} catch(e){}

		view.model.set('options', optsObj);
		if (typeof view.render === 'function') view.render();

		// Ensure DOM select also has the right options (Select2-safe)
		try {
			const $select = view.$el.find('select,[data-setting="'+name+'"]');
			if ($select.length) applyOptionsToSelect($select, optionsMap, prev);
		} catch(e){}

		return true;
	}

	function applyEverywhere(optionsMap) {
		const pv = getPanelView();
		if (!pv || !pv.collection) return;

		// Top-level controls
		pv.collection.each(function (controlModel) {
			const name = controlModel.get('name');
			try {
				const view = pv.getControlView(name);
				applyToControlView(view, optionsMap);
			} catch(e){}

			// Repeater rows
			if (controlModel.get('type') === 'repeater') {
				const rows = controlModel.get('rows');
				if (rows && rows.each) {
					rows.each(function (rowModel) {
						const inner = rowModel.get('controls') || {};
						Object.keys(inner).forEach(function (innerName) {
							try {
								const view = pv.getRepeaterControlView(name, rowModel.cid, innerName);
								applyToControlView(view, optionsMap);
							} catch(e){}
						});
					});
				}
			}
		});

		// DOM last-resort: any raw selects injected later
		const $panel = $('.elementor-panel');
		TARGET_SELECTORS.forEach(sel => {
			$panel.find(sel).each(function(){
				const $sel = $(this);
				const prev = $sel.val();
				applyOptionsToSelect($sel, optionsMap, prev);
			});
		});
	}

	/* -------------------- UPDATE PIPELINE -------------------- */
	let debTimer = null;
	function scheduleUpdate(reason) {
		if (debTimer) clearTimeout(debTimer);
		debTimer = setTimeout(async function(){
			const pv = getPanelView();
			if (!pv || !pv.model) return;

			const menuId = currentMenuIdFromModel(pv.model);
			if (!menuId) {
				applyEverywhere([{ value:'', label:'' }]);
				return;
			}

			const nodes = await getNodes(menuId);
			const options = toOptions(nodes);
			applyEverywhere(options);
		}, 40);
	}

	/* -------------------- BINDINGS -------------------- */
	function bindModelObservers() {
		const pv = getPanelView();
		if (!pv || !pv.model) return;

		// Any model change can re-render controls
		pv.model.on('change', () => scheduleUpdate('model'));

		// Explicitly watch menu keys
		MENU_KEYS.forEach(k => {
			pv.model.on('change:' + k, () => {
				// clear cache when menu changes (fresh fetch)
				cache.clear();
				scheduleUpdate('menu-change');
			});
		});
	}

	function bindDomObservers() {
		const $panel = $('.elementor-panel');
		if (!$panel.length) return;

		// Reapply when a target select is about to open (Select2 hook)
		$panel.on('select2:opening', TARGET_SELECTORS.join(','), function(){
			scheduleUpdate('select2-opening');
		});

		// Repeater add/remove
		$panel.on('click', '.elementor-repeater-add, .elementor-repeater-remove', function(){
			setTimeout(() => scheduleUpdate('repeater'), 50);
		});

		// MutationObserver: whenever Elementor injects nodes, re-run
		const mo = new MutationObserver(() => scheduleUpdate('mutation'));
		mo.observe($panel.get(0), { childList: true, subtree: true });

		// Fallback: focus
		$panel.on('focus', TARGET_SELECTORS.join(','), function(){
			scheduleUpdate('focus');
		});
	}

	function bindMenuChangeDOM() {
		const $panel = $('.elementor-panel');
		MENU_SELECTORS.forEach(sel => {
			$panel.on('change', sel, function(){
				cache.clear();
				scheduleUpdate('menu-dom-change');
			});
		});
	}

	function boot() {
		// Run when a widget panel opens
		elementor.hooks.addAction('panel/open_editor/widget', function(){
			setTimeout(function(){
				bindModelObservers();
				bindDomObservers();
				bindMenuChangeDOM();
				scheduleUpdate('open');
			}, 0);
		});

		// If panel is already open (hot-reload)
		setTimeout(function(){
			bindModelObservers();
			bindDomObservers();
			bindMenuChangeDOM();
			scheduleUpdate('boot');
		}, 0);
	}

	// Start when Elementor editor is ready
	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		if (window.elementor && elementor.getPanelView) boot();
		else $(boot);
	} else {
		$(function(){
			if (window.elementor && elementor.getPanelView) boot();
			else setTimeout(boot, 200);
		});
	}
})(jQuery);
