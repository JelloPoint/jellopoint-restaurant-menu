(function ($) {
	'use strict';

	const LOG = '[JPRM]';
	function log(){ try{ console.log.apply(console, [LOG].concat([].slice.call(arguments))); }catch(e){} }

	function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) ? JPRMAjax.url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
	function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) ? JPRMAjax.nonce : ''; }

	/** Fetch sections via admin-ajax; ALWAYS falls back to menu='' (ALL sections) if anything fails */
	async function fetchSections(menuId) {
		const url = ajaxUrl();
		const nonce = ajaxNonce();
		const params = new URLSearchParams();
		params.set('action', 'jprm_sections_by_menu');
		if (menuId !== undefined && menuId !== null) params.set('menu', menuId);
		if (nonce) params.set('_ajax_nonce', nonce);

		try {
			const res = await fetch(url, {
				method: 'POST',
				credentials: 'include',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: params.toString()
			});
			const txt = await res.text();
			let data = {};
			try { data = JSON.parse(txt); } catch (e) {
				log('AJAX parse error; raw payload:', txt);
				// Final fallback: ask for ALL sections explicitly (no menu)
				if (menuId) return await fetchSections('');
				return {};
			}
			if (!data || !data.success) {
				log('AJAX success=false; payload:', data);
				if (menuId) return await fetchSections('');
				return {};
			}
			const map = data.data || {};
			if (!map || typeof map !== 'object' || Object.keys(map).length === 0) {
				log('AJAX returned empty map for menu=', menuId, '→ requesting ALL');
				if (menuId) return await fetchSections('');
			}
			return map || {};
		} catch (e) {
			log('AJAX error', e);
			if (menuId) return await fetchSections('');
			return {};
		}
	}

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
					if (Array.isArray(currentVal)) keep = currentVal.filter(v => ids.includes(String(v)));
				}
			} catch(e){}

			if (controlView && controlView.model) {
				controlView.model.set('options', map);
				const pv = elementor.getPanelView().getCurrentPanelView();
				pv.model.setSetting('sections', keep);
				if (typeof controlView.render === 'function') controlView.render();
				const $sel = controlView.$el.find('[data-setting="sections"]');
				if ($sel.length) $sel.val(keep).trigger('change');
				log('Applied via controlView', { options: map, keep });
				return;
			}

			// DOM fallback
			const $sel = $panel.find('[data-setting="sections"]');
			if ($sel.length) {
				const selected = ($sel.val() || []).filter(v => ids.includes(String(v)));
				$sel.find('option').remove();
				ids.forEach(function (id) {
					$sel.append(new Option(map[id], id, false, selected.includes(id)));
				});
				$sel.val(selected).trigger('change');
				log('Applied via DOM', { options: map, keep: selected });
			} else {
				log('Sections select not found in panel');
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
		log('Refreshing sections for menu=', mid);
		const map = await fetchSections(mid);
		applySectionsOptions($panel, map);
	}

	function bindMenuChange($panel) {
		const $menu = $panel.find('[data-setting="menus"]');
		if (!$menu.length) {
			let tries = 0;
			const iv = setInterval(() => {
				const $m = $panel.find('[data-setting="menus"]');
				if ($m.length || tries++ > 40) {
					clearInterval(iv);
					if ($m.length) $m.on('change', () => refreshSectionsForPanel($panel));
				}
			}, 250);
			return;
		}
		$menu.on('change', () => refreshSectionsForPanel($panel));
	}

	function startObservingPanel() {
		const $panelRoot = $('.elementor-panel');
		if (!$panelRoot.length) {
			setTimeout(startObservingPanel, 500);
			return;
		}

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
						log('Widget panel detected');
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
			const $p = $panelRoot;
			bindMenuChange($p);
			refreshSectionsForPanel($p);
		}
	}

	function boot() {
		log('Editor JS boot');
		startObservingPanel();
	}

	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		boot();
	} else {
		$(boot);
	}
})(jQuery);
