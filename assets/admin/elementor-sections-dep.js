(function(){
  'use strict';

  // ---- polling cadence (robust across Elementor versions) ----
  var TICK_MS = 600;

  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  // Controls panel root (not the preview iframe)
  function panelRoot(){ return document.querySelector('.elementor-panel'); }

  // ---- DS (Data Source) controls ----
  function menuSelect(root){ return root && root.querySelector('[data-setting="menus"]'); }
  function dsSectionsSelect(root){
    // Your DS "Sections" control
    return root && (root.querySelector('.elementor-control-sections [data-setting="sections"]')
      || root.querySelector('[data-setting="sections"]'));
  }

  // ---- Other scoped targets (we rely on the class you add in PHP) ----
  // Elementor may render a SELECT2 either as a hidden <select> OR a hidden <input type="hidden">
  function findScopeTargets(root){
    if (!root) return [];
    var sel = root.querySelectorAll(
      'select.jprm-scope-target[data-setting="section_id"], input.jprm-scope-target[data-setting="section_id"]'
    );
    return Array.prototype.slice.call(sel);
  }

  // Consider Select2-hidden controls as "visible" so they still update
  function isVisible(el){
    if (!el) return false;
    if (el.classList && el.classList.contains('select2-hidden-accessible')) return true;
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

  // Convert {id: "— label"} into Select2 data [{id,text},...]
  function mapToSelect2Data(map){
    var out = [];
    if (!map) return out;
    Object.keys(map).forEach(function(id){
      out.push({ id: id, text: String(map[id] || '') });
    });
    return out;
  }

  var cache = {
    lastMenuId: null,
    lastMap: null,
    lastSig: '',
    inflight: null
  };

  async function fetchSectionsMap(menuId){
    if (cache.inflight) { try{ cache.inflight.abort(); }catch(_e){} cache.inflight = null; }
    var ctl = new AbortController();
    cache.inflight = ctl;

    var body = new URLSearchParams();
    body.set('action', 'jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    var n = ajaxNonce(); if (n) body.set('_ajax_nonce', n);

    try{
      var res = await fetch(ajaxUrl(), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
        signal: ctl.signal
      });
      var text = await res.text();
      var json = null; try { json = JSON.parse(text); } catch(_e){}
      if (!json || !json.success || !json.data) {
        log('AJAX payload not OK; head=', text.slice(0,200));
        return {};
      }
      return json.data;
    }catch(e){
      if (e && e.name === 'AbortError') return null;
      log('AJAX error', e);
      return {};
    }finally{
      if (cache.inflight === ctl) cache.inflight = null;
    }
  }

  // ----- DOM helpers (SELECT) -----
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
    if (window.jQuery) jQuery(selectEl).trigger('change', { silent:true });
  }
  function rebuildNativeSelect(selectEl, map){
    var wasMultiple = !!selectEl.multiple;
    var selected = getSelectedValues(selectEl);
    var ids = Object.keys(map||{});

    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

    if (!wasMultiple) {
      var emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = '';
      selectEl.appendChild(emptyOpt);
    }
    ids.forEach(function(id){
      var opt = document.createElement('option');
      opt.value = id;
      opt.textContent = String(map[id]||'');
      selectEl.appendChild(opt);
    });

    var kept = selected.filter(function(v){ return ids.includes(String(v)); });
    setSelectedValues(selectEl, kept);
  }

  // ----- Select2 helpers (for hidden <input> or hidden <select>) -----
  function isSelect2(el){
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return false;
    return jQuery(el).hasClass('select2-hidden-accessible') || el.tagName === 'INPUT';
  }
  function rebuildSelect2(el, map){
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return false;

    var $el = jQuery(el);
    var prevVal = $el.val();
    try { $el.select2('destroy'); } catch(_e) {}

    // For <input type=hidden>, ensure it’s empty
    if (el.tagName === 'INPUT') {
      $el.val('');
    } else if (el.tagName === 'SELECT') {
      // rebuild options into the SELECT, so Select2 can read them
      rebuildNativeSelect(el, map);
    }

    // Re-init with new data
    var data = mapToSelect2Data(map);
    // If element is <input>, feed data via Select2 config; if <select>, it reads from options
    if (el.tagName === 'INPUT') {
      $el.select2({ data: data, width: '100%' });
    } else {
      $el.select2({ width: '100%' });
    }

    // Restore selection if still present
    if (prevVal && data.some(function(d){ return String(d.id) === String(prevVal); })) {
      $el.val(prevVal).trigger('change');
    } else {
      // ensure empty if previous selection vanished
      $el.val(null).trigger('change');
    }
    return true;
  }

  function signature(el){ return el.getAttribute('data-jprm-sig') || ''; }
  function setSignature(el, sig){ el.setAttribute('data-jprm-sig', sig); }

  function applyMapToElement(el, map, sig){
    if (!isVisible(el)) return false;

    var prevSig = signature(el);
    if (prevSig === sig) return false;

    var changed = false;

    if (isSelect2(el)) {
      changed = rebuildSelect2(el, map);
    } else if (el.tagName === 'SELECT') {
      rebuildNativeSelect(el, map);
      changed = true;
    }

    if (changed) setSignature(el, sig);
    return changed;
  }

  async function refreshAll(root){
    if (!root) return;

    var menuId = readMenuId(root);
    var mustFetch = (cache.lastMap == null) || (cache.lastMenuId !== menuId);

    var map = cache.lastMap;
    if (mustFetch) {
      log('DS refresh', { menuId: menuId });
      var fetched = await fetchSectionsMap(menuId);
      if (fetched === null) return; // aborted
      map = fetched || {};
      cache.lastMap = map;
      cache.lastSig = optionsSignature(map);
      cache.lastMenuId = menuId;
    }

    // 1) DS first
    var ds = dsSectionsSelect(root);
    if (ds && applyMapToElement(ds, map, cache.lastSig)) {
      log('DS applied', { total: Object.keys(map||{}).length });
    }

    // 2) All other scoped section controls
    var targets = findScopeTargets(root);
    var applied = 0;
    for (var i=0;i<targets.length;i++){
      if (applyMapToElement(targets[i], map, cache.lastSig)) applied++;
    }
    if (applied) log('Others applied', { count: applied, totalTargets: targets.length });
  }

  function bindMenuChange(root){
    var ms = menuSelect(root);
    if (!ms || ms.__jprmBound) return;
    ms.__jprmBound = true;
    ms.addEventListener('change', function(){
      // invalidate cache + signatures → force rebuild on next tick
      cache.lastMap = null;
      cache.lastSig = '';
      var r = panelRoot();
      if (!r) return;

      var ds = dsSectionsSelect(r);
      if (ds) ds.removeAttribute('data-jprm-sig');

      findScopeTargets(r).forEach(function(el){ el.removeAttribute('data-jprm-sig'); });

      // kick a quick refresh
      setTimeout(function(){ refreshAll(r); }, 50);
    });
  }

  function tick(){
    var root = panelRoot();
    if (!root) return;
    bindMenuChange(root);
    refreshAll(root);
  }

  function boot(){
    log('sections-dep.js active (polling)');
    setInterval(tick, TICK_MS);
    setTimeout(tick, 150);
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
