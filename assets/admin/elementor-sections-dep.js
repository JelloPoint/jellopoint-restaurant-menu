(function(){
  'use strict';

  /* ------------------------------
   * Minimal, robust logging
   * ------------------------------ */
  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }

  /* ------------------------------
   * Elementor editor DOM helpers
   * ------------------------------ */
  function panelRoot(){ return document.querySelector('.elementor-panel'); }
  function menuSelect(root){ return root && root.querySelector('[data-setting="menus"]'); }

  // DS control (your SELECT2 “Sections” in Data Source tab)
  function dsSectionsSelect(root){
    return root && (
      root.querySelector('.elementor-control-sections [data-setting="sections"]') ||
      root.querySelector('[data-setting="sections"]')
    );
  }

  // Prefer explicit class added in your controls: 'classes' => 'jprm-scope-target'
  // Also include safe fallbacks for your known field names
  function findMirrorTargets(root){
    if (!root) return [];
    let list = [];
    list = list.concat([].slice.call(root.querySelectorAll('.jprm-scope-target')));
    list = list.concat([].slice.call(root.querySelectorAll(
      '[data-setting="layout_split_after_section"],' +
      '[data-setting="layout_split_after_section2"],' +
      '.elementor-control-type-repeater select[data-setting="section_id"],' +
      '.elementor-control-type-repeater select[data-setting="section_id_info"],' +
      '.elementor-control-type-repeater select[data-setting^="section_id"]'
    )));
    list = list.filter(el => el.getAttribute('data-setting') !== 'sections'); // exclude DS itself
    return Array.from(new Set(list));
  }

  /* ------------------------------
   * AJAX + state
   * ------------------------------ */
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  const state = {
    inflight: null,
    lastMenuId: null,
    lastMap: null,
    debounceTimer: null
  };

  async function fetchSectionsMap(menuId){
    if (state.inflight) { try{ state.inflight.abort(); }catch(_e){} state.inflight = null; }
    const ctl = new AbortController();
    state.inflight = ctl;

    const body = new URLSearchParams();
    body.set('action', 'jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    const n = ajaxNonce(); if (n) body.set('_ajax_nonce', n);

    try{
      const res  = await fetch(ajaxUrl(), {
        method:'POST', credentials:'include',
        headers:{ 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(), signal: ctl.signal
      });
      const text = await res.text();
      let json = null; try{ json = JSON.parse(text); }catch(_){ json = null; }
      if (!json || !json.success || !json.data) {
        log('AJAX payload not OK; head=', text.slice(0, 200));
        return {};
      }
      return json.data; // { id: "— label", ... }
    }catch(e){
      if (e && e.name === 'AbortError') return null;
      log('AJAX error', e);
      return {};
    } finally {
      if (state.inflight === ctl) state.inflight = null;
    }
  }

  /* ------------------------------
   * Select utilities
   * ------------------------------ */
  function isSelect2(el){
    try{
      if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return false;
      const $el = jQuery(el);
      return $el && $el.length && $el.hasClass('select2-hidden-accessible');
    }catch(e){ return false; }
  }

  function getSelectedValues(selectEl){
    const vals = [];
    if (!selectEl) return vals;
    for (let i=0;i<selectEl.options.length;i++){
      if (selectEl.options[i].selected) vals.push(String(selectEl.options[i].value));
    }
    return vals;
  }
  function setSelectedValues(selectEl, values){
    const keep = new Set((values||[]).map(String));
    for (let i=0;i<selectEl.options.length;i++){
      selectEl.options[i].selected = keep.has(String(selectEl.options[i].value));
    }
    if (window.jQuery) jQuery(selectEl).trigger('change',{silent:true});
  }
  function optionsSignature(map){
    if (!map || typeof map !== 'object') return '';
    const keys = Object.keys(map).sort();
    return keys.map(k => k + ':' + String(map[k]||'').length).join('|');
  }
  function rebuildOptionsIfChanged(selectEl, map){
    if (!selectEl) return false;
    const sig  = optionsSignature(map);
    const prev = selectEl.getAttribute('data-jprm-sig') || '';
    if (sig === prev) return false;

    const wasMultiple = !!selectEl.multiple;
    const selected    = getSelectedValues(selectEl);

    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

    if (!wasMultiple) {
      const emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = '';
      selectEl.appendChild(emptyOpt);
    }

    const ids = Object.keys(map||{});
    ids.forEach(id => {
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = String(map[id]||'');
      selectEl.appendChild(opt);
    });

    const kept = selected.filter(v => ids.includes(String(v)));
    setSelectedValues(selectEl, kept);
    selectEl.setAttribute('data-jprm-sig', sig);

    if (isSelect2(selectEl)) jQuery(selectEl).trigger('change.select2');
    return true;
  }

  /* ------------------------------
   * DS refresh + broadcast + mirror
   * ------------------------------ */
  async function refreshDS(root){
    const dsSel = dsSectionsSelect(root);
    if (!dsSel) return;

    const ms = menuSelect(root);
    const menuId = (ms && parseInt(ms.value||0,10)) || 0;
    if (state.lastMenuId === menuId && dsSel.getAttribute('data-jprm-sig')) return;

    state.lastMenuId = menuId;
    log('DS refresh', { menuId });

    const map = await fetchSectionsMap(menuId);
    if (map === null) return; // aborted

    // Update DS itself
    if (rebuildOptionsIfChanged(dsSel, map||{})) {
      log('DS applied', { total: Object.keys(map||{}).length });
    }

    // Save + broadcast for any external listeners (or older snippet if still enabled)
    state.lastMap = map || {};
    try {
      window.dispatchEvent(new CustomEvent('jprm:sectionsMap', { detail: { map: state.lastMap, menuId } }));
    } catch(_e){}

    // Mirror into other section dropdowns right away
    applyMapToMirrors(root, state.lastMap);
  }

  function applyMapToMirrors(root, map){
    const targets = findMirrorTargets(root);
    if (!targets.length) return;

    let applied = 0;
    targets.forEach(el => { if (rebuildOptionsIfChanged(el, map)) applied++; });
    if (applied) log('Others applied', { count: applied, totalTargets: targets.length });
  }

  function scheduleRefresh(root){
    if (state.debounceTimer) clearTimeout(state.debounceTimer);
    state.debounceTimer = setTimeout(function(){
      state.debounceTimer = null;
      refreshDS(root);
    }, 120);
  }

  function bindMenuChange(root){
    const ms = menuSelect(root);
    if (!ms || ms.__jprmBound) return;
    ms.__jprmBound = true;
    ms.addEventListener('change', function(){
      const ds = dsSectionsSelect(root);
      if (ds) ds.removeAttribute('data-jprm-sig');
      // Clear mirror signatures so they rebuild
      findMirrorTargets(root).forEach(el => el.removeAttribute('data-jprm-sig'));
      scheduleRefresh(root);
    });
  }

  function boot(){
    const root = panelRoot();
    if (!root){ setTimeout(boot,250); return; }

    log('sections-dep.js active');

    bindMenuChange(root);
    scheduleRefresh(root); // initial

    // Re-apply map as controls/tab panes appear
    const mo = new MutationObserver(function(){
      bindMenuChange(root);
      // If we already have a map, apply it to any newly-rendered controls
      if (state.lastMap) applyMapToMirrors(root, state.lastMap);
    });
    mo.observe(root, { childList:true, subtree:true });

    // Also listen to our own broadcast (harmless if snippet were still enabled)
    window.addEventListener('jprm:sectionsMap', function(ev){
      if (!ev || !ev.detail || !ev.detail.map) return;
      state.lastMap = ev.detail.map;
      applyMapToMirrors(panelRoot(), state.lastMap);
    });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
