/* global elementor, JPRMAjax, jQuery */
(function () {
  'use strict';

  const d = document;
  const LOG = '[JPRM]';

  function log(){ try{ console.log.apply(console,[LOG].concat([].slice.call(arguments))); }catch(e){} }

  // ---- AJAX ----
  async function fetchSectionsMap(menuId){
    if(!menuId){ return null; }
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
      if(Object.keys(map).length === 0){ return null; }
      return map;
    }catch(e){
      log('AJAX error', e);
      return null;
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

  // Collect ALL section selects to scope (includes Data Source now)
  function collectTargets(scope){
    const root = scope || panelRoot();
    const out  = new Set();

    // Data Source → Sections (SELECT2, multiple)
    root.querySelectorAll('select[data-setting="sections"]').forEach(n=>out.add(n));

    // Layout → Split after section (1/2) (plain SELECT)
    root.querySelectorAll('select[data-setting="layout_split_after_section"]').forEach(n=>out.add(n));
    root.querySelectorAll('select[data-setting="layout_split_after_section2"]').forEach(n=>out.add(n));

    // Info Blocks repeater → Target Section (SELECT2)
    root.querySelectorAll('select[name$="[section_id]"]').forEach(n=>{
      if (n.closest('[data-repeater-items]') || n.closest('.elementor-repeater-fields')) out.add(n);
    });

    // Labels Layout Overrides repeater → Section (plain SELECT)
    root.querySelectorAll('select[name^="labels_layout_overrides"][name$="[section_id]"]').forEach(n=>out.add(n));

    return Array.from(out);
  }

  // Apply options to a select while preserving valid selections (Select2 aware)
  function applyOptions(selectEl, idToLabel){
    if(!selectEl || !idToLabel) return;

    const isMultiple = !!selectEl.multiple;

    // Keep only selections that still exist
    const keep = (function(){
      if(isMultiple){
        const vals = Array.from(selectEl.selectedOptions || []).map(o => o.value);
        return vals.filter(v => Object.prototype.hasOwnProperty.call(idToLabel, String(v)));
      }
      const v = selectEl.value;
      return Object.prototype.hasOwnProperty.call(idToLabel, String(v)) ? [String(v)] : [];
    })();

    // If this is a Select2, temporarily detach to avoid flicker
    let wasSelect2 = false;
    try {
      if (window.jQuery && jQuery.fn && jQuery(selectEl).data('select2')) {
        wasSelect2 = true;
        jQuery(selectEl).select2('destroy');
      }
    } catch(e){}

    // Rebuild options
    while(selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

    if(!isMultiple){
      // allow clearing single-selects
      selectEl.appendChild(new Option('', ''));
    }

    Object.keys(idToLabel).forEach(id=>{
      const opt = new Option(idToLabel[id], id, false, keep.includes(id));
      selectEl.appendChild(opt);
    });

    // Restore selection
    if(isMultiple){
      // set multiple values
      Array.from(selectEl.options).forEach(o => { o.selected = keep.includes(o.value); });
    }else{
      selectEl.value = keep[0] || '';
    }

    // Re-init Select2 if it was one
    try{
      if(wasSelect2 && window.jQuery && jQuery.fn){
        jQuery(selectEl).select2({ width: '100%' });
      }
    }catch(e){}

    // Notify Elementor/Select2 about the change
    try{
      if(window.jQuery && jQuery.fn){
        jQuery(selectEl).trigger('change');
      }else{
        const evt = new Event('change', { bubbles:true });
        selectEl.dispatchEvent(evt);
      }
    }catch(e){}
  }

  let inflight = 0;
  async function refreshAll(scope){
    const menuId = getMenuId();
    if(!menuId){ log('No menu selected → skip'); return; }

    const ticket = ++inflight;
    const map = await fetchSectionsMap(menuId);
    if(ticket !== inflight) return;         // superseded by a newer call
    if(!map){ log('No scoped map → leave controls unchanged'); return; }

    const targets = collectTargets(scope);
    targets.forEach(sel => applyOptions(sel, map));
    log('Scoped ALL section selects', { menuId, targets: targets.length, options: Object.keys(map).length });
  }

  function bindMenuChange(){
    const sel = panelRoot().querySelector('select[data-setting="menus"]');
    if(!sel) return;
    sel.addEventListener('change', ()=>refreshAll(), { passive:true });
  }

  function startObserver(){
    const root = panelRoot();
    const mo   = new MutationObserver(muts=>{
      let needs = false;
      for(const m of muts){
        for(const n of m.addedNodes || []){
          if(!(n instanceof HTMLElement)) continue;
          if(
            n.matches('select[data-setting="sections"], select[data-setting="layout_split_after_section"], select[data-setting="layout_split_after_section2"], select[name$="[section_id]"]')
            ||
            (n.querySelector && (
              n.querySelector('select[data-setting="sections"]') ||
              n.querySelector('select[data-setting="layout_split_after_section"]') ||
              n.querySelector('select[data-setting="layout_split_after_section2"]') ||
              n.querySelector('select[name$="[section_id]"]')
            ))
          ){
            needs = true;
          }
        }
      }
      if(needs) refreshAll(root);
    });
    mo.observe(root, { childList:true, subtree:true });
    return mo;
  }

  function boot(){
    bindMenuChange();
    startObserver();
    refreshAll(); // initial pass
    log('sections-dep (ALL) active');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
