(function ($) {
	'use strict';

	/* ------------------ CONFIG ------------------ */
	// Menu control keys / selectors (try both you’ve used)
	const MENU_KEYS = ['data_menu', 'menus'];
	const MENU_SELECTORS = [
		'[data-setting="data_menu"]',
		'[data-setting="menus"]',
		'select[name$="[data_menu]"]',
		'select[name$="[menus]"]',
	];

	// Targets we must repopulate. We’ll also match any select whose name contains "section".
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

	/* ------------------ UTIL ------------------ */
	const LOG = '[JPRM FORCE]';
	function log(){ try{ console.log.apply(console, [LOG].concat([].slice.call(arguments))); }catch(e){} }

	function ajaxUrl() {
		if (window.JPRMSectionsUX && JPRMSectionsUX.ajaxUrl) return JPRMSectionsUX.ajaxUrl;
		if (typeof ajaxurl !== 'undefined') return ajaxurl;
		return '/wp-admin/admin-ajax.php';
	}
	function ajaxNonce(){ return (window.JPRMSectionsUX && JPRMSectionsUX.nonce) ? JPRMSectionsUX.nonce : ''; }

	function getPanelRoot() {
		const $panel = $('.elementor-panel');
		return $panel.length ? $panel : $(document.body);
	}

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

	function currentMenuId() {
		// Prefer model
		try {
			const pv = getPanelView();
			if (pv && pv.model) {
				const v = getSetting(pv.model, MENU_KEYS);
				const first = Array.isArray(v) ? (v[0] || '') : v;
				if (/^\d+$/.test(String(first))) return parseInt(first, 10);
			}
		} catch(e){}

		// Fallback DOM
		const $panel = getPanelRoot();
		for (const sel of MENU_SELECTORS) {
			const $el = $panel.find(sel).first();
			if ($el.length) {
				const v = Array.isArray($el.val()) ? $el.val()[0] : $el.val();
				if (/^\d+$/.test(String(v))) return parseInt(v, 10);
			}
		}
		return 0;
	}

	/* ------------------ FETCH + CACHE ------------------ */
	const cache = new Map(); // menuId -> nodes[{id,text,level,parent}]

	async function fetchNodes(menuId) {
		const url = ajaxUrl();
		const params = new URLSearchParams();
		params.set('action', 'jprm_sections_for_menu');
		params.set('nonce', ajaxNonce());
		params.set('menu_id', String(menuId));

		const res = await fetch(url + '?' + params.toString(), { method: 'GET', credentials: 'include' });
		const json = await res.json().catch(() => null);
		if (!json || !json.success || !json.data || !Array.isArray(json.data.sections)) {
			log('AJAX failed/new endpoint empty; menu=', menuId, json);
			return [];
		}
		return json.data.sections;
	}

	async function getNodes(menuId) {
		if (!menuId) return [];
		if (cache.has(menuId)) return cache.get(menuId);
		const nodes = await fetchNodes(menuId);
		cache.set(menuId, nodes);
		return nodes;
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

	function applyOptionsToSelect($select, opts) {
		const prev = $select.val();
		$select.find('option').remove();
		opts.forEach(o => $select.append(new Option(o.label, o.value, false, false)));
		// keep previous if still valid
		if (prev && (Array.isArray(prev) ? prev.length : prev)) {
			$select.val(prev).trigger('change');
		} else {
			$select.val('').trigger('change');
		}
	}

	/* ------------------ CORE UPDATE ------------------ */
	function looksLikeSectionSelect($sel) {
		// Match explicit targets or name/data-setting containing "section"
		if (TARGET_SELECTORS.some(s => $sel.is(s))) return true;
		const ds = ($sel.attr('data-setting') || '').toLowerCase();
		const name = ($sel.attr('name') || '').toLowerCase();
		return /section/.test(ds) || /section/.test(name);
	}

	function applyEverywhere(optionsMap) {
		const $panel = getPanelRoot();

		// 1) Try model-backed controls (if available)
		try {
			const pv = getPanelView();
			if (pv && pv.collection) {
				const optsObj = {};
				optionsMap.forEach(o => { if (o.value !== undefined) optsObj[o.value] = o.label; });

				pv.collection.each(function (controlModel) {
					const name = controlModel.get('name');

					// top-level targets
					if (TARGET_KEYS.includes(name) || name === 'section_id') {
						try {
							const view = pv.getControlView(name);
							if (view && view.model) {
								let prev = null;
								try {
									const $sel = view.$el.find('select,[data-setting="'+name+'"]').first();
									prev = $sel.length ? $sel.val() : view.getControlValue();
								} catch(e){}
								view.model.set('options', optsObj);
								if (typeof view.render === 'function') view.render();
								const $select = view.$el.find('select,[data-setting="'+name+'"]');
								if ($select.length) applyOptionsToSelect($select, optionsMap);
							}
						} catch(e){}
					}

					// repeater inner controls
					if (controlModel.get('type') === 'repeater') {
						const rows = controlModel.get('rows');
						if (rows && rows.each) {
							rows.each(function (rowModel) {
								const inner = rowModel.get('controls') || {};
								Object.keys(inner).forEach(function (innerName) {
									if (TARGET_KEYS.includes(innerName) || innerName === 'section_id') {
										try {
											const view = pv.getRepeaterControlView(name, rowModel.cid, innerName);
											if (view && view.model) {
												view.model.set('options', optsObj);
												if (typeof view.render === 'function') view.render();
												const $select = view.$el.find('select,[data-setting="'+innerName+'"]');
												if ($select.length) applyOptionsToSelect($select, optionsMap);
											}
										} catch(e){}
									}
								});
							});
						}
					}
				});
			}
		} catch(e){}

		// 2) DOM last-resort: ANY select that looks like a section picker
		$panel.find('select').each(function(){
			const $sel = $(this);
			if (!looksLikeSectionSelect($sel)) return;
			applyOptionsToSelect($sel, optionsMap);
		});
	}

	let pumpTimer = null;
	function pumpUpdate(label) {
		// Run a short “pump” after changes: 6 shots over ~1.2s to beat late re-renders
		if (pumpTimer) clearInterval(pumpTimer);

		let shots = 0;
		pumpTimer = setInterval(async function(){
			shots++;
			const mid = currentMenuId();
			if (!mid) return;
			const nodes = await getNodes(mid);
			const options = toOptions(nodes);
			log('update', label, 'shot', shots, 'menu', mid, options.length, 'opts');
			applyEverywhere(options);
			if (shots >= 6) { clearInterval(pumpTimer); pumpTimer = null; }
		}, 200);
	}

	/* ------------------ BINDINGS ------------------ */
	function bindAll() {
		const $panel = getPanelRoot();

		// When widget panel opens
		elementor.hooks.addAction('panel/open_editor/widget', function(){
			pumpUpdate('panel-open');
		});

		// Model changes (tabs/controls), if API present
		try {
			const pv = getPanelView();
			if (pv && pv.model) {
				pv.model.on('change', () => pumpUpdate('model-change'));
				MENU_KEYS.forEach(k => pv.model.on('change:' + k, () => { cache.clear(); pumpUpdate('menu-change'); }));
			}
		} catch(e){}

		// DOM: menu selects change
		MENU_SELECTORS.forEach(sel => {
			$panel.on('change', sel, function(){ cache.clear(); pumpUpdate('menu-dom-change'); });
		});

		// Repeater buttons
		$panel.on('click', '.elementor-repeater-add, .elementor-repeater-remove', function(){
			setTimeout(() => pumpUpdate('repeater'), 50);
		});

		// When a select is about to open (Select2), repopulate right before user sees it
		$panel.on('select2:opening focus', TARGET_SELECTORS.join(','), function(){
			pumpUpdate('opening/focus');
		});

		// MutationObserver: any injected nodes → repop
		const mo = new MutationObserver(function(muts){
			let hit = false;
			for (const m of muts) {
				if (m.type !== 'childList') continue;
				if (m.addedNodes && m.addedNodes.length) { hit = true; break; }
			}
			if (hit) pumpUpdate('mutation');
		});
		const root = $panel.get(0);
		if (root) mo.observe(root, { childList: true, subtree: true });

		// One initial run
		pumpUpdate('boot');
	}

	function boot() {
		log('boot');
		if (!window.elementor || !elementor.getPanelView) {
			setTimeout(boot, 150);
			return;
		}
		bindAll();
		// expose manual trigger for debugging
		window.JPRM_FORCE_SECTIONS = function(){ pumpUpdate('manual'); };
	}

	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		boot();
	} else {
		$(boot);
	}
})(jQuery);
