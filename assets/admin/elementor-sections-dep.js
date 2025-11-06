(function(){
  'use strict';

  /* ------------ tiny logger ------------ */
  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }

  /* ------------ ajax helpers ------------ */
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  /* ------------ panel + control locators ------------ */
  function panelRoot(){ return document.querySelector('.elementor-panel') || document; }

  function menuSelect(root){
    return root && root.querySelector('[data-setting="menus"]');
  }

  // DS control (your working SELECT2)
  function dsSectionsSelect(root){
    return root && (root.querySelector('.elementor-control-sections [data-setting="sections"]') || root.querySelector('[data-setting="sections"]'));
  }

  // NON-DS scoped targets:
  // IMPORTANT: Elementor puts the `classes` option on the *wrapper* `.elementor-control`.
  // We look for those wrappers, then fetch their inner <select>.
  function otherScopedSelects(root){
    if (!root) return [];
    const wrappers = Array.from(root.querySelectorAll('.elementor-control.jprm-scope-target'));
    const out = [];
    wrappers.forEach(wrap => {
      // Prefer a select with a data-setting (Elementor standard)
      let sel = wrap.querySelector('select[data-setting]');
      if (!sel) {
        // If Select2 already enhanced, the original is .select2-hidden-accessible
        sel = wrap.querySelector('select.select2-hidden-accessible[data-setting]');
      }
      if (!sel) return;

      // Skip the DS field (data-setting="sections")
      if ((sel.getAttribute('data-setting') || '') === 'sections') return;

      out.push(sel);
    });
    return out;
  }

  /* ------------ utils ------------ */
  function isVisible(el){
    if (!el) return false;
    const s = window.getComputedStyle(el);
    return s && s.display !== 'none' && s.visibility !== 'hidden';
  }
  function readMenuId(root){
    const el = menuSelect(root);
    if (!el) return 0;
    const id = parseInt(el.value || 0, 10);
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

  /* ------------ state ------------ */
  const state = {
    lastMenuId: null,
    inflight: null,
    debounceTimer: null,
    lastMapSig: ''
  };

  /* ------------ AJAX: fetch scoped tree map ------------ */
  async function fetchSectionsMap(menuId){
    if (state.inflight) { try { state.inflight.abort(); } catch(e){} state.inflight = null; }
    const ctl = new AbortController();
    state.inflight = ctl;

    const body = new URLSearchParams();
    body.set('action', 'jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    const n = ajaxNonce();
    if (n) body.set('_ajax_nonce', n);

    try {
      const res = await fetch(ajaxUrl(), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
        signal: ctl.signal
      });
      const text = await res.text();
      let json = null;
      try { json = JSON.parse(text); } catch(_e){ json = null; }
      if (!json || !json.success || !json.data) {
        log('AJAX payload not OK; got:', text.slice(0,200));
        return {};
      }
      return json.data; // { id: "— label", ... }
    } catch(e){
      if (e && e.name === 'AbortError') return null;
      log('AJAX error', e);
      return {};
    } finally {
      if (state.inflight === ctl) state.inflight = null;
    }
  }

  /* ------------ DOM: (re)build options ------------ */
  function rebuildOptionsIfChanged(selectEl, map){
    if (!selectEl || !isVisible(selectEl)) return false;

    const sig = optionsSignature(map);
    const prevSig = selectEl.getAttribute('data-jprm-sig') || '';
    if (sig === prevSig) return false; // no churn

    const multiple = !!selectEl.multiple;
    const selected = getSelectedValues(selectEl);
    const ids = Object.keys(map || {});

    // clear
    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

    // single: empty first option
    if (!multiple) {
      const emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = '';
      selectEl.appendChild(emptyOpt);
    }

    // add all options
    ids.forEach(id => {
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = String(map[id] || '');
      selectEl.appendChild(opt);
    });

    // keep previous selection if valid
    const kept = selected.filter(v => ids.includes(String(v)));
    setSelectedValues(selectEl, kept);

    selectEl.setAttribute('data-jprm-sig', sig);

    // refresh Select2 UI if present (affects DS and any select2 field)
    if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2 && jQuery(selectEl).hasClass('select2-hidden-accessible')) {
      jQuery(selectEl).trigger('change.select2');
    }
    return true;
  }

  /* ------------ DS refresh (kept exactly as your working flow) ------------ */
  async function refreshDataSourceSections(root){
    const sel = dsSectionsSelect(root);
    if (!sel) return;

    const menuId = readMenuId(root);
    if (state.lastMenuId === menuId && sel.getAttribute('data-jprm-sig')) return;
    state.lastMenuId = menuId;

    log('DS refresh', { menuId });
    const map = await fetchSectionsMap(menuId);
    if (map === null) return; // aborted
    const changed = rebuildOptionsIfChanged(sel, map || {});
    if (changed) {
      state.lastMapSig = optionsSignature(map||{});
      log('DS applied', { total: Object.keys(map||{}).length });
    }
    return map || {};
  }

  /* ------------ apply to non-DS, wrapper-tagged controls ------------ */
  function applyScopedOptionsToOthers(root, map){
    const targets = otherScopedSelects(root).filter(isVisible);
    log('Others targets', { total: targets.length });
    if (!targets.length) return;

    let applied = 0;
    targets.forEach(el => { if (rebuildOptionsIfChanged(el, map)) applied++; });
    if (applied) log('Others applied', { count: applied, totalTargets: targets.length });
  }

  /* ------------ orchestration ------------ */
  function scheduleRefresh(root){
    if (state.debounceTimer) clearTimeout(state.debounceTimer);
    state.debounceTimer = setTimeout(async function(){
      state.debounceTimer = null;
      if (!menuSelect(root)) return;

      const map = await refreshDataSourceSections(root);
      if (!map || typeof map !== 'object') return;

      applyScopedOptionsToOthers(root, map);
    }, 180);
  }

  function bindMenuChange(root){
    const ms = menuSelect(root);
    if (!ms || ms.__jprmBound) return;
    ms.__jprmBound = true;
    ms.addEventListener('change', function(){
      // force rebuild on DS + others
      const ds = dsSectionsSelect(root);
      if (ds) ds.removeAttribute('data-jprm-sig');
      otherScopedSelects(root).forEach(el => el.removeAttribute('data-jprm-sig'));
      scheduleRefresh(root);
    });
  }

  // when a repeater row is added, re-apply map to new row selects
  function bindRepeaterAddHooks(root){
    root.addEventListener('click', function(e){
      const t = e.target;
      if (!(t instanceof HTMLElement)) return;
      const addBtn = t.closest('.elementor-repeater-add');
      if (!addBtn) return;
      setTimeout(async function(){
        const map = await refreshDataSourceSections(root) || {};
        applyScopedOptionsToOthers(root, map);
      }, 120);
    }, { passive:true });
  }

  function boot(){
    const root = panelRoot();
    if (!root) { setTimeout(boot, 300); return; }

    log('sections-dep.js active');

    bindMenuChange(root);
    bindRepeaterAddHooks(root);
    scheduleRefresh(root); // initial

    // watch for controls appearing (tab switches, repeater expand, etc.)
    const mo = new MutationObserver((list) => {
      let relevant = false;
      for (const m of list) {
        if (m.type !== 'childList') continue;
        for (const n of m.addedNodes) {
          if (!(n instanceof HTMLElement)) continue;
          if (n.querySelector && (
            n.querySelector('[data-setting="menus"]') ||
            n.querySelector('[data-setting="sections"]') ||                        // DS
            n.querySelector('.elementor-control.jprm-scope-target select') ||      // non-DS (plain)
            n.querySelector('.elementor-control.jprm-scope-target .select2-hidden-accessible') // non-DS (select2)
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
