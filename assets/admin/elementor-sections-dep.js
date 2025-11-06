(function(){
  'use strict';

  // Update the non-DS controls too
  const ENABLE_OTHERS = true;

  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  function panelRoot(){ return document.querySelector('.elementor-panel'); }
  function menuSelect(root){ return root && root.querySelector('select[data-setting="menus"]'); }

  // DS (Sections) SELECT2
  function dsSectionsSelect(root){
    return root && (
      root.querySelector('.elementor-control-sections select[data-setting="sections"]')
      || root.querySelector('select[data-setting="sections"]')
    );
  }

  // Other dependent selects (NO data-id assumptions)
  function splitAfterSelects(root){
    if (!root) return [];
    return Array.from(
      root.querySelectorAll(
        'select[data-setting="layout_split_after_section"], '+
        'select[data-setting="layout_split_after_section2"]'
      )
    );
  }

  // All section pickers inside repeaters (labels_layout_overrides + info_blocks)
  // Both of your repeaters use the SAME data-setting name "section_id".
  function repeaterSectionSelects(root){
    if (!root) return [];
    // Broad but safe: nothing else in your widget uses data-setting="section_id"
    return Array.from(root.querySelectorAll('select[data-setting="section_id"]'));
  }

  function isVisible(el){
    if (!el) return false;
    const s = window.getComputedStyle(el);
    return s && s.display !== 'none' && s.visibility !== 'hidden';
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
    if (typeof jQuery !== 'undefined') {
      const $el = jQuery(selectEl);
      $el.trigger('change', { silent:true });
      if ($el.hasClass('select2-hidden-accessible')) $el.trigger('change.select2');
    } else {
      selectEl.dispatchEvent(new Event('input',  { bubbles:true }));
      selectEl.dispatchEvent(new Event('change', { bubbles:true }));
    }
  }

  function optionsSignature(map){
    if (!map || typeof map !== 'object') return '';
    const keys = Object.keys(map).sort();
    return keys.map(k => k + ':' + String(map[k]||'').length).join('|');
  }

  const state = {
    lastMenuId: null,
    inflight: null,
    debounceTimer: null,
    lastMapSig: ''
  };

  async function fetchSectionsMap(menuId){
    if (state.inflight) { try { state.inflight.abort(); } catch(e){} state.inflight = null; }
    const ctl = new AbortController();
    state.inflight = ctl;

    const body = new URLSearchParams();
    body.set('action', 'jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    const n = ajaxNonce(); if (n) body.set('_ajax_nonce', n);

    try {
      const res  = await fetch(ajaxUrl(), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
        signal: ctl.signal
      });
      const text = await res.text();
      let json = null;
      try { json = JSON.parse(text); } catch(_e){ json = null; }
      if (!json || !json.success || !json.data || typeof json.data !== 'object') {
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

  function rebuildOptionsIfChanged(selectEl, map){
    if (!selectEl || !isVisible(selectEl)) return false;

    const sig = optionsSignature(map);
    const prevSig = selectEl.getAttribute('data-jprm-sig') || '';
    if (sig === prevSig) return false; // unchanged

    const wasMultiple = !!selectEl.multiple;
    const selected = getSelectedValues(selectEl);
    const ids = Object.keys(map || {});

    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

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

    // If Select2 is attached, refresh UI
    if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2 && jQuery(selectEl).hasClass('select2-hidden-accessible')) {
      jQuery(selectEl).trigger('change.select2');
    }
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
    if (map === null) return; // aborted

    const changed = rebuildOptionsIfChanged(sel, map || {});
    if (changed) {
      try { sel.setAttribute('data-jprm-map', JSON.stringify(map || {})); } catch(e){}
      const sigMap = optionsSignature(map||{});
      state.lastMapSig = sigMap;
      log('DS applied', { total: Object.keys(map||{}).length });
    }
    return map || {};
  }

  function applyScopedOptionsToOthers(root, map){
    if (!ENABLE_OTHERS) return;

    if (!map || typeof map !== 'object' || !Object.keys(map).length) {
      const ds = dsSectionsSelect(root);
      if (ds) {
        const stash = ds.getAttribute('data-jprm-map');
        if (stash) { try { map = JSON.parse(stash) || {}; } catch(e){} }
      }
    }
    if (!map || !Object.keys(map).length) return;

    const targets = [
      ...splitAfterSelects(root),
      ...repeaterSectionSelects(root)
    ].filter(isVisible);

    log('Others targets', { total: targets.length });

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

      const map = await refreshDataSourceSections(root);
      if (map && typeof map === 'object') applyScopedOptionsToOthers(root, map);
    }, 160);
  }

  function bindMenuChange(root){
    const ms = menuSelect(root);
    if (!ms || ms.__jprmBound) return;
    ms.__jprmBound = true;
    ms.addEventListener('change', function(){
      const ds = dsSectionsSelect(root);
      if (ds) ds.removeAttribute('data-jprm-sig');
      if (ENABLE_OTHERS) {
        splitAfterSelects(root).forEach(el => el.removeAttribute('data-jprm-sig'));
        repeaterSectionSelects(root).forEach(el => el.removeAttribute('data-jprm-sig'));
      }
      scheduleRefresh(root);
    });
  }

  // When switching tabs, Elementor adds the controls to the DOM: refresh then.
  function bindTabSwitchRefresh(root){
    root.addEventListener('click', function(e){
      const t = e.target;
      if (!(t instanceof HTMLElement)) return;
      if (t.closest('.elementor-panel-navigation, .elementor-tab-control')) {
        setTimeout(() => scheduleRefresh(root), 100);
      }
    }, { passive:true });
  }

  // When a repeater row is added, re-apply map to newly inserted selects
  function bindRepeaterAddHooks(root){
    if (!ENABLE_OTHERS) return;
    root.addEventListener('click', function(e){
      const t = e.target;
      if (!(t instanceof HTMLElement)) return;
      const btn = t.closest('.elementor-repeater__add, .elementor-repeater-add');
      if (!btn) return;
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
    bindTabSwitchRefresh(root);
    bindRepeaterAddHooks(root);
    scheduleRefresh(root); // initial

    // Also observe controls mounting/unmounting
    const mo = new MutationObserver((list) => {
      let relevant = false;
      for (const m of list) {
        if (m.type !== 'childList') continue;
        for (const n of m.addedNodes) {
          if (!(n instanceof HTMLElement)) continue;
          if (n.querySelector && (
              n.querySelector('select[data-setting="menus"]') ||
              n.querySelector('select[data-setting="sections"]') ||
              n.querySelector('select[data-setting="layout_split_after_section"]') ||
              n.querySelector('select[data-setting="layout_split_after_section2"]') ||
              n.querySelector('select[data-setting="section_id"]')
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
