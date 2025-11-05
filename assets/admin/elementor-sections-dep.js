(function () {
  'use strict';

  // --- exact keys we handle ---
  const KEY_SECTIONS = 'sections'; // Data Source (Select2)
  const KEY_SPLIT_1  = 'layout_split_after_section';   // native select
  const KEY_SPLIT_2  = 'layout_split_after_section2';  // native select

  // small cache to avoid repeat network hits per menu id
  const CACHE = new Map();

  // helpers
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
    const prev = select.value;
    if (sameOptions(select, opts)) return false;

    // rebuild list
    select.innerHTML = '';
    for (const o of opts) {
      const opt = document.createElement('option');
      opt.value = o.value;
      opt.textContent = o.label;
      select.appendChild(opt);
    }
    // restore previous selection silently (NO events)
    if (prev && opts.some(o=>o.value===prev)) select.value = prev;
    else select.value = '';
    // stamp so we can detect Elementor overwrites
    select.setAttribute('data-jprm-stamp', String(opts.length) + ':' + (opts[1]?.value || ''));
    return true;
  }

  function selectStamp(select){
    return select.getAttribute('data-jprm-stamp') || '';
  }

  // finders
  function findSectionsHidden(root){
    // Select2 source for Data Source → Sections (already working, keep it)
    return root.querySelector('select.select2-hidden-accessible[data-setting="'+KEY_SECTIONS+'"]') || null;
  }
  function findSplitSelects(root){
    const a = root.querySelectorAll('select[data-setting="'+KEY_SPLIT_1+'"]');
    const b = root.querySelectorAll('select[data-setting="'+KEY_SPLIT_2+'"]');
    return Array.from(new Set([].concat(Array.from(a), Array.from(b))));
  }

  // core runner
  let ticking = false;
  function schedule(){ if (!ticking) { ticking = true; requestAnimationFrame(run); } }

  async function run(){
    ticking = false;
    const root = panel();
    const mid  = currentMenuId();
    if (!mid) return;

    const nodes = await fetchSections(mid);
    const opts  = buildOptions(nodes);

    // sections (Select2) — leave as-is if you already see it working; still apply silently
    const hidden = findSectionsHidden(root);
    if (hidden) applyOptions(hidden, opts);

    // split selects (native) — aggressively enforce, including after Elementor overwrites
    const splits = findSplitSelects(root);
    for (const sel of splits) {
      applyOptions(sel, opts);
      // Observe this specific select's childList so if Elementor repaints it, we re-apply immediately
      ensureObserver(sel, opts);
    }
  }

  // Keep one MutationObserver per split select, re-apply if Elementor rewrites options
  const OBS = new WeakMap();
  function ensureObserver(select, opts){
    if (OBS.has(select)) return;
    const mo = new MutationObserver(() => {
      // if stamp changed or options length changed, re-apply
      const need = !sameOptions(select, opts) || selectStamp(select) === '';
      if (need) applyOptions(select, opts);
    });
    mo.observe(select, { childList:true, subtree:false });
    OBS.set(select, mo);
  }

  function bind(){
    const root = panel();

    // menu changes → clear cache & update
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

    // opening/focusing any of the split selects → ensure they’re updated just-in-time
    root.addEventListener('focusin', (e) => {
      const t = e.target;
      if (!(t instanceof HTMLSelectElement)) return;
      const ds = t.getAttribute('data-setting') || '';
      if (ds === KEY_SPLIT_1 || ds === KEY_SPLIT_2 || ds === KEY_SECTIONS) {
        schedule();
      }
    });

    // tab switches (e.g. going to Layout) → rerun a few times to catch late DOM paints
    root.addEventListener('click', (e) => {
      const el = e.target;
      if (!(el instanceof Element)) return;
      if (el.getAttribute('role') === 'tab' || el.closest('[role="tablist"]')) {
        schedule();
        setTimeout(schedule, 80);
        setTimeout(schedule, 200);
      }
    }, true);

    // global panel mutations → schedule once per frame
    const mo = new MutationObserver(() => schedule());
    mo.observe(root, { childList:true, subtree:true });

    // first pass
    schedule();
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') bind();
  else window.addEventListener('DOMContentLoaded', bind);
})();
