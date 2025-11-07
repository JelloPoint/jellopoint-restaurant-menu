(function(){
  'use strict';
  function log(){ try{ console.log.apply(console, ['[JPRM]'].concat([].slice.call(arguments))); }catch(e){} }
  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) || ''; }
  function panelRoot(){ return document.querySelector('.elementor-panel'); }
  function menuSelect(root){ return root && root.querySelector('[data-setting="menus"]'); }
  function dsSectionsSelect(root){
    return root && (root.querySelector('.elementor-control-sections [data-setting="sections"]') || root.querySelector('[data-setting="sections"]'));
  }
  function optionsSignature(map){
    if (!map || typeof map !== 'object') return '';
    var keys = Object.keys(map).sort();
    return keys.map(function(k){ return k + ':' + String(map[k]||'').length; }).join('|');
  }
  var state = { inflight:null, lastMenuId:null };

  async function fetchSectionsMap(menuId){
    if (state.inflight) { try{ state.inflight.abort(); }catch(e){} state.inflight=null; }
    var ctl = new AbortController(); state.inflight = ctl;
    var body = new URLSearchParams();
    body.set('action','jprm_sections_by_menu');
    body.set('menu', String(menuId||''));
    var n = (window.JPRMAjax && JPRMAjax.nonce) || ''; if(n) body.set('_ajax_nonce', n);
    try{
      var res = await fetch(ajaxUrl(), {
        method:'POST', credentials:'include',
        headers:{ 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(), signal: ctl.signal
      });
      var text = await res.text();
      var json = null; try{ json = JSON.parse(text); }catch(_){}
      if (!json || !json.success || !json.data) return {};
      return json.data;
    }catch(e){ if(e && e.name==='AbortError') return null; return {}; }
    finally{ if(state.inflight===ctl) state.inflight=null; }
  }

  function getSelectedValues(selectEl){
    var vals = [];
    for (var i=0;i<selectEl.options.length;i++){ if(selectEl.options[i].selected) vals.push(String(selectEl.options[i].value)); }
    return vals;
  }
  function setSelectedValues(selectEl, values){
    var keep = new Set((values||[]).map(String));
    for (var i=0;i<selectEl.options.length;i++){ selectEl.options[i].selected = keep.has(String(selectEl.options[i].value)); }
    if (window.jQuery) jQuery(selectEl).trigger('change',{silent:true});
  }
  function rebuildOptionsIfChanged(selectEl, map){
    if (!selectEl) return false;
    var sig = optionsSignature(map);
    var prev = selectEl.getAttribute('data-jprm-sig') || '';
    if (sig === prev) return false;

    var wasMultiple = !!selectEl.multiple;
    var selected = getSelectedValues(selectEl);
    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);
    if (!wasMultiple) {
      var emptyOpt = document.createElement('option'); emptyOpt.value=''; emptyOpt.textContent=''; selectEl.appendChild(emptyOpt);
    }
    Object.keys(map||{}).forEach(function(id){
      var opt = document.createElement('option'); opt.value=id; opt.textContent=String(map[id]||''); selectEl.appendChild(opt);
    });
    var ids = Object.keys(map||{});
    var kept = selected.filter(function(v){ return ids.includes(String(v)); });
    setSelectedValues(selectEl, kept);
    selectEl.setAttribute('data-jprm-sig', sig);
    if (window.jQuery && jQuery.fn && jQuery.fn.select2 && jQuery(selectEl).hasClass('select2-hidden-accessible')) {
      jQuery(selectEl).trigger('change.select2');
    }
    return true;
  }

  async function refreshDS(root){
    var dsSel = dsSectionsSelect(root);
    if (!dsSel) return;
    var mEl = menuSelect(root);
    var menuId = (mEl && parseInt(mEl.value||0,10))||0;
    if (state.lastMenuId === menuId && dsSel.getAttribute('data-jprm-sig')) return;

    state.lastMenuId = menuId;
    log('DS refresh',{menuId:menuId});
    var map = await fetchSectionsMap(menuId);
    if (map === null) return;
    if (rebuildOptionsIfChanged(dsSel, map||{})) log('DS applied',{total:Object.keys(map||{}).length});
  }

  function bind(root){
    var m = menuSelect(root);
    if (m && !m.__jprmBound){
      m.__jprmBound = true;
      m.addEventListener('change', function(){
        var ds = dsSectionsSelect(root); if (ds) ds.removeAttribute('data-jprm-sig');
        setTimeout(function(){ refreshDS(root); }, 30);
      });
    }
  }

  function boot(){
    var root = panelRoot();
    if (!root){ setTimeout(boot,250); return; }
    log('sections-dep.js active');
    bind(root);
    refreshDS(root);
    // also refresh when panel DOM changes (switch tabs)
    var mo = new MutationObserver(function(){
      bind(root); refreshDS(root);
    });
    mo.observe(root,{childList:true,subtree:true});
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
