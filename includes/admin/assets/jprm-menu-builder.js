(function($){
  /** ---------------- REST helpers ---------------- */
  function apiGet(path) {
    return $.ajax({
      url: JPRM_MENU_BUILDER.root + '/' + path.replace(/^\//,''),
      method: 'GET',
      beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', JPRM_MENU_BUILDER.nonce)
    });
  }
  function apiPost(path, data) {
    return $.ajax({
      url: JPRM_MENU_BUILDER.root + '/' + path.replace(/^\//,''),
      method: 'POST',
      contentType: 'application/json; charset=utf-8',
      data: JSON.stringify(data || {}),
      beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', JPRM_MENU_BUILDER.nonce)
    });
  }

  /** ---------------- Diagnostics (visible if JPRM_MENU_BUILDER.debug) ---------------- */
  function runDiagnostics(){
    const $wrap = $('<div class="jprm-diag" style="margin:10px 0;padding:8px;border:1px solid #ccd0d4;background:#fffff8;"></div>');
    $wrap.append('<strong>Diagnostics:</strong> ');
    const $ping = $('<span> ping… </span>');
    const $menus = $('<span> menus… </span>');
    const $secs = $('<span> sections… </span>');
    const $items = $('<span> items… </span>');
    $wrap.append($ping).append($menus).append($secs).append($items);
    $('.jprm-menu-builder-wrap .jprm-toolbar').after($wrap);

    apiGet('ping').done(()=> $ping.text(' ping ✓ '))
                  .fail((e)=> $ping.text(' ping ✗ ' + (e.status||'err')));

    apiGet('menu-builder/menus').done((r)=>{
      $menus.text(' menus: ' + (r.menus ? r.menus.length : 0) + ' ');
    }).fail((e)=> $menus.text(' menus ✗ ' + (e.status||'err') + ' '));

    $(document).on('change', '#jprm-menu-select', function(){
      const mid = parseInt($(this).val(),10)||0;
      if(!mid){ $secs.text(' sections: 0 '); $items.text(' items: 0 '); return; }
      apiGet('menu-builder/sections?menu_id='+mid).done((r)=>{
        $secs.text(' sections: ' + (r.sections? r.sections.length:0) + ' ');
      }).fail((e)=> $secs.text(' sections ✗ ' + (e.status||'err') + ' '));
      apiGet('menu-builder/items?menu_id='+mid).done((r)=>{
        $items.text(' items: ' + (r.items? r.items.length:0) + ' ');
        console.debug('[JPRM] Items sample:', r.items?.slice(0,5));
      }).fail((e)=> $items.text(' items ✗ ' + (e.status||'err') + ' '));
    });
  }

  /** ---------------- Local state ---------------- */
  const state = { menus: [], sections: [], items: [], currentMenu: null };
  const INDENT = 28;     // px per depth (applied to entire li)
  const MAX_DEPTH = 6;
  let drag = null;

  function setLoading(on){ $('#jprm-loading')[on ? 'show' : 'hide'](); }

  /** ---------------- Loaders ---------------- */
  function loadMenus(){
    setLoading(true);
    return apiGet('menu-builder/menus')
      .done((res)=>{
        state.menus = res.menus || [];
        const $sel = $('#jprm-menu-select').empty();
        if (!state.menus.length) {
          $sel.append($('<option>').text('— No menus found —').prop('disabled', true));
          state.currentMenu = null;
          return;
        }
        state.menus.forEach(m => $sel.append($('<option>').val(m.id).text(m.title)));
        if (!state.currentMenu) state.currentMenu = state.menus[0].id;
        $sel.val(state.currentMenu);
      })
      .always(()=> setLoading(false));
  }

  function loadSections(){
    if (!state.currentMenu) { $('#jprm-tree').empty(); return $.Deferred().resolve().promise(); }
    setLoading(true);
    return apiGet('menu-builder/sections?menu_id='+state.currentMenu)
      .done((res)=>{
        state.sections = res.sections || [];
      })
      .always(()=> setLoading(false));
  }

  function loadItems(){
    if (!state.currentMenu) { state.items = []; return $.Deferred().resolve().promise(); }
    return apiGet('menu-builder/items?menu_id='+state.currentMenu)
      .done((res)=>{
        state.items = res.items || [];
      });
  }

  /** ---------------- Build & render ---------------- */
  function applyIndent($li, depth){
    $li.attr('data-depth', depth);
    // Entire box indent:
    $li.css('margin-left', (depth * INDENT) + 'px');
    // Keep row padding small/consistent:
    $li.find('> .jprm-row').css('padding-left', '10px');
  }

  function clampDepth(depth, $item){
    depth = Math.max(0, Math.min(MAX_DEPTH, depth));
    const $prev = $item.prev('.jprm-item');
    if ($prev.length){
      const prevDepth = parseInt($prev.attr('data-depth'),10) || 0;
      depth = Math.min(depth, prevDepth + 1);
    } else {
      depth = 0;
    }
    return depth;
  }

  function renderList(){
    // Build tree of sections to compute depths
    const byId = {}; state.sections.forEach(s => byId[s.id] = { ...s, depth: 0, children: [] });
    const roots = [];
    state.sections.forEach(s => {
      if (s.parent_id && byId[s.parent_id]) byId[s.parent_id].children.push(byId[s.id]);
      else roots.push(byId[s.id]);
    });
    (function setDepth(nodes, d){
      nodes.forEach(n => { n.depth = d; if (n.children) setDepth(n.children, d+1); });
    })(roots, 0);

    // Map items by section
    const itemsBySection = {};
    state.items.forEach(it => {
      if (!itemsBySection[it.section_id]) itemsBySection[it.section_id] = [];
      itemsBySection[it.section_id].push(it);
    });
    Object.values(itemsBySection).forEach(arr => arr.sort((a,b)=> (a.order_in_section||0) - (b.order_in_section||0) || a.title.localeCompare(b.title)));

    const $ul = $('#jprm-tree').empty().removeClass().addClass('jprm-flat');

    function addSectionRow(sec){
      const $li  = $('<li>').attr('data-id', sec.id).attr('data-depth', sec.depth).addClass('jprm-item jprm-section');
      const $row = $('<div class="jprm-row">')
        .append($('<span class="jprm-title">').text(sec.title))
        .append($('<span class="jprm-meta">').text('#'+sec.id));
      $li.append($row);
      $ul.append($li);
      applyIndent($li, sec.depth);

      // Insert items under this section (read-only)
      const its = itemsBySection[sec.id] || [];
      if (its.length === 0) {
        // 🔹 Visual hint so we know render ran and this section has no items
        const depth = sec.depth + 1;
        const $hint = $('<li>').addClass('jprm-item jprm-entry').attr('data-depth', depth);
        $hint.append($('<div class="jprm-row">').append(
          $('<span class="jprm-title">').css('opacity', .6).text('— No items in this section —')
        ));
        $ul.append($hint);
        applyIndent($hint, depth);
      } else {
        its.forEach(it => {
          const depth = sec.depth + 1;
          const $liIt  = $('<li>')
            .attr('data-id', it.id)
            .attr('data-depth', depth)
            .attr('data-section-id', sec.id)
            .addClass('jprm-item jprm-entry is-item'); // not draggable
          const label = it.price ? `${it.title} • ${it.price}` : it.title;
          const $rowIt = $('<div class="jprm-row">')
            .append($('<span class="jprm-title">').text(label))
            .append($('<span class="jprm-meta">').text('#'+it.id));
          $liIt.append($rowIt);
          $ul.append($liIt);
          applyIndent($liIt, depth);
        });
      }

      (sec.children || []).forEach(addSectionRow);
    }

    roots.forEach(addSectionRow);

    // Only sections draggable for now
    initSortable($ul);
  }

  /** ---------------- Sortable (sections only) ---------------- */
  function initSortable($ul){
    try { $ul.sortable('destroy'); } catch(e){}
    $ul.sortable({
      placeholder: 'jprm-placeholder',
      items: '> li.jprm-section',
      handle: '.jprm-row',
      tolerance: 'pointer',
      helper: 'clone',
      forcePlaceholderSize: true,
      start: function(e, ui){
        $('body').addClass('jprm-sorting');
        drag = {
          startX: e.pageX,
          startDepth: parseInt(ui.item.attr('data-depth'),10) || 0,
          $item: ui.item
        };
        ui.placeholder.height(ui.item.outerHeight());
        applyIndent(ui.placeholder, drag.startDepth);
      },
      sort: function(e, ui){
        if (!drag) return;
        const deltaX = e.pageX - drag.startX;
        const deltaDepth = Math.round(deltaX / INDENT);
        let newDepth = clampDepth(drag.startDepth + deltaDepth, ui.placeholder);
        applyIndent(ui.placeholder, newDepth);
      },
      beforeStop: function(e, ui){
        const depth = parseInt(ui.placeholder.attr('data-depth'),10) || 0;
        applyIndent(ui.item, depth);
      },
      stop: function(){
        $('body').removeClass('jprm-sorting');
        drag = null;
      }
    });
  }

  /** ---------------- Save sections only (unchanged) ---------------- */
  function collectForSave(){
    const stack = [];
    const out = [];
    $('#jprm-tree > li.jprm-section').each(function(idx){
      const $li = $(this);
      const id = parseInt($li.attr('data-id'),10);
      const depth = parseInt($li.attr('data-depth'),10) || 0;
      stack.length = depth;
      const parentId = depth > 0 ? (stack[depth-1] || 0) : 0;
      out.push({ id, parent_id: parentId, order: idx });
      stack[depth] = id;
    });
    return out;
  }

  /** ---------------- Expand / Collapse all ---------------- */
  function expandAll(){ $('#jprm-tree > li.jprm-item').show(); }
  function collapseAll(){
    $('#jprm-tree > li.jprm-item').each(function(){
      const depth = parseInt($(this).attr('data-depth'),10) || 0;
      if (depth > 0) $(this).hide(); else $(this).show();
    });
  }

  /** ---------------- Events ---------------- */
  $(document).on('change', '#jprm-menu-select', function(){
    state.currentMenu = parseInt($(this).val(), 10) || null;
    loadSections().then(loadItems).then(renderList);
  });

  $('#jprm-refresh').on('click', function(){
    loadMenus().then(loadSections).then(loadItems).then(renderList);
  });

  $('#jprm-add-section').on('click', function(){
    const title = $('#jprm-new-section-title').val().trim();
    if (!title) { alert('Please enter a section title.'); return; }
    if (!state.currentMenu) { alert('Select a Menu first.'); return; }
    setLoading(true);
    apiPost('menu-builder/section', { name: title, parent: 0, menu_id: state.currentMenu })
      .done(()=> { $('#jprm-new-section-title').val(''); loadSections().then(loadItems).then(renderList); })
      .fail((xhr)=> {
        alert('Could not create section: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'));
      })
      .always(()=> setLoading(false));
  });

  $('#jprm-save').on('click', function(){
    if (!state.currentMenu) { alert('Select a Menu first.'); return; }
    const tree = collectForSave();
    setLoading(true);
    apiPost('menu-builder/sections/order', { tree, menu_id: state.currentMenu })
      .done((res)=> {
        state.sections = res.sections || [];
        loadItems().then(renderList);
      })
      .fail((xhr)=> {
        alert('Could not save order: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'));
      })
      .always(()=> setLoading(false));
  });

  $('#jprm-expand').on('click', expandAll);
  $('#jprm-collapse').on('click', collapseAll);

  // Boot
  $(function(){
    if (window.JPRM_MENU_BUILDER && JPRM_MENU_BUILDER.debug) runDiagnostics();
    loadMenus().then(loadSections).then(loadItems).then(renderList);
  });
})(jQuery);
