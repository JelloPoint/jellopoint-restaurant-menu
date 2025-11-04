(function ($) {
	'use strict';

	/* -------------------- CONFIG -------------------- */
	const MENU_KEYS = ['data_menu', 'menus']; // widget setting names for menu selection
	const TARGET_KEYS = [
		'sections',
		'layout_split_after_section',
		'layout_split_after_section2',
		// repeater inner control name (ends with [section_id] in DOM; in model it's just 'section_id')
		'section_id'
	];

	// DOM fallbacks (for safety)
	const MENU_SELECTORS = [
		'[data-setting="data_menu"]',
		'[data-setting="menus"]',
		'select[name$="[data_menu]"]',
		'select[name$="[menus]"]',
	];
	const TARGET_SELECTORS = [
		'[data-setting="sections"]',
		'[data-setting="layout_split_after_section"]',
		'[data-setting="layout_split_after_section2"]',
		'select[name*="section_layouts"][name$="[section_id]"]'
	];

	/* -------------------- UTIL -------------------- */
	const LOG = '[JPRM Sections UX]';
	function log(){ try{ console.log.apply(console, [LOG].concat([].slice.call(arguments))); }catch(e){} }

	function ajaxUrl() {
		if (window.JPRMSectionsUX && JPRMSectionsUX.ajaxUrl) return JPRMSectionsUX.ajaxUrl;
		if (typeof ajaxurl !== 'undefined') return ajaxurl;
		return '/wp-admin/admin-ajax.php';
	}
	function newNonce(){ return (window.JPRMSectionsUX && JPRMSectionsUX.nonce) ? JPRMSectionsUX.nonce : ''; }
	function legacyNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) ? JPRMAjax.nonce : ''; }

	function getPanelView() {
		try { return elementor.getPanelView().getCurrentPanelView(); } catch(e) { return null; }
	}

	function getSetting(model, keyList) {
		for (const k of keyList) {
			const v = model.getSetting ? model.getSetting(k) : model.get(k);
			if (v !== undefined && v !== null && String(v).length) return v;
		}
		return null;
	}

	function getCurrentMenuIdFromModel(model) {
		const v = getSetting(model, MENU_KEYS);
		if (!v) return 0;
		const val = Array.isArray(v) ? (v[0] || '') : v;
		return /^\d+$/.test(String(val)) ? parseInt(val, 10) : 0;
	}

	function toOptions(nodes) {
		// nodes: [{id,text,level,parent}, ...]
		const opts = [{ value: '', label: '' }];
		nodes.forEach(n => {
			const level = Number(n.level || 0);
			const indent = level > 0 ? Array(level + 1).join('— ') : '';
			opts.push({ value: String(n.id), label: indent + n.text });
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

	async function fetchNew(menuId) {
		const params = new URLSearchParams();
		params.set('action', 'jprm_sections_for_menu');
		params.set('nonce', newNonce());
		params.set('menu_id', String(menuId));
		const res = await fetch(ajaxUrl() + '?' + params.toString(), { method: 'GET', credentials: 'include' });
		const json = await res.json();
		if (!json || !json.success || !json.data || !Array.isArray(json.data.sections)) return null;
		return json.data.sections;
	}

	async function fetchLegacy(menuId) {
		const params = new URLSearchParams();
		params.set('action', 'jprm_sections_by_menu');
		params.set('menu', String(menuId));
		const ln = legacyNonce();
		if (ln) params.set('_ajax_nonce', ln);

		const res = await fetch(ajaxUrl(), {
			method: 'POST',
			credentials: 'include',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: params.toString()
		});
		const txt = await res.text();
		let json = null;
		try { json = JSON.parse(txt); } catch(e){ return null; }
		if (!json || !json.success || !json.data) return null;

		// Convert { id: "Label" } map to nodes[]
		const nodes = [];
		Object.keys(json.data).forEach(id => {
			nodes.push({ id: parseInt(id,10), text: String(json.data[id]||''), level: 0, parent: 0 });
		});
		return nodes;
	}

	async function getNodes(menuId) {
		if (!menuId) return [];
		if (cache.has(menuId)) return cache.get(menuId);
		let nodes = null;
		try { nodes = await fetchNew(menuId); } catch(e) { nodes = null; }
		if (!nodes) {
			try { nodes = await fetchLegacy(menuId); } catch(e) { nodes = null; }
		}
		if (!Array.isArray(nodes)) nodes = [];
		cache.set(menuId, nodes);
		return nodes;
	}

	/* -------------------- CORE APPLY -------------------- */

	// Apply to a single control view (top-level control)
	function applyToControlView(controlView, optionsMap) {
		if (!controlView || !controlView.model) return false;
		const name = controlView.model.get('name');
		if (!name) return false;

		const isTarget =
			TARGET_KEYS.includes(name) ||
			// repeater inner control often just called 'section_id'
			(name === 'section_id');

		if (!isTarget) return false;

		// Build options as {value:label} for Elementor control model
		const optsObj = {};
		optionsMap.forEach(o => { if (o.value !== undefined) optsObj[o.value] = o.label; });

		// Keep previous selection if possible
		let prev = null;
		try {
			const $sel = controlView.$el.find('select,[data-setting="'+name+'"]').first();
			prev = $sel.length ? $sel.val() : controlView.getControlValue();
		} catch(e){}

		controlView.model.set('options', optsObj);
		if (typeof controlView.render === 'function') controlView.render();

		// DOM safety update for select element (Select2)
		try {
			const $select = controlView.$el.find('select,[data-setting="'+name+'"]');
			if ($select.length) applyOptionsToSelect($select, optionsMap, prev);
		} catch(e){}

		return true;
	}

	// Apply to every control in the current panel, including repeater rows
	function applyToAllControls(pv, optionsMap) {
		if (!pv || !pv.collection) return;

		// Top-level controls
		pv.collection.each(function (controlModel) {
			const name = controlModel.get('name');
			// Direct view
			try {
				const view = pv.getControlView(name);
				applyToControlView(view, optionsMap);
			} catch(e){}

			// Repeater controls
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

		// DOM fallback for anything missed (e.g., freshly injected fields)
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
			const menuId = getCurrentMenuIdFromModel(pv.model);

			if (!menuId) {
				// Clear targets if no menu
				const emptyOpts = [{ value:'', label:'' }];
				applyToAllControls(pv, emptyOpts);
				return;
			}

			const nodes = await getNodes(menuId);
			const options = toOptions(nodes);
			applyToAllControls(pv, options);
		}, 60);
	}

	/* -------------------- BINDINGS -------------------- */
	function bindModelObservers() {
		const pv = getPanelView();
		if (!pv || !pv.model) return;

		// Any change on the widget model can cause re-render → re-apply
		pv.model.on('change', function(){ scheduleUpdate('model-change'); });

		// Explicitly watch menu-related keys
		MENU_KEYS.forEach(k => {
			pv.model.on('change:' + k, function(){ scheduleUpdate('menu-change'); });
		});
	}

	function bindDomObservers() {
		const $panel = $('.elementor-panel');
		if (!$panel.length) return;

		// Repeater add/remove
		$panel.on('click', '.elementor-repeater-add, .elementor-repeater-remove', function(){
			setTimeout(function(){ scheduleUpdate('repeater-change'); }, 50);
		});

		// When a target select gets focus (Select2 opens), ensure options are current
		$panel.on('focus', TARGET_SELECTORS.join(','), function(){
			scheduleUpdate('focus');
		});

		// MutationObserver as last resort
		const mo = new MutationObserver(function(muts){
			let hit = false;
			for (const m of muts) {
				if (m.type !== 'childList') continue;
				$(m.addedNodes).each(function(){
					const $n = $(this);
					if ($n.find('select').filter((i, el) => {
						const $el = $(el);
						return TARGET_SELECTORS.some(s => $el.is(s)) || MENU_SELECTORS.some(s => $el.is(s));
					}).length) {
						hit = true;
					}
				});
			}
			if (hit) scheduleUpdate('mutation');
		});
		mo.observe($panel.get(0), { childList: true, subtree: true });

		// Also bind direct menu selects in DOM (fallback)
		MENU_SELECTORS.forEach(sel => {
			$panel.on('change', sel, function(){ scheduleUpdate('menu-dom-change'); });
		});
	}

	function boot() {
		if (!window.elementor || !elementor.getPanelView) {
			// Editor not ready yet
			setTimeout(boot, 150);
			return;
		}

		// Run when a widget panel opens
		elementor.hooks.addAction('panel/open_editor/widget', function(panel, model, view){
			setTimeout(function(){
				bindModelObservers();
				bindDomObservers();
				scheduleUpdate('panel-open');
			}, 0);
		});

		// If already open (hot reload)
		setTimeout(function(){
			bindModelObservers();
			bindDomObservers();
			scheduleUpdate('boot');
		}, 0);
	}

	// Start
	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		boot();
	} else {
		$(boot);
	}
})(jQuery);
