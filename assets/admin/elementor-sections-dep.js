(function(){
  'use strict';

  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  function panelRoot(){ return document.querySelector('.elementor-panel'); }
  function menuSelect(root){ return root && root.querySelector('[data-setting="menus"]'); }
  function dsSectionsSelect(root){
    return root && (root.querySelector('.elementor-control-sections [data-setting="sections"]') || root.querySelector('[data-setting="sections"]'));
  }

  // Other dependent selects:
  function splitAfterSelects(root){
    if (!root) return [];
    return Array.from(root.querySelectorAll('[data-setting="layout_split_after_section"], [data-setting="layout_split_after_section2"]'));
  }
  // Repeater scoping guards: only section_id inside our two repeaters
  function repeaterSectionSelects(root){
    if (!root) return [];
    const out = [];
    const reps = root.querySelectorAll('.elementor-control-type-repeater[data-id="labels_layout_overrides"], .elementor-control-type-repeater[data-id="info_blocks"]');
    reps.forEach(rep => {
      out.push(...rep.querySelectorAll('select[data-setting="section_id"]'));
    });
    return out;
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
  function optionsSignature(map){
    if (!map || typeof map !== 'object') return '';
    const keys = Object.keys(map).sort();
    return keys.map(k => k + ':' + String(map[k]||'').length).join('|');
  }

  const state = {
    lastMenuId: null,
    inflight: null,
    debounceTimer: null
  };

  async function fetchSectionsMap(menuId){
    if (state.inflight) { try { state.inflight.abort(); } catch(e){} state.inflight = null; }
    const ctl = new AbortController();
    state.inflight = ctl;

    const body = new URLSearchParams();
    body.set('action', 'jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    body.set('_ajax_nonce', ajaxNonce());

    try {
      const res = await fetch(ajaxUrl(), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
        signal: ctl.signal
      });
      const json = await res.json();
      if (!json || !json.success || !json.data) return {};
      return json.data; // { id: "— label", ... }
    } catch(e){
      if (e && e.name === 'AbortError') return null;
      log('AJAX error', e);
      return {};
    } finally {
      if (state.inflight === ctl) state.inflight = null;
    }
  }

  function rebuildOptionsIfChanged(selectEl, map){
    if (!selectEl) return false;

    const sig = optionsSignature(map);
    const prevSig = selectEl.getAttribute('data-jprm-sig') || '';
    if (sig === prevSig) return false; // no change → avoid loops

    const wasMultiple = !!selectEl.multiple;
    const selected = getSelectedValues(selectEl);
    const ids = Object.keys(map || {});

    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

    // For single selects, insert an empty option at top
    if (!wasMultiple) {
      const emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = '';
      selectEl.appendChild(emptyOpt);
    }

    ids.forEach(id => {
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = String(map[id] || '');
      selectEl.appendChild(opt);
    });

    const kept = selected.filter(v => ids.includes(String(v)));
    setSelectedValues(selectEl, kept);

    selectEl.setAttribute('data-jprm-sig', sig);
    return true;
  }

  async function refreshDataSourceSections(root){
    const sel = dsSectionsSelect(root);
    if (!sel) return;

    const menuId = readMenuId(root);
    if (state.lastMenuId === menuId && sel.getAttribute('data-jprm-sig')) return;
    state.lastMenuId = menuId;

    log('DS refresh', { menuId });
    const map = await fetchSectionsMap(menuId);
    if (map === null) return; // aborted/superseded
    const changed = rebuildOptionsIfChanged(sel, map || {});
    if (changed) log('DS applied', { total: Object.keys(map||{}).length });
    return map || {};
  }

  // NEW: apply the same map to other dependent selects
  function applyScopedOptionsToOthers(root, map){
    const targets = [
      ...splitAfterSelects(root),
      ...repeaterSectionSelects(root)
    ];
    if (!targets.length) return;

    let applied = 0;
    targets.forEach(el => { if (rebuildOptionsIfChanged(el, map)) applied++; });
    if (applied) log('Others applied', { count: applied, totalTargets: targets.length });
  }

  function scheduleRefresh(root){
    if (state.debounceTimer) clearTimeout(state.debounceTimer);
    state.debounceTimer = setTimeout(async function(){
      state.debounceTimer = null;
      if (!menuSelect(root)) return;

      // Always refresh DS first so we reuse the same map for others
      const map = await refreshDataSourceSections(root);
      if (map && typeof map === 'object') {
        applyScopedOptionsToOthers(root, map);
      }
    }, 200);
  }

  function bindMenuChange(root){
    const ms = menuSelect(root);
    if (!ms || ms.__jprmBound) return;
    ms.__jprmBound = true;
    ms.addEventListener('change', function(){
      // Clear signatures so rebuild happens
      const ds = dsSectionsSelect(root);
      if (ds) ds.removeAttribute('data-jprm-sig');
      splitAfterSelects(root).forEach(el => el.removeAttribute('data-jprm-sig'));
      repeaterSectionSelects(root).forEach(el => el.removeAttribute('data-jprm-sig'));
      scheduleRefresh(root);
    });
  }

  function boot(){
    const root = panelRoot();
    if (!root) { setTimeout(boot, 300); return; }

    log('sections-dep.js active');

    bindMenuChange(root);
    scheduleRefresh(root); // initial

    const mo = new MutationObserver((list) => {
      let relevant = false;
      for (const m of list) {
        if (m.type !== 'childList') continue;
        for (const n of m.addedNodes) {
          if (!(n instanceof HTMLElement)) continue;
          if (n.querySelector && (
              n.querySelector('[data-setting="menus"]') ||
              n.querySelector('[data-setting="sections"]') ||
              n.querySelector('[data-setting="layout_split_after_section"]') ||
              n.querySelector('[data-setting="layout_split_after_section2"]') ||
              n.querySelector('.elementor-control-type-repeater[data-id="labels_layout_overrides"] select[data-setting="section_id"]') ||
              n.querySelector('.elementor-control-type-repeater[data-id="info_blocks"] select[data-setting="section_id"]')
          )) { relevant = true; break; }
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
