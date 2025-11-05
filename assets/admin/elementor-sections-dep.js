(function () {
  'use strict';

  // Exact keys we must handle
  const TARGET_EXACT_KEYS = new Set([
    'sections',                     // Data Source (Select2)
    'layout_split_after_section',   // Layout → Split (1) (native)
    'layout_split_after_section2',  // Layout → Split (2) (native)
    'section_id',                   // repeater rows common key
  ]);
  const TARGET_SUFFIX = '_section'; // e.g. repeater …[section]

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

  const CACHE = new Map();
  async function fetchSections(mid){
    if (CACHE.has(mid)) return CACHE.get(mid);
    const p = new URLSearchParams({ action:'jprm_sections_for_menu', nonce:nonce(), menu_id:String(mid) });
    const r = await fetch(ajaxUrl() + '?' + p.toString(), { credentials:'include' });
    const j = await r.json().catch(()=>null);
    const nodes = (j && j.success && j.data && Array.isArray(j.data.sections)) ? j.data.sections : [];
    CACHE.set(mid, nodes);
    return nodes;
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
    const prev = select.multiple ? Array.from(select.selectedOptions).map(o=>o.value) : select.value;

    if (!sameOptions(select, opts)) {
      select.innerHTML = '';
      for (const o of opts) {
        const opt = document.createElement('option');
        opt.value = o.value; opt.textContent = o.label;
        select.appendChild(opt);
      }
      // restore selection silently (NO events)
      if (Array.isArray(prev)) {
        const keep = prev.filter(v => opts.some(o=>o.value===v));
        for (const o of select.options) o.selected = keep.includes(o.value);
      } else if (prev && opts.some(o=>o.value===prev)) {
        select.value = prev;
      } else {
        select.value = '';
      }
      return true;
    }
    return false;
  }

  function isTargetSelect(select){
    const ds = (select.getAttribute('data-setting') || '').trim();
    const nm = (select.getAttribute('name') || '').trim();
    if (ds && TARGET_EXACT_KEYS.has(ds)) return true;
    if (ds && ds.endsWith(TARGET_SUFFIX)) return true;
    if (/\[section(_id)?\]$/.test(nm)) return true;
    return false;
  }

  function findTargets(root){
    return Array.from(root.querySelectorAll('select')).filter(isTargetSelect);
  }

  // Explicit handles for the two Split selects (re-check them after tab switches)
  function findSplitSelects(root){
    const a = root.querySelectorAll('select[data-setting="layout_split_after_section"]');
    const b = root.querySelectorAll('select[data-setting="layout_split_after_section2"]');
    return Array.from(new Set([].concat(Array.from(a), Array.from(b))));
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

    // Update all detected section-pickers
    const targets = findTargets(root);
    let changed = 0;
    for (const sel of targets) if (applyOptions(sel, opts)) changed++;

    // And specifically re-apply to the two split controls (native selects)
    const splits = findSplitSelects(root);
    for (const sel of splits) if (applyOptions(sel, opts)) changed++;
  }

  function bind(){
    const root = panel();

    // Menu changes → refresh (and clear cache)
    root.addEventListener('change', (e) => {
      const t = e.target;
      if (!(t instanceof HTMLSelectElement)) return;
      const ds = t.getAttribute('data-setting') || '';
      const nm = t.getAttribute('name') || '';
      if (ds === 'data_menu' || ds === 'menus' || nm.endsWith('[data_menu]') || nm.endsWith('[menus]')) {
        CACHE.clear();
        schedule();
      }
    }, true);

    // When target selects open/focus (covers lazy renders / repeaters)
    root.addEventListener('focusin', (e) => {
      const t = e.target;
      if (t instanceof HTMLSelectElement && isTargetSelect(t)) schedule();
    });

    // Tab switches (e.g., going to Layout)
    root.addEventListener('click', (e) => {
      const el = e.target;
      if (!(el instanceof Element)) return;
      if (el.getAttribute('role') === 'tab' || el.closest('[role="tablist"]')) {
        // run a few times to catch late DOM
        schedule();
        setTimeout(schedule, 100);
        setTimeout(schedule, 250);
      }
    }, true);

    // DOM mutations from Elementor panel rebuilds
    const mo = new MutationObserver(() => schedule());
    mo.observe(root, { childList:true, subtree:true });

    // First pass
    schedule();
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') bind();
  else window.addEventListener('DOMContentLoaded', bind);
})();
