(function ($) {
  'use strict';

  // Minimal, safe updater: find the control by its visible label "Sections"
  // Supports translations like "Secties", "Sections and Menus" -> contains "Section"
  const LOG = '[JPRM sections-by-title]';
  function log(){ /* console.log.apply(console,[LOG].concat([].slice.call(arguments))); */ }

  // Menu (data source) controls you use
  const MENU_SELECTORS = [
    '[data-setting="data_menu"]',
    '[data-setting="menus"]',
    'select[name$="[data_menu]"]',
    'select[name$="[menus]"]',
  ];

  function ajaxUrl() {
    if (window.JPRMAjax && JPRMAjax.url) return JPRMAjax.url;
    if (typeof ajaxurl !== 'undefined') return ajaxurl;
    return '/wp-admin/admin-ajax.php';
  }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) ? JPRMAjax.nonce : ''; }

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

  async function fetchSectionsTree(menuId){
    const params = new URLSearchParams();
    params.set('action', 'jprm_sections_for_menu');
    params.set('nonce', ajaxNonce());
    params.set('menu_id', String(menuId));

    const res  = await fetch(ajaxUrl() + '?' + params.toString(), { method:'GET', credentials:'include' });
    const json = await res.json().catch(()=>null);
    if (!json || !json.success || !json.data || !Array.isArray(json.data.sections)) return [];
    return json.data.sections;
  }

  function buildOptions(nodes){
    const opts = [{ value:'', label:'' }];
    nodes.forEach(n => {
      const lvl = Number(n.level || 0);
      const indent = lvl > 0 ? Array(lvl + 1).join('— ') : '';
      opts.push({ value:String(n.id), label: indent + n.text });
    });
    return opts;
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

  function applyOptions($select, opts){
    const prev = $select.val();
    if (optionsEqual($select, opts)) return;

    $select.find('option').remove();
    opts.forEach(o => $select.append(new Option(o.label, o.value, false, false)));

    if (prev && (Array.isArray(prev) ? prev.length : prev)) {
      if (Array.isArray(prev)) {
        const keep = prev.filter(v => opts.some(o => o.value === v));
        $select.val(keep);
      } else {
        const stillValid = opts.some(o => o.value === prev);
        $select.val(stillValid ? prev : '');
      }
    } else {
      $select.val('');
    }
    // do NOT trigger change → no preview flicker
  }

  // Find the “Sections” control by its title text (supports translations)
  function findSectionsSelects($root) {
    const matches = [];
    $root.find('.elementor-control').each(function(){
      const $ctrl = $(this);
      const title = ($ctrl.find('.elementor-control-title').first().text() || '').trim().toLowerCase();

      // Loose match: contains "section" to allow "Sections and Menus" / translations with "section"
      if (!title || title.indexOf('section') === -1) return;

      // Only selects within that control (skip select2 clones)
      const $sels = $ctrl.find('select').filter(function(){
        return !$(this).hasClass('select2-hidden-accessible');
      });
      if ($sels.length) {
        $sels.each(function(){ matches.push($(this)); });
      }
    });
    return matches;
  }

  async function updateOnce(reason){
    const $root = panelRoot();
    const mid   = currentMenuId($root);
    if (!mid) { log('no menu selected'); return; }

    const nodes = await fetchSectionsTree(mid);
    const opts  = buildOptions(nodes);

    const selects = findSectionsSelects($root);
    selects.forEach($sel => applyOptions($sel, opts));

    log('updated', reason, 'menu=', mid, 'targets=', selects.length, 'nodes=', nodes.length);
  }

  let raf = null;
  function schedule(reason){
    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => updateOnce(reason));
  }

  function bind(){
    const $root = panelRoot();

    if (window.elementor && elementor.hooks && elementor.hooks.addAction) {
      elementor.hooks.addAction('panel/open_editor/widget', function(){
        schedule('open-editor');
      });
    }

    MENU_SELECTORS.forEach(sel => {
      $root.on('change', sel, function(){ schedule('menu-change'); });
    });

    // When a control opens/focuses, re-apply (handles late injection)
    $root.on('focus select2:opening', 'select', function(){ schedule('select-open'); });

    const mo = new MutationObserver(() => schedule('mutation'));
    const el = $root.get(0);
    if (el) mo.observe(el, { childList:true, subtree:true });

    schedule('boot');
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    bind();
  } else {
    $(bind);
  }
})(jQuery);
