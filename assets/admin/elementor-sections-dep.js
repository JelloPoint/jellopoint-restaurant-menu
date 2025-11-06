(function(){
  'use strict';

  /* ======= helpers ======= */
  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }
  function panel(){ return document.querySelector('.elementor-panel') || document; }
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

  /* ======= elements we care about ======= */
  function menuSelect(root){ return root.querySelector('[data-setting="menus"]'); }
  function dsSectionsSelect(root){
    return root.querySelector('.elementor-control-sections [data-setting="sections"]')
        || root.querySelector('[data-setting="sections"]');
  }

  // Non-DS targets: explicit control names/classes, no guessing.
  function otherTargets(root){
    const arr = [];
    // Layout → split selects
    const s1 = root.querySelector('[data-setting="layout_split_after_section"]');
    const s2 = root.querySelector('[data-setting="layout_split_after_section2"]');
    if (s1) arr.push(s1);
    if (s2) arr.push(s2);

    // Repeaters → any select named section_id inside our two repeaters
    root.querySelectorAll(
      '.elementor-control-type-repeater[data-id="labels_layout_overrides"] .elementor-repeater-fields select[data-setting="section_id"],' +
      '.elementor-control-type-repeater[data-id="info_blocks"] .elementor-repeater-fields select[data-setting="section_id"]'
    ).forEach(el => arr.push(el));

    // Any control we marked with class jprm-scope-target (backup path)
    root.querySelectorAll('select.jprm-scope-target[data-setting="section_id"]').forEach(el => arr.push(el));

    return arr.filter(visible);
  }

  function readMenuId(root){
    const ms = menuSelect(root); if(!ms) return 0;
    const id = parseInt(ms.value||0,10);
    return Number.isFinite(id) ? id : 0;
  }

  /* ======= AJAX + state ======= */
  const ST = { inflight:null, lastMenuId:null };

  async function fetchSectionsMap(menuId){
    if (ST.inflight){ try{ ST.inflight.abort(); }catch(_e){} ST.inflight=null; }
    const ctl = new AbortController(); ST.inflight = ctl;

    const body = new URLSearchParams();
    body.set('action','jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    const n = ajaxNonce(); if(n) body.set('_ajax_nonce', n);

    try{
      const res = await fetch(ajaxUrl(), {
        method:'POST', credentials:'include',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString(), signal: ctl.signal
      });
      const txt = await res.text();
      let json=null; try{ json = JSON.parse(txt); }catch(_e){}
      if(!json || !json.success || !json.data){ log('AJAX not OK, head:', txt.slice(0,200)); return {}; }
      return json.data; // { id: "— label", ... }
    }catch(e){
      if (e && e.name==='AbortError') return null;
      log('AJAX error', e); return {};
    }finally{
      if (ST.inflight===ctl) ST.inflight=null;
    }
  }

  function rebuildOptions(select, map){
    if(!select || !visible(select)) return false;

    const sig = sigFromMap(map);
    const prev = select.getAttribute('data-jprm-sig') || '';
    if (sig === prev) return false;

    const multiple = !!select.multiple;
    const prevSel = getSelected(select);
    const ids = Object.keys(map||{});

    while(select.firstChild) select.removeChild(select.firstChild);
    if(!multiple){
      const empty = document.createElement('option');
      empty.value=''; empty.textContent=''; select.appendChild(empty);
    }
    ids.forEach(id => {
      const opt = document.createElement('option');
      opt.value = id; opt.textContent = String(map[id]||'');
      select.appendChild(opt);
    });

    setSelected(select, prevSel.filter(v => ids.includes(String(v))));
    select.setAttribute('data-jprm-sig', sig);

    if (typeof jQuery!=='undefined' && jQuery.fn && jQuery.fn.select2 && jQuery(select).hasClass('select2-hidden-accessible')){
      jQuery(select).trigger('change.select2');
    }
    return true;
  }

  async function refreshDS(root){
    const sel = dsSectionsSelect(root);
    if(!sel) return null;

    const menuId = readMenuId(root);
    if (ST.lastMenuId===menuId && sel.getAttribute('data-jprm-sig')) return null;
    ST.lastMenuId = menuId;

    log('DS refresh', {menuId});
    const map = await fetchSectionsMap(menuId);
    if (map===null) return null;

    if (rebuildOptions(sel, map||{})){
      log('DS applied', { total:Object.keys(map||{}).length });
    }
    return map||{};
  }

  function applyOthers(root, map){
    const targets = otherTargets(root);
    log('Others targets', { total: targets.length });
    if (!targets.length) return;

    let applied=0;
    targets.forEach(el => { if (rebuildOptions(el, map)) applied++; });
    if (applied) log('Others applied', { count: applied, totalTargets: targets.length });
  }

  /* ======= deterministic triggers ======= */

  // 1) Menu change → refresh DS first, then Others.
  function bindMenu(root){
    const ms = menuSelect(root);
    if (!ms || ms.__jprm) return;
    ms.__jprm = true;
    ms.addEventListener('change', async function(){
      // clear sigs so we actually rebuild
      const ds = dsSectionsSelect(root);
      if (ds) ds.removeAttribute('data-jprm-sig');
      otherTargets(root).forEach(el => el.removeAttribute('data-jprm-sig'));

      const map = await refreshDS(root) || {};
      applyOthers(root, map);
    });
  }

  // 2) Button we add in the Controls: #jprm-refresh-scoped
  function bindRefreshButton(root){
    if (root.__jprmBtn) return;
    root.__jprmBtn = true;

    root.addEventListener('click', async function(e){
      const t = e.target;
      if (!(t instanceof HTMLElement)) return;
      if (!t.id || t.id !== 'jprm-refresh-scoped') return;

      // Always re-fetch and apply deterministically
      otherTargets(root).forEach(el => el.removeAttribute('data-jprm-sig'));
      const map = await refreshDS(root) || {};
      applyOthers(root, map);
    }, {passive:true});
  }

  function boot(){
    const root = panel();
    if (!root){ setTimeout(boot, 300); return; }
    log('sections-dep.js active');

    bindMenu(root);
    bindRefreshButton(root);

    // First load: make DS right; user can click Refresh to fill Others at any time
    refreshDS(root);
  }

  if (document.readyState==='complete' || document.readyState==='interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
