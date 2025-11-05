/* global elementor, JPRMAjax */
(function () {
  'use strict';

  const d = document;
  const LOG = '[JPRM]';

  function log(){ try{ console.log.apply(console,[LOG].concat([].slice.call(arguments))); }catch(e){} }

  // ---- AJAX ----
  async function fetchSectionsMap(menuId){
    if(!menuId){ return null; } // no menu selected → do nothing
    const url   = (window.JPRMAjax && JPRMAjax.url)   ? JPRMAjax.url   : '/wp-admin/admin-ajax.php';
    const nonce = (window.JPRMAjax && JPRMAjax.nonce) ? JPRMAjax.nonce : '';
    const body  = new URLSearchParams();
    body.set('action','jprm_sections_for_menu');
    body.set('menu_id', String(menuId));
    if(nonce) body.set('nonce', nonce);

    try{
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
      });
      const json = await res.json();
      if(!json || !json.success || !json.data || !Array.isArray(json.data.sections)){ return null; }

      // id → label (with indentation for tree)
      const map = {};
      json.data.sections.forEach(s=>{
        const lvl = Math.max(0, parseInt(s.level||0,10));
        const indent = lvl ? '\u00A0\u00A0'.repeat(lvl) : '';
        map[String(s.id)] = indent + (s.text || '');
      });
      // if empty, bail (don’t clobber)
      if(Object.keys(map).length === 0){ return null; }
      return map;
    }catch(e){
      log('AJAX error', e);
      return null; // fail-safe: do nothing
    }
  }

  // ---- ELEMENTOR HELPERS ----
  function panelRoot(){ return d.querySelector('.elementor-panel') || d.body; }

  function getMenuId(){
    try{
      if(window.elementor && elementor.getPanelView){
        const pv = elementor.getPanelView().getCurrentPanelView();
        const v  = pv && pv.model && pv.model.getSetting('menus');
        if(Array.isArray(v)) return v[0] || '';
        return v || '';
      }
    }catch(e){}
    const sel = panelRoot().querySelector('select[data-setting="menus"]');
    if(!sel) return '';
    return sel.multiple ? (sel.value && sel.value[0]) : sel.value;
  }

  // Only target NON–Data-Source selects:
  function collectTargets(scope){
    const root = scope || panelRoot();
    const out  = new Set();

    // Layout split selects
    root.querySelectorAll('select[data-setting="layout_split_after_section"]').forEach(n=>out.add(n));
    root.querySelectorAll('select[data-setting="layout_split_after_section2"]').forEach(n=>out.add(n));

    // Info Blocks repeater → [section_id]
    root.querySelectorAll('select[name$="[section_id]"]').forEach(n=>{
      if (n.closest('[data-repeater-items]') || n.closest('.elementor-repeater-fields')) out.add(n);
    });

    // Labels Layout Overrides repeater → [section_id]
    root.querySelectorAll('select[name^="labels_layout_overrides"][name$="[section_id]"]').forEach(n=>out.add(n));

    return Array.from(out);
  }

  function applyOptions(selectEl, idToLabel){
    if(!selectEl || !idToLabel) return;

    const multiple = !!selectEl.multiple;
    const keep = (function(){
      if(multiple){
        return Array.from(selectEl.selectedOptions||[])
          .map(o=>o.value)
          .filter(v => Object.prototype.hasOwnProperty.call(idToLabel, String(v)));
      }
      const v = selectEl.value;
      return Object.prototype.hasOwnProperty.call(idToLabel, String(v)) ? [String(v)] : [];
    })();

    // rebuild options (do NOT inject a blank for multi)
    while(selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);
    if(!multiple){
      selectEl.appendChild(new Option('', '')); // allow clearing
    }
    Object.keys(idToLabel).forEach(id=>{
      selectEl.appendChild(new Option(idToLabel[id], id, false, keep.includes(id)));
    });

    // notify Elementor/Select2
    const evt = new Event('change', { bubbles:true });
    selectEl.dispatchEvent(evt);
  }

  let inflight = 0;
  async function refreshScopes(scope){
    const menuId = getMenuId();
    if(!menuId){ log('No menu selected → skip'); return; }

    const ticket = ++inflight;
    const map = await fetchSectionsMap(menuId);
    if(ticket !== inflight) return;           // superseded
    if(!map){ log('No scoped map (AJAX fail/empty) → do nothing'); return; } // don’t clobber

    const targets = collectTargets(scope);
    targets.forEach(sel => applyOptions(sel, map));
    log('Scoped non-DataSource selects', { menuId, targets: targets.length, options: Object.keys(map).length });
  }

  function bindMenuChange(){
    const sel = panelRoot().querySelector('select[data-setting="menus"]');
    if(!sel) return;
    sel.addEventListener('change', ()=>refreshScopes(), { passive:true });
  }

  function startObserver(){
    const root = panelRoot();
    const mo   = new MutationObserver(muts=>{
      let touched = false;
      for(const m of muts){
        for(const n of m.addedNodes || []){
          if(!(n instanceof HTMLElement)) continue;
          if(
            n.matches('select[data-setting="layout_split_after_section"], select[data-setting="layout_split_after_section2"], select[name$="[section_id]"]') ||
            n.querySelector && (
              n.querySelector('select[data-setting="layout_split_after_section"]') ||
              n.querySelector('select[data-setting="layout_split_after_section2"]') ||
              n.querySelector('select[name$="[section_id]"]')
            )
          ){
            touched = true;
          }
        }
      }
      if(touched) refreshScopes(root);
    });
    mo.observe(root, { childList:true, subtree:true });
    return mo;
  }

  function boot(){
    bindMenuChange();
    startObserver();
    // initial pass (will skip if no valid menu / no map)
    refreshScopes();
    log('sections-dep (safe) active');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
