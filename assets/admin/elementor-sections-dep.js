(function () {
  'use strict';

  // =========================
  // Config: which controls?
  // =========================
  // We always include:
  //  - Data Source → Sections:            data-setting="sections"
  //  - Layout → Split after section:      data-setting="layout_split_after_section"
  //  - Layout → Split after section 2:    data-setting="layout_split_after_section2"
  //
  // For repeater rows (e.g., Labels Layout Overrides):
  //  - any select whose data-setting ends with "_section" or equals "section_id"
  //    (and lives inside a repeater item)
  //
  // If your keys differ, add them into TARGET_EXACT or tweak TARGET_SUFFIX.

  const TARGET_EXACT_KEYS = new Set([
    'sections',
    'layout_split_after_section',
    'layout_split_after_section2',
    'section_id', // common in repeater rows
  ]);

  const TARGET_SUFFIX = '_section'; // matches repeater fields like "...[section_layouts][0][section]"

  // When true, logs minimal updates to the console
  const DEBUG = false;
  const LOG = '[JPRM dep]';
  function log(){ if (DEBUG) console.log.apply(console,[LOG].concat([].slice.call(arguments))); }

  // =========================
  // Helpers
  // =========================
  function panel(){ return document.querySelector('.elementor-panel') || document; }
  function ajaxUrl(){ const J=window.JPRMAjax||{}; return J.url || window.ajaxurl || (location.origin + '/wp-admin/admin-ajax.php'); }
  function nonce(){ const J=window.JPRMAjax||{}; return J.nonce || ''; }

  function currentMenuId() {
    const root = panel();
    const sels = root.querySelectorAll(
      '[data-setting="data_menu"],' +
      '[data-setting="menus"],' +
      'select[name$="[data_menu]"],' +
      'select[name$="[menus]"]'
    );
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
      return true;
    }
    return false;
  }

  // Decide if a <select> is one of our section-pickers
  function isTargetSelect(select){
    const ds = (select.getAttribute('data-setting') || '').trim();
    const nm = (select.getAttribute('name') || '').trim();

    // Only consider the *hidden Select2 source* or a native select that isn't a Select2 clone
    const isHiddenSrc = select.classList.contains('select2-hidden-accessible');

    // 1) direct data-setting matches (exact)
    if (ds && TARGET_EXACT_KEYS.has(ds)) return true;

    // 2) suffix match (for fields named like "..._section")
    if (ds && ds.endsWith(TARGET_SUFFIX)) return true;

    // 3) name-based match (covers repeaters: ...[section_layouts][i][section_id] or [section])
    if (/\[section(_id)?\]$/.test(nm)) return true;

    // Prefer operating on hidden Select2 "source" for stability
    if (!isHiddenSrc) return false;

    return false;
  }

  // Collect all relevant selects (hidden Select2 sources and any raw fallback)
  function findTargetSelects(root){
    const found = [];
    const all = root.querySelectorAll('select'); // include hidden select2 sources
    for (const sel of all) {
      if (isTargetSelect(sel)) found.push(sel);
    }
    return found;
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

    const targets = findTargetSelects(root);
    let changed = 0;
    for (const sel of targets) {
      if (applyOptions(sel, opts)) changed++;
    }

    log('updated', 'menu=', mid, 'targets=', targets.length, 'changed=', changed, 'nodes=', nodes.length);
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

    // Update when a select opens/focuses (covers late-created controls & repeaters)
    root.addEventListener('focusin', (e) => {
      const t = e.target;
      if (t instanceof HTMLSelectElement && isTargetSelect(t)) schedule();
    });

    // React to panel DOM mutations (tabs/repeaters open/close)
    const mo = new MutationObserver(() => schedule());
    if (root) mo.observe(root, { childList:true, subtree:true });

    // First pass
    schedule();
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') bind();
  else window.addEventListener('DOMContentLoaded', bind);
})();
