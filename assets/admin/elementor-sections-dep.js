/* global elementor, JPRMAjax, jQuery */
(function(){
  'use strict';

  const LOG = '[JPRM]';
  function log(){ try{ console.log.apply(console,[LOG].concat([].slice.call(arguments))); }catch(e){} }

  /* ---------------- AJAX ---------------- */
  async function fetchSectionsMap(menuId){
    if(!menuId) return null;
    const url   = (window.JPRMAjax && JPRMAjax.url)   ? JPRMAjax.url   : '/wp-admin/admin-ajax.php';
    const nonce = (window.JPRMAjax && JPRMAjax.nonce) ? JPRMAjax.nonce : '';
    const body  = new URLSearchParams();
    body.set('action','jprm_sections_for_menu');
    body.set('menu_id', String(menuId));
    if(nonce) body.set('nonce', nonce);

    const res  = await fetch(url,{
      method:'POST', credentials:'include',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
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

  /* ---------------- ELEMENTOR helpers ---------------- */
  function panelView(){
    try{
      if(window.elementor && elementor.getPanelView){
        return elementor.getPanelView().getCurrentPanelView();
      }
    }catch(e){}
    return null;
  }

  function getMenuIdFromModel(pv){
    try{
      const v = pv && pv.model && pv.model.getSetting('menus');
      if(Array.isArray(v)) return v[0] || '';
      return v || '';
    }catch(e){ return ''; }
  }

  // Safely set options for a control by its name via ControlView (no DOM poking)
  function setControlOptions(pv, controlName, idToLabel){
    if(!pv || !controlName || !idToLabel) return;
    const view = pv.getControlView(controlName);
    if(!view || !view.model) return;

    // Keep valid current selections
    const current = pv.model.getSetting(controlName);
    const ids = Object.keys(idToLabel);
    let keep = [];

    if(Array.isArray(current)){
      keep = current.map(String).filter(v => ids.includes(v));
    }else if(typeof current === 'string' || typeof current === 'number'){
      const s = String(current);
      keep = ids.includes(s) ? [s] : [];
    }

    // 1) set new options
    view.model.set('options', idToLabel);

    // 2) restore selection (Elementor models want raw values)
    if(Array.isArray(current)){
      pv.model.setSetting(controlName, keep);
    }else{
      pv.model.setSetting(controlName, keep[0] || '');
    }

    // 3) re-render the control to rebuild its <select>/<select2>
    if(typeof view.render === 'function') view.render();

    // 4) notify the input (covers Select2)
    try{
      const $el = view.$el && view.$el.find ? view.$el.find('[data-setting="'+controlName+'"]') : null;
      if($el && $el.length && window.jQuery && jQuery.fn){
        $el.val(keep).trigger('change');
      }
    }catch(e){}
  }

  // Update all relevant section-dependent controls
  function applyScopedOptions(pv, map){
    setControlOptions(pv, 'sections', map);                      // Data Source → Sections (SELECT2, multiple)
    setControlOptions(pv, 'layout_split_after_section', map);    // Layout → Split after (1)
    setControlOptions(pv, 'layout_split_after_section2', map);   // Layout → Split after (2)
  }

  let inflight = 0;
  async function refreshAllForCurrentPanel(){
    const pv = panelView();
    if(!pv) return;
    const menuId = getMenuIdFromModel(pv);
    if(!menuId) return;

    const ticket = ++inflight;
    const map = await fetchSectionsMap(menuId).catch(()=>null);
    if(ticket !== inflight) return; // superseded
    if(!map) return;

    applyScopedOptions(pv, map);
    log('Updated controls for menu=', menuId, 'options=', Object.keys(map).length);
  }

  // Debounce helper
  function debounce(fn, wait){
    let t = 0;
    return function(){
      clearTimeout(t);
      t = setTimeout(()=>fn.apply(this, arguments), wait);
    };
  }
  const refreshDebounced = debounce(refreshAllForCurrentPanel, 120);

  function bindModelChanges(){
    const pv = panelView();
    if(!pv || !pv.model) return;

    // When Menu setting changes in the current panel → refresh
    pv.model.on('change:menus', refreshDebounced);

    // When Elementor re-renders the panel or switches controls, re-apply
    // (panelView is replaced frequently; we add a light MutationObserver as a safety net)
    const root = pv.$el && pv.$el[0] ? pv.$el[0] : document.querySelector('.elementor-panel');
    if(!root) return;

    const mo = new MutationObserver((muts)=>{
      let touched = false;
      for(const m of muts){
        if(m.type !== 'childList') continue;
        for(const n of m.addedNodes){ if(n.nodeType===1){ touched = true; break; } }
        if(touched) break;
      }
      if(touched) refreshDebounced();
    });
    mo.observe(root, { childList:true, subtree:true });
  }

  function onPanelOpened(){
    // When a widget panel opens, immediately scope its controls
    try{
      refreshAllForCurrentPanel();
      bindModelChanges();
    }catch(e){}
  }

  function boot(){
    // 1) Run now if panel already open
    onPanelOpened();

    // 2) Also react whenever Elementor opens (or re-opens) the panel for our widget
    if(window.elementor && elementor.channels && elementor.channels.panelElements){
      elementor.channels.panelElements.on('before:activated', onPanelOpened);
      elementor.channels.panelElements.on('activated', onPanelOpened);
    }
    if(window.elementor && elementor.channels && elementor.channels.editor){
      elementor.channels.editor.on('panel:opened', onPanelOpened);
    }

    log('sections-dep active');
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
