(function ($) {
  'use strict';

  /* -------------------- CONFIG -------------------- */
  // Menu controls (use your two keys)
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
    // Generic: any select whose name/data-setting contains "section"
    'select[name*="section"]',
    'select[data-setting*="section"]',
  ];

  /* -------------------- UTIL -------------------- */
  const LOG = '[JPRM calm]';
  function log(){ /* uncomment to debug: console.log.apply(console,[LOG].concat([].slice.call(arguments))); */ }

  function ajaxUrl() {
    if (window.JPRMSectionsUX && JPRMSectionsUX.ajaxUrl) return JPRMSectionsUX.ajaxUrl;
    if (typeof ajaxurl !== 'undefined') return ajaxurl;
    return '/wp-admin/admin-ajax.php';
  }
  function ajaxNonce(){ return (window.JPRMSectionsUX && JPRMSectionsUX.nonce) ? JPRMSectionsUX.nonce : ''; }

  function getPanelRoot(){
    const $p = $('.elementor-panel');
    return $p.length ? $p : $(document.body);
  }

  function getCurrentMenuId($root) {
    for (const sel of MENU_SELECTORS) {
      const $el = $root.find(sel).first();
      if ($el.length) {
        const v = Array.isArray($el.val()) ? $el.val()[0] : $el.val();
        if (v && /^\d+$/.test(String(v))) return parseInt(v, 10);
      }
    }
    return 0;
  }

  /* -------------------- FETCH + CACHE -------------------- */
  const cache = new Map(); // menuId -> nodes[{id,text,level,parent}]

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

  function toOptions(nodes) {
    const opts = [{ value:'', label:'' }];
    nodes.forEach(n => {
      const lvl = Number(n.level || 0);
      const ind = lvl > 0 ? Array(lvl + 1).join('— ') : '';
      opts.push({ value: String(n.id), label: ind + n.text });
    });
    return opts;
  }

  // Compare current <option> list with new one to avoid unnecessary churn
  function optionsEqual($select, opts) {
    const $opts = $select.find('option');
    if ($opts.length !== opts.length) return false;
    for (let i=0;i<opts.length;i++) {
      const o = opts[i];
      const $o = $opts.eq(i);
      if (String($o.attr('value')||'') !== String(o.value||'')) return false;
      if (String($o.text()||'') !== String(o.label||'')) return false;
    }
    return true;
  }

  // Apply options without triggering preview refreshes:
  // - do NOT touch Elementor model
  // - do NOT trigger 'change' unless previous selection is invalid
  function applyOptionsToSelect($select, opts) {
    const prev = $select.val();

    if (optionsEqual($select, opts)) {
      // No change needed
      return;
    }

    $select.find('option').remove();
    opts.forEach(o => $select.append(new Option(o.label, o.value, false, false)));

    if (prev && (Array.isArray(prev) ? prev.length : prev)) {
      // Keep previous if still valid
      if (opts.some(o => (Array.isArray(prev) ? prev.includes(o.value) : o.value === prev))) {
        $select.val(prev); // do NOT trigger change
      } else {
        $select.val('');   // do NOT trigger change
      }
    } else {
      $select.val('');     // do NOT trigger change
    }
  }

  /* -------------------- CORE UPDATE -------------------- */
  let debounceTimer = null;
  let isUpdating    = false;

  function scheduleUpdate(reason) {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      updateAll(reason);
    }, 120);
  }

  async function updateAll(reason) {
    if (isUpdating) return; // prevent overlap
    isUpdating = true;
    const $panel = getPanelRoot();
    const menuId = getCurrentMenuId($panel);

    log('update', reason, 'menu', menuId);
    if (!menuId) { isUpdating = false; return; }

    const nodes   = await getNodes(menuId);
    const options = toOptions(nodes);

    // Fill every matching select under the panel
    // Each select updates only if options actually differ
    const $targets = $panel.find(TARGET_SELECTORS.join(',')).filter(function(){
      // Skip Select2 hidden duplicates; update only the original select
      return !$(this).hasClass('select2-hidden-accessible');
    });

    $targets.each(function(){
      const $sel = $(this);
      applyOptionsToSelect($sel, options);
    });

    isUpdating = false;
  }

  /* -------------------- BINDINGS -------------------- */
  function bindOnce($panel) {
    if ($panel.data('jprm-bound')) return;
    $panel.data('jprm-bound', 1);

    // Menu changes: clear cache and update
    MENU_SELECTORS.forEach(sel => {
      $panel.on('change', sel, function(){
        cache.clear();
        scheduleUpdate('menu-change');
      });
    });

    // When a target select is inserted into DOM, update once
    const mo = new MutationObserver((muts) => {
      let found = false;
      for (const m of muts) {
        if (m.type !== 'childList' || !m.addedNodes || !m.addedNodes.length) continue;
        $(m.addedNodes).each(function(){
          const $n = $(this);
          if ($n.is('select') && matchesAny($n, TARGET_SELECTORS)) { found = true; return false; }
          if ($n.find && $n.find('select').filter((i, el) => matchesAny($(el), TARGET_SELECTORS)).length) { found = true; return false; }
        });
        if (found) break;
      }
      if (found) scheduleUpdate('targets-added');
    });
    mo.observe($panel.get(0), { childList: true, subtree: true });

    // Also refresh when a target select receives focus (if options were not yet applied)
    $panel.on('focus', TARGET_SELECTORS.join(','), function(){
      scheduleUpdate('focus');
    });

    // Initial fill
    scheduleUpdate('boot');
  }

  function matchesAny($el, selectors) {
    for (const s of selectors) if ($el.is(s)) return true;
    return false;
  }

  function boot() {
    const $panel = getPanelRoot();
    if (!$panel.length) {
      setTimeout(boot, 200);
      return;
    }

    // Elementor event when a widget editor opens
    if (window.elementor && elementor.hooks && elementor.hooks.addAction) {
      elementor.hooks.addAction('panel/open_editor/widget', function(){
        bindOnce(getPanelRoot());
      });
    }

    // If panel already present, bind now
    bindOnce($panel);
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    boot();
  } else {
    $(boot);
  }
})(jQuery);
