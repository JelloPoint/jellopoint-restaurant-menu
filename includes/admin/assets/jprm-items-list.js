(function() {
	'use strict';

	function qs(id){ return document.getElementById(id); }

	function onReady(fn){
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	function isItemsList(){
		var body = document.body;
		return body && body.classList.contains('post-type-jprm_menu_item') &&
		       body.classList.contains('wp-admin');
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
		// Reset pagination on filter change
		url.searchParams.delete('paged');
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

	function ajaxRefresh(){
		var menuSel = qs(JPRM_ITEMS.qs.menu);
		var secSel  = qs(JPRM_ITEMS.qs.section);
		if (!menuSel || !secSel) return;

		var url = buildURLWithParams({
			post_type: 'jprm_menu_item',
			[JPRM_ITEMS.qs.menu]: menuSel.value || '0',
			[JPRM_ITEMS.qs.section]: secSel.value || '0'
		});

		fetch(url.toString(), {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
		.then(function(r){ return r.text(); })
		.then(function(html){
			replaceListFromHTML(html);
			if (history && history.replaceState) {
				history.replaceState({}, '', url.toString());
			}
		})
		.catch(function(){
			// Fallback: submit the form normally
			var form = document.getElementById('posts-filter');
			if (form) form.submit();
		});
	}

	function populateSections(menuId){
		var secSel = qs(JPRM_ITEMS.qs.section);
		if (!secSel) return Promise.resolve();

		// Clear and show "All Sections" while loading
		secSel.innerHTML = '';
		var opt = document.createElement('option');
		opt.value = '0';
		opt.textContent = JPRM_ITEMS.labels.allSections || 'All Sections';
		secSel.appendChild(opt);

		// Build REST URL
		var url = new URL(JPRM_ITEMS.rest.sectionsByMenu);
		url.searchParams.set('menu_id', menuId || '0');

		return fetch(url.toString(), {
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'X-WP-Nonce': JPRM_ITEMS.rest.nonce
			}
		})
		.then(function(r){ return r.json(); })
		.then(function(data){
			// Expecting array of sections { term_id, name }
			if (!Array.isArray(data)) return;
			data.forEach(function(s){
				var o = document.createElement('option');
				o.value = String(s.term_id);
				o.textContent = s.name;
				secSel.appendChild(o);
			});
		})
		.catch(function(){
			/* keep "All Sections" only if REST fails */
		});
	}

	function bindHandlers(){
		var menuSel = qs(JPRM_ITEMS.qs.menu);
		var secSel  = qs(JPRM_ITEMS.qs.section);
		if (!menuSel || !secSel) return;

		menuSel.addEventListener('change', function(){
			populateSections(menuSel.value).then(function(){
				// reset section to All on menu change
				secSel.value = '0';
				ajaxRefresh();
			});
		});

		secSel.addEventListener('change', function(){
			ajaxRefresh();
		});
	}

	onReady(function(){
		if (!isItemsList()) return;
		bindHandlers();
	});
})();
