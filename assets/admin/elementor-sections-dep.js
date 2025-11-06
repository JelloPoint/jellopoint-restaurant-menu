(function(){
  'use strict';

  // Polling cadence keeps it robust without Elementor internals
  var TICK_MS = 600;

  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  function panelRoot(){ return document.querySelector('.elementor-panel'); }

  // DS controls (keep exactly as you had)
  function menuSelect(root){ return root && root.querySelector('[data-setting="menus"]'); }
  function dsSectionsSelect(root){
    return root && (root.querySelector('.elementor-control-sections [data-setting="sections"]')
      || root.querySelector('[data-setting="sections"]'));
  }

  // Other section-scoped selects
  function splitAfterSelects(root){
    if (!root) return [];
    return Array.from(root.querySelectorAll(
      '[data-setting="layout_split_after_section"], [data-setting="layout_split_after_section2"]'
    ));
  }
  function repeaterSectionSelects(root){
    if (!root) return [];
    var out = [];
    root.querySelectorAll('.elementor-control-type-repeater[data-id="labels_layout_overrides"], .elementor-control-type-repeater[data-id="info_blocks"]').forEach(function(rep){
      out.push.apply(out, rep.querySelectorAll('select[data-setting="section_id"]'));
    });
    return out;
  }
  // Any select you marked in PHP with classes => 'jprm-scope-target'
  function markedScopeTargets(root){
    if (!root) return [];
    return Array.from(root.querySelectorAll('select.jprm-scope-target[data-setting="section_id"]'));
  }

  // Treat hidden Select2 selects as “visible” so we still update their options
  function isVisible(el){
    if (!el) return false;
    if (el.classList && el.classList.contains('select2-hidden-accessible')) return true; // hidden by Select2 but must be updated
    var s = getComputedStyle(el);
    return s.display !== 'none' && s.visibility !== 'hidden';
  }

  function readMenuId(root){
    var el = menuSelect(root);
    if (!el) return 0;
    var v = el.value;
    var id = parseInt(v || 0, 10);
    return Number.isFinite(id) ? id : 0;
  }

  function getSelectedValues(selectEl){
    var vals = [];
    if (!selectEl) return vals;
    for (var i=0;i<selectEl.options.length;i++){
      var opt = selectEl.options[i];
      if (opt.selected) vals.push(String(opt.value));
    }
    return vals;
  }

  function setSelectedValues(selectEl, values){
    var keep = new Set((values||[]).map(String));
    for (var i=0;i<selectEl.options.length;i++){
      var opt = selectEl.options[i];
      opt.selected = keep.has(String(opt.value));
    }
    if (window.jQuery) { jQuery(selectEl).trigger('change', { silent:true }); }
  }

  function optionsSignature(map){
    if (!map || typeof map !== 'object') return '';
    var keys = Object.keys(map).sort();
    var acc = [];
    for (var i=0;i<keys.length;i++){
      var k = keys[i];
      acc.push(k + ':' + String(map[k]||'').length);
    }
    return acc.join('|');
  }

  var state = {
    lastMenuId: null,
    lastMap: null,
    lastMapSig: '',
    inflight: null
  };

  async function fetchSectionsMap(menuId){
    if (state.inflight) { try { state.inflight.abort(); } catch(_e){} state.inflight = null; }
    var ctl = new AbortController();
    state.inflight = ctl;

    var body = new URLSearchParams();
    body.set('action','jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    var n = ajaxNonce(); if (n) body.set('_ajax_nonce', n);

    try{
      var res = await fetch(ajaxUrl(), {
        method:'POST', credentials:'include',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString(),
        signal: ctl.signal
      });
      var text = await res.text();
      var json = null; try { json = JSON.parse(text); } catch(_e) {}
      if (!json || !json.success || !json.data) {
        log('AJAX payload not OK; head=', text.slice(0,200));
        return {};
      }
      return json.data; // { id: "— label", ... }
    }catch(e){
      if (e && e.name === 'AbortError') return null;
      log('AJAX error', e);
      return {};
    }finally{
      if (state.inflight === ctl) state.inflight = null;
    }
  }

  function rebuildOptionsIfChanged(selectEl, map){
    if (!selectEl || !isVisible(selectEl)) return false;

    var sig = optionsSignature(map);
    var prevSig = selectEl.getAttribute('data-jprm-sig') || '';
    if (sig === prevSig) return false;

    var wasMultiple = !!selectEl.multiple;
    var selected = getSelectedValues(selectEl);
    var ids = Object.keys(map || {});

    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

    if (!wasMultiple) {
      var emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = '';
      selectEl.appendChild(emptyOpt);
    }

    for (var i=0;i<ids.length;i++){
      var id = ids[i];
      var opt = document.createElement('option');
      opt.value = id;
      opt.textContent = String(map[id] || '');
      selectEl.appendChild(opt);
    }

    var kept = selected.filter(function(v){ return ids.includes(String(v)); });
    setSelectedValues(selectEl, kept);

    selectEl.setAttribute('data-jprm-sig', sig);

    // Refresh Select2 UI if this select is enhanced
    if (window.jQuery && jQuery.fn && jQuery.fn.select2 && jQuery(selectEl).hasClass('select2-hidden-accessible')) {
      jQuery(selectEl).trigger('change.select2');
    }
    return true;
  }

  async function refreshNow(root){
    if (!root) return;

    var menuId = readMenuId(root);
    var needFetch = false;
    if (state.lastMap == null) needFetch = true;
    if (state.lastMenuId !== menuId) needFetch = true;

    var map = state.lastMap;
    if (needFetch) {
      var fetched = await fetchSectionsMap(menuId);
      if (fetched === null) return; // aborted
      map = fetched || {};
      state.lastMap = map;
      state.lastMapSig = optionsSignature(map);
      state.lastMenuId = menuId;
    }

    // DS first
    var dsSel = dsSectionsSelect(root);
    if (dsSel) {
      var changedDS = rebuildOptionsIfChanged(dsSel, map);
      if (changedDS) log('DS applied', { total:Object.keys(map||{}).length });
    }

    // Others (plain selects, repeater select2, explicit scope-targets)
    var targets = []
      .concat(splitAfterSelects(root))
      .concat(repeaterSectionSelects(root))
      .concat(markedScopeTargets(root))
      .filter(isVisible);

    if (targets.length){
      var applied = 0;
      targets.forEach(function(el){ if (rebuildOptionsIfChanged(el, map)) applied++; });
      if (applied) log('Others applied', { count:applied, totalTargets:targets.length });
    }
  }

  function bindMenuImmediate(root){
    var ms = menuSelect(root);
    if (!ms || ms.__jprmBound) return;
    ms.__jprmBound = true;
    ms.addEventListener('change', function(){
      // Invalidate cache & clear signatures on all scoped selects so they rebuild
      state.lastMap = null;
      state.lastMapSig = '';

      var ds = dsSectionsSelect(root);
      if (ds) ds.removeAttribute('data-jprm-sig');

      markedScopeTargets(root).forEach(function(el){ el.removeAttribute('data-jprm-sig'); });
      splitAfterSelects(root).forEach(function(el){ el.removeAttribute('data-jprm-sig'); });
      repeaterSectionSelects(root).forEach(function(el){ el.removeAttribute('data-jprm-sig'); });

      // refresh immediately
      refreshNow(root);
    });
  }

  function tick(){
    var root = panelRoot();
    if (!root) return;
    bindMenuImmediate(root);
    refreshNow(root);
  }

  function boot(){
    log('sections-dep.js active (polling)');
    setInterval(tick, TICK_MS);
    setTimeout(tick, 150);
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
