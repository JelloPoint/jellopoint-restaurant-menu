(function(){
  'use strict';

  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  function panelRoot(){
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

  // --- DS (primary) ---
  function dsSectionsSelect(root){
    return root && (root.querySelector('.elementor-control-sections [data-setting="sections"]') || root.querySelector('[data-setting="sections"]'));
  }

  // --- Find OTHER section selects robustly; exclude DS (data-setting="sections") ---
  function findOtherSectionSelects(root){
    if (!root) return [];
    const found = new Set();

    // our class on any wrapper
    root.querySelectorAll('.jprm-scope-target select, .jprm-scope-target select.select2-hidden-accessible').forEach(el => found.add(el));

    // common Elementor wrappers
    root.querySelectorAll('.elementor-control.jprm-scope-target, .elementor-control-field.jprm-scope-target, .elementor-field.jprm-scope-target').forEach(wrap => {
      const sel1 = wrap.querySelector('select[data-setting]');
      const sel2 = wrap.querySelector('select.select2-hidden-accessible[data-setting]');
      if (sel1) found.add(sel1);
      if (sel2) found.add(sel2);
    });

    // heuristic: any select whose data-setting name contains "section"
    root.querySelectorAll('select[data-setting*="section"], select.select2-hidden-accessible[data-setting*="section"]').forEach(el => found.add(el));

    // filter
    const out = [];
    found.forEach(el => {
      const ds = (el.getAttribute('data-setting') || '').toLowerCase().trim();
      if (ds === 'sections') return;                 // skip DS
      if (!isVisible(el)) return;                    // ignore hidden/collapsed; we’ll catch it later
      out.push(el);
    });
    return out;
  }

  // --- AJAX & state ---
  const state = { inflight:null, lastMenuId:null, debounce:null, lastMapSig:'', hb:null };

  async function fetchSectionsMap(menuId){
    if (state.inflight) { try{ state.inflight.abort(); }catch(_e){} state.inflight = null; }
    const ctl = new AbortController(); state.inflight = ctl;

    const body = new URLSearchParams();
    body.set('action','jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    const n = ajaxNonce(); if (n) body.set('_ajax_nonce', n);

    try{
      const res = await fetch(ajaxUrl(), {
        method:'POST', credentials:'include',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString(), signal: ctl.signal
      });
      const text = await res.text();
      let json=null; try{ json=JSON.parse(text); }catch(_e){}
      if (!json || !json.success || !json.data) {
        log('AJAX payload not OK; got:', text.slice(0,200));
        return {};
      }
      return json.data;
    }catch(e){
      if (e && e.name === 'AbortError') return null;
      log('AJAX error', e); return {};
    }finally{
      if (state.inflight === ctl) state.inflight = null;
    }
  }

  function rebuildOptionsIfChanged(selectEl, map){
    if (!selectEl || !isVisible(selectEl)) return false;
    const sig = optionsSignature(map);
    const prevSig = selectEl.getAttribute('data-jprm-sig') || '';
    if (sig === prevSig) return false;

    const multiple = !!selectEl.multiple;
    const selected = getSelectedValues(selectEl);
    const ids = Object.keys(map || {});

    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);
    if (!multiple) {
      const emptyOpt = document.createElement('option');
      emptyOpt.value = ''; emptyOpt.textContent = '';
      selectEl.appendChild(emptyOpt);
    }
    ids.forEach(id => {
      const opt = document.createElement('option');
      opt.value = id; opt.textContent = String(map[id] || '');
      selectEl.appendChild(opt);
    });

    const kept = selected.filter(v => ids.includes(String(v)));
    setSelectedValues(selectEl, kept);
    selectEl.setAttribute('data-jprm-sig', sig);

    if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2 && jQuery(selectEl).hasClass('select2-hidden-accessible')) {
      jQuery(selectEl).trigger('change.select2');
    }
    return true;
  }

  async function refreshDS(root){
    const sel = dsSectionsSelect(root);
    if (!sel) return;

    const menuId = readMenuId(root);
    if (state.lastMenuId === menuId && sel.getAttribute('data-jprm-sig')) return;
    state.lastMenuId = menuId;

    log('DS refresh', { menuId });
    const map = await fetchSectionsMap(menuId);
    if (map === null) return;

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
    }, 140);
  }

  function bindMenuChange(root){
    const ms = root && root.querySelector('[data-setting="menus"]');
    if (!ms || ms.__jprmBound) return;
    ms.__jprmBound = true;
    ms.addEventListener('change', function(){
      const ds = dsSectionsSelect(root);
      if (ds) ds.removeAttribute('data-jprm-sig');
      // clear signatures on visible targets; hidden ones will be handled by heartbeat
      findOtherSectionSelects(root).forEach(el => el.removeAttribute('data-jprm-sig'));
      scheduleRefresh(root);
    });
  }

  // Force refresh on any panel interaction (tab switch, accordion open, repeater row open, etc.)
  function bindPanelClicks(root){
    if (root.__jprmClicksBound) return;
    root.__jprmClicksBound = true;
    root.addEventListener('click', function(){
      setTimeout(()=> scheduleRefresh(root), 120);
    }, { passive:true });
  }

  // Safety net: heartbeat poll while the panel is open
  function startHeartbeat(root){
    if (state.hb) clearInterval(state.hb);
    state.hb = setInterval(()=> {
      scheduleRefresh(root);
    }, 900);
  }

  function bindObserver(root){
    const mo = new MutationObserver((list) => {
      for (const m of list) {
        if (m.type !== 'childList') continue;
        // any additions inside panel → schedule
        if (m.addedNodes && m.addedNodes.length) {
          scheduleRefresh(root);
          break;
        }
      }
    });
    mo.observe(root, { childList:true, subtree:true });
  }

  function boot(){
    const root = panelRoot();
    if (!root) { setTimeout(boot, 300); return; }

    log('sections-dep.js active');

    bindMenuChange(root);
    bindPanelClicks(root);
    bindObserver(root);
    startHeartbeat(root);
    scheduleRefresh(root);
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
