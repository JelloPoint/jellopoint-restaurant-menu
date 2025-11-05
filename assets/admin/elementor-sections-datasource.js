/* global elementor, JPRMAjax, jQuery */
(function(){
  'use strict';

  const LOG='[JPRM-DS]';
  const root = () => document.querySelector('.elementor-panel') || document.body;
  const qs   = (sel,scope)=> (scope||root()).querySelector(sel);

  function log(){ try{ console.log.apply(console,[LOG].concat([].slice.call(arguments))); }catch(e){} }

  function getMenuId(){
    try{
      if(window.elementor && elementor.getPanelView){
        const pv = elementor.getPanelView().getCurrentPanelView();
        const v  = pv && pv.model && pv.model.getSetting('menus');
        if(Array.isArray(v)) return v[0]||'';
        return v||'';
      }
    }catch(e){}
    const sel = qs('select[data-setting="menus"]');
    if(!sel) return '';
    return sel.multiple ? (sel.value && sel.value[0]) : sel.value;
  }

  async function fetchSectionsMap(menuId){
    if(!menuId) return null;
    const url   = (window.JPRMAjax && JPRMAjax.url)   ? JPRMAjax.url   : '/wp-admin/admin-ajax.php';
    const nonce = (window.JPRMAjax && JPRMAjax.nonce) ? JPRMAjax.nonce : '';
    const body  = new URLSearchParams();
    body.set('action','jprm_sections_for_menu');
    body.set('menu_id', String(menuId));
    if(nonce) body.set('nonce', nonce);

    try{
      const res  = await fetch(url,{method:'POST',credentials:'include',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString()
      });
      const json = await res.json();
      if(!json || !json.success || !json.data || !Array.isArray(json.data.sections)) return null;

      const map = {};
      json.data.sections.forEach(s=>{
        const lvl = Math.max(0, parseInt(s.level||0,10));
        const indent = lvl ? '\u00A0\u00A0'.repeat(lvl) : '';
        map[String(s.id)] = indent + (s.text || '');
      });
      return Object.keys(map).length ? map : null;
    }catch(e){
      log('AJAX error',e);
      return null;
    }
  }

  function selectIsDataSourceSections(el){
    return el && el.tagName==='SELECT' && el.getAttribute('data-setting')==='sections';
  }

  function applyOptionsToSelect2(selectEl, idToLabel){
    if(!selectIsDataSourceSections(selectEl) || !idToLabel) return;

    const multiple = !!selectEl.multiple;

    // keep only valid current selection(s)
    const keep = (function(){
      if(multiple){
        const vals = Array.from(selectEl.selectedOptions||[]).map(o=>o.value);
        return vals.filter(v => Object.prototype.hasOwnProperty.call(idToLabel, String(v)));
      }
      const v = selectEl.value;
      return Object.prototype.hasOwnProperty.call(idToLabel, String(v)) ? [String(v)] : [];
    })();

    // If Select2, destroy before rebuilding to avoid flicker
    let wasSelect2=false;
    try{
      if(window.jQuery && jQuery.fn && jQuery(selectEl).data('select2')){
        wasSelect2=true; jQuery(selectEl).select2('destroy');
      }
    }catch(e){}

    while(selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

    // DS → Sections is multiple: no blank option
    Object.keys(idToLabel).forEach(id=>{
      const opt = new Option(idToLabel[id], id, false, keep.includes(id));
      selectEl.appendChild(opt);
    });

    if(multiple){
      Array.from(selectEl.options).forEach(o => { o.selected = keep.includes(o.value); });
    }else{
      selectEl.value = keep[0] || '';
    }

    // Re-init Select2 if needed
    try{
      if(wasSelect2 && window.jQuery && jQuery.fn){
        jQuery(selectEl).select2({ width:'100%' });
      }
    }catch(e){}

    // Notify Elementor/Select2
    try{
      if(window.jQuery && jQuery.fn){
        jQuery(selectEl).trigger('change');
      }else{
        selectEl.dispatchEvent(new Event('change', {bubbles:true}));
      }
    }catch(e){}
  }

  let inflight = 0;
  async function refreshDataSourceSections(scope){
    const select = (scope||root()).querySelector('select[data-setting="sections"]');
    if(!select) return;
    const menuId = getMenuId();
    if(!menuId) return;

    const ticket = ++inflight;
    const map = await fetchSectionsMap(menuId);
    if(ticket !== inflight) return;     // superseded
    if(!map) return;                    // don’t clobber

    applyOptionsToSelect2(select, map);
    log('DS Sections patched', {menuId, options:Object.keys(map).length});
  }

  function bindMenuChange(){
    const menuSelect = qs('select[data-setting="menus"]');
    if(!menuSelect) return;
    menuSelect.addEventListener('change', ()=>refreshDataSourceSections(), {passive:true});
  }

  function observePanel(){
    const mo = new MutationObserver(muts=>{
      for(const m of muts){
        for(const n of m.addedNodes||[]){
          if(!(n instanceof HTMLElement)) continue;
          if( selectIsDataSourceSections(n) || (n.querySelector && n.querySelector('select[data-setting="sections"]')) ){
            // DS Sections appeared or re-rendered → patch it
            refreshDataSourceSections(n);
          }
        }
      }
    });
    mo.observe(root(), {childList:true, subtree:true});
  }

  function boot(){
    bindMenuChange();
    observePanel();
    // initial patch (when panel already open)
    refreshDataSourceSections();
    log('DS hook active');
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', boot);
  }else{
    boot();
  }
})();
