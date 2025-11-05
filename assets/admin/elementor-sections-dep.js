/* global elementor, JPRMAjax */
(function () {
  'use strict';

  const LOG = '[JPRM]';
  const d = document;

  function log() {
    try { console.log.apply(console, [LOG].concat([].slice.call(arguments))); } catch (e) {}
  }

  // ----- AJAX -----
  async function fetchSectionsMap(menuId) {
    const url = (JPRMAjax && JPRMAjax.url) ? JPRMAjax.url : '/wp-admin/admin-ajax.php';
    const nonce = (JPRMAjax && JPRMAjax.nonce) ? JPRMAjax.nonce : '';
    const form = new URLSearchParams();
    form.set('action', 'jprm_sections_for_menu');
    if (menuId) form.set('menu_id', String(menuId));
    if (nonce)  form.set('nonce', nonce);

    const res = await fetch(url, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: form.toString()
    });

    let payload;
    try { payload = await res.json(); } catch (e) { log('AJAX JSON parse error'); return {}; }
    if (!payload || !payload.success || !payload.data || !Array.isArray(payload.data.sections)) return {};

    // Build id -> label map with indentation
    const map = {};
    payload.data.sections.forEach(s => {
      const lvl = Math.max(0, parseInt(s.level || 0, 10));
      const indent = (lvl > 0) ? Array(lvl + 1).join('  ') : ''; // NBSP indents
      map[String(s.id)] = indent + s.text;
    });
    return map;
  }

  // ----- ELEMENTOR PANEL HELPERS -----
  function getPanelRoot() {
    return d.querySelector('.elementor-panel') || d.body;
  }

  // Read selected Menu from model (preferred), fallback to DOM
  function getCurrentMenuId(panel) {
    try {
      if (window.elementor && elementor.getPanelView) {
        const pv = elementor.getPanelView().getCurrentPanelView();
        const v = pv && pv.model && pv.model.getSetting('menus');
        if (Array.isArray(v)) return v[0] || '';
        return v || '';
      }
    } catch (e) {}
    const p = panel || getPanelRoot();
    const sel = p.querySelector('[data-setting="menus"]');
    if (!sel) return '';
    const val = sel.value;
    return Array.isArray(val) ? (val[0] || '') : val || '';
  }

  // Collect all Section selects we need to keep in sync
  function findAllSectionSelects(scope) {
    const root = scope || getPanelRoot();

    const nodes = new Set();

    // Data Source → Sections (SELECT2)
    root.querySelectorAll('select[data-setting="sections"]').forEach(n => nodes.add(n));

    // Layout → Split after section (1/2) (plain SELECT)
    root.querySelectorAll('select[data-setting="layout_split_after_section"]').forEach(n => nodes.add(n));
    root.querySelectorAll('select[data-setting="layout_split_after_section2"]').forEach(n => nodes.add(n));

    // Info Blocks (repeater) → Target Section (SELECT2)
    // Matches inputs like: name="info_blocks[0][section_id]"
    root.querySelectorAll('select[name$="[section_id]"]').forEach(n => {
      if (n.closest('[data-repeater-items]') || n.closest('.elementor-repeater-fields')) nodes.add(n);
    });

    // Labels Layout Overrides (repeater) → Section (plain SELECT)
    // Matches inputs like: name="labels_layout_overrides[0][section_id]"
    root.querySelectorAll('select[name^="labels_layout_overrides"][name$="[section_id]"]').forEach(n => nodes.add(n));

    return Array.from(nodes);
  }

  // Apply new options to a <select> while preserving valid selections
  function applyOptions(selectEl, idToLabelMap) {
    if (!selectEl) return;
    const isMultiple = !!selectEl.multiple;

    // Keep only selections that still exist in map
    const current = (function () {
      if (isMultiple) {
        return Array.from(selectEl.selectedOptions || []).map(o => o.value).filter(v => idToLabelMap.hasOwnProperty(String(v)));
      }
      const v = selectEl.value;
      return idToLabelMap.hasOwnProperty(String(v)) ? [String(v)] : [];
    })();

    // Rebuild options
    while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);
    const frag = d.createDocumentFragment();
    if (!isMultiple) {
      frag.appendChild(new Option('', '')); // allow clearing
    }
    Object.keys(idToLabelMap).forEach(id => {
      const opt = new Option(idToLabelMap[id], id, false, current.includes(id));
      frag.appendChild(opt);
    });
    selectEl.appendChild(frag);

    // Trigger change (Elementor/Select2 aware)
    if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery(selectEl).trigger) {
      jQuery(selectEl).trigger('change');
    } else {
      const evt = new Event('change', { bubbles: true });
      selectEl.dispatchEvent(evt);
    }
  }

  // Refresh all section dropdowns for the active panel
  let inflight = 0;
  async function refreshAllSectionDropdowns(scope) {
    const panel = scope || getPanelRoot();
    const menuId = getCurrentMenuId(panel);
    if (!menuId) { log('No menu selected → skip refresh'); return; }

    const myTicket = ++inflight;
    const map = await fetchSectionsMap(menuId);
    if (myTicket !== inflight) return; // a newer refresh superseded this one

    const selects = findAllSectionSelects(panel);
    selects.forEach(sel => applyOptions(sel, map));
    log('Patched all section selects', { menuId, count: selects.length, options: Object.keys(map).length });
  }

  // Bind to Menu change to re-scope sections
  function bindMenuChange(scope) {
    const panel = scope || getPanelRoot();
    const menuSel = panel.querySelector('[data-setting="menus"]');
    if (!menuSel) return;

    menuSel.addEventListener('change', () => {
      refreshAllSectionDropdowns(panel);
    }, { passive: true });
  }

  // Observe panel for newly rendered controls (repeaters, tabs, etc.)
  function startObserver() {
    const root = getPanelRoot();
    const mo = new MutationObserver((muts) => {
      let needsRefresh = false;
      muts.forEach(m => {
        m.addedNodes && Array.prototype.forEach.call(m.addedNodes, (node) => {
          if (!(node instanceof HTMLElement)) return;
          if (
            node.matches('select[data-setting="sections"], select[data-setting="layout_split_after_section"], select[data-setting="layout_split_after_section2"]') ||
            node.querySelector('select[data-setting="sections"]') ||
            node.querySelector('select[data-setting="layout_split_after_section"]') ||
            node.querySelector('select[data-setting="layout_split_after_section2"]') ||
            node.querySelector('select[name$="[section_id]"]')
          ) {
            needsRefresh = true;
          }
        });
      });
      if (needsRefresh) refreshAllSectionDropdowns(root);
    });
    mo.observe(root, { childList: true, subtree: true });
    return mo;
  }

  // Boot when Elementor editor is ready/open
  function bootWhenPanelReady() {
    // If panel already present, bind immediately
    const attempt = () => {
      const panel = getPanelRoot();
      if (!panel.querySelector('.elementor-panel')) {
        setTimeout(attempt, 300);
        return;
      }
      bindMenuChange(panel);
      startObserver();
      // Initial pass
      refreshAllSectionDropdowns(panel);
      log('sections-dep hook active');
    };
    attempt();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootWhenPanelReady);
  } else {
    bootWhenPanelReady();
  }
})();
