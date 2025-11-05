(function ($) {
  'use strict';

  const LOG = '[JPRM]';
  function log(){ try{ console.log.apply(console, [LOG].concat([].slice.call(arguments))); }catch(e){} }

  function ajaxUrl(){ return (window.JPRMAjax && JPRMAjax.url) ? JPRMAjax.url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'); }
  function ajaxNonce(){ return (window.JPRMAjax && JPRMAjax.nonce) ? JPRMAjax.nonce : ''; }

  async function fetchSectionsTree(menuId) {
    const url  = ajaxUrl();
    const body = new URLSearchParams({
      action: 'jprm_sections_for_menu',
      menu_id: String(menuId || 0),
      nonce: ajaxNonce()
    });

    const res = await fetch(url, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    });

    // If PHP throws (500), don't blow up the editor
    let data = {};
    try { data = await res.json(); } catch (e) {
      log('AJAX parse error; HTTP', res.status);
      return [];
    }
    if (!data || !data.success || !data.data || !Array.isArray(data.data.sections)) return [];

    // Convert to Elementor options map with indentation
    const opts = {};
    data.data.sections.forEach(n => {
      const lvl = Math.max(0, parseInt(n.level || 0, 10));
      const indent = (lvl > 0) ? ('— '.repeat(lvl)) : '';
      opts[String(n.id)] = indent + String(n.text || '');
    });
    return opts;
  }

  /** Safely get current panel + control views */
  function getCurrentPanel() {
    try {
      if (!window.elementor || !elementor.getPanelView) return null;
      const pv = elementor.getPanelView();
      if (pv && pv.getCurrentPanelView) return pv.getCurrentPanelView();
    } catch(e){}
    return null;
  }

  function getControlView(name) {
    try {
      const panel = getCurrentPanel();
      if (!panel || !panel.getControlView) return null;
      return panel.getControlView(name);
    } catch(e){}
    return null;
  }

  function getSetting(name) {
    try {
      const panel = getCurrentPanel();
      if (!panel || !panel.model) return undefined;
      return panel.model.getSetting(name);
    } catch(e){}
    return undefined;
  }

  function setSetting(name, value) {
    try {
      const panel = getCurrentPanel();
      if (!panel || !panel.model) return;
      panel.model.setSetting(name, value);
    } catch(e){}
  }

  /** Rebuild the Data Source → Sections SELECT2 from AJAX */
  async function refreshDataSourceSections() {
    const panel = getCurrentPanel();
    if (!panel) return;

    // Current Menu value (single select)
    let menuVal = getSetting('menus');
    if (Array.isArray(menuVal)) menuVal = menuVal[0] || '';
    const menuId = parseInt(menuVal || 0, 10);

    // Fetch fresh scoped+tree options
    const optionsMap = await fetchSectionsTree(menuId);
    log('DS refresh', { menuId, count: Object.keys(optionsMap).length });

    // Ensure default for multi-select is an array (never null)
    let selected = getSetting('sections');
    if (!Array.isArray(selected)) selected = [];

    // Drop any selected ids that aren't in the new options
    const ids = Object.keys(optionsMap);
    selected = selected.filter(v => ids.includes(String(v)));

    // Update control model options and re-render
    const cv = getControlView('sections'); // Data Source → "sections"
    if (cv && cv.model) {
      cv.model.set('options', optionsMap);

      // Reapply the value safely (array for SELECT2 multiple)
      setSetting('sections', selected);

      // Re-render the control so select2 picks up the new options
      if (typeof cv.render === 'function') cv.render();

      // Make sure select2 shows the current selection
      const $sel = cv.$el.find('[data-setting="sections"]');
      if ($sel && $sel.length) {
        $sel.val(selected).trigger('change', { silent: true });
      }
      log('DS applied', { selected });
      return;
    }

    // Fallback: DOM only (should rarely happen)
    const $fallback = panel.$el ? panel.$el.find('[data-setting="sections"]') : $();
    if ($fallback.length) {
      $fallback.find('option').remove();
      Object.keys(optionsMap).forEach(id => {
        $fallback.append(new Option(optionsMap[id], id, false, selected.includes(id)));
      });
      $fallback.val(selected).trigger('change', { silent: true });
      log('DS applied (DOM fallback)', { selected });
    }
  }

  /** Bind to the “Menu” control so changing it refreshes DS sections immediately */
  function bindMenuWatcher() {
    const panel = getCurrentPanel();
    if (!panel) return;

    // Try via control view first (more reliable)
    const menuCV = getControlView('menus');
    if (menuCV && menuCV.$el) {
      const $sel = menuCV.$el.find('[data-setting="menus"]');
      if ($sel.length) {
        $sel.off('.jprm').on('change.jprm', () => refreshDataSourceSections());
        return;
      }
    }

    // Fallback: observe the panel for a menus field
    const $root = panel.$el || $('.elementor-panel');
    const $try = $root.find('[data-setting="menus"]');
    if ($try.length) {
      $try.off('.jprm').on('change.jprm', () => refreshDataSourceSections());
      return;
    }

    // Last resort: small poll to catch late render
    let tries = 0;
    const iv = setInterval(() => {
      const cv = getControlView('menus');
      if (cv && cv.$el && cv.$el.find('[data-setting="menus"]').length) {
        cv.$el.find('[data-setting="menus"]').off('.jprm').on('change.jprm', () => refreshDataSourceSections());
        clearInterval(iv);
      }
      if (++tries > 20) clearInterval(iv);
    }, 250);
  }

  /** Observe panel swaps (opening the widget, switching tabs) and refresh DS once */
  function watchPanel() {
    const $panelRoot = $('.elementor-panel');
    if (!$panelRoot.length) { setTimeout(watchPanel, 500); return; }

    const run = () => {
      // When widget opens or tab changes, refresh the DS sections once
      refreshDataSourceSections();
      bindMenuWatcher();
    };

    // Initial
    run();

    // Observe dynamic changes inside the editor panel
    const mo = new MutationObserver(() => {
      // Keep this lightweight; just re-bind and refresh DS
      run();
    });
    mo.observe($panelRoot.get(0), { childList: true, subtree: true });
  }

  function boot(){
    log('sections-dep.js active');
    watchPanel();
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') boot();
  else $(boot);

})(jQuery);
