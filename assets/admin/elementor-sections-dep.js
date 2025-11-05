/* global elementor, JPRMAjax, jQuery */
(function(){
  'use strict';

  const LOG = '[JPRM:DS]';
  function log(){ try{ console.log.apply(console,[LOG].concat([].slice.call(arguments))); }catch(e){} }

  /* ------------ AJAX -------------- */
  async function fetchSectionsMap(menuId){
    if(!menuId) return null;
    const url   = (window.JPRMAjax && JPRMAjax.url)   ? JPRMAjax.url   : '/wp-admin/admin-ajax.php';
    const nonce = (window.JPRMAjax && JPRMAjax.nonce) ? JPRMAjax.nonce : '';
    const body  = new URLSearchParams();
    body.set('action','jprm_sections_for_menu');
    body.set('menu_id', String(menuId));
    if(nonce) body.set('nonce', nonce);

    const res  = await fetch(url, {
      method:'POST', credentials:'include',
      headers:{ 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    });
    const json = await res.json().catch(()=>null);
    if(!json || !json.success || !json.data || !Array.isArray(json.data.sections)) return null;

    const map = {};
    json.data.sections.forEach(s=>{
      const lvl    = Math.max(0, parseInt(s.level||0,10));
      const indent = lvl ? '\u00A0\u00A0'.repeat(lvl) : '';
      map[String(s.id)] = indent + (s.text || '');
    });
    return Object.keys(map).length ? map : null;
  }

  /* --------- Elementor helpers --------- */
  function panelView(){
    try{
      if(window.elementor && elementor.getPanelView){
        return elementor.getPanelView().getCurrentPanelView();
      }
    }catch(e){}
    return null;
  }

  function menuValue(pv){
    try{
      const v = pv && pv.model && pv.model.getSetting('menus');
      if(Array.isArray(v)) return v[0] || '';
      return v || '';
    }catch(e){ return ''; }
  }

  function getControlView(pv, name){
    try{ return pv && pv.getControlView ? pv.getControlView(name) : null; }catch(e){ return null; }
  }

  // Rebuild the SELECT2 after options change (Elementor sometimes doesn’t)
  function refreshSelect2(view){
    if(!view || !view.$el || !window.jQuery) return;
    const $ = window.jQuery;
    const $sel = view.$el.find('[data-setting="sections"]');
    if(!$sel.length) return;

    // If Select2 is active → destroy and re-init to pick up new <option>s
    if($sel.data('select2')) {
      $sel.select2('destroy');
    }
    // Elementor reinitializes select2 after render; but ensure now:
    $sel.select2({ width: '100%' }).trigger('change');
  }

  function setOptionsForSections(pv, idToLabel){
    if(!pv || !idToLabel) return;
    const view = getControlView(pv, 'sections'); // Data Source → Sections
    if(!view || !view.model) return;

    // Intersect current selection with new options
    const ids = Object.keys(idToLabel);
    let keep = [];
    try{
      const cur = pv.model.getSetting('sections');
      if(Array.isArray(cur)) keep = cur.map(String).filter(v => ids.includes(v));
    }catch(e){}

    // 1) set options
    view.model.set('options', idToLabel);

    // 2) restore selection
    pv.model.setSetting('sections', keep);

    // 3) re-render control
    if(typeof view.render === 'function') view.render();

    // 4) force Select2 to rebuild
    refreshSelect2(view);

    log('DataSource Sections updated: opts=', ids.length, 'keep=', keep.length);
  }

  let inflight = 0;
  async function refreshDataSourceSections(){
    const pv = panelView();
    if(!pv) return;
    const mid = menuValue(pv);
    if(!mid) return;

    const ticket = ++inflight;
    const map = await fetchSectionsMap(mid).catch(()=>null);
    if(ticket !== inflight) return;
    if(!map) return;

    setOptionsForSections(pv, map);
  }

  // Debounce to avoid storms
  function debounce(fn, wait){
    let t=0; return function(){ clearTimeout(t); t=setTimeout(()=>fn.apply(this,arguments), wait); };
  }
  const refreshDebounced = debounce(refreshDataSourceSections, 120);

  function bind(){
    const pv = panelView();
    if(!pv || !pv.model) return;

    // On Menu change → refresh Data Source → Sections
    pv.model.off('change:menus'); // prevent stacking if bound multiple times
    pv.model.on('change:menus', refreshDebounced);

    // When the panel opens or re-renders, refresh once
    refreshDebounced();
  }

  function boot(){
    // initial bind
    bind();

    // re-bind whenever Elementor opens the panel
    if(window.elementor && elementor.channels && elementor.channels.panelElements){
      elementor.channels.panelElements.on('before:activated', bind);
      elementor.channels.panelElements.on('activated', bind);
    }
    if(window.elementor && elementor.channels && elementor.channels.editor){
      elementor.channels.editor.on('panel:opened', bind);
    }

    log('DataSource sections hook active');
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', boot);
  }else{
    boot();
  }
})();
