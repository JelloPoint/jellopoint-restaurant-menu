(function() {
	'use strict';

	function qs(id){ return document.getElementById(id); }
	function onReady(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }

	function isItemsList(){
		var b = document.body;
		return b && b.classList.contains('wp-admin') && b.classList.contains('post-type-jprm_menu_item');
	}

	function buildURLWithParams(params){
		var url = new URL(window.location.href);
		Object.keys(params).forEach(function(k){
			if (params[k] === '' || params[k] === '0' || params[k] == null) {
				url.searchParams.delete(k);
			} else {
				url.searchParams.set(k, params[k]);
			}
		});
		url.searchParams.delete('paged'); // reset pagination on filter change
		return url;
	}

	function replaceListFromHTML(html){
		var doc = new DOMParser().parseFromString(html, 'text/html');

		var newBody = doc.querySelector('#the-list');
		var curBody = document.getElementById('the-list');
		if (newBody && curBody) curBody.innerHTML = newBody.innerHTML;

		var newTopPag = doc.querySelector('.tablenav.top .tablenav-pages');
		var curTopPag = document.querySelector('.tablenav.top .tablenav-pages');
		if (newTopPag && curTopPag) curTopPag.innerHTML = newTopPag.innerHTML;

		var newBotPag = doc.querySelector('.tablenav.bottom .tablenav-pages');
		var curBotPag = document.querySelector('.tablenav.bottom .tablenav-pages');
		if (newBotPag && curBotPag) curBotPag.innerHTML = newBotPag.innerHTML;

		var newViews = doc.querySelector('.subsubsub');
		var curViews = document.querySelector('.subsubsub');
		if (newViews && curViews) curViews.innerHTML = newViews.innerHTML;
	}

	function ajaxRefresh(menuSel, secSel){
		var url = buildURLWithParams({
			post_type: 'jprm_menu_item',
			[JPRM_ITEMS.qs.menu]: menuSel ? (menuSel.value || '0') : '0',
			[JPRM_ITEMS.qs.section]: secSel ? (secSel.value || '0') : '0'
		});

		fetch(url.toString(), {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
		.then(function(r){ return r.text(); })
		.then(function(html){
			replaceListFromHTML(html);
			if (history && history.replaceState) history.replaceState({}, '', url.toString());
		})
		.catch(function(){
			var form = document.getElementById('posts-filter');
			if (form) form.submit(); // graceful fallback
		});
	}

	function populateSections(menuId, keepValue){
		var secSel = qs(JPRM_ITEMS.qs.section);
		if (!secSel) return Promise.resolve();

		// current selection from URL (for keeping selection on load)
		var currentFromURL = new URL(window.location.href).searchParams.get(JPRM_ITEMS.qs.section) || '0';

		// Reset to "All Sections" while loading
		secSel.innerHTML = '';
		var opt = document.createElement('option');
		opt.value = '0';
		opt.textContent = JPRM_ITEMS.labels.allSections || 'All Sections';
		secSel.appendChild(opt);

		// If no menu selected, done.
		if (!menuId || menuId === '0') {
			// Keep 'All' selected
			secSel.value = '0';
			return Promise.resolve();
		}

		// Build REST URL
		var url = new URL(JPRM_ITEMS.rest.sectionsByMenu);
		url.searchParams.set('menu_id', menuId);

		return fetch(url.toString(), {
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'X-WP-Nonce': JPRM_ITEMS.rest.nonce
			}
		})
		.then(function(r){ return r.json(); })
		.then(function(data){
			if (!Array.isArray(data)) return;

			data.forEach(function(s){
				var o = document.createElement('option');
				o.value = String(s.term_id);
				o.textContent = s.name;
				secSel.appendChild(o);
			});

			// Restore previous section if it still exists and keepValue is true
			if (keepValue) {
				var desired = currentFromURL;
				if (desired && secSel.querySelector('option[value="'+desired+'"]')) {
					secSel.value = desired;
				} else {
					secSel.value = '0';
				}
			} else {
				secSel.value = '0';
			}
		})
		.catch(function(){
			// Leave it as "All Sections" if REST fails
			secSel.value = '0';
		});
	}

	function bindHandlers(){
		var menuSel = qs(JPRM_ITEMS.qs.menu);
		var secSel  = qs(JPRM_ITEMS.qs.section);
		if (!menuSel || !secSel) return;

		// Initial population on page load (keep current section if valid)
		populateSections(menuSel.value || '0', /*keepValue=*/true).then(function(){
			// If page loaded with menu but section not valid, it will be reset to '0'.
		});

		menuSel.addEventListener('change', function(){
			populateSections(menuSel.value, /*keepValue=*/false).then(function(){
				ajaxRefresh(menuSel, secSel);
			});
		});

		secSel.addEventListener('change', function(){
			ajaxRefresh(menuSel, secSel);
		});
	}

	onReady(function(){
		if (!isItemsList()) return;
		bindHandlers();
	});
})();
