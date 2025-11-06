(function(){
  'use strict';

  /* ---------- helpers for Elementor panel iframe ---------- */
  function panelIframe(){ return document.getElementById('elementor-panel-iframe'); }
  function pWin(){ const f = panelIframe(); return f && f.contentWindow ? f.contentWindow : window; }
  function pDoc(){ const f = panelIframe(); return f && f.contentDocument ? f.contentDocument : document; }
  function $jq(){ const w = pWin(); return w && w.jQuery ? w.jQuery : (window.jQuery || null); }

  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  function menuSelect(){ return pDoc().querySelector('select[data-setting="menus"]'); }
  function dsSelect(){    return pDoc().querySelector('.elementor-control-sections select[data-setting="sections"], select[data-setting="sections"]'); }

  function isVisible(el){
    if (!el) return false;
    const s = pWin().getComputedStyle(el);
    return s && s.display !== 'none' && s.visibility !== 'hidden';
  }

  /* ---------- basic select utilities ---------- */
  function readMenuId(){
    const el = menuSelect(); if (!el) return 0;
    const v = el.value; const id = parseInt(v || 0, 10);
    return Number.isFinite(id) ? id : 0;
  }
  function getSelValues(el){
    const out = []; if (!el) return out;
    for (const o of el.options) if (o.selected) out.push(String(o.value));
    return out;
  }
  function setSelValues(el, values){
    const keep = new Set((values||[]).map(String));
    for (const o of el.options) o.selected = keep.has(String(o.value));
    // fire change inside iframe
    el.dispatchEvent(new (pWin().Event)('input',  {bubbles:true}));
    el.dispatchEvent(new (pWin().Event)('change', {bubbles:true}));
    // kick Select2 if present (in iframe)
    const jq = $jq();
    if (jq && jq.fn && jq.fn.select2 && el.classList.contains('select2-hidden-accessible')) {
      jq(el).trigger('change.select2');
    }
  }
  function sigOf(map){
    if (!map || typeof map !== 'object') return '';
    const keys = Object.keys(map).sort();
    return keys.map(k => k + ':' + String(map[k]||'').length).join('|');
  }

  function rebuildOptionsIfChanged(el, map){
    if (!el || !isVisible(el)) return false;
    const sig = sigOf(map || {});
    const prev = el.getAttribute('data-jprm-sig') || '';
    if (sig === prev) return false;

    const wasMultiple = !!el.multiple;
    const selected = getSelValues(el);
    const ids = Object.keys(map || {});

    while (el.firstChild) el.removeChild(el.firstChild);

    if (!wasMultiple) {
      const empty = pDoc().createElement('option');
      empty.value = ''; empty.textContent = '';
      el.appendChild(empty);
    }
    ids.forEach(id => {
      const opt = pDoc().createElement('option');
      opt.value = id;
      opt.textContent = String(map[id] || '');
      el.appendChild(opt);
    });

    const kept = selected.filter(v => ids.includes(String(v)));
    setSelValues(el, kept);

    el.setAttribute('data-jprm-sig', sig);
    return true;
  }

  /* ---------- AJAX to your scoped-sections endpoint ---------- */
  const state = { lastMenuId: null, lastMap: null, inflight: null, debounce: null, poll: null };

  async function fetchScopedMap(menuId){
    if (state.inflight) { try{ state.inflight.abort(); }catch(e){} state.inflight = null; }
    const ctl = new AbortController(); state.inflight = ctl;

    const body = new URLSearchParams();
    body.set('action', 'jprm_sections_by_menu');
    body.set('menu', String(menuId || ''));
    const n = ajaxNonce(); if (n) body.set('_ajax_nonce', n);

    try{
      const res = await fetch(ajaxUrl(), {
        method:'POST', credentials:'include',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString(),
        signal: ctl.signal
      });
      const txt = await res.text();
      let json = null; try{ json = JSON.parse(txt); }catch(_){}
      if (!json || !json.success || typeof json.data !== 'object') {
        log('AJAX not OK; got:', txt.slice(0,200));
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

  /* ---------- apply: DS + every tagged select ---------- */
  function findTaggedTargets(){
    // We only touch selects we explicitly tagged in PHP with classes => 'jprm-scope-target'
    const list = Array.from(pDoc().querySelectorAll('select.jprm-scope-target')).filter(isVisible);
    return list;
  }

  async function refreshDS(){
    const ds = dsSelect(); if (!ds) return null;

    const menuId = readMenuId();
    if (state.lastMenuId === menuId && ds.getAttribute('data-jprm-sig')) return state.lastMap;

    state.lastMenuId = menuId;
    log('DS refresh', { menuId });

    const map = await fetchScopedMap(menuId);
    if (map === null) return null; // aborted

    const changed = rebuildOptionsIfChanged(ds, map || {});
    if (changed) {
      try{ ds.setAttribute('data-jprm-map', JSON.stringify(map||{})); }catch(_){}
      state.lastMap = map || {};
      log('DS applied', { total: Object.keys(state.lastMap||{}).length });
    }
    return state.lastMap;
  }

  function applyToTagged(map){
    if (!map || !Object.keys(map).length) {
      const ds = dsSelect();
      const stash = ds && ds.getAttribute('data-jprm-map');
      if (stash) { try{ map = JSON.parse(stash)||{}; }catch(_){} }
      if (!map || !Object.keys(map).length) return 0;
    }

    const targets = findTaggedTargets();
    let applied = 0;
    for (const el of targets) if (rebuildOptionsIfChanged(el, map)) applied++;
    if (applied) log('Others applied', { count: applied, totalTargets: targets.length });
    return applied;
  }

  function burstPoll(){
    const w = pWin();
    let passes = 6;
    if (state.poll) w.clearInterval(state.poll);
    state.poll = w.setInterval(() => {
      if (--passes <= 0) { w.clearInterval(state.poll); state.poll = null; }
      applyToTagged(state.lastMap);
    }, 220);
  }

  function scheduleAll(){
    const w = pWin();
    if (state.debounce) w.clearTimeout(state.debounce);
    state.debounce = w.setTimeout(async () => {
      state.debounce = null;
      const map = await refreshDS();
      if (map) { applyToTagged(map); burstPoll(); }
    }, 140);
  }

  /* ---------- bindings (robust, iframe-safe) ---------- */
  function bindMenuChange(){
    const el = menuSelect(); if (!el || el.__jprmBound) return;
    el.__jprmBound = true;
    el.addEventListener('change', () => {
      const ds = dsSelect(); if (ds) ds.removeAttribute('data-jprm-sig');
      // clear signatures on our tagged selects; they might mount later
      pDoc().querySelectorAll('select.jprm-scope-target, select[data-setting="sections"]').forEach(s => s.removeAttribute('data-jprm-sig'));
      scheduleAll();
    });
  }

  // When a repeater row is toggled open or a new row is added, its fields mount later.
  function bindRepeaterHooks(){
    const d = pDoc();
    d.addEventListener('click', (e) => {
      const H = pWin().HTMLElement, t = e.target;
      if (!(t instanceof H)) return;

      // Row toggle (opens/expands row)
      if (t.closest('.elementor-repeater-row-toggle')) {
        setTimeout(() => { applyToTagged(state.lastMap); }, 120);
        setTimeout(() => { applyToTagged(state.lastMap); }, 300);
      }

      // Add new row
      if (t.closest('.elementor-repeater__add, .elementor-repeater-add')) {
        setTimeout(() => { applyToTagged(state.lastMap); }, 150);
        setTimeout(() => { applyToTagged(state.lastMap); }, 350);
      }
    }, { passive:true });
  }

  // Tabs / panel navigation
  function bindTabNav(){
    const d = pDoc();
    d.addEventListener('click', (e) => {
      const H = pWin().HTMLElement, t = e.target;
      if (!(t instanceof H)) return;
      if (t.closest('.elementor-panel-navigation, .elementor-tab-control')) scheduleAll();
    }, { passive:true });
  }

  // Observe late mounts in the iframe DOM
  function observePanel(){
    const mo = new MutationObserver((list) => {
      let seen = false;
      for (const m of list) {
        if (m.type !== 'childList') continue;
        for (const n of m.addedNodes) {
          const H = pWin().HTMLElement;
          if (!(n instanceof H)) continue;
          if (n.querySelector && (
              n.querySelector('select[data-setting="menus"]') ||
              n.querySelector('select[data-setting="sections"]') ||
              n.querySelector('select.jprm-scope-target')
          )) { seen = true; break; }
        }
        if (seen) break;
      }
      if (seen) scheduleAll();
    });
    mo.observe(pDoc(), { childList:true, subtree:true });
  }

  /* ---------- boot ---------- */
  function boot(){
    // Wait for the iframe to exist
    const f = panelIframe();
    if (!f || !pDoc().querySelector('.elementor-panel')) { setTimeout(boot, 200); return; }

    log('sections-dep.js active');

    bindMenuChange();
    bindRepeaterHooks();
    bindTabNav();
    observePanel();

    // initial
    scheduleAll();
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
