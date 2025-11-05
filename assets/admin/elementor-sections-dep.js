(function(){
  'use strict';

  /* =========================
     Tiny logger
     ========================= */
  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }

  /* =========================
     AJAX helpers (names must match PHP)
     ========================= */
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  /* =========================
     Panel + controls helpers
     ========================= */
  function panelRoot(){
    return document.querySelector('.elementor-panel');
  }
  function menuSelect(root){
    return root && root.querySelector('[data-setting="menus"]');
  }
  function dsSectionsSelect(root){
    // Data Source → Sections (SELECT2 multiple)
    return root && (root.querySelector('.elementor-control-sections [data-setting="sections"]') || root.querySelector('[data-setting="sections"]'));
  }
  function readMenuId(root){
    const el = menuSelect(root);
    if (!el) return 0;
    const v = el.value;
    const id = parseInt(v || 0, 10);
    return Number.isFinite(id) ? id : 0;
  }
  function getSelectedValues(selectEl){
    const vals = [];
    if (!selectEl) return vals;
    for (const opt of selectEl.options) if (opt.selected) vals.push(String(opt.value));
    return vals;
  }
  function setSelectedValues(selectEl, values){
    const keep = new Set((values||[]).map(String));
    for (const opt of selectEl.options) opt.selected = keep.has(String(opt.value));
    if (typeof jQuery !== 'undefined') { jQuery(selectEl).trigger('change', { silent:true }); }
  }

  /* =========================
     State & guards
     ========================= */
  const state = {
    lastMenuId: null,
    lastSig: '',        // signature of options to avoid self-trigger loops
    inflight: null,     // AbortController for in-flight request
    debounceTimer: null
  };

  function optionsSignature(map){
    // compact signature used to detect “same” options; order-stable
    if (!map || typeof map !== 'object') return '';
    const keys = Object.keys(map);
    keys.sort();
    // include label length to detect simple label changes without huge strings
    return keys.map(k => k + ':' + String(map[k]||'').length).join('|');
  }

  /* =========================
     Fetch + apply (with race & change guards)
     ========================= */
  async function fetchSectionsMap(menuId){
    if (state.inflight) {
      try { state.inflight.abort(); } catch(e){}
      state.inflight = null;
    }
    const ctl = new AbortController();
    state.inflight = ctl;

    const body = new URLSearchParams();
    body.set('action', 'jprm_sections_by_menu');
    body.set('menu', String(menuId || ''));
    body.set('_ajax_nonce', ajaxNonce());

    let res, json;
    try {
      res = await fetch(ajaxUrl(), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
        signal: ctl.signal
      });
      json = await res.json();
    } catch (e) {
      if (e && e.name === 'AbortError') return null; // superseded by newer refresh
      log('AJAX error', e);
      return {};
    } finally {
      // clear inflight only if this request is the current one
      if (state.inflight === ctl) state.inflight = null;
    }

    if (!json || !json.success || !json.data || typeof json.data !== 'object') return {};
    return json.data; // { "id": "Label", ... } (already tree-indented by PHP)
  }

  function rebuildOptionsIfChanged(selectEl, map){
    if (!selectEl) return false;

    const sig = optionsSignature(map);
    const prevSig = selectEl.getAttribute('data-jprm-sig') || '';
    if (sig === prevSig) {
      // nothing changed -> do not touch DOM (prevents observer loops)
      return false;
    }

    // Preserve current selection where possible
    const selected = getSelectedValues(selectEl);
    const ids = Object.keys(map || {});

    // Rebuild
    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);
    ids.forEach(id => {
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = String(map[id] || '');
      selectEl.appendChild(opt);
    });

    // Re-apply selection intersect
    const kept = selected.filter(v => ids.includes(String(v)));
    setSelectedValues(selectEl, kept);

    // Store signature to avoid loops
    selectEl.setAttribute('data-jprm-sig', sig);
    state.lastSig = sig;

    log('DS applied', { kept, total: ids.length });
    return true;
  }

  async function refreshDataSourceSections(root){
    const sel = dsSectionsSelect(root);
    if (!sel) return;

    const menuId = readMenuId(root);

    // Guard 1: only when menu actually changed or we have no signature
    if (state.lastMenuId === menuId && sel.getAttribute('data-jprm-sig')) {
      return;
    }
    state.lastMenuId = menuId;

    log('DS refresh', { menuId });

    const map = await fetchSectionsMap(menuId);
    if (map === null) return; // aborted (superseded)

    rebuildOptionsIfChanged(sel, map || {});
  }

  /* =========================
     Debounced observer wiring
     ========================= */
  function scheduleRefresh(root){
    if (state.debounceTimer) clearTimeout(state.debounceTimer);
    state.debounceTimer = setTimeout(function(){
      state.debounceTimer = null;
      // Only refresh if relevant elements exist
      if (menuSelect(root) && dsSectionsSelect(root)) {
        refreshDataSourceSections(root);
      }
    }, 200); // small debounce to coalesce Elementor re-renders
  }

  function bindMenuChange(root){
    const ms = menuSelect(root);
    if (!ms || ms.__jprmBound) return;
    ms.__jprmBound = true;
    ms.addEventListener('change', function(){
      // When menu changes, clear the current signature so a refresh applies
      const ds = dsSectionsSelect(root);
      if (ds) ds.removeAttribute('data-jprm-sig');
      refreshDataSourceSections(root);
    });
  }

  function boot(){
    const root = panelRoot();
    if (!root) { setTimeout(boot, 300); return; }

    log('sections-dep.js active');

    // Initial pass (once)
    bindMenuChange(root);
    if (menuSelect(root) && dsSectionsSelect(root)) {
      refreshDataSourceSections(root);
    }

    // Observe but debounce updates, and only refresh if both controls exist
    const mo = new MutationObserver((list) => {
      // If neither menus nor sections control touched, ignore
      let relevant = false;
      for (const m of list) {
        if (m.type !== 'childList') continue;
        for (const n of m.addedNodes) {
          if (!(n instanceof HTMLElement)) continue;
          if (n.querySelector && (n.querySelector('[data-setting="menus"]') || n.querySelector('[data-setting="sections"]'))) {
            relevant = true;
            break;
          }
        }
        if (relevant) break;
      }
      if (!relevant) return;

      bindMenuChange(root);
      scheduleRefresh(root);
    });
    mo.observe(root, { childList: true, subtree: true });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
