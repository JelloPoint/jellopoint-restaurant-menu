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

  function renderTree(){
    const $ul = $('#jprm-tree').empty();
    state.sections.forEach(s=>{
      const $li = $('<li>').attr('data-id', s.id);
      const $row = $('<div class="jprm-node">')
        .append($('<span class="title">').text(s.title))
        .append($('<span class="meta">').text('#'+s.id));
      $li.append($row);
      $ul.append($li);
    });
    $ul.sortable({ placeholder: 'ui-state-highlight' });
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
    apiPost('menu-builder/section', { name: title /*, parent: 0, menu_id: state.currentMenu */ })
      .done(()=> {
        $('#jprm-new-section-title').val('');
        loadSections();
      })
      .fail((xhr)=> {
        alert('Could not create section: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'));
      })
      .always(()=> setLoading(false));
  });

  // Boot
  $(function(){
    loadMenus().then(loadSections);
  });
})(jQuery);
