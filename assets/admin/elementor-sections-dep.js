(function ($) {
  'use strict';

  // ===== DEBUG BANNER =====
  console.log('%c[JPRM] sections-dep.js active', 'background:#222;color:#0f0;padding:2px 6px;border-radius:3px');

  const MENU_SELECTORS = [
    '[data-setting="data_menu"]',
    '[data-setting="menus"]',
    'select[name$="[data_menu]"]',
    'select[name$="[menus]"]',
  ];

  // Any select that looks like a section picker
  const TARGET_SELECTORS = [
    '[data-setting="sections"]',
    '[data-setting="layout_split_after_section"]',
    '[data-setting="layout_split_after_section2"]',
    'select[name*="section_layouts"][name$="[section_id]"]',
    'select[name*="section"]',
    'select[data-setting*="section"]',
  ];

  function ajaxUrl() {
    if (window.JPRMSectionsUX && JPRMSectionsUX.ajaxUrl) return JPRMSectionsUX.ajaxUrl;
    if (typeof ajaxurl !== 'undefined') return ajaxurl;
    return '/wp-admin/admin-ajax.php';
  }
  function ajaxNonce(){ return (window.JPRMSectionsUX && JPRMSectionsUX.nonce) ? JPRMSectionsUX.nonce : ''; }

  function panelRoot(){
    const $p = $('.elementor-panel');
    return $p.length ? $p : $(document.body);
  }

  function currentMenuId($root) {
    for (const sel of MENU_SELECTORS) {
      const $el = $root.find(sel).first();
      if (!$el.length) continue;
      const v = Array.isArray($el.val()) ? $el.val()[0] : $el.val();
      if (v && /^\d+$/.test(String(v))) return parseInt(v, 10);
    }
    return 0;
  }

  const cache = new Map(); // menuId -> nodes[]

  async function fetchNodes(menuId) {
    const params = new URLSearchParams();
    params.set('action', 'jprm_sections_for_menu');
    params.set('nonce', ajaxNonce());
    params.set('menu_id', String(menuId));

    const res  = await fetch(ajaxUrl() + '?' + params.toString(), { method: 'GET', credentials: 'include' });
    const json = await res.json().catch(() => null);
    if (!json || !json.success || !json.data || !Array.isArray(json.data.sections)) return [];
    return json.data.sections;
  }

  async function getNodes(menuId) {
    if (!menuId) return [];
    if (cache.has(menuId)) return cache.get(menuId);
    const nodes = await fetchNodes(menuId);
    cache.set(menuId, nodes);
    return nodes;
  }

  function makeOptions(nodes) {
    const out = [{ value:'', label:'' }];
    nodes.forEach(n => {
      const lvl = Number(n.level || 0);
      const ind = lvl > 0 ? Array(lvl + 1).join('— ') : '';
      out.push({ value:String(n.id), label: ind + n.text });
    });
    return out;
  }

  function optionsEqual($select, opts) {
    const $o = $select.find('option');
    if ($o.length !== opts.length) return false;
    for (let i=0;i<opts.length;i++) {
      if (String($o.eq(i).attr('value')||'') !== String(opts[i].value||'')) return false;
      if (String($o.eq(i).text()||'')       !== String(opts[i].label||'')) return false;
    }
    return true;
  }

  function applyOptions($select, opts) {
    if (optionsEqual($select, opts)) return;
    const prev = $select.val();
    $select.find('option').remove();
    opts.forEach(o => $select.append(new Option(o.label, o.value, false, false)));
    // keep previous silently if still valid
    if (prev && (Array.isArray(prev) ? prev.length : prev)) {
      if (opts.some(o => (Array.isArray(prev) ? prev.includes(o.value) : o.value === prev))) {
        $select.val(prev);
      } else {
        $select.val('');
      }
    } else {
      $select.val('');
    }
  }

  let queued = null;
  function schedule(reason){
    if (queued) cancelAnimationFrame(queued);
    queued = requestAnimationFrame(() => updateAll(reason));
  }

  async function updateAll(reason){
    const $root = panelRoot();
    const mid   = currentMenuId($root);
    if (!mid) return;

    const nodes   = await getNodes(mid);
    const options = makeOptions(nodes);

    const $targets = $root.find(TARGET_SELECTORS.join(',')).filter(function(){
      return !$(this).hasClass('select2-hidden-accessible'); // skip select2 clones
    });

    $targets.each(function(){
      applyOptions($(this), options);
    });

    console.log('[JPRM] updated', reason, 'menu=', mid, 'targets=', $targets.length, 'nodes=', nodes.length);
  }

  function boot(){
    const $root = panelRoot();

    // Initial fill
    schedule('boot');

    // Menu change
    MENU_SELECTORS.forEach(sel => {
      $root.on('change', sel, function(){ cache.clear(); schedule('menu-change'); });
    });

    // New controls injected
    const mo = new MutationObserver(() => schedule('mutation'));
    const el = $root.get(0);
    if (el) mo.observe(el, { childList: true, subtree: true });

    // Focus/open of any target select
    $root.on('focus select2:opening', TARGET_SELECTORS.join(','), function(){ schedule('open/focus'); });

    // Repeater add/remove
    $root.on('click', '.elementor-repeater-add, .elementor-repeater-remove', function(){
      setTimeout(() => schedule('repeater'), 50);
    });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    boot();
  } else {
    $(boot);
  }
})(jQuery);
