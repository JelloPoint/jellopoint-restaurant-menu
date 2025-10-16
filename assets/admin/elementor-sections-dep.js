(function ($) {
	'use strict';

	const LOG_PREFIX = '[JPRM]';
	function log(){ try{ console.log.apply(console, [LOG_PREFIX].concat([].slice.call(arguments))); }catch(e){} }

	/** AJAX fetch helper (admin-ajax.php) */
	async function fetchSections(menuId) {
		if (!menuId) return {};
		try {
			const params = new URLSearchParams();
			params.set('action', 'jprm_sections_by_menu');
			params.set('menu', menuId);
			params.set('_ajax_nonce', (window.JPRMAjax && JPRMAjax.nonce) ? JPRMAjax.nonce : '');

			const res = await fetch((window.JPRMAjax ? JPRMAjax.url : ajaxurl), {
				method: 'POST',
				credentials: 'include',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: params.toString()
			});
			if (!res.ok) { log('AJAX non-OK', res.status); return {}; }
			const data = await res.json();
			if (!data || !data.success) { log('AJAX payload not success', data); return {}; }
			const map = data.data || {};
			return (map && typeof map === 'object') ? map : {};
		} catch (e) {
			log('AJAX error', e);
			return {};
		}
	}

	/** Apply options to the Sections control (model-first; fallback DOM) */
	function applySectionsOptions($panel, optionsMap) {
		try {
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
				const pv = elementor.getPanelView().getCurrentPanelView();
				pv.model.setSetting('sections', keep);
				if (typeof controlView.render === 'function') controlView.render();
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
			}
		} catch (e) {
			log('applySectionsOptions error', e);
		}
	}

	function getMenuValue($panel) {
		try {
			if (window.elementor && elementor.getPanelView) {
				const pv = elementor.getPanelView().getCurrentPanelView();
				const val = pv.model.getSetting('menus');
				return Array.isArray(val) ? (val[0] || '') : (val || '');
			}
		} catch(e){}
		const $menu = $panel.find('[data-setting="menus"]');
		const v = $menu.val();
		return Array.isArray(v) ? (v[0] || '') : (v || '');
	}

	async function refreshSectionsForPanel($panel) {
		const mid = getMenuValue($panel);
		if (!mid) {
			log('No menu selected — clearing Sections');
			applySectionsOptions($panel, {});
			return;
		}
		log('AJAX fetching sections for menu:', mid);
		const map = await fetchSections(mid);
		applySectionsOptions($panel, map);
	}

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
					const hasOurControls =
						$node.find('[data-setting="menus"]').length &&
						$node.find('[data-setting="sections"]').length;
					const titleText = $node.find('.elementor-panel-heading-title').text() || '';
					const isOursByTitle = /Restaurant Menu \(JelloPoint\)/i.test(titleText);

					if (hasOurControls || isOursByTitle) {
						log('Our widget panel detected.');
						bindMenuChange($node);
						refreshSectionsForPanel($node);
					}
				});
			}
		});

		observer.observe($panelRoot.get(0), { childList: true, subtree: true });

		// If panel already open
		const $existing = $panelRoot.find('[data-setting="menus"]');
		if ($existing.length) {
			log('Existing panel detected on load.');
			const $p = $panelRoot;
			bindMenuChange($p);
			refreshSectionsForPanel($p);
		}
	}

	function boot() {
		log('Boot script (AJAX) loaded.');
		startObservingPanel();
	}

	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		boot();
	} else {
		$(boot);
	}
})(jQuery);
