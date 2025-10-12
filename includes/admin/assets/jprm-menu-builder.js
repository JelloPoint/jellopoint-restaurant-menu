(function($){
  const api = (path) => {
    return $.ajax({
      url: JPRM_MENU_BUILDER.root + '/' + path.replace(/^\//,''),
      method: 'GET',
      beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', JPRM_MENU_BUILDER.nonce)
    });
  };

  const state = { menus: [], sections: [], currentMenu: null };

  function loadMenus(){
    $('#jprm-loading').show();
    return api('menu-builder/menus').done((res)=>{
      state.menus = res.menus || [];
      const $sel = $('#jprm-menu-select').empty();
      state.menus.forEach(m => $sel.append($('<option>').val(m.id).text(m.title)));
      if (state.menus.length && !state.currentMenu) state.currentMenu = state.menus[0].id;
      if (state.currentMenu) $sel.val(state.currentMenu);
    }).always(()=>$('#jprm-loading').hide());
  }

  function loadSections(){
    if (!state.currentMenu) return;
    $('#jprm-loading').show();
    return api('menu-builder/sections?menu_id='+state.currentMenu).done((res)=>{
      state.sections = res.sections || [];
      renderTree();
    }).always(()=>$('#jprm-loading').hide());
  }

  function renderTree(){
    const $ul = $('#jprm-tree').empty();
    // Phase 1: flat list; Phase 2 will render nested + sortable
    state.sections.forEach(s=>{
      const $li = $('<li>').attr('data-id', s.id);
      const $row = $('<div class="jprm-node">')
        .append($('<span class="title">').text(s.title))
        .append($('<span class="meta">').text('#'+s.id));
      $li.append($row);
      $ul.append($li);
    });
    // Prepare for nesting in Phase 2
    $ul.sortable({ placeholder: 'ui-state-highlight' });
  }

  $(document).on('change', '#jprm-menu-select', function(){
    state.currentMenu = parseInt($(this).val(), 10);
    loadSections();
  });

  $('#jprm-refresh').on('click', function(){ loadMenus().then(loadSections); });

  // Boot
  $(function(){
    loadMenus().then(loadSections);
  });
})(jQuery);
