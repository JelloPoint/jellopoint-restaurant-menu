(function(){
  'use strict';

  // ----- tiny logger -----
  function log(){ try{ console.log.apply(console, ['[JPRM DS]'].concat([].slice.call(arguments))); }catch(e){} }

  // ----- ajax helpers -----
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }

  // ----- elementor panel helpers -----
  function root(){ return document.querySelector('.elementor-panel'); }
  function menuSelect(r){ return r && r.querySelector('.elementor-control[data-id="menus"] [data-setting="menus"]'); }
  function dsSectionsSelect(r){ return r && r.querySelector('.elementor-control[data-id="sections"] [data-setting="sections"]'); }

  // read current menu id
  function readMenuId(r){
    const el = menuSelect(r);
    if (!el) return 0;
    const v = el.value;
    const id = parseInt(v||0, 10);
    return Number.isFinite(id) ? id : 0;
  }

  // fetch map id => label (tree, owner-scoped) from PHP
  async function fetchSectionsMap(menuId){
    const body = new URLSearchParams();
    body.set('action', 'jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    const n = ajaxNonce();
    if (n) body.set('_ajax_nonce', n);

    try{
      const res = await fetch(ajaxUrl(), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
      });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const json = await res.json();
      if (!json || !json.success || !json.data) return {};
      return json.data; // {id: "— label", ...}
    }catch(e){
      log('AJAX error', e);
      return {};
    }
  }

  // signature to avoid unnecessary rebuilds
  function sig(map){
    const k = Object.keys(map||{}).sort();
    return k.map(id => id+':'+String(map[id]||'').length).join('|');
  }

  function selectedValues(selectEl){
    const vals = [];
    if (!selectEl) return vals;
    for (const o of selectEl.options) if (o.selected) vals.push(String(o.value));
    return vals;
  }

  function setSelectedValues(selectEl, values){
    const keep = new Set((values||[]).map(String));
    for (const o of selectEl.options) o.selected = keep.has(String(o.value));
    if (typeof jQuery !== 'undefined') jQuery(selectEl).trigger('change', { silent:true });
  }

  function rebuildSelectOptions(selectEl, map, isMultiple){
    const newSig = sig(map);
    const prevSig = selectEl.getAttribute('data-jprm-ds-sig') || '';
    if (newSig === prevSig) return false; // nothing changed

    const keep = selectedValues(selectEl);
    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

    if (!isMultiple){
      const emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = '';
      selectEl.appendChild(emptyOpt);
    }

    Object.keys(map||{}).forEach(id => {
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = String(map[id]||'');
      selectEl.appendChild(opt);
    });

    setSelectedValues(selectEl, keep.filter(v => Object.prototype.hasOwnProperty.call(map, v)));
    selectEl.setAttribute('data-jprm-ds-sig', newSig);

    // refresh Select2 UI if mounted
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2 && jQuery(selectEl).hasClass('select2-hidden-accessible')){
      jQuery(selectEl).trigger('change.select2');
    }
    return true;
  }

  async function refreshDS(r){
    const ds = dsSectionsSelect(r);
    if (!ds) return;
    const menuId = readMenuId(r);
    log('refresh', { menuId });

    // wait a tick to let Elementor/Select2 mount
    await new Promise(res => requestAnimationFrame(res));

    const map = await fetchSectionsMap(menuId);
    const changed = rebuildSelectOptions(ds, map, true);
    if (changed) log('applied', { total: Object.keys(map||{}).length });
  }

  function bindOnce(r){
    const m = menuSelect(r);
    if (!m || m.__jprmDSBound) return;
    m.__jprmDSBound = true;
    m.addEventListener('change', () => {
      const ds = dsSectionsSelect(r);
      if (ds) ds.removeAttribute('data-jprm-ds-sig'); // force rebuild
      refreshDS(r);
    });
  }

  function boot(){
    const r = root();
    if (!r){ setTimeout(boot, 300); return; }
    log('active');

    bindOnce(r);
    refreshDS(r); // initial

    // Observe panel changes, but only to (re)bind and refresh DS when DS control appears
    const mo = new MutationObserver((list) => {
      let needs = false;
      for (const m of list){
        if (m.type !== 'childList') continue;
        for (const n of m.addedNodes){
          if (!(n instanceof HTMLElement)) continue;
          if (n.matches && n.matches('.elementor-control[data-id="sections"], .elementor-control[data-id="menus"]')) { needs = true; break; }
          if (n.querySelector && (n.querySelector('.elementor-control[data-id="sections"]') || n.querySelector('.elementor-control[data-id="menus"]'))) { needs = true; break; }
        }
        if (needs) break;
      }
      if (!needs) return;
      bindOnce(r);
      refreshDS(r);
    });
    mo.observe(r, { childList: true, subtree: true });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
