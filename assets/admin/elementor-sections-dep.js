(function(){
  'use strict';

  /* ========= small utils ========= */
  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }
  function visible(el){ if(!el) return false; const s=getComputedStyle(el); return s.display!=='none' && s.visibility!=='hidden'; }
  function sigFromMap(map){ if(!map||typeof map!=='object') return ''; const k=Object.keys(map).sort(); return k.map(x=>x+':'+String(map[x]||'').length).join('|'); }
  function getSelected(select){
    const out=[]; if(!select) return out;
    for(const o of select.options){ if(o.selected) out.push(String(o.value)); }
    return out;
  }
  function setSelected(select, vals){
    const keep=new Set((vals||[]).map(String));
    for(const o of select.options){ o.selected = keep.has(String(o.value)); }
    if (typeof jQuery!=='undefined') jQuery(select).trigger('change', {silent:true});
  }

  /* ========= AJAX map (same endpoint DS uses) ========= */
  async function fetchSectionsMap(menuId){
    const body = new URLSearchParams();
    body.set('action','jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    const n = ajaxNonce(); if(n) body.set('_ajax_nonce', n);

    const res = await fetch(ajaxUrl(), {
      method:'POST', credentials:'include',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    });

    const txt = await res.text();
    let json=null; try{ json = JSON.parse(txt); }catch(_e){}
    if(!json || !json.success || !json.data){
      log('AJAX not OK, head:', txt.slice(0,200));
      return {};
    }
    return json.data; // { id: "— label", ... }
  }

  function rebuildOptions(select, map){
    if(!select || !visible(select)) return false;

    const sig = sigFromMap(map);
    const prev = select.getAttribute('data-jprm-sig') || '';
    if (sig === prev) return false;

    const wasMultiple = !!select.multiple;
    const prevSel = getSelected(select);
    const ids = Object.keys(map||{});

    while(select.firstChild) select.removeChild(select.firstChild);
    if(!wasMultiple){
      const empty = document.createElement('option');
      empty.value=''; empty.textContent='';
      select.appendChild(empty);
    }
    ids.forEach(id=>{
      const opt=document.createElement('option');
      opt.value=id; opt.textContent=String(map[id]||'');
      select.appendChild(opt);
    });

    setSelected(select, prevSel.filter(v=>ids.includes(String(v))));
    select.setAttribute('data-jprm-sig', sig);

    // If DS is Select2, refresh its UI
    if (typeof jQuery!=='undefined' && jQuery.fn && jQuery.fn.select2 && jQuery(select).hasClass('select2-hidden-accessible')){
      jQuery(select).trigger('change.select2');
    }
    return true;
  }

  /* ========= locate controls inside the widget panel view ========= */
  function findControls(rootEl){
    // DS controls
    const menuSel = rootEl.querySelector('[data-setting="menus"]') || null;
    const dsSel =
      rootEl.querySelector('.elementor-control-sections [data-setting="sections"]') ||
      rootEl.querySelector('[data-setting="sections"]') || null;

    // Other section-scoped selects:
    const others = [];

    // Layout split (1)/(2)
    const s1 = rootEl.querySelector('[data-setting="layout_split_after_section"]');
    const s2 = rootEl.querySelector('[data-setting="layout_split_after_section2"]');
    if (s1) others.push(s1);
    if (s2) others.push(s2);

    // Repeaters section_id for both repeaters (labels_layout_overrides, info_blocks)
    rootEl.querySelectorAll(
      '.elementor-control-type-repeater[data-id="labels_layout_overrides"] .elementor-repeater-fields select[data-setting="section_id"],' +
      '.elementor-control-type-repeater[data-id="info_blocks"] .elementor-repeater-fields select[data-setting="section_id"]'
    ).forEach(el=>others.push(el));

    // Any fallback marked explicitly
    rootEl.querySelectorAll('select.jprm-scope-target[data-setting="section_id"]').forEach(el=>others.push(el));

    return { menuSel, dsSel, others: others.filter(visible) };
  }

  function readMenuId(menuSel){
    const id = parseInt(menuSel && menuSel.value || 0, 10);
    return Number.isFinite(id) ? id : 0;
  }

  async function refreshAllInView(rootEl){
    const { menuSel, dsSel, others } = findControls(rootEl);
    if (!menuSel) return;

    const menuId = readMenuId(menuSel);
    // Always refetch fresh map (fast, tiny payload)
    const map = await fetchSectionsMap(menuId);
    if (!map) return;

    // DS first
    if (dsSel){
      // clear sig so it updates
      dsSel.removeAttribute('data-jprm-sig');
      if (rebuildOptions(dsSel, map)) log('DS applied', { total:Object.keys(map||{}).length });
    }

    // Then others
    let applied=0;
    others.forEach(el=>{ el.removeAttribute('data-jprm-sig'); if (rebuildOptions(el, map)) applied++; });
    if (others.length) log('Others applied', { count: applied, totalTargets: others.length });
  }

  /* ========= bind to Elementor editor events ========= */
  function bindWidgetPanel(panelView){
    // fires every time a widget panel opens
    elementor.hooks.addAction('panel/open_editor/widget', function(panel, model, view){
      try{
        const widgetType = model && model.get && model.get('widgetType');
        if (widgetType !== 'jprm_restaurant_menu') return; // only our widget

        const rootEl = view && view.$el && view.$el[0];
        if (!rootEl) return;

        log('panel/open_editor/widget (JPRM)');

        // initial fill when panel opens
        refreshAllInView(rootEl);

        // when Menu changes inside this widget, refresh everything
        model.on('change:menus', function(){
          log('menus changed');
          refreshAllInView(rootEl);
        });

        // when a repeater row is added, Elementor inserts DOM after click
        // delegate to view container
        view.$el.on('click', '.elementor-repeater-add', function(){
          setTimeout(function(){ refreshAllInView(rootEl); }, 120);
        });

        // also when switching sections/tabs inside the widget, the DOM re-renders;
        // hook into panel section activated to re-apply map to newly inserted selects
        elementor.channels.panel.on('section:activated', function(){
          // throttle a little to let DOM settle
          setTimeout(function(){ refreshAllInView(rootEl); }, 80);
        });

      }catch(e){ log('bind error', e); }
    });
  }

  /* ========= boot once Elementor editor is ready ========= */
  function bootWhenReady(){
    if (typeof elementor === 'undefined' || !elementor || !elementor.hooks || !elementor.channels || !elementor.getPanelView){
      setTimeout(bootWhenReady, 150);
      return;
    }
    try{
      const pv = elementor.getPanelView && elementor.getPanelView();
      if (!pv) { setTimeout(bootWhenReady, 150); return; }
      log('sections-dep.js active (events)');
      bindWidgetPanel(pv);
    }catch(e){
      setTimeout(bootWhenReady, 150);
    }
  }

  if (document.readyState==='complete' || document.readyState==='interactive') bootWhenReady();
  else document.addEventListener('DOMContentLoaded', bootWhenReady);
})();
