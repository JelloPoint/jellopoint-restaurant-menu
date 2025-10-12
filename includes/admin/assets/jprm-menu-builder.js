(function($){
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

  const state = { menus: [], sections: [], currentMenu: null };

  function setLoading(on){ $('#jprm-loading')[on ? 'show' : 'hide'](); }

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
        renderTree();
      })
      .always(()=> setLoading(false));
  }

  // Build nested structure in-memory
  function buildTree(items){
    const byId = {}; items.forEach(i => byId[i.id] = {...i, children: []});
    const roots = [];
    items.forEach(i => {
      if (i.parent_id && byId[i.parent_id]) byId[i.parent_id].children.push(byId[i.id]);
      else roots.push(byId[i.id]);
    });
    // sort children by menu_order asc
    function sortRec(nodes){
      nodes.sort((a,b)=> (a.menu_order||0) - (b.menu_order||0) || a.title.localeCompare(b.title));
      nodes.forEach(n=> sortRec(n.children));
    }
    sortRec(roots);
    return roots;
  }

  function nodeLi(n){
    const $li = $('<li>').attr('data-id', n.id).addClass('jprm-li');
    const $row = $('<div class="jprm-node">')
      .append($('<span class="title">').text(n.title))
      .append($('<span class="meta">').text('#'+n.id));
    $li.append($row);

    const $kids = $('<ul class="jprm-children jprm-sortable">');
    n.children.forEach(c => $kids.append(nodeLi(c)));
    $li.append($kids);
    return $li;
  }

  function renderTree(){
    const roots = buildTree(state.sections);
    const $ul = $('#jprm-tree').empty().addClass('jprm-sortable');
    roots.forEach(n => $ul.append(nodeLi(n)));

    // Make every UL sortable and connected so items can be moved into/out of children
    $('.jprm-sortable').sortable('destroy'); // reset
    $('.jprm-sortable').sortable({
      connectWith: '.jprm-sortable',
      placeholder: 'jprm-placeholder',
      items: '> li',
      handle: '.jprm-node',
      tolerance: 'pointer'
    });
  }

  function collectTree(){
    // Flatten DOM into [{id,parent_id,order}]
    const out = [];
    function walk($ul, parentId){
      $ul.children('li').each(function(idx){
        const id = parseInt($(this).attr('data-id'), 10);
        out.push({ id, parent_id: parentId || 0, order: idx });
        const $kids = $(this).children('ul.jprm-children');
        if ($kids.length) walk($kids, id);
      });
    }
    walk($('#jprm-tree'), 0);
    return out;
  }

  // Events
  $(document).on('change', '#jprm-menu-select', function(){
    state.currentMenu = parseInt($(this).val(), 10) || null;
    loadSections();
  });

  $('#jprm-refresh').on('click', function(){ loadMenus().then(loadSections); });

  $('#jprm-add-section').on('click', function(){
    const title = $('#jprm-new-section-title').val().trim();
    if (!title) { alert('Please enter a section title.'); return; }
    setLoading(true);
    apiPost('menu-builder/section', { name: title, parent: 0 })
      .done(()=> { $('#jprm-new-section-title').val(''); loadSections(); })
      .fail((xhr)=> {
        alert('Could not create section: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'));
      })
      .always(()=> setLoading(false));
  });

  $('#jprm-save').on('click', function(){
    const tree = collectTree();
    setLoading(true);
    apiPost('menu-builder/sections/order', { tree })
      .done((res)=> { state.sections = res.sections || []; renderTree(); })
      .fail((xhr)=> {
        alert('Could not save order: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'));
      })
      .always(()=> setLoading(false));
  });

  // Boot
  $(function(){ loadMenus().then(loadSections); });
})(jQuery);
