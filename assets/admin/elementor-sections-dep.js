(function(){
  'use strict';

  /* ============== tiny logger ============== */
  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }

  /* ============== DOM helpers ============== */
  function panelRoot(){ return document.querySelector('.elementor-panel'); }
  function menuSelect(root){ return root && root.querySelector('[data-setting="menus"]'); }

  // Return the *native* <select data-setting="sections"> (not the Select2 wrapper)
  function dsSelect(root){
    if (!root) return null;
    var el = root.querySelector('.elementor-control-sections select[data-setting="sections"]') ||
             root.querySelector('select[data-setting="sections"]');
    return ensureNativeSelect(el);
  }

  // Find non-DS mirrors (use the class you added in controls)
  function findMirrorTargets(root){
    if (!root) return [];
    var list = [];
    list = list.concat([].slice.call(root.querySelectorAll('select.jprm-scope-target')));

    // Safe fallbacks if some controls didn’t get the class yet:
    list = list.concat([].slice.call(root.querySelectorAll(
      'select[data-setting="layout_split_after_section"],' +
      'select[data-setting="layout_split_after_section2"],' +
      '.elementor-control-type-repeater select[data-setting="section_id"],' +
      '.elementor-control-type-repeater select[data-setting^="section_id_"]'
    )));

    // Never include DS itself
    list = list.filter(function(n){ return n && n.getAttribute('data-setting') !== 'sections'; });

    // Normalize to native <select> only
    list = list.map(ensureNativeSelect).filter(Boolean);

    // Dedup
    var seen = new Set(), out = [];
    list.forEach(function(el){ if(!seen.has(el)) { seen.add(el); out.push(el); }});
    return out;
  }

  // If we somehow got a wrapper, try to locate the hidden native <select>
  function ensureNativeSelect(el){
    if (!el) return null;
    if (el.tagName === 'SELECT') return el;
    // Sometimes S2 returns the wrapper; look nearby for a select
    var s = el.querySelector && el.querySelector('select[data-setting]'); // child
    if (s && s.tagName === 'SELECT') return s;
    // try sibling
    if (el.previousElementSibling && el.previousElementSibling.tagName === 'SELECT') return el.previousElementSibling;
    if (el.nextElementSibling && el.nextElementSibling.tagName === 'SELECT') return el.nextElementSibling;
    return null;
  }

  /* ============== AJAX + state ============== */
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  var state = {
    inflight: null,
    lastMenuId: null,
    lastMap: null,
    debounce: null
  };

  async function fetchSectionsMap(menuId){
    if (state.inflight) { try{ state.inflight.abort(); }catch(_){} state.inflight = null; }

    var ctl = new AbortController();
    state.inflight = ctl;

    var body = new URLSearchParams();
    body.set('action','jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    var n = ajaxNonce(); if (n) body.set('_ajax_nonce', n);

    try{
      var res  = await fetch(ajaxUrl(), {
        method:'POST', credentials:'include',
        headers:{ 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(), signal: ctl.signal
      });
      var text = await res.text();
      var json = null; try{ json = JSON.parse(text); }catch(_e){ json = null; }
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

  /* ============== select utils ============== */
  function isSelect2(el){
    try{
      if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return false;
      var $el = jQuery(el);
      return $el && $el.length && $el.hasClass('select2-hidden-accessible');
    }catch(e){ return false; }
  }

  function getSelectedValues(selectEl){
    var vals = [];
    if (!selectEl || selectEl.tagName !== 'SELECT' || !selectEl.options) return vals;
    for (var i=0;i<selectEl.options.length;i++){
      if (selectEl.options[i].selected) vals.push(String(selectEl.options[i].value));
    }
    return vals;
  }

  function setSelectedValues(selectEl, values){
    if (!selectEl || selectEl.tagName !== 'SELECT' || !selectEl.options) return;
    var keep = new Set((values||[]).map(String));
    for (var i=0;i<selectEl.options.length;i++){
      selectEl.options[i].selected = keep.has(String(selectEl.options[i].value));
    }
    if (window.jQuery) jQuery(selectEl).trigger('change', { silent:true });
  }

  function optionsSignature(map){
    if (!map || typeof map !== 'object') return '';
    var keys = Object.keys(map).sort();
    return keys.map(function(k){ return k + ':' + String(map[k]||'').length; }).join('|');
  }

  function rebuildOptionsIfChanged(selectEl, map){
    var sel = ensureNativeSelect(selectEl);
    if (!sel || sel.tagName !== 'SELECT' || !map || typeof map !== 'object') return false;

    var sig  = optionsSignature(map);
    var prev = sel.getAttribute('data-jprm-sig') || '';
    if (sig === prev) return false;

    var wasMultiple = !!sel.multiple;
    var selected = getSelectedValues(sel);

    // Rebuild safely
    while (sel.firstChild) sel.removeChild(sel.firstChild);

    if (!wasMultiple){
      var emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = '';
      sel.appendChild(emptyOpt);
    }

    var ids = Object.keys(map);
    for (var i=0;i<ids.length;i++){
      var id = ids[i];
      var opt = document.createElement('option');
      opt.value = id;
      opt.textContent = String(map[id] || '');
      sel.appendChild(opt);
    }

    var kept = selected.filter(function(v){ return ids.includes(String(v)); });
    setSelectedValues(sel, kept);
    sel.setAttribute('data-jprm-sig', sig);

    if (isSelect2(sel)) jQuery(sel).trigger('change.select2');
    return true;
  }

  /* ============== core flows ============== */
  async function refreshDS(root){
    var ds = dsSelect(root);
    if (!ds) return;

    var ms = menuSelect(root);
    var menuId = (ms && parseInt(ms.value||0,10)) || 0;

    // Always refresh if we have no map yet, otherwise only when menu changes
    if (state.lastMap && state.lastMenuId === menuId && ds.getAttribute('data-jprm-sig')) {
      // We already have scoped map; still mirror to new controls that appeared
      applyMapToMirrors(root, state.lastMap);
      return;
    }

    state.lastMenuId = menuId;
    log('DS refresh', { menuId: menuId });

    var map = await fetchSectionsMap(menuId);
    if (map === null) return; // aborted

    // Apply to DS
    if (rebuildOptionsIfChanged(ds, map)) {
      log('DS applied', { total: Object.keys(map||{}).length });
    }

    // cache + mirror
    state.lastMap = map || {};
    applyMapToMirrors(root, state.lastMap);

    // broadcast (harmless if nothing listens)
    try{ window.dispatchEvent(new CustomEvent('jprm:sectionsMap', { detail:{ map: state.lastMap, menuId: menuId } })); }catch(_){}
  }

  function applyMapToMirrors(root, map){
    if (!map) return;
    var targets = findMirrorTargets(root);
    if (!targets.length) return;
    var applied = 0;
    targets.forEach(function(el){ if (rebuildOptionsIfChanged(el, map)) applied++; });
    if (applied) log('Others applied', { count: applied, totalTargets: targets.length });
  }

  function scheduleRefresh(root){
    if (state.debounce) clearTimeout(state.debounce);
    state.debounce = setTimeout(function(){ refreshDS(root); }, 120);
  }

  function bindMenuChange(root){
    var ms = menuSelect(root);
    if (!ms || ms.__jprmBound) return;
    ms.__jprmBound = true;
    ms.addEventListener('change', function(){
      // Clear signatures so both DS + mirrors rebuild
      var ds = dsSelect(root);
      if (ds) ds.removeAttribute('data-jprm-sig');
      findMirrorTargets(root).forEach(function(el){ el.removeAttribute('data-jprm-sig'); });
      scheduleRefresh(root);
    });
  }

  function boot(){
    var root = panelRoot();
    if (!root) { setTimeout(boot, 250); return; }

    log('sections-dep.js active');

    bindMenuChange(root);
    // Initial build (no need to touch the Menu first)
    scheduleRefresh(root);

    // Observe panel; whenever DS control or any mirror renders, apply current map
    var mo = new MutationObserver(function(muts){
      var relevant = false;
      for (var i=0;i<muts.length && !relevant;i++){
        var m = muts[i];
        if (m.type !== 'childList') continue;
        for (var j=0;j<m.addedNodes.length;j++){
          var n = m.addedNodes[j];
          if (!(n instanceof HTMLElement)) continue;
          if (n.querySelector && (
              n.querySelector('select[data-setting="sections"]') ||  // DS appeared
              n.querySelector('.jprm-scope-target') ||               // mirrors with class
              n.querySelector('select[data-setting="layout_split_after_section"]') ||
              n.querySelector('select[data-setting="layout_split_after_section2"]') ||
              n.querySelector('.elementor-control-type-repeater select[data-setting="section_id"]') ||
              n.querySelector('.elementor-control-type-repeater select[data-setting^="section_id_"]')
            )) {
            relevant = true; break;
          }
        }
      }
      if (!relevant) return;
      bindMenuChange(root);
      // If we already have a map, mirror instantly; otherwise refresh DS once
      if (state.lastMap) applyMapToMirrors(root, state.lastMap);
      else scheduleRefresh(root);
    });
    mo.observe(root, { childList:true, subtree:true });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
