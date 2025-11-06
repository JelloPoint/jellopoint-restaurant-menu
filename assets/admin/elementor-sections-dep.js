(function(){
  'use strict';

  /* =========================
   * Small helpers
   * ========================= */
  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  function panelRoot(){
    // Elementor sometimes mounts the panel inside an iframe; but the DOM we need is in the main doc
    return document.querySelector('.elementor-panel') || document;
  }

  function isVisible(el){
    if (!el) return false;
    const s = window.getComputedStyle(el);
    return s && s.display !== 'none' && s.visibility !== 'hidden';
  }

  function readMenuId(root){
    const ms = root && root.querySelector('[data-setting="menus"]');
    if (!ms) return 0;
    const id = parseInt(ms.value || 0, 10);
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

  /* =========================
   * DS control finders (your working ones)
   * ========================= */
  function dsSectionsSelect(root){
    return root && (root.querySelector('.elementor-control-sections [data-setting="sections"]') || root.querySelector('[data-setting="sections"]'));
  }

  /* =========================
   * OTHER section selects (robust finder)
   * We look in MANY places:
   *  - select elements inside ANY element with class containing jprm-scope-target
   *  - control wrappers that have that class (Elementor puts it on .elementor-control)
   *  - select2-hidden-accessible originals
   *  - any select with data-setting that *looks like* a section id (contains "section")
   * …then we EXCLUDE data-setting="sections" (that’s DS).
   * ========================= */
  function findOtherSectionSelects(root){
    if (!root) return [];

    const found = new Set();

    // 1) any select under an element that has our class on it
    root.querySelectorAll('.jprm-scope-target select').forEach(el => found.add(el));

    // 2) control wrappers that carry the class
    root.querySelectorAll('.elementor-control.jprm-scope-target').forEach(wrap => {
      const sel = wrap.querySelector('select[data-setting]') || wrap.querySelector('select');
      if (sel) found.add(sel);
      // if Select2: original select is hidden
      const s2 = wrap.querySelector('select.select2-hidden-accessible[data-setting]');
      if (s2) found.add(s2);
    });

    // 3) class may be on inner field container
    root.querySelectorAll('.elementor-control-field.jprm-scope-target, .elementor-field.jprm-scope-target').forEach(wrap => {
      const sel = wrap.querySelector('select[data-setting]') || wrap.querySelector('select');
      if (sel) found.add(sel);
      const s2 = wrap.querySelector('select.select2-hidden-accessible[data-setting]');
      if (s2) found.add(s2);
    });

    // 4) any select where data-setting *contains* "section" (covers repeaters if classes aren’t applied)
    root.querySelectorAll('select[data-setting*="section"]').forEach(el => found.add(el));
    root.querySelectorAll('select.select2-hidden-accessible[data-setting*="section"]').forEach(el => found.add(el));

    // EXCLUDE the DS field
    const out = [];
    found.forEach(el => {
      const ds = (el.getAttribute('data-setting') || '').toLowerCase().trim();
      if (ds === 'sections') return; // DS – skip
      // ignore if not visible (collapsed rows will get picked up when opened)
      if (!isVisible(el)) return;
      out.push(el);
    });

    return out;
  }

  /* =========================
   * AJAX: fetch scoped tree options
   * ========================= */
  const state = { inflight: null, lastMenuId: null, debounce: null, lastMapSig: '' };

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
      if (!json || !json.success || !json.data) {
        log('AJAX payload not OK; got:', text.slice(0, 200));
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

  /* =========================
   * DOM: rebuild <select> options
   * ========================= */
  function rebuildOptionsIfChanged(selectEl, map){
    if (!selectEl || !isVisible(selectEl)) return false;

    const sig = optionsSignature(map);
    const prevSig = selectEl.getAttribute('data-jprm-sig') || '';
    if (sig === prevSig) return false;

    const multiple = !!selectEl.multiple;
    const selected = getSelectedValues(selectEl);
    const ids = Object.keys(map || {});

    // clear
    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

    // single: keep empty option
    if (!multiple) {
      const emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = '';
      selectEl.appendChild(emptyOpt);
    }

    // add options
    ids.forEach(id => {
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = String(map[id] || '');
      selectEl.appendChild(opt);
    });

    // restore selection if still valid
    const kept = selected.filter(v => ids.includes(String(v)));
    setSelectedValues(selectEl, kept);

    selectEl.setAttribute('data-jprm-sig', sig);

    // refresh Select2 if this select is enhanced
    if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2 && jQuery(selectEl).hasClass('select2-hidden-accessible')) {
      jQuery(selectEl).trigger('change.select2');
    }

    return true;
  }

  /* =========================
   * DS refresh (kept intact)
   * ========================= */
  async function refreshDS(root){
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

  function applyToOthers(root, map){
    const targets = findOtherSectionSelects(root);
    log('Others targets', { total: targets.length });
    if (!targets.length) return;

    let applied = 0;
    targets.forEach(el => { if (rebuildOptionsIfChanged(el, map)) applied++; });
    if (applied) log('Others applied', { count: applied, totalTargets: targets.length });
  }

  function scheduleRefresh(root){
    if (state.debounce) clearTimeout(state.debounce);
    state.debounce = setTimeout(async function(){
      state.debounce = null;
      const map = await refreshDS(root);
      if (!map || typeof map !== 'object') return;
      applyToOthers(root, map);
    }, 160);
  }

  /* =========================
   * Bindings
   * ========================= */
  function bindMenuChange(root){
    const ms = root && root.querySelector('[data-setting="menus"]');
    if (!ms || ms.__jprmBound) return;
    ms.__jprmBound = true;
    ms.addEventListener('change', function(){
      // force rebuild on all targets by clearing signatures
      const ds = dsSectionsSelect(root);
      if (ds) ds.removeAttribute('data-jprm-sig');
      findOtherSectionSelects(root).forEach(el => el.removeAttribute('data-jprm-sig'));
      scheduleRefresh(root);
    });
  }

  function bindRepeaterHooks(root){
    // When user adds a repeater row or opens one, new selects appear.
    // We re-apply current map shortly after the click.
    root.addEventListener('click', function(e){
      const t = e.target;
      if (!(t instanceof HTMLElement)) return;
      const addBtn  = t.closest('.elementor-repeater-add');
      const editBtn = t.closest('.elementor-repeater-tool-edit, .elementor-repeater-row-item'); // open row
      if (!addBtn && !editBtn) return;
      setTimeout(()=> scheduleRefresh(root), 150);
    }, { passive:true });
  }

  function boot(){
    const root = panelRoot();
    if (!root) { setTimeout(boot, 300); return; }

    log('sections-dep.js active');

    bindMenuChange(root);
    bindRepeaterHooks(root);
    scheduleRefresh(root); // initial

    // Panel changes: tab switch, accordion expand, new controls, etc.
    const mo = new MutationObserver((mut) => {
      let relevant = false;
      for (const m of mut) {
        if (m.type !== 'childList') continue;
        for (const n of m.addedNodes) {
          if (!(n instanceof HTMLElement)) continue;
          if (n.querySelector && (
            n.querySelector('[data-setting="menus"]') ||
            n.querySelector('[data-setting="sections"]') ||
            n.querySelector('.jprm-scope-target select') ||
            n.querySelector('.elementor-control.jprm-scope-target select') ||
            n.querySelector('.elementor-control.jprm-scope-target .select2-hidden-accessible') ||
            n.querySelector('select[data-setting*="section"]') ||
            n.querySelector('select.select2-hidden-accessible[data-setting*="section"]')
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
