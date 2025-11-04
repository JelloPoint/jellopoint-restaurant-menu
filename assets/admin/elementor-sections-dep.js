(function ($) {
	'use strict';

	/* -----------------------------------------------------------
	   Config: adjust selectors here if your control IDs differ
	----------------------------------------------------------- */
	const MENU_SELECTORS = [
		'[data-setting="menus"]',
		'[data-setting="data_menu"]',
		'select[name$="[menus]"]',
		'select[name$="[data_menu]"]',
	];

	const TARGET_SELECTORS = [
		// Data Source → single/multiple section pickers (if present)
		'[data-setting="sections"]',
		'select[name$="[sections]"]',

		// Layout → Split after section controls
		'[data-setting="layout_split_after_section"]',
		'[data-setting="layout_split_after_section2"]',
		'select[name$="[layout_split_after_section]"]',
		'select[name$="[layout_split_after_section2]"]',

		// Labels Layout Overrides repeater: ...section_layouts[*][section_id]
		'select[name*="section_layouts"][name$="[section_id]"]',
	];

	/* -----------------------------------------------------------
	   Utilities
	----------------------------------------------------------- */
	const LOG = '[JPRM UX]';
	function log(){ try{ console.log.apply(console, [LOG].concat([].slice.call(arguments))); }catch(e){} }

	function adminAjaxUrl() {
		if (window.JPRMSectionsUX && JPRMSectionsUX.ajaxUrl) return JPRMSectionsUX.ajaxUrl;
		if (typeof ajaxurl !== 'undefined') return ajaxurl;
		return '/wp-admin/admin-ajax.php';
	}
	function nonceForNew() {
		return (window.JPRMSectionsUX && JPRMSectionsUX.nonce) ? JPRMSectionsUX.nonce : '';
	}
	function nonceForLegacy() {
		return (window.JPRMAjax && JPRMAjax.nonce) ? JPRMAjax.nonce : '';
	}

	function getCurrentMenuId($root) {
		for (const sel of MENU_SELECTORS) {
			const $el = $root.find(sel).first();
			if ($el.length) {
				const v = Array.isArray($el.val()) ? $el.val()[0] : $el.val();
				if (v && String(v).match(/^\d+$/)) return parseInt(v, 10);
			}
		}
		// Try panel model (Elementor API)
		try {
			if (window.elementor && elementor.getPanelView) {
				const pv = elementor.getPanelView().getCurrentPanelView();
				if (pv && pv.model) {
					const m = pv.model.getSetting('data_menu') || pv.model.getSetting('menus');
					if (m && String(m).match(/^\d+$/)) return parseInt(m, 10);
				}
			}
		} catch (e) {}
		return 0;
	}

	/* -----------------------------------------------------------
	   AJAX (dual endpoint support) + cache
	----------------------------------------------------------- */
	const _cache = new Map(); // key: menuId -> [{id,text,level,parent},...]

	function indentLabel(node) {
		const level = Number(node.level || 0);
		const indent = level > 0 ? Array(level + 1).join('— ') : '';
		return indent + node.text;
	}

	async function fetchSectionsNew(menuId) {
		const url = adminAjaxUrl();
		const params = new URLSearchParams();
		params.set('action', 'jprm_sections_for_menu');
		params.set('nonce', nonceForNew());
		params.set('menu_id', String(menuId));

		const res = await fetch(url + '?' + params.toString(), { method: 'GET', credentials: 'include' });
		const json = await res.json();
		if (!json || !json.success || !json.data || !Array.isArray(json.data.sections)) return null;
		return json.data.sections; // [{id,text,level,parent}, ...]
	}

	async function fetchSectionsLegacy(menuId) {
		// Older endpoint your file used before: expects POST + _ajax_nonce
		const url = adminAjaxUrl();
		const params = new URLSearchParams();
		params.set('action', 'jprm_sections_by_menu');
		params.set('menu', String(menuId));
		const legacyNonce = nonceForLegacy();
		if (legacyNonce) params.set('_ajax_nonce', legacyNonce);

		const res = await fetch(url, {
			method: 'POST',
			credentials: 'include',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: params.toString()
		});
		const txt = await res.text();
		let json = null;
		try { json = JSON.parse(txt); } catch (e) {
			log('legacy parse fail', txt);
			return null;
		}
		if (!json || !json.success || !json.data) return null;

		// Legacy shape was likely { id: "Label", ... } (flat map). Convert to array.
		const arr = [];
		Object.keys(json.data).forEach((id) => {
			arr.push({ id: parseInt(id,10), text: String(json.data[id]||''), level: 0, parent: 0 });
		});
		return arr;
	}

	async function getSections(menuId) {
		if (!menuId) return [];
		if (_cache.has(menuId)) return _cache.get(menuId);

		let nodes = null;

		// Try new endpoint first
		try { nodes = await fetchSectionsNew(menuId); } catch (e) { nodes = null; }
		// Fallback to legacy if needed
		if (!nodes) {
			try { nodes = await fetchSectionsLegacy(menuId); } catch (e) { nodes = null; }
		}
		// Final fallback: empty
		if (!Array.isArray(nodes)) nodes = [];

		_cache.set(menuId, nodes);
		return nodes;
	}

	/* -----------------------------------------------------------
	   Repopulate selects
	----------------------------------------------------------- */
	function repopulateTargets($root, nodes) {
		// Build options [{value,label}]
		const opts = [{ value: '', label: '' }]; // blank option first
		nodes.forEach(n => {
			opts.push({ value: String(n.id), label: indentLabel(n) });
		});

		// Update each target select under $root
		TARGET_SELECTORS.forEach(sel => {
			$root.find(sel).each(function () {
				const $sel = $(this);
				const previous = $sel.val();

				$sel.find('option').remove();
				opts.forEach(o => {
					const opt = new Option(o.label, o.value, false, false);
					$sel.append(opt);
				});

				// keep previous value if still present
				if (previous && (!Array.isArray(previous) ? previous : previous[0])) {
					$sel.val(previous).trigger('change');
				} else {
					$sel.val('').trigger('change');
				}
			});
		});
	}

	/* -----------------------------------------------------------
	   Debounced updater that survives Elementor re-renders
	----------------------------------------------------------- */
	let _debounceTimer = null;
	function scheduleUpdate($scope) {
		if (_debounceTimer) clearTimeout(_debounceTimer);
		_debounceTimer = setTimeout(async function () {
			const $panel = getPanelRoot($scope);
			const menuId = getCurrentMenuId($panel);
			if (!menuId) return;
			const nodes  = await getSections(menuId);
			repopulateTargets($panel, nodes);
		}, 80);
	}

	function getPanelRoot($el) {
		// Elementor panel container
		const $panel = $('.elementor-panel');
		return $panel.length ? $panel : $(document.body);
	}

	/* -----------------------------------------------------------
	   Bindings
	----------------------------------------------------------- */

	function bindMenuChange($root) {
		// Bind to any of our menu selectors
		MENU_SELECTORS.forEach(sel => {
			$root.on('change', sel, function () {
				// clear cache for menu swap (in case different widget instance uses different submenu)
				scheduleUpdate($root);
			});
		});
	}

	function observePanelMutations() {
		const $panel = $('.elementor-panel');
		if (!$panel.length) { setTimeout(observePanelMutations, 300); return; }

		const observer = new MutationObserver((mutations) => {
			let touched = false;

			for (const m of mutations) {
				if (m.type !== 'childList') continue;

				// If a target select or a menu select was added, trigger update
				$(m.addedNodes).each(function () {
					const $n = $(this);

					// Trigger when our selects appear
					if ($n.is('select') && (matchesAny($n, TARGET_SELECTORS) || matchesAny($n, MENU_SELECTORS))) {
						touched = true;
					} else if ($n.find('select').filter((i, el) => matchesAny($(el), TARGET_SELECTORS) || matchesAny($(el), MENU_SELECTORS)).length) {
						touched = true;
					}
				});
			}

			if (touched) {
				scheduleUpdate($panel);
			}
		});

		observer.observe($panel.get(0), { childList: true, subtree: true });

		// Also update when a target select receives focus (some Elementor versions lazy-render options)
		$panel.on('focus', TARGET_SELECTORS.join(','), function () {
			scheduleUpdate($panel);
		});

		// Repeater add/remove buttons: re-run
		$panel.on('click', '.elementor-repeater-add, .elementor-repeater-remove', function () {
			setTimeout(() => scheduleUpdate($panel), 50);
		});
	}

	function matchesAny($el, selectors) {
		for (const s of selectors) {
			if ($el.is(s)) return true;
		}
		return false;
	}

	function boot() {
		const $panel = getPanelRoot($(document));

		// Initial fill (when widget panel opens)
		scheduleUpdate($panel);

		// Bind handlers
		bindMenuChange($panel);
		observePanelMutations();
	}

	// Elementor: init when editor loads the panel
	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		boot();
	} else {
		$(boot);
	}
})(jQuery);
