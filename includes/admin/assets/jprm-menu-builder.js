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
    if (!state.unassigned.length) { $box.append($('<div>').addClass('jprm-muted').text('— No unassigned items —')); return; }
    state.unassigned.forEach(it => {
      const id = 'ua-'+it.id;
      const label = it.price ? `${it.title} • ${it.price}` : it.title;
      const $row = $('<label for="'+id+'">').addClass('jprm-ua-row');
      $row.append($('<input type="checkbox">').attr('id', id).attr('data-id', it.id));
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
      return $('<span class="dashicons '+cls+' jprm-act" title="'+title+'">');
    }

    function addSectionRow(sec){
      const $li  = $('<li>').attr('data-id', sec.id).attr('data-depth', sec.depth).addClass('jprm-item jprm-section');
      const $toggle = $('<button type="button" class="jprm-toggle button button-small" title="Toggle">▾</button>');
      const $right = $('<span class="jprm-actions">')
        .append(actionIcon('dashicons-dismiss','Unassign section').attr('data-action','section-unassign'));
      const $row = $('<div class="jprm-row">')
        .append($('<span class="jprm-handle dashicons dashicons-move" title="Drag section"></span>'))
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
          $('<span class="jprm-title jprm-muted">').text('— No items in this section —')
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
            .addClass('jprm-item jprm-entry is-item');
          const $right = $('<span class="jprm-actions">')
            .append(actionIcon('dashicons-dismiss','Unassign item').attr('data-action','item-unassign'));
          const $rowIt = $('<div class="jprm-row">')
            .append($('<span class="jprm-handle dashicons dashicons-move" title="Drag item"></span>'))
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

    // Toggle-all label reflects current state (default expanded)
    setToggleAllLabel(false);
  }

  /* ---------------- Sortable (sections + items) ---------------- */
  function initSortable($ul){
    try { $ul.sortable('destroy'); } catch(e){}

    function buildSectionHelper($item){
      const startDepth = parseInt($item.attr('data-depth'),10) || 0;
      const $helper = $('<div class="jprm-helper-group">');
      const $secClone = $item.clone();
      $helper.append($secClone);

      const block = [];
      let $next = $item.next();
      while ($next.length) {
        const d = parseInt($next.attr('data-depth'),10) || 0;
        if (d > startDepth) {
          block.push($next[0]);
          $helper.append($next.clone());
          $next.data('jprm-old-display', $next.css('display'));
          $next.css('display','none').addClass('jprm-drag-hidden');
          $next = $next.next();
        } else {
          break;
        }
      }
      $item.data('jprm-drag-block', block);
      $item.data('jprm-start-depth', startDepth);
      $helper.find('> li').each(function(){
        const d = parseInt($(this).attr('data-depth'),10) || 0;
        $(this).css('margin-left', (d * INDENT) + 'px');
      });
      return $helper;
    }

    function snapPlaceholderForSection(ui){
      if (!drag || !drag.isSection) return;
      let $prev = ui.placeholder.prev();
      while ($prev.length && $prev.hasClass('is-item')) {
        ui.placeholder.insertBefore($prev);
        $prev = ui.placeholder.prev();
      }
    }

    function enforceItemDepth(ui){
      // Items cannot be indented/outdented horizontally.
      // Force their depth to be (nearest previous section depth + 1).
      if (!drag || drag.isSection) return;
      let $prev = ui.placeholder.prev();
      let sectionDepth = 0;
      while ($prev.length) {
        if ($prev.hasClass('jprm-section')) {
          sectionDepth = parseInt($prev.attr('data-depth'),10) || 0;
          break;
        }
        $prev = $prev.prev();
      }
      const newDepth = sectionDepth + 1;
      applyIndent(ui.placeholder, newDepth);
    }

    $ul.sortable({
      placeholder: 'jprm-placeholder',
      items: '> li.jprm-section, > li.is-item',
      handle: '.jprm-handle', // drag only via the handle (≡)
      tolerance: 'pointer',
      forcePlaceholderSize: true,
      helper: function(e, item){
        if ($(item).hasClass('jprm-section')) return buildSectionHelper($(item));
        return item.clone(); // item helper is single-row
      },
      start: function(e, ui){
        $('body').addClass('jprm-sorting');

        const isSection = ui.item.hasClass('jprm-section');
        const startDepth = parseInt(ui.item.attr('data-depth'),10) || 0;
        drag = { startX: e.pageX, startDepth, isSection, $item: ui.item };

        ui.placeholder.height(ui.item.outerHeight());
        applyIndent(ui.placeholder, startDepth);

        if (isSection) snapPlaceholderForSection(ui);
        else enforceItemDepth(ui);
      },
      sort: function(e, ui){
        if (!drag) return;

        if (drag.isSection) {
          // allow horizontal change for sections only
          const deltaX = e.pageX - drag.startX;
          let newDepth = drag.startDepth + Math.round(deltaX / INDENT);
          newDepth = clampDepth(newDepth, ui.placeholder);
          snapPlaceholderForSection(ui);
          applyIndent(ui.placeholder, newDepth);
        } else {
          // items: vertical only — keep placeholder aligned to nearest section depth + 1
          enforceItemDepth(ui);
        }
      },
      beforeStop: function(e, ui){
        const depth = parseInt(ui.placeholder.attr('data-depth'),10) || 0;
        applyIndent(ui.item, depth);
      },
      stop: function(e, ui){
        $('body').removeClass('jprm-sorting');

        // If a section was dragged, reattach its hidden descendants after it
        if (drag && drag.isSection) {
          const block = ui.item.data('jprm-drag-block') || [];
          if (block.length) {
            for (let i=0;i<block.length;i++){
              const $n = $(block[i]);
              $n.css('display', $n.data('jprm-old-display') || '');
              $n.removeClass('jprm-drag-hidden');
              $n.insertAfter(ui.item);
            }
          }
          ui.item.removeData('jprm-drag-block').removeData('jprm-start-depth');
        }

        drag = null;
      }
    });
  }

  /* ---------------- Expand/Collapse ---------------- */
  function setToggleAllLabel(collapsed){
    $('#jprm-toggle-all').text(collapsed ? 'Expand all' : 'Collapse all')
                         .attr('data-collapsed', collapsed ? '1' : '0');
  }
  function collapseAll(){
    $('#jprm-tree > li.jprm-item').each(function(){
      const depth = parseInt($(this).attr('data-depth'),10) || 0;
      if (depth > 0) $(this).hide(); else $(this).show();
    });
    $('.jprm-toggle').text('▸');
    setToggleAllLabel(true);
  }
  function expandAll(){
    $('#jprm-tree > li.jprm-item').show();
    $('.jprm-toggle').text('▾');
    setToggleAllLabel(false);
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

    const anyHidden = $('#jprm-tree > li.jprm-item[data-depth!="0"]:hidden').length > 0;
    setToggleAllLabel(anyHidden);
  });

  // Single toggle-all button
  $(document).on('click', '#jprm-toggle-all', function(){
    const collapsed = $(this).attr('data-collapsed') === '1';
    if (collapsed) expandAll(); else collapseAll();
  });

  /* ---------------- Actions: sections & items ---------------- */
  // Section unassign (trash removed)
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
        .done(()=> chainLoadAndRender(true))
        .fail((xhr)=> alert('Unassign failed: ' + (xhr.responseJSON?.message || 'Unknown error')))
        .always(()=> setLoading(false));
    }
  });

  // Item unassign (trash removed)
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
        .done(()=> chainLoadAndRender(false))
        .fail((xhr)=> alert('Unassign failed: ' + (xhr.responseJSON?.message || 'Unknown error')))
        .always(()=> setLoading(false));
    }
  });

  /* ---------------- Save & Events ---------------- */
  function chainLoadAndRender(expand){
    return loadSections()
      .then(()=> loadItems())
      .then(()=> loadUnassigned())
      .then(()=> { renderList(); if (expand) expandAll(); else setToggleAllLabel(false); });
  }

  $(document).on('change', '#jprm-menu-select', function(){
    state.currentMenu = parseInt($(this).val(), 10) || null;
    chainLoadAndRender(true);
  });

  $('#jprm-refresh').on('click', function(){
    loadMenus().then(()=> chainLoadAndRender(true));
  });

  $('#jprm-add-section').on('click', function(){
    const title = $('#jprm-new-section-title').val().trim();
    if (!title) { alert('Please enter a section title.'); return; }
    if (!state.currentMenu) { alert('Select a Menu first.'); return; }
    setLoading(true);
    apiPost('menu-builder/section', { name: title, parent: 0, menu_id: state.currentMenu })
      .done(()=> { $('#jprm-new-section-title').val(''); chainLoadAndRender(true); })
      .fail((xhr)=> { alert('Could not create section: ' + (xhr.responseJSON?.message || 'Unknown error')); })
      .always(()=> setLoading(false));
  });

  // Save: sections + items
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
      .then(()=> chainLoadAndRender(true))
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
      .done(()=> chainLoadAndRender(false))
      .fail((xhr)=> { alert('Could not assign items: ' + (xhr.responseJSON?.message || 'Unknown error')); })
      .always(()=> setLoading(false));
  });

  // First load: sequential chain
  $(function(){
    loadMenus()
      .then(()=> loadSections())
      .then(()=> loadItems())
      .then(()=> loadUnassigned())
      .then(()=> { renderList(); expandAll(); });
  });
})(jQuery);
