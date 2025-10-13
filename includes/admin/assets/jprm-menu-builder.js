(function($){
  /* ---------------- REST helpers ---------------- */
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

  /* ---------------- State ---------------- */
  const state = { menus: [], sections: [], items: [], unassigned: [], currentMenu: null };
  const INDENT = 28;
  const MAX_DEPTH = 6;
  let drag = null;

  function setLoading(on){ $('#jprm-loading')[on ? 'show' : 'hide'](); }

  /* ---------------- Loaders ---------------- */
  function loadMenus(){
    setLoading(true);
    return apiGet('menu-builder/menus').done((res)=>{
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
    }).always(()=> setLoading(false));
  }
  function loadSections(){
    if (!state.currentMenu) { $('#jprm-tree').empty(); return $.Deferred().resolve().promise(); }
    setLoading(true);
    return apiGet('menu-builder/sections?menu_id='+state.currentMenu).done((res)=>{
      state.sections = res.sections || [];
    }).always(()=> setLoading(false));
  }
  function loadItems(){
    if (!state.currentMenu) { state.items = []; return $.Deferred().resolve().promise(); }
    return apiGet('menu-builder/items?menu_id='+state.currentMenu).done((res)=>{
      state.items = res.items || [];
    });
  }
  function loadUnassigned(){
    if (!state.currentMenu) { state.unassigned = []; return $.Deferred().resolve().promise(); }
    return apiGet('menu-builder/items?menu_id='+state.currentMenu+'&unassigned=1').done((res)=>{
      state.unassigned = res.items || [];
    });
  }

  /* ---------------- Helpers ---------------- */
  function applyIndent($li, depth){
    $li.attr('data-depth', depth);
    $li.css('margin-left', (depth * INDENT) + 'px');
    $li.find('> .jprm-row').css('padding-left', '10px');
  }
  function clampDepth(depth, $placeholder){
    depth = Math.max(0, Math.min(MAX_DEPTH, depth));
    const $prev = $placeholder.prev('.jprm-item');
    if ($prev.length){
      const prevDepth = parseInt($prev.attr('data-depth'),10) || 0;
      depth = Math.min(depth, prevDepth + 1);
    } else {
      depth = 0; // cannot indent without a previous parent
    }
    return depth;
  }
  function buildSectionTree(){
    const byId = {}; state.sections.forEach(s => byId[s.id] = { ...s, depth: 0, children: [] });
    const roots = [];
    state.sections.forEach(s => {
      if (s.parent_id && byId[s.parent_id]) byId[s.parent_id].children.push(byId[s.id]);
      else roots.push(byId[s.id]);
    });
    (function setDepth(nodes,d){ nodes.forEach(n => { n.depth = d; if (n.children) setDepth(n.children, d+1); }); })(roots, 0);
    const flat = [];
    (function walk(nodes){ nodes.forEach(n => { flat.push({ id:n.id, title:n.title, depth:n.depth }); walk(n.children||[]); }); })(roots);
    return { roots, flat };
  }
  function fillTargetSectionSelect(){
    const $sel = $('#jprm-item-target-section').empty();
    const tree = buildSectionTree();
    if (!tree.flat.length) { $sel.append($('<option>').text('— No sections —').prop('disabled', true)); return; }
    tree.flat.forEach(s => {
      const indent = new Array(s.depth + 1).join('— ');
      $sel.append($('<option>').val(s.id).text(indent + s.title));
    });
    $sel.val(tree.flat[0].id);
  }
  function renderUnassignedCheckboxes(){
    const $box = $('#jprm-unassigned-list').empty();
    if (!state.unassigned.length) { $box.append($('<div>').css('opacity',.7).text('— No unassigned items —')); return; }
    state.unassigned.forEach(it => {
      const id = 'ua-'+it.id;
      const label = it.price ? `${it.title} • ${it.price}` : it.title;
      const $row = $('<label for="'+id+'">').css({display:'block', padding:'4px 2px'});
      $row.append($('<input type="checkbox">').attr('id', id).attr('data-id', it.id).css('margin-right','6px'));
      $row.append($('<span>').text(label));
      $box.append($row);
    });
    $('#jprm-unassigned-all').prop('checked', false);
  }

  /* ---------------- Rendering ---------------- */
  function renderList(){
    const itemsBySection = {};
    state.items.forEach(it => {
      if (!itemsBySection[it.section_id]) itemsBySection[it.section_id] = [];
      itemsBySection[it.section_id].push(it);
    });
    Object.values(itemsBySection).forEach(arr => arr.sort((a,b)=> (a.order_in_section||0) - (b.order_in_section||0) || a.title.localeCompare(b.title)));

    const byId = {}; state.sections.forEach(s => byId[s.id] = { ...s, depth: 0, children: [] });
    const roots = [];
    state.sections.forEach(s => {
      if (s.parent_id && byId[s.parent_id]) byId[s.parent_id].children.push(byId[s.id]);
      else roots.push(byId[s.id]);
    });
    (function setDepth(nodes,d){ nodes.forEach(n => { n.depth = d; if (n.children) setDepth(n.children, d+1); }); })(roots, 0);

    const $ul = $('#jprm-tree').empty().removeClass().addClass('jprm-flat');

    function actionIcon(cls, title){
      return $('<span class="dashicons '+cls+' jprm-act" title="'+title+'">').css({cursor:'pointer',opacity:.8});
    }

    function addSectionRow(sec){
      const $li  = $('<li>').attr('data-id', sec.id).attr('data-depth', sec.depth).addClass('jprm-item jprm-section');
      const $toggle = $('<button type="button" class="jprm-toggle button button-small" title="Toggle">▸</button>').css({marginRight:'6px'});
      const $right = $('<span class="jprm-actions">')
        .append(actionIcon('dashicons-dismiss','Unassign section').attr('data-action','section-unassign'))
        .append(' ')
        .append(actionIcon('dashicons-trash','Delete section').attr('data-action','section-delete'));
      const $row = $('<div class="jprm-row">')
        .append($toggle)
        .append($('<span class="jprm-title">').text(sec.title))
        .append($right);
      $li.append($row);
      $ul.append($li);
      applyIndent($li, sec.depth);

      const its = itemsBySection[sec.id] || [];
      if (its.length === 0) {
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
            .addClass('jprm-item jprm-entry is-item'); // draggable
          const $right = $('<span class="jprm-actions">')
            .append(actionIcon('dashicons-dismiss','Unassign item').attr('data-action','item-unassign'))
            .append(' ')
            .append(actionIcon('dashicons-trash','Delete item').attr('data-action','item-delete'));
          const $rowIt = $('<div class="jprm-row">')
            .append($('<span class="jprm-title">').text(it.price ? `${it.title} • ${it.price}` : it.title))
            .append($right);
          $liIt.append($rowIt);
          $ul.append($liIt);
          applyIndent($liIt, depth);
        });
      }

      (sec.children || []).forEach(addSectionRow);
    }

    roots.forEach(addSectionRow);

    // Right pane
    fillTargetSectionSelect();
    renderUnassignedCheckboxes();

    // Init drag/drop
    initSortable($ul);
  }

  /* ---------------- Sortable (sections + items) ---------------- */
  function collectItemsForSave(){
    const itemsPayload = [];
    let currentSectionId = 0;
    let orderInSection = -1;

    $('#jprm-tree > li.jprm-item').each(function(){
      const $li = $(this);
      if ($li.hasClass('jprm-section')) {
        currentSectionId = parseInt($li.attr('data-id'),10);
        orderInSection = -1;
      } else if ($li.hasClass('is-item')) {
        const depth = parseInt($li.attr('data-depth'),10) || 1;
        if (depth < 1 || !currentSectionId) return;
        orderInSection++;
        const id = parseInt($li.attr('data-id'),10);
        itemsPayload.push({ id, section_id: currentSectionId, order: orderInSection });
        $li.attr('data-section-id', currentSectionId);
      }
    });
    return itemsPayload;
  }

  function initSortable($ul){
    try { $ul.sortable('destroy'); } catch(e){}

    let dragBlock = null;

    $ul.sortable({
      placeholder: 'jprm-placeholder',
      items: '> li.jprm-section, > li.is-item',
      handle: '.jprm-row',
      tolerance: 'pointer',
      helper: 'clone',
      forcePlaceholderSize: true,
      start: function(e, ui){
        $('body').addClass('jprm-sorting');

        const isSection = ui.item.hasClass('jprm-section');
        const startDepth = parseInt(ui.item.attr('data-depth'),10) || 0;

        if (isSection) {
          dragBlock = [];
          let $next = ui.item.next();
          while ($next.length) {
            const d = parseInt($next.attr('data-depth'),10) || 0;
            if (d > startDepth) { dragBlock.push($next[0]); $next = $next.next(); }
            else break;
          }
        } else {
          dragBlock = null;
        }

        drag = { startX: e.pageX, startDepth, isSection, $item: ui.item };
        ui.placeholder.height(ui.item.outerHeight());
        applyIndent(ui.placeholder, startDepth);
      },
      sort: function(e, ui){
        if (!drag) return;
        const deltaX = e.pageX - drag.startX;
        let newDepth = drag.startDepth + Math.round(deltaX / INDENT);
        newDepth = clampDepth(newDepth, ui.placeholder);
        if (!drag.isSection && newDepth < 1) newDepth = 1;
        applyIndent(ui.placeholder, newDepth);
      },
      beforeStop: function(e, ui){
        const depth = parseInt(ui.placeholder.attr('data-depth'),10) || 0;
        applyIndent(ui.item, depth);
      },
      stop: function(e, ui){
        $('body').removeClass('jprm-sorting');

        if (drag && drag.isSection && dragBlock && dragBlock.length) {
          $(dragBlock).insertAfter(ui.item);
        }

        drag = null;
        dragBlock = null;
      }
    });
  }

  /* ---------------- Expand/Collapse ---------------- */
  function collapseAll(){
    $('#jprm-tree > li.jprm-item').each(function(){
      const depth = parseInt($(this).attr('data-depth'),10) || 0;
      if (depth > 0) $(this).hide(); else $(this).show();
    });
    $('.jprm-toggle').text('▸');
    $('#jprm-toggle-all').text('Expand all').attr('data-collapsed','1');
  }
  function expandAll(){
    $('#jprm-tree > li.jprm-item').show();
    $('.jprm-toggle').text('▾');
    $('#jprm-toggle-all').text('Collapse all').attr('data-collapsed','0');
  }

  // Per-section toggle
  $(document).on('click', '.jprm-toggle', function(){
    const $sec = $(this).closest('li.jprm-section');
    const myDepth = parseInt($sec.attr('data-depth'),10) || 0;
    const expanded = $(this).text() === '▾';
    $(this).text(expanded ? '▸' : '▾');

    let $next = $sec.next();
    while ($next.length) {
      const d = parseInt($next.attr('data-depth'),10) || 0;
      if (d <= myDepth) break;
      if (expanded) $next.hide(); else $next.show();
      $next = $next.next();
    }
  });

  // Toggle button
  $(document).on('click', '#jprm-toggle-all', function(){
    const collapsed = $(this).attr('data-collapsed') === '1';
    if (collapsed) expandAll(); else collapseAll();
  });

  /* ---------------- Actions: sections & items ---------------- */

  // Section unassign / delete
  $(document).on('click', '.jprm-section .jprm-act', function(e){
    e.preventDefault();
    const $sec = $(this).closest('li.jprm-section');
    const sectionId = parseInt($sec.attr('data-id'),10);
    const action = $(this).data('action');

    if (!state.currentMenu || !sectionId) return;

    if (action === 'section-unassign') {
      if (!confirm('Unassign this section from the menu?')) return;
      setLoading(true);
      apiPost('menu-builder/section/unassign', { menu_id: state.currentMenu, section_id: sectionId })
        .done(()=> $.when( loadSections(), loadItems(), loadUnassigned() ).then(renderList))
        .fail((xhr)=> alert('Unassign failed: ' + (xhr.responseJSON?.message || 'Unknown error')))
        .always(()=> setLoading(false));
    }
    if (action === 'section-delete') {
      if (!confirm('Delete this section? (Only possible if it has no subsections/items)')) return;
      setLoading(true);
      apiPost('menu-builder/section/delete', { menu_id: state.currentMenu, section_id: sectionId })
        .done(()=> $.when( loadSections(), loadItems(), loadUnassigned() ).then(renderList))
        .fail((xhr)=> alert('Delete failed: ' + (xhr.responseJSON?.message || 'Unknown error')))
        .always(()=> setLoading(false));
    }
  });

  // Item unassign / delete
  $(document).on('click', '.is-item .jprm-act', function(e){
    e.preventDefault();
    const $it = $(this).closest('li.is-item');
    const id = parseInt($it.attr('data-id'),10);
    const action = $(this).data('action');

    if (!id) return;

    if (action === 'item-unassign') {
      if (!confirm('Remove this item from its section?')) return;
      setLoading(true);
      apiPost('menu-builder/item/unassign', { id })
        .done(()=> $.when( loadItems(), loadUnassigned() ).then(renderList))
        .fail((xhr)=> alert('Unassign failed: ' + (xhr.responseJSON?.message || 'Unknown error')))
        .always(()=> setLoading(false));
    }
    if (action === 'item-delete') {
      if (!confirm('Delete this item permanently?')) return;
      setLoading(true);
      apiPost('menu-builder/item/delete', { id })
        .done(()=> $.when( loadItems(), loadUnassigned() ).then(renderList))
        .fail((xhr)=> alert('Delete failed: ' + (xhr.responseJSON?.message || 'Unknown error')))
        .always(()=> setLoading(false));
    }
  });

  /* ---------------- Events ---------------- */
  $(document).on('change', '#jprm-menu-select', function(){
    state.currentMenu = parseInt($(this).val(), 10) || null;
    $.when( loadSections(), loadItems(), loadUnassigned() ).then(function(){
      renderList();
      expandAll(); // keep expanded by default
    });
  });

  $('#jprm-refresh').on('click', function(){
    $.when( loadMenus(), loadSections(), loadItems(), loadUnassigned() ).then(function(){
      renderList();
      expandAll();
    });
  });

  $('#jprm-add-section').on('click', function(){
    const title = $('#jprm-new-section-title').val().trim();
    if (!title) { alert('Please enter a section title.'); return; }
    if (!state.currentMenu) { alert('Select a Menu first.'); return; }
    setLoading(true);
    apiPost('menu-builder/section', { name: title, parent: 0, menu_id: state.currentMenu })
      .done(()=> { $('#jprm-new-section-title').val(''); $.when( loadSections(), loadItems(), loadUnassigned() ).then(renderList); })
      .fail((xhr)=> { alert('Could not create section: ' + (xhr.responseJSON?.message || 'Unknown error')); })
      .always(()=> setLoading(false));
  });

  // Save: sections + items (and then reload fresh to avoid stale UI)
  $('#jprm-save').on('click', function(){
    if (!state.currentMenu) { alert('Select a Menu first.'); return; }

    const tree = (function collectSections(){
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
    })();

    const itemsPayload = (function collectItemsForSave(){
      const arr = [];
      let currentSectionId = 0;
      let orderInSection = -1;
      $('#jprm-tree > li.jprm-item').each(function(){
        const $li = $(this);
        if ($li.hasClass('jprm-section')) {
          currentSectionId = parseInt($li.attr('data-id'),10);
          orderInSection = -1;
        } else if ($li.hasClass('is-item')) {
          const depth = parseInt($li.attr('data-depth'),10) || 1;
          if (depth < 1 || !currentSectionId) return;
          orderInSection++;
          const id = parseInt($li.attr('data-id'),10);
          arr.push({ id, section_id: currentSectionId, order: orderInSection });
        }
      });
      return arr;
    })();

    setLoading(true);
    apiPost('menu-builder/sections/order', { tree, menu_id: state.currentMenu })
      .then(()=> apiPost('menu-builder/items/order', { menu_id: state.currentMenu, items: itemsPayload }))
      .then(()=> $.when( loadSections(), loadItems(), loadUnassigned() ).then(renderList))
      .fail((xhr)=> { alert('Save failed: ' + (xhr.responseJSON?.message || 'Unknown error')); })
      .always(()=> setLoading(false));
  });

  // Unassigned: select all
  $(document).on('change', '#jprm-unassigned-all', function(){
    const checked = $(this).is(':checked');
    $('#jprm-unassigned-list input[type="checkbox"]').prop('checked', checked);
  });

  // Assign selected checkboxes
  $('#jprm-assign-item').on('click', function(){
    if (!state.currentMenu) { alert('Select a Menu first.'); return; }
    const secId = parseInt($('#jprm-item-target-section').val(), 10) || 0;
    const ids = $('#jprm-unassigned-list input[type="checkbox"]:checked').map(function(){ return parseInt($(this).attr('data-id'),10); }).get();
    if (!secId || !ids.length) { alert('Choose a section and at least one item.'); return; }

    setLoading(true);
    apiPost('menu-builder/item/assign-batch', { menu_id: state.currentMenu, section_id: secId, ids })
      .done(()=> $.when( loadItems(), loadUnassigned() ).then(renderList))
      .fail((xhr)=> { alert('Could not assign items: ' + (xhr.responseJSON?.message || 'Unknown error')); })
      .always(()=> setLoading(false));
  });

  // Boot
  $(function(){
    $.when( loadMenus(), loadSections(), loadItems(), loadUnassigned() ).then(function(){
      renderList();
      expandAll(); // default state
    });
  });
})(jQuery);
