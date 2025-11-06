(function(){
  'use strict';

  /* --------- tiny helpers --------- */
  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }
  function panel(){ return document.querySelector('.elementor-panel') || document; }
  function vis(el){ if(!el) return false; const s=getComputedStyle(el); return s.display!=='none' && s.visibility!=='hidden'; }
  function sigFromMap(map){ if(!map||typeof map!=='object') return ''; const k=Object.keys(map).sort(); return k.map(x=>x+':'+String(map[x]||'').length).join('|'); }
  function getSelected(select){
    const out=[]; if(!select) return out;
    for(const o of select.options){ if(o.selected) out.push(String(o.value)); }
    return out;
  }
  function setSelected(select, values){
    const keep = new Set((values||[]).map(String));
    for(const o of select.options){ o.selected = keep.has(String(o.value)); }
    if (typeof jQuery!=='undefined') jQuery(select).trigger('change', {silent:true});
  }

  /* --------- elements we care about --------- */
  function menuSelect(root){ return root.querySelector('[data-setting="menus"]'); }
  function dsSectionsSelect(root){
    // DS control (SELECT2 underneath)
    return root.querySelector('.elementor-control-sections [data-setting="sections"]')
        || root.querySelector('[data-setting="sections"]');
  }

  // EXPLICIT, no guessing:
  function splitSelects(root){
    const arr = [];
    const s1 = root.querySelector('[data-setting="layout_split_after_section"]');
    const s2 = root.querySelector('[data-setting="layout_split_after_section2"]');
    if (s1) arr.push(s1);
    if (s2) arr.push(s2);
    return arr.filter(vis);
  }

  function repeaterSectionSelects(root){
    // any select named section_id inside the two repeaters
    const out = [];
    root.querySelectorAll(
      '.elementor-control-type-repeater[data-id="labels_layout_overrides"] .elementor-repeater-fields select[data-setting="section_id"],' +
      '.elementor-control-type-repeater[data-id="info_blocks"] .elementor-repeater-fields select[data-setting="section_id"]'
    ).forEach(el => { if (vis(el)) out.push(el); });
    return out;
  }

  function readMenuId(root){
    const ms = menuSelect(root); if(!ms) return 0;
    const id = parseInt(ms.value||0,10);
    return Number.isFinite(id) ? id : 0;
  }

  /* --------- AJAX + state --------- */
  const ST = { inflight:null, lastMenuId:null, debounce:null, poll:null };

  async function fetchSectionsMap(menuId){
    if (ST.inflight) { try{ ST.inflight.abort(); }catch(_e){} ST.inflight=null; }
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
    if(!select || !vis(select)) return false;

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

    // refresh Select2 if it’s applied
    if (typeof jQuery!=='undefined' && jQuery.fn && jQuery.fn.select2 && jQuery(select).hasClass('select2-hidden-accessible')){
      jQuery(select).trigger('change.select2');
    }
    return true;
  }

  async function applyDS(root){
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
    // explicit targets only:
    const targets = [].concat( splitSelects(root), repeaterSectionSelects(root) );
    log('Others targets', { total: targets.length });
    if (!targets.length) return;

    let applied=0;
    targets.forEach(el => { if (rebuildOptions(el, map)) applied++; });
    if (applied) log('Others applied', { count: applied, totalTargets: targets.length });
  }

  function schedule(root){
    if (ST.debounce) clearTimeout(ST.debounce);
    ST.debounce = setTimeout(async function(){
      ST.debounce = null;
      const map = await applyDS(root);
      if (map && typeof map==='object') applyOthers(root, map);
    }, 140);
  }

  /* --------- bindings --------- */
  function bindMenu(root){
    const ms = menuSelect(root);
    if (!ms || ms.__jprm) return;
    ms.__jprm = true;
    ms.addEventListener('change', function(){
      const ds = dsSectionsSelect(root);
      if (ds) ds.removeAttribute('data-jprm-sig');
      splitSelects(root).forEach(el => el.removeAttribute('data-jprm-sig'));
      repeaterSectionSelects(root).forEach(el => el.removeAttribute('data-jprm-sig'));
      schedule(root);
    });
  }

  // click-anywhere-in-panel → schedule (tab switches, accordion opens, repeater toggles)
  function bindClicks(root){
    if (root.__jprmClicks) return;
    root.__jprmClicks = true;
    root.addEventListener('click', function(e){
      // only clicks inside the left editor panel, not the preview iframe
      schedule(root);
    }, {passive:true});
  }

  // small heartbeat so opening a repeater row late still gets filled
  function startPoll(root){
    if (ST.poll) clearInterval(ST.poll);
    ST.poll = setInterval(()=> schedule(root), 900);
  }

  function boot(){
    const root = panel();
    if (!root){ setTimeout(boot, 300); return; }

    log('sections-dep.js active');

    bindMenu(root);
    bindClicks(root);
    startPoll(root);

    // initial fill
    schedule(root);
  }

  if (document.readyState==='complete' || document.readyState==='interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
