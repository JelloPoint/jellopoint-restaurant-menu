(function () {
  'use strict';

  function panel(){ return document.querySelector('.elementor-panel') || document; }
  function ajaxUrl(){ const J=window.JPRMAjax||{}; return J.url || window.ajaxurl || (location.origin + '/wp-admin/admin-ajax.php'); }
  function nonce(){ const J=window.JPRMAjax||{}; return J.nonce || ''; }

  function currentMenuId() {
    const root = panel();
    const sels = root.querySelectorAll('[data-setting="data_menu"],[data-setting="menus"],select[name$="[data_menu]"],select[name$="[menus]"]');
    for (const el of sels) {
      const v = el.value || (el.selectedOptions?.[0]?.value || '');
      if (v && /^\d+$/.test(v)) return parseInt(v, 10);
    }
    return 0;
  }

  async function fetchSections(mid){
    const p = new URLSearchParams({ action:'jprm_sections_for_menu', nonce:nonce(), menu_id:String(mid) });
    const r = await fetch(ajaxUrl() + '?' + p.toString(), { credentials:'include' });
    const j = await r.json().catch(()=>null);
    return (j && j.success && j.data && Array.isArray(j.data.sections)) ? j.data.sections : [];
  }

  function buildOptions(nodes){
    const out = [{ value:'', label:'' }];
    for (const n of nodes) {
      const lvl = Number(n.level || 0);
      const indent = lvl > 0 ? '— '.repeat(lvl) : '';
      out.push({ value:String(n.id), label: indent + n.text });
    }
    return out;
  }

  function sameOptions(select, opts){
    const cur = Array.from(select.options).map(o=>({value:o.value,label:o.text}));
    if (cur.length !== opts.length) return false;
    for (let i=0;i<opts.length;i++){
      if (String(cur[i].value) !== String(opts[i].value)) return false;
      if (String(cur[i].label) !== String(opts[i].label)) return false;
    }
    return true;
  }

  function applyOptions(select, opts){
    const prev = select.multiple
      ? Array.from(select.selectedOptions).map(o=>o.value)
      : select.value;

    if (!sameOptions(select, opts)) {
      select.innerHTML = '';
      for (const o of opts) {
        const opt = document.createElement('option');
        opt.value = o.value;
        opt.textContent = o.label;
        select.appendChild(opt);
      }
      // Restore previous selection silently (NO events)
      if (Array.isArray(prev)) {
        const keep = prev.filter(v => opts.some(o=>o.value===v));
        for (const o of select.options) o.selected = keep.includes(o.value);
      } else if (prev && opts.some(o=>o.value===prev)) {
        select.value = prev;
      } else {
        select.value = '';
      }
    }
  }

  let ticking = false;
  function schedule(){ if (!ticking) { ticking = true; requestAnimationFrame(run); } }
  async function run(){
    ticking = false;
    const root = panel();
    const mid  = currentMenuId();
    if (!mid) return;

    const nodes = await fetchSections(mid);
    const opts  = buildOptions(nodes);

    // authoritative hidden Select2 source for “Sections”
    const hidden = root.querySelector('select.select2-hidden-accessible[data-setting="sections"]');
    if (hidden) applyOptions(hidden, opts);
  }

  function bind(){
    const root = panel();

    // Update when Menu changes
    root.addEventListener('change', (e) => {
      const t = e.target;
      if (!(t instanceof HTMLSelectElement)) return;
      const ds = t.getAttribute('data-setting') || '';
      const nm = t.getAttribute('name') || '';
      if (ds === 'data_menu' || ds === 'menus' || nm.endsWith('[data_menu]') || nm.endsWith('[menus]')) {
        schedule();
      }
    }, true);

    // Update when controls appear
    const mo = new MutationObserver(() => schedule());
    mo.observe(root, { childList: true, subtree: true });

    // First pass
    schedule();
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') bind();
  else window.addEventListener('DOMContentLoaded', bind);
})();
