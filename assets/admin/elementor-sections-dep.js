(function ($) {
  'use strict';

  // === Minimal, safe, single-control updater =================================
  // Targets ONLY the "Sections" picker in Content → Data Source
  // Does not touch Elementor model; updates DOM <select> in place.

  const LOG = '[JPRM sections]';
  function log(){ /* console.log.apply(console,[LOG].concat([].slice.call(arguments))); */ }

  // Menu (data source) controls you use
  const MENU_SELECTORS = [
    '[data-setting="data_menu"]',
    '[data-setting="menus"]',
    'select[name$="[data_menu]"]',
    'select[name$="[menus]"]',
  ];

  // The ONE control we update in this phase
  const SECTIONS_SELECTOR = '[data-setting="sections"], select[name$="[sections]"]';

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
    // Keep current selection if still valid; do NOT trigger change (prevents preview refresh)
    const prev = $select.val();
    if (optionsEqual($select, opts)) return;

    $select.find('option').remove();
    opts.forEach(o => $select.append(new Option(o.label, o.value, false, false)));

    if (prev && (Array.isArray(prev) ? prev.length : prev)) {
      // If multiple (multi-select), keep intersection silently
      if (Array.isArray(prev)) {
        const keep = prev.filter(v => opts.some(o => o.value === v));
        $select.val(keep);
      } else {
        // single
        const stillValid = opts.some(o => o.value === prev);
        $select.val(stillValid ? prev : '');
      }
    } else {
      $select.val('');
    }
  }

  let raf = null;
  function schedule(reason){
    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => updateOnce(reason));
  }

  async function updateOnce(reason){
    const $root = panelRoot();
    const mid   = currentMenuId($root);
    if (!mid) { log('no menu selected'); return; }

    const nodes = await fetchSectionsTree(mid);
    const opts  = buildOptions(nodes);

    // Find ONLY the Data Source → Sections control(s)
    const $targets = $root.find(SECTIONS_SELECTOR).filter(function(){
      return !$(this).hasClass('select2-hidden-accessible');
    });

    $targets.each(function(){ applyOptions($(this), opts); });
    log('updated', reason, 'menu=', mid, 'targets=', $targets.length, 'nodes=', nodes.length);
  }

  function bind(){
    const $root = panelRoot();

    // Initial fill when the widget panel opens
    if (window.elementor && elementor.hooks && elementor.hooks.addAction) {
      elementor.hooks.addAction('panel/open_editor/widget', function(){
        schedule('open-editor');
      });
    }

    // Update when the menu is changed
    MENU_SELECTORS.forEach(sel => {
      $root.on('change', sel, function(){ schedule('menu-change'); });
    });

    // Update when the Sections select is opened/focused (ensures late-initialized selects are correct)
    $root.on('focus select2:opening', SECTIONS_SELECTOR, function(){ schedule('sections-open'); });

    // Update when new controls appear
    const mo = new MutationObserver(() => schedule('mutation'));
    const el = $root.get(0);
    if (el) mo.observe(el, { childList:true, subtree:true });

    // First run
    schedule('boot');
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    bind();
  } else {
    $(bind);
  }
})(jQuery);
