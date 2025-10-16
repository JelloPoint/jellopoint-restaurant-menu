(function ($) {
	'use strict';

	const LOG_PREFIX = '[JPRM]';
	function log() {
		try { console.log.apply(console, [LOG_PREFIX].concat([].slice.call(arguments))); } catch(e){}
	}

	/** REST fetch helper */
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
			if (!res.ok) { log('REST non-OK', res.status); return {}; }
			const data = await res.json();
			return data && typeof data === 'object' ? data : {};
		} catch (e) {
			log('REST error', e);
			return {};
		}
	}

	/** Apply options to the Sections control (model-first; fallback to DOM) */
	function applySectionsOptions($panel, optionsMap) {
		try {
			// Try to find the control view through elementor panel if available
			let controlView = null;
			if (window.elementor && elementor.getPanelView) {
				const panelView = elementor.getPanelView();
				if (panelView && panelView.getCurrentPanelView) {
					const current = panelView.getCurrentPanelView();
					if (current && current.getControlView) {
						controlView = current.getControlView('sections');
					}
				}
			}

			const map = optionsMap || {};
			const ids = Object.keys(map);

			// Determine current selection; keep only values still present.
			let keep = [];
			try {
				if (window.elementor && elementor.getPanelView) {
					const pv = elementor.getPanelView().getCurrentPanelView();
					const currentVal = pv.model.getSetting('sections');
					if (Array.isArray(currentVal)) {
						keep = currentVal.filter(v => ids.includes(String(v)));
					}
				}
			} catch(e){}

			if (controlView && controlView.model) {
				controlView.model.set('options', map);
				// Also set on the widget model:
				const pv = elementor.getPanelView().getCurrentPanelView();
				pv.model.setSetting('sections', keep);
				// Re-render control:
				if (typeof controlView.render === 'function') controlView.render();
				// Sync Select2:
				const $sel = controlView.$el.find('[data-setting="sections"]');
				if ($sel.length) $sel.val(keep).trigger('change');
				log('Applied via controlView. Options:', map, 'Keep:', keep);
				return;
			}

			// Fallback: DOM manipulation
			const $sel = $panel.find('[data-setting="sections"]');
			if ($sel.length) {
				const selected = ($sel.val() || []).filter(v => ids.includes(String(v)));
				$sel.find('option').remove();
				ids.forEach(function (id) {
					$sel.append(new Option(map[id], id, false, selected.includes(id)));
				});
				$sel.val(selected).trigger('change');
				log('Applied via DOM. Options:', map, 'Keep:', selected);
			} else {
				log('Sections select not found in panel');
			}
		} catch (e) {
			log('applySectionsOptions error', e);
		}
	}

	/** Read current Menu value from panel DOM or model */
	function getMenuValue($panel) {
		// Prefer model if available
		try {
			if (window.elementor && elementor.getPanelView) {
				const pv = elementor.getPanelView().getCurrentPanelView();
				const val = pv.model.getSetting('menus');
				return Array.isArray(val) ? (val[0] || '') : (val || '');
			}
		} catch(e){}
		// Fallback: DOM
		const $menu = $panel.find('[data-setting="menus"]');
		const v = $menu.val();
		return Array.isArray(v) ? (v[0] || '') : (v || '');
	}

	/** Handle changes (fetch + apply) */
	async function refreshSectionsForPanel($panel) {
		const mid = getMenuValue($panel);
		if (!mid) {
			log('No menu selected — clearing Sections');
			applySectionsOptions($panel, {});
			return;
		}
		log('Fetching sections for menu:', mid);
		const map = await fetchSections(mid);
		applySectionsOptions($panel, map);
	}

	/** Attach change listener to Menu select inside this panel */
	function bindMenuChange($panel) {
		const $menu = $panel.find('[data-setting="menus"]');
		if (!$menu.length) {
			log('Menu select not found yet, will poll…');
			let tries = 0;
			const iv = setInterval(() => {
				const $m = $panel.find('[data-setting="menus"]');
				if ($m.length || tries++ > 40) {
					clearInterval(iv);
					if ($m.length) {
						log('Menu select found (late). Binding change.');
						$m.on('change', () => refreshSectionsForPanel($panel));
					}
				}
			}, 250);
			return;
		}
		log('Binding menu change.');
		$menu.on('change', () => refreshSectionsForPanel($panel));
	}

	/**
	 * MutationObserver: watch the Elementor right panel, detect when our widget panel appears,
	 * then hook up dependency logic.
	 */
	function startObservingPanel() {
		const $panelRoot = $('.elementor-panel'); // main editor panel container
		if (!$panelRoot.length) {
			log('Elementor panel root not found; will retry…');
			setTimeout(startObservingPanel, 500);
			return;
		}
		log('Panel root found, starting observer.');

		const observer = new MutationObserver((mutations) => {
			for (const m of mutations) {
				if (m.type !== 'childList') continue;
				const $added = $(m.addedNodes);
				$added.each(function () {
					const $node = $(this);
					// widget settings panel has data-widget_type attribute; check for our widget type.
					// Elementor often wraps controls in .elementor-control-* inside the panel; we detect by presence of our controls.
					const hasOurControls =
						$node.find('[data-setting="menus"]').length &&
						$node.find('[data-setting="sections"]').length;

					// Also try to detect by header title text containing our widget name (fallback).
					const titleText = $node.find('.elementor-panel-heading-title').text() || '';
					const isOursByTitle = /Restaurant Menu \(JelloPoint\)/i.test(titleText);

					if (hasOurControls || isOursByTitle) {
						log('Our widget panel detected.');
						// Bind change and do initial refresh.
						bindMenuChange($node);
						refreshSectionsForPanel($node);
					}
				});
			}
		});

		observer.observe($panelRoot.get(0), { childList: true, subtree: true });

		// Also run once in case the panel is already open when we start.
		const $existingPanels = $panelRoot.find('[data-setting="menus"]').closest('.elementor-control').closest('.elementor-controls-stack, .elementor-panel');
		if ($existingPanels.length) {
			log('Existing panel detected on load.');
			const $p = $panelRoot; // apply to whole panel root; refresh will scope by selectors
			bindMenuChange($p);
			refreshSectionsForPanel($p);
		}
	}

	/** Boot: ensure we’re in the editor */
	function boot() {
		log('Boot script loaded.');
		startObservingPanel();
	}

	// Run when editor assets are ready
	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		boot();
	} else {
		$(boot);
	}
})(jQuery);
