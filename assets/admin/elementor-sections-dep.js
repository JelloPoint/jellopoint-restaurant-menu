(function(){
  'use strict';

  const ENABLE_OTHERS = true;

  /* --------- helpers bound to the Elementor panel iframe --------- */
  function getPanelIframe(){
    return document.getElementById('elementor-panel-iframe');
  }
  function getPanelDoc(){
    const ifr = getPanelIframe();
    return (ifr && ifr.contentDocument) ? ifr.contentDocument : document;
  }
  function getPanelWin(){
    const ifr = getPanelIframe();
    return (ifr && ifr.contentWindow) ? ifr.contentWindow : window;
  }
  function $jq(){
    const win = getPanelWin();
    return (win && win.jQuery) ? win.jQuery : (window.jQuery || null);
  }

  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  function panelRoot(){
    const d = getPanelDoc();
    return d && d.querySelector('.elementor-panel');
  }

  function menuSelect(root){
    return root && root.querySelector('select[data-setting="menus"]');
  }

  // DS (Sections) SELECT2
  function dsSectionsSelect(root){
    return root && (
      root.querySelector('.elementor-control-sections select[data-setting="sections"]') ||
      root.querySelector('select[data-setting="sections"]')
    );
  }

  // Other dependent selects (NO data-id or tab assumptions; all from panel doc)
  function splitAfterSelects(root){
    if (!root) return [];
    return Array.from(
      root.querySelectorAll(
        'select[data-setting="layout_split_after_section"], ' +
        'select[data-setting="layout_split_after_section2"]'
      )
    );
  }

  // All repeater “Section” pickers (both repeaters use data-setting="section_id")
  function repeaterSectionSelects(root){
    if (!root) return [];
    return Array.from(root.querySelectorAll('select[data-setting="section_id"]'));
  }

  function isVisible(el){
    if (!el) return false;
    const s = getPanelWin().getComputedStyle(el);
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

    // Fire change in the iframe’s context
    const win = getPanelWin();
    selectEl.dispatchEvent(new win.Event('input',  { bubbles:true }));
    selectEl.dispatchEvent(new win.Event('change', { bubbles:true }));

    // If Select2 is attached (in iframe), gently poke it
    const jq = $jq();
    if (jq && jq.fn && jq.fn.select2 && selectEl.classList.contains('select2-hidden-accessible')) {
      jq(selectEl).trigger('change.select2');
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
    debounceTimer: null
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
      const emptyOpt = getPanelDoc().createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = '';
      selectEl.appendChild(emptyOpt);
    }

    ids.forEach(id => {
      const opt = getPanelDoc().createElement('option');
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
    if (map === null) return; // aborted

    const changed = rebuildOptionsIfChanged(sel, map || {});
    if (changed) {
      try { sel.setAttribute('data-jprm-map', JSON.stringify(map || {})); } catch(e){}
      log('DS applied', { total: Object.keys(map||{}).length });
    }
    return map || {};
  }

  function applyScopedOptionsToOthers(root, map){
    if (!ENABLE_OTHERS) return;

    // If map missing (e.g. selector not mounted yet), try DS stash
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
    const win = getPanelWin();
    state.debounceTimer = win.setTimeout(async function(){
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

  // When switching tabs, Elementor mounts controls in the panel iframe
  function bindTabSwitchRefresh(root){
    const d = getPanelDoc();
    d.addEventListener('click', function(e){
      const t = e.target;
      if (!(t instanceof getPanelWin().HTMLElement)) return;
      if (t.closest('.elementor-panel-navigation, .elementor-tab-control')) {
        getPanelWin().setTimeout(() => scheduleRefresh(root), 100);
      }
    }, { passive:true });
  }

  // When a repeater row is added, re-apply map to newly inserted selects
  function bindRepeaterAddHooks(root){
    if (!ENABLE_OTHERS) return;
    const d = getPanelDoc();
    d.addEventListener('click', function(e){
      const t = e.target;
      if (!(t instanceof getPanelWin().HTMLElement)) return;
      const btn = t.closest('.elementor-repeater__add, .elementor-repeater-add');
      if (!btn) return;
      getPanelWin().setTimeout(async function(){
        const map = await refreshDataSourceSections(root) || {};
        applyScopedOptionsToOthers(root, map);
      }, 120);
    }, { passive:true });
  }

  function boot(){
    const root = panelRoot();
    if (!root) { setTimeout(boot, 250); return; }

    log('sections-dep.js active (iframe-aware)');

    bindMenuChange(root);
    bindTabSwitchRefresh(root);
    bindRepeaterAddHooks(root);
    scheduleRefresh(root); // initial

    // Observe mounts inside the iframe’s panel
    const d = getPanelDoc();
    const mo = new MutationObserver((list) => {
      let relevant = false;
      for (const m of list) {
        if (m.type !== 'childList') continue;
        for (const n of m.addedNodes) {
          if (!(n instanceof getPanelWin().HTMLElement)) continue;
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
    mo.observe(d, { childList: true, subtree: true });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
