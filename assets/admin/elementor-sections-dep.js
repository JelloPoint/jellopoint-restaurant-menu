(function(){
  'use strict';

  /* -------- configuration -------- */
  const ENABLE_OTHERS = true;
  const TARGET_SETTINGS = new Set([
    'layout_split_after_section',
    'layout_split_after_section2',
    'section_id' // used in both repeaters
  ]);

  /* -------- iframe helpers -------- */
  function getPanelIframe(){ return document.getElementById('elementor-panel-iframe'); }
  function getPanelWin(){ const ifr = getPanelIframe(); return ifr && ifr.contentWindow ? ifr.contentWindow : window; }
  function getPanelDoc(){ const ifr = getPanelIframe(); return ifr && ifr.contentDocument ? ifr.contentDocument : document; }
  function $jq(){ const w = getPanelWin(); return w && w.jQuery ? w.jQuery : (window.jQuery || null); }

  /* -------- misc helpers -------- */
  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  function panelRoot(){ return getPanelDoc().querySelector('.elementor-panel'); }
  function isVisible(el){
    if (!el) return false;
    const s = getPanelWin().getComputedStyle(el);
    return s && s.display !== 'none' && s.visibility !== 'hidden';
  }

  /* -------- control pickers -------- */
  function selectMenu(){ return getPanelDoc().querySelector('select[data-setting="menus"]'); }
  function selectDS(){   return getPanelDoc().querySelector('.elementor-control-sections select[data-setting="sections"], select[data-setting="sections"]'); }

  // Robust “other controls” finder: scan ALL visible selects with one of our data-settings
  function findOtherTargets(){
    if (!ENABLE_OTHERS) return [];
    const doc = getPanelDoc();
    const list = Array.from(doc.querySelectorAll('select[data-setting]'))
      .filter(el => TARGET_SETTINGS.has(el.getAttribute('data-setting')||''))
      .filter(isVisible);
    return list;
  }

  /* -------- values + options -------- */
  function readMenuId(){
    const el = selectMenu();
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

    // Fire change in iframe context
    const win = getPanelWin();
    selectEl.dispatchEvent(new win.Event('input',  { bubbles:true }));
    selectEl.dispatchEvent(new win.Event('change', { bubbles:true }));

    // Nudge Select2 if present (in iframe)
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

  function rebuildOptionsIfChanged(selectEl, map){
    if (!selectEl || !isVisible(selectEl)) return false;

    const sig = optionsSignature(map);
    const prevSig = selectEl.getAttribute('data-jprm-sig') || '';
    if (sig === prevSig) return false; // unchanged → skip

    const wasMultiple = !!selectEl.multiple;
    const selected = getSelectedValues(selectEl);
    const ids = Object.keys(map || {});

    // clear
    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

    // single selects get an empty first option
    if (!wasMultiple) {
      const emptyOpt = getPanelDoc().createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = '';
      selectEl.appendChild(emptyOpt);
    }

    // fill
    ids.forEach(id => {
      const opt = getPanelDoc().createElement('option');
      opt.value = id;
      opt.textContent = String(map[id] || '');
      selectEl.appendChild(opt);
    });

    // restore valid selections
    const kept = selected.filter(v => ids.includes(String(v)));
    setSelectedValues(selectEl, kept);

    selectEl.setAttribute('data-jprm-sig', sig);
    return true;
  }

  /* -------- data layer (AJAX to your endpoint) -------- */
  const state = {
    lastMenuId: null,
    lastMap: null,
    inflight: null,
    debounceTimer: null,
    pollTimer: null
  };

  async function fetchSectionsMap(menuId){
    if (state.inflight) { try { state.inflight.abort(); } catch(e){} state.inflight = null; }
    const ctl = new AbortController();
    state.inflight = ctl;

    const body = new URLSearchParams();
    body.set('action', 'jprm_sections_by_menu');
    body.set('menu', String(menuId || ''));
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

  /* -------- application -------- */
  async function refreshDS(){
    const rootSel = selectDS();
    if (!rootSel) return null;

    const menuId = readMenuId();
    if (state.lastMenuId === menuId && rootSel.getAttribute('data-jprm-sig')) {
      // no need to re-fetch if nothing changed and DS already has sig
      return state.lastMap;
    }
    state.lastMenuId = menuId;

    log('DS refresh', { menuId });
    const map = await fetchSectionsMap(menuId);
    if (map === null) return null; // aborted

    const changed = rebuildOptionsIfChanged(rootSel, map || {});
    if (changed) {
      try { rootSel.setAttribute('data-jprm-map', JSON.stringify(map || {})); } catch(e){}
      state.lastMap = map || {};
      log('DS applied', { total: Object.keys(state.lastMap||{}).length });
    }
    return state.lastMap;
  }

  function applyToOthers(map){
    if (!ENABLE_OTHERS) return 0;
    if (!map || !Object.keys(map).length) {
      // fallback to DS stash
      const ds = selectDS();
      const stash = ds && ds.getAttribute('data-jprm-map');
      if (stash) { try { map = JSON.parse(stash) || {}; } catch(e){} }
      if (!map || !Object.keys(map).length) return 0;
    }

    const targets = findOtherTargets();
    // Debug: how many controls we’re touching
    log('Others targets', { total: targets.length });

    let applied = 0;
    for (const el of targets) {
      if (rebuildOptionsIfChanged(el, map)) applied++;
    }
    if (applied) log('Others applied', { count: applied, totalTargets: targets.length });
    return applied;
  }

  // After a menu change or tab switch, Elementor can mount controls late.
  // We run a short polling burst (e.g., 6 passes over ~1.5s) to catch them.
  function burstPollApply(){
    const win = getPanelWin();
    let passes = 6;
    if (state.pollTimer) win.clearInterval(state.pollTimer);
    state.pollTimer = win.setInterval(() => {
      if (--passes <= 0) { win.clearInterval(state.pollTimer); state.pollTimer = null; }
      applyToOthers(state.lastMap);
    }, 250);
  }

  function scheduleRefresh(){
    const win = getPanelWin();
    if (state.debounceTimer) win.clearTimeout(state.debounceTimer);
    state.debounceTimer = win.setTimeout(async () => {
      state.debounceTimer = null;
      // Always refresh DS first to have the latest scoped tree
      const map = await refreshDS();
      if (map) {
        applyToOthers(map);
        burstPollApply(); // keep trying briefly for late-mounted controls
      }
    }, 140);
  }

  /* -------- bindings -------- */
  function bindMenuChange(){
    const ms = selectMenu();
    if (!ms || ms.__jprmBound) return;
    ms.__jprmBound = true;
    ms.addEventListener('change', () => {
      const ds = selectDS();
      if (ds) ds.removeAttribute('data-jprm-sig');
      // clear signatures on potential targets; they might not be present yet
      const all = getPanelDoc().querySelectorAll('select[data-setting]');
      all.forEach(el => {
        const key = el.getAttribute('data-setting')||'';
        if (TARGET_SETTINGS.has(key) || key === 'sections') el.removeAttribute('data-jprm-sig');
      });
      scheduleRefresh();
    });
  }

  function bindTabSwitch(){
    const doc = getPanelDoc();
    doc.addEventListener('click', (e) => {
      const t = e.target;
      const H = getPanelWin().HTMLElement;
      if (!(t instanceof H)) return;
      if (t.closest('.elementor-panel-navigation, .elementor-tab-control')) {
        // on tab change, controls mount later
        scheduleRefresh();
      }
    }, { passive:true });
  }

  function bindRepeaterAdd(){
    if (!ENABLE_OTHERS) return;
    const doc = getPanelDoc();
    doc.addEventListener('click', (e) => {
      const t = e.target;
      const H = getPanelWin().HTMLElement;
      if (!(t instanceof H)) return;
      if (t.closest('.elementor-repeater__add, .elementor-repeater-add')) {
        // new row mounts shortly afterwards
        const win = getPanelWin();
        win.setTimeout(() => { applyToOthers(state.lastMap); }, 120);
        win.setTimeout(() => { applyToOthers(state.lastMap); }, 300);
      }
    }, { passive:true });
  }

  function observePanel(){
    const doc = getPanelDoc();
    const mo = new MutationObserver((list) => {
      let relevant = false;
      for (const m of list) {
        if (m.type !== 'childList') continue;
        for (const n of m.addedNodes) {
          const H = getPanelWin().HTMLElement;
          if (!(n instanceof H)) continue;
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
      if (relevant) scheduleRefresh();
    });
    mo.observe(doc, { childList:true, subtree:true });
  }

  /* -------- boot -------- */
  function boot(){
    const root = panelRoot();
    if (!root) { setTimeout(boot, 250); return; }
    log('sections-dep.js active (iframe + late-mount safe)');

    bindMenuChange();
    bindTabSwitch();
    bindRepeaterAdd();
    observePanel();

    // initial run
    scheduleRefresh();
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
