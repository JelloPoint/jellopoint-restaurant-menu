(function(){
  'use strict';

  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }

  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  // Read the current "Menu" control (single SELECT with data-setting="menus")
  function readMenuId(panelRoot){
    const sel = panelRoot.querySelector('[data-setting="menus"]');
    if (!sel) return 0;
    const v = sel.value;
    const id = parseInt(v || 0, 10);
    return Number.isFinite(id) ? id : 0;
  }

  // The Data Source → Sections control (SELECT2 multiple)
  function dsSectionsSelect(panelRoot){
    // Prefer the “Data Source” group; fall back to any data-setting="sections"
    return panelRoot.querySelector('.elementor-control-sections [data-setting="sections"]')
        || panelRoot.querySelector('[data-setting="sections"]');
  }

  async function fetchSectionsMap(menuId){
    const body = new URLSearchParams();
    body.set('action', 'jprm_sections_by_menu');   // ← your PHP action
    body.set('menu', String(menuId || ''));        // ← your PHP expects 'menu'
    body.set('_ajax_nonce', ajaxNonce());          // ← your PHP expects '_ajax_nonce'

    let res;
    try {
      res = await fetch(ajaxUrl(), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
      });
    } catch (e) {
      log('AJAX network error', e);
      return {};
    }

    let json;
    try { json = await res.json(); } catch (e) {
      log('AJAX parse error, status=', res && res.status);
      return {};
    }

    if (!json || !json.success || !json.data || typeof json.data !== 'object') return {};
    return json.data; // { "id": "Label", ... } (tree-indented labels already)
  }

  function getSelectedValues(selectEl){
    // Use plain DOM to avoid jQuery dependency
    if (!selectEl) return [];
    const vals = [];
    for (const opt of selectEl.options) {
      if (opt.selected) vals.push(String(opt.value));
    }
    return vals;
  }

  function setSelectedValues(selectEl, values){
    const keep = new Set((values || []).map(String));
    for (const opt of selectEl.options) {
      opt.selected = keep.has(String(opt.value));
    }
    // Fire a change for select2 if present
    if (typeof jQuery !== 'undefined') { jQuery(selectEl).trigger('change', { silent:true }); }
  }

  function rebuildOptionsFromMap(selectEl, map){
    const selected = getSelectedValues(selectEl);
    const ids = Object.keys(map || {});
    // Clear
    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);
    // Rebuild with the already-indented labels coming from PHP
    ids.forEach(id => {
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = String(map[id] || '');
      selectEl.appendChild(opt);
    });
    // Reapply only those still present
    const kept = selected.filter(v => ids.includes(String(v)));
    setSelectedValues(selectEl, kept);
    log('DS applied', { kept, total: ids.length });
  }

  async function refreshDataSourceSections(panelRoot){
    const menuId = readMenuId(panelRoot);
    log('DS refresh', { menuId });
    const sel = dsSectionsSelect(panelRoot);
    if (!sel) { log('DS select not found'); return; }
    const map = await fetchSectionsMap(menuId);
    rebuildOptionsFromMap(sel, map);
  }

  function bindMenuChange(panelRoot){
    const menuSel = panelRoot.querySelector('[data-setting="menus"]');
    if (!menuSel) return;
    if (menuSel.__jprmBound) return;
    menuSel.__jprmBound = true;
    menuSel.addEventListener('change', function(){
      refreshDataSourceSections(panelRoot);
    });
  }

  function boot(){
    const panelRoot = document.querySelector('.elementor-panel');
    if (!panelRoot) { setTimeout(boot, 300); return; }

    log('sections-dep.js active');

    // Initial bind + first refresh
    bindMenuChange(panelRoot);
    refreshDataSourceSections(panelRoot);

    // Watch the panel for re-renders / tab switches
    const mo = new MutationObserver(() => {
      bindMenuChange(panelRoot);
      if (dsSectionsSelect(panelRoot)) {
        // Keep DS select in sync when the section control re-renders
        refreshDataSourceSections(panelRoot);
      }
    });
    mo.observe(panelRoot, { childList: true, subtree: true });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
