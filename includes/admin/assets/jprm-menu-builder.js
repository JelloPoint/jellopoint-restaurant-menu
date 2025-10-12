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

  /** ---------------- Local state ---------------- */
  const state = { menus: [], sections: [], currentMenu: null };
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
    if (!state.currentMenu) { $('#jprm-tree').empty(); return; }
    setLoading(true);
    return apiGet('menu-builder/sections?menu_id='+state.currentMenu)
      .done((res)=>{
        state.sections = res.sections || [];
        renderList();
      })
      .always(()=> setLoading(false));
  }

  /** ---------------- Build a flat, depth-annotated list ---------------- */
  function buildFlat(items){
    const byId = {}; items.forEach(i => byId[i.id] = {...i, children: []});
    const roots = [];
    items.forEach(i => {
      if (i.parent_id && byId[i.parent_id]) byId[i.parent_id].children.push(byId[i.id]);
      else roots.push(byId[i.id]);
    });

    function sortRec(nodes){
      nodes.sort((a,b)=> (a.menu_order||0) - (b.menu_order||0) || a.title.localeCompare(b.title));
      nodes.forEach(n=> sortRec(n.children));
    }
    sortRec(roots);

    const out = [];
    (function walk(nodes, depth){
      nodes.forEach(n=>{
        out.push({ id:n.id, title:n.title, depth });
        if (n.children && n.children.length) walk(n.children, depth+1);
      });
    })(roots, 0);

    return out;
  }

  /** ---------------- Render (flat list) ---------------- */
  function renderList(){
    const flat = buildFlat(state.sections);
    const $ul = $('#jprm-tree').empty().removeClass().addClass('jprm-flat');

    flat.forEach(row=>{
      const $li  = $('<li>').attr('data-id', row.id).attr('data-depth', row.depth).addClass('jprm-item');
      const $row = $('<div class="jprm-row">')
        .append($('<span class="jprm-title">').text(row.title))
        .append($('<span class="jprm-meta">').text('#'+row.id));
      $li.append($row);
      $ul.append($li);
      applyIndent($li, row.depth);
    });

    initSortable($ul);
  }

  /** ---------------- Indentation helpers (WHOLE BOX) ---------------- */
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

  /** ---------------- Sortable (with horizontal-depth control) ---------------- */
  function initSortable($ul){
    try { $ul.sortable('destroy'); } catch(e){}
    $ul.sortable({
      placeholder: 'jprm-placeholder',
      items: '> li',
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
        applyIndent(ui.placeholder, drag.startDepth); // indent placeholder too
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

  /** ---------------- Build payload to save ---------------- */
  function collectForSave(){
    const stack = [];
    const out = [];
    $('#jprm-tree > li.jprm-item').each(function(idx){
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
  function expandAll(){
    $('#jprm-tree > li.jprm-item').show();
  }
  function collapseAll(){
    // Hide everything except depth 0
    $('#jprm-tree > li.jprm-item').each(function(){
      const depth = parseInt($(this).attr('data-depth'),10) || 0;
      if (depth > 0) $(this).hide(); else $(this).show();
    });
  }

  /** ---------------- Events ---------------- */
  $(document).on('change', '#jprm-menu-select', function(){
    state.currentMenu = parseInt($(this).val(), 10) || null;
    loadSections();
  });

  $('#jprm-refresh').on('click', function(){ loadMenus().then(loadSections); });

  $('#jprm-add-section').on('click', function(){
    const title = $('#jprm-new-section-title').val().trim();
    if (!title) { alert('Please enter a section title.'); return; }
    if (!state.currentMenu) { alert('Select a Menu first.'); return; }
    setLoading(true);
    apiPost('menu-builder/section', { name: title, parent: 0, menu_id: state.currentMenu })
      .done(()=> { $('#jprm-new-section-title').val(''); loadSections(); })
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
      .done((res)=> { state.sections = res.sections || []; renderList(); })
      .fail((xhr)=> {
        alert('Could not save order: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'));
      })
      .always(()=> setLoading(false));
  });

  $('#jprm-expand').on('click', expandAll);
  $('#jprm-collapse').on('click', collapseAll);

  // Boot
  $(function(){ loadMenus().then(loadSections); });
})(jQuery);
