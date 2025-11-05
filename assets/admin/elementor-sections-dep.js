(function () {
  'use strict';

  // === Section-pickers we must refresh ===
  const TARGET_EXACT_KEYS = new Set([
    'sections',                     // Data Source
    'layout_split_after_section',   // Layout → Split after section (1)
    'layout_split_after_section2',  // Layout → Split after section (2)
    'section_id',                   // repeater rows (common key)
  ]);
  const TARGET_SUFFIX = '_section'; // e.g. repeater fields named "...[section]"

  const DEBUG = false;
  const LOG = '[JPRM dep]';
  function dlog(){ if (DEBUG) console.log.apply(console,[LOG].concat([].slice.call(arguments))); }

  function panel(){ return document.querySelector('.elementor-panel') || document; }
  function ajaxUrl(){ const J=window.JPRMAjax||{}; return J.url || window.ajaxurl || (location.origin + '/wp-admin/admin-ajax.php'); }
  function nonce(){ const J=window.JPRMAjax||{}; return J.nonce || ''; }

  function currentMenuId() {
    const root = panel();
    const sels = root.querySelectorAll(
      '[data-setting="data_menu"],[data-setting="menus"],select[name$="[data_menu]"],select[name$="[menus]"]'
    );
    for (const el of sels) {
      const v = el.value || (el.selectedOptions?.[0]?.value || '');
      if (v && /^\d+$/.test(v)) return parseInt(v, 10);
    }
    return 0;
  }

  // Simple in-memory cache per menu id to avoid extra AJAX
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
      return true;
    }
    return false;
  }

  // Is this <select> one of our section pickers?
  function isTargetSelect(select){
    const ds = (select.getAttribute('data-setting') || '').trim();
    const nm = (select.getAttribute('name') || '').trim();

    if (ds && TARGET_EXACT_KEYS.has(ds)) return true;
    if (ds && ds.endsWith(TARGET_SUFFIX)) return true;
    if (/\[section(_id)?\]$/.test(nm)) return true; // repeater names …[section] / …[section_id]
    return false;
  }

  // Collect both native selects and hidden Select2 sources
  function findTargetSelects(root){
    const found = [];
    const all = root.querySelectorAll('select'); // includes hidden Select2 sources
    for (const sel of all) {
      if (isTargetSelect(sel)) found.push(sel);
    }
    return found;
  }

  // Force-refresh only the two Split selects — explicit handles for reliability
  function findSplitSelects(root){
    const one = root.querySelectorAll('select[data-setting="layout_split_after_section"]');
    const two = root.querySelectorAll('select[data-setting="layout_split_after_section2"]');
    return Array.from(new Set([].concat(Array.from(one), Array.from(two))));
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

    // 1) Update EVERYTHING that looks like a section picker
    const targets = findTargetSelects(root);
    let changed = 0;
    for (const sel of targets) {
      if (applyOptions(sel, opts)) changed++;
    }

    // 2) Make absolutely sure the split controls are refreshed (native selects)
    const splits = findSplitSelects(root);
    for (const sel of splits) {
      if (applyOptions(sel, opts)) changed++;
    }

    dlog('updated', 'menu=', mid, 'targets=', targets.length, 'splits=', splits.length, 'changed=', changed, 'nodes=', nodes.length);
  }

  function bind(){
    const root = panel();

    // Update when the Menu changes
    root.addEventListener('change', (e) => {
      const t = e.target;
      if (!(t instanceof HTMLSelectElement)) return;
      const ds = t.getAttribute('data-setting') || '';
      const nm = t.getAttribute('name') || '';
      if (ds === 'data_menu' || ds === 'menus' || nm.endsWith('[data_menu]') || nm.endsWith('[menus]')) {
        CACHE.clear(); // menu changed → clear cache
        schedule();
      }
    }, true);

    // Update when any target select receives focus (tabs/repeaters lazy render)
    root.addEventListener('focusin', (e) => {
      const t = e.target;
      if (t instanceof HTMLSelectElement && isTargetSelect(t)) schedule();
    });

    // Also update when the Layout tab is shown (catch tab switches)
    root.addEventListener('click', (e) => {
      const el = e.target;
      if (!(el instanceof Element)) return;
      // Elementor tab buttons often have role="tab"
      if (el.getAttribute('role') === 'tab' || el.closest('[role="tablist"]')) {
        schedule();
      }
    }, true);

    // React to DOM mutations (panel rebuilds when switching tabs)
    const mo = new MutationObserver(() => schedule());
    if (root) mo.observe(root, { childList:true, subtree:true });

    // First pass
    schedule();
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') bind();
  else window.addEventListener('DOMContentLoaded', bind);
})();
